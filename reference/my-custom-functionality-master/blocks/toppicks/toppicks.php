<ul class="toppicks">
<?php $fields = get_fields(); foreach($fields['product'] as $pick){ 

 $name = "";
 $pricetype = "static";
 
 if($pick['id']){
	 
	$product = get_fields($pick['id']);
	$name = $product['brand']." ".$product['model'];
	
	$metas = get_post_meta($pick['id']);
	
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
							
						}
	$link = "#";
	
	if($useful[0]['price'] > 0){
		$link = $useful[0]['url'];
		$pricetype = "dynamic";
	}
	
 }
 
 if($pick['title']){
	$name = $pick['title'];
 }
 
  if($link == "#"){
	if($useful[0]['domain'] == "amazon.com"){
		$link = $useful[0]['url'];
	}
 }
 
 if($link == "#" && $pick['aff_link']){
	 $link = $pick['aff_link'];
 }	 

?>
<li class="toppick">
	<?php 
	if($pick['tagline']) { echo '<span class="toppicktag">'.$pick['tagline'].': </span>'; }
	if($link !== "#") {
		echo '<a href="'.afflink($link,$pick['id']).'" target="_blank" class="afftrigger" rel="sponsored external noopener">'.$name.'</a>';
	} else {
		echo $name;
	}
	?>
</li>
<?php } ?>
</ul>