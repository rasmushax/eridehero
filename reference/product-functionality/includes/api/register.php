<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

error_log('Register.php reached');
error_log('POST data: ' . print_r($_POST, true));

// Rate limiting
$ip_address = $_SERVER['REMOTE_ADDR'];
$transient_key = 'registration_attempt_' . $ip_address;
$attempt_count = get_transient($transient_key);

if ($attempt_count !== false && $attempt_count >= 5) {
    wp_send_json_error('Too many registration attempts. Please try again later.');
    return;
}

// Honeypot check
if (!empty($_POST['website'])) {
    // This is likely a bot submission
    wp_send_json_error('Invalid submission.');
    return;
}

// Get the registration data from the POST request
$username = isset($_POST['user_login']) ? sanitize_user($_POST['user_login']) : '';
$email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
$password = isset($_POST['user_pass']) ? $_POST['user_pass'] : '';

// Validate the data
if (empty($username) || empty($email) || empty($password)) {
    wp_send_json_error('Please fill in all required fields.');
    return;
}

// Username format validation
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    wp_send_json_error('Username can only contain alphanumeric characters and underscores.');
    return;
}

// Email validation
if (!is_email($email)) {
    wp_send_json_error('Please enter a valid email address.');
    return;
}

// Email domain validation
$email_parts = explode('@', $email);
if (!checkdnsrr(array_pop($email_parts), 'MX')) {
    wp_send_json_error('Please enter a valid email address with an existing domain.');
    return;
}

// Password length check
if (strlen($password) < 8) {
    wp_send_json_error('Password must be at least 8 characters long.');
    return;
}

if (username_exists($username)) {
    wp_send_json_error('This username is already taken.');
    return;
}

if (email_exists($email)) {
    wp_send_json_error('This email address is already registered.');
    return;
}

// Create the user
$user_id = wp_create_user($username, $password, $email);

if (is_wp_error($user_id)) {
    wp_send_json_error($user_id->get_error_message());
    return;
}

// Set the user role
$user = new WP_User($user_id);
$user->set_role('subscriber');

// Log IP address
update_user_meta($user_id, 'registration_ip', $ip_address);

// Log the user in
wp_set_current_user($user_id);
wp_set_auth_cookie($user_id);

// Increment rate limiting counter
if ($attempt_count === false) {
    set_transient($transient_key, 1, HOUR_IN_SECONDS);
} else {
    set_transient($transient_key, $attempt_count + 1, HOUR_IN_SECONDS);
}

// Send success response with user data
wp_send_json_success(array(
    'message' => 'Registration successful! You are now logged in.',
    'user_id' => $user_id,
    'username' => $username,
    'email' => $email
));