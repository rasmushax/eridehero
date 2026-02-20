<?php
/**
 * Article Hero Component
 *
 * Featured image hero with title and excerpt for articles/guides.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_id = get_the_ID();
?>

<header class="article-hero">
    <?php if ( has_post_thumbnail() ) : ?>
        <div class="article-hero-image">
            <?php the_post_thumbnail( 'large', array( 'class' => 'article-hero-img' ) ); ?>
        </div>
    <?php endif; ?>

    <div class="article-hero-content">
        <?php if ( has_excerpt() ) : ?>
            <p class="article-hero-excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
        <?php endif; ?>
    </div>
</header>
