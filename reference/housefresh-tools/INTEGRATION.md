# Housefresh Tools - Plugin Integration Guide

This document provides comprehensive documentation for integrating with the Housefresh Tools WordPress plugin from external plugins or themes. The plugin provides affiliate product management, automated price tracking, geo-targeted affiliate links, and schema markup generation.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Database Schema](#database-schema)
3. [REST API Endpoints](#rest-api-endpoints)
4. [WordPress Hooks](#wordpress-hooks)
5. [PHP API Reference](#php-api-reference)
6. [Frontend JavaScript API](#frontend-javascript-api)
7. [Creating Custom Parsers](#creating-custom-parsers)
8. [Integration Examples](#integration-examples)
9. [Caching Strategies](#caching-strategies)
10. [Best Practices](#best-practices)

---

## Architecture Overview

### Core Components

```
┌─────────────────────────────────────────────────────────────────────┐
│                     Housefresh Tools Plugin                         │
├─────────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────────┐  │
│  │   Products   │  │   Scrapers   │  │     REST API             │  │
│  │  (hf_product │  │  (Database-  │  │  /housefresh-tools/v1/   │  │
│  │    CPT)      │  │   driven)    │  │                          │  │
│  └──────────────┘  └──────────────┘  └──────────────────────────┘  │
│          │                │                       │                 │
│          ▼                ▼                       ▼                 │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │                    Database Tables                            │  │
│  │  hft_tracked_links | hft_price_history | hft_scrapers | ...   │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                              │                                      │
│                              ▼                                      │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │               Cron Job (5-minute intervals)                   │  │
│  │         Price scraping via Parser Factory                     │  │
│  └──────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

### Design Patterns Used

| Pattern    | Implementation                        | Purpose                              |
|------------|---------------------------------------|--------------------------------------|
| Singleton  | `HFT_Loader`, `HFT_Scraper_Registry`  | Single instance management           |
| Factory    | `HFT_Parser_Factory`                  | Dynamic parser instantiation         |
| Strategy   | `HFT_ParserInterface` implementations | Interchangeable scraping algorithms  |
| Repository | `HFT_Scraper_Repository`              | Data access abstraction              |
| Observer   | WordPress hooks                       | Event-driven cache invalidation      |

### Key Constants

```php
// Defined in housefresh-tools.php
define('HFT_VERSION', '1.0.0');
define('HFT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('HFT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HFT_PLUGIN_FILE', __FILE__);
```

---

## Database Schema

The plugin creates 6 custom database tables. All table names are prefixed with `{$wpdb->prefix}hft_`.

### hft_tracked_links

Primary table storing affiliate link tracking records.

```sql
CREATE TABLE {prefix}hft_tracked_links (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_post_id       BIGINT UNSIGNED NOT NULL,     -- Links to hf_product CPT
    tracking_url          TEXT NOT NULL,                 -- URL being tracked (or ASIN for Amazon)
    parser_identifier     VARCHAR(100) NOT NULL,         -- 'amazon' or domain like 'shop.levoit.com'
    scraper_id            BIGINT UNSIGNED NULL,          -- FK to hft_scrapers
    geo_target            TEXT NULL,                     -- Target GEO code (e.g., 'US', 'GB')
    affiliate_link_override TEXT NULL,                   -- Optional manual affiliate URL override
    current_price         DECIMAL(10,2) NULL,            -- Latest scraped price
    current_currency      VARCHAR(10) NULL,              -- Currency code (USD, EUR, etc.)
    current_status        VARCHAR(50) NULL,              -- 'In Stock', 'Out of Stock', etc.
    current_shipping_info TEXT NULL,                     -- Shipping details
    last_scraped_at       DATETIME NULL,                 -- Last scrape timestamp (GMT)
    last_scrape_successful BOOLEAN NULL,                 -- Success indicator
    consecutive_failures  INT UNSIGNED DEFAULT 0,        -- Failure counter for health monitoring
    last_error_message    TEXT NULL,                     -- Last error details
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX product_post_id (product_post_id),
    INDEX parser_identifier (parser_identifier),
    INDEX scraper_id (scraper_id),
    INDEX last_scrape_successful (last_scrape_successful),
    INDEX idx_product_geo (product_post_id, geo_target(20)),
    INDEX idx_last_scraped (last_scraped_at, id)
);
```

### hft_price_history

Time-series price data for historical tracking and charts.

```sql
CREATE TABLE {prefix}hft_price_history (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracked_link_id BIGINT UNSIGNED NOT NULL,    -- FK to hft_tracked_links
    price           DECIMAL(10,2) NOT NULL,
    currency        VARCHAR(10) NOT NULL,
    status          VARCHAR(50) NOT NULL,
    scraped_at      DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX tracked_link_id (tracked_link_id),
    INDEX idx_link_scraped (tracked_link_id, scraped_at)
);
```

### hft_scrapers

Defines scraper configurations for different retailers.

```sql
CREATE TABLE {prefix}hft_scrapers (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain                  VARCHAR(191) NOT NULL UNIQUE,  -- e.g., 'shop.levoit.com'
    name                    VARCHAR(255) NOT NULL,          -- Display name
    currency                VARCHAR(3) DEFAULT 'USD',
    geos                    TEXT NULL,                      -- Comma-separated GEO codes
    affiliate_link_format   TEXT NULL,                      -- Template: {URL}, {URLE}, {ID}
    is_active               BOOLEAN DEFAULT 1,
    use_base_parser         BOOLEAN DEFAULT 1,              -- Use XPath-based parser
    use_curl                BOOLEAN DEFAULT 0,              -- Use cURL instead of Guzzle
    use_scrapingrobot       BOOLEAN DEFAULT 0,              -- Use Scraping Robot API
    scrapingrobot_render_js BOOLEAN DEFAULT 0,
    consecutive_successes   INT UNSIGNED DEFAULT 0,         -- Health metric
    health_reset_at         DATETIME NULL,
    test_url                TEXT NULL,                      -- URL for testing
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX is_active (is_active)
);
```

### hft_scraper_rules

XPath/CSS selectors for data extraction per scraper.

```sql
CREATE TABLE {prefix}hft_scraper_rules (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scraper_id      BIGINT UNSIGNED NOT NULL,           -- FK to hft_scrapers
    field_type      ENUM('price', 'status', 'shipping') NOT NULL,
    xpath_selector  TEXT NOT NULL,                       -- XPath expression
    attribute       VARCHAR(100) NULL,                   -- DOM attribute to extract
    post_processing TEXT NULL,                           -- Post-processing logic
    is_active       BOOLEAN DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX scraper_id (scraper_id),
    INDEX field_type (field_type),
    UNIQUE KEY unique_scraper_field (scraper_id, field_type)
);
```

### hft_parser_rules (Legacy)

Legacy table for site-specific parsing configurations.

```sql
CREATE TABLE {prefix}hft_parser_rules (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_domain      VARCHAR(191) NOT NULL UNIQUE,
    affiliate_format TEXT NULL,
    priority         INT DEFAULT 10
);
```

### hft_scraper_logs

Audit trail of scraping operations.

```sql
CREATE TABLE {prefix}hft_scraper_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scraper_id      BIGINT UNSIGNED NOT NULL,
    tracked_link_id BIGINT UNSIGNED NULL,
    url             TEXT NOT NULL,
    success         BOOLEAN NOT NULL,
    extracted_data  TEXT NULL,                 -- JSON encoded
    error_message   TEXT NULL,
    execution_time  FLOAT NULL,                -- Seconds
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX scraper_id (scraper_id),
    INDEX tracked_link_id (tracked_link_id),
    INDEX success (success),
    INDEX created_at (created_at),
    INDEX idx_scraper_created (scraper_id, created_at)
);
```

### Direct Database Access Example

```php
<?php
// Get all tracked links for a product
global $wpdb;
$tracked_links_table = $wpdb->prefix . 'hft_tracked_links';

$links = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$tracked_links_table} WHERE product_post_id = %d",
        $product_id
    ),
    ARRAY_A
);

// Get price history for a tracked link
$price_history_table = $wpdb->prefix . 'hft_price_history';

$history = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT price, currency, status, scraped_at
         FROM {$price_history_table}
         WHERE tracked_link_id = %d
         ORDER BY scraped_at DESC
         LIMIT 100",
        $tracked_link_id
    ),
    ARRAY_A
);
```

---

## REST API Endpoints

Base URL: `/wp-json/housefresh-tools/v1`

### GET /get-affiliate-link

Fetches GEO-targeted affiliate links for a product.

**Parameters:**

| Parameter   | Type    | Required | Default | Description                    |
|-------------|---------|----------|---------|--------------------------------|
| product_id  | integer | Yes      | -       | Product post ID (hf_product)   |
| target_geo  | string  | No       | 'US'    | 2-letter ISO country code      |

**Rate Limit:** 60 requests/minute per IP

**Response:**

```json
{
  "links": [
    {
      "url": "https://www.amazon.com/dp/B08XXXXX?tag=yourtag-20",
      "retailer_name": "Amazon",
      "price_string": "$299.99",
      "parser_identifier": "amazon",
      "original_url_used": "B08XXXXX",
      "link_geo": "US",
      "is_primary_geo": true
    }
  ],
  "message": "",
  "processed_geo": "US"
}
```

**Example Usage:**

```javascript
// JavaScript
fetch('/wp-json/housefresh-tools/v1/get-affiliate-link?product_id=123&target_geo=GB')
  .then(response => response.json())
  .then(data => {
    if (data.links && data.links.length > 0) {
      data.links.forEach(link => {
        console.log(`${link.retailer_name}: ${link.price_string} - ${link.url}`);
      });
    }
  });
```

```php
// PHP
$response = wp_remote_get(
    rest_url('housefresh-tools/v1/get-affiliate-link'),
    [
        'body' => [
            'product_id' => 123,
            'target_geo' => 'GB'
        ]
    ]
);
$data = json_decode(wp_remote_retrieve_body($response), true);
```

---

### GET /detect-geo

Detects user's country via IP address using IPInfo API.

**Parameters:**

| Parameter | Type   | Required | Description                      |
|-----------|--------|----------|----------------------------------|
| test_ip   | string | No       | IP address to test (for debugging) |

**Rate Limit:** 30 requests/minute per IP

**Response:**

```json
{
  "country_code": "US",
  "ip": "203.0.113.45",
  "source": "ipinfo_api"
}
```

**Cache:** Results cached for 24 hours.

---

### GET /products-for-select

Fetches all products for admin SelectControl components.

**Permission:** Requires `manage_options` capability (configurable via filter).

**Response:**

```json
[
  {
    "value": 123,
    "label": "Dyson Air Purifier HP07",
    "original_url": "https://example.com/?p=123"
  },
  {
    "value": 124,
    "label": "Levoit Core 300S",
    "original_url": "https://example.com/?p=124"
  }
]
```

---

### GET /product/{product_id}/price-history

Fetches complete price history for a product (admin use).

**Permission:** Requires `edit_post` capability for the specific product.

**Response:**

```json
{
  "productId": 123,
  "links": [
    {
      "trackedLinkId": 1,
      "identifier": "amazon (B08XXXXX)",
      "currencySymbol": "$",
      "history": [
        {"scraped_at": "2024-01-15 10:30:00", "price": 299.99},
        {"scraped_at": "2024-01-14 10:30:00", "price": 319.99}
      ],
      "summary": {
        "currentPrice": 299.99,
        "lowestPrice": {"price": 279.99, "date": "2024-01-10 10:30:00"},
        "highestPrice": {"price": 349.99, "date": "2023-12-01 10:30:00"},
        "averagePrice": 305.50
      }
    }
  ],
  "overallSummary": {
    "lowestPrice": {"price": 279.99, "date": "2024-01-10 10:30:00", "source_id": 1},
    "highestPrice": {"price": 349.99, "date": "2023-12-01 10:30:00", "source_id": 1},
    "averagePrice": 305.50,
    "totalDataPoints": 365
  }
}
```

---

### GET /product/{product_id}/price-history-chart

Fetches GEO-targeted price history for frontend charts.

**Parameters:**

| Parameter   | Type    | Required | Default | Description                |
|-------------|---------|----------|---------|----------------------------|
| product_id  | integer | Yes      | -       | Product post ID            |
| target_geo  | string  | No       | 'US'    | 2-letter country code      |

**Rate Limit:** 30 requests/minute per IP

**Response:**

```json
{
  "productId": 123,
  "targetGeo": "US",
  "links": [
    {
      "trackedLinkId": 1,
      "retailerName": "Amazon (B08XXXXX)",
      "parserIdentifier": "amazon",
      "currencySymbol": "$",
      "geo": "US",
      "history": [
        {"x": "2024-01-15 10:30:00", "y": 299.99, "date": "2024-01-15 10:30:00", "status": "In Stock"},
        {"x": "2024-01-14 10:30:00", "y": 319.99, "date": "2024-01-14 10:30:00", "status": "In Stock"}
      ]
    }
  ]
}
```

**Cache:** 30 minutes.

---

## WordPress Hooks

### Actions (do_action)

#### hft_price_updated

Triggered when a price is successfully scraped and stored.

```php
/**
 * @param int $tracked_link_id The ID of the tracked link
 * @param int $product_id The product post ID
 */
do_action('hft_price_updated', $tracked_link_id, $product_id);
```

**Example Usage:**

```php
<?php
// In your integration plugin
add_action('hft_price_updated', 'my_handle_price_update', 10, 2);

function my_handle_price_update(int $tracked_link_id, int $product_id): void {
    // Clear your plugin's cache
    wp_cache_delete('my_product_data_' . $product_id, 'my_plugin');

    // Notify external systems
    global $wpdb;
    $tracked_link = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hft_tracked_links WHERE id = %d",
            $tracked_link_id
        )
    );

    if ($tracked_link) {
        // Send webhook, update external database, etc.
        do_something_with_new_price($tracked_link->current_price, $product_id);
    }
}
```

#### hft_scraper_updated

Triggered when scraper configuration changes.

```php
do_action('hft_scraper_updated', $scraper_id);
```

#### hft_rate_limit_exceeded

Triggered when a rate limit is exceeded.

```php
/**
 * @param string $endpoint The endpoint that was rate limited
 * @param string $identifier The user/IP identifier
 * @param array $rate_limit_result Rate limit details
 */
do_action('hft_rate_limit_exceeded', $endpoint, $identifier, $rate_limit_result);
```

---

### Filters (apply_filters)

#### hft_rate_limit_config

Customize rate limit configurations.

```php
/**
 * @param array $config Rate limit configuration
 * @return array Modified configuration
 */
apply_filters('hft_rate_limit_config', $config);
```

**Example:**

```php
<?php
add_filter('hft_rate_limit_config', 'my_customize_rate_limits');

function my_customize_rate_limits(array $config): array {
    // Increase affiliate link rate limit
    $config['get-affiliate-link'] = [
        'requests_per_minute' => 120,  // Double the default
        'burst_limit' => 20
    ];

    return $config;
}
```

#### hft_rate_limit_config_{endpoint}

Per-endpoint rate limit customization.

```php
add_filter('hft_rate_limit_config_detect-geo', function($config) {
    $config['requests_per_minute'] = 60;
    return $config;
});
```

#### hft_products_select_capability

Customize capability required for products-for-select endpoint.

```php
/**
 * @param string $capability Default: 'manage_options'
 * @return string Required capability
 */
apply_filters('hft_products_select_capability', 'manage_options');
```

**Example:**

```php
<?php
// Allow editors to access products list
add_filter('hft_products_select_capability', function($cap) {
    return 'edit_posts';
});
```

---

### Hooking Into WordPress Core Actions

The plugin hooks into these WordPress actions:

| Hook                        | Priority | Purpose                            |
|-----------------------------|----------|------------------------------------|
| `init`                      | 0        | Register hf_product CPT            |
| `admin_menu`                | 10       | Add admin menu pages               |
| `save_post_hf_product`      | 10       | Save tracking links meta           |
| `rest_api_init`             | 10       | Register REST endpoints            |
| `wp_head`                   | 10       | Output schema.org markup           |
| `acf/init`                  | 5        | Register ACF blocks                |

---

## PHP API Reference

### HFT_Affiliate_Link_Generator (Static Class)

Generate affiliate links programmatically.

```php
<?php
// Ensure the class is loaded
if (!class_exists('HFT_Affiliate_Link_Generator')) {
    require_once WP_PLUGIN_DIR . '/housefresh-tools/includes/class-hft-affiliate-link-generator.php';
}

/**
 * Generate an affiliate link
 *
 * @param string $original_url Original product URL or ASIN
 * @param string|null $target_geo Target country code (default: 'US')
 * @param string|null $product_id_override Optional product ID (e.g., ASIN)
 * @return string|null Affiliate link or null if no config found
 */
$affiliate_link = HFT_Affiliate_Link_Generator::get_affiliate_link(
    'https://www.amazon.com/dp/B08XXXXX',
    'US',
    'B08XXXXX'
);

/**
 * Check if URL is an Amazon URL
 */
$is_amazon = HFT_Affiliate_Link_Generator::is_amazon_url('https://www.amazon.co.uk/dp/B08XXX');
// Returns: true

/**
 * Extract ASIN from Amazon URL
 */
$asin = HFT_Affiliate_Link_Generator::extract_asin_from_url(
    'https://www.amazon.com/Some-Product/dp/B08XXXXX/ref=sr_1_1'
);
// Returns: 'B08XXXXX'

/**
 * Validate ASIN format
 */
$valid = HFT_Affiliate_Link_Generator::is_valid_asin('B08XXXXX12');
// Returns: true (10 alphanumeric characters)
```

---

### HFT_Scraper_Repository

Data access layer for scraper configurations.

```php
<?php
if (!class_exists('HFT_Scraper_Repository')) {
    require_once WP_PLUGIN_DIR . '/housefresh-tools/includes/repositories/class-hft-scraper-repository.php';
}

$repository = new HFT_Scraper_Repository();

// Find scraper by ID (includes rules)
$scraper = $repository->find_by_id(1);
// Returns: HFT_Scraper object or null

// Find scraper by domain
$scraper = $repository->find_by_domain('shop.levoit.com');

// Get all active scrapers
$active_scrapers = $repository->find_all_active();
// Returns: array of HFT_Scraper objects

// Get rules for a scraper
$rules = $repository->get_rules_for_scraper($scraper_id);
// Returns: array of HFT_Scraper_Rule objects
```

---

### HFT_Scraper_Registry (Singleton)

Registry for looking up scrapers by domain.

```php
<?php
$registry = HFT_Scraper_Registry::get_instance();

// Get scraper for a domain
$scraper = $registry->get_scraper_for_domain('shop.levoit.com');

// Extract domain from URL
$domain = $registry->extract_domain_from_url('https://shop.levoit.com/products/some-product');
// Returns: 'shop.levoit.com'

// Check if scraper exists for URL
$has_scraper = $registry->has_scraper_for_url('https://shop.levoit.com/products/test');
// Returns: true/false
```

---

### HFT_Scraper_Manager

Manage scraping operations.

```php
<?php
if (!class_exists('HFT_Scraper_Manager')) {
    require_once WP_PLUGIN_DIR . '/housefresh-tools/includes/class-hft-scraper-manager.php';
}

$manager = new HFT_Scraper_Manager();

// Scrape a specific tracked link
$success = $manager->scrape_link($tracked_link_id);
// Returns: true on success, false on failure

// Scrape by product ID (scrapes the most recent tracked link)
$result = $manager->scrape_link_by_product_id($product_id);
// Returns: true on success, WP_Error on failure
```

---

### HFT_Parser_Factory (Static Class)

Create parser instances dynamically.

```php
<?php
if (!class_exists('HFT_Parser_Factory')) {
    require_once WP_PLUGIN_DIR . '/housefresh-tools/includes/parsers/class-hft-parser-factory.php';
}

// Create parser by URL and identifier
$parser = HFT_Parser_Factory::create_parser(
    'https://shop.levoit.com/products/some-product',
    'shop.levoit.com'
);

// Create parser by scraper ID
$parser = HFT_Parser_Factory::create_parser_by_scraper_id(5);

// Use the parser
if ($parser instanceof HFT_ParserInterface) {
    $result = $parser->parse($url, $tracked_link_data);
    // $result contains: price, currency, status, shipping_info, error
}
```

---

### HFT_Rate_Limiter

Manage rate limiting for your own endpoints.

```php
<?php
if (!class_exists('HFT_Rate_Limiter')) {
    require_once WP_PLUGIN_DIR . '/housefresh-tools/includes/class-hft-rate-limiter.php';
}

$rate_limiter = new HFT_Rate_Limiter();

// Check rate limit
$result = $rate_limiter->check_rate_limit('my-custom-endpoint', get_current_user_id());

if (!$result['allowed']) {
    // Rate limit exceeded
    return $rate_limiter->create_rate_limit_error($result);
}

// Add rate limit headers to response
$rate_limiter->add_rate_limit_headers($result);
```

---

### HFT_IPInfo_Service

GEO detection service.

```php
<?php
if (!class_exists('HFT_IPInfo_Service')) {
    require_once WP_PLUGIN_DIR . '/housefresh-tools/includes/class-hft-ipinfo-service.php';
}

$service = new HFT_IPInfo_Service();

$result = $service->get_country_code('203.0.113.45');
// Returns: ['country_code' => 'US', 'ip' => '203.0.113.45', 'source' => 'ipinfo_api']
// Or on error: ['country_code' => 'US', 'error' => 'Error message']
```

---

### HFT_Cache_Manager

Cache invalidation management.

```php
<?php
if (!class_exists('HFT_Cache_Manager')) {
    require_once WP_PLUGIN_DIR . '/housefresh-tools/includes/class-hft-cache-manager.php';
}

$cache_manager = new HFT_Cache_Manager();

// Invalidate product-related caches
$cache_manager->invalidate_product_caches($post_id, $post, $update);

// Invalidate price-related caches (called automatically on hft_price_updated)
$cache_manager->invalidate_price_related_caches($tracked_link_id, $product_id);

// Invalidate all scraper configuration caches
$cache_manager->invalidate_scraper_caches();
```

---

## Frontend JavaScript API

### HFT_Frontend (Global Object)

The plugin exposes `window.HFT_Frontend` for GEO detection.

```javascript
// Include the script dependency
// The script is registered as 'hft-frontend-core'
// Automatically loaded when affiliate-link or price-history blocks are used

// Detect user GEO (with 6-hour localStorage cache)
window.HFT_Frontend.detectUserGeo().then(geo => {
    console.log('User country:', geo); // e.g., 'US'
});

// Clear GEO cache
window.HFT_Frontend.clearGeoCache();

// Get cache info
const cacheInfo = window.HFT_Frontend.getGeoCacheInfo();
// Returns: { cached: true, geo: 'US', expires: 1705312345678 }
```

### Enqueuing Frontend Scripts

```php
<?php
// Enqueue the frontend core script in your theme/plugin
add_action('wp_enqueue_scripts', function() {
    // Check if HFT is active
    if (function_exists('is_plugin_active') && is_plugin_active('housefresh-tools/housefresh-tools.php')) {
        wp_enqueue_script(
            'hft-frontend-core',
            plugins_url('housefresh-tools/assets/js/hft-frontend-core.js'),
            [],
            '1.0.0',
            true
        );

        wp_localize_script('hft-frontend-core', 'hftFrontendData', [
            'restUrl' => rest_url('housefresh-tools/v1/'),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }
});
```

### Custom Affiliate Link Display

```javascript
// Custom implementation for displaying affiliate links
async function displayAffiliateLinks(productId, containerId) {
    const geo = await window.HFT_Frontend.detectUserGeo();

    const response = await fetch(
        `${hftFrontendData.restUrl}get-affiliate-link?product_id=${productId}&target_geo=${geo}`,
        {
            headers: {
                'X-WP-Nonce': hftFrontendData.nonce
            }
        }
    );

    const data = await response.json();
    const container = document.getElementById(containerId);

    if (data.links && data.links.length > 0) {
        container.innerHTML = data.links.map(link => `
            <a href="${link.url}"
               target="_blank"
               rel="nofollow noopener"
               class="affiliate-button">
                ${link.retailer_name} - ${link.price_string}
            </a>
        `).join('');

        if (data.message) {
            container.insertAdjacentHTML('beforeend',
                `<p class="geo-notice">${data.message}</p>`
            );
        }
    } else {
        container.innerHTML = '<p>No prices available for your region.</p>';
    }
}
```

---

## Creating Custom Parsers

### Implementing HFT_ParserInterface

Create a custom parser by implementing the parser interface.

```php
<?php
/**
 * Custom parser for MyRetailer.com
 * File: includes/parsers/class-hft-myretailer-parser.php
 */

// Ensure interface is loaded
if (!interface_exists('HFT_ParserInterface')) {
    require_once HFT_PLUGIN_PATH . 'includes/interfaces/interface-hft-parser.php';
}

class HFT_MyRetailer_Parser implements HFT_ParserInterface {

    /**
     * Parse product page to extract price data
     *
     * @param string $url Product URL
     * @param array $tracked_link_data Data from hft_tracked_links table
     * @return array Parsed data
     */
    public function parse(string $url, array $tracked_link_data): array {
        // Default response structure
        $result = [
            'price'         => null,
            'currency'      => null,
            'status'        => null,
            'shipping_info' => null,
            'error'         => null
        ];

        try {
            // Fetch page content
            $response = wp_remote_get($url, [
                'timeout' => 30,
                'user-agent' => 'Mozilla/5.0 (compatible; HousefreshBot/1.0)'
            ]);

            if (is_wp_error($response)) {
                $result['error'] = $response->get_error_message();
                return $result;
            }

            $html = wp_remote_retrieve_body($response);

            // Parse HTML
            $doc = new DOMDocument();
            @$doc->loadHTML($html);
            $xpath = new DOMXPath($doc);

            // Extract price (customize XPath for your retailer)
            $price_node = $xpath->query('//span[@class="product-price"]')->item(0);
            if ($price_node) {
                $price_text = $price_node->textContent;
                $result['price'] = (float) preg_replace('/[^0-9.]/', '', $price_text);
                $result['currency'] = 'USD'; // Or detect from page
            }

            // Extract status
            $status_node = $xpath->query('//span[@class="availability"]')->item(0);
            if ($status_node) {
                $result['status'] = trim($status_node->textContent);
            } else {
                $result['status'] = 'Unknown';
            }

            // Extract shipping info (optional)
            $shipping_node = $xpath->query('//div[@class="shipping-info"]')->item(0);
            if ($shipping_node) {
                $result['shipping_info'] = trim($shipping_node->textContent);
            }

        } catch (Exception $e) {
            $result['error'] = 'Parser error: ' . $e->getMessage();
        }

        return $result;
    }
}
```

### Registering Custom Parser with Factory

```php
<?php
// Hook into parser creation
add_filter('hft_parser_for_domain', function($parser, $domain, $url) {
    if ($domain === 'myretailer.com' || $domain === 'www.myretailer.com') {
        require_once __DIR__ . '/class-hft-myretailer-parser.php';
        return new HFT_MyRetailer_Parser();
    }
    return $parser;
}, 10, 3);
```

### Using the Dynamic Parser System

Alternatively, configure scrapers via the admin interface using XPath rules:

1. Go to **Housefresh Tools > Scrapers**
2. Add a new scraper with domain `myretailer.com`
3. Add XPath rules for price, status, and shipping fields
4. The `HFT_Dynamic_Parser` will automatically use your rules

---

## Integration Examples

### Example 1: Display Prices in Custom Theme

```php
<?php
/**
 * Template function to display affiliate links for a product
 * Add to your theme's functions.php
 */
function my_theme_display_affiliate_links($product_id) {
    // Ensure HFT is active
    if (!class_exists('HFT_Affiliate_Link_Generator')) {
        return '<p>Price tracking not available.</p>';
    }

    global $wpdb;
    $table = $wpdb->prefix . 'hft_tracked_links';

    $links = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE product_post_id = %d
             AND current_price IS NOT NULL
             ORDER BY current_price ASC",
            $product_id
        ),
        ARRAY_A
    );

    if (empty($links)) {
        return '<p>No pricing data available.</p>';
    }

    $output = '<div class="product-prices">';

    foreach ($links as $link) {
        $affiliate_url = HFT_Affiliate_Link_Generator::get_affiliate_link(
            $link['tracking_url'],
            $link['geo_target'] ?: 'US'
        );

        if ($affiliate_url) {
            $price = number_format((float)$link['current_price'], 2);
            $currency = $link['current_currency'] ?: 'USD';
            $status = $link['current_status'] ?: 'Check Availability';

            $output .= sprintf(
                '<a href="%s" class="price-button" target="_blank" rel="nofollow">
                    <span class="price">%s %s</span>
                    <span class="status">%s</span>
                </a>',
                esc_url($affiliate_url),
                esc_html($currency),
                esc_html($price),
                esc_html($status)
            );
        }
    }

    $output .= '</div>';

    return $output;
}

// Usage in template
echo my_theme_display_affiliate_links(get_the_ID());
```

---

### Example 2: Sync Prices to External System

```php
<?php
/**
 * Plugin: ERideHero Price Sync
 * Syncs HFT prices to external API
 */

class ERideHero_Price_Sync {

    public function __construct() {
        // Listen for price updates
        add_action('hft_price_updated', [$this, 'sync_price_to_external'], 10, 2);
    }

    public function sync_price_to_external(int $tracked_link_id, int $product_id): void {
        global $wpdb;

        // Get the updated link data
        $link = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hft_tracked_links WHERE id = %d",
                $tracked_link_id
            ),
            ARRAY_A
        );

        if (!$link || !$link['current_price']) {
            return;
        }

        // Get product meta for mapping
        $external_id = get_post_meta($product_id, '_eridehero_product_id', true);

        if (!$external_id) {
            return; // No mapping exists
        }

        // Send to external API
        $api_response = wp_remote_post('https://api.eridehero.com/products/price-update', [
            'headers' => [
                'Authorization' => 'Bearer ' . get_option('eridehero_api_key'),
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'product_id' => $external_id,
                'price' => (float) $link['current_price'],
                'currency' => $link['current_currency'],
                'status' => $link['current_status'],
                'source' => $link['parser_identifier'],
                'geo' => $link['geo_target'],
                'updated_at' => $link['last_scraped_at']
            ])
        ]);

        if (is_wp_error($api_response)) {
            error_log('ERideHero sync failed: ' . $api_response->get_error_message());
        }
    }
}

// Initialize
new ERideHero_Price_Sync();
```

---

### Example 3: Custom REST Endpoint Using HFT Data

```php
<?php
/**
 * Custom REST endpoint that combines HFT data with your plugin
 */

add_action('rest_api_init', function() {
    register_rest_route('eridehero/v1', '/products/(?P<id>\d+)/pricing', [
        'methods' => 'GET',
        'callback' => 'eridehero_get_product_pricing',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ]
        ]
    ]);
});

function eridehero_get_product_pricing(WP_REST_Request $request) {
    $product_id = $request->get_param('id');

    // Get your product data
    $product = get_post($product_id);
    if (!$product || $product->post_type !== 'eridehero_product') {
        return new WP_Error('not_found', 'Product not found', ['status' => 404]);
    }

    // Get linked HFT product ID
    $hft_product_id = get_post_meta($product_id, '_hft_linked_product', true);

    if (!$hft_product_id) {
        return new WP_REST_Response([
            'product_id' => $product_id,
            'product_name' => $product->post_title,
            'pricing' => null,
            'message' => 'No price tracking configured'
        ], 200);
    }

    // Fetch HFT pricing data
    global $wpdb;
    $links = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                tl.parser_identifier,
                tl.current_price,
                tl.current_currency,
                tl.current_status,
                tl.geo_target,
                tl.last_scraped_at,
                s.name as retailer_name
             FROM {$wpdb->prefix}hft_tracked_links tl
             LEFT JOIN {$wpdb->prefix}hft_scrapers s ON tl.scraper_id = s.id
             WHERE tl.product_post_id = %d
             AND tl.current_price IS NOT NULL
             ORDER BY tl.current_price ASC",
            $hft_product_id
        ),
        ARRAY_A
    );

    // Get price history stats
    $stats = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                MIN(ph.price) as lowest_price,
                MAX(ph.price) as highest_price,
                AVG(ph.price) as average_price
             FROM {$wpdb->prefix}hft_price_history ph
             JOIN {$wpdb->prefix}hft_tracked_links tl ON ph.tracked_link_id = tl.id
             WHERE tl.product_post_id = %d
             AND ph.scraped_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $hft_product_id
        ),
        ARRAY_A
    );

    return new WP_REST_Response([
        'product_id' => $product_id,
        'product_name' => $product->post_title,
        'pricing' => [
            'current_offers' => array_map(function($link) {
                return [
                    'retailer' => $link['retailer_name'] ?: ucfirst($link['parser_identifier']),
                    'price' => (float) $link['current_price'],
                    'currency' => $link['current_currency'],
                    'status' => $link['current_status'],
                    'geo' => $link['geo_target'],
                    'last_updated' => $link['last_scraped_at']
                ];
            }, $links),
            'stats_30d' => [
                'lowest' => $stats['lowest_price'] ? (float) $stats['lowest_price'] : null,
                'highest' => $stats['highest_price'] ? (float) $stats['highest_price'] : null,
                'average' => $stats['average_price'] ? round((float) $stats['average_price'], 2) : null
            ]
        ]
    ], 200);
}
```

---

### Example 4: Shortcode for Price Display

```php
<?php
/**
 * Shortcode to display HFT prices anywhere
 * Usage: [hft_price product_id="123" geo="US"]
 */

add_shortcode('hft_price', function($atts) {
    $atts = shortcode_atts([
        'product_id' => 0,
        'geo' => 'US',
        'show_retailer' => 'true',
        'class' => 'hft-price-display'
    ], $atts);

    $product_id = absint($atts['product_id']);
    if (!$product_id) {
        return '<!-- HFT: No product ID specified -->';
    }

    global $wpdb;
    $table = $wpdb->prefix . 'hft_tracked_links';

    // Get best price for specified GEO
    $link = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE product_post_id = %d
             AND (geo_target = %s OR geo_target IS NULL OR geo_target = '')
             AND current_price IS NOT NULL
             ORDER BY current_price ASC
             LIMIT 1",
            $product_id,
            strtoupper($atts['geo'])
        ),
        ARRAY_A
    );

    if (!$link) {
        return '<span class="' . esc_attr($atts['class']) . ' no-price">Price unavailable</span>';
    }

    $currency_symbols = [
        'USD' => '$', 'EUR' => '&euro;', 'GBP' => '&pound;',
        'CAD' => 'C$', 'AUD' => 'A$', 'JPY' => '&yen;'
    ];

    $symbol = $currency_symbols[$link['current_currency']] ?? $link['current_currency'] . ' ';
    $price = number_format((float) $link['current_price'], 2);

    // Generate affiliate link
    $affiliate_url = '#';
    if (class_exists('HFT_Affiliate_Link_Generator')) {
        $generated = HFT_Affiliate_Link_Generator::get_affiliate_link(
            $link['tracking_url'],
            $atts['geo']
        );
        if ($generated) {
            $affiliate_url = $generated;
        }
    }

    $output = sprintf(
        '<span class="%s">',
        esc_attr($atts['class'])
    );

    $output .= sprintf(
        '<a href="%s" target="_blank" rel="nofollow noopener" class="hft-price-link">%s%s</a>',
        esc_url($affiliate_url),
        $symbol,
        esc_html($price)
    );

    if ($atts['show_retailer'] === 'true') {
        $retailer = ucfirst(str_replace(['-', '_', '.'], ' ', $link['parser_identifier']));
        $output .= sprintf(
            ' <span class="hft-retailer">at %s</span>',
            esc_html($retailer)
        );
    }

    $output .= '</span>';

    return $output;
});
```

---

## Caching Strategies

### Cache Keys Used by HFT

| Cache Key Pattern                        | Duration   | Purpose                          |
|------------------------------------------|------------|----------------------------------|
| `hft_aff_links_{product_id}_{geo}`       | 1 hour     | Affiliate link responses         |
| `hft_geoip_{ip_hash}`                    | 24 hours   | GEO detection results            |
| `hft_price_chart_{product_id}_{geo}`     | 30 minutes | Price history chart data         |
| `hft_scraper_configs_active`             | 1 hour     | Active scraper configurations    |

### Invalidating HFT Caches

```php
<?php
// Clear specific product cache
function clear_hft_product_cache($product_id, $geo = null) {
    if ($geo) {
        delete_transient("hft_aff_links_{$product_id}_{$geo}");
        delete_transient("hft_price_chart_{$product_id}_{$geo}");
        wp_cache_delete("hft_aff_links_{$product_id}_{$geo}", 'hft_frontend');
        wp_cache_delete("hft_price_chart_{$product_id}_{$geo}", 'hft_frontend');
    } else {
        // Clear for all common GEOs
        $geos = ['US', 'GB', 'DE', 'FR', 'CA', 'AU'];
        foreach ($geos as $g) {
            delete_transient("hft_aff_links_{$product_id}_{$g}");
            delete_transient("hft_price_chart_{$product_id}_{$g}");
            wp_cache_delete("hft_aff_links_{$product_id}_{$g}", 'hft_frontend');
            wp_cache_delete("hft_price_chart_{$product_id}_{$g}", 'hft_frontend');
        }
    }
}

// Clear all scraper config cache
function clear_hft_scraper_cache() {
    delete_transient('hft_scraper_configs_active');
    wp_cache_delete('hft_scraper_configs_active', 'hft_frontend');
}
```

### Implementing Your Own Cache Layer

```php
<?php
/**
 * Example: Add Redis caching layer for HFT data
 */
add_filter('hft_cache_get', function($value, $key, $group) {
    if (class_exists('Redis')) {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $cached = $redis->get("hft:{$group}:{$key}");
        if ($cached !== false) {
            return unserialize($cached);
        }
    }
    return $value;
}, 10, 3);

add_action('hft_cache_set', function($key, $value, $group, $expiration) {
    if (class_exists('Redis')) {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->setex("hft:{$group}:{$key}", $expiration, serialize($value));
    }
}, 10, 4);
```

---

## Best Practices

### 1. Check Plugin Availability

Always verify HFT is active before using its features:

```php
<?php
function is_hft_active(): bool {
    return defined('HFT_VERSION') && class_exists('HFT_Loader');
}

// Or check for specific features
function hft_has_affiliate_generator(): bool {
    return class_exists('HFT_Affiliate_Link_Generator');
}
```

### 2. Use Hooks Instead of Direct Database Access

Prefer hooks for data synchronization:

```php
<?php
// Good: React to events
add_action('hft_price_updated', 'my_sync_price', 10, 2);

// Avoid: Polling the database
// $prices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hft_tracked_links");
```

### 3. Respect Rate Limits

When building features that call HFT REST endpoints:

```php
<?php
function my_fetch_with_rate_limit($url, $max_retries = 3) {
    for ($i = 0; $i < $max_retries; $i++) {
        $response = wp_remote_get($url);
        $code = wp_remote_retrieve_response_code($response);

        if ($code === 429) {
            $retry_after = wp_remote_retrieve_header($response, 'retry-after') ?: 60;
            sleep(min((int) $retry_after, 120));
            continue;
        }

        return $response;
    }

    return new WP_Error('rate_limited', 'Max retries exceeded');
}
```

### 4. Handle Missing Data Gracefully

```php
<?php
function get_product_price_safe($product_id, $default = 'Price not available') {
    if (!is_hft_active()) {
        return $default;
    }

    global $wpdb;
    $price = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT current_price FROM {$wpdb->prefix}hft_tracked_links
             WHERE product_post_id = %d AND current_price IS NOT NULL
             ORDER BY current_price ASC LIMIT 1",
            $product_id
        )
    );

    return $price ? '$' . number_format((float) $price, 2) : $default;
}
```

### 5. Use Proper Capability Checks

```php
<?php
// For admin-only operations
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

// For product-specific operations
if (!current_user_can('edit_post', $product_id)) {
    return new WP_Error('unauthorized', 'Cannot access this product');
}
```

### 6. Localize External API Calls

```php
<?php
// Register your scripts with HFT data
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('my-affiliate-display', ...);

    wp_localize_script('my-affiliate-display', 'myAffiliateConfig', [
        'hftRestUrl' => rest_url('housefresh-tools/v1/'),
        'nonce' => wp_create_nonce('wp_rest'),
        'defaultGeo' => 'US',
        'cacheEnabled' => true
    ]);
});
```

---

## Plugin Settings Reference

### Option: hft_settings

```php
<?php
$settings = get_option('hft_settings', []);

// Available keys:
// 'amazon_credentials'    - Per-region Creators API credentials:
//                           ['NA' => ['credential_id' => '...', 'credential_secret' => '...'],
//                            'EU' => ['credential_id' => '...', 'credential_secret' => '...'],
//                            'FE' => ['credential_id' => '...', 'credential_secret' => '...']]
// 'amazon_associate_tags' - Array of [{geo: 'US', tag: 'yourtag-20'}, ...]
// 'scrape_interval'       - 'five_minutes', 'hourly', 'daily', etc.
// 'products_per_batch'    - Number of links to process per cron run
// 'ipinfo_api_token'      - IPInfo.io API token for GEO detection
```

### Reading Settings Safely

```php
<?php
function get_hft_setting($key, $default = null) {
    $settings = get_option('hft_settings', []);
    return $settings[$key] ?? $default;
}

// Example usage
$amazon_tags = get_hft_setting('amazon_associate_tags', []);
$batch_size = get_hft_setting('products_per_batch', 10);
```

---

## Troubleshooting

### Common Issues

**1. REST API returns 403/401**
- Check if permalink structure is set (not "Plain")
- Verify WordPress REST API is accessible
- Check for security plugins blocking API

**2. Prices not updating**
- Verify cron is running: `wp cron event list`
- Check scraper logs: Housefresh Tools > Scraper Logs
- Ensure scraper rules are configured correctly

**3. GEO detection failing**
- Verify IPInfo API token is set
- Check for proxy/CDN stripping IP headers
- Test with explicit `test_ip` parameter

**4. Cache not invalidating**
- Clear object cache if using Redis/Memcached
- Delete transients: `wp transient delete --all`
- Check `hft_price_updated` hook is firing

### Debug Mode

```php
<?php
// Enable HFT debug logging
add_filter('hft_debug_mode', '__return_true');

// Or in wp-config.php
define('HFT_DEBUG', true);
```

---

## Version Compatibility

| HFT Version | WordPress | PHP     | ACF     |
|-------------|-----------|---------|---------|
| 1.0.x       | 5.0+      | 7.4+    | 5.0+    |

---

## Support & Resources

- **Plugin Repository:** Check CLAUDE.md for development guidelines
- **Database Schema:** See HFT_Db class in `/includes/class-hft-db.php`
- **REST API:** See HFT_REST_Controller in `/includes/class-hft-rest-controller.php`
- **Parser Interface:** See `/includes/interfaces/interface-hft-parser.php`

---

*This documentation is for Housefresh Tools v1.0. Last updated: December 2024.*
