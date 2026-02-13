<?php
/**
 * Spec Populator Handler - Shared backend for AI-powered spec population.
 *
 * Handles prompt building, Perplexity API calls, response parsing,
 * validation, and saving. Used by both the bulk admin page and the
 * single-product modal.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\Schema\AcfSchemaParser;
use ERH\CategoryConfig;

/**
 * Handler for spec population logic.
 */
class SpecPopulatorHandler {

    /**
     * Field paths to exclude from population.
     *
     * @var array<string>
     */
    private const EXCLUDED_FIELD_PATHS = [
        'editor_rating',
        'obsolete.is_product_obsolete',
        'obsolete.has_the_product_been_superseded',
        'review.youtube_video',
    ];

    /**
     * ACF group keys to exclude entirely.
     *
     * @var array<string>
     */
    private const EXCLUDED_GROUP_KEYS = [];

    /**
     * Performance test fields to INCLUDE (all others in that group are excluded).
     * These are manufacturer-claimed specs that Perplexity can research.
     *
     * @var array<string>
     */
    private const PERF_TEST_ALLOWED_FIELDS = [
        'manufacturer_top_speed',
        'manufacturer_range',
        'max_incline',
    ];

    /**
     * ACF schema parser instance.
     *
     * @var AcfSchemaParser
     */
    private AcfSchemaParser $schema_parser;

    /**
     * Perplexity client instance.
     *
     * @var PerplexityClient
     */
    private PerplexityClient $perplexity;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->schema_parser = new AcfSchemaParser();
        $this->perplexity = new PerplexityClient();
    }

    /**
     * Check if the Perplexity API is configured.
     *
     * @return bool True if API key is set.
     */
    public function is_configured(): bool {
        return $this->perplexity->is_configured();
    }

    /**
     * Fetch AI-generated spec suggestions for a product.
     *
     * @param int    $product_id         Product post ID.
     * @param string $product_type       Product type key (escooter, ebike, etc.).
     * @param bool   $overwrite_existing Whether to include fields that already have values.
     * @return array{success: bool, suggestions?: array, current_values?: array, schema?: array, error?: string, empty?: bool}
     */
    public function fetch_specs(int $product_id, string $product_type, bool $overwrite_existing): array {
        // Get schema for this product type.
        $schema = $this->schema_parser->get_schema($product_type);
        if (empty($schema)) {
            return ['success' => false, 'error' => 'No schema found for this product type.'];
        }

        // Filter out excluded groups and fields.
        $populatable = $this->filter_populatable_fields($schema);
        if (empty($populatable)) {
            return ['success' => false, 'error' => 'No populatable fields found.'];
        }

        // Get current values for all populatable fields.
        $current_values = [];
        foreach ($populatable as $column) {
            $current_values[$column['key']] = $this->schema_parser->get_field_value($product_id, $column['key']);
        }

        // If not overwriting, keep only fields where current value is empty/null.
        $fields_to_populate = [];
        foreach ($populatable as $column) {
            $current = $current_values[$column['key']];
            if ($overwrite_existing || $this->is_empty_value($current)) {
                $fields_to_populate[] = $column;
            }
        }

        if (empty($fields_to_populate)) {
            return ['success' => true, 'empty' => true, 'message' => 'All fields already have values.'];
        }

        // Get product context.
        $product_name = get_the_title($product_id);
        $brand = $this->get_product_brand($product_id);
        $product_type_label = $this->get_product_type_label($product_type);

        // Build and send prompt — uses numbered keys for reliable matching.
        [$system_prompt, $user_prompt, $index_map] = $this->build_prompt(
            $product_name,
            $brand,
            $product_type_label,
            $fields_to_populate
        );

        // Debug: log what we send.
        error_log('[ERH Spec Populator] Sending prompt for product #' . $product_id . ' (' . $product_name . ')');
        error_log('[ERH Spec Populator] System: ' . $system_prompt);
        error_log('[ERH Spec Populator] User prompt: ' . substr($user_prompt, 0, 2000) . (strlen($user_prompt) > 2000 ? '...[truncated]' : ''));
        error_log('[ERH Spec Populator] Fields to populate: ' . count($fields_to_populate));

        $api_result = $this->perplexity->send_request($system_prompt, $user_prompt);
        if (!$api_result['success']) {
            error_log('[ERH Spec Populator] API error: ' . $api_result['error']);
            return ['success' => false, 'error' => $api_result['error']];
        }

        // Debug: log what we receive.
        error_log('[ERH Spec Populator] Raw API response: ' . substr($api_result['content'], 0, 3000));

        // Parse JSON response.
        $raw_json = $this->extract_json($api_result['content']);
        if ($raw_json === null) {
            error_log('[ERH Spec Populator] Failed to extract JSON from response.');
            return ['success' => false, 'error' => 'AI returned invalid JSON format.'];
        }

        error_log('[ERH Spec Populator] Parsed JSON keys: ' . implode(', ', array_keys($raw_json)));

        // Map numbered keys back to field paths.
        $suggestions = $this->map_numbered_response($raw_json, $index_map);

        error_log('[ERH Spec Populator] Mapped ' . count($suggestions) . ' field(s): ' . implode(', ', array_keys($suggestions)));

        // Validate each suggestion against schema.
        $validated = $this->validate_suggestions($suggestions, $fields_to_populate);

        // Include ALL populatable fields — even those AI didn't return data for.
        foreach ($fields_to_populate as $column) {
            if (!isset($validated[$column['key']])) {
                $validated[$column['key']] = [
                    'value'   => null,
                    'valid'   => false,
                    'message' => 'No data returned by AI',
                    'no_data' => true,
                ];
            }
        }

        error_log('[ERH Spec Populator] Total fields: ' . count($validated) . ' (' . count($suggestions) . ' from AI)');

        // Build schema map for frontend (keyed by field path).
        $schema_map = [];
        foreach ($populatable as $column) {
            $schema_map[$column['key']] = [
                'label'   => $column['label'],
                'group'   => $column['group'],
                'type'    => $column['type'],
                'choices' => $column['choices'] ?? [],
                'append'  => $column['append'] ?? '',
            ];
        }

        return [
            'success'        => true,
            'suggestions'    => $validated,
            'current_values' => $current_values,
            'schema'         => $schema_map,
        ];
    }

    /**
     * Save validated specs to a product.
     *
     * @param int    $product_id   Product post ID.
     * @param array  $specs        Field path => value mapping to save.
     * @param string $product_type Product type key.
     * @return array{saved: int, errors: array<string>}
     */
    public function save_specs(int $product_id, array $specs, string $product_type): array {
        $schema = $this->schema_parser->get_schema($product_type);
        $schema_map = [];
        foreach ($schema as $column) {
            $schema_map[$column['key']] = $column;
        }

        $saved = 0;
        $errors = [];

        foreach ($specs as $field_path => $value) {
            // Find matching column in schema.
            if (!isset($schema_map[$field_path])) {
                continue; // Unknown field, silently skip.
            }

            $column = $schema_map[$field_path];

            // Skip read-only fields.
            if (!empty($column['readonly'])) {
                continue;
            }

            // Validate.
            $validation = $this->schema_parser->validate_value($value, $column);
            if (!$validation['valid']) {
                $errors[] = sprintf('%s: %s', $column['label'], $validation['message']);
                continue;
            }

            // Normalize.
            $normalized = $this->schema_parser->normalize_value($value, $column);

            // Save.
            $result = $this->schema_parser->set_field_value($product_id, $field_path, $normalized);
            if ($result) {
                $saved++;
            } else {
                $errors[] = sprintf('%s: Failed to save.', $column['label']);
            }
        }

        return [
            'saved'  => $saved,
            'errors' => $errors,
        ];
    }

    /**
     * Get the count of populatable fields and how many are empty for a product.
     *
     * @param int    $product_id   Product post ID.
     * @param string $product_type Product type key.
     * @return array{total: int, empty: int}
     */
    public function get_field_counts(int $product_id, string $product_type): array {
        $schema = $this->schema_parser->get_schema($product_type);
        $populatable = $this->filter_populatable_fields($schema);

        $total = count($populatable);
        $empty = 0;

        foreach ($populatable as $column) {
            $value = $this->schema_parser->get_field_value($product_id, $column['key']);
            if ($this->is_empty_value($value)) {
                $empty++;
            }
        }

        return [
            'total' => $total,
            'empty' => $empty,
        ];
    }

    /**
     * Filter schema columns to only those that can be AI-populated.
     *
     * @param array $schema Full schema columns.
     * @return array Filtered columns.
     */
    private function filter_populatable_fields(array $schema): array {
        return array_values(array_filter($schema, function (array $column): bool {
            // Skip post_title.
            if ($column['key'] === 'post_title') {
                return false;
            }

            // Skip read-only fields.
            if (!empty($column['readonly'])) {
                return false;
            }

            // Skip excluded group keys.
            if (!empty($column['group_key']) && in_array($column['group_key'], self::EXCLUDED_GROUP_KEYS, true)) {
                return false;
            }

            // Performance tests group: only allow specific manufacturer-spec fields.
            if (!empty($column['group_key']) && $column['group_key'] === 'group_erh_performance_tests') {
                return in_array($column['key'], self::PERF_TEST_ALLOWED_FIELDS, true);
            }

            // Skip excluded field paths.
            if (in_array($column['key'], self::EXCLUDED_FIELD_PATHS, true)) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Build prompts for the Perplexity API.
     *
     * @param string $product_name       Product name.
     * @param string $brand              Brand name.
     * @param string $product_type_label Human-readable product type.
     * @param array  $fields             Array of column definitions to populate.
     * @return array{0: string, 1: string} [system_prompt, user_prompt]
     */
    private function build_prompt(string $product_name, string $brand, string $product_type_label, array $fields): array {
        $system_prompt = 'You are a product specification researcher. '
            . 'Return ONLY a valid JSON object where keys are the field numbers (as strings) and values are the specs. '
            . 'Include as many fields as you can find. Only omit a field if you truly cannot find the information. '
            . 'No markdown, no explanation, just JSON.';

        // Build numbered field list and index map.
        $field_lines = [];
        $index_map = []; // number => field_path

        foreach ($fields as $i => $column) {
            $num = $i + 1;
            $index_map[(string) $num] = $column['key'];

            $desc = $this->describe_field($column);
            $line = sprintf('%d. %s: %s', $num, $column['label'], $desc);

            if (!empty($column['instructions'])) {
                $line .= ' — ' . $column['instructions'];
            }

            $field_lines[] = $line;
        }

        // Build example with first 3 fields.
        $example = [];
        $example_count = min(3, count($fields));
        for ($i = 0; $i < $example_count; $i++) {
            $example[(string) ($i + 1)] = $this->get_example_value($fields[$i]);
        }

        $user_prompt = sprintf(
            "Find the specifications for the %s \"%s\"%s.\n\n"
            . "Fields to populate:\n%s\n\n"
            . "Return JSON with the field numbers as keys. Example:\n%s",
            $product_type_label,
            $product_name,
            $brand ? " by {$brand}" : '',
            implode("\n", $field_lines),
            wp_json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return [$system_prompt, $user_prompt, $index_map];
    }

    /**
     * Get a placeholder example value for a field (used in prompt example).
     *
     * @param array $column Column definition.
     * @return mixed Example value.
     */
    private function get_example_value(array $column): mixed {
        $type = $column['type'] ?? 'text';

        switch ($type) {
            case 'number':
                return 1000;
            case 'boolean':
                return 1;
            case 'select':
                $choices = array_keys($column['choices'] ?? []);
                return $choices[0] ?? 'value';
            case 'checkbox':
                $choices = array_keys($column['choices'] ?? []);
                return array_slice($choices, 0, 2) ?: ['value'];
            default:
                return 'example text';
        }
    }

    /**
     * Map numbered AI response keys back to field paths.
     *
     * @param array $raw_json   Parsed JSON with numbered string keys.
     * @param array $index_map  Number => field_path mapping.
     * @return array Field path => value mapping.
     */
    private function map_numbered_response(array $raw_json, array $index_map): array {
        $mapped = [];

        foreach ($raw_json as $key => $value) {
            $str_key = (string) $key;

            if (isset($index_map[$str_key])) {
                $mapped[$index_map[$str_key]] = $value;
            }
        }

        return $mapped;
    }

    /**
     * Generate a human-readable description of a field's expected format.
     *
     * @param array $column Column definition.
     * @return string Description string.
     */
    private function describe_field(array $column): string {
        $type = $column['type'] ?? 'text';

        switch ($type) {
            case 'number':
                $desc = 'number';
                if (!empty($column['append'])) {
                    $desc .= ' (' . $column['append'] . ')';
                }
                if ($column['min'] !== null && $column['min'] !== '') {
                    $desc .= ', min: ' . $column['min'];
                }
                if ($column['max'] !== null && $column['max'] !== '') {
                    $desc .= ', max: ' . $column['max'];
                }
                return $desc;

            case 'select':
                if (!empty($column['choices'])) {
                    $choice_values = array_keys($column['choices']);
                    return 'MUST be one of: ' . implode(', ', $choice_values);
                }
                return 'text string';

            case 'checkbox':
                if (!empty($column['choices'])) {
                    $choice_values = array_keys($column['choices']);
                    return 'array, select applicable from: ' . implode(', ', $choice_values);
                }
                return 'array of strings';

            case 'boolean':
                return '1 for yes/true, 0 for no/false';

            case 'textarea':
                return 'text string (can be multi-line)';

            default:
                return 'text string';
        }
    }

    /**
     * Extract JSON from an AI response that may contain markdown fences.
     *
     * @param string $content Raw API response content.
     * @return array|null Parsed JSON array or null on failure.
     */
    private function extract_json(string $content): ?array {
        $content = trim($content);

        // Strip markdown code fences.
        $content = preg_replace('/^```(?:json)?\s*\n?/i', '', $content);
        $content = preg_replace('/\n?```\s*$/i', '', $content);
        $content = trim($content);

        // Find first { and last }.
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $json_str = substr($content, $start, $end - $start + 1);
        $decoded = json_decode($json_str, true);

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Validate AI suggestions against schema definitions.
     *
     * @param array $suggestions Raw suggestions from AI.
     * @param array $fields      Schema columns for validation.
     * @return array Validated suggestions with status info.
     */
    private function validate_suggestions(array $suggestions, array $fields): array {
        // Build a lookup map of field paths to columns.
        $field_map = [];
        foreach ($fields as $column) {
            $field_map[$column['key']] = $column;
        }

        $validated = [];
        foreach ($suggestions as $field_path => $value) {
            // Skip unknown fields.
            if (!isset($field_map[$field_path])) {
                continue;
            }

            // Treat empty/null/placeholder AI responses as no data.
            if ($this->is_empty_ai_value($value)) {
                $validated[$field_path] = [
                    'value'   => null,
                    'valid'   => false,
                    'message' => 'No data returned by AI',
                    'no_data' => true,
                ];
                continue;
            }

            $column = $field_map[$field_path];

            // Validate against schema.
            $validation = $this->schema_parser->validate_value($value, $column);

            $validated[$field_path] = [
                'value'   => $value,
                'valid'   => $validation['valid'],
                'message' => $validation['message'],
            ];
        }

        return $validated;
    }

    /**
     * Check if a field value is considered empty.
     *
     * @param mixed $value Field value.
     * @return bool True if the value is empty.
     */
    private function is_empty_value($value): bool {
        if ($value === null || $value === '' || $value === false) {
            return true;
        }

        if (is_array($value) && empty($value)) {
            return true;
        }

        return false;
    }

    /**
     * Check if an AI-returned value is effectively empty / a non-answer.
     *
     * @param mixed $value AI response value.
     * @return bool True if the value should be treated as "no data".
     */
    private function is_empty_ai_value($value): bool {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value) && empty($value)) {
            return true;
        }

        // Catch common AI placeholder responses.
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            $placeholders = ['n/a', 'na', 'unknown', 'not available', 'not specified', 'none', '-', '—', 'null', 'tbd'];
            if (in_array($lower, $placeholders, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the brand name for a product.
     *
     * @param int $product_id Product post ID.
     * @return string Brand name or empty string.
     */
    private function get_product_brand(int $product_id): string {
        $brand_terms = get_the_terms($product_id, 'brand');
        if ($brand_terms && !is_wp_error($brand_terms)) {
            return $brand_terms[0]->name;
        }
        return '';
    }

    /**
     * Get human-readable product type label.
     *
     * @param string $product_type Product type key.
     * @return string Product type label.
     */
    private function get_product_type_label(string $product_type): string {
        $normalized = CategoryConfig::normalize_key($product_type);
        if (isset(CategoryConfig::CATEGORIES[$normalized])) {
            return CategoryConfig::CATEGORIES[$normalized]['name'];
        }
        return $product_type;
    }
}
