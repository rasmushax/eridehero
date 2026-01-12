<?php
/**
 * Retailer Logos - Get retailer logo images from HFT scrapers.
 *
 * @package ERH\Pricing
 */

declare(strict_types=1);

namespace ERH\Pricing;

/**
 * Retrieves retailer logo images from HFT scraper configuration.
 */
class RetailerLogos {

    /**
     * HFT Scraper Repository instance.
     *
     * @var object|null
     */
    private ?object $repository = null;

    /**
     * Cache of scraper data by domain.
     *
     * @var array<string, object|null>
     */
    private array $cache = [];

    /**
     * Special retailers not in HFT (like Amazon which uses PA-API).
     * Maps identifier to display name and logo filename.
     *
     * @var array<string, array{name: string, logo: string}>
     */
    private const SPECIAL_RETAILERS = [
        'amazon' => [
            'name' => 'Amazon',
            'logo' => 'amazon.svg',
        ],
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init_repository();
    }

    /**
     * Initialize the HFT Scraper Repository if available.
     *
     * @return void
     */
    private function init_repository(): void {
        if (class_exists('HFT_Scraper_Repository')) {
            $this->repository = new \HFT_Scraper_Repository();
        }
    }

    /**
     * Check if HFT repository is available.
     *
     * @return bool True if HFT logos are available.
     */
    public function is_available(): bool {
        return $this->repository !== null;
    }

    /**
     * Get logo URL for a scraper by ID.
     *
     * @param int    $scraper_id The scraper ID.
     * @param string $size       Image size (thumbnail, medium, large, full).
     * @return string|null Logo URL or null if not found.
     */
    public function get_logo_by_id(int $scraper_id, string $size = 'thumbnail'): ?string {
        if (!$this->is_available()) {
            return null;
        }

        $scraper = $this->repository->find_by_id($scraper_id);

        return $this->get_logo_from_scraper($scraper, $size);
    }

    /**
     * Get logo URL for a scraper by domain or identifier.
     *
     * @param string $domain The retailer domain or special identifier (e.g., 'amazon').
     * @param string $size   Image size (thumbnail, medium, large, full).
     * @return string|null Logo URL or null if not found.
     */
    public function get_logo_by_domain(string $domain, string $size = 'thumbnail'): ?string {
        // Check cache first.
        $cache_key = $domain . '_' . $size;
        if (array_key_exists($cache_key, $this->cache)) {
            return $this->cache[$cache_key];
        }

        // Check for special retailers (like Amazon).
        $identifier = strtolower($domain);
        if (isset(self::SPECIAL_RETAILERS[$identifier])) {
            $logo_url = $this->get_special_retailer_logo($identifier);
            $this->cache[$cache_key] = $logo_url;
            return $logo_url;
        }

        // Check if it's an Amazon domain.
        if (strpos($domain, 'amazon.') !== false) {
            $logo_url = $this->get_special_retailer_logo('amazon');
            $this->cache[$cache_key] = $logo_url;
            return $logo_url;
        }

        if (!$this->is_available()) {
            return null;
        }

        // Normalize domain (remove www prefix).
        $domain = preg_replace('/^www\./', '', $domain);

        $scraper = $this->repository->find_by_domain($domain);
        $logo_url = $this->get_logo_from_scraper($scraper, $size);

        // Cache the result.
        $this->cache[$cache_key] = $logo_url;

        return $logo_url;
    }

    /**
     * Get logo HTML img tag for a retailer.
     *
     * @param string $domain The retailer domain.
     * @param string $size   Image size.
     * @param string $class  CSS class for the img tag.
     * @return string HTML img tag or empty string.
     */
    public function get_logo_html(string $domain, string $size = 'thumbnail', string $class = ''): string {
        $url = $this->get_logo_by_domain($domain, $size);

        if (!$url) {
            return '';
        }

        $alt = esc_attr($this->get_retailer_name($domain));
        $class_attr = $class ? ' class="' . esc_attr($class) . '"' : '';

        return sprintf(
            '<img src="%s" alt="%s" loading="lazy" decoding="async"%s />',
            esc_url($url),
            $alt,
            $class_attr
        );
    }

    /**
     * Get retailer name from scraper or special retailers.
     *
     * @param string $domain The retailer domain or special identifier.
     * @return string Retailer name or formatted domain.
     */
    public function get_retailer_name(string $domain): string {
        // Check for special retailers (like Amazon).
        $identifier = strtolower($domain);
        if (isset(self::SPECIAL_RETAILERS[$identifier])) {
            return self::SPECIAL_RETAILERS[$identifier]['name'];
        }

        // Check if it's an Amazon domain.
        if (strpos($domain, 'amazon.') !== false) {
            return self::SPECIAL_RETAILERS['amazon']['name'];
        }

        if (!$this->is_available()) {
            return $this->format_domain_as_name($domain);
        }

        $domain = preg_replace('/^www\./', '', $domain);
        $scraper = $this->repository->find_by_domain($domain);

        if ($scraper && !empty($scraper->name)) {
            return $scraper->name;
        }

        return $this->format_domain_as_name($domain);
    }

    /**
     * Get logo data for multiple domains at once.
     *
     * @param array<string> $domains Array of retailer domains.
     * @param string        $size    Image size.
     * @return array<string, string|null> Logo URLs indexed by domain.
     */
    public function get_logos_bulk(array $domains, string $size = 'thumbnail'): array {
        $logos = [];

        foreach ($domains as $domain) {
            $logos[$domain] = $this->get_logo_by_domain($domain, $size);
        }

        return $logos;
    }

    /**
     * Extract logo URL from scraper object.
     *
     * @param object|null $scraper The scraper object.
     * @param string      $size    Image size.
     * @return string|null Logo URL or null.
     */
    private function get_logo_from_scraper(?object $scraper, string $size): ?string {
        if (!$scraper || empty($scraper->logo_attachment_id)) {
            return null;
        }

        if ($size === 'full') {
            $url = wp_get_attachment_url($scraper->logo_attachment_id);
        } else {
            $url = wp_get_attachment_image_url($scraper->logo_attachment_id, $size);
        }

        return $url ?: null;
    }

    /**
     * Get logo URL for a special retailer (stored in theme assets).
     *
     * @param string $identifier The retailer identifier (e.g., 'amazon').
     * @return string|null Logo URL or null if not found.
     */
    private function get_special_retailer_logo(string $identifier): ?string {
        if (!isset(self::SPECIAL_RETAILERS[$identifier])) {
            return null;
        }

        $logo_file = self::SPECIAL_RETAILERS[$identifier]['logo'];

        // Try theme directory first.
        $theme_logo_path = get_stylesheet_directory() . '/assets/images/logos/' . $logo_file;
        if (file_exists($theme_logo_path)) {
            return get_stylesheet_directory_uri() . '/assets/images/logos/' . $logo_file;
        }

        // Fall back to parent theme.
        $parent_logo_path = get_template_directory() . '/assets/images/logos/' . $logo_file;
        if (file_exists($parent_logo_path)) {
            return get_template_directory_uri() . '/assets/images/logos/' . $logo_file;
        }

        return null;
    }

    /**
     * Format a domain as a display name.
     *
     * @param string $domain The domain.
     * @return string Formatted name.
     */
    private function format_domain_as_name(string $domain): string {
        // Remove www and TLD.
        $name = preg_replace('/^www\./', '', $domain);
        $name = preg_replace('/\.(com|net|org|co|io|us|uk|ca|au|de|fr|eu)$/', '', $name);

        // Capitalize first letter.
        return ucfirst($name);
    }

    /**
     * Clear the internal cache.
     *
     * @return void
     */
    public function clear_cache(): void {
        $this->cache = [];
    }
}
