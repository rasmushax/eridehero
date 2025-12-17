<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registry for managing scrapers
 */
class HFT_Scraper_Registry {
    private static ?HFT_Scraper_Registry $instance = null;
    private HFT_Scraper_Repository $repository;
    private array $cache = [];
    
    private function __construct() {
        $this->repository = new HFT_Scraper_Repository();
    }
    
    public static function get_instance(): HFT_Scraper_Registry {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get scraper for a domain
     */
    public function get_scraper_for_domain(string $domain): ?HFT_Scraper {
        // Normalize domain
        $domain = $this->normalize_domain($domain);
        
        // Check cache
        if (isset($this->cache[$domain])) {
            return $this->cache[$domain];
        }
        
        // Load from database
        $scraper = $this->repository->find_by_domain($domain);
        
        if ($scraper && $scraper->is_active) {
            $this->cache[$domain] = $scraper;
            return $scraper;
        }
        
        return null;
    }
    
    /**
     * Get scraper by ID
     */
    public function get_scraper(int $id): ?HFT_Scraper {
        return $this->repository->find_by_id($id);
    }
    
    /**
     * Clear cache
     */
    public function clear_cache(): void {
        $this->cache = [];
    }
    
    /**
     * Normalize domain for consistency
     */
    private function normalize_domain(string $domain): string {
        // Handle empty or invalid domain
        if (!is_string($domain) || $domain === '') {
            return '';
        }
        
        // Remove protocol
        $result = preg_replace('~^https?://~', '', $domain);
        $domain = is_string($result) && $result !== '' ? $result : $domain;
        
        // Remove www
        $result = preg_replace('~^www\.~', '', $domain);
        $domain = is_string($result) && $result !== '' ? $result : $domain;
        
        // Remove trailing slash
        $domain = rtrim($domain, '/');
        
        // Convert to lowercase
        $domain = strtolower($domain);
        
        return $domain;
    }
    
    /**
     * Extract domain from URL
     */
    public function extract_domain_from_url(string $url): string {
        if (!is_string($url) || $url === '') {
            return '';
        }
        
        $parsed = parse_url($url);
        if (!is_array($parsed)) {
            return '';
        }
        
        $host = isset($parsed['host']) && is_string($parsed['host']) ? $parsed['host'] : '';
        return $this->normalize_domain($host);
    }
}