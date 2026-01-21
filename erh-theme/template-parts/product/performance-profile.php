<?php
/**
 * Product Performance Overview
 *
 * Displays a radar chart (category scores + bracket average) and strengths/weaknesses analysis.
 * Both are geo-dependent (price brackets vary by region) and loaded via JS with skeletons.
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

<section class="content-section performance-overview" id="performance" data-performance-profile data-product-id="<?php echo esc_attr( $product_id ); ?>" data-category="<?php echo esc_attr( $category_key ); ?>">
    <h2 class="section-title">Performance Overview</h2>

    <div class="performance-overview-content">
        <!-- Radar Chart (geo-dependent for bracket average, loaded via JS) -->
        <div class="performance-radar" data-radar-chart>
            <!-- Skeleton loading state -->
            <div class="performance-radar-skeleton" data-radar-skeleton>
                <div class="skeleton skeleton--circle" style="width: 280px; height: 280px; margin: 0 auto;"></div>
            </div>
            <!-- Chart rendered by JS -->
            <div class="performance-radar-content" data-radar-content style="display: none;"></div>
        </div>

        <!-- Strengths & Weaknesses (geo-dependent, loaded via JS) -->
        <div class="performance-analysis" data-product-analysis>
            <!-- Skeleton loading state (shown until JS hydrates) -->
            <div class="performance-analysis-skeleton" data-analysis-skeleton>
                <div class="performance-analysis-group">
                    <div class="skeleton skeleton-text" style="width: 80px; height: 16px; margin-bottom: 12px;"></div>
                    <div class="skeleton skeleton-text" style="width: 100%; height: 44px; margin-bottom: 8px;"></div>
                    <div class="skeleton skeleton-text" style="width: 100%; height: 44px; margin-bottom: 8px;"></div>
                    <div class="skeleton skeleton-text" style="width: 100%; height: 44px;"></div>
                </div>
                <div class="performance-analysis-group">
                    <div class="skeleton skeleton-text" style="width: 90px; height: 16px; margin-bottom: 12px;"></div>
                    <div class="skeleton skeleton-text" style="width: 100%; height: 44px; margin-bottom: 8px;"></div>
                    <div class="skeleton skeleton-text" style="width: 100%; height: 44px;"></div>
                </div>
            </div>

            <!-- Content rendered by JS -->
            <div class="performance-analysis-content" data-analysis-content style="display: none;">
                <!-- Strengths -->
                <div class="analysis-group analysis-strengths">
                    <h3 class="analysis-group-title">Strengths</h3>
                    <ul class="analysis-list" data-strengths-list>
                        <!-- Populated by JS -->
                    </ul>
                </div>

                <!-- Weaknesses -->
                <div class="analysis-group analysis-weaknesses">
                    <h3 class="analysis-group-title">Weaknesses</h3>
                    <ul class="analysis-list" data-weaknesses-list>
                        <!-- Populated by JS -->
                    </ul>
                </div>

                <!-- Bracket context with popover -->
                <div class="analysis-context" data-analysis-context>
                    <span class="analysis-context-text" data-context-text>
                        <!-- Populated by JS -->
                    </span>
                    <div class="popover-wrapper">
                        <button type="button" class="btn btn-link btn-sm" data-popover-trigger="bracket-info-popover">
                            <svg class="icon" aria-hidden="true"><use href="#icon-info"></use></svg>
                            How we compare
                        </button>
                        <div id="bracket-info-popover" class="popover popover--top" aria-hidden="true">
                            <div class="popover-arrow"></div>
                            <h4 class="popover-title">Price Bracket Comparison</h4>
                            <p class="popover-text">Strengths and weaknesses are determined by comparing this scooter against others in the same price range.</p>
                            <p class="popover-text">This ensures fair comparisons — a budget scooter is measured against budget competitors, not premium models.</p>
                            <p class="popover-text"><strong>Price Brackets:</strong></p>
                            <ul class="popover-list">
                                <li>Budget: Under $500</li>
                                <li>Mid-Range: $500–$1,000</li>
                                <li>Performance: $1,000–$1,500</li>
                                <li>Premium: $1,500–$2,500</li>
                                <li>Ultra: $2,500+</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error/empty state -->
            <div class="performance-analysis-empty" data-analysis-empty style="display: none;">
                <p class="analysis-empty-message">Unable to load comparison data.</p>
            </div>
        </div>
    </div>
</section>
