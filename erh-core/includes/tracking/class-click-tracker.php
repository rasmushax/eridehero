<?php
/**
 * Click Tracker - Records affiliate link clicks.
 *
 * @package ERH\Tracking
 */

declare(strict_types=1);

namespace ERH\Tracking;

/**
 * Records click data to the erh_clicks table.
 */
class ClickTracker {

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Clicks table name.
     *
     * @var string
     */
    private string $table_name;

    /**
     * Whether debug logging is enabled.
     *
     * @var bool
     */
    private bool $debug_enabled;

    /**
     * Known bot user agent patterns.
     *
     * @var array
     */
    private const BOT_PATTERNS = [
        // Search engine bots.
        'googlebot',
        'bingbot',
        'slurp',           // Yahoo
        'duckduckbot',
        'baiduspider',
        'yandexbot',
        'sogou',
        'exabot',
        'facebot',
        'facebookexternalhit',

        // SEO and monitoring tools.
        'semrush',
        'ahrefs',
        'mj12bot',        // Majestic
        'majestic12',
        'screaming frog',
        'dotbot',
        'rogerbot',
        'sistrix',
        'moz.com',        // Moz SEO (not 'moz' which matches Mozilla!)

        // General bot indicators.
        'bot',
        'spider',
        'crawler',
        'scraper',
        'fetch',
        'slurp',
        'archive',
        'wget',
        'curl',
        'python-requests',
        'python-urllib',
        'java/',
        'httpclient',
        'libwww',
        'headless',
        'phantom',
        'selenium',
        'puppeteer',
        'playwright',

        // Link checkers and validators.
        'linkcheck',
        'w3c_validator',
        'w3c-checklink',

        // Social media crawlers.
        'twitterbot',
        'linkedinbot',
        'pinterest',
        'slackbot',
        'telegrambot',
        'discordbot',
        'whatsapp',

        // AI/LLM crawlers.
        'gptbot',
        'chatgpt',
        'anthropic',
        'claudebot',
        'perplexity',
        'cohere',

        // Other common bots.
        'uptimerobot',
        'pingdom',
        'site24x7',
        'statuscake',
    ];

    /**
     * Valid geo regions.
     *
     * @var array
     */
    private const VALID_GEOS = ['US', 'GB', 'EU', 'CA', 'AU'];

    /**
     * Country to region mapping.
     *
     * @var array
     */
    private const COUNTRY_TO_REGION = [
        // US
        'US' => 'US',
        // UK
        'GB' => 'GB',
        'UK' => 'GB',
        // Canada
        'CA' => 'CA',
        // Australia/NZ
        'AU' => 'AU',
        'NZ' => 'AU',
        // EU countries
        'DE' => 'EU',
        'FR' => 'EU',
        'IT' => 'EU',
        'ES' => 'EU',
        'NL' => 'EU',
        'BE' => 'EU',
        'AT' => 'EU',
        'IE' => 'EU',
        'PT' => 'EU',
        'FI' => 'EU',
        'GR' => 'EU',
        'PL' => 'EU',
        'SE' => 'EU',
        'DK' => 'EU',
        'NO' => 'EU', // Not EU but uses EUR region
        'CH' => 'EU',
        'CZ' => 'EU',
        'RO' => 'EU',
        'HU' => 'EU',
        'SK' => 'EU',
        'BG' => 'EU',
        'HR' => 'EU',
        'SI' => 'EU',
        'LT' => 'EU',
        'LV' => 'EU',
        'EE' => 'EU',
        'LU' => 'EU',
        'MT' => 'EU',
        'CY' => 'EU',
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . ERH_TABLE_CLICKS;
        $this->debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Log debug message if debugging is enabled.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context data.
     */
    private function log(string $message, array $context = []): void {
        if (!$this->debug_enabled) {
            return;
        }

        $log_message = '[ERH Click Tracker] ' . $message;
        if (!empty($context)) {
            $log_message .= ' | ' . wp_json_encode($context);
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log($log_message);
    }

    /**
     * Record a click event.
     *
     * @param int $link_id    The tracked link ID from HFT.
     * @param int $product_id The product post ID.
     * @return bool True on success.
     */
    public function record_click(int $link_id, int $product_id): bool {
        $start_time = microtime(true);
        $timings = [];

        // Bot detection.
        $bot_start = microtime(true);
        $is_bot = $this->detect_bot();
        $timings['bot_detection'] = round((microtime(true) - $bot_start) * 1000, 2);

        // Geo detection.
        $geo_start = microtime(true);
        $user_geo = $this->get_user_geo();
        $geo_source = $user_geo ? 'cookie' : null;

        // If no geo from cookie, try IPInfo fallback.
        if (!$user_geo) {
            $user_geo = $this->get_geo_from_ipinfo();
            $geo_source = $user_geo ? 'ipinfo' : 'none';
        }
        $timings['geo_detection'] = round((microtime(true) - $geo_start) * 1000, 2);

        // Device detection.
        $device_start = microtime(true);
        $device_type = $this->get_device_type();
        $timings['device_detection'] = round((microtime(true) - $device_start) * 1000, 2);

        // Get referrer info.
        $referrer_url = $this->get_referrer_url();
        $referrer_path = $this->get_referrer_path();

        // Database insert.
        $db_start = microtime(true);
        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'tracked_link_id' => $link_id,
                'product_id'      => $product_id,
                'referrer_url'    => $referrer_url,
                'referrer_path'   => $referrer_path,
                'user_geo'        => $user_geo,
                'user_id'         => $this->get_user_id(),
                'device_type'     => $device_type,
                'is_bot'          => $is_bot ? 1 : 0,
                'clicked_at'      => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s']
        );
        $timings['db_insert'] = round((microtime(true) - $db_start) * 1000, 2);

        $total_time = round((microtime(true) - $start_time) * 1000, 2);

        // Log comprehensive debug info.
        $this->log('Click recorded', [
            'link_id'       => $link_id,
            'product_id'    => $product_id,
            'is_bot'        => $is_bot,
            'geo'           => $user_geo ?? 'unknown',
            'geo_source'    => $geo_source,
            'device'        => $device_type,
            'referrer_path' => $referrer_path,
            'success'       => $result !== false,
            'timings_ms'    => $timings,
            'total_ms'      => $total_time,
        ]);

        return $result !== false;
    }

    /**
     * Detect if the request is from a bot.
     *
     * @return bool True if likely a bot.
     */
    private function detect_bot(): bool {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            $this->log('Bot detected: no user agent');
            return true;
        }

        $ua = strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])));

        // Empty user agent is suspicious.
        if (empty($ua)) {
            $this->log('Bot detected: empty user agent');
            return true;
        }

        // Check against known bot patterns.
        foreach (self::BOT_PATTERNS as $pattern) {
            if (strpos($ua, $pattern) !== false) {
                $this->log('Bot detected: pattern match', ['pattern' => $pattern, 'ua_snippet' => substr($ua, 0, 100)]);
                return true;
            }
        }

        // Additional heuristics.

        // Very short user agents are suspicious.
        if (strlen($ua) < 20) {
            $this->log('Bot detected: short UA', ['length' => strlen($ua), 'ua' => $ua]);
            return true;
        }

        // No browser-like user agent.
        $browser_indicators = ['mozilla', 'chrome', 'safari', 'firefox', 'edge', 'opera'];
        $has_browser_indicator = false;
        foreach ($browser_indicators as $indicator) {
            if (strpos($ua, $indicator) !== false) {
                $has_browser_indicator = true;
                break;
            }
        }

        // If no browser indicator and no Accept-Language header, likely a bot.
        if (!$has_browser_indicator && empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $this->log('Bot detected: no browser indicator and no Accept-Language');
            return true;
        }

        $this->log('Human detected', ['ua_snippet' => substr($ua, 0, 80)]);
        return false;
    }

    /**
     * Get the referrer URL from HTTP headers.
     *
     * @return string|null The referrer URL or null.
     */
    private function get_referrer_url(): ?string {
        $referrer = isset($_SERVER['HTTP_REFERER']) ? sanitize_url(wp_unslash($_SERVER['HTTP_REFERER'])) : null;

        if (!$referrer) {
            return null;
        }

        // Limit length for database storage.
        return substr($referrer, 0, 500);
    }

    /**
     * Get the referrer path (for grouping in stats).
     *
     * @return string|null The path portion of the referrer or null.
     */
    private function get_referrer_path(): ?string {
        $referrer = $this->get_referrer_url();

        if (!$referrer) {
            return null;
        }

        $parsed = wp_parse_url($referrer);

        if (!isset($parsed['path'])) {
            return '/';
        }

        // Normalize path: remove trailing slash except for root.
        $path = $parsed['path'];
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return substr($path, 0, 255);
    }

    /**
     * Get the user's geo from cookie.
     *
     * The erh_geo cookie is set by geo-price.js when the user's region is detected.
     *
     * @return string|null The geo code (US, GB, EU, CA, AU) or null.
     */
    private function get_user_geo(): ?string {
        // Check cookie first (set by geo-price.js detection).
        if (isset($_COOKIE['erh_geo'])) {
            $geo = strtoupper(sanitize_text_field(wp_unslash($_COOKIE['erh_geo'])));

            if (in_array($geo, self::VALID_GEOS, true)) {
                $this->log('Geo from cookie', ['geo' => $geo]);
                return $geo;
            }
            $this->log('Invalid geo in cookie', ['cookie_value' => $geo]);
        } else {
            $this->log('No erh_geo cookie present');
        }

        return null;
    }

    /**
     * Get user geo from IPInfo API (fallback for external traffic).
     *
     * Used when no cookie is present (e.g., links from YouTube descriptions).
     *
     * @return string|null The geo code or null on failure.
     */
    private function get_geo_from_ipinfo(): ?string {
        // Get IPInfo token from HFT settings.
        $hft_settings = get_option('hft_settings', []);
        $token = $hft_settings['ipinfo_api_token'] ?? '';

        if (empty($token)) {
            $this->log('IPInfo: no token configured');
            return null;
        }

        // Get user IP.
        $ip = $this->get_client_ip();

        // Skip local/private IPs (no point calling IPInfo for these).
        if (!$ip || $this->is_local_ip($ip)) {
            $this->log('IPInfo: skipped - local/private IP', ['ip' => $ip ?? 'null']);
            return null;
        }

        // Rate limit: max 1 IPInfo call per IP per hour (use transient).
        $cache_key = 'erh_ipinfo_' . md5($ip);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            $this->log('IPInfo: using cached result', ['ip' => $ip, 'cached_geo' => $cached]);
            return $cached === 'none' ? null : $cached;
        }

        $this->log('IPInfo: making API call', ['ip' => $ip]);

        try {
            $api_start = microtime(true);
            $response = wp_remote_get(
                "https://ipinfo.io/{$ip}/json?token={$token}",
                [
                    'timeout' => 2, // Short timeout to not delay redirects.
                    'sslverify' => true,
                ]
            );
            $api_time = round((microtime(true) - $api_start) * 1000, 2);

            if (is_wp_error($response)) {
                $this->log('IPInfo: API error', ['error' => $response->get_error_message(), 'time_ms' => $api_time]);
                set_transient($cache_key, 'none', HOUR_IN_SECONDS);
                return null;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!empty($data['country'])) {
                $country = strtoupper($data['country']);
                $region = self::COUNTRY_TO_REGION[$country] ?? 'US';

                $this->log('IPInfo: success', [
                    'ip'       => $ip,
                    'country'  => $country,
                    'region'   => $region,
                    'time_ms'  => $api_time,
                ]);

                // Cache the result.
                set_transient($cache_key, $region, HOUR_IN_SECONDS);
                return $region;
            }

            $this->log('IPInfo: no country in response', ['response' => $body, 'time_ms' => $api_time]);
        } catch (\Exception $e) {
            $this->log('IPInfo: exception', ['error' => $e->getMessage()]);
        }

        set_transient($cache_key, 'none', HOUR_IN_SECONDS);
        return null;
    }

    /**
     * Check if an IP address is local/private (not routable on internet).
     *
     * @param string $ip The IP address to check.
     * @return bool True if local/private.
     */
    private function is_local_ip(string $ip): bool {
        // IPv6 localhost.
        if ($ip === '::1') {
            return true;
        }

        // IPv4 localhost and private ranges.
        if (
            $ip === '127.0.0.1' ||
            strpos($ip, '127.') === 0 ||
            strpos($ip, '192.168.') === 0 ||
            strpos($ip, '10.') === 0 ||
            strpos($ip, '172.16.') === 0 ||
            strpos($ip, '172.17.') === 0 ||
            strpos($ip, '172.18.') === 0 ||
            strpos($ip, '172.19.') === 0 ||
            strpos($ip, '172.20.') === 0 ||
            strpos($ip, '172.21.') === 0 ||
            strpos($ip, '172.22.') === 0 ||
            strpos($ip, '172.23.') === 0 ||
            strpos($ip, '172.24.') === 0 ||
            strpos($ip, '172.25.') === 0 ||
            strpos($ip, '172.26.') === 0 ||
            strpos($ip, '172.27.') === 0 ||
            strpos($ip, '172.28.') === 0 ||
            strpos($ip, '172.29.') === 0 ||
            strpos($ip, '172.30.') === 0 ||
            strpos($ip, '172.31.') === 0
        ) {
            return true;
        }

        // IPv6 private/link-local (fe80::, fc00::, fd00::).
        $ip_lower = strtolower($ip);
        if (
            strpos($ip_lower, 'fe80:') === 0 ||
            strpos($ip_lower, 'fc00:') === 0 ||
            strpos($ip_lower, 'fd00:') === 0
        ) {
            return true;
        }

        return false;
    }

    /**
     * Get client IP address.
     *
     * @return string|null The IP address or null.
     */
    private function get_client_ip(): ?string {
        // Check various headers for proxied requests.
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare.
            'HTTP_X_FORWARDED_FOR',      // Generic proxy.
            'HTTP_X_REAL_IP',            // Nginx.
            'REMOTE_ADDR',               // Direct connection.
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                // X-Forwarded-For can contain multiple IPs; take the first.
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP.
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Get the current user ID if logged in.
     *
     * @return int|null User ID or null if not logged in.
     */
    private function get_user_id(): ?int {
        $user_id = get_current_user_id();
        return $user_id > 0 ? $user_id : null;
    }

    /**
     * Detect device type from User-Agent.
     *
     * @return string 'mobile', 'tablet', or 'desktop'.
     */
    private function get_device_type(): string {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            $this->log('Device: no UA, defaulting to desktop');
            return 'desktop';
        }

        $ua = strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])));

        // Check for tablets first (before mobile, as tablets often have 'mobile' in UA).
        $tablet_patterns = [
            'ipad',
            'tablet',
            'kindle',
            'playbook',
            'silk',
            'sm-t',       // Samsung tablets.
            'gt-p',       // Samsung tablets.
            'nexus 7',
            'nexus 9',
            'nexus 10',
            'surface',
            'tab',        // Various Android tablets.
        ];
        foreach ($tablet_patterns as $pattern) {
            if (strpos($ua, $pattern) !== false) {
                $this->log('Device: tablet', ['matched_pattern' => $pattern]);
                return 'tablet';
            }
        }

        // Check for mobile (after tablet check).
        $mobile_patterns = [
            'mobile',
            'android',
            'iphone',
            'ipod',
            'blackberry',
            'bb10',
            'opera mini',
            'opera mobi',
            'iemobile',
            'windows phone',
            'webos',
            'palm',
            'symbian',
            'nokia',
            'samsung-gt',
            'lg-',
            'htc',
            'sony',
        ];
        foreach ($mobile_patterns as $pattern) {
            if (strpos($ua, $pattern) !== false) {
                // But exclude tablets that might match 'android'.
                if ($pattern === 'android' && strpos($ua, 'mobile') === false) {
                    $this->log('Device: tablet (android without mobile)', ['matched_pattern' => $pattern]);
                    return 'tablet'; // Android without 'mobile' is usually a tablet.
                }
                $this->log('Device: mobile', ['matched_pattern' => $pattern]);
                return 'mobile';
            }
        }

        $this->log('Device: desktop (no mobile/tablet patterns matched)');
        return 'desktop';
    }

    /**
     * Get click count for a specific product.
     *
     * @param int  $product_id  The product post ID.
     * @param int  $days        Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return int Click count.
     */
    public function get_product_click_count(int $product_id, int $days = 30, bool $exclude_bots = true): int {
        $bot_clause = $exclude_bots ? 'AND is_bot = 0' : '';

        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE product_id = %d
                AND clicked_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                {$bot_clause}",
                $product_id,
                $days
            )
        );

        return (int) $count;
    }

    /**
     * Get click count for a specific tracked link.
     *
     * @param int  $link_id      The tracked link ID.
     * @param int  $days         Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return int Click count.
     */
    public function get_link_click_count(int $link_id, int $days = 30, bool $exclude_bots = true): int {
        $bot_clause = $exclude_bots ? 'AND is_bot = 0' : '';

        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE tracked_link_id = %d
                AND clicked_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                {$bot_clause}",
                $link_id,
                $days
            )
        );

        return (int) $count;
    }

    /**
     * Clean up old click data.
     *
     * @param int $days_to_keep Number of days of data to retain.
     * @return int Number of rows deleted.
     */
    public function cleanup_old_clicks(int $days_to_keep = 90): int {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name}
                WHERE clicked_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_to_keep
            )
        );

        return $result !== false ? (int) $result : 0;
    }
}
