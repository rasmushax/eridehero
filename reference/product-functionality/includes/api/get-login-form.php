<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly


$form = wp_login_form();

wp_send_json_success($form);