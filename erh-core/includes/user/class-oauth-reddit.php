<?php
/**
 * Reddit OAuth Provider - Handles Reddit OAuth 2.0 authentication.
 *
 * @package ERH\User
 */

declare(strict_types=1);

namespace ERH\User;

/**
 * Reddit OAuth 2.0 provider implementation.
 */
class OAuthReddit implements OAuthProviderInterface {

    /**
     * Provider name.
     */
    private const NAME = 'reddit';

    /**
     * Reddit OAuth endpoints.
     */
    private const AUTH_URL = 'https://www.reddit.com/api/v1/authorize';
    private const TOKEN_URL = 'https://www.reddit.com/api/v1/access_token';
    private const USERINFO_URL = 'https://oauth.reddit.com/api/v1/me';

    /**
     * OAuth scopes.
     */
    private const SCOPES = 'identity';

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
        $this->client_id = (string) get_option('erh_reddit_client_id', '');
        $this->client_secret = (string) get_option('erh_reddit_client_secret', '');
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
            'duration'      => 'temporary',
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * {@inheritDoc}
     */
    public function get_access_token(string $code): ?array {
        // Reddit requires Basic Auth for token endpoint.
        $auth = base64_encode($this->client_id . ':' . $this->client_secret);

        $response = wp_remote_post(self::TOKEN_URL, [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => $this->get_callback_url(),
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('Reddit OAuth token error: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            error_log('Reddit OAuth token response missing access_token: ' . wp_remote_retrieve_body($response));
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
                'User-Agent'    => 'ERideHero/1.0 (by /u/eridehero)',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('Reddit OAuth userinfo error: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['id']) || empty($body['name'])) {
            error_log('Reddit OAuth userinfo missing id or name: ' . wp_remote_retrieve_body($response));
            return null;
        }

        // Reddit doesn't provide email in the identity scope.
        // We use the username as a fallback identifier.
        return [
            'id'       => $body['id'],
            'email'    => '', // Reddit doesn't expose email.
            'name'     => $body['name'], // This is the username.
            'picture'  => $body['icon_img'] ?? '',
            'username' => $body['name'],
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
        return rest_url('erh/v1/auth/social/reddit/callback');
    }
}
