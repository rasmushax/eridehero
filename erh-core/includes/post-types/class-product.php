<?php
/**
 * Product Custom Post Type.
 *
 * @package ERH\PostTypes
 */

declare(strict_types=1);

namespace ERH\PostTypes;

/**
 * Handles the Products custom post type registration and functionality.
 */
class Product {

    /**
     * Post type slug.
     *
     * @var string
     */
    public const POST_TYPE = 'products';

    /**
     * Register the post type and hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('init', [$this, 'register_post_type']);
        // Note: Taxonomies are now registered by the Taxonomies class.
    }

    /**
     * Register the Products custom post type.
     *
     * @return void
     */
    public function register_post_type(): void {
        $labels = [
            'name'                  => _x('Products', 'Post type general name', 'erh-core'),
            'singular_name'         => _x('Product', 'Post type singular name', 'erh-core'),
            'menu_name'             => _x('Products', 'Admin Menu text', 'erh-core'),
            'name_admin_bar'        => _x('Product', 'Add New on Toolbar', 'erh-core'),
            'add_new'               => __('Add New', 'erh-core'),
            'add_new_item'          => __('Add New Product', 'erh-core'),
            'new_item'              => __('New Product', 'erh-core'),
            'edit_item'             => __('Edit Product', 'erh-core'),
            'view_item'             => __('View Product', 'erh-core'),
            'all_items'             => __('All Products', 'erh-core'),
            'search_items'          => __('Search Products', 'erh-core'),
            'parent_item_colon'     => __('Parent Products:', 'erh-core'),
            'not_found'             => __('No products found.', 'erh-core'),
            'not_found_in_trash'    => __('No products found in Trash.', 'erh-core'),
            'featured_image'        => _x('Product Image', 'Overrides the "Featured Image" phrase', 'erh-core'),
            'set_featured_image'    => _x('Set product image', 'Overrides the "Set featured image" phrase', 'erh-core'),
            'remove_featured_image' => _x('Remove product image', 'Overrides the "Remove featured image" phrase', 'erh-core'),
            'use_featured_image'    => _x('Use as product image', 'Overrides the "Use as featured image" phrase', 'erh-core'),
            'archives'              => _x('Product archives', 'The post type archive label', 'erh-core'),
            'insert_into_item'      => _x('Insert into product', 'Overrides the "Insert into post" phrase', 'erh-core'),
            'uploaded_to_this_item' => _x('Uploaded to this product', 'Overrides the "Uploaded to this post" phrase', 'erh-core'),
            'filter_items_list'     => _x('Filter products list', 'Screen reader text', 'erh-core'),
            'items_list_navigation' => _x('Products list navigation', 'Screen reader text', 'erh-core'),
            'items_list'            => _x('Products list', 'Screen reader text', 'erh-core'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'products', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-cart',
            'show_in_rest'       => true,
            'rest_base'          => 'products',
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Get product type from ACF field.
     *
     * @param int $product_id The product ID.
     * @return string|null The product type or null.
     */
    public static function get_product_type(int $product_id): ?string {
        if (!function_exists('get_field')) {
            return null;
        }

        $product_type = get_field('product_type', $product_id);
        return is_string($product_type) ? $product_type : null;
    }

    /**
     * Get all valid product types.
     *
     * @return array<string> List of product types.
     */
    public static function get_product_types(): array {
        return [
            'Electric Scooter',
            'Electric Bike',
            'Electric Skateboard',
            'Electric Unicycle',
            'Hoverboard',
        ];
    }

    /**
     * Check if a product is of a specific type.
     *
     * @param int    $product_id   The product ID.
     * @param string $product_type The product type to check.
     * @return bool True if the product matches the type.
     */
    public static function is_product_type(int $product_id, string $product_type): bool {
        return self::get_product_type($product_id) === $product_type;
    }

    /**
     * Get the slug-friendly version of a product type.
     *
     * @param string $product_type The product type.
     * @return string The slug.
     */
    public static function get_product_type_slug(string $product_type): string {
        return sanitize_title($product_type);
    }
}
