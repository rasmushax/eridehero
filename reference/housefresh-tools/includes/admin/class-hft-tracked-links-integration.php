<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integration between tracked links and scrapers
 */
class HFT_Tracked_Links_Integration {
    
    /**
     * Update tracked link to use new scraper
     */
    public static function update_link_scraper(int $link_id, int $scraper_id): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hft_tracked_links';
        
        $result = $wpdb->update(
            $table,
            ['scraper_id' => $scraper_id],
            ['id' => $link_id],
            ['%d'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Migrate links from parser_identifier to scraper_id
     */
    public static function migrate_link_to_scraper(int $link_id): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hft_tracked_links';
        
        // Get link data
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $link_id
        ), ARRAY_A);
        
        if (!$link || !empty($link['scraper_id'])) {
            return false; // Already migrated or not found
        }
        
        // Skip Amazon links
        if ($link['parser_identifier'] === 'amazon') {
            return true;
        }
        
        // Try to find scraper by domain
        $registry = HFT_Scraper_Registry::get_instance();
        $scraper = $registry->get_scraper_for_domain($link['parser_identifier']);
        
        if ($scraper) {
            return self::update_link_scraper($link_id, $scraper->id);
        }
        
        return false;
    }
    
    /**
     * Get parser identifier for display
     */
    public static function get_parser_display_name(array $link_data): string {
        if (!empty($link_data['scraper_id'])) {
            $scraper = HFT_Scraper_Registry::get_instance()->get_scraper((int)$link_data['scraper_id']);
            if ($scraper) {
                return $scraper->name;
            }
        }
        
        // Fall back to parser_identifier
        if ($link_data['parser_identifier'] === 'amazon') {
            return __('Amazon API', 'housefresh-tools');
        }
        
        return $link_data['parser_identifier'] ?? __('Unknown', 'housefresh-tools');
    }
    
    /**
     * Get available parsers for dropdown
     */
    public static function get_available_parsers(): array {
        $parsers = [];
        
        // Always include Amazon
        $parsers['amazon'] = __('Amazon API', 'housefresh-tools');
        
        // Add all active scrapers
        $repository = new HFT_Scraper_Repository();
        $scrapers = $repository->find_all_active();
        
        foreach ($scrapers as $scraper) {
            $parsers['scraper_' . $scraper->id] = $scraper->name;
        }
        
        return $parsers;
    }
}