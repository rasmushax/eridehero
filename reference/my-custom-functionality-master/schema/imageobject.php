<?php

function getImageObject() {
    global $post;
	return $post;
}

add_action( 'wp_head', 'getImageObject' );

?>