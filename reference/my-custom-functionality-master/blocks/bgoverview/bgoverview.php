<?php
$items = get_fields();
/**
echo "<pre>";
print_r($items);
echo "</pre>";**/
?>
<div class="bgp">
	<?php echo ( !$items['title'] ? '' : '<div class="bgp-main">'. $items['title'] .'</div>' );  ?>
	
	<?php
		foreach($items['item'] as $item){
			$relationship = get_fields($item['relationship']->ID);
			
			if(empty($item['name'])){
				$name = $relationship['brand']." ".$relationship['model'];
			} else {
				$name = $item['name'];
			}
			
			if(empty($item['image'])){
				$img = wp_get_attachment_image($relationship['big_thumbnail'],array('80','80'),"",array('class'=>'bgp-img','alt' => $name,'title' => $name));
			} else {
				$img = wp_get_attachment_image($item['image'],array('80','80'),"",array('class'=>'bgp-img','alt' => $name,'title' => $name));
			}
			
			$ctatext = "Check Best Price";
			$ctalink = "#";
			
			if($item['cta_link']){
				$ctalink = $item['cta_link'];
				if($item['cta_text']){
					$ctatext = $item['cta_text'];
				}
			} else {
				
				$prices = getPrices($item['relationship']->ID);
				
				if(($prices[0]['url'])){
					$ctalink = $prices[0]['url'];
				}
				if(($prices[0]['price'])){
					$ctatext = "$".$prices[0]['price']." at ".prettydomain($prices[0]['domain']);
				}
				
			}			
			
	?>		
	
	<div class="bgp-item">
		<a href="<?=$ctalink?>" target="_blank" rel="noopener external sponsored" class="afftrigger bgp-link-img"><?=$img?></a>
		<div class="bgp-text">
			<?php echo ( !$item['title'] ? '' : '<div class="bgp-title">'. $item['title'] .'</div>' );  ?>
			<div class="bgp-name"><?=$name?></div>
			
			<?php
				if(!empty($item['read_more_link'])){
					echo '<a href="'.$item['read_more_link'].'" class="bgp-text-link">'.$item['read_more_text'].'<svg xmlns="http://www.w3.org/2000/svg" height="18" viewBox="0 -960 960 960" width="18"><path d="M480-362q-8 0-15-2.5t-13-8.5L268-557q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-373q-6 6-13 8.5t-15 2.5Z"/></svg>
			</a>';
				}
			?>
		</div>
		<div class="bgp-right">
			<a href="<?=afflink($ctalink,$item['relationship']->ID)?>" target="_blank" rel="noopener external sponsored" class="afftrigger bgp-cta"><?=$ctatext?></a>
		</div>
	</div>
	
	<?php } ?>
	
</div>