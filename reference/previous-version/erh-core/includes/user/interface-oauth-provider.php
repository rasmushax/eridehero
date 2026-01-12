<?php
/**
 * OAuth Provider Interface - Contract for OAuth 2.0 providers.
 *
 * @package ERH\User
 */

declare(strict_types=1);

namespace ERH\User;

/**
 * Interface for OAuth 2.0 provider implementations.
 */
interface OAuthProviderInterface {

    /**
     * Get the provider name.
     *
     * @return string The provider name (e.g., 'google', 'facebook', 'reddit').
     */
    public function get_name(): string;

    /**
     * Get the authorization URL to redirect the user to.
     *
     * @param string $state CSRF state token.
     * @return string The authorization URL.
     */
    public function get_authorization_url(string $state): string;

    /**
     * Exchange authorization code for access token.
     *
     * @param string $code The authorization code from the provider.
     * @return array{access_token: string, token_type: string, expires_in?: int, refresh_token?: string}|null
     */
    public function get_access_token(string $code): ?array;

    /**
     * Get user profile from the provider using access token.
     *
     * @param string $access_token The access token.
     * @return array{id: string, email: string, name?: string, picture?: string}|null
     */
    public function get_user_profile(string $access_token): ?array;

    /**
     * Check if the provider is configured (has credentials).
     *
     * @return bool True if configured.
     */
    public function is_configured(): bool;

    /**
     * Get the callback URL for this provider.
     *
     * @return string The callback URL.
     */
    public function get_callback_url(): string;
}
