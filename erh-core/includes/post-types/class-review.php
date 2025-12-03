<?php
/**
 * Review Custom Post Type.
 *
 * @package ERH\PostTypes
 */

declare(strict_types=1);

namespace ERH\PostTypes;

/**
 * Handles the Review custom post type registration and functionality.
 */
class Review {

    /**
     * Post type slug.
     *
     * @var string
     */
    public const POST_TYPE = 'review';

    /**
     * Register the post type and hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta'], 10, 2);

        // Add admin columns.
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
    }

    /**
     * Register the Review custom post type.
     *
     * @return void
     */
    public function register_post_type(): void {
        $labels = [
            'name'                  => _x('Reviews', 'Post type general name', 'erh-core'),
            'singular_name'         => _x('Review', 'Post type singular name', 'erh-core'),
            'menu_name'             => _x('Reviews', 'Admin Menu text', 'erh-core'),
            'name_admin_bar'        => _x('Review', 'Add New on Toolbar', 'erh-core'),
            'add_new'               => __('Add New', 'erh-core'),
            'add_new_item'          => __('Add New Review', 'erh-core'),
            'new_item'              => __('New Review', 'erh-core'),
            'edit_item'             => __('Edit Review', 'erh-core'),
            'view_item'             => __('View Review', 'erh-core'),
            'all_items'             => __('All Reviews', 'erh-core'),
            'search_items'          => __('Search Reviews', 'erh-core'),
            'parent_item_colon'     => __('Parent Reviews:', 'erh-core'),
            'not_found'             => __('No reviews found.', 'erh-core'),
            'not_found_in_trash'    => __('No reviews found in Trash.', 'erh-core'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-star-filled',
            'show_in_rest'       => true,
            'rest_base'          => 'reviews',
            'supports'           => ['title', 'author'],
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Add meta boxes for the review post type.
     *
     * @return void
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'erh_review_details',
            __('Review Details', 'erh-core'),
            [$this, 'render_details_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render the review details meta box.
     *
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function render_details_meta_box(\WP_Post $post): void {
        wp_nonce_field('erh_review_meta', 'erh_review_meta_nonce');

        $product_id = get_post_meta($post->ID, 'product', true);
        $score = get_post_meta($post->ID, 'score', true);
        $text = get_post_meta($post->ID, 'text', true);
        $review_image = get_post_meta($post->ID, 'review_image', true);

        // Get all products for the dropdown.
        $products = get_posts([
            'post_type'      => Product::POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ]);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="erh_review_product"><?php esc_html_e('Product', 'erh-core'); ?></label></th>
                <td>
                    <select name="erh_review_product" id="erh_review_product" class="regular-text">
                        <option value=""><?php esc_html_e('Select a product', 'erh-core'); ?></option>
                        <?php foreach ($products as $product) : ?>
                            <option value="<?php echo esc_attr((string)$product->ID); ?>" <?php selected($product_id, $product->ID); ?>>
                                <?php echo esc_html($product->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="erh_review_score"><?php esc_html_e('Score (1-5)', 'erh-core'); ?></label></th>
                <td>
                    <select name="erh_review_score" id="erh_review_score">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <option value="<?php echo esc_attr((string)$i); ?>" <?php selected($score, $i); ?>>
                                <?php echo esc_html((string)$i); ?> <?php echo str_repeat('★', $i); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="erh_review_text"><?php esc_html_e('Review Text', 'erh-core'); ?></label></th>
                <td>
                    <textarea name="erh_review_text" id="erh_review_text" class="large-text" rows="6"><?php echo esc_textarea($text); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="erh_review_image"><?php esc_html_e('Review Image ID', 'erh-core'); ?></label></th>
                <td>
                    <input type="number" name="erh_review_image" id="erh_review_image" value="<?php echo esc_attr((string)$review_image); ?>" class="regular-text">
                    <?php if ($review_image) : ?>
                        <?php $image_url = wp_get_attachment_image_url((int)$review_image, 'thumbnail'); ?>
                        <?php if ($image_url) : ?>
                            <br><img src="<?php echo esc_url($image_url); ?>" alt="" style="margin-top: 10px; max-width: 150px;">
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save review meta data.
     *
     * @param int      $post_id The post ID.
     * @param \WP_Post $post    The post object.
     * @return void
     */
    public function save_meta(int $post_id, \WP_Post $post): void {
        // Verify nonce.
        if (!isset($_POST['erh_review_meta_nonce']) ||
            !wp_verify_nonce($_POST['erh_review_meta_nonce'], 'erh_review_meta')) {
            return;
        }

        // Check autosave.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save fields.
        if (isset($_POST['erh_review_product'])) {
            update_post_meta($post_id, 'product', absint($_POST['erh_review_product']));
        }

        if (isset($_POST['erh_review_score'])) {
            $score = absint($_POST['erh_review_score']);
            $score = max(1, min(5, $score)); // Clamp between 1-5.
            update_post_meta($post_id, 'score', $score);
        }

        if (isset($_POST['erh_review_text'])) {
            update_post_meta($post_id, 'text', sanitize_textarea_field($_POST['erh_review_text']));
        }

        if (isset($_POST['erh_review_image'])) {
            update_post_meta($post_id, 'review_image', absint($_POST['erh_review_image']));
        }
    }

    /**
     * Add custom admin columns.
     *
     * @param array<string, string> $columns The existing columns.
     * @return array<string, string> Modified columns.
     */
    public function add_admin_columns(array $columns): array {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'title') {
                $new_columns['product'] = __('Product', 'erh-core');
                $new_columns['score'] = __('Score', 'erh-core');
                $new_columns['reviewer'] = __('Reviewer', 'erh-core');
            }
        }

        return $new_columns;
    }

    /**
     * Render custom admin column content.
     *
     * @param string $column  The column name.
     * @param int    $post_id The post ID.
     * @return void
     */
    public function render_admin_columns(string $column, int $post_id): void {
        switch ($column) {
            case 'product':
                $product_id = get_post_meta($post_id, 'product', true);
                if ($product_id) {
                    $product = get_post((int)$product_id);
                    if ($product) {
                        printf(
                            '<a href="%s">%s</a>',
                            esc_url(get_edit_post_link((int)$product_id) ?: ''),
                            esc_html($product->post_title)
                        );
                    }
                }
                break;

            case 'score':
                $score = get_post_meta($post_id, 'score', true);
                if ($score) {
                    echo str_repeat('★', (int)$score) . str_repeat('☆', 5 - (int)$score);
                }
                break;

            case 'reviewer':
                $post = get_post($post_id);
                if ($post) {
                    $author = get_userdata((int)$post->post_author);
                    if ($author) {
                        echo esc_html($author->display_name);
                    }
                }
                break;
        }
    }

    /**
     * Get reviews for a product.
     *
     * @param int $product_id The product ID.
     * @return array{ratings_distribution: array<string, mixed>, reviews: array<int, array<string, mixed>>}
     */
    public static function get_reviews(int $product_id): array {
        $reviews = [];
        $ratings_distribution = [
            'ratings_count'  => 0,
            'average_rating' => 0,
            'count_1_star'   => 0,
            'count_2_star'   => 0,
            'count_3_star'   => 0,
            'count_4_star'   => 0,
            'count_5_star'   => 0,
        ];
        $total_score = 0;

        $args = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'product',
                    'value'   => $product_id,
                    'compare' => '=',
                ],
            ],
        ];

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $review_id = get_the_ID();
                $author_id = get_post_field('post_author', $review_id);
                $score = (int)get_post_meta($review_id, 'score', true);
                $text = get_post_meta($review_id, 'text', true);

                // Get review image URLs.
                $image_id = get_post_meta($review_id, 'review_image', true);
                $thumbnail_url = '';
                $large_url = '';
                if ($image_id) {
                    $thumbnail_url = wp_get_attachment_image_url((int)$image_id, 'thumbnail') ?: '';
                    $large_url = wp_get_attachment_image_url((int)$image_id, 'large') ?: '';
                }

                $reviews[] = [
                    'id'            => $review_id,
                    'text'          => $text,
                    'score'         => $score,
                    'author'        => get_the_author_meta('display_name', (int)$author_id),
                    'date'          => get_the_date('Y-m-d H:i:s'),
                    'thumbnail_url' => $thumbnail_url,
                    'large_url'     => $large_url,
                ];

                // Update ratings distribution.
                $ratings_distribution['ratings_count']++;
                $total_score += $score;
                $star_count_key = 'count_' . $score . '_star';
                if (isset($ratings_distribution[$star_count_key])) {
                    $ratings_distribution[$star_count_key]++;
                }
            }
            wp_reset_postdata();

            // Calculate average rating.
            if ($ratings_distribution['ratings_count'] > 0) {
                $ratings_distribution['average_rating'] = number_format(
                    round($total_score / $ratings_distribution['ratings_count'], 1),
                    1
                );
            }
        }

        return [
            'ratings_distribution' => $ratings_distribution,
            'reviews'              => $reviews,
        ];
    }
}
