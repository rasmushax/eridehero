<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

wp_logout();
wp_send_json_success(array('message' => 'Logged out successfully'));