<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scraper rule data model
 *
 * Supports advanced extraction modes:
 * - xpath: Standard XPath extraction (default)
 * - xpath_regex: XPath extraction followed by regex capture group
 * - css: CSS selector (converted to XPath internally)
 * - json_path: JSON path extraction from JSON-LD or inline JSON
 */
class HFT_Scraper_Rule {
    /**
     * Supported extraction modes
     */
    public const MODE_XPATH = 'xpath';
    public const MODE_XPATH_REGEX = 'xpath_regex';
    public const MODE_CSS = 'css';
    public const MODE_JSON_PATH = 'json_path';

    /**
     * Supported field types
     */
    public const FIELD_PRICE = 'price';
    public const FIELD_STATUS = 'status';
    public const FIELD_SHIPPING = 'shipping';

    public int $id;
    public int $scraper_id;
    public string $field_type;
    public int $priority;
    public string $extraction_mode;
    public string $xpath_selector;
    public ?string $attribute;
    public ?string $regex_pattern;
    public ?array $regex_fallbacks;
    public bool $return_boolean;
    public ?array $boolean_true_values;
    public ?array $post_processing;
    public bool $is_active;

    public function __construct(array $data = []) {
        $this->id = (int) ($data['id'] ?? 0);
        $this->scraper_id = (int) ($data['scraper_id'] ?? 0);
        $this->field_type = $data['field_type'] ?? '';
        $this->priority = (int) ($data['priority'] ?? 10);
        $this->extraction_mode = $data['extraction_mode'] ?? self::MODE_XPATH;
        $this->xpath_selector = $data['xpath_selector'] ?? '';
        $this->attribute = $data['attribute'] ?? null;
        $this->regex_pattern = $data['regex_pattern'] ?? null;
        $this->regex_fallbacks = $this->parse_json_field($data['regex_fallbacks'] ?? null);
        $this->return_boolean = (bool) ($data['return_boolean'] ?? false);
        $this->boolean_true_values = $this->parse_json_field($data['boolean_true_values'] ?? null);
        $this->post_processing = $this->parse_json_field($data['post_processing'] ?? null);
        $this->is_active = (bool) ($data['is_active'] ?? true);
    }

    /**
     * Parse JSON field from string or return array as-is
     */
    private function parse_json_field($value): ?array {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    /**
     * Check if this rule uses regex extraction
     */
    public function uses_regex(): bool {
        return $this->extraction_mode === self::MODE_XPATH_REGEX && !empty($this->regex_pattern);
    }

    /**
     * Check if this rule returns a boolean result (for status checks)
     */
    public function returns_boolean(): bool {
        return $this->return_boolean && $this->field_type === self::FIELD_STATUS;
    }

    /**
     * Get all regex patterns to try (primary + fallbacks)
     */
    public function get_all_regex_patterns(): array {
        $patterns = [];

        if (!empty($this->regex_pattern)) {
            $patterns[] = $this->regex_pattern;
        }

        if (!empty($this->regex_fallbacks)) {
            $patterns = array_merge($patterns, $this->regex_fallbacks);
        }

        return $patterns;
    }

    /**
     * Get boolean true values (defaults to ['true', '1', 'yes'])
     */
    public function get_boolean_true_values(): array {
        if (!empty($this->boolean_true_values)) {
            return array_map('strtolower', $this->boolean_true_values);
        }
        return ['true', '1', 'yes', 'in stock', 'available'];
    }

    public function to_array(): array {
        return [
            'id' => $this->id,
            'scraper_id' => $this->scraper_id,
            'field_type' => $this->field_type,
            'priority' => $this->priority,
            'extraction_mode' => $this->extraction_mode,
            'xpath_selector' => $this->xpath_selector,
            'attribute' => $this->attribute,
            'regex_pattern' => $this->regex_pattern,
            'regex_fallbacks' => $this->regex_fallbacks,
            'return_boolean' => $this->return_boolean,
            'boolean_true_values' => $this->boolean_true_values,
            'post_processing' => $this->post_processing,
            'is_active' => $this->is_active
        ];
    }

    /**
     * Convert to database row format
     */
    public function to_db_row(): array {
        return [
            'scraper_id' => $this->scraper_id,
            'field_type' => $this->field_type,
            'priority' => $this->priority,
            'extraction_mode' => $this->extraction_mode,
            'xpath_selector' => $this->xpath_selector,
            'attribute' => $this->attribute,
            'regex_pattern' => $this->regex_pattern,
            'regex_fallbacks' => $this->regex_fallbacks ? json_encode($this->regex_fallbacks) : null,
            'return_boolean' => $this->return_boolean ? 1 : 0,
            'boolean_true_values' => $this->boolean_true_values ? json_encode($this->boolean_true_values) : null,
            'post_processing' => $this->post_processing ? json_encode($this->post_processing) : null,
            'is_active' => $this->is_active ? 1 : 0
        ];
    }

    /**
     * Get valid extraction modes
     */
    public static function get_extraction_modes(): array {
        return [
            self::MODE_XPATH => __('XPath', 'housefresh-tools'),
            self::MODE_XPATH_REGEX => __('XPath + Regex', 'housefresh-tools'),
            self::MODE_CSS => __('CSS Selector', 'housefresh-tools'),
            self::MODE_JSON_PATH => __('JSON Path', 'housefresh-tools'),
        ];
    }

    /**
     * Get valid field types
     */
    public static function get_field_types(): array {
        return [
            self::FIELD_PRICE => __('Price', 'housefresh-tools'),
            self::FIELD_STATUS => __('Stock Status', 'housefresh-tools'),
            self::FIELD_SHIPPING => __('Shipping Info', 'housefresh-tools'),
        ];
    }
}
