<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scraper data model
 */
class HFT_Scraper {
    public int $id;
    public string $domain;
    public string $name;
    public ?int $logo_attachment_id;
    public string $currency;
    public ?string $geos;
    public ?string $geos_input;
    public ?string $affiliate_link_format;
    public bool $is_active;
    public bool $use_base_parser;
    public bool $use_curl;
    public bool $use_scrapingrobot;
    public bool $scrapingrobot_render_js;
    public bool $shopify_markets;
    public ?string $shopify_method;
    public ?string $shopify_storefront_token;
    public ?string $shopify_shop_domain;
    public int $consecutive_successes;
    public ?string $health_reset_at;
    public ?string $test_url;
    public ?string $created_at;
    public ?string $updated_at;
    public array $rules = [];

    public function __construct(array $data = []) {
        $this->id = (int) ($data['id'] ?? 0);
        $this->domain = $data['domain'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->logo_attachment_id = isset($data['logo_attachment_id']) ? (int) $data['logo_attachment_id'] ?: null : null;
        $this->currency = $data['currency'] ?? 'USD';
        $this->geos = $data['geos'] ?? null;
        $this->geos_input = $data['geos_input'] ?? null;
        $this->affiliate_link_format = $data['affiliate_link_format'] ?? null;
        $this->is_active = (bool) ($data['is_active'] ?? true);
        $this->use_base_parser = (bool) ($data['use_base_parser'] ?? true);
        $this->use_curl = (bool) ($data['use_curl'] ?? false);
        $this->use_scrapingrobot = (bool) ($data['use_scrapingrobot'] ?? false);
        $this->scrapingrobot_render_js = (bool) ($data['scrapingrobot_render_js'] ?? false);
        $this->shopify_markets = (bool) ($data['shopify_markets'] ?? false);
        $this->shopify_method = $data['shopify_method'] ?? null;
        $this->shopify_storefront_token = $data['shopify_storefront_token'] ?? null;
        $this->shopify_shop_domain = $data['shopify_shop_domain'] ?? null;
        $this->consecutive_successes = (int) ($data['consecutive_successes'] ?? 0);
        $this->health_reset_at = $data['health_reset_at'] ?? null;
        $this->test_url = $data['test_url'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
    }

    public function to_array(): array {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'name' => $this->name,
            'logo_attachment_id' => $this->logo_attachment_id,
            'currency' => $this->currency,
            'geos' => $this->geos,
            'geos_input' => $this->geos_input,
            'affiliate_link_format' => $this->affiliate_link_format,
            'is_active' => $this->is_active,
            'use_base_parser' => $this->use_base_parser,
            'use_curl' => $this->use_curl,
            'use_scrapingrobot' => $this->use_scrapingrobot,
            'scrapingrobot_render_js' => $this->scrapingrobot_render_js,
            'shopify_markets' => $this->shopify_markets,
            'shopify_method' => $this->shopify_method,
            'shopify_storefront_token' => $this->shopify_storefront_token,
            'shopify_shop_domain' => $this->shopify_shop_domain,
            'consecutive_successes' => $this->consecutive_successes,
            'health_reset_at' => $this->health_reset_at,
            'test_url' => $this->test_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'rules' => $this->rules
        ];
    }
}