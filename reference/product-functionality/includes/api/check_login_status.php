<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

$isLoggedIn = is_user_logged_in();

wp_send_json_success(array('isLoggedIn' => $isLoggedIn));