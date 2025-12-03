<?php
/**
 * Review handler for submitting and managing reviews.
 *
 * @package ERH\Reviews
 */

declare(strict_types=1);

namespace ERH\Reviews;

use ERH\User\RateLimiter;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles review submission via REST API.
 */
class ReviewHandler {

    /**
     * REST API namespace.
     */
    private const API_NAMESPACE = 'erh/v1';

    /**
     * Maximum review text length.
     */
    private const MAX_REVIEW_LENGTH = 5000;

    /**
     * Maximum image file size in bytes (5MB).
     */
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024;

    /**
     * Allowed image MIME types.
     */
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Rate limiter instance.
     *
     * @var RateLimiter
     */
    private RateLimiter $rate_limiter;

    /**
     * Review query instance.
     *
     * @var ReviewQuery
     */
    private ReviewQuery $review_query;

    /**
     * Constructor.
     *
     * @param RateLimiter $rate_limiter Rate limiter instance.
     * @param ReviewQuery $review_query Review query instance.
     */
    public function __construct(RateLimiter $rate_limiter, ReviewQuery $review_query) {
        $this->rate_limiter = $rate_limiter;
        $this->review_query = $review_query;
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Submit a review.
        register_rest_route(self::API_NAMESPACE, '/reviews', [
            'methods'             => 'POST',
            'callback'            => [$this, 'submit_review'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        // Get reviews for a product.
        register_rest_route(self::API_NAMESPACE, '/products/(?P<id>\d+)/reviews', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_product_reviews'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                ],
            ],
        ]);

        // Check if user has reviewed a product.
        register_rest_route(self::API_NAMESPACE, '/products/(?P<id>\d+)/review-status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_review_status'],
            'permission_callback' => [$this, 'check_logged_in'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                ],
            ],
        ]);

        // Get current user's reviews.
        register_rest_route(self::API_NAMESPACE, '/user/reviews', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_user_reviews'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        // Delete a review (own review only).
        register_rest_route(self::API_NAMESPACE, '/reviews/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'delete_review'],
            'permission_callback' => [$this, 'check_logged_in'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                ],
            ],
        ]);
    }

    /**
     * Check if user is logged in.
     *
     * @return bool|WP_Error
     */
    public function check_logged_in() {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                __('You must be logged in to perform this action.', 'erh-core'),
                ['status' => 401]
            );
        }
        return true;
    }

    /**
     * Submit a new review.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function submit_review(WP_REST_Request $request) {
        $user_id = get_current_user_id();

        // Rate limiting: 5 reviews per hour.
        if (!$this->rate_limiter->check("review_submit_{$user_id}", 5, 3600)) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Too many review submissions. Please try again later.', 'erh-core'),
                ['status' => 429]
            );
        }

        // Get and validate input.
        $product_id = (int) $request->get_param('product_id');
        $rating = (int) $request->get_param('rating');
        $review_text = sanitize_textarea_field($request->get_param('review') ?? '');

        // Validate product ID.
        if (!$product_id || get_post_type($product_id) !== 'products') {
            return new WP_Error(
                'invalid_product',
                __('Invalid product ID.', 'erh-core'),
                ['status' => 400]
            );
        }

        // Validate rating.
        if (!in_array($rating, [1, 2, 3, 4, 5], true)) {
            return new WP_Error(
                'invalid_rating',
                __('Rating must be between 1 and 5.', 'erh-core'),
                ['status' => 400]
            );
        }

        // Validate review text.
        if (empty($review_text)) {
            return new WP_Error(
                'empty_review',
                __('Please enter your review.', 'erh-core'),
                ['status' => 400]
            );
        }

        if (strlen($review_text) > self::MAX_REVIEW_LENGTH) {
            return new WP_Error(
                'review_too_long',
                sprintf(
                    __('Review must be less than %d characters.', 'erh-core'),
                    self::MAX_REVIEW_LENGTH
                ),
                ['status' => 400]
            );
        }

        // Check for duplicate review.
        if ($this->review_query->user_has_reviewed($user_id, $product_id)) {
            return new WP_Error(
                'duplicate_review',
                __('You have already submitted a review for this product.', 'erh-core'),
                ['status' => 400]
            );
        }

        // Handle image upload.
        $image_id = 0;
        $files = $request->get_file_params();

        if (!empty($files['review_image']['name'])) {
            $image_result = $this->handle_image_upload($files['review_image']);

            if (is_wp_error($image_result)) {
                return $image_result;
            }

            $image_id = $image_result;
        }

        // Create the review post.
        $review_content = wp_kses($review_text, ['br' => []]);
        $product_title = get_the_title($product_id);

        $review_data = [
            'post_title'   => sprintf('Review for %s', $product_title),
            'post_content' => '',
            'post_status'  => 'pending',
            'post_type'    => 'review',
            'post_author'  => $user_id,
        ];

        $review_id = wp_insert_post($review_data);

        if (is_wp_error($review_id)) {
            // Clean up uploaded image on failure.
            if ($image_id) {
                wp_delete_attachment($image_id, true);
            }

            return new WP_Error(
                'review_creation_failed',
                __('Failed to submit review. Please try again.', 'erh-core'),
                ['status' => 500]
            );
        }

        // Add meta data.
        update_post_meta($review_id, 'product', $product_id);
        update_post_meta($review_id, 'score', $rating);
        update_post_meta($review_id, 'text', $review_content);

        if ($image_id) {
            update_post_meta($review_id, 'review_image', $image_id);
            set_post_thumbnail($review_id, $image_id);
        }

        // Notify admin of new review.
        $this->notify_admin_new_review($review_id, $product_id, $user_id, $rating);

        // Record the rate limit hit.
        $this->rate_limiter->hit("review_submit_{$user_id}");

        return new WP_REST_Response([
            'success'   => true,
            'message'   => __('Your review has been submitted and is awaiting moderation.', 'erh-core'),
            'review_id' => $review_id,
        ], 201);
    }

    /**
     * Get reviews for a product.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_product_reviews(WP_REST_Request $request): WP_REST_Response {
        $product_id = (int) $request->get_param('id');
        $page = max(1, (int) $request->get_param('page'));
        $per_page = min(50, max(1, (int) ($request->get_param('per_page') ?? 10)));

        $data = $this->review_query->get_product_reviews($product_id, [
            'posts_per_page' => $per_page,
            'paged'          => $page,
        ]);

        return new WP_REST_Response([
            'reviews'              => $data['reviews'],
            'ratings_distribution' => $data['ratings_distribution'],
            'page'                 => $page,
            'per_page'             => $per_page,
        ]);
    }

    /**
     * Check if user has reviewed a product.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_review_status(WP_REST_Request $request): WP_REST_Response {
        $product_id = (int) $request->get_param('id');
        $user_id = get_current_user_id();

        $has_reviewed = $this->review_query->user_has_reviewed($user_id, $product_id);

        return new WP_REST_Response([
            'has_reviewed' => $has_reviewed,
        ]);
    }

    /**
     * Get current user's reviews.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_user_reviews(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $data = $this->review_query->get_user_reviews($user_id);

        return new WP_REST_Response([
            'reviews' => $data['reviews'],
            'total'   => count($data['reviews']),
        ]);
    }

    /**
     * Delete a review (own review only).
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function delete_review(WP_REST_Request $request) {
        $review_id = (int) $request->get_param('id');
        $user_id = get_current_user_id();

        $review = get_post($review_id);

        if (!$review || $review->post_type !== 'review') {
            return new WP_Error(
                'review_not_found',
                __('Review not found.', 'erh-core'),
                ['status' => 404]
            );
        }

        // Check ownership (allow admins to delete any review).
        if ((int) $review->post_author !== $user_id && !current_user_can('delete_others_posts')) {
            return new WP_Error(
                'not_authorized',
                __('You can only delete your own reviews.', 'erh-core'),
                ['status' => 403]
            );
        }

        // Delete associated image.
        $image_id = get_post_meta($review_id, 'review_image', true);
        if ($image_id) {
            wp_delete_attachment((int) $image_id, true);
        }

        // Delete the review.
        $deleted = wp_delete_post($review_id, true);

        if (!$deleted) {
            return new WP_Error(
                'delete_failed',
                __('Failed to delete review.', 'erh-core'),
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Review deleted successfully.', 'erh-core'),
        ]);
    }

    /**
     * Handle image upload for a review.
     *
     * @param array $file The uploaded file data.
     * @return int|WP_Error The attachment ID or error.
     */
    private function handle_image_upload(array $file) {
        // Validate file type.
        if (!in_array($file['type'], self::ALLOWED_IMAGE_TYPES, true)) {
            return new WP_Error(
                'invalid_file_type',
                __('Invalid file type. Please upload a JPEG, PNG, or WebP image.', 'erh-core'),
                ['status' => 400]
            );
        }

        // Validate file size.
        if ($file['size'] > self::MAX_IMAGE_SIZE) {
            return new WP_Error(
                'file_too_large',
                __('Image file size must be less than 5MB.', 'erh-core'),
                ['status' => 400]
            );
        }

        // Load required files for media handling.
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Upload the file.
        $attachment_id = media_handle_sideload([
            'name'     => $file['name'],
            'type'     => $file['type'],
            'tmp_name' => $file['tmp_name'],
            'error'    => $file['error'],
            'size'     => $file['size'],
        ], 0);

        if (is_wp_error($attachment_id)) {
            return new WP_Error(
                'upload_failed',
                __('Failed to upload image. Please try again.', 'erh-core'),
                ['status' => 500]
            );
        }

        return $attachment_id;
    }

    /**
     * Notify admin of a new review submission.
     *
     * @param int $review_id The review post ID.
     * @param int $product_id The product ID.
     * @param int $user_id The user ID who submitted the review.
     * @param int $rating The review rating.
     * @return void
     */
    private function notify_admin_new_review(int $review_id, int $product_id, int $user_id, int $rating): void {
        $admin_email = get_option('admin_email');
        $user = get_userdata($user_id);
        $product_title = get_the_title($product_id);
        $edit_link = admin_url("post.php?post={$review_id}&action=edit");

        $subject = sprintf(
            __('[%s] New Review Pending: %s', 'erh-core'),
            get_bloginfo('name'),
            $product_title
        );

        $message = sprintf(
            __(
                "A new review has been submitted and is awaiting moderation.\n\n" .
                "Product: %s\n" .
                "Rating: %d/5\n" .
                "Submitted by: %s (%s)\n\n" .
                "Review the submission: %s",
                'erh-core'
            ),
            $product_title,
            $rating,
            $user->display_name,
            $user->user_email,
            $edit_link
        );

        wp_mail($admin_email, $subject, $message);
    }
}
