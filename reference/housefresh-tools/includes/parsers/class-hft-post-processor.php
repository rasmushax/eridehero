<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post-processing for extracted values
 */
class HFT_Post_Processor {
    
    /**
     * Apply post-processing rules to a value
     * 
     * @param string|null $value The extracted value
     * @param array|null $rules Post-processing rules
     * @param string $field_type The field type being processed
     * @return string|null Processed value
     */
    public function process(?string $value, ?array $rules, string $field_type): ?string {
        if (!is_string($value) || $value === '') {
            return null;
        }
        
        if (empty($rules)) {
            return $value;
        }
        
        // Apply trim
        if (!empty($rules['trim'])) {
            $value = trim($value);
        }
        
        // Field-specific processing
        switch ($field_type) {
            case 'price':
                $value = $this->processPrice($value, $rules);
                break;
                
            case 'currency':
                $value = $this->processCurrency($value, $rules);
                break;
                
            case 'status':
                $value = $this->processStatus($value, $rules);
                break;
                
            case 'shipping':
                $value = $this->processShipping($value, $rules);
                break;
        }
        
        // Apply regex replacement
        if (!empty($rules['regex_replace']) && is_array($rules['regex_replace'])) {
            $value = $this->applyRegexReplace($value, $rules['regex_replace']);
        }
        
        
        return $value !== '' ? $value : null;
    }
    
    /**
     * Process price value
     */
    private function processPrice(string $value, array $rules): string {
        if (!is_string($value) || $value === '') {
            return '';
        }
        
        // Remove currency symbols if requested
        if (!empty($rules['remove_currency'])) {
            // Common currency symbols and codes
            $currencies = [
                '$', '€', '£', '¥', '₹', '₽', 'kr', 'R$', 'zł', 'Kč',
                'USD', 'EUR', 'GBP', 'JPY', 'INR', 'RUB', 'SEK', 'BRL', 'PLN', 'CZK'
            ];
            
            // Remove currency symbols/codes
            $value = str_replace($currencies, '', $value);
            
            // Also remove common price prefixes/suffixes
            $value = preg_replace('/^(price[:\s]*|cost[:\s]*|from[:\s]*)/i', '', $value);
        }
        
        // Extract numeric value
        $value = $this->extractNumericValue($value);
        
        
        return $value;
    }
    
    /**
     * Process currency value
     */
    private function processCurrency(string $value, array $rules): string {
        if (!is_string($value) || $value === '') {
            return '';
        }
        
        // Normalize currency codes
        $value = strtoupper(trim($value));
        
        // Map symbols to codes
        $symbol_map = [
            '$' => 'USD',
            '€' => 'EUR',
            '£' => 'GBP',
            '¥' => 'JPY',
            '₹' => 'INR',
            '₽' => 'RUB',
            'kr' => 'SEK',
            'R$' => 'BRL',
            'zł' => 'PLN',
            'Kč' => 'CZK'
        ];
        
        if (isset($symbol_map[$value])) {
            $value = $symbol_map[$value];
        }
        
        // Ensure it's a valid 3-letter currency code
        if (strlen($value) !== 3) {
            // Try to extract 3-letter code
            if (preg_match('/([A-Z]{3})/', $value, $matches)) {
                $value = $matches[1];
            }
        }
        
        return $value;
    }
    
    /**
     * Process status value
     */
    private function processStatus(string $value, array $rules): string {
        if (!is_string($value) || $value === '') {
            return '';
        }
        
        // Normalize common status values
        $value_lower = strtolower(trim($value));
        
        // Map to standard statuses
        $status_map = [
            'in stock' => 'In Stock',
            'instock' => 'In Stock',
            'available' => 'In Stock',
            'in-stock' => 'In Stock',
            'out of stock' => 'Out of Stock',
            'outofstock' => 'Out of Stock',
            'unavailable' => 'Out of Stock',
            'out-of-stock' => 'Out of Stock',
            'sold out' => 'Out of Stock',
            'soldout' => 'Out of Stock',
            'pre-order' => 'Pre-order',
            'preorder' => 'Pre-order',
            'backorder' => 'Backorder',
            'back-order' => 'Backorder'
        ];
        
        if (isset($status_map[$value_lower])) {
            $value = $status_map[$value_lower];
        } else {
            // Keep original casing for unknown statuses
            $value = trim($value);
        }
        
        return $value;
    }
    
    /**
     * Process shipping value
     */
    private function processShipping(string $value, array $rules): string {
        if (!is_string($value) || $value === '') {
            return '';
        }
        
        // Clean up shipping info
        $value = trim($value);
        
        // Remove common prefixes
        $value = preg_replace('/^(shipping[:\s]*|delivery[:\s]*)/i', '', $value);
        
        // Normalize "free shipping" variations
        if (preg_match('/free\s*(shipping|delivery)/i', $value)) {
            $value = 'Free Shipping';
        }
        
        return $value;
    }
    
    /**
     * Apply regex replacement
     */
    private function applyRegexReplace(string $value, array $config): string {
        if (!is_string($value) || empty($config['pattern']) || !is_string($config['pattern'])) {
            return $value;
        }
        
        $pattern = $config['pattern'];
        $replacement = isset($config['replacement']) && is_string($config['replacement']) ? $config['replacement'] : '';
        
        // Ensure pattern has delimiters
        if (!preg_match('/^[\/\#\~].*[\/\#\~][imsxADSUXJu]*$/', $pattern)) {
            // Add delimiters if missing
            $pattern = '/' . str_replace('/', '\/', $pattern) . '/';
        }
        
        // Suppress warnings for invalid patterns
        $result = @preg_replace($pattern, $replacement, $value);
        
        // Return original if regex failed
        return $result !== null ? $result : $value;
    }
    
    /**
     * Extract numeric value from string
     */
    private function extractNumericValue(string $value): string {
        if (!is_string($value) || $value === '') {
            return '';
        }
        
        // Remove thousands separators (both comma and space)
        $value = str_replace([',', ' '], '', $value);
        
        // Convert comma decimal separator to dot
        if (preg_match('/(\d+)[,](\d{1,2})(?!\d)/', $value, $matches)) {
            $value = str_replace($matches[0], $matches[1] . '.' . $matches[2], $value);
        }
        
        // Extract first numeric value
        if (preg_match('/\d+\.?\d*/', $value, $matches)) {
            return $matches[0];
        }
        
        return $value;
    }
}