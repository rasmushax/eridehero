<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scraper rule data model
 */
class HFT_Scraper_Rule {
    public int $id;
    public int $scraper_id;
    public string $field_type;
    public string $xpath_selector;
    public ?string $attribute;
    public ?array $post_processing;
    public bool $is_active;

    public function __construct(array $data = []) {
        $this->id = (int) ($data['id'] ?? 0);
        $this->scraper_id = (int) ($data['scraper_id'] ?? 0);
        $this->field_type = $data['field_type'] ?? '';
        $this->xpath_selector = $data['xpath_selector'] ?? '';
        $this->attribute = $data['attribute'] ?? null;
        $this->post_processing = $this->parse_post_processing($data['post_processing'] ?? null);
        $this->is_active = (bool) ($data['is_active'] ?? true);
    }

    private function parse_post_processing($value): ?array {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    public function to_array(): array {
        return [
            'id' => $this->id,
            'scraper_id' => $this->scraper_id,
            'field_type' => $this->field_type,
            'xpath_selector' => $this->xpath_selector,
            'attribute' => $this->attribute,
            'post_processing' => $this->post_processing,
            'is_active' => $this->is_active
        ];
    }
}