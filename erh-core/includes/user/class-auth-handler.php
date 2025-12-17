<?php
/**
 * Auth Handler - Handles email authentication, registration, and password reset.
 *
 * @package ERH\User
 */

declare(strict_types=1);

namespace ERH\User;

/**
 * Handles user authentication via email/password.
 */
class AuthHandler {

    /**
     * Rate limiter instance.
     *
     * @var RateLimiter
     */
    private RateLimiter $rate_limiter;

    /**
     * User repository instance.
     *
     * @var UserRepository
     */
    private UserRepository $user_repo;

    /**
     * REST API namespace.
     */
    public const REST_NAMESPACE = 'erh/v1';

    /**
     * Constructor.
     *
     * @param RateLimiter|null    $rate_limiter Optional rate limiter instance.
     * @param UserRepository|null $user_repo    Optional user repository instance.
     */
    public function __construct(?RateLimiter $rate_limiter = null, ?UserRepository $user_repo = null) {
        $this->rate_limiter = $rate_limiter ?? new RateLimiter();
        $this->user_repo = $user_repo ?? new UserRepository();
    }

    /**
     * Register hooks and REST routes.
     *
     * @return void
     */
    public function register(): void {
        // Register REST API routes.
        add_action('rest_api_init', [$this, 'register_routes']);

        // Redirect wp-login.php for non-admin users.
        add_action('login_init', [$this, 'maybe_redirect_login']);

        // Custom user notification email.
        add_filter('wp_new_user_notification_email', [$this, 'custom_new_user_email'], 10, 3);
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_login'],
            'permission_callback' => '__return_true',
            'args'                => [
                'username' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_user',
                ],
                'password' => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'remember' => [
                    'type'    => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auth/register', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_register'],
            'permission_callback' => '__return_true',
            'args'                => [
                'username' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_user',
                ],
                'email'    => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'password' => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'website'  => [
                    'type'    => 'string',
                    'default' => '',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auth/forgot-password', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_forgot_password'],
            'permission_callback' => '__return_true',
            'args'                => [
                'user_login' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auth/reset-password', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_reset_password'],
            'permission_callback' => '__return_true',
            'args'                => [
                'key'              => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'login'            => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'password'         => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'password_confirm' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auth/logout', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_logout'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auth/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_auth_status'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle login request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function handle_login(\WP_REST_Request $request) {
        // Check if already logged in.
        if (is_user_logged_in()) {
            return new \WP_Error(
                'already_logged_in',
                'You are already logged in.',
                ['status' => 400]
            );
        }

        $ip = RateLimiter::get_client_ip();

        // Check rate limit.
        $rate_check = $this->rate_limiter->check_and_record('login', $ip);
        if (!$rate_check['allowed']) {
            return new \WP_Error(
                'rate_limited',
                $rate_check['message'],
                ['status' => 429]
            );
        }

        $username = $request->get_param('username');
        $password = $request->get_param('password');
        $remember = (bool) $request->get_param('remember');

        // Attempt login.
        $user = wp_signon([
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ], is_ssl());

        if (is_wp_error($user)) {
            return new \WP_Error(
                'login_failed',
                'Invalid username or password.',
                ['status' => 401]
            );
        }

        // Reset rate limit on successful login.
        $this->rate_limiter->reset('login', $ip);

        // Set cookies.
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Login successful.',
            'user'    => [
                'id'           => $user->ID,
                'username'     => $user->user_login,
                'email'        => $user->user_email,
                'display_name' => $user->display_name,
            ],
        ], 200);
    }

    /**
     * Handle registration request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function handle_register(\WP_REST_Request $request) {
        // Check if already logged in.
        if (is_user_logged_in()) {
            return new \WP_Error(
                'already_logged_in',
                'You are already logged in.',
                ['status' => 400]
            );
        }

        // Honeypot check.
        if (!empty($request->get_param('website'))) {
            return new \WP_Error(
                'invalid_submission',
                'Invalid submission.',
                ['status' => 400]
            );
        }

        $ip = RateLimiter::get_client_ip();

        // Check rate limit.
        $rate_check = $this->rate_limiter->check_and_record('register', $ip);
        if (!$rate_check['allowed']) {
            return new \WP_Error(
                'rate_limited',
                $rate_check['message'],
                ['status' => 429]
            );
        }

        $username = $request->get_param('username');
        $email = $request->get_param('email');
        $password = $request->get_param('password');

        // Validate username format.
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return new \WP_Error(
                'invalid_username',
                'Username can only contain letters, numbers, and underscores.',
                ['status' => 400]
            );
        }

        // Validate email.
        if (!is_email($email)) {
            return new \WP_Error(
                'invalid_email',
                'Please enter a valid email address.',
                ['status' => 400]
            );
        }

        // DNS/MX validation for email domain.
        if (!$this->validate_email_domain($email)) {
            return new \WP_Error(
                'invalid_email_domain',
                'Please enter an email address with a valid domain.',
                ['status' => 400]
            );
        }

        // Validate password length.
        if (strlen($password) < 8) {
            return new \WP_Error(
                'weak_password',
                'Password must be at least 8 characters long.',
                ['status' => 400]
            );
        }

        // Check if username exists.
        if (username_exists($username)) {
            return new \WP_Error(
                'username_exists',
                'This username is already taken.',
                ['status' => 400]
            );
        }

        // Check if email exists.
        if (email_exists($email)) {
            return new \WP_Error(
                'email_exists',
                'This email address is already registered.',
                ['status' => 400]
            );
        }

        // Create user.
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return new \WP_Error(
                'registration_failed',
                $user_id->get_error_message(),
                ['status' => 500]
            );
        }

        // Set role.
        $user = new \WP_User($user_id);
        $user->set_role('subscriber');

        // Store registration IP.
        $this->user_repo->set_registration_ip($user_id, $ip);

        // Log the user in.
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        // Trigger welcome email.
        do_action('erh_user_registered', $user_id, $user);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Registration successful. You are now logged in.',
            'user'    => [
                'id'           => $user_id,
                'username'     => $username,
                'email'        => $email,
                'display_name' => $username,
            ],
        ], 201);
    }

    /**
     * Handle forgot password request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function handle_forgot_password(\WP_REST_Request $request) {
        if (is_user_logged_in()) {
            return new \WP_Error(
                'already_logged_in',
                'You are already logged in.',
                ['status' => 400]
            );
        }

        $ip = RateLimiter::get_client_ip();

        // Check rate limit.
        $rate_check = $this->rate_limiter->check_and_record('password_reset', $ip);
        if (!$rate_check['allowed']) {
            return new \WP_Error(
                'rate_limited',
                $rate_check['message'],
                ['status' => 429]
            );
        }

        $user_login = $request->get_param('user_login');

        // Find user by login or email.
        $user = $this->user_repo->get_by_login($user_login);
        if (!$user) {
            $user = $this->user_repo->get_by_email($user_login);
        }

        if (!$user) {
            // Return success to prevent user enumeration.
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'If an account exists with that username or email, a password reset link has been sent.',
            ], 200);
        }

        // Generate reset key.
        $key = get_password_reset_key($user);

        if (is_wp_error($key)) {
            return new \WP_Error(
                'reset_key_failed',
                'Failed to generate password reset link. Please try again.',
                ['status' => 500]
            );
        }

        // Store key age for expiration check.
        update_user_meta($user->ID, UserRepository::META_PASSWORD_RESET_KEY_AGE, time());

        // Build reset URL.
        $reset_page_url = $this->get_reset_password_url();
        $reset_url = add_query_arg([
            'key'   => $key,
            'login' => rawurlencode($user->user_login),
        ], $reset_page_url);

        // Send reset email.
        $sent = $this->send_password_reset_email($user, $reset_url);

        if (!$sent) {
            return new \WP_Error(
                'email_failed',
                'Failed to send password reset email. Please try again.',
                ['status' => 500]
            );
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'If an account exists with that username or email, a password reset link has been sent.',
        ], 200);
    }

    /**
     * Handle reset password request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function handle_reset_password(\WP_REST_Request $request) {
        if (is_user_logged_in()) {
            return new \WP_Error(
                'already_logged_in',
                'You are already logged in.',
                ['status' => 400]
            );
        }

        $key = $request->get_param('key');
        $login = $request->get_param('login');
        $password = $request->get_param('password');
        $password_confirm = $request->get_param('password_confirm');

        // Validate passwords match.
        if ($password !== $password_confirm) {
            return new \WP_Error(
                'password_mismatch',
                'Passwords do not match.',
                ['status' => 400]
            );
        }

        // Validate password length.
        if (strlen($password) < 8) {
            return new \WP_Error(
                'weak_password',
                'Password must be at least 8 characters long.',
                ['status' => 400]
            );
        }

        // Verify reset key.
        $user = check_password_reset_key($key, $login);

        if (is_wp_error($user)) {
            return new \WP_Error(
                'invalid_key',
                'This password reset link has expired or is invalid.',
                ['status' => 400]
            );
        }

        // Reset password.
        reset_password($user, $password);

        // Clear reset key meta.
        delete_user_meta($user->ID, UserRepository::META_PASSWORD_RESET_KEY_AGE);

        // Log user in.
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        return new \WP_REST_Response([
            'success'  => true,
            'message'  => 'Your password has been reset. You are now logged in.',
            'redirect' => home_url(),
        ], 200);
    }

    /**
     * Handle logout request.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response.
     */
    public function handle_logout(\WP_REST_Request $request): \WP_REST_Response {
        wp_logout();

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'You have been logged out.',
        ], 200);
    }

    /**
     * Get current auth status.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response.
     */
    public function get_auth_status(\WP_REST_Request $request): \WP_REST_Response {
        if (!is_user_logged_in()) {
            return new \WP_REST_Response([
                'logged_in' => false,
            ], 200);
        }

        $user = wp_get_current_user();

        return new \WP_REST_Response([
            'logged_in' => true,
            'user'      => [
                'id'           => $user->ID,
                'username'     => $user->user_login,
                'email'        => $user->user_email,
                'display_name' => $user->display_name,
            ],
        ], 200);
    }

    /**
     * Redirect wp-login.php for non-admin users.
     *
     * @return void
     */
    public function maybe_redirect_login(): void {
        // Allow admins through.
        if (current_user_can('manage_options')) {
            return;
        }

        // Allow emergency access with secret param.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['wpadmin']) && $_GET['wpadmin'] === 'true') {
            return;
        }

        // Allow POST requests (actual login form submissions) to process.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return;
        }

        // Allow logout action.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        if ($action === 'logout') {
            return;
        }

        // Allow social login callbacks.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['loginSocial']) || isset($_GET['provider'])) {
            return;
        }

        // Allow password reset flow via wp-login.php.
        if (in_array($action, ['lostpassword', 'rp', 'resetpass'], true)) {
            return;
        }

        // Get custom login page URL.
        $login_url = $this->get_login_page_url();

        // Redirect to custom login page.
        wp_safe_redirect($login_url);
        exit;
    }

    /**
     * Customize the new user notification email.
     *
     * @param array    $email   The email data.
     * @param \WP_User $user    The user object.
     * @param string   $blogname The blog name.
     * @return array Modified email data.
     */
    public function custom_new_user_email(array $email, \WP_User $user, string $blogname): array {
        $site_url = home_url();
        $user_login = $user->user_login;

        // Build welcome email content.
        $content = $this->generate_email_paragraph(sprintf('Welcome to %s!', $blogname));
        $content .= $this->generate_email_paragraph(sprintf('Hello %s,', $user_login));
        $content .= $this->generate_email_paragraph(
            'Thank you for joining our community of e-mobility enthusiasts! Your account has been created.'
        );

        $content .= $this->generate_email_paragraph("Here's what you can do on ERideHero:");

        $content .= '<ul style="padding-left: 20px; margin-bottom: 16px;">';
        $content .= '<li style="margin-bottom: 8px;"><b>Product Finder:</b> Search and compare models using filters.</li>';
        $content .= '<li style="margin-bottom: 8px;"><b>Price Trackers:</b> Get notified when prices drop.</li>';
        $content .= '<li style="margin-bottom: 8px;"><b>User Reviews:</b> Share your experience with the community.</li>';
        $content .= '<li style="margin-bottom: 8px;"><b>Deals:</b> Find real discounts based on historical data.</li>';
        $content .= '</ul>';

        $content .= $this->generate_email_button($site_url, 'Visit ERideHero');
        $content .= $this->generate_email_paragraph('Ride on!');
        $content .= $this->generate_email_paragraph('The ERideHero Team');

        $email['subject'] = sprintf('Welcome to %s!', $blogname);
        $email['message'] = $this->wrap_email_content($content);
        $email['headers'] = ['Content-Type: text/html; charset=UTF-8'];

        return $email;
    }

    /**
     * Validate email domain has MX records.
     *
     * @param string $email The email address.
     * @return bool True if valid domain.
     */
    private function validate_email_domain(string $email): bool {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }

        $domain = $parts[1];

        // Check for MX records.
        return checkdnsrr($domain, 'MX');
    }

    /**
     * Send password reset email.
     *
     * @param \WP_User $user      The user.
     * @param string   $reset_url The reset URL.
     * @return bool True if sent.
     */
    private function send_password_reset_email(\WP_User $user, string $reset_url): bool {
        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $subject = sprintf('[%s] Password Reset', $blogname);

        $content = $this->generate_email_paragraph('Someone has requested a password reset for the following account:');
        $content .= $this->generate_email_paragraph(sprintf('Username: %s', $user->user_login));
        $content .= $this->generate_email_paragraph('If this was a mistake, just ignore this email and nothing will happen.');
        $content .= $this->generate_email_paragraph('To reset your password, click the button below:');
        $content .= $this->generate_email_button($reset_url, 'Reset Password');
        $content .= $this->generate_email_paragraph('If the button doesn\'t work, copy and paste this URL into your browser:');
        $content .= $this->generate_email_paragraph(sprintf('<a href="%s">%s</a>', esc_url($reset_url), esc_html($reset_url)));

        $html = $this->wrap_email_content($content);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return wp_mail($user->user_email, $subject, $html, $headers);
    }

    /**
     * Get the custom login page URL.
     *
     * @return string The login page URL.
     */
    private function get_login_page_url(): string {
        $page_id = (int) get_option('erh_login_page_id', 0);
        if ($page_id > 0) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }

        // Fallback to /login/ slug.
        return home_url('/login/');
    }

    /**
     * Get the password reset page URL.
     *
     * @return string The reset page URL.
     */
    private function get_reset_password_url(): string {
        $page_id = (int) get_option('erh_reset_password_page_id', 0);
        if ($page_id > 0) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }

        // Fallback to /reset-password/ slug.
        return home_url('/reset-password/');
    }

    /**
     * Generate an email paragraph.
     *
     * @param string $text The text.
     * @return string HTML paragraph.
     */
    private function generate_email_paragraph(string $text): string {
        return sprintf(
            '<p style="font-family: Helvetica, sans-serif; font-size: 16px; font-weight: normal; margin: 0; margin-bottom: 16px;">%s</p>',
            $text
        );
    }

    /**
     * Generate an email button.
     *
     * @param string $url  The URL.
     * @param string $text The button text.
     * @return string HTML button.
     */
    private function generate_email_button(string $url, string $text): string {
        return sprintf(
            '<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; width: auto; margin-bottom: 16px;">
                <tr>
                    <td style="border-radius: 4px; background-color: #5e2ced; text-align: center;">
                        <a href="%s" target="_blank" style="border: solid 2px #5e2ced; border-radius: 4px; box-sizing: border-box; cursor: pointer; display: inline-block; font-size: 16px; font-weight: bold; margin: 0; padding: 12px 24px; text-decoration: none; background-color: #5e2ced; color: #ffffff;">%s</a>
                    </td>
                </tr>
            </table>',
            esc_url($url),
            esc_html($text)
        );
    }

    /**
     * Wrap email content in the template.
     *
     * @param string $content The email content.
     * @return string The wrapped content.
     */
    private function wrap_email_content(string $content): string {
        // Use the email template function if available.
        if (function_exists('erh_get_email_template')) {
            return erh_get_email_template($content);
        }

        // Basic wrapper fallback.
        return sprintf(
            '<!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"></head>
            <body style="font-family: Helvetica, sans-serif; background-color: #f4f4f4; padding: 20px;">
                <div style="max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px;">
                    %s
                </div>
            </body>
            </html>',
            $content
        );
    }
}
