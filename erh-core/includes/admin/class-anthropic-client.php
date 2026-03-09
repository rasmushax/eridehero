<?php
/**
 * Anthropic Client - Wrapper for Claude API.
 *
 * Supports web search (tool_use) and extended thinking.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

/**
 * Handles communication with the Anthropic Claude API.
 */
class AnthropicClient {

    /**
     * Anthropic Messages API endpoint.
     */
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    /**
     * API version header.
     */
    private const API_VERSION = '2023-06-01';

    /**
     * Option name for API key.
     */
    private const OPTION_KEY = 'erh_anthropic_api_key';

    /**
     * Available models.
     *
     * @var array<string, string>
     */
    public const MODELS = [
        'claude-opus-4-6'   => 'Claude Opus 4.6',
        'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
    ];

    /**
     * Model to use for requests.
     *
     * @var string
     */
    private string $model;

    /**
     * Whether to enable extended thinking.
     *
     * @var bool
     */
    private bool $extended_thinking;

    /**
     * Constructor.
     *
     * @param string $model            Model ID (e.g., 'claude-sonnet-4-6').
     * @param bool   $extended_thinking Whether to enable extended thinking.
     */
    public function __construct(string $model = 'claude-sonnet-4-6', bool $extended_thinking = false) {
        $this->model = $model;
        $this->extended_thinking = $extended_thinking;
    }

    /**
     * Get the API key from options.
     *
     * @return string The API key.
     */
    private function get_api_key(): string {
        return get_option(self::OPTION_KEY, '');
    }

    /**
     * Check if the API is configured.
     *
     * @return bool True if API key is set.
     */
    public function is_configured(): bool {
        return !empty($this->get_api_key());
    }

    /**
     * Send a request to the Claude API.
     *
     * Returns the same structure as PerplexityClient::send_request()
     * for easy swapping between providers.
     *
     * @param string $system_prompt System prompt for Claude.
     * @param string $user_prompt   User prompt with the actual request.
     * @param int    $max_tokens    Maximum tokens in the response.
     * @param float  $temperature   Temperature for response randomness.
     * @param int    $timeout       Request timeout in seconds.
     * @return array{success: bool, content: string|null, error: string|null}
     */
    public function send_request(
        string $system_prompt,
        string $user_prompt,
        int $max_tokens = 8000,
        float $temperature = 0.1,
        int $timeout = 120
    ): array {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'content' => null,
                'error'   => 'Anthropic API key not configured.',
            ];
        }

        $body = $this->build_request_body($system_prompt, $user_prompt, $max_tokens, $temperature);

        error_log('[ERH AnthropicClient] Sending request to ' . $this->model
            . ' (thinking=' . ($this->extended_thinking ? 'on' : 'off') . ')');

        $response = wp_remote_post(self::API_URL, [
            'timeout' => $timeout,
            'headers' => $this->build_headers(),
            'body'    => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'content' => null,
                'error'   => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $data = json_decode($raw_body, true);

        if ($code === 429) {
            return [
                'success' => false,
                'content' => null,
                'error'   => 'Rate limit exceeded. Please wait before retrying.',
            ];
        }

        if ($code !== 200) {
            $error = $data['error']['message'] ?? ('Unknown API error (HTTP ' . $code . ')');
            error_log('[ERH AnthropicClient] API error: ' . $error);
            return [
                'success' => false,
                'content' => null,
                'error'   => $error,
            ];
        }

        // Extract text content from the response content blocks.
        $content = $this->extract_text_content($data);

        if ($content === null) {
            error_log('[ERH AnthropicClient] No text content in response: ' . substr($raw_body, 0, 2000));
            return [
                'success' => false,
                'content' => null,
                'error'   => 'No text content in API response.',
            ];
        }

        error_log('[ERH AnthropicClient] Response usage: '
            . ($data['usage']['input_tokens'] ?? '?') . ' in / '
            . ($data['usage']['output_tokens'] ?? '?') . ' out');

        return [
            'success' => true,
            'content' => $content,
            'error'   => null,
        ];
    }

    /**
     * Build the request body for the Claude API.
     *
     * @param string $system_prompt System prompt.
     * @param string $user_prompt   User prompt.
     * @param int    $max_tokens    Max output tokens.
     * @param float  $temperature   Temperature.
     * @return array Request body array.
     */
    private function build_request_body(
        string $system_prompt,
        string $user_prompt,
        int $max_tokens,
        float $temperature
    ): array {
        $body = [
            'model'      => $this->model,
            'max_tokens' => $max_tokens,
            'system'     => $system_prompt,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $user_prompt,
                ],
            ],
            'tools' => [
                [
                    'type' => 'web_search_20250305',
                    'name' => 'web_search',
                ],
            ],
        ];

        if ($this->extended_thinking) {
            // Extended thinking: budget must be >= 1024 and < max_tokens.
            $budget = min(10000, $max_tokens - 1024);
            $budget = max(1024, $budget);
            $budget = min($budget, $max_tokens - 1);

            $body['thinking'] = [
                'type'          => 'enabled',
                'budget_tokens' => $budget,
            ];
            // Temperature must not be set when thinking is enabled.
        } else {
            $body['temperature'] = $temperature;
        }

        return $body;
    }

    /**
     * Build the HTTP headers for the API request.
     *
     * @return array<string, string> Headers array.
     */
    private function build_headers(): array {
        return [
            'x-api-key'         => $this->get_api_key(),
            'anthropic-version'  => self::API_VERSION,
            'content-type'       => 'application/json',
        ];
    }

    /**
     * Extract text content from Claude API response.
     *
     * Claude returns an array of content blocks. We extract all 'text' type
     * blocks and concatenate them. Thinking blocks and tool-use blocks are
     * skipped since we only need the final answer.
     *
     * @param array $data Decoded API response.
     * @return string|null Extracted text or null if none found.
     */
    private function extract_text_content(array $data): ?string {
        $content_blocks = $data['content'] ?? [];

        if (empty($content_blocks) || !is_array($content_blocks)) {
            return null;
        }

        $text_parts = [];

        foreach ($content_blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            // Only extract text blocks (skip thinking, tool_use, web_search_tool_result, etc.).
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $text_parts[] = $block['text'];
            }
        }

        if (empty($text_parts)) {
            return null;
        }

        return implode("\n", $text_parts);
    }
}
