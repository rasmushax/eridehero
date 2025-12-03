<?php
/**
 * Related Posts Block Template.
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during backend preview render.
 * @param   int $post_id The post ID the block is rendering content against.
 *          This is either the post ID currently being displayed inside a query loop,
 *          or the post ID of the post hosting this block.
 * @param   array $context The context provided to the block by the post or it's parent block.
 */
$review = null;
$dynamic = get_field('dynamic');
$small = get_field('small');

// Always fetch dynamic data as fallback
$id = get_field('product');
$relationship = get_fields($id);
$dynamic_name = $relationship['brand']." ".$relationship['model'];
$dynamic_thumbnail = wp_get_attachment_image($relationship['big_thumbnail'], "thumbnail");
if($relationship['review']['review_post']){
	$review = get_permalink($relationship['review']['review_post']);
}

$prices = getPrices($id);

$dynamic_link = "#";
if (is_array($prices) && !empty($prices) && isset($prices[0]['price']) && $prices[0]['price'] > 0) {
	$dynamic_link = $prices[0]['url'];
}

// Get manual data
$manual = get_field('manual');

// Determine final values with fallback logic
if($dynamic == 1){
	// Pure dynamic mode
	$name = $dynamic_name;
	$thumbnail = $dynamic_thumbnail;
	$link = $dynamic_link;
	$price_now = isset($prices[0]['price']) ? $prices[0]['price'] : null;
	$price_was = isset($prices[0]['priceOld']) ? $prices[0]['priceOld'] : null;
	$percentage_saved = isset($prices[0]['percentageSaved']) ? $prices[0]['percentageSaved'] : null;
} else {
	// Manual mode with dynamic fallbacks
	$name = !empty($manual['name']) ? $manual['name'] : $dynamic_name;
	$thumbnail = !empty($manual['image']) ? wp_get_attachment_image($manual['image'], "thumbnail") : $dynamic_thumbnail;
	$link = !empty($manual['link']) ? $manual['link'] : $dynamic_link;
	$price_now = !empty($manual['price_now']) ? $manual['price_now'] : (isset($prices[0]['price']) ? $prices[0]['price'] : null);
	$price_was = !empty($manual['price_was']) ? $manual['price_was'] : (isset($prices[0]['priceOld']) ? $prices[0]['priceOld'] : null);
	
	// Calculate percentage for manual prices, or use dynamic
	if($price_was && $price_now && $price_now !== $price_was){ 
		$percentage_saved = round((($price_was - $price_now) / $price_was) * 100);
	} else {
		$percentage_saved = isset($prices[0]['percentageSaved']) ? $prices[0]['percentageSaved'] : null;
	}
	
	// Fallback for review link if not set in dynamic
	if(!$review && !empty($manual['review_link'])){
		$review = $manual['review_link'];
	}
}

if(!$small){	
?>
<div class="dealcon">
	<a rel="external noopener sponsored" class="afftrigger" target="_blank" href="<?=afflink($link)?>">
		<?=$thumbnail?>
	</a>
	<div class="dealcontent">
		<div class="dealtitle"><?=$name?></div>
		<a rel="external noopener sponsored" href="<?=afflink($link)?>" target="_blank" class="dealpricing afftrigger">
			<?php if($price_now): ?>
				<span class="dealnow">Now: $<?=$price_now?></span>
			<?php endif; ?>
			<?php if($price_was): ?>
				<span class="dealwas">Was: $<?=$price_was?></span>
			<?php endif; ?>
			<?php if($percentage_saved): ?>
				<span class="dealsavings">-<?=$percentage_saved?>%</span>
			<?php endif; ?>
		</a>
	</div>
	<div class="dealtext"><?=get_field('description')?></div>
	<div class="dealbtns">
		<a href="<?=afflink($link)?>" rel="external noopener sponsored" target="_blank" class="dealbtn afftrigger">Get Best Deal</a>
		<?php if($review): ?>
			<a href="<?=$review?>" target="_blank" class="dealreviewlink">Read Review</a>
		<?php endif; ?>
	</div>
</div>
<?php } else { ?>

<div class="dealcon small">
	<a rel="external noopener sponsored" target="_blank" class="afftrigger" href="<?=afflink($link)?>">
		<?=$thumbnail?>
	</a>
	<div class="dealcontent">
		<div class="dealtitle"><?=$name?></div>
		<a rel="external noopener sponsored" href="<?=afflink($link)?>" target="_blank" class="dealpricing afftrigger">
			<?php if($price_now): ?>
				<span class="dealnow">Now: $<?=$price_now?></span>
			<?php endif; ?>
			<?php if($price_was): ?>
				<span class="dealwas">Was: $<?=$price_was?></span>
			<?php endif; ?>
			<?php if($percentage_saved): ?>
				<span class="dealsavings">-<?=$percentage_saved?>%</span>
			<?php endif; ?>
		</a>
		<?php if(get_field('description')): ?>
			<div class="dealtext"><?=get_field('description')?></div>
		<?php endif; ?>
	</div>
	<div class="dealbtns">
		<a href="<?=afflink($link)?>" rel="external noopener sponsored" target="_blank" class="dealbtn afftrigger">Get Best Deal</a>
		<?php if($review): ?>
			<a href="<?=$review?>" target="_blank" class="dealreviewlink">Read Review</a>
		<?php endif; ?>
	</div>
</div>

<?php } ?>