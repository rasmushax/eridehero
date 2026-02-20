<?php
/**
 * Default Page Template
 *
 * Used for standard WordPress pages (editorial, disclaimers, etc.)
 * that use Gutenberg content without custom layouts.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<main id="main-content" class="page-default">
    <article class="page-content">
        <?php if ( has_post_thumbnail() ) : ?>
            <div class="page-hero-image">
                <?php the_post_thumbnail( 'large' ); ?>
            </div>
        <?php endif; ?>

        <h1 class="page-title"><?php the_title(); ?></h1>
        <p class="page-updated">Last updated: <?php echo get_the_modified_date( 'F Y' ); ?></p>

        <div class="article-body">
            <?php the_content(); ?>
        </div>
    </article>
</main>

<?php
get_footer();
