<?php
/**
 * Social Auth - Orchestrates OAuth authentication across providers.
 *
 * @package ERH\User
 */

declare(strict_types=1);

namespace ERH\User;

/**
 * Manages social login flows for Google, Facebook, and Reddit.
 */
class SocialAuth {

    /**
     * Available OAuth providers.
     *
     * @var array<string, OAuthProviderInterface>
     */
    private array $providers = [];

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
     * Transient prefix for OAuth state.
     */
    private const STATE_TRANSIENT_PREFIX = 'erh_oauth_state_';

    /**
     * Constructor.
     *
     * @param UserRepository|null $user_repo Optional user repository instance.
     */
    public function __construct(?UserRepository $user_repo = null) {
        $this->user_repo = $user_repo ?? new UserRepository();
        $this->init_providers();
    }

    /**
     * Initialize OAuth providers.
     *
     * @return void
     */
    private function init_providers(): void {
        $this->providers['google'] = new OAuthGoogle();
        $this->providers['facebook'] = new OAuthFacebook();
        $this->providers['reddit'] = new OAuthReddit();
    }

    /**
     * Register hooks and REST routes.
     *
     * @return void
     */
    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Initiate OAuth flow.
        register_rest_route(self::REST_NAMESPACE, '/auth/social/(?P<provider>google|facebook|reddit)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'initiate_oauth'],
            'permission_callback' => '__return_true',
            'args'                => [
                'provider' => [
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => ['google', 'facebook', 'reddit'],
                ],
                'redirect' => [
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ],
        ]);

        // OAuth callback.
        register_rest_route(self::REST_NAMESPACE, '/auth/social/(?P<provider>google|facebook|reddit)/callback', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_callback'],
            'permission_callback' => '__return_true',
            'args'                => [
                'provider' => [
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => ['google', 'facebook', 'reddit'],
                ],
                'code'  => ['type' => 'string'],
                'state' => ['type' => 'string'],
                'error' => ['type' => 'string'],
            ],
        ]);

        // Get available providers.
        register_rest_route(self::REST_NAMESPACE, '/auth/social/providers', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_available_providers'],
            'permission_callback' => '__return_true',
        ]);

        // Link/unlink social account (for logged-in users).
        register_rest_route(self::REST_NAMESPACE, '/user/social/(?P<provider>google|facebook|reddit)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'unlink_provider'],
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
     * Initiate OAuth flow by redirecting to provider.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or redirect.
     */
    public function initiate_oauth(\WP_REST_Request $request) {
        $provider_name = $request->get_param('provider');
        $redirect = $request->get_param('redirect') ?: home_url('/');

        $provider = $this->get_provider($provider_name);

        if (!$provider) {
            return new \WP_Error(
                'invalid_provider',
                'Invalid OAuth provider.',
                ['status' => 400]
            );
        }

        if (!$provider->is_configured()) {
            return new \WP_Error(
                'provider_not_configured',
                'This login method is not available.',
                ['status' => 503]
            );
        }

        // Generate state token for CSRF protection.
        $state = $this->generate_state($provider_name, $redirect);

        // Get authorization URL.
        $auth_url = $provider->get_authorization_url($state);

        // Redirect to provider.
        wp_redirect($auth_url);
        exit;
    }

    /**
     * Handle OAuth callback from provider.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or redirect.
     */
    public function handle_callback(\WP_REST_Request $request) {
        $provider_name = $request->get_param('provider');
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        $error = $request->get_param('error');

        // Check for OAuth error.
        if ($error) {
            return $this->redirect_with_error('OAuth error: ' . $error);
        }

        // Verify state.
        $state_data = $this->verify_state($state);
        if (!$state_data) {
            return $this->redirect_with_error('Invalid or expired state token.');
        }

        // Ensure provider matches.
        if ($state_data['provider'] !== $provider_name) {
            return $this->redirect_with_error('Provider mismatch.');
        }

        $provider = $this->get_provider($provider_name);
        if (!$provider) {
            return $this->redirect_with_error('Invalid provider.');
        }

        // Exchange code for token.
        $token_data = $provider->get_access_token($code);
        if (!$token_data) {
            return $this->redirect_with_error('Failed to get access token.');
        }

        // Get user profile from provider.
        $profile = $provider->get_user_profile($token_data['access_token']);
        if (!$profile) {
            return $this->redirect_with_error('Failed to get user profile.');
        }

        // Handle the authentication.
        $result = $this->authenticate_user($provider_name, $profile);

        if (is_wp_error($result)) {
            // Check for special redirect case (no email, need to collect it).
            if ($result->get_error_code() === 'email_required_redirect') {
                // Clear the OAuth state (we have a new pending state now).
                $this->clear_state($state);

                // Redirect to complete-profile page (URL is in error message).
                wp_redirect($result->get_error_message());
                exit;
            }

            return $this->redirect_with_error($result->get_error_message());
        }

        // Clear state.
        $this->clear_state($state);

        // Build redirect URL.
        $redirect_url = $state_data['redirect'] ?: home_url('/');

        // If a social account was auto-linked to existing account, add toast param.
        if (!empty($result['linked']) && !empty($result['provider'])) {
            $redirect_url = add_query_arg('linked', $result['provider'], $redirect_url);
        }

        // If new user, redirect to email preferences onboarding.
        if (!empty($result['new_user'])) {
            $return_url = rawurlencode($redirect_url);
            $redirect_url = home_url('/email-preferences/?redirect=' . $return_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Get available OAuth providers.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response.
     */
    public function get_available_providers(\WP_REST_Request $request): \WP_REST_Response {
        $available = [];

        foreach ($this->providers as $name => $provider) {
            if ($provider->is_configured()) {
                $available[] = [
                    'name'      => $name,
                    'login_url' => rest_url(self::REST_NAMESPACE . '/auth/social/' . $name),
                ];
            }
        }

        return new \WP_REST_Response([
            'success'   => true,
            'providers' => $available,
        ], 200);
    }

    /**
     * Unlink a social provider from the current user.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function unlink_provider(\WP_REST_Request $request) {
        $provider_name = $request->get_param('provider');
        $user_id = get_current_user_id();

        // Check if user has a password set (can't unlink if no other auth method).
        $user = get_userdata($user_id);
        $linked_providers = $this->user_repo->get_linked_providers($user_id);

        // Count linked providers.
        $linked_count = count(array_filter($linked_providers));

        // Check if user has a password.
        $has_password = !empty($user->user_pass);

        if (!$has_password && $linked_count <= 1) {
            return new \WP_Error(
                'cannot_unlink',
                'You cannot unlink your only login method. Please set a password first.',
                ['status' => 400]
            );
        }

        $success = $this->user_repo->unlink_social_account($user_id, $provider_name);

        if (!$success) {
            return new \WP_Error(
                'unlink_failed',
                'Failed to unlink provider.',
                ['status' => 500]
            );
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => ucfirst($provider_name) . ' account unlinked successfully.',
        ], 200);
    }

    /**
     * Authenticate user with OAuth profile.
     *
     * @param string               $provider_name The provider name.
     * @param array<string, mixed> $profile       The user profile from provider.
     * @return array{user_id: int, linked: bool}|\WP_Error Result array or error.
     */
    private function authenticate_user(string $provider_name, array $profile) {
        $provider_id = $profile['id'];
        $email = $profile['email'] ?? '';
        $name = $profile['name'] ?? '';

        // First, check if this provider ID is already linked to a user.
        $existing_user = $this->user_repo->get_by_social_id($provider_name, $provider_id);

        if ($existing_user) {
            // Log in the existing user.
            $this->login_user($existing_user->ID);
            return ['user_id' => $existing_user->ID, 'linked' => false];
        }

        // Check if we have an email and if a user exists with that email.
        if (!empty($email)) {
            $user_by_email = $this->user_repo->get_by_email($email);

            if ($user_by_email) {
                // Link the social account to this existing user.
                $this->user_repo->link_social_account($user_by_email->ID, $provider_name, $provider_id);
                $this->login_user($user_by_email->ID);
                // Return with linked=true to show toast notification.
                return ['user_id' => $user_by_email->ID, 'linked' => true, 'provider' => $provider_name];
            }
        }

        // No existing user found, create a new one.
        $result = $this->create_user_from_profile($provider_name, $profile);

        if (is_wp_error($result)) {
            return $result;
        }

        return ['user_id' => $result, 'linked' => false, 'new_user' => true];
    }

    /**
     * Create a new user from OAuth profile.
     *
     * @param string               $provider_name The provider name.
     * @param array<string, mixed> $profile       The user profile.
     * @return int|\WP_Error User ID on success, WP_Error on failure.
     */
    private function create_user_from_profile(string $provider_name, array $profile) {
        $provider_id = $profile['id'];
        $email = $profile['email'] ?? '';
        $name = $profile['name'] ?? '';
        $username = $profile['username'] ?? '';

        // If no email (Reddit), redirect to complete-profile page to collect email.
        if (empty($email)) {
            // Store pending OAuth data in transient.
            $pending_state = wp_generate_password(32, false);
            set_transient('erh_oauth_pending_' . $pending_state, [
                'provider'    => $provider_name,
                'provider_id' => $provider_id,
                'name'        => $name,
                'username'    => $username,
            ], 600); // 10 minutes.

            // Return special error code to trigger redirect.
            return new \WP_Error(
                'email_required_redirect',
                home_url('/complete-profile/?provider=' . $provider_name . '&state=' . $pending_state),
                ['status' => 302]
            );
        }

        // Generate username.
        $base_username = $this->generate_username($name, $username, $email);
        $final_username = $this->ensure_unique_username($base_username);

        // Generate random password.
        $password = wp_generate_password(24, true, true);

        // Create user.
        $user_id = wp_create_user($final_username, $password, $email);

        if (is_wp_error($user_id)) {
            return $user_id;
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
        $this->user_repo->link_social_account($user_id, $provider_name, $provider_id);

        // Store registration info.
        $this->user_repo->set_registration_ip($user_id, RateLimiter::get_client_ip());

        // Trigger welcome email.
        do_action('erh_user_registered', $user_id, $user);

        // Log in the user.
        $this->login_user($user_id);

        return $user_id;
    }

    /**
     * Log in a user.
     *
     * @param int $user_id The user ID.
     * @return void
     */
    private function login_user(int $user_id): void {
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        do_action('wp_login', get_userdata($user_id)->user_login, get_userdata($user_id));
    }

    /**
     * Generate a username from profile data.
     *
     * @param string $name     The display name.
     * @param string $username The username from provider.
     * @param string $email    The email address.
     * @return string The generated username.
     */
    private function generate_username(string $name, string $username, string $email): string {
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
     * @param string $username The base username.
     * @return string A unique username.
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
     * Generate a state token for CSRF protection.
     *
     * @param string $provider The provider name.
     * @param string $redirect The redirect URL after auth.
     * @return string The state token.
     */
    private function generate_state(string $provider, string $redirect): string {
        $state = wp_generate_password(32, false);

        set_transient(self::STATE_TRANSIENT_PREFIX . $state, [
            'provider' => $provider,
            'redirect' => $redirect,
            'created'  => time(),
        ], 600); // 10 minutes.

        return $state;
    }

    /**
     * Verify a state token.
     *
     * @param string $state The state token.
     * @return array<string, mixed>|null The state data or null if invalid.
     */
    private function verify_state(string $state): ?array {
        if (empty($state)) {
            return null;
        }

        $data = get_transient(self::STATE_TRANSIENT_PREFIX . $state);

        if (!$data || !is_array($data)) {
            return null;
        }

        // Check if expired (extra safety).
        if (time() - $data['created'] > 600) {
            $this->clear_state($state);
            return null;
        }

        return $data;
    }

    /**
     * Clear a state token.
     *
     * @param string $state The state token.
     * @return void
     */
    private function clear_state(string $state): void {
        delete_transient(self::STATE_TRANSIENT_PREFIX . $state);
    }

    /**
     * Redirect with an error message.
     *
     * @param string $message The error message.
     * @return void
     */
    private function redirect_with_error(string $message): void {
        $login_url = home_url('/login/');
        $redirect_url = add_query_arg('error', urlencode($message), $login_url);
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Get a provider by name.
     *
     * @param string $name The provider name.
     * @return OAuthProviderInterface|null The provider or null.
     */
    private function get_provider(string $name): ?OAuthProviderInterface {
        return $this->providers[$name] ?? null;
    }
}
