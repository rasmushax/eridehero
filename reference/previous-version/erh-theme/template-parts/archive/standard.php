<?php
/**
 * Standard Category Archive Template
 *
 * Basic archive layout for non-hub categories.
 * Shows posts in a grid with pagination.
 *
 * Expected args:
 * - category (WP_Term): The category term object
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$category = $args['category'] ?? null;
?>

<?php get_header(); ?>

<main id="main-content" class="archive-main">
	<div class="container">

		<!-- Archive Header -->
		<header class="archive-header">
			<h1 class="archive-title">
				<?php
				if ( $category ) {
					echo esc_html( $category->name );
				} else {
					the_archive_title();
				}
				?>
			</h1>
			<?php if ( $category && ! empty( $category->description ) ) : ?>
				<p class="archive-subtitle"><?php echo esc_html( $category->description ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( have_posts() ) : ?>

			<!-- Posts Grid -->
			<div class="archive-grid">
				<?php
				while ( have_posts() ) :
					the_post();

					// Get first category for badge.
					$categories   = get_the_category();
					$category_name = ! empty( $categories ) ? erh_get_category_short_name( $categories[0]->name ) : '';
				?>
					<a href="<?php the_permalink(); ?>" class="archive-card">
						<div class="archive-card-img">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'medium_large', array( 'alt' => get_the_title() ) ); ?>
							<?php else : ?>
								<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/placeholder.jpg' ); ?>" alt="<?php the_title_attribute(); ?>">
							<?php endif; ?>

							<?php if ( $category_name ) : ?>
								<span class="archive-card-tag"><?php echo esc_html( $category_name ); ?></span>
							<?php endif; ?>
						</div>
						<div class="archive-card-content">
							<h3 class="archive-card-title"><?php the_title(); ?></h3>
							<span class="archive-card-meta"><?php echo esc_html( get_the_date( 'M j, Y' ) ); ?></span>
						</div>
					</a>
				<?php endwhile; ?>
			</div>

			<!-- Pagination -->
			<nav class="pagination" aria-label="<?php esc_attr_e( 'Pagination', 'erh' ); ?>">
				<?php
				$prev_link = get_previous_posts_link();
				$next_link = get_next_posts_link();
				?>

				<?php if ( $prev_link ) : ?>
					<span class="pagination-btn pagination-prev">
						<?php previous_posts_link( '&larr; ' . __( 'Previous', 'erh' ) ); ?>
					</span>
				<?php endif; ?>

				<?php if ( $next_link ) : ?>
					<span class="pagination-btn pagination-next">
						<?php next_posts_link( __( 'Next', 'erh' ) . ' &rarr;' ); ?>
					</span>
				<?php endif; ?>
			</nav>

		<?php else : ?>

			<!-- No Posts -->
			<div class="empty-state">
				<?php erh_the_icon( 'book' ); ?>
				<h3><?php esc_html_e( 'No posts found', 'erh' ); ?></h3>
				<p><?php esc_html_e( 'There are no posts in this category yet.', 'erh' ); ?></p>
			</div>

		<?php endif; ?>

	</div>
</main>

<?php get_footer(); ?>
