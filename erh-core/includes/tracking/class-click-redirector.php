<?php
/**
 * Click Redirector - Handles /go/ URL redirects.
 *
 * Optimized for performance:
 * - Uses early `parse_request` hook instead of `template_redirect`
 * - Direct SQL queries instead of WP_Query
 * - Async click tracking (records after redirect sent)
 *
 * SEO optimized:
 * - Sends X-Robots-Tag: noindex header
 * - Excluded from RankMath processing
 *
 * @package ERH\Tracking
 */

declare(strict_types=1);

namespace ERH\Tracking;

use ERH\GeoConfig;
use ERH\Pricing\PriceFetcher;

/**
 * Handles URL rewrite rules and redirect logic for tracked links.
 *
 * Supports two URL patterns:
 * - /go/{product-slug}/       -> Auto-pick best retailer for user's geo
 * - /go/{product-slug}/{id}/  -> Specific tracked link by ID
 */
class ClickRedirector {

    /**
     * Query variable for product slug.
     */
    public const QUERY_VAR_PRODUCT = 'erh_go_product';

    /**
     * Query variable for specific link ID.
     */
    public const QUERY_VAR_LINK = 'erh_go_link';

    /**
     * Valid geo regions.
     */
    private const VALID_GEOS = ['US', 'GB', 'EU', 'CA', 'AU'];

    /**
     * Click data to record after redirect (async tracking).
     *
     * @var array|null
     */
    private static ?array $pending_click = null;

    /**
     * Price fetcher instance.
     *
     * @var PriceFetcher|null
     */
    private ?PriceFetcher $price_fetcher = null;

    /**
     * Register hooks for URL handling.
     *
     * @return void
     */
    public function register(): void {
        // Rewrite rules need init hook.
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);

        // Use parse_request for earlier interception (before main query).
        add_action('parse_request', [$this, 'handle_redirect_early'], 1);

        // SEO: Exclude /go/ from RankMath processing.
        // These filters only fire if RankMath is active; methods are type-safe.
        add_filter('rank_math/frontend/robots', [$this, 'rankmath_noindex']);
        add_filter('rank_math/frontend/title', [$this, 'rankmath_exclude'], 1);
        add_filter('rank_math/frontend/description', [$this, 'rankmath_exclude'], 1);

        // Async click tracking: record after response sent.
        add_action('shutdown', [__CLASS__, 'record_pending_click']);
    }

    /**
     * Add rewrite rules for /go/ URLs.
     *
     * @return void
     */
    public function add_rewrite_rules(): void {
        // /go/{product-slug}/ - Auto-pick best retailer.
        add_rewrite_rule(
            '^go/([^/]+)/?$',
            'index.php?' . self::QUERY_VAR_PRODUCT . '=$matches[1]',
            'top'
        );

        // /go/{product-slug}/{link-id}/ - Specific tracked link.
        add_rewrite_rule(
            '^go/([^/]+)/([0-9]+)/?$',
            'index.php?' . self::QUERY_VAR_PRODUCT . '=$matches[1]&' . self::QUERY_VAR_LINK . '=$matches[2]',
            'top'
        );
    }

    /**
     * Register query variables.
     *
     * @param array $vars Existing query vars.
     * @return array Modified query vars.
     */
    public function add_query_vars(array $vars): array {
        $vars[] = self::QUERY_VAR_PRODUCT;
        $vars[] = self::QUERY_VAR_LINK;
        return $vars;
    }

    /**
     * Handle the redirect early in the request lifecycle.
     *
     * Hooked to parse_request (before main WP query runs).
     *
     * @param \WP $wp WordPress environment instance.
     * @return void
     */
    public function handle_redirect_early(\WP $wp): void {
        // Check if this is a /go/ request.
        $product_slug = $wp->query_vars[self::QUERY_VAR_PRODUCT] ?? '';

        if (empty($product_slug)) {
            return;
        }

        $link_id = (int) ($wp->query_vars[self::QUERY_VAR_LINK] ?? 0);

        // Get product by slug using direct query (faster than get_posts).
        $product_id = $this->get_product_id_by_slug($product_slug);

        if (!$product_id) {
            $this->show_not_found('Product not found.');
            return;
        }

        // Determine which link to use.
        if ($link_id > 0) {
            $link = $this->get_specific_link($link_id);
        } else {
            $link = $this->get_auto_pick_link($product_id);
        }

        if (!$link) {
            $this->show_not_found('No retailer available for this product.');
            return;
        }

        // Validate URL before redirecting.
        $redirect_url = $link['url'];

        if (empty($redirect_url)) {
            $this->show_not_found('No redirect URL available.');
            return;
        }

        // Check if URL is valid.
        $is_valid = filter_var($redirect_url, FILTER_VALIDATE_URL)
            || preg_match('#^https?://#i', $redirect_url);

        if (!$is_valid) {
            $this->show_not_found('Invalid redirect URL.');
            return;
        }

        // Queue click for async recording (after redirect).
        self::$pending_click = [
            'link_id'    => $link['id'],
            'product_id' => $product_id,
        ];

        // Perform the redirect.
        $this->do_redirect($redirect_url);
    }

    /**
     * Perform the actual redirect with proper headers.
     *
     * @param string $url The URL to redirect to.
     * @return void
     */
    private function do_redirect(string $url): void {
        // Prevent any output.
        if (ob_get_level()) {
            ob_end_clean();
        }

        // SEO: Tell search engines not to index /go/ URLs.
        header('X-Robots-Tag: noindex, nofollow', true);

        // Prevent caching.
        header('Cache-Control: no-cache, no-store, must-revalidate', true);
        header('Pragma: no-cache', true);
        header('Expires: 0', true);

        // 302 Temporary redirect (affiliate URLs may change).
        header('Location: ' . $url, true, 302);

        // Flush output to browser immediately.
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            // For non-FastCGI environments, flush what we can.
            if (function_exists('ob_flush')) {
                @ob_flush();
            }
            flush();
        }

        // Don't exit yet - let shutdown hook record the click.
        // But prevent WordPress from continuing.
        // We use a flag and check in shutdown.

        // Actually, after fastcgi_finish_request, we can continue execution.
        // The response is already sent to the client.
        // Record click synchronously if fastcgi available, otherwise shutdown hook handles it.
        if (function_exists('fastcgi_finish_request')) {
            self::record_pending_click();
        }

        exit;
    }

    /**
     * Record pending click data (called on shutdown or after fastcgi_finish_request).
     *
     * @return void
     */
    public static function record_pending_click(): void {
        if (self::$pending_click === null) {
            return;
        }

        $click_data = self::$pending_click;
        self::$pending_click = null; // Prevent double-recording.

        // Record the click.
        $tracker = new ClickTracker();
        $tracker->record_click($click_data['link_id'], $click_data['product_id']);
    }

    /**
     * Get product ID by slug using direct SQL query.
     *
     * Much faster than get_posts() or WP_Query for simple lookups.
     *
     * @param string $slug The product slug.
     * @return int|null The product ID or null if not found.
     */
    private function get_product_id_by_slug(string $slug): ?int {
        global $wpdb;

        $product_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                WHERE post_name = %s
                AND post_type = 'products'
                AND post_status = 'publish'
                LIMIT 1",
                sanitize_title($slug)
            )
        );

        return $product_id ? (int) $product_id : null;
    }

    /**
     * Get a specific tracked link by ID.
     *
     * @param int $link_id The tracked link ID.
     * @return array|null Link data or null if not found.
     */
    private function get_specific_link(int $link_id): ?array {
        global $wpdb;

        $tracked_links_table = $wpdb->prefix . 'hft_tracked_links';
        $scrapers_table = $wpdb->prefix . 'hft_scrapers';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    tl.id,
                    tl.product_post_id,
                    tl.tracking_url,
                    tl.affiliate_link_override,
                    tl.parser_identifier,
                    tl.geo_target,
                    tl.market_prices,
                    s.affiliate_link_format
                FROM {$tracked_links_table} tl
                LEFT JOIN {$scrapers_table} s ON tl.scraper_id = s.id
                WHERE tl.id = %d",
                $link_id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        // For Shopify Markets links, use the geo-localized URL for the visitor's region.
        if (!empty($row['market_prices'])) {
            $geo_url = $this->get_market_url_for_user($row['market_prices']);
            if ($geo_url) {
                $row['tracking_url'] = $geo_url;
            }
        }

        // Build the affiliate URL.
        $url = $row['affiliate_link_override'];

        if (empty($url)) {
            // Check if this is an Amazon link.
            if ($row['parser_identifier'] === 'amazon' && !empty($row['tracking_url'])) {
                $url = $this->build_amazon_url($row['tracking_url'], $row['geo_target']);
            } else {
                $url = $this->build_affiliate_url($row);
            }
        }

        return [
            'id'         => (int) $row['id'],
            'product_id' => (int) $row['product_post_id'],
            'url'        => $url,
        ];
    }

    /**
     * Get the best link for auto-pick (user's geo).
     *
     * @param int $product_id The product post ID.
     * @return array|null Link data or null if none available.
     */
    private function get_auto_pick_link(int $product_id): ?array {
        $geo = $this->get_user_geo();

        // Initialize price fetcher if needed.
        if (!$this->price_fetcher) {
            $this->price_fetcher = new PriceFetcher();
        }

        $best_price = $this->price_fetcher->get_best_price($product_id, $geo);

        if (!$best_price) {
            return null;
        }

        return [
            'id'         => $best_price['id'],
            'product_id' => $best_price['product_id'],
            'url'        => $best_price['url'],
        ];
    }

    /**
     * Get the geo-localized product URL for the current visitor from market_prices JSON.
     *
     * Matches the visitor's region to a Shopify Markets entry and returns
     * that market's URL (e.g., /en-ca/ for Canada instead of base US URL).
     *
     * @param string $market_prices_json The market_prices JSON from hft_tracked_links.
     * @return string|null The geo-localized URL, or null if no match.
     */
    private function get_market_url_for_user(string $market_prices_json): ?string {
        $markets = json_decode($market_prices_json, true);
        if (!is_array($markets) || empty($markets)) {
            return null;
        }

        $user_geo = $this->get_user_geo();

        foreach ($markets as $market) {
            if (empty($market['url']) || empty($market['countries'])) {
                continue;
            }

            foreach ($market['countries'] as $country) {
                if (GeoConfig::get_region(strtoupper($country)) === $user_geo) {
                    return $market['url'];
                }
            }
        }

        return null;
    }

    /**
     * Get user's geo from cookie.
     *
     * @return string The geo code (defaults to US).
     */
    private function get_user_geo(): string {
        if (isset($_COOKIE['erh_geo'])) {
            $geo = strtoupper(sanitize_text_field(wp_unslash($_COOKIE['erh_geo'])));

            if (in_array($geo, self::VALID_GEOS, true)) {
                return $geo;
            }
        }

        return 'US';
    }

    /**
     * Build Amazon affiliate URL from ASIN and geo.
     *
     * @param string      $asin The Amazon ASIN.
     * @param string|null $geo  The geo target (US, GB, DE, etc.).
     * @return string The full Amazon affiliate URL.
     */
    private function build_amazon_url(string $asin, ?string $geo): string {
        $domains = [
            'US' => 'www.amazon.com',
            'GB' => 'www.amazon.co.uk',
            'UK' => 'www.amazon.co.uk',
            'DE' => 'www.amazon.de',
            'FR' => 'www.amazon.fr',
            'IT' => 'www.amazon.it',
            'ES' => 'www.amazon.es',
            'CA' => 'www.amazon.ca',
            'AU' => 'www.amazon.com.au',
            'JP' => 'www.amazon.co.jp',
            'IN' => 'www.amazon.in',
            'MX' => 'www.amazon.com.mx',
            'BR' => 'www.amazon.com.br',
        ];

        $domain = $domains[strtoupper($geo ?? 'US')] ?? 'www.amazon.com';
        $tag = $this->get_amazon_associate_tag($geo);

        $url = "https://{$domain}/dp/{$asin}";

        if ($tag) {
            $url .= "?tag={$tag}";
        }

        return $url;
    }

    /**
     * Get Amazon associate tag for a geo from HFT settings.
     *
     * @param string|null $geo The geo target.
     * @return string|null The associate tag or null.
     */
    private function get_amazon_associate_tag(?string $geo): ?string {
        $settings = get_option('hft_settings', []);
        $tags_by_geo = $settings['amazon_associate_tags'] ?? [];
        $geo_upper = strtoupper($geo ?? 'US');
        $global_tag = null;

        foreach ($tags_by_geo as $tag_config) {
            $config_geo = isset($tag_config['geo']) && is_string($tag_config['geo'])
                ? strtoupper(trim($tag_config['geo']))
                : null;
            $tag_value = isset($tag_config['tag']) ? trim($tag_config['tag']) : null;

            if ($config_geo === $geo_upper && !empty($tag_value)) {
                return $tag_value;
            }

            if (($config_geo === 'GLOBAL' || empty($config_geo)) && !empty($tag_value)) {
                $global_tag = $tag_value;
            }
        }

        return $global_tag;
    }

    /**
     * Build affiliate URL from tracking URL and format template.
     *
     * @param array $row Database row with URL and format info.
     * @return string The affiliate URL.
     */
    private function build_affiliate_url(array $row): string {
        $tracking_url = $row['tracking_url'];
        $format = $row['affiliate_link_format'] ?? '';

        if (empty($format)) {
            return $tracking_url;
        }

        return str_replace(
            ['{URL}', '{URLE}', '{ID}'],
            [$tracking_url, urlencode($tracking_url), $this->extract_product_id($tracking_url)],
            $format
        );
    }

    /**
     * Extract product ID from URL (for Amazon ASINs, etc.).
     *
     * @param string $url The product URL.
     * @return string The extracted ID or empty string.
     */
    private function extract_product_id(string $url): string {
        if (preg_match('/\/(?:dp|gp\/product)\/([A-Z0-9]{10})/i', $url, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Show a not found page.
     *
     * @param string $message Error message.
     * @return void
     */
    private function show_not_found(string $message): void {
        // Send noindex header even for 404s.
        header('X-Robots-Tag: noindex, nofollow', true);

        status_header(404);
        nocache_headers();

        // Minimal 404 response (avoid loading full theme).
        wp_die(
            esc_html($message),
            esc_html__('Not Found', 'erh-core'),
            ['response' => 404]
        );
    }

    /**
     * RankMath: Set noindex for /go/ URLs.
     *
     * @param mixed $robots The robots array.
     * @return array Modified robots array.
     */
    public function rankmath_noindex($robots): array {
        // Ensure we have an array (RankMath may pass different types).
        if (!is_array($robots)) {
            $robots = [];
        }

        if ($this->is_go_url()) {
            $robots['index'] = 'noindex';
            $robots['follow'] = 'nofollow';
        }

        return $robots;
    }

    /**
     * RankMath: Skip processing for /go/ URLs.
     *
     * @param mixed $value The value to filter.
     * @return string Original or empty value.
     */
    public function rankmath_exclude($value): string {
        if ($this->is_go_url()) {
            return '';
        }

        return is_string($value) ? $value : '';
    }

    /**
     * Check if current request is a /go/ URL.
     *
     * Handles subfolder installs (e.g., /eridehero/go/).
     *
     * @return bool True if /go/ URL.
     */
    private function is_go_url(): bool {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Get site path for subfolder installs.
        $site_path = wp_parse_url(home_url(), PHP_URL_PATH) ?: '';
        $go_pattern = '#^' . preg_quote($site_path, '#') . '/go/#';

        return (bool) preg_match($go_pattern, $request_uri);
    }

    /**
     * Generate a tracked URL for a product and link.
     *
     * @param string   $product_slug The product slug.
     * @param int|null $link_id      Optional specific link ID.
     * @return string The tracked URL.
     */
    public static function get_tracked_url(string $product_slug, ?int $link_id = null): string {
        if ($link_id) {
            return home_url('/go/' . $product_slug . '/' . $link_id . '/');
        }

        return home_url('/go/' . $product_slug . '/');
    }

    /**
     * Flush rewrite rules.
     *
     * Should be called on plugin activation/deactivation.
     *
     * @return void
     */
    public static function flush_rules(): void {
        $redirector = new self();
        $redirector->add_rewrite_rules();
        flush_rewrite_rules();
    }
}
