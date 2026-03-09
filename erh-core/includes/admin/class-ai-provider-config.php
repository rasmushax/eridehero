<?php
/**
 * AI Provider Config - Manages provider/model selection for AI-powered features.
 *
 * Reads default settings from WP options and supports per-request overrides.
 * Returns the appropriate client (PerplexityClient or AnthropicClient).
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

/**
 * Configuration layer for AI provider selection.
 */
class AiProviderConfig {

    /**
     * Valid provider keys.
     */
    public const PROVIDERS = ['perplexity', 'anthropic'];

    /**
     * Provider labels for display.
     *
     * @var array<string, string>
     */
    public const PROVIDER_LABELS = [
        'perplexity' => 'Perplexity',
        'anthropic'  => 'Anthropic',
    ];

    /**
     * Models per provider.
     *
     * @var array<string, array<string, string>>
     */
    public const PROVIDER_MODELS = [
        'perplexity' => [
            'sonar-pro' => 'Sonar Pro',
        ],
        'anthropic' => [
            'claude-opus-4-6'   => 'Claude Opus 4.6',
            'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
        ],
    ];

    /**
     * Get the AI client based on saved settings with optional overrides.
     *
     * @param array{provider?: string, model?: string, extended_thinking?: bool} $overrides Per-request overrides.
     * @return PerplexityClient|AnthropicClient The configured client.
     */
    public static function get_client(array $overrides = []): PerplexityClient|AnthropicClient {
        $provider = $overrides['provider'] ?? get_option('erh_ai_provider', 'perplexity');

        // Validate provider.
        if (!in_array($provider, self::PROVIDERS, true)) {
            $provider = 'perplexity';
        }

        if ($provider === 'anthropic') {
            $model = $overrides['model'] ?? get_option('erh_ai_model', 'claude-sonnet-4-6');
            $thinking = $overrides['extended_thinking'] ?? (bool) get_option('erh_ai_extended_thinking', '0');

            // Validate model belongs to Anthropic — fall back to Sonnet if not.
            if (!isset(self::PROVIDER_MODELS['anthropic'][$model])) {
                $model = 'claude-sonnet-4-6';
            }

            return new AnthropicClient($model, $thinking);
        }

        return new PerplexityClient();
    }

    /**
     * Check if the given (or default) provider is configured.
     *
     * @param string|null $provider Provider key, or null to use saved default.
     * @return bool True if the provider's API key is set.
     */
    public static function is_configured(?string $provider = null): bool {
        $provider = $provider ?? get_option('erh_ai_provider', 'perplexity');

        if ($provider === 'anthropic') {
            return !empty(get_option('erh_anthropic_api_key', ''));
        }

        return !empty(get_option('erh_perplexity_api_key', ''));
    }

    /**
     * Check if ANY provider is configured (for general availability checks).
     *
     * @return bool True if at least one provider has an API key.
     */
    public static function any_configured(): bool {
        return !empty(get_option('erh_perplexity_api_key', ''))
            || !empty(get_option('erh_anthropic_api_key', ''));
    }

    /**
     * Get the full config for JavaScript localization.
     *
     * Used by the spec populator UI to render provider/model dropdowns
     * and set defaults.
     *
     * @return array Config data for JS.
     */
    public static function get_js_config(): array {
        return [
            'provider'         => get_option('erh_ai_provider', 'perplexity'),
            'model'            => get_option('erh_ai_model', 'sonar-pro'),
            'extendedThinking' => (bool) get_option('erh_ai_extended_thinking', '0'),
            'providers'        => [
                'perplexity' => [
                    'label'      => 'Perplexity',
                    'configured' => !empty(get_option('erh_perplexity_api_key', '')),
                    'models'     => self::PROVIDER_MODELS['perplexity'],
                    'supportsThinking' => false,
                ],
                'anthropic' => [
                    'label'      => 'Anthropic',
                    'configured' => !empty(get_option('erh_anthropic_api_key', '')),
                    'models'     => self::PROVIDER_MODELS['anthropic'],
                    'supportsThinking' => true,
                ],
            ],
        ];
    }
}
