<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles migration of existing parsers to new scraper system
 */
class HFT_Scraper_Migration {
    
    /**
     * Run migration to populate initial scrapers from existing tracked links
     */
    public static function migrate(): void {
        global $wpdb;

        $tracked_links_table = $wpdb->prefix . 'hft_tracked_links';
        $scrapers_table = $wpdb->prefix . 'hft_scrapers';

        // Get unique parser identifiers (excluding Amazon)
        $identifiers = $wpdb->get_col("
            SELECT DISTINCT parser_identifier
            FROM {$tracked_links_table}
            WHERE parser_identifier != 'amazon'
            AND parser_identifier IS NOT NULL
            AND parser_identifier != ''
        ");

        $repository = new HFT_Scraper_Repository();

        foreach ($identifiers as $identifier) {
            // Skip if scraper already exists for this exact domain
            $existing = $repository->find_by_domain($identifier);
            if ($existing) {
                continue;
            }

            // Skip if a scraper already exists for a subdomain of this base domain
            // e.g., if identifier is "niu.com" and a scraper exists for "shop.niu.com"
            // This prevents duplicate scrapers being created on plugin reactivation
            $subdomain_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$scrapers_table} WHERE domain LIKE %s LIMIT 1",
                '%.' . $wpdb->esc_like($identifier)
            ));

            if ($subdomain_exists) {
                continue;
            }

            // Create new scraper
            $scraper = new HFT_Scraper([
                'domain' => $identifier,
                'name' => ucfirst(str_replace('.', ' ', $identifier)) . ' Scraper',
                'is_active' => true,
                'use_base_parser' => true
            ]);

            $scraper_id = $repository->save($scraper);

            // Update tracked links to reference the new scraper
            if ($scraper_id > 0) {
                $wpdb->update(
                    $tracked_links_table,
                    ['scraper_id' => $scraper_id],
                    ['parser_identifier' => $identifier],
                    ['%d'],
                    ['%s']
                );
            }
        }
    }
}