<?php
declare(strict_types=1);

namespace Housefresh\Tools\Libs\Amazon;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class HFT_Creators_Api_Auth
 *
 * Handles OAuth 2.0 token management for the Amazon Creators API.
 * Fetches bearer tokens from Amazon Cognito and caches them in WordPress transients.
 * One token per region group (NA, EU, FE).
 */
class HFT_Creators_Api_Auth {

    private static string $transient_prefix = 'hft_amazon_token_';
    private static int $token_ttl = 3500; // Just under 3600s expiry for safety margin

    /**
     * Get an OAuth 2.0 access token for the given region group.
     *
     * @param string $region_group The region group ('NA', 'EU', or 'FE').
     * @param string $credential_id The Creators API Credential ID.
     * @param string $credential_secret The Creators API Credential Secret.
     * @param string $token_endpoint The Cognito token endpoint URL.
     * @return string|null The bearer token, or null on failure.
     */
    public static function get_access_token(string $region_group, string $credential_id, string $credential_secret, string $token_endpoint): ?string {
        $transient_key = self::$transient_prefix . strtoupper($region_group);

        // Check cached token
        $cached_token = get_transient($transient_key);
        if ( false !== $cached_token && ! empty($cached_token) ) {
            return $cached_token;
        }

        // Fetch new token from Cognito
        $token = self::fetch_token($credential_id, $credential_secret, $token_endpoint);
        if ( $token ) {
            set_transient($transient_key, $token, self::$token_ttl);
        }

        return $token;
    }

    /**
     * Clear a cached token for a specific region group.
     * Used when a 401 TokenExpired response is received.
     *
     * @param string $region_group The region group ('NA', 'EU', or 'FE').
     */
    public static function clear_cached_token(string $region_group): void {
        delete_transient(self::$transient_prefix . strtoupper($region_group));
    }

    /**
     * Clear all cached tokens (all 3 region groups).
     * Useful when credentials are updated on the settings page.
     */
    public static function clear_all_cached_tokens(): void {
        foreach ( ['NA', 'EU', 'FE'] as $region ) {
            delete_transient(self::$transient_prefix . $region);
        }
    }

    /**
     * Fetch a new bearer token from the Amazon Cognito token endpoint.
     *
     * @param string $credential_id Client ID.
     * @param string $credential_secret Client Secret.
     * @param string $token_endpoint The Cognito OAuth 2.0 token URL.
     * @return string|null The access token, or null on failure.
     */
    private static function fetch_token(string $credential_id, string $credential_secret, string $token_endpoint): ?string {
        try {
            $client = new Client([
                'timeout' => 15.0,
                'connect_timeout' => 10.0,
            ]);

            $response = $client->request('POST', $token_endpoint, [
                'form_params' => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $credential_id,
                    'client_secret' => $credential_secret,
                    'scope'         => 'creatorsapi/default',
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'http_errors' => false,
            ]);

            $status_code = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $parsed = json_decode($body, true);

            if ( $status_code === 200 && isset($parsed['access_token']) ) {
                return $parsed['access_token'];
            }

            $error_desc = $parsed['error_description'] ?? ($parsed['error'] ?? 'Unknown error');
            error_log("[HFT Creators API Auth] Token fetch failed (HTTP {$status_code}): {$error_desc}");
            return null;

        } catch (RequestException $e) {
            error_log("[HFT Creators API Auth] Guzzle error fetching token: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            error_log("[HFT Creators API Auth] Error fetching token: " . $e->getMessage());
            return null;
        }
    }
}
