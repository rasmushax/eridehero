<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (is_user_logged_in()) {
    wp_send_json_error(array('message' => 'You are already logged in.'));
    return;
}

if (!check_ajax_referer('pf_nonce', '_wpnonce', false)) {
    wp_send_json_error(array('message' => 'Invalid nonce'));
    return;
}

$reset_key = sanitize_text_field($_POST['reset_key']);
$user_login = sanitize_text_field($_POST['user_login']);
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];

if (empty($reset_key) || empty($user_login) || empty($new_password) || empty($confirm_password)) {
    wp_send_json_error(array('message' => 'All fields are required.'));
    return;
}

if (strlen($new_password) < 8) {
    wp_send_json_error(array('message' => 'Password must be at least 8 characters long.'));
    return;
}

if ($new_password !== $confirm_password) {
    wp_send_json_error(array('message' => 'Passwords do not match.'));
    return;
}

$user = check_password_reset_key($reset_key, $user_login);

if (is_wp_error($user)) {
    wp_send_json_error(array('message' => 'This password reset link has expired or is invalid.'));
    return;
}

// Reset the password
reset_password($user, $new_password);

// Log the user in
$creds = array(
    'user_login'    => $user_login,
    'user_password' => $new_password,
    'remember'      => true
);

$user = wp_signon($creds, false);

if (is_wp_error($user)) {
    wp_send_json_error(array('message' => 'Password reset successfully, but there was an error logging you in. Please try logging in manually.'));
    return;
}

// Delete the password reset key meta to prevent reuse
delete_user_meta($user->ID, 'password_reset_key_age');

wp_send_json_success(array(
    'message' => 'Your password has been reset successfully. You are now logged in.',
    'redirect' => home_url() // Or any other URL you want to redirect to
));