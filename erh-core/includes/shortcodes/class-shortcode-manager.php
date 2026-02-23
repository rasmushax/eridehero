<?php
/**
 * Shortcode registration and "this" product resolution.
 *
 * @package ERH\Shortcodes
 */

declare(strict_types=1);

namespace ERH\Shortcodes;

/**
 * Registers all 14 shortcodes and resolves the "this" product
 * from a review post's relationship field.
 */
class ShortcodeManager {

    /**
     * Per-post cache for resolved "this" product ID.
     *
     * @var array<int, int|null>
     */
    private array $this_cache = [];

    /**
     * Hook into WordPress init to register shortcodes.
     *
     * @return void
     */
    public function register(): void {
        add_action('init', [$this, 'init_shortcodes']);
    }

    /**
     * Register all shortcodes.
     *
     * @return void
     */
    public function init_shortcodes(): void {
        $stat   = new StatShortcode($this);
        $tables = new ComparisonTables($this);

        // Inline value shortcodes.
        add_shortcode('stat',   [$stat, 'render']);
        add_shortcode('year',   [$stat, 'render_year']);
        add_shortcode('batval', [$stat, 'render_batval']);

        // Comparison table shortcodes.
        add_shortcode('speedcomp',     [$tables, 'speedcomp']);
        add_shortcode('rangetest',     [$tables, 'rangetest']);
        add_shortcode('acceltest',     [$tables, 'acceltest']);
        add_shortcode('accelcomp',     [$tables, 'accelcomp']);
        add_shortcode('hillcomp',      [$tables, 'hillcomp']);
        add_shortcode('rangecomp',     [$tables, 'rangecomp']);
        add_shortcode('rangevsweight', [$tables, 'rangevsweight']);
        add_shortcode('weight',        [$tables, 'weight_table']);
        add_shortcode('braking',       [$tables, 'braking']);
        add_shortcode('batcapcomp',    [$tables, 'batcapcomp']);
        add_shortcode('ipcomp',        [$tables, 'ipcomp']);
    }

    /**
     * Resolve the "this" product for the current post.
     *
     * Review posts have a "relationship" ACF field pointing to a product.
     * Result is cached per post ID within the request.
     *
     * @return int|null The product post ID, or null if not found.
     */
    public function resolve_this_product(): ?int {
        $current_post_id = get_the_ID();
        if (!$current_post_id) {
            return null;
        }

        if (array_key_exists($current_post_id, $this->this_cache)) {
            return $this->this_cache[$current_post_id];
        }

        $product_id   = null;
        $relationship = get_field('relationship', $current_post_id);

        if (!empty($relationship) && is_array($relationship) && isset($relationship[0])) {
            $post       = $relationship[0];
            $product_id = is_object($post) ? (int) $post->ID : (int) $post;
        }

        $this->this_cache[$current_post_id] = $product_id;
        return $product_id;
    }
}
