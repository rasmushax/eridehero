<?php
/**
 * Mailchimp Sync - Handles newsletter subscription sync with Mailchimp.
 *
 * @package ERH\Email
 */

declare(strict_types=1);

namespace ERH\Email;

use ERH\User\UserRepository;

/**
 * Syncs newsletter subscriptions between WordPress and Mailchimp.
 */
class MailchimpSync {

    /**
     * Mailchimp API base URL.
     *
     * @var string
     */
    private string $api_url;

    /**
     * Mailchimp API key.
     *
     * @var string
     */
    private string $api_key;

    /**
     * Mailchimp list/audience ID.
     *
     * @var string
     */
    private string $list_id;

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
        $this->api_key = (string) get_option('erh_mailchimp_api_key', '');
        $this->list_id = (string) get_option('erh_mailchimp_list_id', '');
        $this->user_repo = $user_repo ?? new UserRepository();

        // Extract datacenter from API key.
        if (!empty($this->api_key)) {
            $dc = substr($this->api_key, strpos($this->api_key, '-') + 1);
            $this->api_url = 'https://' . $dc . '.api.mailchimp.com/3.0';
        } else {
            $this->api_url = '';
        }
    }

    /**
     * Register hooks and REST routes.
     *
     * @return void
     */
    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);

        // Listen for newsletter preference changes.
        add_action('erh_newsletter_subscription_changed', [$this, 'handle_subscription_change'], 10, 2);

        // Listen for email changes.
        add_action('erh_user_email_changed', [$this, 'handle_email_change'], 10, 3);
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Mailchimp webhook endpoint.
        register_rest_route(self::REST_NAMESPACE, '/webhooks/mailchimp', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true',
        ]);

        // Mailchimp webhook verification (GET request).
        register_rest_route(self::REST_NAMESPACE, '/webhooks/mailchimp', [
            'methods'             => 'GET',
            'callback'            => [$this, 'verify_webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Check if Mailchimp is configured.
     *
     * @return bool True if configured.
     */
    public function is_configured(): bool {
        return !empty($this->api_key) && !empty($this->list_id);
    }

    /**
     * Subscribe a user to the newsletter.
     *
     * @param int $user_id The user ID.
     * @return bool True on success.
     */
    public function subscribe_user(int $user_id): bool {
        if (!$this->is_configured()) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $subscriber_hash = $this->get_subscriber_hash($user->user_email);

        $result = $this->api_request('PUT', "/lists/{$this->list_id}/members/{$subscriber_hash}", [
            'email_address' => $user->user_email,
            'status_if_new' => 'subscribed',
            'status'        => 'subscribed',
            'merge_fields'  => [
                'FNAME' => $user->first_name ?: $user->display_name,
                'LNAME' => $user->last_name ?: '',
            ],
        ]);

        if (!$result || isset($result['status']) && $result['status'] >= 400) {
            error_log('Mailchimp subscribe error for user ' . $user_id . ': ' . json_encode($result));
            return false;
        }

        return true;
    }

    /**
     * Unsubscribe a user from the newsletter.
     *
     * @param int $user_id The user ID.
     * @return bool True on success.
     */
    public function unsubscribe_user(int $user_id): bool {
        if (!$this->is_configured()) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $subscriber_hash = $this->get_subscriber_hash($user->user_email);

        $result = $this->api_request('PATCH', "/lists/{$this->list_id}/members/{$subscriber_hash}", [
            'status' => 'unsubscribed',
        ]);

        if (!$result || isset($result['status']) && $result['status'] >= 400) {
            error_log('Mailchimp unsubscribe error for user ' . $user_id . ': ' . json_encode($result));
            return false;
        }

        return true;
    }

    /**
     * Handle subscription change from user preferences.
     *
     * @param int  $user_id    The user ID.
     * @param bool $subscribed Whether subscribed.
     * @return void
     */
    public function handle_subscription_change(int $user_id, bool $subscribed): void {
        if ($subscribed) {
            $this->subscribe_user($user_id);
        } else {
            $this->unsubscribe_user($user_id);
        }
    }

    /**
     * Handle email change - update Mailchimp subscription.
     *
     * @param int    $user_id   The user ID.
     * @param string $old_email The old email.
     * @param string $new_email The new email.
     * @return void
     */
    public function handle_email_change(int $user_id, string $old_email, string $new_email): void {
        if (!$this->is_configured()) {
            return;
        }

        // Check if user is subscribed to newsletter.
        $preferences = $this->user_repo->get_preferences($user_id);
        if (!$preferences['newsletter_subscription']) {
            return;
        }

        // Unsubscribe old email.
        $old_hash = $this->get_subscriber_hash($old_email);
        $this->api_request('PATCH', "/lists/{$this->list_id}/members/{$old_hash}", [
            'status' => 'unsubscribed',
        ]);

        // Subscribe new email.
        $user = get_userdata($user_id);
        $new_hash = $this->get_subscriber_hash($new_email);
        $this->api_request('PUT', "/lists/{$this->list_id}/members/{$new_hash}", [
            'email_address' => $new_email,
            'status_if_new' => 'subscribed',
            'status'        => 'subscribed',
            'merge_fields'  => [
                'FNAME' => $user->first_name ?: $user->display_name,
                'LNAME' => $user->last_name ?: '',
            ],
        ]);
    }

    /**
     * Handle Mailchimp webhook (unsubscribe, profile updates, etc.).
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response.
     */
    public function handle_webhook(\WP_REST_Request $request): \WP_REST_Response {
        $body = $request->get_body();
        $data = [];

        // Mailchimp sends form-encoded data.
        parse_str($body, $data);

        // Also try JSON.
        if (empty($data)) {
            $data = json_decode($body, true) ?: [];
        }

        $type = $data['type'] ?? '';
        $email = $data['data']['email'] ?? '';

        if (empty($email)) {
            return new \WP_REST_Response(['status' => 'ok'], 200);
        }

        // Handle different webhook types.
        switch ($type) {
            case 'unsubscribe':
            case 'cleaned': // Cleaned = email bounced/invalid.
                $this->handle_mailchimp_unsubscribe($email);
                break;

            case 'subscribe':
                // Could handle re-subscribe from Mailchimp here if needed.
                break;

            case 'profile':
            case 'upemail':
                // Profile update - could sync back to WordPress if needed.
                break;
        }

        // Always return 200 to acknowledge receipt.
        return new \WP_REST_Response(['status' => 'ok'], 200);
    }

    /**
     * Verify webhook endpoint (Mailchimp sends GET request to verify).
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response.
     */
    public function verify_webhook(\WP_REST_Request $request): \WP_REST_Response {
        return new \WP_REST_Response(['status' => 'ok'], 200);
    }

    /**
     * Handle unsubscribe from Mailchimp.
     *
     * @param string $email The email address.
     * @return void
     */
    private function handle_mailchimp_unsubscribe(string $email): void {
        $user = $this->user_repo->get_by_email($email);

        if (!$user) {
            return;
        }

        // Update user preferences to reflect unsubscription.
        $this->user_repo->update_preferences($user->ID, [
            'newsletter_subscription' => false,
        ]);

        error_log('User ' . $user->ID . ' unsubscribed from newsletter via Mailchimp webhook.');
    }

    /**
     * Make an API request to Mailchimp.
     *
     * @param string               $method   The HTTP method.
     * @param string               $endpoint The API endpoint.
     * @param array<string, mixed> $data     The request data.
     * @return array<string, mixed>|null Response data or null on error.
     */
    private function api_request(string $method, string $endpoint, array $data = []): ?array {
        if (!$this->is_configured()) {
            return null;
        }

        $url = $this->api_url . $endpoint;

        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('anystring:' . $this->api_key),
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ];

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('Mailchimp API error: ' . $response->get_error_message());
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Get subscriber hash for Mailchimp API.
     *
     * @param string $email The email address.
     * @return string The MD5 hash.
     */
    private function get_subscriber_hash(string $email): string {
        return md5(strtolower(trim($email)));
    }

    /**
     * Get the webhook URL for Mailchimp configuration.
     *
     * @return string The webhook URL.
     */
    public function get_webhook_url(): string {
        return rest_url(self::REST_NAMESPACE . '/webhooks/mailchimp');
    }
}
