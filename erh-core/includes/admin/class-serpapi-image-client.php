<?php
/**
 * SerpAPI Image Client - Wrapper for SerpAPI Google Images endpoint.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

/**
 * Handles communication with SerpAPI for Google Image search results.
 */
class SerpApiImageClient {

    /**
     * SerpAPI endpoint.
     */
    private const API_URL = 'https://serpapi.com/search.json';

    /**
     * Option name for API key.
     */
    private const OPTION_API_KEY = 'erh_serpapi_key';

    /**
     * Request timeout in seconds.
     */
    private const TIMEOUT = 20;

    /**
     * Get the API key from options.
     *
     * @return string The API key.
     */
    private function get_api_key(): string {
        return get_option(self::OPTION_API_KEY, '');
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
     * Search for product images.
     *
     * @param string $query Search query.
     * @param int    $num   Max number of results to return.
     * @return array{success: bool, images?: array, error?: string}
     */
    public function search_images(string $query, int $num = 10): array {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'error'   => 'SerpAPI key not configured. Go to Settings > ERideHero > APIs.',
            ];
        }

        $url = add_query_arg([
            'engine'  => 'google_images',
            'q'       => $query,
            'api_key' => $this->get_api_key(),
            'safe'    => 'active',
            'ijn'     => '0',
        ], self::API_URL);

        $response = wp_remote_get($url, [
            'timeout' => self::TIMEOUT,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        if ($code !== 200) {
            $error = $body['error'] ?? 'Unknown API error (HTTP ' . $code . ')';
            error_log('[ERH Image Populator] SerpAPI error (HTTP ' . $code . '): ' . $error);
            return [
                'success' => false,
                'error'   => $error,
            ];
        }

        $items = $body['images_results'] ?? [];
        $images = [];

        foreach (array_slice($items, 0, $num) as $item) {
            $images[] = [
                'url'       => $item['original'] ?? '',
                'thumbnail' => $item['thumbnail'] ?? '',
                'width'     => (int) ($item['original_width'] ?? 0),
                'height'    => (int) ($item['original_height'] ?? 0),
                'format'    => $this->detect_format($item['original'] ?? ''),
                'source'    => $item['source'] ?? '',
                'title'     => $item['title'] ?? '',
            ];
        }

        if (empty($images)) {
            return [
                'success' => true,
                'images'  => [],
            ];
        }

        return [
            'success' => true,
            'images'  => $images,
        ];
    }

    /**
     * Detect image format from URL.
     *
     * @param string $url Image URL.
     * @return string Format label (JPG, PNG, WebP, AVIF, GIF).
     */
    private function detect_format(string $url): string {
        $ext = strtolower(pathinfo(wp_parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        $ext_map = [
            'jpg'  => 'JPG',
            'jpeg' => 'JPG',
            'png'  => 'PNG',
            'webp' => 'WebP',
            'avif' => 'AVIF',
            'gif'  => 'GIF',
        ];

        return $ext_map[$ext] ?? 'JPG';
    }
}
