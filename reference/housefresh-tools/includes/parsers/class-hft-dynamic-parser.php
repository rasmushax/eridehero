<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic parser that uses database configurations and the enhanced extraction pipeline
 *
 * Supports:
 * - Multiple rules per field with priority ordering
 * - XPath, XPath+Regex, CSS, and JSON Path extraction modes
 * - Regex capture groups with fallback patterns
 * - Boolean return mode for stock status
 * - Fallback to base parser for structured data
 */
class HFT_Dynamic_Parser extends HFT_Base_Parser {
    private HFT_Scraper $scraper;
    private ?HFT_Extraction_Pipeline $pipeline = null;
    private HFT_Post_Processor $processor;
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
        $this->processor = new HFT_Post_Processor();
    }

    /**
     * {@inheritdoc}
     */
    public function parse(string $url_or_identifier, array $link_meta = []): array {
        // Start timing
        $this->parse_start_time = microtime(true);
        $this->current_parse_url = $url_or_identifier;
        $this->current_tracked_link_id = $link_meta['tracked_link_id'] ?? null;

        $result = [
            'price' => null,
            'currency' => null,
            'status' => null,
            'shipping_info' => null,
            'error' => null,
        ];

        // Log parse attempt
        $this->logParseAttempt($url_or_identifier);

        // Fetch HTML
        $fetch_error = null;
        $html_content = $this->_fetch_html($url_or_identifier, $fetch_error);

        if ($html_content === null) {
            $result['error'] = $fetch_error ?? __('Failed to fetch HTML content.', 'housefresh-tools');
            if (defined('WP_DEBUG') && WP_DEBUG === true) {
                error_log('[HFT Scraper] Dynamic parser fetch failed for ' . $url_or_identifier . ': ' . $result['error']);
            }
            $this->logParseResult($result, false, $url_or_identifier);
            return $result;
        }

        // Initialize extraction pipeline
        $this->pipeline = new HFT_Extraction_Pipeline($html_content);

        // Group rules by field type
        $rules_by_field = $this->groupRulesByField();

        // Extract each field using the pipeline
        $custom_results = $this->extractAllFields($rules_by_field);

        // Merge custom results
        foreach ($custom_results as $field => $value) {
            if ($value !== null) {
                $result[$field] = $value;
            }
        }

        // Always use the configured currency
        $result['currency'] = $this->scraper->currency;

        // If use_base_parser is enabled and we're missing data, try base parser
        if ($this->scraper->use_base_parser && $this->needsBaseParser($result)) {
            $base_results = parent::parse($url_or_identifier, $link_meta);

            // Merge base results (only fill in missing fields, but never overwrite with errors)
            foreach ($result as $field => $value) {
                if ($value === null && isset($base_results[$field]) && $base_results[$field] !== null) {
                    // Don't merge error messages if we have successful data
                    if ($field === 'error' && $result['price'] !== null) {
                        continue;
                    }
                    $result[$field] = $base_results[$field];
                }
            }
        }

        // Set error if no price found
        if ($result['price'] === null && $result['error'] === null) {
            $result['error'] = __('Could not extract product price.', 'housefresh-tools');
            if (defined('WP_DEBUG') && WP_DEBUG === true) {
                error_log('[HFT Scraper] No price extracted for ' . $url_or_identifier . ' using scraper ID ' . $this->scraper->id);
            }
        }

        // Log result with extraction details
        $this->logParseResult($result, $result['error'] === null, $url_or_identifier);

        return $result;
    }

    /**
     * Group rules by field type
     */
    private function groupRulesByField(): array {
        $grouped = [
            'price' => [],
            'status' => [],
            'shipping' => [],
        ];

        foreach ($this->scraper->rules as $rule) {
            if (isset($grouped[$rule->field_type])) {
                $grouped[$rule->field_type][] = $rule;
            }
        }

        return $grouped;
    }

    /**
     * Extract all fields using the pipeline
     */
    private function extractAllFields(array $rules_by_field): array {
        $results = [];

        // Extract price
        if (!empty($rules_by_field['price'])) {
            $price = $this->pipeline->extractField($rules_by_field['price'], 'price');
            if ($price !== null) {
                $results['price'] = is_numeric($price) ? (float)$price : null;
            }
        }

        // Extract status
        if (!empty($rules_by_field['status'])) {
            $results['status'] = $this->pipeline->extractField($rules_by_field['status'], 'status');
        }

        // Extract shipping
        if (!empty($rules_by_field['shipping'])) {
            $results['shipping_info'] = $this->pipeline->extractField($rules_by_field['shipping'], 'shipping');
        }

        return $results;
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
     * Check if base parser is needed
     */
    private function needsBaseParser(array $result): bool {
        // Always need price at minimum
        if ($result['price'] === null) {
            return true;
        }

        // Optionally need other fields
        if ($result['currency'] === null || $result['status'] === null) {
            return true;
        }

        return false;
    }

    /**
     * Log parse attempt
     */
    private function logParseAttempt(string $url): void {
        // This will be used for tracking in Part 6
        // For now, just a placeholder
    }

    /**
     * {@inheritdoc}
     */
    protected function should_use_curl(): bool {
        return $this->scraper->use_curl;
    }

    /**
     * {@inheritdoc}
     */
    protected function should_use_scrapingrobot(): bool {
        return $this->scraper->use_scrapingrobot;
    }

    /**
     * {@inheritdoc}
     */
    protected function should_render_javascript(): bool {
        // Only render JS if ScrapingRobot is enabled AND JS rendering is enabled
        return $this->scraper->use_scrapingrobot && $this->scraper->scrapingrobot_render_js;
    }

    /**
     * Enhanced log parse result with extraction pipeline details
     */
    private function logParseResult(array $result, bool $success, string $url): void {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'hft_scraper_logs';

        // Calculate execution time if we stored start time
        $execution_time = null;
        if (isset($this->parse_start_time)) {
            $execution_time = microtime(true) - $this->parse_start_time;
        }

        // Enhanced error message with more context
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

        // Store URL from parse call
        $url = $this->current_parse_url ?? $url;

        // Include extraction log for debugging
        $extracted_data = $result;
        if ($this->pipeline !== null) {
            $extracted_data['_extraction_log'] = $this->pipeline->getExtractionLog();
        }

        $wpdb->insert(
            $logs_table,
            [
                'scraper_id' => $this->scraper->id,
                'tracked_link_id' => $this->current_tracked_link_id ?? null,
                'url' => substr($url, 0, 500),
                'success' => $success ? 1 : 0,
                'extracted_data' => json_encode($extracted_data),
                'error_message' => $error_message,
                'execution_time' => $execution_time,
                'created_at' => current_time('mysql', true)
            ],
            ['%d', '%d', '%s', '%d', '%s', '%s', '%f', '%s']
        );

        // Update scraper health tracking
        $this->updateScraperHealth($success);
    }

    /**
     * Update scraper health tracking and reset after 10 consecutive successes
     */
    private function updateScraperHealth(bool $success): void {
        global $wpdb;

        $scrapers_table = $wpdb->prefix . 'hft_scrapers';

        if ($success) {
            // Increment consecutive successes
            $wpdb->query($wpdb->prepare(
                "UPDATE {$scrapers_table} SET consecutive_successes = consecutive_successes + 1 WHERE id = %d",
                $this->scraper->id
            ));

            // Get current consecutive successes count
            $consecutive = $wpdb->get_var($wpdb->prepare(
                "SELECT consecutive_successes FROM {$scrapers_table} WHERE id = %d",
                $this->scraper->id
            ));

            // Reset health after 10 consecutive successes
            if ($consecutive >= 10) {
                $this->resetScraperHealth();
            }
        } else {
            // Reset consecutive successes counter on failure
            $wpdb->query($wpdb->prepare(
                "UPDATE {$scrapers_table} SET consecutive_successes = 0 WHERE id = %d",
                $this->scraper->id
            ));
        }
    }

    /**
     * Reset scraper health - clear old failures and set reset timestamp
     */
    private function resetScraperHealth(): void {
        global $wpdb;

        $scrapers_table = $wpdb->prefix . 'hft_scrapers';
        $logs_table = $wpdb->prefix . 'hft_scraper_logs';
        $tracked_links_table = $wpdb->prefix . 'hft_tracked_links';

        // Set health reset timestamp and reset consecutive counter
        $wpdb->query($wpdb->prepare(
            "UPDATE {$scrapers_table} SET health_reset_at = %s, consecutive_successes = 0 WHERE id = %d",
            current_time('mysql', true),
            $this->scraper->id
        ));

        // Clear old failure logs (keep last 24 hours for debugging)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$logs_table}
             WHERE scraper_id = %d
             AND success = 0
             AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)",
            $this->scraper->id
        ));

        // Reset consecutive failures for tracked links using this scraper
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
