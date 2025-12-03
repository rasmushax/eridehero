<?php
/**
 * Review query class for retrieving and calculating review data.
 *
 * @package ERH\Reviews
 */

declare(strict_types=1);

namespace ERH\Reviews;

use WP_Query;

/**
 * Handles querying reviews and calculating ratings distribution.
 */
class ReviewQuery {

    /**
     * Get reviews for a product with ratings distribution.
     *
     * @param int $product_id The product ID.
     * @param array $args Optional query arguments.
     * @return array{reviews: array, ratings_distribution: array}
     */
    public function get_product_reviews(int $product_id, array $args = []): array {
        $defaults = [
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $query_args = [
            'post_type'      => 'review',
            'post_status'    => 'publish',
            'posts_per_page' => $args['posts_per_page'],
            'orderby'        => $args['orderby'],
            'order'          => $args['order'],
            'meta_query'     => [
                [
                    'key'     => 'product',
                    'value'   => $product_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ];

        return $this->execute_query($query_args);
    }

    /**
     * Get reviews submitted by a user.
     *
     * @param int $user_id The user ID.
     * @param array $args Optional query arguments.
     * @return array{reviews: array, ratings_distribution: array}
     */
    public function get_user_reviews(int $user_id, array $args = []): array {
        $defaults = [
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => ['publish', 'pending'],
        ];

        $args = wp_parse_args($args, $defaults);

        $query_args = [
            'post_type'      => 'review',
            'post_status'    => $args['post_status'],
            'posts_per_page' => $args['posts_per_page'],
            'orderby'        => $args['orderby'],
            'order'          => $args['order'],
            'author'         => $user_id,
        ];

        return $this->execute_query($query_args, true);
    }

    /**
     * Check if a user has already reviewed a product.
     *
     * @param int $user_id The user ID.
     * @param int $product_id The product ID.
     * @return bool True if the user has already reviewed the product.
     */
    public function user_has_reviewed(int $user_id, int $product_id): bool {
        $existing = get_posts([
            'post_type'      => 'review',
            'author'         => $user_id,
            'post_status'    => ['publish', 'pending'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'product',
                    'value'   => $product_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]);

        return !empty($existing);
    }

    /**
     * Get a single review by ID.
     *
     * @param int $review_id The review post ID.
     * @return array|null The review data or null if not found.
     */
    public function get_review(int $review_id): ?array {
        $post = get_post($review_id);

        if (!$post || $post->post_type !== 'review') {
            return null;
        }

        return $this->format_review($post);
    }

    /**
     * Get the average rating for a product.
     *
     * @param int $product_id The product ID.
     * @return float|null The average rating or null if no reviews.
     */
    public function get_average_rating(int $product_id): ?float {
        $data = $this->get_product_reviews($product_id);

        if ($data['ratings_distribution']['ratings_count'] === 0) {
            return null;
        }

        return (float) $data['ratings_distribution']['average_rating'];
    }

    /**
     * Get the total review count for a product.
     *
     * @param int $product_id The product ID.
     * @return int The review count.
     */
    public function get_review_count(int $product_id): int {
        return (int) get_posts([
            'post_type'      => 'review',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'product',
                    'value'   => $product_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]) ? count(get_posts([
            'post_type'      => 'review',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'product',
                    'value'   => $product_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ])) : 0;
    }

    /**
     * Execute the review query and format results.
     *
     * @param array $query_args WP_Query arguments.
     * @param bool $include_product Whether to include product info in each review.
     * @return array{reviews: array, ratings_distribution: array}
     */
    private function execute_query(array $query_args, bool $include_product = false): array {
        $reviews = [];
        $ratings_distribution = $this->get_empty_distribution();
        $total_score = 0;

        $query = new WP_Query($query_args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post = get_post();

                $review = $this->format_review($post, $include_product);
                $reviews[] = $review;

                // Update ratings distribution (only for published reviews).
                if ($post->post_status === 'publish') {
                    $score = (int) $review['score'];
                    $ratings_distribution['ratings_count']++;
                    $total_score += $score;

                    $star_key = 'count_' . $score . '_star';
                    if (isset($ratings_distribution[$star_key])) {
                        $ratings_distribution[$star_key]++;
                    }

                    // Store in ratings array for compatibility.
                    if (!isset($ratings_distribution['ratings'])) {
                        $ratings_distribution['ratings'] = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
                    }
                    $ratings_distribution['ratings'][$score]++;
                }
            }
            wp_reset_postdata();

            // Calculate average rating.
            if ($ratings_distribution['ratings_count'] > 0) {
                $average = round($total_score / $ratings_distribution['ratings_count'], 1);
                $ratings_distribution['average_rating'] = number_format($average, 1);
            }
        }

        return [
            'reviews'              => $reviews,
            'ratings_distribution' => $ratings_distribution,
        ];
    }

    /**
     * Format a review post into a standard array structure.
     *
     * @param \WP_Post $post The review post.
     * @param bool $include_product Whether to include product info.
     * @return array The formatted review data.
     */
    private function format_review(\WP_Post $post, bool $include_product = false): array {
        $author_id = (int) $post->post_author;
        $score = (int) get_field('score', $post->ID);
        $text = get_field('text', $post->ID);
        $product_id = (int) get_post_meta($post->ID, 'product', true);

        // Get review image URLs.
        $image_id = get_post_meta($post->ID, 'review_image', true);
        $thumbnail_url = '';
        $large_url = '';

        if ($image_id) {
            $thumbnail_url = wp_get_attachment_image_url((int) $image_id, 'thumbnail') ?: '';
            $large_url = wp_get_attachment_image_url((int) $image_id, 'large') ?: '';
        }

        $review = [
            'id'            => $post->ID,
            'text'          => $text,
            'score'         => $score,
            'author'        => get_the_author_meta('display_name', $author_id),
            'author_id'     => $author_id,
            'date'          => get_the_date('Y-m-d H:i:s', $post),
            'date_relative' => $this->time_elapsed_string(get_the_date('Y-m-d H:i:s', $post)),
            'status'        => $post->post_status,
            'thumbnail_url' => $thumbnail_url,
            'large_url'     => $large_url,
            'product_id'    => $product_id,
        ];

        // Include product info if requested (for user reviews list).
        if ($include_product && $product_id) {
            $product = get_post($product_id);
            if ($product) {
                $review['product'] = [
                    'id'        => $product_id,
                    'title'     => $product->post_title,
                    'permalink' => get_permalink($product_id),
                ];
            }
        }

        return $review;
    }

    /**
     * Get an empty ratings distribution array.
     *
     * @return array The empty distribution.
     */
    private function get_empty_distribution(): array {
        return [
            'ratings_count'  => 0,
            'average_rating' => 0,
            'count_1_star'   => 0,
            'count_2_star'   => 0,
            'count_3_star'   => 0,
            'count_4_star'   => 0,
            'count_5_star'   => 0,
            'ratings'        => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
        ];
    }

    /**
     * Convert a datetime to a human-readable relative time string.
     *
     * @param string $datetime The datetime string.
     * @return string The relative time (e.g., "2 hours ago").
     */
    private function time_elapsed_string(string $datetime): string {
        $now = new \DateTime();
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $weeks = (int) floor($diff->d / 7);
        $days = $diff->d - ($weeks * 7);

        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
        }
        if ($weeks > 0) {
            $parts[] = $weeks . ' week' . ($weeks > 1 ? 's' : '');
        }
        if ($days > 0 && empty($parts)) {
            $parts[] = $days . ' day' . ($days > 1 ? 's' : '');
        }
        if ($diff->h > 0 && empty($parts)) {
            $parts[] = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        }
        if ($diff->i > 0 && empty($parts)) {
            $parts[] = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }

        if (empty($parts)) {
            return 'just now';
        }

        return $parts[0] . ' ago';
    }
}
