<?php
/**
 * Front Page Template
 *
 * The template for the homepage.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

    <?php get_template_part( 'template-parts/home/hero' ); ?>

    <?php get_template_part( 'template-parts/home/as-seen-on' ); ?>

    <?php get_template_part( 'template-parts/home/features' ); ?>

    <?php get_template_part( 'template-parts/home/comparison' ); ?>

    <?php get_template_part( 'template-parts/home/deals' ); ?>

    <?php get_template_part( 'template-parts/home/buying-guides' ); ?>

    <?php get_template_part( 'template-parts/home/reviews' ); ?>

    <?php get_template_part( 'template-parts/home/articles' ); ?>

    <?php get_template_part( 'template-parts/home/youtube' ); ?>

<?php
get_footer();
