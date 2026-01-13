<?php
/**
 * Tool Custom Post Type.
 *
 * @package ERH\PostTypes
 */

declare(strict_types=1);

namespace ERH\PostTypes;

/**
 * Handles the Tools custom post type registration.
 * Used for calculators and interactive tools.
 */
class Tool {

    /**
     * Post type slug.
     *
     * @var string
     */
    public const POST_TYPE = 'tool';

    /**
     * Register the post type and hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('init', [$this, 'register_post_type']);
    }

    /**
     * Register the Tools custom post type.
     *
     * @return void
     */
    public function register_post_type(): void {
        $labels = [
            'name'                  => _x('Tools', 'Post type general name', 'erh-core'),
            'singular_name'         => _x('Tool', 'Post type singular name', 'erh-core'),
            'menu_name'             => _x('Tools', 'Admin Menu text', 'erh-core'),
            'name_admin_bar'        => _x('Tool', 'Add New on Toolbar', 'erh-core'),
            'add_new'               => __('Add New', 'erh-core'),
            'add_new_item'          => __('Add New Tool', 'erh-core'),
            'new_item'              => __('New Tool', 'erh-core'),
            'edit_item'             => __('Edit Tool', 'erh-core'),
            'view_item'             => __('View Tool', 'erh-core'),
            'all_items'             => __('All Tools', 'erh-core'),
            'search_items'          => __('Search Tools', 'erh-core'),
            'parent_item_colon'     => __('Parent Tools:', 'erh-core'),
            'not_found'             => __('No tools found.', 'erh-core'),
            'not_found_in_trash'    => __('No tools found in Trash.', 'erh-core'),
            'featured_image'        => _x('Tool Image', 'Overrides the "Featured Image" phrase', 'erh-core'),
            'set_featured_image'    => _x('Set tool image', 'Overrides the "Set featured image" phrase', 'erh-core'),
            'remove_featured_image' => _x('Remove tool image', 'Overrides the "Remove featured image" phrase', 'erh-core'),
            'use_featured_image'    => _x('Use as tool image', 'Overrides the "Use as featured image" phrase', 'erh-core'),
            'archives'              => _x('Tool archives', 'The post type archive label', 'erh-core'),
            'insert_into_item'      => _x('Insert into tool', 'Overrides the "Insert into post" phrase', 'erh-core'),
            'uploaded_to_this_item' => _x('Uploaded to this tool', 'Overrides the "Uploaded to this post" phrase', 'erh-core'),
            'filter_items_list'     => _x('Filter tools list', 'Screen reader text', 'erh-core'),
            'items_list_navigation' => _x('Tools list navigation', 'Screen reader text', 'erh-core'),
            'items_list'            => _x('Tools list', 'Screen reader text', 'erh-core'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'tools', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-calculator',
            'show_in_rest'       => true,
            'rest_base'          => 'tools',
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Get tool slug from post.
     *
     * @param int $tool_id The tool ID.
     * @return string The tool slug.
     */
    public static function get_tool_slug(int $tool_id): string {
        $post = get_post($tool_id);
        return $post ? $post->post_name : '';
    }

    /**
     * Get all published tools.
     *
     * @param int $limit Maximum number of tools to return.
     * @return array<\WP_Post> List of tool posts.
     */
    public static function get_tools(int $limit = -1): array {
        return get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
    }
}
