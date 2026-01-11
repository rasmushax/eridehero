<?php
/**
 * Amazon API Client - Uses PA-API 5.0 SearchItems to find products.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\Amazon\AmazonLocales;
use ERH\Amazon\AwsV4Signer;

/**
 * Handles Amazon PA-API 5.0 SearchItems requests.
 */
class AmazonApiClient {

    /**
     * Service name for PA-API.
     */
    private const SERVICE_NAME = 'ProductAdvertisingAPI';

    /**
     * Request timeout in seconds.
     */
    private const TIMEOUT = 15;

    /**
     * Minimum resources needed to get ASIN and title.
     */
    private const SEARCH_RESOURCES = [
        'ItemInfo.Title',
        'ItemInfo.ByLineInfo',
        'Offers.Listings.Price',
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
        $api_key = !empty($settings['amazon_api_key']);
        $api_secret = !empty($settings['amazon_api_secret']);
        $tags = !empty($settings['amazon_associate_tags']);

        return $api_key && $api_secret && $tags;
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
            $default_response['error'] = 'Amazon PA-API not configured. Check HFT settings.';
            return $default_response;
        }

        $locale = strtoupper($locale);

        if (!AmazonLocales::is_valid_geo($locale)) {
            $default_response['error'] = "Invalid locale: {$locale}";
            return $default_response;
        }

        $settings = $this->get_settings();
        $api_key = trim($settings['amazon_api_key']);
        $api_secret = trim($settings['amazon_api_secret']);
        $associate_tag = $this->get_associate_tag_for_locale($locale);

        if (!$associate_tag) {
            $default_response['error'] = "No associate tag configured for locale: {$locale}";
            return $default_response;
        }

        // Get locale details.
        $api_host = AmazonLocales::get_api_host($locale);
        $aws_region = AmazonLocales::get_region($locale);
        $marketplace = AmazonLocales::get_marketplace_name($locale);
        $retail_host = AmazonLocales::get_retail_host($locale);

        if (!$api_host || !$aws_region || !$marketplace) {
            $default_response['error'] = "Could not determine Amazon marketplace for locale: {$locale}";
            return $default_response;
        }

        // Build request payload.
        $payload_array = [
            'Keywords'    => $keywords,
            'Resources'   => self::SEARCH_RESOURCES,
            'PartnerTag'  => $associate_tag,
            'PartnerType' => 'Associates',
            'Marketplace' => $marketplace,
            'ItemCount'   => 3, // Get top 3 results.
        ];

        $payload = wp_json_encode($payload_array);
        if (false === $payload) {
            $default_response['error'] = 'Failed to encode API request payload.';
            return $default_response;
        }

        // Sign the request.
        $api_path = '/paapi5/searchitems';
        $target_header = 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.SearchItems';

        try {
            $signer = new AwsV4Signer($api_key, $api_secret);
            $signer->setRegionName($aws_region);
            $signer->setServiceName(self::SERVICE_NAME);
            $signer->setPath($api_path);
            $signer->setPayload($payload);
            $signer->setRequestMethod('POST');

            $signer->addHeader('host', $api_host);
            $signer->addHeader('content-encoding', 'amz-1.0');
            $signer->addHeader('content-type', 'application/json; charset=utf-8');
            $signer->addHeader('x-amz-target', $target_header);

            $headers = $signer->getHeaders();
        } catch (\Exception $e) {
            $default_response['error'] = 'AWS signing error: ' . $e->getMessage();
            return $default_response;
        }

        // Make the API request.
        $api_url = "https://{$api_host}{$api_path}";

        $response = wp_remote_post($api_url, [
            'timeout' => self::TIMEOUT,
            'headers' => $headers,
            'body'    => $payload,
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
        if ($status_code >= 400 || isset($parsed['Errors'])) {
            $error_msg = "Amazon API error (HTTP {$status_code})";
            if (isset($parsed['Errors'][0])) {
                $error_msg .= ': ' . ($parsed['Errors'][0]['Code'] ?? '') . ' - ' . ($parsed['Errors'][0]['Message'] ?? '');
            }
            $default_response['error'] = $error_msg;
            return $default_response;
        }

        // Parse results.
        $items = $parsed['SearchResult']['Items'] ?? [];

        if (empty($items)) {
            $default_response['success'] = true;
            $default_response['error'] = 'No products found for this search.';
            return $default_response;
        }

        // Return the first (best matching) item.
        $item = $items[0];
        $asin = $item['ASIN'] ?? null;
        $title = $item['ItemInfo']['Title']['DisplayValue'] ?? null;

        if (!$asin) {
            $default_response['success'] = true;
            $default_response['error'] = 'Could not extract ASIN from response.';
            return $default_response;
        }

        return [
            'success' => true,
            'asin'    => $asin,
            'title'   => $title,
            'url'     => "https://{$retail_host}/dp/{$asin}",
            'error'   => null,
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

        if (empty($settings['amazon_api_key'])) {
            return 'Amazon API Key not configured in HFT settings.';
        }

        if (empty($settings['amazon_api_secret'])) {
            return 'Amazon API Secret not configured in HFT settings.';
        }

        if (empty($settings['amazon_associate_tags'])) {
            return 'Amazon Associate Tags not configured in HFT settings.';
        }

        return null;
    }
}
