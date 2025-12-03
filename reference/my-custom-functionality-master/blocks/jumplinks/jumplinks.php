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
 
$fields = get_fields();

?>

<ul class="jumplinks">
	<li><?=$fields['title']?>:</li>
	<?php foreach($fields['jumplinks'] as $jumplink){ ?>
		<li><a href="<?=$jumplink['anchor']?>"><?=$jumplink['title']?></a></li>
	<?php } ?>
</ul>