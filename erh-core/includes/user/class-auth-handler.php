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
                'email' => [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'username' => [
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
                'email' => [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'user_login' => [
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

        // Check if email exists (for social auth flow).
        register_rest_route(self::REST_NAMESPACE, '/auth/check-email', [
            'methods'             => 'POST',
            'callback'            => [$this, 'check_email_exists'],
            'permission_callback' => '__return_true',
            'args'                => [
                'email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);

        // Complete social auth (when provider doesn't return email).
        register_rest_route(self::REST_NAMESPACE, '/auth/social/complete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_social_complete'],
            'permission_callback' => '__return_true',
            'args'                => [
                'state' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'provider' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'password' => [
                    'type'              => 'string',
                    'default'           => '',
                ],
            ],
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

        $email = $request->get_param('email');
        $username = $request->get_param('username');
        $password = $request->get_param('password');
        $remember = (bool) $request->get_param('remember');

        // Accept either email or username for login.
        $login = $username ?: $email;
        if (empty($login)) {
            return new \WP_Error(
                'missing_credentials',
                'Please enter your email or username.',
                ['status' => 400]
            );
        }

        // If email provided, look up the username.
        if ($email && is_email($email)) {
            $user_by_email = get_user_by('email', $email);
            if ($user_by_email) {
                $login = $user_by_email->user_login;
            }
        }

        // Attempt login.
        $user = wp_signon([
            'user_login'    => $login,
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

        // Auto-generate username from email if not provided.
        if (empty($username)) {
            $username = $this->generate_username_from_email($email);
        }

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
            'success'         => true,
            'message'         => 'Registration successful. You are now logged in.',
            'needsOnboarding' => true, // New users always need onboarding
            'user'            => [
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

        $email = $request->get_param('email');
        $user_login = $request->get_param('user_login');

        // Accept either email or user_login parameter.
        $identifier = $user_login ?: $email;
        if (empty($identifier)) {
            return new \WP_Error(
                'missing_email',
                'Please enter your email address.',
                ['status' => 400]
            );
        }

        // Find user by login or email.
        $user = $this->user_repo->get_by_login($identifier);
        if (!$user) {
            $user = $this->user_repo->get_by_email($identifier);
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
     * Check if an email address exists.
     *
     * Used by social auth flow to determine if password is needed for linking.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response.
     */
    public function check_email_exists(\WP_REST_Request $request): \WP_REST_Response {
        $email = $request->get_param('email');

        if (!is_email($email)) {
            return new \WP_REST_Response([
                'exists' => false,
            ], 200);
        }

        $exists = email_exists($email) !== false;

        return new \WP_REST_Response([
            'exists' => $exists,
        ], 200);
    }

    /**
     * Complete social auth when provider didn't return email.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function handle_social_complete(\WP_REST_Request $request) {
        $state = $request->get_param('state');
        $provider = $request->get_param('provider');
        $email = $request->get_param('email');
        $password = $request->get_param('password');

        // Verify pending OAuth state.
        $pending_data = get_transient('erh_oauth_pending_' . $state);
        if (!$pending_data) {
            return new \WP_Error(
                'invalid_state',
                'Session expired. Please try logging in again.',
                ['status' => 400]
            );
        }

        // Verify provider matches.
        if ($pending_data['provider'] !== $provider) {
            return new \WP_Error(
                'provider_mismatch',
                'Invalid request.',
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

        // DNS/MX validation.
        if (!$this->validate_email_domain($email)) {
            return new \WP_Error(
                'invalid_email_domain',
                'Please enter an email address with a valid domain.',
                ['status' => 400]
            );
        }

        $provider_id = $pending_data['provider_id'];
        $name = $pending_data['name'] ?? '';
        $username = $pending_data['username'] ?? '';

        // Check if email already exists.
        $existing_user = get_user_by('email', $email);

        if ($existing_user) {
            // Email exists - need to verify password to link.
            if (empty($password)) {
                return new \WP_Error(
                    'password_required',
                    'An account with this email exists. Please enter your password to link your ' . ucfirst($provider) . ' account.',
                    ['status' => 400]
                );
            }

            // Verify password.
            if (!wp_check_password($password, $existing_user->user_pass, $existing_user->ID)) {
                return new \WP_Error(
                    'invalid_password',
                    'Incorrect password.',
                    ['status' => 401]
                );
            }

            // Link the social account.
            $this->user_repo->link_social_account($existing_user->ID, $provider, $provider_id);

            // Log in.
            wp_set_current_user($existing_user->ID);
            wp_set_auth_cookie($existing_user->ID, true);

            // Clear pending state.
            delete_transient('erh_oauth_pending_' . $state);

            return new \WP_REST_Response([
                'success'         => true,
                'message'         => ucfirst($provider) . ' account linked successfully.',
                'needsOnboarding' => !$this->user_repo->has_preferences_set($existing_user->ID),
                'redirect'        => home_url('/'),
            ], 200);
        }

        // No existing user - create new account.
        $base_username = $this->generate_username_from_profile($name, $username, $email);
        $final_username = $this->ensure_unique_username($base_username);
        $random_password = wp_generate_password(24, true, true);

        $user_id = wp_create_user($final_username, $random_password, $email);

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

        // Update display name.
        if (!empty($name)) {
            wp_update_user([
                'ID'           => $user_id,
                'display_name' => $name,
            ]);
        }

        // Link social account.
        $this->user_repo->link_social_account($user_id, $provider, $provider_id);

        // Store registration IP.
        $this->user_repo->set_registration_ip($user_id, RateLimiter::get_client_ip());

        // Trigger welcome email.
        do_action('erh_user_registered', $user_id, $user);

        // Log in.
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        // Clear pending state.
        delete_transient('erh_oauth_pending_' . $state);

        return new \WP_REST_Response([
            'success'         => true,
            'message'         => 'Account created successfully.',
            'needsOnboarding' => true,
            'redirect'        => home_url('/'),
        ], 201);
    }

    /**
     * Generate username from profile data.
     *
     * @param string $name     Display name.
     * @param string $username Provider username.
     * @param string $email    Email address.
     * @return string Generated username.
     */
    private function generate_username_from_profile(string $name, string $username, string $email): string {
        // Try username first.
        if (!empty($username) && preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return sanitize_user($username, true);
        }

        // Try name.
        if (!empty($name)) {
            $name_username = preg_replace('/[^a-zA-Z0-9]/', '', $name);
            if (!empty($name_username)) {
                return strtolower($name_username);
            }
        }

        // Fall back to email prefix.
        $email_parts = explode('@', $email);
        return sanitize_user($email_parts[0], true);
    }

    /**
     * Ensure username is unique.
     *
     * @param string $username Base username.
     * @return string Unique username.
     */
    private function ensure_unique_username(string $username): string {
        if (!username_exists($username)) {
            return $username;
        }

        $counter = 1;
        while (username_exists($username . $counter)) {
            $counter++;
        }

        return $username . $counter;
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
     * Generate a unique username from an email address.
     *
     * @param string $email The email address.
     * @return string Generated username.
     */
    private function generate_username_from_email(string $email): string {
        // Extract local part of email.
        $parts = explode('@', $email);
        $base = $parts[0] ?? 'user';

        // Clean up: remove dots, plus-aliases, and non-alphanumeric chars.
        $base = preg_replace('/\+.*$/', '', $base); // Remove +alias part.
        $base = preg_replace('/[^a-zA-Z0-9]/', '', $base);
        $base = strtolower($base);

        // Ensure minimum length.
        if (strlen($base) < 3) {
            $base = 'user' . $base;
        }

        // Truncate if too long.
        $base = substr($base, 0, 20);

        // Check if username exists, add random suffix if so.
        $username = $base;
        $counter = 1;
        while (username_exists($username)) {
            $suffix = $counter < 10 ? random_int(100, 999) : random_int(1000, 9999);
            $username = $base . $suffix;
            $counter++;

            // Safety limit.
            if ($counter > 20) {
                $username = $base . bin2hex(random_bytes(4));
                break;
            }
        }

        return $username;
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
