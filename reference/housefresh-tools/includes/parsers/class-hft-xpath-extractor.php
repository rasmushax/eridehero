<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * XPath extraction engine
 */
class HFT_XPath_Extractor {
    private DOMDocument $dom;
    private DOMXPath $xpath;
    private bool $errors_suppressed = false;
    
    /**
     * Initialize with HTML content
     */
    public function __construct(string $html_content) {
        
        // Suppress libxml errors temporarily
        $old_setting = libxml_use_internal_errors(true);
        $this->errors_suppressed = !$old_setting;
        
        // Create DOM document
        $this->dom = new DOMDocument();
        
        // Load HTML with proper encoding
        $loaded = $this->dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $html_content,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        
        if (!$loaded) {
            error_log("[HFT XPath Debug] Failed to load HTML into DOMDocument");
        }
        
        // Check for any parsing errors
        $errors = libxml_get_errors();
        
        // Clear any errors that occurred
        libxml_clear_errors();
        
        // Create XPath object
        $this->xpath = new DOMXPath($this->dom);
        
    }
    
    /**
     * Destructor to restore error handling
     */
    public function __destruct() {
        if ($this->errors_suppressed) {
            libxml_use_internal_errors(false);
        }
    }
    
    /**
     * Extract value using XPath selector
     * 
     * @param string $selector XPath selector
     * @param string|null $attribute Attribute to extract, null for text content
     * @return string|null Extracted value or null if not found
     */
    public function extract(string $selector, ?string $attribute = null): ?string {
        
        try {
            // Validate XPath
            if (empty($selector)) {
                return null;
            }
            
            // Clear any previous libxml errors
            libxml_clear_errors();
            
            // Execute XPath query
            $nodes = @$this->xpath->query($selector);
            
            // Check for libxml errors
            $libxml_errors = libxml_get_errors();
            if (!empty($libxml_errors)) {
                libxml_clear_errors();
            }
            
            if ($nodes === false) {
                return null;
            }
            
            if ($nodes->length === 0) {
                return null;
            }
            
            // Get first node
            $node = $nodes->item(0);
            
            // Extract value
            if ($attribute) {
                // Get attribute value
                if ($node instanceof DOMElement && $node->hasAttribute($attribute)) {
                    $value = $node->getAttribute($attribute);
                    return $value;
                }
                return null;
            } else {
                // Get text content
                $value = $this->getNodeText($node);
                return $value;
            }
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Extract all matching values
     * 
     * @param string $selector XPath selector
     * @param string|null $attribute Attribute to extract
     * @return array Array of extracted values
     */
    public function extractAll(string $selector, ?string $attribute = null): array {
        try {
            $values = [];
            
            // Execute XPath query
            $nodes = @$this->xpath->query($selector);
            
            if ($nodes === false || $nodes->length === 0) {
                return $values;
            }
            
            // Extract from each node
            foreach ($nodes as $node) {
                $value = $attribute && $node instanceof DOMElement && $node->hasAttribute($attribute)
                    ? $node->getAttribute($attribute)
                    : $this->getNodeText($node);
                    
                if ($value !== null && $value !== '') {
                    $values[] = $value;
                }
            }
            
            return $values;
        } catch (Exception $e) {
            error_log('HFT XPath extraction error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Test if selector matches any nodes
     */
    public function test(string $selector): array {
        
        try {
            // Clear any previous libxml errors
            libxml_clear_errors();
            
            $nodes = @$this->xpath->query($selector);
            
            // Get any libxml errors
            $libxml_errors = libxml_get_errors();
            if (!empty($libxml_errors)) {
                $error_messages = [];
                foreach ($libxml_errors as $error) {
                    $error_messages[] = trim($error->message);
                }
                libxml_clear_errors();
                
                return [
                    'valid' => false,
                    'error' => 'XPath error: ' . implode('; ', $error_messages),
                    'count' => 0
                ];
            }
            
            if ($nodes === false) {
                return [
                    'valid' => false,
                    'error' => 'Invalid XPath selector',
                    'count' => 0
                ];
            }
            
            return [
                'valid' => true,
                'error' => null,
                'count' => $nodes->length
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'count' => 0
            ];
        }
    }
    
    /**
     * Get text content from node
     */
    private function getNodeText(DOMNode $node): ?string {
        // For text nodes, return node value
        if ($node->nodeType === XML_TEXT_NODE) {
            return trim($node->nodeValue);
        }
        
        // For element nodes, get text content
        if ($node instanceof DOMElement) {
            // First try textContent for simple text
            $text = trim($node->textContent);
            
            // If empty or only whitespace, try to get direct text children
            if (empty($text)) {
                $text = '';
                foreach ($node->childNodes as $child) {
                    if ($child->nodeType === XML_TEXT_NODE) {
                        $text .= $child->nodeValue;
                    }
                }
                $text = trim($text);
            }
            
            return $text !== '' ? $text : null;
        }
        
        // For other node types, try nodeValue
        $value = trim($node->nodeValue ?? '');
        return $value !== '' ? $value : null;
    }
}