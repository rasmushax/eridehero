<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Advanced extraction pipeline supporting multiple extraction modes
 *
 * Modes:
 * - xpath: Standard XPath extraction
 * - xpath_regex: XPath extraction followed by regex capture group extraction
 * - css: CSS selector (converted to XPath)
 * - json_path: JSON path for structured data
 *
 * Features:
 * - Multiple rules per field with priority ordering
 * - Regex capture group extraction
 * - Fallback pattern chains
 * - Boolean return mode for stock status checks
 * - Detailed extraction logging for debugging
 */
class HFT_Extraction_Pipeline {
    private HFT_XPath_Extractor $extractor;
    private HFT_Post_Processor $processor;
    private string $html_content;
    private array $extraction_log = [];

    /**
     * Constructor
     *
     * @param string $html_content The HTML content to extract from
     */
    public function __construct(string $html_content) {
        $this->html_content = $html_content;
        $this->extractor = new HFT_XPath_Extractor($html_content);
        $this->processor = new HFT_Post_Processor();
    }

    /**
     * Extract a field value using multiple rules in priority order
     *
     * @param array $rules Array of HFT_Scraper_Rule objects for this field
     * @param string $field_type The field type (price, status, shipping)
     * @return mixed|null Extracted value or null if all rules fail
     */
    public function extractField(array $rules, string $field_type) {
        // Sort rules by priority (lower = first)
        usort($rules, fn($a, $b) => $a->priority <=> $b->priority);

        foreach ($rules as $rule) {
            if (!$rule->is_active) {
                continue;
            }

            $result = $this->executeRule($rule);

            if ($result !== null) {
                $this->log($rule, 'success', $result);
                return $result;
            }

            $this->log($rule, 'no_match', null);
        }

        return null;
    }

    /**
     * Execute a single extraction rule
     *
     * @param HFT_Scraper_Rule $rule The rule to execute
     * @return mixed|null Extracted value or null
     */
    public function executeRule(HFT_Scraper_Rule $rule) {
        $value = null;

        switch ($rule->extraction_mode) {
            case HFT_Scraper_Rule::MODE_XPATH:
                $value = $this->extractXPath($rule);
                break;

            case HFT_Scraper_Rule::MODE_XPATH_REGEX:
                $value = $this->extractXPathRegex($rule);
                break;

            case HFT_Scraper_Rule::MODE_CSS:
                $value = $this->extractCSS($rule);
                break;

            case HFT_Scraper_Rule::MODE_JSON_PATH:
                $value = $this->extractJsonPath($rule);
                break;

            default:
                // Fallback to XPath
                $value = $this->extractXPath($rule);
        }

        // Handle boolean return mode (for stock status)
        if ($value !== null && $rule->returns_boolean()) {
            $value = $this->convertToBoolean($value, $rule);
        }

        // Apply post-processing
        if ($value !== null && !$rule->returns_boolean()) {
            $value = $this->processor->process($value, $rule->post_processing, $rule->field_type);
        }

        return $value;
    }

    /**
     * Standard XPath extraction
     */
    private function extractXPath(HFT_Scraper_Rule $rule): ?string {
        return $this->extractor->extract($rule->xpath_selector, $rule->attribute);
    }

    /**
     * XPath extraction followed by regex capture group
     * This is the key feature for handling complex sites like Segway
     */
    private function extractXPathRegex(HFT_Scraper_Rule $rule): ?string {
        // First, extract content via XPath
        $content = $this->extractor->extract($rule->xpath_selector, $rule->attribute);

        if ($content === null || $content === '') {
            return null;
        }

        // Get all regex patterns to try (primary + fallbacks)
        $patterns = $rule->get_all_regex_patterns();

        if (empty($patterns)) {
            return $content;
        }

        // Try each pattern in order until one succeeds
        foreach ($patterns as $pattern) {
            $result = $this->applyRegexCapture($content, $pattern);
            if ($result !== null) {
                $this->log($rule, 'regex_matched', ['pattern' => $pattern, 'result' => $result]);
                return $result;
            }
        }

        // No patterns matched
        $this->log($rule, 'regex_no_match', ['patterns_tried' => count($patterns)]);
        return null;
    }

    /**
     * CSS selector extraction (converted to XPath)
     */
    private function extractCSS(HFT_Scraper_Rule $rule): ?string {
        // Convert CSS selector to XPath
        $xpath = $this->cssToXPath($rule->xpath_selector);

        if ($xpath === null) {
            return null;
        }

        return $this->extractor->extract($xpath, $rule->attribute);
    }

    /**
     * JSON path extraction from JSON-LD or inline JSON
     */
    private function extractJsonPath(HFT_Scraper_Rule $rule): ?string {
        // Look for JSON-LD script tags
        $json_content = $this->extractor->extract(
            '//script[@type="application/ld+json"]',
            null
        );

        if ($json_content === null) {
            // Try to find inline JSON in the page
            $json_content = $this->extractInlineJson();
        }

        if ($json_content === null) {
            return null;
        }

        // Parse JSON and navigate path
        return $this->navigateJsonPath($json_content, $rule->xpath_selector);
    }

    /**
     * Apply regex capture group extraction
     *
     * @param string $content The content to search
     * @param string $pattern The regex pattern with capture groups
     * @return string|null The captured value (first capture group) or null
     */
    private function applyRegexCapture(string $content, string $pattern): ?string {
        // Ensure pattern has delimiters
        $pattern = $this->normalizeRegexPattern($pattern);

        if (empty($pattern)) {
            return null;
        }

        // Try to match
        $result = @preg_match($pattern, $content, $matches);

        if ($result === false || $result === 0) {
            return null;
        }

        // Return first capture group if exists, otherwise full match
        if (isset($matches[1])) {
            return trim($matches[1]);
        }

        return isset($matches[0]) ? trim($matches[0]) : null;
    }

    /**
     * Normalize regex pattern to ensure it has delimiters
     */
    private function normalizeRegexPattern(string $pattern): string {
        if (empty($pattern)) {
            return '';
        }

        // Check if pattern already has delimiters
        if (preg_match('/^[\/\#\~\@\!].*[\/\#\~\@\!][imsxADSUXJu]*$/', $pattern)) {
            return $pattern;
        }

        // Add delimiters, escaping any forward slashes in the pattern
        return '/' . str_replace('/', '\/', $pattern) . '/s';
    }

    /**
     * Convert boolean-like value to stock status string
     */
    private function convertToBoolean($value, HFT_Scraper_Rule $rule): string {
        $value_lower = strtolower(trim((string)$value));
        $true_values = $rule->get_boolean_true_values();

        if (in_array($value_lower, $true_values, true)) {
            return 'In Stock';
        }

        return 'Out of Stock';
    }

    /**
     * Convert CSS selector to XPath
     * Supports common CSS selectors
     */
    private function cssToXPath(string $css): ?string {
        $css = trim($css);

        if (empty($css)) {
            return null;
        }

        // Already XPath? Return as-is
        if (str_starts_with($css, '//') || str_starts_with($css, '/')) {
            return $css;
        }

        $xpath = '';

        // Handle common CSS patterns
        $parts = preg_split('/\s+/', $css);

        foreach ($parts as $i => $part) {
            $segment = $this->cssPartToXPath($part);
            if ($segment === null) {
                return null;
            }

            if ($i === 0) {
                $xpath = '//' . $segment;
            } else {
                $xpath .= '//' . $segment;
            }
        }

        return $xpath;
    }

    /**
     * Convert a single CSS selector part to XPath
     */
    private function cssPartToXPath(string $part): ?string {
        // ID selector: #id
        if (preg_match('/^#([\w-]+)$/', $part, $m)) {
            return "*[@id='{$m[1]}']";
        }

        // Class selector: .class
        if (preg_match('/^\.([\w-]+)$/', $part, $m)) {
            return "*[contains(@class, '{$m[1]}')]";
        }

        // Element with class: div.class
        if (preg_match('/^(\w+)\.([\w-]+)$/', $part, $m)) {
            return "{$m[1]}[contains(@class, '{$m[2]}')]";
        }

        // Element with ID: div#id
        if (preg_match('/^(\w+)#([\w-]+)$/', $part, $m)) {
            return "{$m[1]}[@id='{$m[2]}']";
        }

        // Element with attribute: input[type="text"]
        if (preg_match('/^(\w+)\[(\w+)=["\']([^"\']+)["\']\]$/', $part, $m)) {
            return "{$m[1]}[@{$m[2]}='{$m[3]}']";
        }

        // Simple element: div
        if (preg_match('/^(\w+)$/', $part, $m)) {
            return $m[1];
        }

        // Attribute selector: [data-price]
        if (preg_match('/^\[(\w+)\]$/', $part, $m)) {
            return "*[@{$m[1]}]";
        }

        return null;
    }

    /**
     * Extract inline JSON from page (looks for common patterns)
     */
    private function extractInlineJson(): ?string {
        // Try various patterns where inline JSON might be found
        $patterns = [
            '//script[contains(text(), "product")]',
            '//script[contains(text(), "price")]',
            '//script[contains(text(), "dataLayer")]',
        ];

        foreach ($patterns as $pattern) {
            $content = $this->extractor->extract($pattern, null);
            if ($content !== null && $this->isValidJson($content)) {
                return $content;
            }
        }

        return null;
    }

    /**
     * Check if string is valid JSON
     */
    private function isValidJson(string $str): bool {
        // Try to find JSON object or array
        if (preg_match('/\{[\s\S]*\}|\[[\s\S]*\]/', $str, $matches)) {
            json_decode($matches[0]);
            return json_last_error() === JSON_ERROR_NONE;
        }
        return false;
    }

    /**
     * Navigate JSON using a simple path syntax
     * Supports: $.product.price, product.offers[0].price
     */
    private function navigateJsonPath(string $json_str, string $path): ?string {
        // Extract JSON from string if needed
        if (!preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])/', $json_str, $matches)) {
            return null;
        }

        $data = json_decode($matches[1], true);
        if ($data === null) {
            return null;
        }

        // Remove leading $. if present
        $path = preg_replace('/^\$\.?/', '', $path);

        // Split path into parts
        $parts = preg_split('/\.|\[|\]/', $path, -1, PREG_SPLIT_NO_EMPTY);

        $current = $data;
        foreach ($parts as $part) {
            if (is_array($current)) {
                if (is_numeric($part)) {
                    $current = $current[(int)$part] ?? null;
                } else {
                    $current = $current[$part] ?? null;
                }
            } else {
                return null;
            }

            if ($current === null) {
                return null;
            }
        }

        return is_scalar($current) ? (string)$current : null;
    }

    /**
     * Log extraction attempt for debugging
     */
    private function log(HFT_Scraper_Rule $rule, string $status, $data): void {
        $this->extraction_log[] = [
            'rule_id' => $rule->id,
            'field_type' => $rule->field_type,
            'mode' => $rule->extraction_mode,
            'selector' => $rule->xpath_selector,
            'status' => $status,
            'data' => $data,
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Get extraction log for debugging
     */
    public function getExtractionLog(): array {
        return $this->extraction_log;
    }

    /**
     * Get extraction log as JSON for storing in database
     */
    public function getExtractionLogJson(): string {
        return json_encode($this->extraction_log);
    }

    /**
     * Clear extraction log
     */
    public function clearLog(): void {
        $this->extraction_log = [];
    }
}
