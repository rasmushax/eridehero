<?php

//product_getoffers.php

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
	echo "error_1";
	exit;
} else {
	$id = (int)$_GET['id'];
	require_once(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/wp-load.php');
	
	$post = get_post($id);
	
	if(empty($post)){
		echo "error_2";
		exit;
	} else {
		//print_r($post);
		
		$fields = get_fields($post->ID);
		//print_r($fields);
		//			
		$metas = get_post_meta($post->ID);

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
			//echo "<pre>";print_r($useful);echo "</pre>";
			foreach($useful as $meta){
				if($meta['stock_status'] == -1){
					$oos[] = $meta;
				} elseif($meta['stock_status'] == 1){
					if($meta['price'] == 0){
						$isunknownprice[] = $meta;
					} else {
						$is[] = $meta;
					}
				}
			}
		} else {
			$useful = null;
		}
?>
		
		<div class="pc-modal-offers">
			
			<?php
		
			$coupons = get_field('coupon',$post->ID);
		
			if(count($useful) > 0){
				
			$useful2 = array();
			$useful3 = array();
			$useful4 = array();
				foreach($useful as $key => $item){
					if($item['price'] == 0 && $item['stock_status'] == 1) {
						$useful2[] = $item;
						unset($useful[$key]);
					} elseif($item['stock_status'] == -1 && $item['price'] > 0){
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
				
				foreach($useful as $key2 => $item2) {
		
			?>
		
			<a href="<?=afflink($item2['url'])?>" target="_blank" rel="nofollow external noopener" class="pc-modal-offer afftrigger">
				
				<div class="pc-modal-offer-container">
					
					<div class="pc-modal-offer-content-left">
						<div class="pc-modal-domain"><?php
								echo ucfirst($item2['domain']);
								if($item2['stock_status'] == 1) {
									echo '<div class="pc-modal-instock isinstock"><svg aria-hidden="true" width="20" height="20" preserveAspectRatio="none" viewBox="0 0 24 24"><use href="#InStock"></use></svg><span>In stock</span></div>';
								} else {
									echo '<div class="pc-modal-instock isoutofstock"><svg aria-hidden="true" width="20" height="20" preserveAspectRatio="none" viewBox="0 0 24 24"><use href="#OutOfStock"></use></svg><span>Out of stock</span></div>';
								}
							?>
						</div>
						<?php
							$couponkey = array_search($item2['domain'], array_column($coupons, 'website'));
							if(strlen($couponkey) > 0){
								echo '<div class="pc-modal-coupon">Coupon: <span class="pc-modal-coupon-code">'.$coupons[$couponkey]['code'].'</span> (-';
								if($coupons[$couponkey]['discount_type'] == "Money") {echo $item['currency'].$coupons[$couponkey]['discount_value']." ".$item['currencyCode']; } else { echo $coupons[$couponkey]['discount_value']."%"; }
								echo ')</div>';
							}
						?>
					</div>
					
					<div class="pc-modal-offer-content-right">
						<div class="pc-item-price">
							<span class="pc-item-pricenow">
								<?php
								  if($item2['price'] == 0){
									  echo "Price Unknown";
								  } else {
								  	echo $item2['currency'].number_format($item2['price'],2)." ".$item2['currencyCode'];
								  }
								?>
							</span>
							<?php if($item2['percentageSaved'] > 0) { ?>
								<span class="pc-item-pricewas"><span class="pc-item-pricewas-price"><?php echo $item2['currency'].number_format($item2['priceOld'],2)." ".$item2['currencyCode'];?></span><span class="pc-item-saved">(-<?=$item2['percentageSaved']?>%)</span></span>
							<?php } ?>
						</div>
					</div>
					
				</div>
				
				<div class="pc-modal-chevron">
					<svg aria-hidden="true" width="30" height="30" preserveAspectRatio="none" viewBox="0 0 24 24"><use href="#Chevron"></use></svg>
				</div>
				
			</a>
			
			<?php } } else { echo "No offers available!"; } ?>
			
		</div>

<?php
		
	}
	
}