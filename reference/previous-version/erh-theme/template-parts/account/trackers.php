<?php
/**
 * Account Trackers Component
 *
 * Displays the price trackers table with search, sort, edit, and delete functionality.
 * Data is loaded via JS from /wp-json/erh/v1/user/trackers
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="trackers-section" data-trackers>
	<header class="trackers-header">
		<h1 class="account-title">Price Trackers</h1>

		<div class="trackers-search search-input-wrapper">
			<?php erh_the_icon( 'search' ); ?>
			<input
				type="text"
				placeholder="Find a tracker..."
				data-trackers-search
				aria-label="Search trackers"
			>
		</div>
	</header>

	<!-- Loading State -->
	<div class="trackers-loading" data-trackers-loading>
		<div class="loading-spinner"></div>
		<span>Loading your trackers...</span>
	</div>

	<!-- Trackers Table -->
	<div class="trackers-table-wrapper" data-trackers-table-wrapper hidden>
		<table class="trackers-table">
			<thead>
				<tr>
					<th class="trackers-th trackers-th--product" data-sort="name">
						<button type="button" class="trackers-sort-btn">
							Product
							<?php erh_the_icon( 'sort', 'sort-icon sort-icon--neutral' ); ?>
							<?php erh_the_icon( 'sort-up', 'sort-icon sort-icon--asc' ); ?>
							<?php erh_the_icon( 'sort-up', 'sort-icon sort-icon--desc' ); ?>
						</button>
					</th>
					<th class="trackers-th trackers-th--start" data-sort="start_price">
						<button type="button" class="trackers-sort-btn">
							Start
							<?php erh_the_icon( 'sort', 'sort-icon sort-icon--neutral' ); ?>
							<?php erh_the_icon( 'sort-up', 'sort-icon sort-icon--asc' ); ?>
							<?php erh_the_icon( 'sort-up', 'sort-icon sort-icon--desc' ); ?>
						</button>
					</th>
					<th class="trackers-th trackers-th--current" data-sort="current_price">
						<button type="button" class="trackers-sort-btn">
							Current
							<?php erh_the_icon( 'sort', 'sort-icon sort-icon--neutral' ); ?>
							<?php erh_the_icon( 'sort-up', 'sort-icon sort-icon--asc' ); ?>
							<?php erh_the_icon( 'sort-up', 'sort-icon sort-icon--desc' ); ?>
						</button>
					</th>
					<th class="trackers-th trackers-th--tracker">Alert</th>
					<th class="trackers-th trackers-th--actions">
						<span class="visually-hidden">Actions</span>
					</th>
				</tr>
			</thead>
			<tbody data-trackers-body>
				<!-- Rows populated via JS -->
			</tbody>
		</table>
	</div>

	<!-- Empty State -->
	<div class="trackers-empty" data-trackers-empty hidden>
		<?php erh_the_icon( 'bell', 'trackers-empty-icon' ); ?>
		<h2 class="trackers-empty-title">No price trackers yet</h2>
		<p class="trackers-empty-text">
			Set up price alerts on products you're interested in, and we'll notify you when prices drop.
		</p>
		<a href="<?php echo esc_url( home_url( '/reviews/' ) ); ?>" class="btn btn-primary">
			Browse Products
		</a>
	</div>

	<!-- Error State -->
	<div class="trackers-error" data-trackers-error hidden>
		<?php erh_the_icon( 'x', 'trackers-error-icon' ); ?>
		<h2 class="trackers-error-title">Unable to load trackers</h2>
		<p class="trackers-error-text">
			Something went wrong. Please try again.
		</p>
		<button type="button" class="btn btn-secondary" data-trackers-retry>
			Try Again
		</button>
	</div>
</div>
