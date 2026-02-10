<?php
/**
 * Amazon API Client - Uses Creators API SearchItems to find products.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\Amazon\AmazonLocales;

/**
 * Handles Amazon Creators API SearchItems requests.
 */
class AmazonApiClient {

    /**
     * Creators API endpoint.
     */
    private const API_ENDPOINT = 'https://creatorsapi.amazon/catalog/v1/searchItems';

    /**
     * Request timeout in seconds.
     */
    private const TIMEOUT = 15;

    /**
     * Token cache duration in seconds (55 minutes — tokens last 1 hour).
     */
    private const TOKEN_TTL = 3300;

    /**
     * Resources to request from Creators API for product verification.
     */
    private const SEARCH_RESOURCES = [
        'itemInfo.title',
        'itemInfo.byLineInfo',
        'images.primary.small',
        'offersV2.listings.price',
        'offersV2.listings.availability',
        'offersV2.listings.merchantInfo',
    ];

    /**
     * HFT settings cache.
     *
     * @var array|null
     */
    private ?array $settings = null;

    /**
     * Check if Amazon API is available and configured.
     *
     * @return bool True if API is ready to use.
     */
    public function is_configured(): bool {
        $settings = $this->get_settings();
        $credentials = $settings['amazon_credentials'] ?? [];
        $tags = !empty($settings['amazon_associate_tags']);

        // Need at least one region group with credentials.
        $has_credentials = false;
        foreach (['NA', 'EU', 'FE'] as $group) {
            if (!empty($credentials[$group]['credential_id']) && !empty($credentials[$group]['credential_secret'])) {
                $has_credentials = true;
                break;
            }
        }

        return $has_credentials && $tags;
    }

    /**
     * Get HFT settings.
     *
     * @return array Settings array.
     */
    private function get_settings(): array {
        if ($this->settings === null) {
            $this->settings = get_option('hft_settings', []);
        }
        return $this->settings;
    }

    /**
     * Get available Amazon locales.
     *
     * @return array Locale code => retail domain mapping.
     */
    public function get_available_locales(): array {
        $locales = AmazonLocales::get_all_locales();
        $result = [];

        foreach ($locales as $code => $data) {
            // Skip UK alias (use GB).
            if ($code === 'UK') {
                continue;
            }
            $result[$code] = $data[1]; // Retail host.
        }

        return $result;
    }

    /**
     * Search Amazon for a product and return the best matching ASIN.
     *
     * @param string $keywords The product name to search for.
     * @param string $locale The Amazon locale (US, GB, DE, etc.).
     * @return array{success: bool, asin: string|null, title: string|null, url: string|null, error: string|null}
     */
    public function search_product(string $keywords, string $locale = 'US'): array {
        $default_response = [
            'success' => false,
            'asin'    => null,
            'title'   => null,
            'url'     => null,
            'error'   => null,
        ];

        if (!$this->is_configured()) {
            $default_response['error'] = 'Amazon Creators API not configured. Check HFT settings.';
            return $default_response;
        }

        $locale = strtoupper($locale);

        if (!AmazonLocales::is_valid_geo($locale)) {
            $default_response['error'] = "Invalid locale: {$locale}";
            return $default_response;
        }

        // Get region group for this locale.
        $region_group = AmazonLocales::get_region_group($locale);
        if (!$region_group) {
            $default_response['error'] = "No region group mapping for locale: {$locale}";
            return $default_response;
        }

        // Get credentials for this region group.
        $settings = $this->get_settings();
        $credentials = $settings['amazon_credentials'][$region_group] ?? [];

        if (empty($credentials['credential_id']) || empty($credentials['credential_secret'])) {
            $default_response['error'] = "No Creators API credentials configured for region group: {$region_group}";
            return $default_response;
        }

        $associate_tag = $this->get_associate_tag_for_locale($locale);
        if (!$associate_tag) {
            $default_response['error'] = "No associate tag configured for locale: {$locale}";
            return $default_response;
        }

        // Get bearer token.
        $token = $this->get_bearer_token($region_group);
        if (!$token) {
            $default_response['error'] = "Failed to obtain OAuth token for region group: {$region_group}";
            return $default_response;
        }

        // Get locale details.
        $marketplace = AmazonLocales::get_marketplace_name($locale);
        $retail_host = AmazonLocales::get_retail_host($locale);
        $version = AmazonLocales::get_credential_version($region_group);

        if (!$marketplace) {
            $default_response['error'] = "Could not determine Amazon marketplace for locale: {$locale}";
            return $default_response;
        }

        // Build request payload (camelCase for Creators API).
        $payload_array = [
            'keywords'    => $keywords,
            'resources'   => self::SEARCH_RESOURCES,
            'partnerTag'  => $associate_tag,
            'partnerType' => 'Associates',
            'marketplace' => $marketplace,
            'itemCount'   => 3, // Get top 3 results.
        ];

        $payload = wp_json_encode($payload_array);
        if (false === $payload) {
            $default_response['error'] = 'Failed to encode API request payload.';
            return $default_response;
        }

        // Make the API request with OAuth bearer token.
        // Authorization combines token + version per Creators API spec.
        $response = wp_remote_post(self::API_ENDPOINT, [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Authorization' => "Bearer {$token}, Version {$version}",
                'Content-Type'  => 'application/json; charset=utf-8',
                'host'          => 'creatorsapi.amazon',
                'x-marketplace' => $marketplace,
            ],
            'body' => $payload,
        ]);

        if (is_wp_error($response)) {
            $default_response['error'] = 'Request failed: ' . $response->get_error_message();
            return $default_response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $parsed = json_decode($body, true);

        // Handle rate limiting.
        if ($status_code === 429) {
            $default_response['error'] = 'Amazon API rate limit exceeded. Wait 1 second between requests.';
            return $default_response;
        }

        // Handle errors.
        if ($status_code >= 400 || isset($parsed['errors'])) {
            $error_msg = "Amazon API error (HTTP {$status_code})";
            if (isset($parsed['errors'][0])) {
                $error_msg .= ': ' . ($parsed['errors'][0]['code'] ?? '') . ' - ' . ($parsed['errors'][0]['message'] ?? '');
            }
            $default_response['error'] = $error_msg;
            return $default_response;
        }

        // Parse results (camelCase response).
        $items = $parsed['searchResult']['items'] ?? [];

        if (empty($items)) {
            $default_response['success'] = true;
            $default_response['error'] = 'No products found for this search.';
            return $default_response;
        }

        // Return the first (best matching) item.
        $item = $items[0];
        $asin = $item['asin'] ?? null;

        if (!$asin) {
            $default_response['success'] = true;
            $default_response['error'] = 'Could not extract ASIN from response.';
            return $default_response;
        }

        // Extract product details for verification.
        $title = $item['itemInfo']['title']['displayValue'] ?? null;
        $brand = $item['itemInfo']['byLineInfo']['brand']['displayValue'] ?? null;
        $image = $item['images']['primary']['small']['url'] ?? null;

        // Extract offer/price details (offersV2 in Creators API).
        $listing = $item['offersV2']['listings'][0] ?? null;
        $price = null;
        $price_display = null;
        $availability = null;
        $merchant = null;

        if ($listing) {
            $price = $listing['price']['amount'] ?? null;
            $price_display = $listing['price']['displayAmount'] ?? null;
            $availability = $listing['availability']['message'] ?? null;
            $merchant = $listing['merchantInfo']['name'] ?? null;
        }

        return [
            'success'       => true,
            'asin'          => $asin,
            'title'         => $title,
            'brand'         => $brand,
            'image'         => $image,
            'price'         => $price,
            'price_display' => $price_display,
            'merchant'      => $merchant,
            'availability'  => $availability,
            'url'           => "https://{$retail_host}/dp/{$asin}",
            'error'         => null,
        ];
    }

    /**
     * Get the associate tag for a locale.
     *
     * @param string $locale The locale code.
     * @return string|null The associate tag or null.
     */
    private function get_associate_tag_for_locale(string $locale): ?string {
        $settings = $this->get_settings();
        $tags_by_geo = $settings['amazon_associate_tags'] ?? [];
        $locale_upper = strtoupper($locale);
        $global_tag = null;

        foreach ($tags_by_geo as $tag_config) {
            $config_geo = isset($tag_config['geo']) && is_string($tag_config['geo'])
                ? strtoupper(trim($tag_config['geo']))
                : null;
            $tag_value = isset($tag_config['tag']) ? trim($tag_config['tag']) : null;

            if ($config_geo === $locale_upper && !empty($tag_value)) {
                return $tag_value;
            }

            if (($config_geo === 'GLOBAL' || empty($config_geo)) && !empty($tag_value)) {
                $global_tag = $tag_value;
            }
        }

        return $global_tag;
    }

    /**
     * Get configuration error message.
     *
     * @return string|null Error message or null if configured.
     */
    public function get_configuration_error(): ?string {
        if (!defined('HFT_VERSION')) {
            return 'HFT plugin is not active (needed for Amazon API credentials).';
        }

        $settings = $this->get_settings();
        $credentials = $settings['amazon_credentials'] ?? [];

        $has_any = false;
        foreach (['NA', 'EU', 'FE'] as $group) {
            if (!empty($credentials[$group]['credential_id']) && !empty($credentials[$group]['credential_secret'])) {
                $has_any = true;
                break;
            }
        }

        if (!$has_any) {
            return 'Amazon Creators API credentials not configured in HFT settings. Need credential_id and credential_secret for at least one region group (NA/EU/FE).';
        }

        if (empty($settings['amazon_associate_tags'])) {
            return 'Amazon Associate Tags not configured in HFT settings.';
        }

        return null;
    }

    /**
     * Fetch and cache an OAuth 2.0 bearer token for a region group.
     *
     * @param string $region_group Region group (NA/EU/FE).
     * @return string|null Bearer token or null on failure.
     */
    private function get_bearer_token(string $region_group): ?string {
        $cache_key = 'erh_creators_token_' . strtoupper($region_group);

        // Check cache first.
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Get credentials and endpoint.
        $settings = $this->get_settings();
        $credentials = $settings['amazon_credentials'][$region_group] ?? [];
        $credential_id = $credentials['credential_id'] ?? '';
        $credential_secret = $credentials['credential_secret'] ?? '';

        if (empty($credential_id) || empty($credential_secret)) {
            return null;
        }

        $token_endpoint = AmazonLocales::get_token_endpoint($region_group);
        if (!$token_endpoint) {
            return null;
        }

        // Request token via client_credentials grant.
        $response = wp_remote_post($token_endpoint, [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $credential_id,
                'client_secret' => $credential_secret,
                'scope'         => 'creatorsapi/default',
            ]),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $token = $body['access_token'] ?? null;

        if (!$token) {
            return null;
        }

        // Cache for 55 minutes (tokens are valid for 1 hour).
        set_transient($cache_key, $token, self::TOKEN_TTL);

        return $token;
    }
}
