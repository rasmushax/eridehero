<?php
/**
 * User Preferences - Handles email preferences and account settings.
 *
 * @package ERH\User
 */

declare(strict_types=1);

namespace ERH\User;

/**
 * Manages user email preferences and account settings via REST API.
 */
class UserPreferences {

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
     * @param UserRepository|null $user_repo Optional user repository instance.
     */
    public function __construct(?UserRepository $user_repo = null) {
        $this->user_repo = $user_repo ?? new UserRepository();
    }

    /**
     * Register hooks and REST routes.
     *
     * @return void
     */
    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);

        // Check if user needs to set preferences.
        add_action('template_redirect', [$this, 'maybe_redirect_to_preferences']);
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Get preferences.
        register_rest_route(self::REST_NAMESPACE, '/user/preferences', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_preferences'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Update preferences.
        register_rest_route(self::REST_NAMESPACE, '/user/preferences', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'update_preferences'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'price_tracker_emails'    => ['type' => 'boolean'],
                'sales_roundup_emails'    => ['type' => 'boolean'],
                'sales_roundup_frequency' => [
                    'type' => 'string',
                    'enum' => UserRepository::VALID_FREQUENCIES,
                ],
                'sales_roundup_types'     => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => array_keys(UserRepository::VALID_ROUNDUP_TYPES),
                    ],
                ],
                'newsletter_subscription' => ['type' => 'boolean'],
            ],
        ]);

        // Change email.
        register_rest_route(self::REST_NAMESPACE, '/user/email', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'change_email'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'new_email'        => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'current_password' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);

        // Change password.
        register_rest_route(self::REST_NAMESPACE, '/user/password', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'change_password'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'current_password' => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'new_password'     => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'confirm_password' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);

        // Get user profile.
        register_rest_route(self::REST_NAMESPACE, '/user/profile', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_profile'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Unlink social provider.
        register_rest_route(self::REST_NAMESPACE, '/user/unlink-social', [
            'methods'             => 'POST',
            'callback'            => [$this, 'unlink_social_provider'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'provider' => [
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => ['google', 'facebook', 'reddit'],
                ],
            ],
        ]);
    }

    /**
     * Get current user's preferences.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response.
     */
    public function get_preferences(\WP_REST_Request $request): \WP_REST_Response {
        $user_id = get_current_user_id();
        $preferences = $this->user_repo->get_preferences($user_id);

        return new \WP_REST_Response([
            'success'     => true,
            'preferences' => $preferences,
        ], 200);
    }

    /**
     * Update current user's preferences.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function update_preferences(\WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $params = $request->get_params();

        // Build preferences array from provided params.
        $preferences = [];

        if (isset($params['price_tracker_emails'])) {
            $preferences['price_tracker_emails'] = (bool) $params['price_tracker_emails'];
        }

        if (isset($params['sales_roundup_emails'])) {
            $preferences['sales_roundup_emails'] = (bool) $params['sales_roundup_emails'];
        }

        if (isset($params['sales_roundup_frequency'])) {
            $preferences['sales_roundup_frequency'] = $params['sales_roundup_frequency'];
        }

        if (isset($params['sales_roundup_types'])) {
            $preferences['sales_roundup_types'] = (array) $params['sales_roundup_types'];
        }

        if (isset($params['newsletter_subscription'])) {
            $preferences['newsletter_subscription'] = (bool) $params['newsletter_subscription'];
        }

        $this->user_repo->update_preferences($user_id, $preferences);

        return new \WP_REST_Response([
            'success'     => true,
            'message'     => 'Preferences updated successfully.',
            'preferences' => $this->user_repo->get_preferences($user_id),
        ], 200);
    }

    /**
     * Change user's email address.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function change_email(\WP_REST_Request $request) {
        $user = wp_get_current_user();
        $new_email = $request->get_param('new_email');
        $current_password = $request->get_param('current_password');

        // Validate email format.
        if (!is_email($new_email)) {
            return new \WP_Error(
                'invalid_email',
                'Please enter a valid email address.',
                ['status' => 400]
            );
        }

        // Check if email already in use.
        if (email_exists($new_email)) {
            return new \WP_Error(
                'email_exists',
                'This email address is already in use.',
                ['status' => 400]
            );
        }

        // Verify current password.
        if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
            return new \WP_Error(
                'incorrect_password',
                'Current password is incorrect.',
                ['status' => 401]
            );
        }

        $old_email = $user->user_email;

        // Update email.
        $result = wp_update_user([
            'ID'         => $user->ID,
            'user_email' => $new_email,
        ]);

        if (is_wp_error($result)) {
            return new \WP_Error(
                'update_failed',
                'Failed to update email address.',
                ['status' => 500]
            );
        }

        // Trigger email change action (for Mailchimp sync, notifications, etc.).
        do_action('erh_user_email_changed', $user->ID, $old_email, $new_email);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Email address updated successfully.',
            'email'   => $new_email,
        ], 200);
    }

    /**
     * Change user's password.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function change_password(\WP_REST_Request $request) {
        $user = wp_get_current_user();
        $current_password = $request->get_param('current_password');
        $new_password = $request->get_param('new_password');
        $confirm_password = $request->get_param('confirm_password');

        // Verify current password.
        if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
            return new \WP_Error(
                'incorrect_password',
                'Current password is incorrect.',
                ['status' => 401]
            );
        }

        // Validate new password length.
        if (strlen($new_password) < 8) {
            return new \WP_Error(
                'weak_password',
                'New password must be at least 8 characters long.',
                ['status' => 400]
            );
        }

        // Confirm passwords match.
        if ($new_password !== $confirm_password) {
            return new \WP_Error(
                'password_mismatch',
                'New passwords do not match.',
                ['status' => 400]
            );
        }

        // Set new password.
        wp_set_password($new_password, $user->ID);

        // Log out all sessions.
        wp_logout();

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Password changed successfully. Please log in with your new password.',
        ], 200);
    }

    /**
     * Get current user's profile.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response.
     */
    public function get_profile(\WP_REST_Request $request): \WP_REST_Response {
        $user = wp_get_current_user();
        $user_id = $user->ID;

        // Get tracker count.
        global $wpdb;
        $tracker_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}price_trackers WHERE user_id = %d",
            $user_id
        ));

        // Get review count.
        $review_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'review'
             AND post_author = %d
             AND post_status IN ('publish', 'pending')",
            $user_id
        ));

        // Get linked social providers.
        $linked_providers = $this->user_repo->get_linked_providers($user_id);

        return new \WP_REST_Response([
            'success' => true,
            'profile' => [
                'id'               => $user_id,
                'username'         => $user->user_login,
                'email'            => $user->user_email,
                'display_name'     => $user->display_name,
                'registered'       => $user->user_registered,
                'tracker_count'    => $tracker_count,
                'review_count'     => $review_count,
                'linked_providers' => array_keys(array_filter($linked_providers)),
                'preferences'      => $this->user_repo->get_preferences($user_id),
            ],
        ], 200);
    }

    /**
     * Unlink a social provider from the current user's account.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function unlink_social_provider(\WP_REST_Request $request) {
        $user = wp_get_current_user();
        $user_id = $user->ID;
        $provider = strtolower($request->get_param('provider'));

        // Get current linked providers.
        $linked = $this->user_repo->get_linked_providers($user_id);
        $linked_count = count(array_filter($linked));

        // Check if this provider is actually linked.
        if (empty($linked[$provider])) {
            return new \WP_Error(
                'not_linked',
                'This account is not linked.',
                ['status' => 400]
            );
        }

        // Check if user has a password set (has_password returns false for empty string hash).
        $has_password = !empty($user->user_pass) && $user->user_pass !== '';

        // If this is the only linked provider and user has no password, prevent unlinking.
        if ($linked_count === 1 && !$has_password) {
            return new \WP_Error(
                'cannot_unlink',
                'You must set a password before disconnecting your only login method.',
                ['status' => 400]
            );
        }

        // Unlink the provider.
        $result = $this->user_repo->unlink_social_account($user_id, $provider);

        if (!$result) {
            return new \WP_Error(
                'unlink_failed',
                'Failed to disconnect account.',
                ['status' => 500]
            );
        }

        return new \WP_REST_Response([
            'success'          => true,
            'message'          => ucfirst($provider) . ' account disconnected.',
            'linked_providers' => array_keys(array_filter($this->user_repo->get_linked_providers($user_id))),
        ], 200);
    }

    /**
     * Redirect to email preferences page if not set.
     *
     * @return void
     */
    public function maybe_redirect_to_preferences(): void {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        // Skip if preferences already set.
        if ($this->user_repo->has_preferences_set($user_id)) {
            return;
        }

        // Get preferences page URL.
        $preferences_page_id = (int) get_option('erh_email_preferences_page_id', 0);
        if ($preferences_page_id <= 0) {
            return;
        }

        // Don't redirect if already on preferences page.
        if (is_page($preferences_page_id)) {
            return;
        }

        // Don't redirect on admin, REST API, or AJAX.
        if (is_admin() || wp_doing_ajax() || defined('REST_REQUEST')) {
            return;
        }

        // Build redirect URL with return URL.
        $current_url = home_url(add_query_arg([]));
        $redirect_url = add_query_arg(
            'redirect',
            urlencode($current_url),
            get_permalink($preferences_page_id)
        );

        wp_safe_redirect($redirect_url);
        exit;
    }
}
