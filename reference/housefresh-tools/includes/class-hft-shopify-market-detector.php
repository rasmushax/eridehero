<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Detects which Shopify Markets are available for a store.
 *
 * Groups configured geos by currency, tests one representative per group,
 * and validates that the store returns the expected currency for each market.
 */
class HFT_Shopify_Market_Detector {

    /**
     * Detect available markets for a Shopify store.
     *
     * @param string      $test_url   A product URL on the store to test against.
     * @param string      $method     'cookie' or 'api'.
     * @param array       $geos       Array of country codes to test.
     * @param string|null $api_token  Storefront API token (required for 'api' method).
     * @param string|null $shop_domain The .myshopify.com domain (required for 'api' method).
     * @return array Array of market detection results.
     */
    public function detect(string $test_url, string $method, array $geos, ?string $api_token = null, ?string $shop_domain = null): array {
        $currency_groups = HFT_Shopify_Currencies::group_by_currency($geos);
        $results = [];

        foreach ($currency_groups as $expected_currency => $countries) {
            $representative = $countries[0];

            if ($method === 'api') {
                $result = $this->test_storefront_api($test_url, $representative, $expected_currency, $api_token, $shop_domain);
            } else {
                $result = $this->test_cookie_method($test_url, $representative, $expected_currency);
            }

            $result['countries'] = $countries;
            $result['representative'] = $representative;
            $result['expected_currency'] = $expected_currency;
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Test a geo using the cookie injection method.
     *
     * Fetches the product page with Shopify localization cookies and checks
     * if the response reflects the requested geo and currency.
     *
     * @param string $test_url          Product URL to fetch.
     * @param string $country_code      Country code for localization cookie.
     * @param string $expected_currency Expected currency code in response.
     * @return array Result with 'success', 'currency', 'price', and optionally 'error'.
     */
    private function test_cookie_method(string $test_url, string $country_code, string $expected_currency): array {
        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'currency' => null,
                'price' => null,
                'error' => __('cURL is not available on this server.', 'housefresh-tools'),
            ];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $test_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_COOKIE, "cart_currency={$expected_currency}; localization={$country_code}");
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno || $http_code < 200 || $http_code >= 300 || empty($response)) {
            return [
                'success' => false,
                'currency' => null,
                'price' => null,
                'error' => $errno ? "cURL error: {$error}" : "HTTP {$http_code}",
            ];
        }

        // Check Shopify.country in response
        $returned_country = null;
        if (preg_match('/Shopify\.country\s*=\s*"([^"]+)"/', $response, $m)) {
            $returned_country = strtoupper($m[1]);
        }

        // Check Shopify.currency.active in response
        $returned_currency = null;
        if (preg_match('/"active"\s*:\s*"([A-Z]{3})"/', $response, $m)) {
            $returned_currency = $m[1];
        }

        // Extract price from OG meta tag
        $returned_price = null;
        if (preg_match('/og:price:amount["\'][^>]*content=["\']([^"\']+)/', $response, $m)) {
            $returned_price = str_replace(',', '', $m[1]);
        }

        // Validate: the store must reflect the requested geo
        if ($returned_country !== $country_code) {
            return [
                'success' => false,
                'currency' => $returned_currency,
                'price' => $returned_price,
                'error' => sprintf(
                    __('Store returned country %s instead of %s — market not available.', 'housefresh-tools'),
                    $returned_country ?? 'unknown',
                    $country_code
                ),
            ];
        }

        if ($returned_currency !== $expected_currency) {
            return [
                'success' => false,
                'currency' => $returned_currency,
                'price' => $returned_price,
                'error' => sprintf(
                    __('Store returned %s instead of expected %s.', 'housefresh-tools'),
                    $returned_currency ?? 'unknown',
                    $expected_currency
                ),
            ];
        }

        return [
            'success' => true,
            'currency' => $returned_currency,
            'price' => $returned_price,
            'error' => null,
        ];
    }

    /**
     * Test a geo using the Storefront API method.
     *
     * Sends a GraphQL query with @inContext(country:) and checks the returned currency.
     *
     * @param string      $test_url          Product URL (used to extract handle).
     * @param string      $country_code      Country code for @inContext directive.
     * @param string      $expected_currency Expected currency code in response.
     * @param string|null $api_token         Storefront API access token.
     * @param string|null $shop_domain       The .myshopify.com domain.
     * @return array Result with 'success', 'currency', 'price', and optionally 'error'.
     */
    private function test_storefront_api(string $test_url, string $country_code, string $expected_currency, ?string $api_token, ?string $shop_domain): array {
        if (empty($api_token) || empty($shop_domain)) {
            return [
                'success' => false,
                'currency' => null,
                'price' => null,
                'error' => __('Storefront API token and shop domain are required.', 'housefresh-tools'),
            ];
        }

        // Extract product handle from URL
        $handle = self::extract_product_handle($test_url);
        if (!$handle) {
            return [
                'success' => false,
                'currency' => null,
                'price' => null,
                'error' => __('Could not extract product handle from URL. URL must contain /products/{handle}.', 'housefresh-tools'),
            ];
        }

        $graphql_url = 'https://' . rtrim($shop_domain, '/') . '/api/2024-01/graphql.json';
        $query = sprintf(
            'query @inContext(country: %s) { product(handle: "%s") { title priceRange { minVariantPrice { amount currencyCode } } variants(first: 1) { edges { node { price { amount currencyCode } availableForSale } } } } }',
            strtoupper($country_code),
            $handle
        );

        $response = wp_remote_post($graphql_url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Storefront-Access-Token' => $api_token,
            ],
            'body' => wp_json_encode(['query' => $query]),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'currency' => null,
                'price' => null,
                'error' => $response->get_error_message(),
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($http_code < 200 || $http_code >= 300) {
            return [
                'success' => false,
                'currency' => null,
                'price' => null,
                'error' => "HTTP {$http_code}",
            ];
        }

        // Check for GraphQL errors
        if (!empty($data['errors'])) {
            $error_msg = $data['errors'][0]['message'] ?? 'Unknown GraphQL error';
            return [
                'success' => false,
                'currency' => null,
                'price' => null,
                'error' => $error_msg,
            ];
        }

        // Extract price data
        $product = $data['data']['product'] ?? null;
        if (!$product) {
            return [
                'success' => false,
                'currency' => null,
                'price' => null,
                'error' => __('Product not found via Storefront API.', 'housefresh-tools'),
            ];
        }

        $min_price = $product['priceRange']['minVariantPrice'] ?? null;
        $returned_currency = $min_price['currencyCode'] ?? null;
        $returned_price = $min_price['amount'] ?? null;

        // Validate currency matches expected
        if ($returned_currency !== $expected_currency) {
            return [
                'success' => false,
                'currency' => $returned_currency,
                'price' => $returned_price,
                'error' => sprintf(
                    __('API returned %s instead of expected %s — market not available.', 'housefresh-tools'),
                    $returned_currency ?? 'unknown',
                    $expected_currency
                ),
            ];
        }

        return [
            'success' => true,
            'currency' => $returned_currency,
            'price' => $returned_price,
            'error' => null,
        ];
    }

    /**
     * Extract Shopify product handle from a URL.
     *
     * @param string $url Product URL.
     * @return string|null The product handle, or null if not found.
     */
    public static function extract_product_handle(string $url): ?string {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        // Match /products/{handle} in the path (handle may be followed by query string or nothing)
        if (preg_match('~/products/([^/?#]+)~', $path, $matches)) {
            $handle = $matches[1];
            // Sanitize: Shopify handles only contain alphanumeric, hyphens, and dots
            if (preg_match('/^[a-zA-Z0-9\-_.]+$/', $handle)) {
                return $handle;
            }
            return null;
        }

        return null;
    }
}
