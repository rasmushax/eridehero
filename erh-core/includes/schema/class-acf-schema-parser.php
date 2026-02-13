<?php
/**
 * ACF Schema Parser - Dynamically discovers ACF fields for products CPT.
 *
 * Uses ACF's PHP API to read field groups and fields, avoiding hardcoded field mappings.
 * Add new ACF fields and they automatically appear in the Spec Editor.
 *
 * @package ERH\Schema
 */

declare(strict_types=1);

namespace ERH\Schema;

use ERH\CategoryConfig;

/**
 * Parser for ACF field schema discovery.
 */
class AcfSchemaParser {

    /**
     * Field types that are editable in the spec editor.
     *
     * @var array<string>
     */
    private const EDITABLE_TYPES = [
        'text',
        'textarea',
        'number',
        'select',
        'checkbox',
        'true_false',
        'radio',
        'url',
        'email',
    ];

    /**
     * Field types to skip (not useful for bulk editing).
     *
     * @var array<string>
     */
    private const SKIP_TYPES = [
        'tab',
        'message',
        'accordion',
        'post_object',
        'page_link',
        'relationship',
        'gallery',
        'image',
        'file',
        'wysiwyg',
        'oembed',
        'google_map',
        'date_picker',
        'date_time_picker',
        'time_picker',
        'color_picker',
        'button_group',
        'link',
        'clone',
        'flexible_content',
        'repeater',
    ];

    /**
     * Product type to ACF group key mapping.
     *
     * @var array<string, string>
     */
    private const TYPE_GROUP_MAP = [
        'escooter'    => 'group_erh_escooters',
        'ebike'       => 'group_erh_ebikes',
        'eskateboard' => 'group_erh_eskateboards',
        'euc'         => 'group_erh_eucs',
        'hoverboard'  => 'group_erh_hoverboards',
    ];

    /**
     * Shared field groups that apply to all product types.
     *
     * @var array<string>
     */
    private const SHARED_GROUPS = [
        'group_erh_basic_info',
        'group_erh_performance_tests',
    ];

    /**
     * Fields to always show first (pinned columns).
     *
     * @var array<string>
     */
    private const PINNED_FIELDS = [
        'post_title',
    ];

    /**
     * Cached schemas per product type.
     *
     * @var array<string, array>
     */
    private array $schema_cache = [];

    /**
     * Get schema for a product type.
     *
     * @param string $product_type Product type key (escooter, ebike, etc.).
     * @return array<int, array> Array of column definitions.
     */
    public function get_schema(string $product_type): array {
        // Normalize the key.
        $product_type = CategoryConfig::normalize_key($product_type);

        // Check cache.
        if (isset($this->schema_cache[$product_type])) {
            return $this->schema_cache[$product_type];
        }

        // Check if ACF is available.
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
            return [];
        }

        $columns = [];

        // Add the pinned product name column first.
        $columns[] = [
            'key'         => 'post_title',
            'acf_key'     => null,
            'label'       => 'Product Name',
            'group'       => 'Product',
            'type'        => 'text',
            'readonly'    => true,
            'pinned'      => true,
            'choices'     => [],
            'multiple'    => false,
            'min'         => null,
            'max'         => null,
            'append'      => '',
        ];

        // Get type-specific field group.
        $type_group_key = self::TYPE_GROUP_MAP[$product_type] ?? null;

        error_log('[ERH AcfSchemaParser] get_schema("' . $product_type . '") — type_group_key: ' . ($type_group_key ?: 'NULL'));

        // Collect all groups to process.
        $groups_to_process = [];

        // Add shared groups first.
        foreach (self::SHARED_GROUPS as $shared_key) {
            $groups_to_process[] = $shared_key;
        }

        // Add type-specific group.
        if ($type_group_key) {
            $groups_to_process[] = $type_group_key;
        }

        error_log('[ERH AcfSchemaParser] Groups to process: ' . implode(', ', $groups_to_process));

        // Process each group.
        foreach ($groups_to_process as $group_key) {
            $group = acf_get_field_group($group_key);
            if (!$group) {
                error_log('[ERH AcfSchemaParser] Group not found: ' . $group_key);
                continue;
            }

            $fields = acf_get_fields($group_key);
            if (!$fields) {
                error_log('[ERH AcfSchemaParser] No fields for group: ' . $group_key);
                continue;
            }

            error_log('[ERH AcfSchemaParser] Loaded group "' . $group_key . '" with ' . count($fields) . ' top-level field(s)');

            $group_title = $group['title'] ?? 'Unknown';

            // Flatten fields recursively.
            $this->flatten_fields($fields, '', $group_title, $columns, $group_key);
        }

        // Cache and return.
        $this->schema_cache[$product_type] = $columns;
        return $columns;
    }

    /**
     * Get all available product types.
     *
     * @return array<string, string> Key => label mapping.
     */
    public function get_product_types(): array {
        $types = [];
        foreach (CategoryConfig::CATEGORIES as $key => $config) {
            $types[$key] = $config['name'];
        }
        return $types;
    }

    /**
     * Flatten nested ACF fields into column definitions.
     *
     * @param array  $fields      Array of ACF field definitions.
     * @param string $path_prefix Dot-notation path prefix.
     * @param string $group_label Group label for display.
     * @param array  $columns     Reference to columns array.
     * @return void
     */
    private function flatten_fields(array $fields, string $path_prefix, string $group_label, array &$columns, string $group_key = ''): void {
        foreach ($fields as $field) {
            $field_type = $field['type'] ?? '';
            $field_name = $field['name'] ?? '';
            $field_key = $field['key'] ?? '';

            // Skip if no name.
            if (empty($field_name)) {
                continue;
            }

            // Skip non-editable types.
            if (in_array($field_type, self::SKIP_TYPES, true)) {
                continue;
            }

            // Build the field path.
            $field_path = $path_prefix ? "{$path_prefix}.{$field_name}" : $field_name;

            // Handle group fields - recurse into sub_fields.
            if ($field_type === 'group' && !empty($field['sub_fields'])) {
                $sub_group_label = $field['label'] ?? $group_label;
                $this->flatten_fields($field['sub_fields'], $field_path, $sub_group_label, $columns, $group_key);
                continue;
            }

            // Skip if not an editable type.
            if (!in_array($field_type, self::EDITABLE_TYPES, true)) {
                continue;
            }

            // Build column definition.
            $column = [
                'key'       => $field_path,
                'acf_key'   => $field_key,
                'label'     => $field['label'] ?? $field_name,
                'group'     => $group_label,
                'group_key' => $group_key,
                'type'      => $this->normalize_field_type($field_type),
                'readonly'  => false,
                'pinned'    => false,
                'choices'   => $this->extract_choices($field),
                'multiple'  => !empty($field['multiple']),
                'min'       => $field['min'] ?? null,
                'max'       => $field['max'] ?? null,
                'step'      => $field['step'] ?? null,
                'append'    => $field['append'] ?? '',
                'prepend'   => $field['prepend'] ?? '',
                'instructions' => $field['instructions'] ?? '',
            ];

            // Handle true_false default.
            if ($field_type === 'true_false') {
                $column['default'] = !empty($field['default_value']);
            }

            $columns[] = $column;
        }
    }

    /**
     * Normalize ACF field type to editor type.
     *
     * @param string $acf_type ACF field type.
     * @return string Normalized editor type.
     */
    private function normalize_field_type(string $acf_type): string {
        switch ($acf_type) {
            case 'text':
            case 'email':
            case 'url':
                return 'text';

            case 'textarea':
                return 'textarea';

            case 'number':
                return 'number';

            case 'select':
            case 'radio':
                return 'select';

            case 'checkbox':
                return 'checkbox';

            case 'true_false':
                return 'boolean';

            default:
                return 'text';
        }
    }

    /**
     * Extract choices from select/checkbox fields.
     *
     * @param array $field ACF field definition.
     * @return array<string, string> Choices array.
     */
    private function extract_choices(array $field): array {
        if (empty($field['choices'])) {
            return [];
        }

        $choices = $field['choices'];

        // Normalize to key => label format.
        if (is_array($choices)) {
            return $choices;
        }

        return [];
    }

    /**
     * Get field value from a product using dot-notation path.
     *
     * @param int    $product_id Product post ID.
     * @param string $field_path Dot-notation field path.
     * @return mixed Field value or null.
     */
    public function get_field_value(int $product_id, string $field_path) {
        // Handle post_title specially.
        if ($field_path === 'post_title') {
            return get_the_title($product_id);
        }

        // Parse the path.
        $parts = explode('.', $field_path);

        // Get the root field.
        $root_field = array_shift($parts);
        $value = get_field($root_field, $product_id);

        // Navigate nested structure.
        foreach ($parts as $part) {
            if (!is_array($value) || !isset($value[$part])) {
                return null;
            }
            $value = $value[$part];
        }

        return $value;
    }

    /**
     * Set field value for a product using dot-notation path.
     *
     * @param int    $product_id Product post ID.
     * @param string $field_path Dot-notation field path.
     * @param mixed  $value      Value to set.
     * @return bool True on success.
     */
    public function set_field_value(int $product_id, string $field_path, $value): bool {
        // Handle post_title specially (not allowed to edit).
        if ($field_path === 'post_title') {
            return false;
        }

        // Parse the path.
        $parts = explode('.', $field_path);

        // If single field, update directly.
        if (count($parts) === 1) {
            // Note: update_field() returns false if value doesn't change, which isn't an error.
            // We call it and assume success - ACF handles the actual storage.
            update_field($field_path, $value, $product_id);
            return true;
        }

        // For nested fields, we need to get the full parent, modify, and save.
        $root_field = $parts[0];
        $full_value = get_field($root_field, $product_id);

        if (!is_array($full_value)) {
            $full_value = [];
        }

        // Navigate to parent and set value.
        $reference = &$full_value;
        $last_key = array_pop($parts);

        // Skip the root (already have it).
        array_shift($parts);

        foreach ($parts as $part) {
            if (!isset($reference[$part]) || !is_array($reference[$part])) {
                $reference[$part] = [];
            }
            $reference = &$reference[$part];
        }

        // Set new value.
        $reference[$last_key] = $value;

        // Update the root field.
        // Note: update_field() returns false if value doesn't change, which isn't an error.
        update_field($root_field, $full_value, $product_id);

        return true;
    }

    /**
     * Get all field values for a product.
     *
     * @param int   $product_id  Product post ID.
     * @param array $schema      Schema from get_schema().
     * @return array<string, mixed> Field path => value mapping.
     */
    public function get_all_field_values(int $product_id, array $schema): array {
        $values = [];

        foreach ($schema as $column) {
            $key = $column['key'];
            $values[$key] = $this->get_field_value($product_id, $key);
        }

        return $values;
    }

    /**
     * Validate a field value against its schema.
     *
     * @param mixed $value  Value to validate.
     * @param array $column Column schema.
     * @return array{valid: bool, message: string|null}
     */
    public function validate_value($value, array $column): array {
        $type = $column['type'] ?? 'text';

        switch ($type) {
            case 'number':
                if ($value !== '' && $value !== null && !is_numeric($value)) {
                    return ['valid' => false, 'message' => 'Value must be a number'];
                }
                // Only validate min/max if they are actually set (not null or empty string).
                if ($column['min'] !== null && $column['min'] !== '' && is_numeric($value) && $value < $column['min']) {
                    return ['valid' => false, 'message' => "Value must be at least {$column['min']}"];
                }
                if ($column['max'] !== null && $column['max'] !== '' && is_numeric($value) && $value > $column['max']) {
                    return ['valid' => false, 'message' => "Value must be at most {$column['max']}"];
                }
                break;

            case 'select':
                if (!empty($column['choices']) && $value !== '' && $value !== null) {
                    if (!isset($column['choices'][$value])) {
                        return ['valid' => false, 'message' => 'Invalid selection'];
                    }
                }
                break;

            case 'checkbox':
                if (!is_array($value) && $value !== '' && $value !== null) {
                    return ['valid' => false, 'message' => 'Value must be an array'];
                }
                break;

            case 'boolean':
                // Accept various boolean representations.
                if (!in_array($value, [true, false, 1, 0, '1', '0', '', null], true)) {
                    return ['valid' => false, 'message' => 'Value must be true or false'];
                }
                break;
        }

        return ['valid' => true, 'message' => null];
    }

    /**
     * Normalize a value based on field type.
     *
     * @param mixed $value  Raw value.
     * @param array $column Column schema.
     * @return mixed Normalized value.
     */
    public function normalize_value($value, array $column) {
        $type = $column['type'] ?? 'text';

        switch ($type) {
            case 'number':
                if ($value === '' || $value === null) {
                    return '';
                }
                return is_numeric($value) ? floatval($value) : $value;

            case 'boolean':
                // ACF true_false fields expect 1/0, not PHP true/false.
                return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

            case 'checkbox':
                if (is_string($value)) {
                    return array_filter(array_map('trim', explode(',', $value)));
                }
                return is_array($value) ? $value : [];

            default:
                return $value;
        }
    }
}
