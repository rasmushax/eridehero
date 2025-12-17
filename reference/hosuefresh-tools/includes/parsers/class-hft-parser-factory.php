<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Factory for creating parser instances
 */
class HFT_Parser_Factory {
    
    /**
     * Create parser for a given URL or identifier
     * 
     * @param string $url_or_identifier URL or identifier (e.g., ASIN)
     * @param string|null $parser_identifier Optional parser identifier override
     * @return HFT_ParserInterface|null Parser instance or null if not found
     */
    public static function create_parser(string $url_or_identifier, ?string $parser_identifier = null): ?HFT_ParserInterface {
        // If Amazon ASIN or explicit amazon identifier
        if ($parser_identifier === 'amazon' || self::is_amazon_asin($url_or_identifier)) {
            return self::create_amazon_parser();
        }
        
        // Extract domain from URL
        $domain = null;
        if (filter_var($url_or_identifier, FILTER_VALIDATE_URL)) {
            $registry = HFT_Scraper_Registry::get_instance();
            $domain = $registry->extract_domain_from_url($url_or_identifier);
        } elseif ($parser_identifier) {
            $domain = $parser_identifier;
        }
        
        if (!$domain) {
            return null;
        }
        
        // Check for database scraper
        $scraper = HFT_Scraper_Registry::get_instance()->get_scraper_for_domain($domain);
        if ($scraper) {
            return new HFT_Dynamic_Parser($scraper);
        }
        
        return null;
    }
    
    /**
     * Create parser by scraper ID
     */
    public static function create_parser_by_scraper_id(int $scraper_id): ?HFT_ParserInterface {
        $scraper = HFT_Scraper_Registry::get_instance()->get_scraper($scraper_id);
        
        if (!$scraper || !$scraper->is_active) {
            return null;
        }
        
        return new HFT_Dynamic_Parser($scraper);
    }
    
    /**
     * Check if identifier is Amazon ASIN
     */
    private static function is_amazon_asin(string $identifier): bool {
        // ASIN is 10 characters, alphanumeric
        return preg_match('/^[A-Z0-9]{10}$/', $identifier) === 1;
    }
    
    /**
     * Create Amazon parser
     */
    private static function create_amazon_parser(): ?HFT_ParserInterface {
        $parser_file = HFT_PLUGIN_PATH . 'includes/parsers/class-hft-amazon-api-parser.php';
        
        if (!file_exists($parser_file)) {
            return null;
        }
        
        require_once $parser_file;
        
        if (!class_exists('HFT_Amazon_Api_Parser')) {
            return null;
        }
        
        return new HFT_Amazon_Api_Parser();
    }
}