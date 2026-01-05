<?php
/**
 * User Repository - Centralized user data access.
 *
 * @package ERH\User
 */

declare(strict_types=1);

namespace ERH\User;

/**
 * Provides a clean interface for reading and writing user data.
 */
class UserRepository {

    /**
     * User meta keys used by ERH.
     */
    public const META_PRICE_TRACKER_EMAILS = 'price_trackers_emails';
    public const META_SALES_ROUNDUP_EMAILS = 'sales_roundup_emails';
    public const META_SALES_ROUNDUP_FREQUENCY = 'sales_roundup_frequency';
    public const META_SALES_ROUNDUP_TYPES = 'sales_roundup_types';
    public const META_NEWSLETTER_SUBSCRIPTION = 'newsletter_subscription';
    public const META_REGISTRATION_IP = 'registration_ip';
    public const META_EMAIL_PREFERENCES_SET = 'email_preferences_set';
    public const META_LAST_DEALS_EMAIL_SENT = 'last_deals_email_sent';
    public const META_PASSWORD_RESET_KEY_AGE = 'password_reset_key_age';

    // Social login meta keys.
    public const META_GOOGLE_ID = 'erh_google_id';
    public const META_FACEBOOK_ID = 'erh_facebook_id';
    public const META_REDDIT_ID = 'erh_reddit_id';
    public const META_SOCIAL_LOGIN_PROVIDER = 'social_login_provider';

    /**
     * Valid sales roundup frequencies.
     */
    public const VALID_FREQUENCIES = ['weekly', 'bi-weekly', 'monthly'];

    /**
     * Valid product types for sales roundup.
     * Keys are the slug used in API/storage, values are display names.
     */
    public const VALID_ROUNDUP_TYPES = [
        'escooter'   => 'Electric Scooter',
        'ebike'      => 'Electric Bike',
        'eskate'     => 'Electric Skateboard',
        'euc'        => 'Electric Unicycle',
        'hoverboard' => 'Hoverboard',
    ];

    /**
     * Get a user by ID.
     *
     * @param int $user_id The user ID.
     * @return \WP_User|null The user object or null if not found.
     */
    public function get_by_id(int $user_id): ?\WP_User {
        $user = get_userdata($user_id);
        return $user instanceof \WP_User ? $user : null;
    }

    /**
     * Get a user by email.
     *
     * @param string $email The email address.
     * @return \WP_User|null The user object or null if not found.
     */
    public function get_by_email(string $email): ?\WP_User {
        $user = get_user_by('email', $email);
        return $user instanceof \WP_User ? $user : null;
    }

    /**
     * Get a user by login (username).
     *
     * @param string $login The username.
     * @return \WP_User|null The user object or null if not found.
     */
    public function get_by_login(string $login): ?\WP_User {
        $user = get_user_by('login', $login);
        return $user instanceof \WP_User ? $user : null;
    }

    /**
     * Get a user by social provider ID.
     *
     * @param string $provider The provider name (google, facebook, reddit).
     * @param string $provider_id The provider's user ID.
     * @return \WP_User|null The user object or null if not found.
     */
    public function get_by_social_id(string $provider, string $provider_id): ?\WP_User {
        $meta_key = $this->get_social_meta_key($provider);
        if (!$meta_key) {
            return null;
        }

        $users = get_users([
            'meta_key'   => $meta_key,
            'meta_value' => $provider_id,
            'number'     => 1,
        ]);

        return !empty($users) ? $users[0] : null;
    }

    /**
     * Get user preferences.
     *
     * @param int $user_id The user ID.
     * @return array<string, mixed> The user preferences.
     */
    public function get_preferences(int $user_id): array {
        return [
            'price_tracker_emails'    => $this->get_meta_bool($user_id, self::META_PRICE_TRACKER_EMAILS),
            'sales_roundup_emails'    => $this->get_meta_bool($user_id, self::META_SALES_ROUNDUP_EMAILS),
            'sales_roundup_frequency' => $this->get_sales_roundup_frequency($user_id),
            'sales_roundup_types'     => $this->get_sales_roundup_types($user_id),
            'newsletter_subscription' => $this->get_meta_bool($user_id, self::META_NEWSLETTER_SUBSCRIPTION),
            'email_preferences_set'   => $this->get_meta_bool($user_id, self::META_EMAIL_PREFERENCES_SET),
        ];
    }

    /**
     * Update user preferences.
     *
     * Note: update_user_meta() returns false both on error AND when no change
     * is needed (value already matches). We use update_metadata() which has
     * better return semantics, or just save and verify the value was set.
     *
     * @param int                  $user_id     The user ID.
     * @param array<string, mixed> $preferences The preferences to update.
     * @return bool True on success.
     */
    public function update_preferences(int $user_id, array $preferences): bool {
        if (isset($preferences['price_tracker_emails'])) {
            $this->set_meta_value(
                $user_id,
                self::META_PRICE_TRACKER_EMAILS,
                $preferences['price_tracker_emails'] ? '1' : '0'
            );
        }

        if (isset($preferences['sales_roundup_emails'])) {
            $this->set_meta_value(
                $user_id,
                self::META_SALES_ROUNDUP_EMAILS,
                $preferences['sales_roundup_emails'] ? '1' : '0'
            );
        }

        if (isset($preferences['sales_roundup_frequency'])) {
            $frequency = $preferences['sales_roundup_frequency'];
            if (in_array($frequency, self::VALID_FREQUENCIES, true)) {
                $this->set_meta_value(
                    $user_id,
                    self::META_SALES_ROUNDUP_FREQUENCY,
                    $frequency
                );
            }
        }

        if (isset($preferences['sales_roundup_types'])) {
            $this->set_sales_roundup_types($user_id, $preferences['sales_roundup_types']);
        }

        if (isset($preferences['newsletter_subscription'])) {
            $this->set_meta_value(
                $user_id,
                self::META_NEWSLETTER_SUBSCRIPTION,
                $preferences['newsletter_subscription'] ? '1' : '0'
            );
        }

        // Mark preferences as set.
        $this->set_meta_value($user_id, self::META_EMAIL_PREFERENCES_SET, '1');

        return true;
    }

    /**
     * Set a user meta value, handling the case where value is unchanged.
     *
     * @param int    $user_id  The user ID.
     * @param string $meta_key The meta key.
     * @param string $value    The value to set.
     * @return void
     */
    private function set_meta_value(int $user_id, string $meta_key, string $value): void {
        // update_user_meta returns false when value is unchanged, which is fine.
        // We just need to ensure the value is set, not track if it changed.
        update_user_meta($user_id, $meta_key, $value);
    }

    /**
     * Get the sales roundup frequency for a user.
     *
     * @param int $user_id The user ID.
     * @return string The frequency (weekly, bi-weekly, monthly).
     */
    public function get_sales_roundup_frequency(int $user_id): string {
        $frequency = get_user_meta($user_id, self::META_SALES_ROUNDUP_FREQUENCY, true);
        return in_array($frequency, self::VALID_FREQUENCIES, true) ? $frequency : 'weekly';
    }

    /**
     * Get the product types a user wants in their sales roundup.
     *
     * @param int $user_id The user ID.
     * @return array<string> Array of product type slugs (e.g., ['escooter', 'ebike']).
     */
    public function get_sales_roundup_types(int $user_id): array {
        $types = get_user_meta($user_id, self::META_SALES_ROUNDUP_TYPES, true);

        if (empty($types)) {
            // Default to all types for backwards compatibility
            return array_keys(self::VALID_ROUNDUP_TYPES);
        }

        // Filter to only valid types
        if (is_array($types)) {
            return array_values(array_intersect($types, array_keys(self::VALID_ROUNDUP_TYPES)));
        }

        return array_keys(self::VALID_ROUNDUP_TYPES);
    }

    /**
     * Set the product types a user wants in their sales roundup.
     *
     * @param int           $user_id The user ID.
     * @param array<string> $types   Array of product type slugs.
     * @return void
     */
    public function set_sales_roundup_types(int $user_id, array $types): void {
        // Filter to only valid types
        $valid_types = array_values(array_intersect($types, array_keys(self::VALID_ROUNDUP_TYPES)));
        update_user_meta($user_id, self::META_SALES_ROUNDUP_TYPES, $valid_types);
    }

    /**
     * Check if user has email preferences set.
     *
     * @param int $user_id The user ID.
     * @return bool True if preferences are set.
     */
    public function has_preferences_set(int $user_id): bool {
        return $this->get_meta_bool($user_id, self::META_EMAIL_PREFERENCES_SET);
    }

    /**
     * Link a social provider to a user account.
     *
     * @param int    $user_id     The user ID.
     * @param string $provider    The provider name.
     * @param string $provider_id The provider's user ID.
     * @return bool True on success.
     */
    public function link_social_account(int $user_id, string $provider, string $provider_id): bool {
        $meta_key = $this->get_social_meta_key($provider);
        if (!$meta_key) {
            return false;
        }

        // Check if this provider ID is already linked to another user.
        $existing = $this->get_by_social_id($provider, $provider_id);
        if ($existing && $existing->ID !== $user_id) {
            return false;
        }

        update_user_meta($user_id, $meta_key, $provider_id);
        update_user_meta($user_id, self::META_SOCIAL_LOGIN_PROVIDER, $provider);

        return true;
    }

    /**
     * Unlink a social provider from a user account.
     *
     * @param int    $user_id  The user ID.
     * @param string $provider The provider name.
     * @return bool True on success.
     */
    public function unlink_social_account(int $user_id, string $provider): bool {
        $meta_key = $this->get_social_meta_key($provider);
        if (!$meta_key) {
            return false;
        }

        return delete_user_meta($user_id, $meta_key);
    }

    /**
     * Get linked social providers for a user.
     *
     * @param int $user_id The user ID.
     * @return array<string, string|null> Provider IDs indexed by provider name.
     */
    public function get_linked_providers(int $user_id): array {
        return [
            'google'   => get_user_meta($user_id, self::META_GOOGLE_ID, true) ?: null,
            'facebook' => get_user_meta($user_id, self::META_FACEBOOK_ID, true) ?: null,
            'reddit'   => get_user_meta($user_id, self::META_REDDIT_ID, true) ?: null,
        ];
    }

    /**
     * Record the registration IP for a user.
     *
     * @param int    $user_id The user ID.
     * @param string $ip      The IP address.
     * @return bool True on success.
     */
    public function set_registration_ip(int $user_id, string $ip): bool {
        return (bool) update_user_meta($user_id, self::META_REGISTRATION_IP, $ip);
    }

    /**
     * Get the registration IP for a user.
     *
     * @param int $user_id The user ID.
     * @return string|null The IP address or null.
     */
    public function get_registration_ip(int $user_id): ?string {
        $ip = get_user_meta($user_id, self::META_REGISTRATION_IP, true);
        return $ip ?: null;
    }

    /**
     * Update the last deals email sent timestamp.
     *
     * @param int $user_id The user ID.
     * @return bool True on success.
     */
    public function update_last_deals_email_sent(int $user_id): bool {
        return (bool) update_user_meta(
            $user_id,
            self::META_LAST_DEALS_EMAIL_SENT,
            current_time('mysql', true)
        );
    }

    /**
     * Get the last deals email sent timestamp.
     *
     * @param int $user_id The user ID.
     * @return string|null The timestamp or null.
     */
    public function get_last_deals_email_sent(int $user_id): ?string {
        $timestamp = get_user_meta($user_id, self::META_LAST_DEALS_EMAIL_SENT, true);
        return $timestamp ?: null;
    }

    /**
     * Get users subscribed to sales roundup emails.
     *
     * @param string|null $frequency Optional frequency filter.
     * @return array<\WP_User> Array of users.
     */
    public function get_sales_roundup_subscribers(?string $frequency = null): array {
        $args = [
            'meta_query' => [
                [
                    'key'     => self::META_SALES_ROUNDUP_EMAILS,
                    'value'   => '1',
                    'compare' => '=',
                ],
            ],
        ];

        if ($frequency && in_array($frequency, self::VALID_FREQUENCIES, true)) {
            $args['meta_query'][] = [
                'key'     => self::META_SALES_ROUNDUP_FREQUENCY,
                'value'   => $frequency,
                'compare' => '=',
            ];
        }

        return get_users($args);
    }

    /**
     * Get users subscribed to price tracker emails.
     *
     * @return array<\WP_User> Array of users.
     */
    public function get_price_tracker_subscribers(): array {
        return get_users([
            'meta_query' => [
                [
                    'key'     => self::META_PRICE_TRACKER_EMAILS,
                    'value'   => '1',
                    'compare' => '=',
                ],
            ],
        ]);
    }

    /**
     * Get a boolean user meta value.
     *
     * @param int    $user_id  The user ID.
     * @param string $meta_key The meta key.
     * @return bool The meta value as boolean.
     */
    private function get_meta_bool(int $user_id, string $meta_key): bool {
        return get_user_meta($user_id, $meta_key, true) === '1';
    }

    /**
     * Set a boolean user meta value.
     *
     * @param int    $user_id  The user ID.
     * @param string $meta_key The meta key.
     * @param bool   $value    The value.
     * @return bool True on success.
     */
    private function set_meta_bool(int $user_id, string $meta_key, bool $value): bool {
        return (bool) update_user_meta($user_id, $meta_key, $value ? '1' : '0');
    }

    /**
     * Get the meta key for a social provider.
     *
     * @param string $provider The provider name.
     * @return string|null The meta key or null if invalid provider.
     */
    private function get_social_meta_key(string $provider): ?string {
        $keys = [
            'google'   => self::META_GOOGLE_ID,
            'facebook' => self::META_FACEBOOK_ID,
            'reddit'   => self::META_REDDIT_ID,
        ];

        return $keys[strtolower($provider)] ?? null;
    }
}
