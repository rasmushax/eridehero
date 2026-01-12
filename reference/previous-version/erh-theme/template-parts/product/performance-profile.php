<?php
/**
 * Product Performance Profile
 *
 * Displays a radar chart and "What to Know" insights.
 * Phase 11 TODO: Replace hardcoded insights with real percentile-based data.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type int    $product_id   Product ID.
 *     @type string $product_type Product type label.
 *     @type string $category_key Category key (escooter, ebike, etc.).
 * }
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Extract args.
$product_id   = $args['product_id'] ?? 0;
$product_type = $args['product_type'] ?? '';
$category_key = $args['category_key'] ?? 'escooter';

if ( ! $product_id ) {
    return;
}

// TODO Phase 11: Replace with real percentile-based insights from database.
// For now, hardcoded examples to nail the visual design.
$insights = array(
    array(
        'type'  => 'positive',
        'label' => '40mi range',
        'note'  => 'above average',
    ),
    array(
        'type'  => 'positive',
        'label' => 'Tubeless tires',
        'note'  => 'low maintenance',
    ),
    array(
        'type'  => 'neutral',
        'label' => '48.7 lbs',
        'note'  => 'heavier than most',
    ),
    array(
        'type'  => 'neutral',
        'label' => '6h charge time',
        'note'  => 'slower than average',
    ),
);
?>

<section class="content-section performance-profile" id="performance" data-performance-profile data-product-id="<?php echo esc_attr( $product_id ); ?>" data-category="<?php echo esc_attr( $category_key ); ?>">
    <h2 class="section-title">Performance Profile</h2>

    <div class="performance-profile-content">
        <!-- Radar Chart Container -->
        <div class="performance-radar" data-radar-chart>
            <!-- Loading state -->
            <div class="performance-radar-loading" data-radar-loading>
                <div class="skeleton" style="width: 280px; height: 280px; border-radius: 50%;"></div>
            </div>
            <!-- Chart rendered by JS -->
        </div>

        <!-- What to Know Insights -->
        <div class="performance-insights">
            <h3 class="performance-insights-title">What to Know</h3>
            <ul class="performance-insights-list">
                <?php foreach ( $insights as $insight ) : ?>
                    <li class="performance-insight performance-insight--<?php echo esc_attr( $insight['type'] ); ?>">
                        <?php if ( 'positive' === $insight['type'] ) : ?>
                            <?php echo erh_icon( 'check', 'performance-insight-icon' ); ?>
                        <?php elseif ( 'negative' === $insight['type'] ) : ?>
                            <?php echo erh_icon( 'x', 'performance-insight-icon' ); ?>
                        <?php else : ?>
                            <?php echo erh_icon( 'info', 'performance-insight-icon' ); ?>
                        <?php endif; ?>
                        <span class="performance-insight-text">
                            <strong><?php echo esc_html( $insight['label'] ); ?></strong>
                            <span class="performance-insight-note"><?php echo esc_html( $insight['note'] ); ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>
