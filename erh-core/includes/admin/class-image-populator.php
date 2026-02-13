<?php
/**
 * Image Populator - AI-powered product image finder on edit screens.
 *
 * Adds a floating button on product edit pages that opens a modal
 * for searching Google Images, selecting one, and setting it as
 * the featured image (downloaded, transcoded to JPG, properly named).
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\CategoryConfig;

/**
 * Admin tool for finding and setting product featured images.
 */
class ImagePopulator {

    /**
     * AJAX nonce action.
     */
    private const NONCE_ACTION = 'erh_image_populator';

    /**
     * Google Image client.
     *
     * @var SerpApiImageClient
     */
    private SerpApiImageClient $google;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->google = new SerpApiImageClient();
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers.
        add_action('wp_ajax_erh_ip_search_images', [$this, 'ajax_search_images']);
        add_action('wp_ajax_erh_ip_select_image', [$this, 'ajax_select_image']);
    }

    /**
     * Enqueue assets on product edit screens.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_assets(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'products') {
            return;
        }

        $post_id = (int) ($_GET['post'] ?? 0);
        if (!$post_id && $hook === 'post.php') {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));

        wp_enqueue_style(
            'erh-image-populator',
            $plugin_url . 'assets/css/image-populator.css',
            [],
            ERH_VERSION
        );

        wp_enqueue_script(
            'erh-image-populator',
            $plugin_url . 'assets/js/image-populator.js',
            [],
            ERH_VERSION,
            true
        );

        // Build default search query.
        $product_name = $post_id ? get_the_title($post_id) : '';
        $product_type = '';
        if ($post_id) {
            $type_terms = get_the_terms($post_id, 'product_type');
            $product_type = ($type_terms && !is_wp_error($type_terms)) ? $type_terms[0]->name : '';
        }

        $default_query = trim($product_name . ' ' . strtolower($product_type));

        wp_localize_script('erh-image-populator', 'erhImagePopulator', [
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce(self::NONCE_ACTION),
            'productId'    => $post_id,
            'productName'  => $product_name,
            'defaultQuery' => $default_query,
            'isConfigured' => $this->google->is_configured(),
            'hasThumbnail' => has_post_thumbnail($post_id),
        ]);
    }

    /**
     * AJAX: Search for images via Google.
     *
     * @return void
     */
    public function ajax_search_images(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        if (empty($query)) {
            wp_send_json_error(['message' => __('Search query is required.', 'erh-core')]);
        }

        $result = $this->google->search_images($query, 10);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['error']]);
        }

        wp_send_json_success(['images' => $result['images']]);
    }

    /**
     * AJAX: Download selected image, process it, and set as featured image.
     *
     * @return void
     */
    public function ajax_select_image(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';

        if (!$product_id || empty($image_url)) {
            wp_send_json_error(['message' => __('Missing required parameters.', 'erh-core')]);
        }

        $post = get_post($product_id);
        if (!$post || $post->post_type !== 'products') {
            wp_send_json_error(['message' => __('Invalid product.', 'erh-core')]);
        }

        // Download the image.
        $download = $this->download_image($image_url);
        if (!$download['success']) {
            wp_send_json_error(['message' => $download['error']]);
        }

        // Transcode to web-safe format (PNG kept for transparency, others → JPG).
        $processed = $this->transcode_image($download['tmp_path'], $download['mime']);
        if (!$processed) {
            @unlink($download['tmp_path']);
            wp_send_json_error(['message' => __('Failed to process image.', 'erh-core')]);
        }

        $processed_path = $processed['path'];
        $output_mime    = $processed['mime'];
        $ext            = $output_mime === 'image/png' ? '.png' : '.jpg';

        // Generate filename from product title.
        $filename = sanitize_title($post->post_title) . $ext;

        // Upload to media library.
        $attachment_id = $this->upload_to_media_library($processed_path, $filename, $product_id, $output_mime);

        // Clean up temp file.
        if ($processed_path !== $download['tmp_path']) {
            @unlink($download['tmp_path']);
        }
        @unlink($processed_path);

        if (!$attachment_id) {
            wp_send_json_error(['message' => __('Failed to upload image to media library.', 'erh-core')]);
        }

        // Set alt text to product name.
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $post->post_title);

        // Set as featured image.
        set_post_thumbnail($product_id, $attachment_id);

        wp_send_json_success([
            'attachment_id'  => $attachment_id,
            'thumbnail_url'  => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
        ]);
    }

    /**
     * Download an image from a URL to a temporary file.
     *
     * @param string $url Image URL.
     * @return array{success: bool, tmp_path?: string, mime?: string, error?: string}
     */
    private function download_image(string $url): array {
        $response = wp_remote_get($url, [
            'timeout'   => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return [
                'success' => false,
                'error'   => sprintf(__('Failed to download image (HTTP %d).', 'erh-core'), $code),
            ];
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return [
                'success' => false,
                'error'   => __('Downloaded image is empty.', 'erh-core'),
            ];
        }

        // Detect MIME type.
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $mime = $this->parse_mime($content_type, $url);

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'];
        if (!in_array($mime, $allowed, true)) {
            return [
                'success' => false,
                'error'   => sprintf(__('Unsupported image type: %s', 'erh-core'), $mime),
            ];
        }

        // Write to temp file.
        $tmp_path = wp_tempnam('erh_img_');
        if (!$tmp_path || !file_put_contents($tmp_path, $body)) {
            return [
                'success' => false,
                'error'   => __('Failed to save temporary file.', 'erh-core'),
            ];
        }

        return [
            'success'  => true,
            'tmp_path' => $tmp_path,
            'mime'     => $mime,
        ];
    }

    /**
     * Parse MIME type from content-type header, with URL fallback.
     *
     * @param string $content_type Content-Type header value.
     * @param string $url          Image URL for extension-based fallback.
     * @return string MIME type.
     */
    private function parse_mime(string $content_type, string $url): string {
        // Parse from content-type header.
        $mime = strtolower(explode(';', $content_type)[0]);
        $mime = trim($mime);

        $valid = ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'];
        if (in_array($mime, $valid, true)) {
            return $mime;
        }

        // Fallback to URL extension.
        $ext = strtolower(pathinfo(wp_parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        $ext_map = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'gif'  => 'image/gif',
        ];

        return $ext_map[$ext] ?? 'image/jpeg';
    }

    /**
     * Transcode image to a web-safe format.
     *
     * PNGs are kept as PNG to preserve transparency.
     * All other formats (WebP, AVIF, GIF) are converted to JPG with a white background.
     * JPEGs pass through as-is.
     *
     * @param string $source_path Path to source image.
     * @param string $mime        Source MIME type.
     * @return array{path: string, mime: string}|null Processed file info, or null on failure.
     */
    private function transcode_image(string $source_path, string $mime): ?array {
        // Already JPEG — return as-is.
        if ($mime === 'image/jpeg') {
            return ['path' => $source_path, 'mime' => 'image/jpeg'];
        }

        // PNG — keep as PNG to preserve transparency.
        if ($mime === 'image/png') {
            return ['path' => $source_path, 'mime' => 'image/png'];
        }

        // WebP, AVIF, GIF → JPG with white background to avoid black/broken transparency.
        $gd_image = match ($mime) {
            'image/webp' => @imagecreatefromwebp($source_path),
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($source_path) : false,
            'image/gif'  => @imagecreatefromgif($source_path),
            default      => false,
        };

        if (!$gd_image) {
            // GD failed — fall back to WP Image Editor (no white bg, but better than nothing).
            $editor = wp_get_image_editor($source_path);
            if (is_wp_error($editor)) {
                return null;
            }
            $jpg_path = $source_path . '.jpg';
            $saved = $editor->save($jpg_path, 'image/jpeg');
            return is_wp_error($saved) ? null : ['path' => $saved['path'], 'mime' => 'image/jpeg'];
        }

        // Flatten onto white background.
        $width  = imagesx($gd_image);
        $height = imagesy($gd_image);
        $flat   = imagecreatetruecolor($width, $height);
        $white  = imagecolorallocate($flat, 255, 255, 255);
        imagefill($flat, 0, 0, $white);
        imagecopy($flat, $gd_image, 0, 0, 0, 0, $width, $height);
        imagedestroy($gd_image);

        $jpg_path = $source_path . '.jpg';
        $success = imagejpeg($flat, $jpg_path, 90);
        imagedestroy($flat);

        return $success ? ['path' => $jpg_path, 'mime' => 'image/jpeg'] : null;
    }

    /**
     * Upload a processed image to the WordPress media library.
     *
     * @param string $file_path  Path to the image file.
     * @param string $filename   Desired filename (e.g. "product-name.jpg").
     * @param int    $product_id Product post ID to attach to.
     * @param string $mime       MIME type of the image.
     * @return int|null Attachment ID or null on failure.
     */
    private function upload_to_media_library(string $file_path, string $filename, int $product_id, string $mime = 'image/jpeg'): ?int {
        // Read file contents.
        $file_data = file_get_contents($file_path);
        if ($file_data === false) {
            return null;
        }

        // Upload using wp_upload_bits.
        $upload = wp_upload_bits($filename, null, $file_data);
        if (!empty($upload['error'])) {
            return null;
        }

        // Prepare attachment data.
        $attachment = [
            'post_mime_type' => $mime,
            'post_title'     => pathinfo($filename, PATHINFO_FILENAME),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $product_id);
        if (is_wp_error($attachment_id) || !$attachment_id) {
            return null;
        }

        // Generate metadata (thumbnails, sizes, etc.).
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);

        return $attachment_id;
    }
}
