<?php
/**
 * Deals Page Hero
 *
 * Reusable hero for both hub and category deals pages.
 * Follows finder-page-header pattern for consistency.
 *
 * @param array $args {
 *     @type string $title            Page title from WordPress
 *     @type string $content          Page content from WordPress (intro text)
 *     @type string $category         Category key for API (e.g., "escooter") - null for hub
 *     @type string $back_url         Parent deals page URL - null for hub
 *     @type string $breadcrumb_title Short title for breadcrumb (e.g., "Electric Scooters")
 * }
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Extract args with defaults.
$title            = $args['title'] ?? 'Deals';
$content          = $args['content'] ?? '';
$category         = $args['category'] ?? null;
$back_url         = $args['back_url'] ?? null;
$breadcrumb_title = $args['breadcrumb_title'] ?? '';

// Determine if this is a category page (has back link).
$is_category = ! empty( $back_url );
?>

<section class="deals-hero">
    <div class="container">
        <?php
        if ( $is_category && $back_url ) {
            erh_breadcrumb( [
                [ 'label' => 'Deals', 'url' => $back_url ],
                [ 'label' => $breadcrumb_title ],
            ] );
        }
        ?>

        <div class="deals-hero-content">
            <div class="deals-hero-text">
                <h1 class="deals-hero-title"><?php echo esc_html( $title ); ?></h1>
                <?php if ( $content ) : ?>
                    <div class="deals-hero-intro"><?php echo wp_kses_post( $content ); ?></div>
                <?php endif; ?>
            </div>

            <?php if ( $is_category ) : ?>
                <div class="deals-hero-meta" data-deals-meta>
                    <span class="deals-hero-count" data-deals-count>
                        <span class="skeleton skeleton-text" style="display: inline-block; width: 120px; height: 17px; margin-bottom: 0;"></span>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
