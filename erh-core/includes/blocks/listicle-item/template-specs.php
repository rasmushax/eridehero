<?php
/**
 * Listicle Item Specs Tab Template
 *
 * AJAX-loaded specs content for the listicle item block.
 * Shows performance tests first, then collapsible spec groups.
 *
 * @package ERH\Blocks
 *
 * Variables passed from REST endpoint:
 * @var int    $product_id   Product ID.
 * @var string $category_key Category key (escooter, ebike, etc.).
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Get product data from cache.
$product_data = erh_get_product_cache_data($product_id);

if (!$product_data || empty($product_data['specs'])) {
    echo '<p class="listicle-item-error">' . esc_html__('Specifications not available.', 'erh-core') . '</p>';
    return;
}

$specs = $product_data['specs'];

// Ensure specs is an array.
if (!is_array($specs)) {
    $specs = maybe_unserialize($specs);
}

if (!is_array($specs)) {
    echo '<p class="listicle-item-error">' . esc_html__('Specifications not available.', 'erh-core') . '</p>';
    return;
}

// Get spec groups configuration.
$spec_groups = erh_get_product_spec_groups_config($category_key);

if (empty($spec_groups)) {
    echo '<p class="listicle-item-error">' . esc_html__('Specifications not available for this product type.', 'erh-core') . '</p>';
    return;
}

// Get the wrapper key for nested specs.
$nested_wrapper = erh_get_specs_wrapper_key($category_key);

// Check for performance test data.
$performance_specs = $product_data['performance'] ?? [];
if (!is_array($performance_specs)) {
    $performance_specs = maybe_unserialize($performance_specs) ?: [];
}

// Build performance items from spec groups config.
$test_results_config = $spec_groups['ERideHero Test Results'] ?? null;
$performance_items = [];

// Shorter labels for listicle display.
$short_labels = [
    'Top Speed (Tested)'    => 'Top Speed',
    'Range (Regular Riding)' => 'Range (Regular)',
    'Range (Fast Riding)'    => 'Range (Fast)',
    'Range (Eco Mode)'       => 'Range (Eco)',
];

if ($test_results_config) {
    foreach ($test_results_config['specs'] as $spec_def) {
        $value = erh_get_spec_from_cache($specs, $spec_def['key'], $nested_wrapper);
        $formatted = erh_format_spec_value($value, $spec_def);

        if ($formatted === '' || $formatted === 'N/A') {
            continue;
        }

        // Use shorter label if available.
        $label = $short_labels[$spec_def['label']] ?? $spec_def['label'];

        $performance_items[] = [
            'label' => $label,
            'value' => $formatted,
        ];
    }
}
$has_performance = !empty($performance_items);
?>

<!-- Performance Tests Section (First) -->
<?php if ($has_performance) : ?>
<?php $popover_id = 'how-we-test-' . $product_id; ?>
<div class="listicle-specs-performance">
    <div class="listicle-specs-performance-header">
        <div class="listicle-specs-performance-title">
            <?php erh_the_icon('zap'); ?>
            <span><?php esc_html_e('ERideHero Test Results', 'erh-core'); ?></span>
        </div>
        <div class="popover-wrapper">
            <button type="button" class="listicle-specs-how-we-test" data-popover-trigger="<?php echo esc_attr($popover_id); ?>">
                <?php erh_the_icon('info'); ?>
                <span><?php esc_html_e('How we test', 'erh-core'); ?></span>
            </button>
            <div id="<?php echo esc_attr($popover_id); ?>" class="popover popover--bottom" aria-hidden="true">
                <div class="popover-arrow"></div>
                <h4 class="popover-title"><?php esc_html_e('Data-driven testing', 'erh-core'); ?></h4>
                <p class="popover-text"><?php esc_html_e('All performance data is captured using a VBox Sport GPS logger — professional-grade equipment for precise vehicle measurements. Tests follow strict protocols with a 175 lb rider under controlled conditions.', 'erh-core'); ?></p>
                <a href="/how-we-test/" class="popover-link">
                    <?php esc_html_e('Full methodology', 'erh-core'); ?>
                    <?php erh_the_icon('arrow-right'); ?>
                </a>
            </div>
        </div>
    </div>
    <div class="listicle-specs-performance-grid">
        <?php foreach ($performance_items as $item) : ?>
            <div class="listicle-specs-performance-item">
                <span class="listicle-specs-performance-value"><?php echo esc_html($item['value']); ?></span>
                <span class="listicle-specs-performance-label"><?php echo esc_html($item['label']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Spec Groups (Accordion) -->
<div class="listicle-specs-accordion" data-specs-accordion>
    <?php
    $group_index = 0;
    foreach ($spec_groups as $group_name => $group_config) :
        // Skip test results group (handled above).
        if ($group_name === 'ERideHero Test Results') {
            continue;
        }

        $spec_defs = $group_config['specs'] ?? [];

        // Build spec rows.
        $rows = [];
        foreach ($spec_defs as $spec_def) {
            // Special handling for feature_check format.
            if (($spec_def['format'] ?? '') === 'feature_check') {
                $feature_value = $spec_def['feature_value'] ?? '';
                $raw_value = erh_get_spec_from_cache($specs, $spec_def['key'], $nested_wrapper);

                // Determine if feature is present.
                if ($feature_value === true) {
                    $has_feature = !empty($raw_value) && $raw_value !== 'No' && $raw_value !== 'no' && $raw_value !== '0';
                } else {
                    $has_feature = is_array($raw_value) && in_array($feature_value, $raw_value, true);
                }

                $rows[] = [
                    'label' => $spec_def['label'],
                    'value' => $has_feature ? __('Yes', 'erh-core') : __('No', 'erh-core'),
                    'class' => $has_feature ? 'feature-yes' : 'feature-no',
                ];
                continue;
            }

            $value = erh_get_spec_from_cache($specs, $spec_def['key'], $nested_wrapper);
            $formatted = erh_format_spec_value($value, $spec_def);

            // Skip empty values.
            if ($formatted === '' || $formatted === 'No') {
                continue;
            }

            $rows[] = [
                'label' => $spec_def['label'],
                'value' => $formatted,
                'class' => '',
            ];
        }

        // Skip empty groups.
        if (empty($rows)) {
            continue;
        }

        $is_open = $group_index === 0; // First group open by default.
        $group_index++;
        ?>
        <div class="listicle-specs-group<?php echo $is_open ? ' is-open' : ''; ?>">
            <button type="button" class="listicle-specs-group-header" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
                <span class="listicle-specs-group-title"><?php echo esc_html($group_name); ?></span>
                <span class="listicle-specs-group-count"><?php echo count($rows); ?></span>
                <?php erh_the_icon('chevron-down'); ?>
            </button>
            <div class="listicle-specs-group-content"<?php echo !$is_open ? ' hidden' : ''; ?>>
                <table class="listicle-specs-table">
                    <?php foreach ($rows as $row) : ?>
                        <tr<?php echo $row['class'] ? ' class="' . esc_attr($row['class']) . '"' : ''; ?>>
                            <td><?php echo esc_html($row['label']); ?></td>
                            <td><?php echo esc_html($row['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>
