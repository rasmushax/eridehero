<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository for scraper database operations
 */
class HFT_Scraper_Repository {
    private string $scrapers_table;
    private string $rules_table;
    
    public function __construct() {
        global $wpdb;
        $this->scrapers_table = $wpdb->prefix . 'hft_scrapers';
        $this->rules_table = $wpdb->prefix . 'hft_scraper_rules';
    }

    /**
     * Get scraper by ID with rules
     */
    public function find_by_id(int $id): ?HFT_Scraper {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->scrapers_table} WHERE id = %d", $id),
            ARRAY_A
        );
        
        if (!$row) {
            return null;
        }
        
        $scraper = new HFT_Scraper($row);
        $scraper->rules = $this->get_rules_for_scraper($id);
        
        return $scraper;
    }

    /**
     * Get scraper by domain
     */
    public function find_by_domain(string $domain): ?HFT_Scraper {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->scrapers_table} WHERE domain = %s", $domain),
            ARRAY_A
        );
        
        if (!$row) {
            return null;
        }
        
        $scraper = new HFT_Scraper($row);
        $scraper->rules = $this->get_rules_for_scraper($scraper->id);
        
        return $scraper;
    }

    /**
     * Get all active scrapers
     */
    public function find_all_active(): array {
        global $wpdb;
        
        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->scrapers_table} WHERE is_active = 1 ORDER BY domain ASC",
            ARRAY_A
        );
        
        $scrapers = [];
        foreach ($rows as $row) {
            $scraper = new HFT_Scraper($row);
            $scraper->rules = $this->get_rules_for_scraper($scraper->id);
            $scrapers[] = $scraper;
        }
        
        return $scrapers;
    }


    /**
     * Save scraper (insert or update)
     */
    public function save(HFT_Scraper $scraper): int {
        global $wpdb;

        $data = [
            'domain' => $scraper->domain,
            'name' => $scraper->name,
            'logo_attachment_id' => $scraper->logo_attachment_id,
            'currency' => $scraper->currency,
            'geos' => $scraper->geos,
            'geos_input' => $scraper->geos_input,
            'affiliate_link_format' => $scraper->affiliate_link_format,
            'is_active' => $scraper->is_active,
            'use_base_parser' => $scraper->use_base_parser,
            'use_curl' => $scraper->use_curl,
            'use_scrapingrobot' => $scraper->use_scrapingrobot,
            'test_url' => $scraper->test_url
        ];

        if ($scraper->id > 0) {
            // Update
            $wpdb->update(
                $this->scrapers_table,
                $data,
                ['id' => $scraper->id],
                ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s'],
                ['%d']
            );

            // Trigger cache invalidation
            do_action( 'hft_scraper_updated' );

            return $scraper->id;
        } else {
            // Insert
            $result = $wpdb->insert(
                $this->scrapers_table,
                $data,
                ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s']
            );
            
            if ($result === false) {
                return 0;
            }
            
            // Trigger cache invalidation for new scrapers too
            do_action( 'hft_scraper_updated' );
            
            return $wpdb->insert_id;
        }
    }

    /**
     * Delete scraper and its rules
     */
    public function delete(int $id): bool {
        global $wpdb;
        
        // Delete rules first
        $wpdb->delete($this->rules_table, ['scraper_id' => $id], ['%d']);
        
        // Delete scraper
        $result = $wpdb->delete($this->scrapers_table, ['id' => $id], ['%d']);
        
        if ($result !== false) {
            // Trigger cache invalidation
            do_action( 'hft_scraper_updated' );
        }
        
        return $result !== false;
    }

    /**
     * Get rules for a scraper (ordered by priority)
     */
    private function get_rules_for_scraper(int $scraper_id): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->rules_table} WHERE scraper_id = %d AND is_active = 1 ORDER BY field_type ASC, priority ASC",
                $scraper_id
            ),
            ARRAY_A
        );

        $rules = [];
        foreach ($rows as $row) {
            $rules[] = new HFT_Scraper_Rule($row);
        }

        return $rules;
    }

    /**
     * Get rules for a scraper grouped by field type
     */
    public function get_rules_grouped_by_field(int $scraper_id): array {
        $rules = $this->get_rules_for_scraper($scraper_id);

        $grouped = [
            'price' => [],
            'status' => [],
            'shipping' => [],
        ];

        foreach ($rules as $rule) {
            if (isset($grouped[$rule->field_type])) {
                $grouped[$rule->field_type][] = $rule;
            }
        }

        return $grouped;
    }

    /**
     * Save rule (insert or update)
     */
    public function save_rule(HFT_Scraper_Rule $rule): int {
        global $wpdb;

        $data = [
            'scraper_id' => $rule->scraper_id,
            'field_type' => $rule->field_type,
            'priority' => $rule->priority,
            'extraction_mode' => $rule->extraction_mode,
            'xpath_selector' => $rule->xpath_selector,
            'attribute' => $rule->attribute,
            'regex_pattern' => $rule->regex_pattern,
            'regex_fallbacks' => $rule->regex_fallbacks ? json_encode($rule->regex_fallbacks) : null,
            'return_boolean' => $rule->return_boolean ? 1 : 0,
            'boolean_true_values' => $rule->boolean_true_values ? json_encode($rule->boolean_true_values) : null,
            'post_processing' => $rule->post_processing ? json_encode($rule->post_processing) : null,
            'is_active' => $rule->is_active ? 1 : 0
        ];

        $format = ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d'];

        if ($rule->id > 0) {
            // Update existing rule
            $wpdb->update(
                $this->rules_table,
                $data,
                ['id' => $rule->id],
                $format,
                ['%d']
            );
            return $rule->id;
        } else {
            // Insert new rule (no unique constraint check - multiple rules per field allowed)
            $wpdb->insert(
                $this->rules_table,
                $data,
                $format
            );
            return $wpdb->insert_id;
        }
    }

    /**
     * Delete rule by ID
     */
    public function delete_rule(int $rule_id): bool {
        global $wpdb;
        $result = $wpdb->delete($this->rules_table, ['id' => $rule_id], ['%d']);
        return $result !== false;
    }

    /**
     * Delete all rules for a scraper
     */
    public function delete_rules_for_scraper(int $scraper_id): bool {
        global $wpdb;
        $result = $wpdb->delete($this->rules_table, ['scraper_id' => $scraper_id], ['%d']);
        return $result !== false;
    }
    
    /**
     * Count total scrapers
     */
    public function count(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->scrapers_table}");
    }
    
    /**
     * Find scraper by ID (alias for find_by_id)
     */
    public function find(int $id): ?HFT_Scraper {
        return $this->find_by_id($id);
    }
    
    /**
     * Create new scraper
     */
    public function create(HFT_Scraper $scraper): int {
        $scraper->id = 0; // Ensure it's treated as new
        return $this->save($scraper);
    }
    
    /**
     * Update existing scraper
     */
    public function update(HFT_Scraper $scraper): bool {
        if ($scraper->id <= 0) {
            return false;
        }
        $this->save($scraper);
        return true;
    }
    
    /**
     * Find all scrapers with pagination and sorting
     */
    public function find_all(int $limit = -1, int $offset = 0, string $orderby = 'domain', string $order = 'ASC'): array {
        global $wpdb;
        
        // Validate orderby column
        $allowed_orderby = ['domain', 'name', 'last_used', 'success_rate'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'domain';
        }
        
        // Special handling for calculated columns
        if ($orderby === 'last_used') {
            $orderby_sql = "(SELECT MAX(created_at) FROM {$wpdb->prefix}hft_scraper_logs WHERE scraper_id = s.id)";
        } elseif ($orderby === 'success_rate') {
            $orderby_sql = "(SELECT AVG(success) FROM {$wpdb->prefix}hft_scraper_logs WHERE scraper_id = s.id AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY))";
        } else {
            $orderby_sql = "s.{$orderby}";
        }
        
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        
        $sql = "SELECT s.* FROM {$this->scrapers_table} s ORDER BY {$orderby_sql} {$order}";
        
        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }
        
        $rows = $wpdb->get_results($sql, ARRAY_A);
        
        $scrapers = [];
        foreach ($rows as $row) {
            $scrapers[] = new HFT_Scraper($row);
        }
        
        return $scrapers;
    }
    
    /**
     * Find rules by scraper ID
     */
    public function find_rules_by_scraper_id(int $scraper_id): array {
        return $this->get_rules_for_scraper($scraper_id);
    }
    
    /**
     * Create new rule
     */
    public function create_rule(HFT_Scraper_Rule $rule): int {
        $rule->id = 0; // Ensure it's treated as new
        return $this->save_rule($rule);
    }
    
    /**
     * Update existing rule
     */
    public function update_rule(HFT_Scraper_Rule $rule): bool {
        if ($rule->id <= 0) {
            return false;
        }
        $this->save_rule($rule);
        return true;
    }
    
    /**
     * Delete rule by scraper and field type
     */
    public function delete_rule_by_scraper_and_field(int $scraper_id, string $field_type): bool {
        global $wpdb;
        $result = $wpdb->delete(
            $this->rules_table,
            ['scraper_id' => $scraper_id, 'field_type' => $field_type],
            ['%d', '%s']
        );
        return $result !== false;
    }
    
    /**
     * Find rule by scraper and field type
     */
    public function find_rule_by_scraper_and_field(int $scraper_id, string $field_type): ?HFT_Scraper_Rule {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->rules_table} WHERE scraper_id = %d AND field_type = %s",
                $scraper_id,
                $field_type
            ),
            ARRAY_A
        );
        
        if (!$row) {
            return null;
        }
        
        return new HFT_Scraper_Rule($row);
    }
}