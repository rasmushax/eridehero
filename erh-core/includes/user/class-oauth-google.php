<?php
/**
 * Google OAuth Provider - Handles Google OAuth 2.0 authentication.
 *
 * @package ERH\User
 */

declare(strict_types=1);

namespace ERH\User;

/**
 * Google OAuth 2.0 provider implementation.
 */
class OAuthGoogle implements OAuthProviderInterface {

    /**
     * Provider name.
     */
    private const NAME = 'google';

    /**
     * Google OAuth endpoints.
     */
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

    /**
     * OAuth scopes.
     */
    private const SCOPES = 'openid email profile';

    /**
     * Client ID.
     *
     * @var string
     */
    private string $client_id;

    /**
     * Client secret.
     *
     * @var string
     */
    private string $client_secret;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->client_id = (string) get_option('erh_google_client_id', '');
        $this->client_secret = (string) get_option('erh_google_client_secret', '');
    }

    /**
     * {@inheritDoc}
     */
    public function get_name(): string {
        return self::NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function get_authorization_url(string $state): string {
        $params = [
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->get_callback_url(),
            'response_type' => 'code',
            'scope'         => self::SCOPES,
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * {@inheritDoc}
     */
    public function get_access_token(string $code): ?array {
        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $this->get_callback_url(),
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('Google OAuth token error: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            error_log('Google OAuth token response missing access_token: ' . wp_remote_retrieve_body($response));
            return null;
        }

        return [
            'access_token'  => $body['access_token'],
            'token_type'    => $body['token_type'] ?? 'Bearer',
            'expires_in'    => $body['expires_in'] ?? 3600,
            'refresh_token' => $body['refresh_token'] ?? null,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function get_user_profile(string $access_token): ?array {
        $response = wp_remote_get(self::USERINFO_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('Google OAuth userinfo error: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['id']) || empty($body['email'])) {
            error_log('Google OAuth userinfo missing id or email: ' . wp_remote_retrieve_body($response));
            return null;
        }

        return [
            'id'      => $body['id'],
            'email'   => $body['email'],
            'name'    => $body['name'] ?? '',
            'picture' => $body['picture'] ?? '',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function is_configured(): bool {
        return !empty($this->client_id) && !empty($this->client_secret);
    }

    /**
     * {@inheritDoc}
     */
    public function get_callback_url(): string {
        return rest_url('erh/v1/auth/social/google/callback');
    }
}
