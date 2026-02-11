<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shopify Storefront API parser
 *
 * Uses the Shopify Storefront API (GraphQL) to fetch product price, currency,
 * and availability data. Supports geo-targeted pricing via the @inContext directive.
 */
class HFT_Shopify_Storefront_Parser implements HFT_ParserInterface {

    private HFT_Scraper $scraper;
    private ?float $parse_start_time = null;
    private ?string $current_parse_url = null;
    private ?int $current_tracked_link_id = null;

    /**
     * Constructor
     *
     * @param HFT_Scraper $scraper The scraper configuration
     */
    public function __construct(HFT_Scraper $scraper) {
        $this->scraper = $scraper;
    }

    /**
     * {@inheritdoc}
     */
    public function parse(string $url_or_identifier, array $link_meta = []): array {
        $this->parse_start_time = microtime(true);
        $this->current_parse_url = $url_or_identifier;
        $this->current_tracked_link_id = $link_meta['tracked_link_id'] ?? null;

        $result = [
            'price'         => null,
            'currency'      => null,
            'status'        => null,
            'shipping_info' => null,
            'error'         => null,
        ];

        // Validate scraper configuration
        if (empty($this->scraper->shopify_shop_domain)) {
            $result['error'] = __('Shopify shop domain is not configured.', 'housefresh-tools');
            $this->logParseResult($result, false, $url_or_identifier);
            return $result;
        }

        if (empty($this->scraper->shopify_storefront_token)) {
            $result['error'] = __('Shopify Storefront Access Token is not configured.', 'housefresh-tools');
            $this->logParseResult($result, false, $url_or_identifier);
            return $result;
        }

        // Extract product handle from URL
        $handle = $this->extractProductHandle($url_or_identifier);
        if ($handle === null) {
            $result['error'] = sprintf(
                __('Could not extract product handle from URL: %s', 'housefresh-tools'),
                $url_or_identifier
            );
            $this->logParseResult($result, false, $url_or_identifier);
            return $result;
        }

        // Determine geo target for @inContext directive
        $geo = $this->determineScrapeGeo($link_meta);

        // Build GraphQL query
        $query = $this->buildGraphQLQuery($handle, $geo);

        // Make API request
        $api_url = sprintf(
            'https://%s/api/2024-01/graphql.json',
            $this->scraper->shopify_shop_domain
        );

        $response = wp_remote_post($api_url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type'                      => 'application/json',
                'X-Shopify-Storefront-Access-Token'  => $this->scraper->shopify_storefront_token,
            ],
            'body' => wp_json_encode(['query' => $query]),
        ]);

        if (is_wp_error($response)) {
            $result['error'] = __('Storefront API request failed: ', 'housefresh-tools') . $response->get_error_message();
            if (defined('WP_DEBUG') && WP_DEBUG === true) {
                error_log('[HFT Scraper] Shopify Storefront API error for ' . $url_or_identifier . ': ' . $result['error']);
            }
            $this->logParseResult($result, false, $url_or_identifier);
            return $result;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code < 200 || $http_code >= 300) {
            $result['error'] = sprintf(
                __('Storefront API returned HTTP %d.', 'housefresh-tools'),
                $http_code
            );
            if (defined('WP_DEBUG') && WP_DEBUG === true) {
                error_log('[HFT Scraper] Shopify Storefront API HTTP error for ' . $url_or_identifier . ': ' . $result['error']);
            }
            $this->logParseResult($result, false, $url_or_identifier);
            return $result;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data === null) {
            $result['error'] = __('Failed to decode Storefront API response.', 'housefresh-tools');
            $this->logParseResult($result, false, $url_or_identifier);
            return $result;
        }

        // Check for GraphQL errors
        if (!empty($data['errors'])) {
            $error_messages = [];
            foreach ($data['errors'] as $error) {
                $error_messages[] = $error['message'] ?? 'Unknown GraphQL error';
            }
            $result['error'] = __('Storefront API GraphQL errors: ', 'housefresh-tools') . implode('; ', $error_messages);
            if (defined('WP_DEBUG') && WP_DEBUG === true) {
                error_log('[HFT Scraper] Shopify Storefront GraphQL errors for ' . $url_or_identifier . ': ' . $result['error']);
            }
            $this->logParseResult($result, false, $url_or_identifier);
            return $result;
        }

        // Check for product data
        $product = $data['data']['product'] ?? null;
        if ($product === null) {
            $result['error'] = sprintf(
                __('Product not found for handle: %s', 'housefresh-tools'),
                $handle
            );
            $this->logParseResult($result, false, $url_or_identifier);
            return $result;
        }

        // Extract price and currency from variant data (preferred) or priceRange fallback
        $variant_node = $data['data']['product']['variants']['edges'][0]['node'] ?? null;

        if ($variant_node !== null) {
            $price_amount = $variant_node['price']['amount'] ?? null;
            $currency_code = $variant_node['price']['currencyCode'] ?? null;

            if ($price_amount !== null) {
                $result['price'] = (float) $price_amount;
            }
            if ($currency_code !== null) {
                $result['currency'] = $currency_code;
            }

            // Map availability
            if (isset($variant_node['availableForSale'])) {
                $result['status'] = $variant_node['availableForSale'] ? 'In Stock' : 'Out of Stock';
            }
        } else {
            // Fallback to priceRange
            $min_price = $product['priceRange']['minVariantPrice'] ?? null;
            if ($min_price !== null) {
                if (isset($min_price['amount'])) {
                    $result['price'] = (float) $min_price['amount'];
                }
                if (isset($min_price['currencyCode'])) {
                    $result['currency'] = $min_price['currencyCode'];
                }
            }
        }

        // Set error if no price found
        if ($result['price'] === null && $result['error'] === null) {
            $result['error'] = __('Could not extract product price from Storefront API response.', 'housefresh-tools');
        }

        $this->logParseResult($result, $result['error'] === null, $url_or_identifier);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function get_source_type_slug(): string {
        return 'scraper_' . $this->scraper->id;
    }

    /**
     * {@inheritdoc}
     */
    public function get_source_type_label(): string {
        return $this->scraper->name;
    }

    /**
     * {@inheritdoc}
     */
    public function get_identifier_label(): string {
        return __('Product URL', 'housefresh-tools');
    }

    /**
     * {@inheritdoc}
     */
    public function get_identifier_placeholder(): string {
        return sprintf(
            __('e.g., https://%s/products/...', 'housefresh-tools'),
            $this->scraper->shopify_shop_domain ?? $this->scraper->domain
        );
    }

    /**
     * {@inheritdoc}
     */
    public function is_identifier_asin(): bool {
        return false;
    }

    /**
     * Extract the product handle from a Shopify product URL.
     *
     * @param string $url The product URL.
     * @return string|null The product handle, or null if not found.
     */
    private function extractProductHandle(string $url): ?string {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === false) {
            return null;
        }

        // Match /products/{handle} in the path
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

    /**
     * Determine the country code for geo-targeted pricing.
     *
     * @param array $link_meta The link metadata.
     * @return string|null Two-letter country code, or null if not available.
     */
    private function determineScrapeGeo(array $link_meta): ?string {
        $geo_target = $link_meta['geo_target'] ?? null;
        if (empty($geo_target)) {
            return null;
        }

        // Take the first country code if comma-separated
        $geos = explode(',', $geo_target);
        $first_geo = trim($geos[0]);

        return !empty($first_geo) ? strtoupper($first_geo) : null;
    }

    /**
     * Build the GraphQL query for the Storefront API.
     *
     * @param string $handle The product handle.
     * @param string|null $geo The country code for @inContext, or null.
     * @return string The GraphQL query string.
     */
    private function buildGraphQLQuery(string $handle, ?string $geo): string {
        $context_directive = '';
        if ($geo !== null) {
            $context_directive = sprintf('@inContext(country: %s)', $geo);
        }

        return sprintf(
            'query %s { product(handle: "%s") { title priceRange { minVariantPrice { amount currencyCode } } variants(first: 1) { edges { node { price { amount currencyCode } compareAtPrice { amount currencyCode } availableForSale } } } } }',
            $context_directive,
            $handle
        );
    }

    /**
     * Log parse result to hft_scraper_logs table.
     *
     * @param array  $result  The parse result array.
     * @param bool   $success Whether the parse was successful.
     * @param string $url     The URL that was parsed.
     */
    private function logParseResult(array $result, bool $success, string $url): void {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'hft_scraper_logs';

        // Calculate execution time
        $execution_time = null;
        if (isset($this->parse_start_time)) {
            $execution_time = microtime(true) - $this->parse_start_time;
        }

        $error_message = $result['error'] ?? null;
        if (!$success && !$error_message) {
            $missing_fields = [];
            if ($result['price'] === null) $missing_fields[] = 'price';
            if ($result['currency'] === null) $missing_fields[] = 'currency';
            if ($result['status'] === null) $missing_fields[] = 'status';

            if (!empty($missing_fields)) {
                $error_message = sprintf(
                    'Missing required fields: %s',
                    implode(', ', $missing_fields)
                );
            }
        }

        $url = $this->current_parse_url ?? $url;

        $wpdb->insert(
            $logs_table,
            [
                'scraper_id'      => $this->scraper->id,
                'tracked_link_id' => $this->current_tracked_link_id ?? null,
                'url'             => substr($url, 0, 500),
                'success'         => $success ? 1 : 0,
                'extracted_data'  => wp_json_encode($result),
                'error_message'   => $error_message,
                'execution_time'  => $execution_time,
                'created_at'      => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%d', '%s', '%s', '%f', '%s']
        );

        // Update scraper health tracking
        $this->updateScraperHealth($success);
    }

    /**
     * Update scraper health tracking and reset after 10 consecutive successes.
     *
     * @param bool $success Whether the parse was successful.
     */
    private function updateScraperHealth(bool $success): void {
        global $wpdb;

        $scrapers_table = $wpdb->prefix . 'hft_scrapers';

        if ($success) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$scrapers_table} SET consecutive_successes = consecutive_successes + 1 WHERE id = %d",
                $this->scraper->id
            ));

            $consecutive = $wpdb->get_var($wpdb->prepare(
                "SELECT consecutive_successes FROM {$scrapers_table} WHERE id = %d",
                $this->scraper->id
            ));

            if ($consecutive >= 10) {
                $this->resetScraperHealth();
            }
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$scrapers_table} SET consecutive_successes = 0 WHERE id = %d",
                $this->scraper->id
            ));
        }
    }

    /**
     * Reset scraper health -- clear old failures and set reset timestamp.
     */
    private function resetScraperHealth(): void {
        global $wpdb;

        $scrapers_table = $wpdb->prefix . 'hft_scrapers';
        $logs_table = $wpdb->prefix . 'hft_scraper_logs';
        $tracked_links_table = $wpdb->prefix . 'hft_tracked_links';

        $wpdb->query($wpdb->prepare(
            "UPDATE {$scrapers_table} SET health_reset_at = %s, consecutive_successes = 0 WHERE id = %d",
            current_time('mysql', true),
            $this->scraper->id
        ));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$logs_table}
             WHERE scraper_id = %d
             AND success = 0
             AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)",
            $this->scraper->id
        ));

        $wpdb->query($wpdb->prepare(
            "UPDATE {$tracked_links_table}
             SET consecutive_failures = 0, last_error_message = NULL
             WHERE scraper_id = %d",
            $this->scraper->id
        ));

        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log("[HFT Health] Scraper ID {$this->scraper->id} health reset after 10 consecutive successes");
        }
    }
}
