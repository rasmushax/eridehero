<?php
/**
 * Single Standard Post Template Part
 *
 * Template for regular posts (without the "review" tag).
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<main id="main-content" class="content-page">
    <div class="container">
        <div class="content-with-sidebar">
            <article <?php post_class( 'post-content' ); ?>>

                <!-- Breadcrumb -->
                <?php erh_the_breadcrumbs(); ?>

                <!-- Post Header -->
                <header class="post-header">
                    <h1 class="post-title"><?php the_title(); ?></h1>

                    <div class="post-meta">
                        <span class="post-date"><?php echo get_the_date(); ?></span>
                        <span class="post-author">
                            <?php esc_html_e( 'by', 'erh' ); ?>
                            <?php the_author(); ?>
                        </span>
                    </div>
                </header>

                <!-- Featured Image -->
                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="post-featured-image">
                        <?php the_post_thumbnail( 'large' ); ?>
                    </div>
                <?php endif; ?>

                <!-- Post Content -->
                <div class="post-body">
                    <?php the_content(); ?>
                </div>

                <!-- Post Footer -->
                <footer class="post-footer">
                    <?php
                    $tags = get_the_tags();
                    if ( $tags ) :
                        ?>
                        <div class="post-tags">
                            <?php foreach ( $tags as $tag ) : ?>
                                <a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>" class="tag">
                                    <?php echo esc_html( $tag->name ); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </footer>

            </article>

            <!-- Sidebar -->
            <aside class="sidebar">
                <?php get_template_part( 'template-parts/components/sidebar-about' ); ?>
            </aside>
        </div>
    </div>
</main>
