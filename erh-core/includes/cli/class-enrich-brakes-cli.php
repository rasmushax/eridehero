<?php
/**
 * WP-CLI command to enrich electric scooter brake data via Perplexity AI.
 *
 * One-time migration script. Safe to re-run — only populates empty fields.
 *
 * @package ERH\Cli
 */

declare(strict_types=1);

namespace ERH\Cli;

use ERH\Admin\PerplexityClient;

/**
 * Enriches electric scooter brake specs using Perplexity sonar-pro.
 */
class EnrichBrakesCli {

    /**
     * Valid values for front/rear brake ACF select fields.
     */
    private const VALID_BRAKE_TYPES = [
        'None',
        'Drum',
        'Disc (Mechanical)',
        'Disc (Hydraulic)',
    ];

    /**
     * Delay between API requests in seconds.
     */
    private const REQUEST_DELAY = 3;

    /**
     * Register WP-CLI commands.
     */
    public static function register(): void {
        if (!defined('WP_CLI') || !\WP_CLI) {
            return;
        }

        \WP_CLI::add_command('erh enrich-brakes', [new self(), 'run']);
    }

    /**
     * Enrich brake data for electric scooters using Perplexity AI.
     *
     * Queries all electric scooter products with missing brake fields
     * and uses Perplexity sonar-pro to look up front and rear brake type.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview changes without saving to database.
     *
     * [--limit=<number>]
     * : Maximum number of products to process.
     *
     * [--product=<slug>]
     * : Process a single product by slug.
     *
     * ## EXAMPLES
     *
     *     wp erh enrich-brakes --dry-run
     *     wp erh enrich-brakes --limit=5
     *     wp erh enrich-brakes --product=ninebot-max-g2
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function run(array $args, array $assoc_args): void {
        $dry_run = isset($assoc_args['dry-run']);
        $limit   = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 0;
        $slug    = $assoc_args['product'] ?? '';

        $client = new PerplexityClient();

        if (!$client->is_configured()) {
            \WP_CLI::error('Perplexity API key not configured. Set it at Settings > ERH Settings > APIs.');
        }

        if ($dry_run) {
            \WP_CLI::log('DRY RUN — no changes will be saved.');
        }

        // Get products.
        $products = $this->get_products($slug);

        if (empty($products)) {
            \WP_CLI::warning('No electric scooter products found.');
            return;
        }

        // Filter to those with missing brake data.
        $to_process = $this->filter_missing_brakes($products);

        \WP_CLI::log(sprintf(
            'Found %d products with missing brake data (of %d total e-scooters).',
            count($to_process),
            count($products)
        ));

        if (empty($to_process)) {
            \WP_CLI::success('All products already have brake data.');
            return;
        }

        if ($limit > 0) {
            $to_process = array_slice($to_process, 0, $limit);
            \WP_CLI::log(sprintf('Processing limited to %d products.', $limit));
        }

        // Process each product.
        $results = $this->process_products($to_process, $client, $dry_run);

        // Log results to file.
        $this->write_log($results);

        // Summary.
        $saved   = count(array_filter($results, fn($r) => $r['status'] === 'saved'));
        $skipped = count(array_filter($results, fn($r) => $r['status'] === 'skipped'));
        $errors  = count(array_filter($results, fn($r) => $r['status'] === 'error'));

        \WP_CLI::log('');
        \WP_CLI::log(sprintf(
            'Summary: %d saved, %d errors, %d skipped.',
            $saved,
            $errors,
            $skipped
        ));

        if ($dry_run) {
            \WP_CLI::log('(Dry run — nothing was actually saved.)');
        }

        \WP_CLI::success('Done.');
    }

    /**
     * Get electric scooter product IDs.
     *
     * @param string $slug Optional single product slug.
     * @return array Array of post IDs.
     */
    private function get_products(string $slug = ''): array {
        $query_args = [
            'post_type'      => 'products',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => 'electric-scooter',
                ],
            ],
        ];

        if (!empty($slug)) {
            $query_args['name'] = $slug;
        }

        return get_posts($query_args);
    }

    /**
     * Filter products to those missing any brake field.
     *
     * @param array $product_ids Array of post IDs.
     * @return array Array of ['id' => int, 'name' => string, 'missing' => array].
     */
    private function filter_missing_brakes(array $product_ids): array {
        $to_process = [];

        foreach ($product_ids as $id) {
            $escooter_data = get_field('e-scooters', $id);
            $brakes = $escooter_data['brakes'] ?? [];

            $missing = [];
            if (empty($brakes['front'])) {
                $missing[] = 'front';
            }
            if (empty($brakes['rear'])) {
                $missing[] = 'rear';
            }

            if (!empty($missing)) {
                $to_process[] = [
                    'id'      => $id,
                    'name'    => get_the_title($id),
                    'missing' => $missing,
                ];
            }
        }

        return $to_process;
    }

    /**
     * Process products: call Perplexity, validate, save.
     *
     * @param array            $products Products to process.
     * @param PerplexityClient $client   API client.
     * @param bool             $dry_run  Whether this is a dry run.
     * @return array Results per product.
     */
    private function process_products(array $products, PerplexityClient $client, bool $dry_run): array {
        $results = [];
        $total   = count($products);

        foreach ($products as $index => $product) {
            $id   = $product['id'];
            $name = $product['name'];

            \WP_CLI::log(sprintf(
                '[%d/%d] %s (missing: %s)...',
                $index + 1,
                $total,
                $name,
                implode(', ', $product['missing'])
            ));

            // Call Perplexity.
            $response = $this->call_with_retry($client, $name);

            if (!$response['success']) {
                \WP_CLI::warning(sprintf('  API error: %s', $response['error']));
                $results[] = [
                    'id'     => $id,
                    'name'   => $name,
                    'status' => 'error',
                    'error'  => $response['error'],
                ];
                $this->delay_if_needed($index, $total);
                continue;
            }

            // Parse JSON from response.
            $parsed = $this->parse_response($response['content']);

            if ($parsed === null) {
                \WP_CLI::warning(sprintf('  Invalid JSON response: %s', substr($response['content'], 0, 200)));
                $results[] = [
                    'id'     => $id,
                    'name'   => $name,
                    'status' => 'error',
                    'error'  => 'Invalid JSON',
                    'raw'    => $response['content'],
                ];
                $this->delay_if_needed($index, $total);
                continue;
            }

            // Validate and collect fields to save.
            $saved_fields   = [];
            $skipped_fields = [];

            foreach (['front', 'rear'] as $field) {
                if (!in_array($field, $product['missing'], true)) {
                    continue;
                }

                $value = $parsed[$field] ?? null;

                if ($value !== null && in_array($value, self::VALID_BRAKE_TYPES, true)) {
                    $saved_fields[$field] = $value;
                } else {
                    $skipped_fields[$field] = $value;
                    \WP_CLI::warning(sprintf('  Invalid %s brake value: %s', $field, var_export($value, true)));
                }
            }

            // Save all valid fields in a single update.
            if (!empty($saved_fields) && !$dry_run) {
                $this->save_brake_fields($id, $saved_fields);
            }

            $status = !empty($saved_fields) ? 'saved' : 'skipped';

            \WP_CLI::log(sprintf(
                '  %s | front: %s | rear: %s',
                strtoupper($status),
                $saved_fields['front'] ?? ($skipped_fields['front'] ?? '-'),
                $saved_fields['rear'] ?? ($skipped_fields['rear'] ?? '-')
            ));

            $results[] = [
                'id'      => $id,
                'name'    => $name,
                'status'  => $status,
                'saved'   => $saved_fields,
                'skipped' => $skipped_fields,
            ];

            $this->delay_if_needed($index, $total);
        }

        return $results;
    }

    /**
     * Call Perplexity with a single retry on rate limit.
     *
     * @param PerplexityClient $client       API client.
     * @param string           $product_name Product name.
     * @return array API response.
     */
    private function call_with_retry(PerplexityClient $client, string $product_name): array {
        $response = $client->send_request(
            $this->get_system_prompt(),
            $this->get_user_prompt($product_name),
            500,
            0.1,
            30
        );

        if (!$response['success'] && str_contains($response['error'] ?? '', 'Rate limit')) {
            \WP_CLI::log('  Rate limited. Waiting 10s and retrying...');
            sleep(10);

            $response = $client->send_request(
                $this->get_system_prompt(),
                $this->get_user_prompt($product_name),
                500,
                0.1,
                30
            );
        }

        return $response;
    }

    /**
     * Save brake fields to ACF in a single update.
     *
     * Reads the full e-scooters group once, merges brake changes, saves once.
     *
     * @param int   $product_id   Product post ID.
     * @param array $brake_fields Fields to save (['front' => value, 'rear' => value, ...]).
     */
    private function save_brake_fields(int $product_id, array $brake_fields): void {
        $full = get_field('e-scooters', $product_id) ?: [];
        $full['brakes'] = $full['brakes'] ?? [];

        foreach ($brake_fields as $key => $value) {
            $full['brakes'][$key] = $value;
        }

        update_field('e-scooters', $full, $product_id);
    }

    /**
     * Delay between requests (skip for last product).
     *
     * @param int $index Current index.
     * @param int $total Total products.
     */
    private function delay_if_needed(int $index, int $total): void {
        if ($index < $total - 1) {
            sleep(self::REQUEST_DELAY);
        }
    }

    /**
     * System prompt for Perplexity.
     */
    private function get_system_prompt(): string {
        return 'You are a precise electric scooter specification database. '
            . 'Return ONLY a JSON object with no additional text, no markdown, no explanation. '
            . 'If you are unsure about a value, use your best judgment based on available sources.';
    }

    /**
     * User prompt for a specific product.
     *
     * @param string $product_name Product name.
     * @return string The prompt.
     */
    private function get_user_prompt(string $product_name): string {
        return sprintf(
            'What are the front and rear brake types on the %s electric scooter?

Return ONLY valid JSON: {"front": "...", "rear": "..."}

Each value MUST be one of these exact strings:
- "None" (no mechanical brake on that wheel)
- "Drum"
- "Disc (Mechanical)" (cable-actuated disc brake)
- "Disc (Hydraulic)" (hydraulic disc brake)

Example: {"front": "Disc (Mechanical)", "rear": "Drum"}',
            $product_name
        );
    }

    /**
     * Parse JSON from Perplexity response.
     *
     * Handles markdown fences, stray characters, and malformed starts.
     * Tries each { position until valid JSON is found.
     *
     * @param string $content Raw API response content.
     * @return array|null Parsed data or null on failure.
     */
    private function parse_response(string $content): ?array {
        $content = trim($content);

        // Strip markdown code fences.
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```\s*$/', '', $content);

        $end = strrpos($content, '}');
        if ($end === false) {
            return null;
        }

        // Try each { position left to right until we find valid JSON.
        $offset = 0;
        while (($start = strpos($content, '{', $offset)) !== false && $start < $end) {
            $json = substr($content, $start, $end - $start + 1);
            $data = json_decode($json, true);

            if (is_array($data) && (isset($data['front']) || isset($data['rear']))) {
                return $data;
            }

            $offset = $start + 1;
        }

        return null;
    }

    /**
     * Write results to a JSON log file.
     *
     * @param array $results Processing results.
     */
    private function write_log(array $results): void {
        $upload_dir = wp_upload_dir();
        $log_dir    = $upload_dir['basedir'] . '/erh-logs';

        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $filename = sprintf('enrich-brakes-%s.json', gmdate('Y-m-d-His'));
        $filepath = $log_dir . '/' . $filename;

        file_put_contents($filepath, wp_json_encode([
            'timestamp' => gmdate('c'),
            'count'     => count($results),
            'results'   => $results,
        ], JSON_PRETTY_PRINT));

        \WP_CLI::log(sprintf('Log saved to: %s', $filepath));
    }
}
