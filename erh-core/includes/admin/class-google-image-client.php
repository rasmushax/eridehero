<?php
/**
 * Google Image Client - Wrapper for Google Custom Search Image API.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

/**
 * Handles communication with the Google Custom Search API for image results.
 */
class GoogleImageClient {

    /**
     * Google Custom Search API endpoint.
     */
    private const API_URL = 'https://www.googleapis.com/customsearch/v1';

    /**
     * Option name for API key.
     */
    private const OPTION_API_KEY = 'erh_google_api_key';

    /**
     * Option name for Custom Search Engine ID.
     */
    private const OPTION_CSE_ID = 'erh_google_search_engine_id';

    /**
     * Request timeout in seconds.
     */
    private const TIMEOUT = 15;

    /**
     * Get the API key from options.
     *
     * @return string The API key.
     */
    private function get_api_key(): string {
        return get_option(self::OPTION_API_KEY, '');
    }

    /**
     * Get the Custom Search Engine ID from options.
     *
     * @return string The CSE ID.
     */
    private function get_cse_id(): string {
        return get_option(self::OPTION_CSE_ID, '');
    }

    /**
     * Check if the API is configured.
     *
     * @return bool True if both API key and CSE ID are set.
     */
    public function is_configured(): bool {
        return !empty($this->get_api_key()) && !empty($this->get_cse_id());
    }

    /**
     * Search for product images.
     *
     * @param string $query Search query.
     * @param int    $num   Number of results (1-10).
     * @return array{success: bool, images?: array, error?: string}
     */
    public function search_images(string $query, int $num = 12): array {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'error'   => 'Google Search API not configured.',
            ];
        }

        $num = min(10, max(1, $num));

        $url = add_query_arg([
            'key'        => $this->get_api_key(),
            'cx'         => $this->get_cse_id(),
            'q'          => $query,
            'searchType' => 'image',
            'num'        => $num,
            'safe'       => 'active',
            'imgType'    => 'photo',
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
            $error = $body['error']['message'] ?? 'Unknown API error (HTTP ' . $code . ')';
            error_log('[ERH Image Populator] API error (HTTP ' . $code . '): ' . $error);
            error_log('[ERH Image Populator] Response body: ' . substr($raw_body, 0, 1000));
            return [
                'success' => false,
                'error'   => $error . ' (HTTP ' . $code . ')',
            ];
        }

        $items = $body['items'] ?? [];
        $images = [];

        foreach ($items as $item) {
            $image = $item['image'] ?? [];
            $images[] = [
                'url'       => $item['link'] ?? '',
                'thumbnail' => $image['thumbnailLink'] ?? '',
                'width'     => (int) ($image['width'] ?? 0),
                'height'    => (int) ($image['height'] ?? 0),
                'format'    => $this->detect_format($item['link'] ?? '', $item['mime'] ?? ''),
                'source'    => wp_parse_url($image['contextLink'] ?? '', PHP_URL_HOST) ?: '',
                'title'     => $item['title'] ?? '',
            ];
        }

        return [
            'success' => true,
            'images'  => $images,
        ];
    }

    /**
     * Detect image format from URL or MIME type.
     *
     * @param string $url  Image URL.
     * @param string $mime MIME type.
     * @return string Format label (JPG, PNG, WebP, AVIF, or unknown).
     */
    private function detect_format(string $url, string $mime): string {
        $mime_map = [
            'image/jpeg' => 'JPG',
            'image/png'  => 'PNG',
            'image/webp' => 'WebP',
            'image/avif' => 'AVIF',
            'image/gif'  => 'GIF',
        ];

        if (isset($mime_map[$mime])) {
            return $mime_map[$mime];
        }

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
