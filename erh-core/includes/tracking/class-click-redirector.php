<?php
/**
 * Click Redirector - Handles /go/ URL redirects.
 *
 * @package ERH\Tracking
 */

declare(strict_types=1);

namespace ERH\Tracking;

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
     * Click tracker instance.
     *
     * @var ClickTracker
     */
    private ClickTracker $tracker;

    /**
     * Price fetcher instance.
     *
     * @var PriceFetcher|null
     */
    private ?PriceFetcher $price_fetcher = null;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->tracker = new ClickTracker();
    }

    /**
     * Register hooks for URL handling.
     *
     * @return void
     */
    public function register(): void {
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_redirect']);
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
     * Handle the redirect.
     *
     * @return void
     */
    public function handle_redirect(): void {
        $product_slug = get_query_var(self::QUERY_VAR_PRODUCT);

        if (empty($product_slug)) {
            return;
        }

        $link_id = (int) get_query_var(self::QUERY_VAR_LINK);

        // Get product by slug.
        $product = $this->get_product_by_slug($product_slug);

        if (!$product) {
            $this->show_not_found('Product not found.');
            return;
        }

        $product_id = $product->ID;

        // Determine which link to use.
        if ($link_id > 0) {
            // Specific link requested.
            $link = $this->get_specific_link($link_id);
        } else {
            // Auto-pick best link for user's geo.
            $link = $this->get_auto_pick_link($product_id);
        }

        if (!$link) {
            $this->show_not_found('No retailer available for this product.');
            return;
        }

        // Record the click.
        $this->tracker->record_click($link['id'], $product_id);

        // Redirect to affiliate URL.
        $redirect_url = $link['url'];

        // Validate URL before redirecting.
        if (empty($redirect_url)) {
            $this->show_not_found('No redirect URL available.');
            return;
        }

        // Check if URL is valid - filter_var can be too strict, so also check for basic URL structure
        $is_valid = filter_var($redirect_url, FILTER_VALIDATE_URL)
            || preg_match('#^https?://#i', $redirect_url);

        if (!$is_valid) {
            // Log for debugging
            error_log('[ERH ClickRedirector] Invalid URL for link ' . $link['id'] . ': ' . $redirect_url);
            $this->show_not_found('Invalid redirect URL.');
            return;
        }

        // Set cache headers to prevent caching of redirect.
        nocache_headers();

        // Use 302 (temporary) redirect so it's not cached by browsers.
        wp_redirect($redirect_url, 302, 'ERideHero');
        exit;
    }

    /**
     * Get product post by slug.
     *
     * @param string $slug The product slug.
     * @return \WP_Post|null The product post or null.
     */
    private function get_product_by_slug(string $slug): ?\WP_Post {
        $posts = get_posts([
            'name'        => sanitize_title($slug),
            'post_type'   => 'products',
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);

        return $posts[0] ?? null;
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

        // Build the affiliate URL.
        $url = $row['affiliate_link_override'];

        if (empty($url)) {
            // Check if this is an Amazon link (parser_identifier = 'amazon' and tracking_url is ASIN)
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
     * Get user's geo from cookie.
     *
     * @return string|null The geo code or null for default (US).
     */
    private function get_user_geo(): ?string {
        if (isset($_COOKIE['erh_geo'])) {
            $geo = strtoupper(sanitize_text_field(wp_unslash($_COOKIE['erh_geo'])));
            $valid_geos = ['US', 'GB', 'EU', 'CA', 'AU'];

            if (in_array($geo, $valid_geos, true)) {
                return $geo;
            }
        }

        return 'US'; // Default to US.
    }

    /**
     * Build Amazon affiliate URL from ASIN and geo.
     *
     * @param string      $asin The Amazon ASIN.
     * @param string|null $geo  The geo target (US, GB, DE, etc.).
     * @return string The full Amazon affiliate URL.
     */
    private function build_amazon_url(string $asin, ?string $geo): string {
        // Map geo to Amazon domain
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

        // Get associate tag from HFT settings
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

        // Replace template placeholders.
        $url = str_replace(
            ['{URL}', '{URLE}', '{ID}'],
            [$tracking_url, urlencode($tracking_url), $this->extract_product_id($tracking_url)],
            $format
        );

        return $url;
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
        global $wp_query;
        $wp_query->set_404();
        status_header(404);

        // Try to load 404 template.
        if ($template = get_404_template()) {
            include $template;
        } else {
            wp_die(
                esc_html($message),
                esc_html__('Product Not Found', 'erh-core'),
                ['response' => 404]
            );
        }
        exit;
    }

    /**
     * Generate a tracked URL for a product and link.
     *
     * @param string   $product_slug The product slug.
     * @param int|null $link_id      Optional specific link ID.
     * @return string The tracked URL.
     */
    public static function get_tracked_url(string $product_slug, ?int $link_id = null): string {
        $base_url = home_url('/go/' . $product_slug . '/');

        if ($link_id) {
            return home_url('/go/' . $product_slug . '/' . $link_id . '/');
        }

        return $base_url;
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
