<?php
/**
 * Product Performance Profile
 *
 * Displays a radar chart showing category scores and "Great for..." highlights.
 * Scores are calculated client-side using the same compare-config.js scoring system.
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
?>

<section class="review-section performance-profile" id="performance" data-performance-profile data-product-id="<?php echo esc_attr( $product_id ); ?>" data-category="<?php echo esc_attr( $category_key ); ?>">
    <h2 class="review-section-title">Performance Profile</h2>

    <div class="performance-profile-content">
        <!-- Radar Chart Container -->
        <div class="performance-radar" data-radar-chart>
            <!-- Loading state -->
            <div class="performance-radar-loading" data-radar-loading>
                <div class="skeleton" style="width: 280px; height: 280px; border-radius: 50%;"></div>
            </div>
            <!-- Chart rendered by JS -->
        </div>

        <!-- Highlights / "Great for..." -->
        <div class="performance-highlights" data-performance-highlights>
            <h3 class="performance-highlights-title">Great for...</h3>
            <ul class="performance-highlights-list" data-highlights-list>
                <!-- Loading state -->
                <li class="performance-highlight-item" data-highlight-skeleton>
                    <span class="skeleton skeleton-text" style="width: 180px;"></span>
                </li>
                <li class="performance-highlight-item" data-highlight-skeleton>
                    <span class="skeleton skeleton-text" style="width: 150px;"></span>
                </li>
                <li class="performance-highlight-item" data-highlight-skeleton>
                    <span class="skeleton skeleton-text" style="width: 160px;"></span>
                </li>
            </ul>
            <!-- Empty state (shown if no highlights) -->
            <p class="performance-highlights-empty" data-highlights-empty style="display: none;">
                Scores are being calculated...
            </p>
        </div>
    </div>

    <!-- Category Score Bars (below chart) -->
    <div class="performance-scores" data-score-bars>
        <!-- Score bars rendered by JS -->
    </div>
</section>
