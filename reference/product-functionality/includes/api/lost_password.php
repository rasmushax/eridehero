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

$user_login = sanitize_text_field($_POST['user_login']);

if (empty($user_login)) {
    wp_send_json_error(array('message' => 'Please enter a username or email address.'));
    return;
}

$user_data = get_user_by('login', $user_login);

if (!$user_data) {
    $user_data = get_user_by('email', $user_login);
}

if (!$user_data) {
    wp_send_json_error(array('message' => 'Invalid username or email address.'));
    return;
}

$user_login = $user_data->user_login;
$user_email = $user_data->user_email;
$key = get_password_reset_key($user_data);

if (is_wp_error($key)) {
    wp_send_json_error(array('message' => 'An error occurred while generating the password reset link. Please try again.'));
    return;
}

$product_functionality = new Product_Functionality();

$reset_page_id = 14630; // Replace with the ID of your custom reset password page

update_user_meta($user_data->ID, 'password_reset_key_age', time());

$reset_url = get_permalink($reset_page_id) . "?key=$key&login=" . rawurlencode($user_login);

$content = $product_functionality->generate_email_paragraph('Someone has requested a password reset for the following account:');
$content .= $product_functionality->generate_email_paragraph('Username: ' . $user_login);
$content .= $product_functionality->generate_email_paragraph('If this was a mistake, just ignore this email and nothing will happen.');
$content .= $product_functionality->generate_email_paragraph('To reset your password, click the button below:');
$content .= $product_functionality->generate_email_button($reset_url, 'Reset Password');
$content .= $product_functionality->generate_email_paragraph('If you\'re having trouble clicking the button, copy and paste th URL below into your web browser:');
$content .= $product_functionality->generate_email_paragraph($product_functionality->generate_email_link($reset_url, $reset_url));
$content .= $product_functionality->generate_email_paragraph('Sincerely,');
$content .= $product_functionality->generate_email_paragraph('Rasmus Barslund from ERideHero');

$title = sprintf(__('[%s] Password Reset'), wp_specialchars_decode(get_option('blogname'), ENT_QUOTES));

if ($product_functionality->send_html_email($user_email, $title, $content)) {
	error_log("Reset mail success to " . $user_email);
    wp_send_json_success(array('message' => 'Password reset email sent. Please check your inbox.'));
} else {
	error_log("Error to " . $user_email);
    wp_send_json_error(array('message' => 'An error occurred while sending the password reset email. Please try again.'));
}