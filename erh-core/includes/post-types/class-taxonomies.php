<?php
/**
 * Custom Taxonomies Registration
 *
 * @package ERH\PostTypes
 */

namespace ERH\PostTypes;

/**
 * Handles registration of custom taxonomies for products.
 */
class Taxonomies {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('init', [$this, 'register_taxonomies']);
    }

    /**
     * Register custom taxonomies.
     *
     * @return void
     */
    public function register_taxonomies(): void {
        $this->register_product_type_taxonomy();
        $this->register_brands_taxonomy();
    }

    /**
     * Register the product_type taxonomy.
     *
     * @return void
     */
    private function register_product_type_taxonomy(): void {
        $labels = [
            'name'                       => _x('Product Types', 'taxonomy general name', 'erh-core'),
            'singular_name'              => _x('Product Type', 'taxonomy singular name', 'erh-core'),
            'search_items'               => __('Search Product Types', 'erh-core'),
            'popular_items'              => __('Popular Product Types', 'erh-core'),
            'all_items'                  => __('All Product Types', 'erh-core'),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __('Edit Product Type', 'erh-core'),
            'update_item'                => __('Update Product Type', 'erh-core'),
            'add_new_item'               => __('Add New Product Type', 'erh-core'),
            'new_item_name'              => __('New Product Type Name', 'erh-core'),
            'separate_items_with_commas' => __('Separate product types with commas', 'erh-core'),
            'add_or_remove_items'        => __('Add or remove product types', 'erh-core'),
            'choose_from_most_used'      => __('Choose from the most used product types', 'erh-core'),
            'not_found'                  => __('No product types found.', 'erh-core'),
            'menu_name'                  => __('Product Types', 'erh-core'),
        ];

        $args = [
            'hierarchical'          => false,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_in_rest'          => true,
            'show_admin_column'     => true,
            'publicly_queryable'    => false, // No frontend archive pages.
            'query_var'             => true,
            'rewrite'               => false,
            'meta_box_cb'           => false, // Use ACF or custom metabox
        ];

        register_taxonomy('product_type', ['products'], $args);

        // Register default product types if they don't exist.
        $this->maybe_create_default_product_types();
    }

    /**
     * Register the brands taxonomy.
     *
     * @return void
     */
    private function register_brands_taxonomy(): void {
        $labels = [
            'name'                       => _x('Brands', 'taxonomy general name', 'erh-core'),
            'singular_name'              => _x('Brand', 'taxonomy singular name', 'erh-core'),
            'search_items'               => __('Search Brands', 'erh-core'),
            'popular_items'              => __('Popular Brands', 'erh-core'),
            'all_items'                  => __('All Brands', 'erh-core'),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __('Edit Brand', 'erh-core'),
            'update_item'                => __('Update Brand', 'erh-core'),
            'add_new_item'               => __('Add New Brand', 'erh-core'),
            'new_item_name'              => __('New Brand Name', 'erh-core'),
            'separate_items_with_commas' => __('Separate brands with commas', 'erh-core'),
            'add_or_remove_items'        => __('Add or remove brands', 'erh-core'),
            'choose_from_most_used'      => __('Choose from the most used brands', 'erh-core'),
            'not_found'                  => __('No brands found.', 'erh-core'),
            'menu_name'                  => __('Brands', 'erh-core'),
        ];

        $args = [
            'hierarchical'          => false,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_in_rest'          => true,
            'show_admin_column'     => true,
            'publicly_queryable'    => false, // No frontend archive pages.
            'query_var'             => true,
            'rewrite'               => false,
        ];

        register_taxonomy('brand', ['products'], $args);
    }

    /**
     * Create default product types if they don't exist.
     *
     * @return void
     */
    private function maybe_create_default_product_types(): void {
        $default_types = [
            'electric-scooter'    => 'Electric Scooter',
            'electric-bike'       => 'Electric Bike',
            'electric-unicycle'   => 'Electric Unicycle',
            'electric-skateboard' => 'Electric Skateboard',
            'hoverboard'          => 'Hoverboard',
            'other'               => 'Other',
        ];

        foreach ($default_types as $slug => $name) {
            if (!term_exists($slug, 'product_type')) {
                wp_insert_term($name, 'product_type', ['slug' => $slug]);
            }
        }
    }

    /**
     * Get product type term ID by slug.
     *
     * @param string $slug The term slug.
     * @return int|null The term ID or null if not found.
     */
    public static function get_product_type_id(string $slug): ?int {
        $term = get_term_by('slug', $slug, 'product_type');
        return $term ? $term->term_id : null;
    }

    /**
     * Get brand term ID by name (creates if doesn't exist).
     *
     * @param string $brand_name The brand name.
     * @return int|null The term ID or null on failure.
     */
    public static function get_or_create_brand(string $brand_name): ?int {
        $brand_name = trim($brand_name);
        if (empty($brand_name)) {
            return null;
        }

        $term = get_term_by('name', $brand_name, 'brand');
        if ($term) {
            return $term->term_id;
        }

        // Create the brand.
        $result = wp_insert_term($brand_name, 'brand');
        if (is_wp_error($result)) {
            return null;
        }

        return $result['term_id'];
    }

    /**
     * Map old product_type ACF value to taxonomy slug.
     *
     * @param string $old_value The old ACF product_type value.
     * @return string|null The taxonomy slug or null if not found.
     */
    public static function map_old_product_type(string $old_value): ?string {
        $map = [
            'Electric Scooter'    => 'electric-scooter',
            'Electric Bike'       => 'electric-bike',
            'Electric Unicycle'   => 'electric-unicycle',
            'Electric Skateboard' => 'electric-skateboard',
            'Hoverboard'          => 'hoverboard',
            'Other'               => 'other',
        ];

        return $map[$old_value] ?? null;
    }
}
