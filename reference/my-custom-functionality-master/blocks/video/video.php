<?php
/**
 * ACF Video Block Template.
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during backend preview render.
 * @param   int $post_id The post ID the block is rendering content against.
 *          This is either the post ID currently being displayed inside a query loop,
 *          or the post ID of the post hosting this block.
 * @param   array $context The context provided to the block by the post or it's parent block.
 */

$mp4 = get_field('video');
$thumbnail = get_field('thumbnail');

//print_r($products);

global $post;
?>

<div class="acfvideo" data-src="<?=$mp4?>">
	<video muted loop playsinline style="pointer-events: none;" preload="none"></video>
	<?=wp_get_attachment_image($thumbnail, 'large',"",array("class"=>"acfvidimg"))?>
	<div class="video-controls">
		<svg class="vid_play" version="1.1" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
			<title>play</title>
			<path d="M5.541 2.159c-0.153-0.1-0.34-0.159-0.541-0.159-0.552 0-1 0.448-1 1v18c-0.001 0.182 0.050 0.372 0.159 0.541 0.299 0.465 0.917 0.599 1.382 0.3l14-9c0.114-0.072 0.219-0.174 0.3-0.3 0.299-0.465 0.164-1.083-0.3-1.382zM6 4.832l11.151 7.168-11.151 7.168z"></path>
		</svg>
		<svg class="vid_loading" style="display:none" version="1.1" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
		<title>loader</title>
		<path d="M11 2v4c0 0.552 0.448 1 1 1s1-0.448 1-1v-4c0-0.552-0.448-1-1-1s-1 0.448-1 1zM11 18v4c0 0.552 0.448 1 1 1s1-0.448 1-1v-4c0-0.552-0.448-1-1-1s-1 0.448-1 1zM4.223 5.637l2.83 2.83c0.391 0.391 1.024 0.391 1.414 0s0.391-1.024 0-1.414l-2.83-2.83c-0.391-0.391-1.024-0.391-1.414 0s-0.391 1.024 0 1.414zM15.533 16.947l2.83 2.83c0.391 0.391 1.024 0.391 1.414 0s0.391-1.024 0-1.414l-2.83-2.83c-0.391-0.391-1.024-0.391-1.414 0s-0.391 1.024 0 1.414zM2 13h4c0.552 0 1-0.448 1-1s-0.448-1-1-1h-4c-0.552 0-1 0.448-1 1s0.448 1 1 1zM18 13h4c0.552 0 1-0.448 1-1s-0.448-1-1-1h-4c-0.552 0-1 0.448-1 1s0.448 1 1 1zM5.637 19.777l2.83-2.83c0.391-0.391 0.391-1.024 0-1.414s-1.024-0.391-1.414 0l-2.83 2.83c-0.391 0.391-0.391 1.024 0 1.414s1.024 0.391 1.414 0zM16.947 8.467l2.83-2.83c0.391-0.391 0.391-1.024 0-1.414s-1.024-0.391-1.414 0l-2.83 2.83c-0.391 0.391-0.391 1.024 0 1.414s1.024 0.391 1.414 0z"></path>
		</svg>
	</div>
</div>