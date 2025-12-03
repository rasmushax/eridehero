<?php
// Ensure this file is being included by a parent file
if (!defined('ABSPATH')) exit;

$current_user_id = get_current_user_id();

// Query arguments
  global $wpdb;
    $table_name = $wpdb->prefix . 'price_trackers';

$query = $wpdb->prepare(
    "SELECT * FROM $table_name WHERE user_id = %d",
    $current_user_id
);

$trackers = $wpdb->get_results($query);


if ($trackers) :
    ?>
    <h1 class="account-title">My Price Trackers</h1>
    <div class="trackers-container">
	<table id="trackers" class="account-table display" style="width:100%">
	<thead>
		<tr>
			<th>Product</th>
			<th class="price-column">Start Price</th>
			<th class="price-column">Current Price</th>
			<th>Tracker</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
    <?php
     foreach ($trackers as $row) {
		 //print_r($row);
		 $prod = get_fields($row->product_id);
		 $name = get_the_title($row->product_id);
		 $perma = get_the_permalink($row->product_id);
		 $prices = getPrices($row->product_id);
	?>
	<tr>
		<td>
			<a class="account-table-prod" href="<?=$perma?>">
				<?php if($prod['big_thumbnail']){ echo '<div class="review-item-imgcon">'.wp_get_attachment_image($prod['big_thumbnail'],array(50,50),false,array("class" => "review-item-img")).'</div>'; } ?>
				<?=$name?>
			</a>
		</td>
		<td class="price-column col-start-price"><?=$row->start_price?></td>
		<td class="price-column col-current-price"><?=$row->current_price?></td>
		<td class="tracker-type">
			<?php
				if($row->target_price){
					echo "Price: $".number_format($row->target_price,2,'.','.');
			 } else {
				echo "Drop: $".number_format($row->price_drop,2,'.','.');
			}
			 ?>
		</td>
		<td class="tracker-btn"><button class="tracker-more tooltip"
				<?=(isset($prices[0]['price'])) ? 'data-price="'.$prices[0]['price'].'"' : ''; ?>
				<?=(isset($row->target_price)) ? 'data-target-price="'.$row->target_price.'"' : ''; ?>
				<?=(isset($row->price_drop)) ? 'data-price-drop="'.$row->price_drop.'"' : ''; ?>
				data-product-id="<?php echo esc_attr($row->product_id); ?>"
			>
			<svg><use xlink:href="#icon-more-vertical"></use></svg>
			<div class="tooltip-text">View Options</div>
		</button>
		</td>
	</tr>
	 <?php } ?>
	</tbody>
    </div>
	</table>
	<svg class="iconshidden">
		<symbol id="icon-more-vertical" viewBox="0 0 24 24">
			<path d="M14 12c0-0.552-0.225-1.053-0.586-1.414s-0.862-0.586-1.414-0.586-1.053 0.225-1.414 0.586-0.586 0.862-0.586 1.414 0.225 1.053 0.586 1.414 0.862 0.586 1.414 0.586 1.053-0.225 1.414-0.586 0.586-0.862 0.586-1.414zM14 5c0-0.552-0.225-1.053-0.586-1.414s-0.862-0.586-1.414-0.586-1.053 0.225-1.414 0.586-0.586 0.862-0.586 1.414 0.225 1.053 0.586 1.414 0.862 0.586 1.414 0.586 1.053-0.225 1.414-0.586 0.586-0.862 0.586-1.414zM14 19c0-0.552-0.225-1.053-0.586-1.414s-0.862-0.586-1.414-0.586-1.053 0.225-1.414 0.586-0.586 0.862-0.586 1.414 0.225 1.053 0.586 1.414 0.862 0.586 1.414 0.586 1.053-0.225 1.414-0.586 0.586-0.862 0.586-1.414z"></path>
		</symbol>
	</svg>
    <?php
else :
    echo '<div class="account-empty">
	<svg class="account-empty-icon" viewBox="0 0 24 24">	<path d="M4 9h16v11h-16zM1 2c-0.552 0-1 0.448-1 1v5c0 0.552 0.448 1 1 1h1v12c0 0.552 0.448 1 1 1h18c0.552 0 1-0.448 1-1v-12h1c0.552 0 1-0.448 1-1v-5c0-0.552-0.448-1-1-1zM2 4h20v3h-20zM10 13h4c0.552 0 1-0.448 1-1s-0.448-1-1-1h-4c-0.552 0-1 0.448-1 1s0.448 1 1 1z"></path></svg>
	<div class="account-empty-txt">You are not tracking any products at the moment. 🥺</div>
	</div>';
endif;
?>