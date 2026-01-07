<?php
/**
 * Deals Page Hero
 *
 * Reusable hero for both hub and category deals pages.
 * Follows finder-page-header pattern for consistency.
 *
 * @param array $args {
 *     @type string $title       Page title (e.g., "E-Scooter Deals")
 *     @type string $description Page description
 *     @type string $category    Category key for API (e.g., "escooter") - null for hub
 *     @type string $back_url    Back link URL - null for hub
 *     @type string $back_text   Back link text (default: "All Deals")
 * }
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Extract args with defaults.
$title       = $args['title'] ?? 'Deals';
$description = $args['description'] ?? '';
$category    = $args['category'] ?? null;
$back_url    = $args['back_url'] ?? null;
$back_text   = $args['back_text'] ?? 'All Deals';

// Determine if this is a category page (has back link).
$is_category = ! empty( $back_url );
?>

<section class="deals-hero">
    <div class="container">
        <?php if ( $is_category && $back_url ) : ?>
            <a href="<?php echo esc_url( $back_url ); ?>" class="deals-hero-back">
                <?php erh_the_icon( 'arrow-left' ); ?>
                <span><?php echo esc_html( $back_text ); ?></span>
            </a>
        <?php endif; ?>

        <div class="deals-hero-content">
            <div class="deals-hero-text">
                <h1 class="deals-hero-title"><?php echo esc_html( $title ); ?></h1>
                <?php if ( $description ) : ?>
                    <p class="deals-hero-description"><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>
            </div>

            <?php if ( $is_category ) : ?>
                <div class="deals-hero-meta" data-deals-meta>
                    <span class="deals-hero-count" data-deals-count>
                        <!-- Populated by JS after geo detection -->
                        <span class="skeleton skeleton-text" style="width: 120px;"></span>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
