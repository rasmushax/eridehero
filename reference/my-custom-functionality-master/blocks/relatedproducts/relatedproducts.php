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

$products = get_field('related_products');

global $post;

$current = get_field('relationship',$post->ID)[0];
$link = get_fields($current);

//print_r($link);


?>

<div class="related_products_container">
	<div class="related_products_title">Alternatives to <?=$link['brand']." ".$link['model']?></div>
	<div class="related_products_prods">
		<?php 
		foreach($products as $key => $product) { 
			$fields = get_fields(get_field('relationship',$product->ID)[0]);
		?>
		<a href="<?=get_permalink($product->ID)?>" class="related_products_prod">
			<div class="related_products_prod_imgcon">
				<?=wp_get_attachment_image(get_post_thumbnail_id($product->ID))?>
				<div class="related_products_prod_rating"><?=$fields['ratings']['overall']?> / 10</div>
			</div>
			<div class="related_products_prod_right">
				<div class="related_products_prod_right_top">
					<div class="related_products_prod_title"><?=$fields['brand']." ".$fields['model']?></div>
					<?php
						$metas = get_post_meta(get_field('relationship',$product->ID)[0]);

						$useful = array();

						foreach($metas as $key => $meta){
							if(strpos($key, "_cegg_data") !== false){		
								$unserialize = unserialize($meta[0]);
								$key2 = array_keys($unserialize)[0];
								$useful[] = $unserialize[$key2];	
							}
						}

						$oos = array(); $is = array(); $isunknownprice = array(); $finaldomain = NULL;

						usort($useful, fn($a, $b) => $a['price'] <=> $b['price']);
						
						

						if(count($useful) > 0){
		
						$useful2 = array();
						$useful3 = array();
						$useful4 = array();
							foreach($useful as $key => $item){
								if($item['price'] == 0 && $item['stock_status'] == 1) {
									$useful2[] = $item;
									unset($useful[$key]);
								} elseif($item['stock_status'] == -1){
									$useful3[] = $item;
									unset($useful[$key]);
								} elseif($item['stock_status'] == -1 && $item['price'] == 0) {
									$useful4[] = $item;
									unset($useful[$key]);
								}
							}

							$useful = array_merge($useful,$useful2);
							$useful = array_merge($useful,$useful3);
							$useful = array_merge($useful,$useful4);
							
						//	print_r($useful);
			
					?>
					<div class="related_products_prod_price"><?=$useful[0]['currency'].$useful[0]['price']. " ".$useful[0]['currencyCode']?></div>
					<?php } ?>
				</div>
				<div class="related_products_prod_right_bottom">Read Review</div>
			</div>
		</a>
		<?php } ?>
	</div>
</div>