<?php
// Ensure this file is being included by a parent file
if (!defined('ABSPATH')) exit;

$current_user_id = get_current_user_id();

// Query arguments
$args = array(
    'post_type' => 'review',
    'author' => $current_user_id,
    'post_status' => array('publish', 'pending'),
    'posts_per_page' => -1,
);

// Get the reviews
$reviews_query = new WP_Query($args);

if ($reviews_query->have_posts()) :
    ?>
    <h1 class="account-title">My Reviews</h1>
    <div class="reviews-container">
    <?php
    while ($reviews_query->have_posts()) : $reviews_query->the_post();
        $review_id = get_the_ID();
        $review_text = get_post_meta($review_id, 'text', true);
        $review_score = get_post_meta($review_id, 'score', true);
        $product_id = get_post_meta($review_id, 'product', true);
        $review_status = get_post_status();
		
		if($review_status == "pending"){
			$status = "pending";			
		} elseif($review_status == "publish"){
			$status = "published";
		}

        // Get product fields
        $product_fields = get_fields($product_id);
		//print_r($product_fields);
        $product_title = get_the_title($product_id);
        ?>
        <div class="review-item">
			<div class="review-item-top">
				<a class="review-item-link" href="<?=get_the_permalink($product_id)?>">
					<div class="review-item-imgcon">
						<?=wp_get_attachment_image($product_fields['big_thumbnail'],array(50,50),false,array("class" => "review-item-img"))?>
					</div>
					<div class="review-item-top-right">
						<div class="review-item-title">
							<?php echo esc_html($product_title); ?>
							<div class="review-item-status review-<?=$status?>"><?=esc_html(ucfirst($status))?></div>
						</div>
						<div class="review-item-time"><?=time_elapsed_string(get_the_date('c'))?></div>
					</div>
				</a>
				<div class="review-item-stars review-stars-4">
				  <svg class="review-item-star"><use xlink:href="#icon-star"></use></svg>
				  <svg class="review-item-star"><use xlink:href="#icon-star"></use></svg>
				  <svg class="review-item-star"><use xlink:href="#icon-star"></use></svg>
				  <svg class="review-item-star"><use xlink:href="#icon-star"></use></svg>
				  <svg class="review-item-star"><use xlink:href="#icon-star"></use></svg>
				</div>
			</div>
			<div class="review-item-body"><?php echo esc_html($review_text); ?></div>
        </div>
        <?php
    endwhile;
    ?>
    </div>
	<svg class="iconshidden">
		<symbol id="icon-star" viewBox="0 0 26 28">
			<title>star</title>
			<path d="M26 10.109c0 0.281-0.203 0.547-0.406 0.75l-5.672 5.531 1.344 7.812c0.016 0.109 0.016 0.203 0.016 0.313 0 0.406-0.187 0.781-0.641 0.781-0.219 0-0.438-0.078-0.625-0.187l-7.016-3.687-7.016 3.687c-0.203 0.109-0.406 0.187-0.625 0.187-0.453 0-0.656-0.375-0.656-0.781 0-0.109 0.016-0.203 0.031-0.313l1.344-7.812-5.688-5.531c-0.187-0.203-0.391-0.469-0.391-0.75 0-0.469 0.484-0.656 0.875-0.719l7.844-1.141 3.516-7.109c0.141-0.297 0.406-0.641 0.766-0.641s0.625 0.344 0.766 0.641l3.516 7.109 7.844 1.141c0.375 0.063 0.875 0.25 0.875 0.719z"></path>
		</symbol>
	</svg>
    <?php
    wp_reset_postdata();
else :
    echo '<div class="account-empty">
	<svg class="account-empty-icon" viewBox="0 0 24 24">	<path d="M4 9h16v11h-16zM1 2c-0.552 0-1 0.448-1 1v5c0 0.552 0.448 1 1 1h1v12c0 0.552 0.448 1 1 1h18c0.552 0 1-0.448 1-1v-12h1c0.552 0 1-0.448 1-1v-5c0-0.552-0.448-1-1-1zM2 4h20v3h-20zM10 13h4c0.552 0 1-0.448 1-1s-0.448-1-1-1h-4c-0.552 0-1 0.448-1 1s0.448 1 1 1z"></path></svg>
	<div class="account-empty-txt">You have not submitted any reviews yet. 🥺</div>
	</div>';
endif;
?>