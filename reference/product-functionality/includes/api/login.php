<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (!check_ajax_referer('pf_nonce', '_wpnonce', false)) {
    wp_send_json_error('Invalid nonce');
}

error_log('Login.php reached');
error_log('POST data: ' . print_r($_POST, true));

// Get the login credentials from the POST data
$username = isset($_POST['log']) ? sanitize_user($_POST['log']) : '';
$password = isset($_POST['pwd']) ? $_POST['pwd'] : '';
$remember = isset($_POST['rememberme']) ? true : false;

if(is_user_logged_in()){
	wp_send_json_error("You're already logged in.");
	return;
}

if (empty($username) || empty($password)) {
    wp_send_json_error('Username and password are required');
    return;
}

// Attempt to log the user in
$user = wp_signon(array(
    'user_login' => $username,
    'user_password' => $password,
    'remember' => $remember
), is_ssl());

if (is_wp_error($user)) {
    wp_send_json_error("No user match those credentials. Try again or contact site admin.");
} else {
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, $remember);
    
    $response_data = array(
        'user_login' => $user->user_login,
        'user_email' => $user->user_email,
        'display_name' => $user->display_name
    );
    
    wp_send_json_success($response_data);
}