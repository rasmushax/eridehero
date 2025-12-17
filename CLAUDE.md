# ERideHero Platform Rebuild

## Project Overview

ERideHero is a data-driven electric mobility review platform (scooters, e-bikes, EUCs, skateboards, hoverboards) with ~50,000 monthly visitors. We're rebuilding from Oxygen Builder to a custom WordPress theme with Tailwind CSS, and restructuring the plugin architecture for maintainability.

**Goal**: Modern, maintainable codebase with clean separation of concerns, while preserving all existing functionality and data.

**Timeline**: ~3 weeks with focused, systematic implementation.

---

## Current Technical Stack

| Component | Current | Target |
|-----------|---------|--------|
| Theme | Oxygen Builder | Custom theme + Tailwind CSS |
| Plugins | my-custom-functionality-master + product-functionality | erh-core (unified) |
| Price Data | HFT Plugin (Housefresh Tools) | HFT Plugin (integrated) |
| Fields | ACF Pro | ACF Pro (keep) |
| Tables | Custom tables | Same (keep schema) |
| Caching | LiteSpeed Cache | LiteSpeed + Cloudflare |

## Reference Code

The current functionality is split across two plugins in `reference/`:

- **`reference/my-custom-functionality-master/`** - Shortcodes, blocks, general utilities
- **`reference/product-functionality/`** - Core product logic: pricing, reviews, price trackers, user system, cron jobs

When looking for existing implementations, check both folders. The new `erh-core` plugin will unify all functionality.

---

## Data Architecture

### Pricing System: HFT Plugin (Housefresh Tools)

The site uses a custom price crawling plugin (HFT) instead of Content Egg. This plugin provides:
- Database-driven scraper configuration (add retailers via admin, no code)
- XPath-based parsing rules per domain
- Geo-targeted affiliate links with IPInfo integration
- REST API for price data
- Automatic price history tracking

### HFT Database Tables

#### `wp_hft_tracked_links` - Primary Price Data
```sql
CREATE TABLE wp_hft_tracked_links (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_post_id       BIGINT UNSIGNED NOT NULL,     -- Links to products CPT
    tracking_url          TEXT NOT NULL,                 -- URL or ASIN
    parser_identifier     VARCHAR(100) NOT NULL,         -- 'amazon' or domain
    scraper_id            BIGINT UNSIGNED NULL,          -- FK to hft_scrapers
    geo_target            TEXT NULL,                     -- 'US', 'GB', etc.
    affiliate_link_override TEXT NULL,                   -- Manual override
    current_price         DECIMAL(10,2) NULL,
    current_currency      VARCHAR(10) NULL,
    current_status        VARCHAR(50) NULL,              -- 'In Stock', 'Out of Stock'
    current_shipping_info TEXT NULL,
    last_scraped_at       DATETIME NULL,
    last_scrape_successful BOOLEAN NULL,
    consecutive_failures  INT UNSIGNED DEFAULT 0,
    last_error_message    TEXT NULL,
    created_at            DATETIME,
    updated_at            DATETIME,
    
    INDEX product_post_id (product_post_id),
    INDEX idx_product_geo (product_post_id, geo_target(20))
);
```

#### `wp_hft_price_history` - Price History
```sql
CREATE TABLE wp_hft_price_history (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracked_link_id BIGINT UNSIGNED NOT NULL,
    price           DECIMAL(10,2) NOT NULL,
    currency        VARCHAR(10) NOT NULL,
    status          VARCHAR(50) NOT NULL,
    scraped_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX tracked_link_id (tracked_link_id),
    INDEX idx_link_scraped (tracked_link_id, scraped_at)
);
```

#### `wp_hft_scrapers` - Scraper Configs
```sql
CREATE TABLE wp_hft_scrapers (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain                  VARCHAR(191) NOT NULL UNIQUE,
    name                    VARCHAR(255) NOT NULL,
    currency                VARCHAR(3) DEFAULT 'USD',
    geos                    TEXT NULL,                      -- Comma-separated
    affiliate_link_format   TEXT NULL,                      -- Template with {URL}, {URLE}, {ID}
    is_active               BOOLEAN DEFAULT 1,
    use_base_parser         BOOLEAN DEFAULT 1,
    -- ... more config fields
);
```

#### `wp_hft_scraper_rules` - XPath Selectors
```sql
CREATE TABLE wp_hft_scraper_rules (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scraper_id      BIGINT UNSIGNED NOT NULL,
    field_type      ENUM('price', 'status', 'shipping') NOT NULL,
    xpath_selector  TEXT NOT NULL,
    attribute       VARCHAR(100) NULL,
    post_processing TEXT NULL,
    is_active       BOOLEAN DEFAULT 1
);
```

### HFT Integration Points

**ERH-Core Integration Filters** (in `class-core.php`):
```php
// Use ERH 'products' CPT instead of HFT's 'hf_product'
add_filter('hft_product_post_types', function() {
    return ['products'];
});

// Disable HFT's built-in CPT registration
add_filter('hft_register_product_cpt', '__return_false');
```

**PHP Functions** (from HFT plugin):
```php
// Check if HFT is active
defined('HFT_VERSION') && class_exists('HFT_Loader')

// Get affiliate links with geo support
HFT_Affiliate_Link_Generator::get_links_for_product($product_id, $geo)

// Hooks
do_action('hft_price_updated', $tracked_link_id, $new_price);
do_action('hft_link_status_changed', $tracked_link_id, $old_status, $new_status);
apply_filters('hft_best_price', $best_price_data, $product_post_id);
```

**REST API Endpoints**:
```
GET  /wp-json/housefresh-tools/v1/product/{id}/prices
GET  /wp-json/housefresh-tools/v1/product/{id}/affiliate-links?geo=US
GET  /wp-json/housefresh-tools/v1/product/{id}/price-chart?period=30d
```

### ERH Custom Tables (Keep These)

### Custom Post Types

#### `products` (existing - keep as-is)
- Single CPT for all product types
- `product_type` stored as ACF field (values: Electric Scooter, Electric Bike, Electric Skateboard, Electric Unicycle, Hoverboard)

#### `review` (existing - keep as-is)  
- User-submitted reviews linked to products
- Meta: `product` (product ID), `score` (1-5), `text`, `review_image`
- Status: `pending` (needs moderation) or `publish`

### ERH Database Tables

#### `wp_product_data` - Finder Tool Cache
```sql
CREATE TABLE wp_product_data (
    id bigint(20) unsigned AUTO_INCREMENT,
    product_id bigint(20) unsigned NOT NULL,
    product_type varchar(50) NOT NULL,
    name varchar(255) NOT NULL,
    specs longtext,                    -- Serialized ACF fields + computed values
    rating decimal(3,1),
    popularity_score int(8),
    permalink varchar(255),
    image_url varchar(255),
    price_history longtext,            -- Geo-keyed pricing data (see below)
    last_updated datetime,
    PRIMARY KEY (id),
    UNIQUE KEY product_id (product_id),
    KEY product_type (product_type),
    KEY popularity_score (popularity_score)
);
```

**Note**: The `price`, `instock`, and `bestlink` columns were removed in v1.2.1. All pricing data is now stored per-geo in the serialized `price_history` field.

**`price_history` Structure** (geo-keyed):
```php
[
    'US' => [
        'current_price' => 499.99,
        'currency'      => 'USD',
        'instock'       => true,
        'retailer'      => 'Amazon',
        'bestlink'      => 'https://...',
        // Averages
        'avg_3m'   => 549.99,
        'avg_6m'   => 579.99,
        'avg_12m'  => 599.99,
        'avg_all'  => 589.99,
        // Lows
        'low_3m'   => 449.99,
        'low_6m'   => 429.99,
        'low_12m'  => 399.99,
        'low_all'  => 379.99,
        // Highs
        'high_3m'  => 649.99,
        'high_6m'  => 699.99,
        'high_12m' => 749.99,
        'high_all' => 799.99,
        'updated_at' => '2025-12-17 10:00:00',
    ],
    'GB' => [...],  // Only present if genuine GB price exists
    'DE' => [...],
]
```

**Supported Geos**: US, GB, DE, CA, AU

#### `wp_product_daily_prices` - Price History (per geo/currency)
```sql
CREATE TABLE wp_product_daily_prices (
    id bigint(20) AUTO_INCREMENT,
    product_id bigint(20) NOT NULL,
    price decimal(10,2) NOT NULL,
    currency varchar(10) NOT NULL DEFAULT 'USD',
    domain varchar(255) NOT NULL,
    geo varchar(10) NOT NULL DEFAULT 'US',
    date date NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY product_date_geo_currency (product_id, date, geo, currency),
    KEY product_id (product_id),
    KEY date (date),
    KEY geo (geo),
    KEY currency (currency)
);
```

**Note**: Updated in v1.1.0 to support multiple geos and currencies per product per day.

#### `wp_price_trackers` - User Price Alerts
```sql
CREATE TABLE wp_price_trackers (
    id bigint(20) AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    product_id bigint(20) NOT NULL,
    start_price decimal(10,2),
    current_price decimal(10,2),
    target_price decimal(10,2),        -- NULL if using price_drop
    price_drop decimal(10,2),          -- NULL if using target_price
    last_notified_price decimal(10,2),
    created_at datetime,
    updated_at datetime,
    last_notification_time datetime,
    PRIMARY KEY (id)
);
```

#### `wp_product_views` - View Tracking
```sql
CREATE TABLE wp_product_views (
    id bigint(20) AUTO_INCREMENT,
    product_id bigint(20) NOT NULL,
    ip_hash varchar(64) NOT NULL,      -- SHA256 of IP
    user_agent varchar(255),
    view_date datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);
```

---

## Core Functionality Map

### Pricing System (HFT Integration)

| Class | Method | Description |
|-------|--------|-------------|
| `PriceFetcher` | `get_prices($product_id, $geo)` | Gets prices from HFT `tracked_links` table |
| `PriceFetcher` | `get_best_price($product_id, $geo)` | Returns lowest in-stock price for geo |
| `RetailerLogos` | `get_logo_by_id($scraper_id)` | Gets retailer logo from HFT scraper |
| `DealsFinder` | `get_deals($type, $geo, $period)` | Products below period average |

**HFT hooks to listen for**:
```php
// React to price updates from HFT scraper
add_action('hft_price_updated', function($tracked_link_id, $new_price) {
    // Invalidate caches, trigger user notifications, etc.
}, 10, 2);

// Filter best price selection
add_filter('hft_best_price', function($best_price, $product_id) {
    // Custom sorting logic if needed
    return $best_price;
}, 10, 2);
```

### Geo-Aware Pricing Architecture

The pricing system is fully geo-aware, showing users prices in their local currency when available.

**Data Flow**:
1. **HFT Plugin** scrapes prices with `geo_target` per retailer
2. **CacheRebuildJob** builds `price_history` per geo (only genuine prices, no US fallbacks)
3. **JSON Generators** output geo-keyed pricing data
4. **Frontend JS** detects user geo via IPInfo, displays appropriate prices

**Geo Validation Logic** (`CacheRebuildJob::is_genuine_geo_price()`):
- Price is for requested geo if `price_geo === $geo`
- Global prices (null geo) default to US only
- Non-US geos require matching currency (e.g., GB requires GBP)
- US fallbacks are NOT stored under other geo keys

**Frontend Service** (`geo-price.js`):
```javascript
import { getUserGeo, formatPrice } from './services/geo-price.js';

// Detect user's geo (cached in localStorage for 24h)
const { geo, currency } = await getUserGeo();

// Get price for user's geo, fallback to US
const prices = product.pricing || {};
const price = prices[geo]?.current_price ?? prices['US']?.current_price;
const displayCurrency = prices[geo] ? currency : 'USD';

// Format with proper symbol
formatPrice(price, displayCurrency); // "$499" or "£399"
```

**Currency Mapping**:
| Geo | Currency | Symbol |
|-----|----------|--------|
| US | USD | $ |
| GB | GBP | £ |
| DE | EUR | € |
| CA | CAD | CA$ |
| AU | AUD | A$ |

### Generated JSON Files

**`comparison_products.json`** (for H2H widgets):
```json
{
    "id": 123,
    "name": "Segway Ninebot Max",
    "category": "escooter",
    "thumbnail": "...",
    "prices": { "US": 799, "GB": 649 },
    "url": "...",
    "popularity": 85
}
```

**`finder_escooter.json`** etc (for Finder tool):
```json
{
    "id": 123,
    "name": "Segway Ninebot Max",
    "category": "escooter",
    "url": "...",
    "thumbnail": "...",
    "rating": 4.5,
    "popularity": 85,
    "pricing": {
        "US": {
            "current_price": 799,
            "currency": "USD",
            "instock": true,
            "bestlink": "...",
            "avg_3m": 825, "avg_6m": 850, "avg_12m": 875, "avg_all": 860,
            "low_3m": 749, "low_6m": 699, "low_12m": 649, "low_all": 599,
            "high_3m": 899, "high_6m": 999, "high_12m": 1099, "high_all": 1199
        },
        "GB": { ... }
    },
    "specs": { ... }
}
```

**`search_items.json`** (for search autocomplete - no pricing)

### User System (REST API - Implemented)

**Authentication** (`class-auth-handler.php`):
```
POST /erh/v1/auth/login          - Email/password login
POST /erh/v1/auth/register       - Registration with DNS validation
POST /erh/v1/auth/forgot-password - Password reset request
POST /erh/v1/auth/reset-password - Password reset completion
POST /erh/v1/auth/logout         - Logout
GET  /erh/v1/auth/status         - Check auth status
```

**Social Login** (`class-social-auth.php`):
```
GET /erh/v1/auth/social/{provider}          - Initiate OAuth (google/facebook/reddit)
GET /erh/v1/auth/social/{provider}/callback - OAuth callback
GET /erh/v1/auth/social/providers           - List available providers
```

**User Features** (`class-user-preferences.php`, `class-user-tracker.php`):
```
GET/PUT /erh/v1/user/preferences        - Email preferences
PUT     /erh/v1/user/email              - Change email
PUT     /erh/v1/user/password           - Change password
GET     /erh/v1/user/trackers           - List user's price trackers
DELETE  /erh/v1/user/trackers/{id}      - Delete tracker
GET/POST/DELETE /erh/v1/products/{id}/tracker - Tracker CRUD
```

**Webhooks**:
```
POST /erh/v1/webhooks/mailchimp - Mailchimp unsubscribe sync
```

**User Meta Keys** (defined in `UserRepository`):
- `price_trackers_emails` - Boolean, receive price drop alerts
- `sales_roundup_emails` - Boolean, receive deals digest
- `sales_roundup_frequency` - `weekly`, `bi-weekly`, or `monthly`
- `newsletter_subscription` - Boolean, general newsletter
- `erh_google_id` - Google OAuth user ID
- `erh_facebook_id` - Facebook OAuth user ID
- `erh_reddit_id` - Reddit OAuth user ID
- `erh_preferences_set` - Boolean, has user set preferences
- `last_deals_email_sent` - Datetime, for frequency throttling
- `registration_ip` - For security

### Price Tracker System

| File | Function | Description |
|------|----------|-------------|
| `price_tracker.php` | `pf_check_price_data()` | AJAX: Check if product has price data |
| `price_tracker.php` | `pf_check_price_tracker()` | AJAX: Get user's tracker for product |
| `price_tracker.php` | `pf_set_price_tracker()` | AJAX: Create/update tracker |
| `price_tracker.php` | `pf_delete_price_tracker()` | AJAX: Delete tracker |
| `price-tracker-cron.php` | `Price_Tracker_Cron::run()` | Cron: Check all trackers, send notifications |
| `trackers.php` | Template | User's tracker dashboard |

### Review System

| File | Function | Description |
|------|----------|-------------|
| `submit_review.php` | AJAX handler | Submit review with image upload |
| `user_review_status.php` | AJAX handler | Check if user already reviewed product |
| `reviews.php` (account) | Template | User's submitted reviews |
| `plugin.php` | `getReviews($product_id)` | Get reviews with ratings distribution |

### Email System

| File | Function | Description |
|------|----------|-------------|
| `email-template.php` | `get_email_template($content)` | Wraps content in branded HTML email |
| `deals-email.php` | `Deals_Email::send_deals_email()` | Weekly/bi-weekly/monthly deals digest |
| `price-tracker-cron.php` | Notification emails | Price drop alerts |

**Email helper methods** (in `Product_Functionality` class):
- `generate_email_paragraph($text)`
- `generate_email_button($url, $text)`
- `generate_email_link($url, $text, $style)`
- `send_html_email($to, $subject, $content)`

### Cron Jobs

| Hook | Schedule | Class | Description |
|------|----------|-------|-------------|
| `erh_cron_cache_rebuild` | Every 2 hours | `CacheRebuildJob` | Rebuild wp_product_data with geo-keyed pricing |
| `erh_cron_finder_json` | Every 2 hours | `FinderJsonJob` | Generate finder_*.json files per product type |
| `erh_cron_comparison_json` | Every 2 hours | `ComparisonJsonJob` | Generate comparison_products.json |
| `erh_cron_search_json` | Every 2 hours | `SearchJsonJob` | Generate search_items.json |
| `erh_cron_daily_price` | Daily | `DailyPriceJob` | Record daily price snapshots per geo |
| `erh_cron_price_tracker` | Daily | `NotificationJob` | Check price alerts, send notifications |

**Cron Manager** (`class-cron-manager.php`) handles:
- Central job registration
- Lock mechanism to prevent concurrent runs
- Run time tracking for admin display
- Manual "Run Now" capability

### Search System

- `search.php` generates `/wp-content/uploads/search_items.json`
- Contains: `id`, `title`, `url`, `type`, `thumbnail`, `product_type`
- Frontend loads JSON and filters client-side for instant search

### View Tracking

- AJAX endpoint tracks unique views per product per IP per day
- Bot detection via user agent
- Used in popularity scoring
- 90-day retention with probabilistic cleanup

### Popularity Scoring

Calculated in `product_data_cron_job()`:
```
+5 points: In stock
+rating value: Average user rating
+2 per review: Review count
+2 per tracker: Users tracking price
+10 points: Released current year
+5 points: Released last year
+log(views) * 3.5: 30-day view count (logarithmic)
```

---

## ACF Field Structure

### Electric Scooter Fields (partial)
```
- product_type: "Electric Scooter"
- big_thumbnail: Image ID
- brand: Text
- release_year: Number
- manufacturer_top_speed: Number (mph)
- manufacturer_range: Number (miles)
- weight: Number (lbs)
- max_weight_capacity: Number (lbs)
- battery_capacity: Number (Wh)
- nominal_motor_wattage: Number
- peak_motor_wattage: Number
- tested_range_regular: Number
- tested_range_fast: Number
- acceleration:_0-15_mph: Number (seconds)
- acceleration:_0-20_mph: Number (seconds)
- ... many more
- coupon: Repeater [website, code, discount_type, discount_value]
```

### Electric Bike Fields
Nested under `e-bikes` group:
```
- e-bikes.motor.power_nominal
- e-bikes.motor.power_peak  
- e-bikes.motor.torque
- e-bikes.battery.capacity
- e-bikes.battery.range_claimed
- ... etc
```

**Important**: E-bike fields are nested differently than scooter fields. The `product_data_cron_job` handles this with special logic.

### ACF Restructuring (Post-Launch)

The current scooter fields are flat (~100+ fields at root level), while e-bikes are properly nested. A restructuring plan exists in `ACF_RESTRUCTURE_PLAN.md` to:

1. Create nested groups for e-scooters, e-skateboards, EUCs, hoverboards
2. Match the clean structure of e-bikes
3. Improve admin UX with collapsible sections

**This is Phase 10** - do it AFTER the main theme rebuild is stable. The flat fields work fine, this is a polish/UX improvement.

---

## Affiliate Link Networks

The `extractDomain()` function handles these networks:

| Network | URL Pattern | Parameter |
|---------|-------------|-----------|
| ShareASale | `shareasale.com` | `urllink` |
| Avantlink | `www.avantlink.com` | `url` |
| CJ | `www.tkqlhce.com` | `url` |
| Awin | `www.awin1.com` | `ued` |
| PartnerBoost | `app.partnerboost.com` | `url` |
| Impact | `*.pxf.io` | `u` |
| Sovrn | `go.sjv.io` | `u` |

---

## Target Architecture

### Plugin: `erh-core`

```
erh-core/
├── erh-core.php                          # Bootstrap, constants, autoloader
├── composer.json                         # PSR-4 autoloading
├── includes/
│   ├── class-erh-core.php               # Main class, hook registration
│   │
│   ├── post-types/
│   │   ├── class-product.php            # Products CPT registration
│   │   └── class-review.php             # Review CPT registration
│   │
│   ├── database/
│   │   ├── class-schema.php             # Table creation/migration
│   │   ├── class-product-cache.php      # wp_product_data operations
│   │   ├── class-price-history.php      # wp_product_daily_prices operations
│   │   ├── class-price-tracker.php      # wp_price_trackers operations
│   │   └── class-view-tracker.php       # wp_product_views operations
│   │
│   ├── pricing/
│   │   ├── class-price-fetcher.php      # Get prices from HFT tables
│   │   ├── class-affiliate-resolver.php # URL → domain resolution
│   │   ├── class-retailer-registry.php  # Domain → name/logo mapping
│   │   └── class-deals-finder.php       # Find products below avg price
│   │
│   ├── cron/
│   │   ├── class-cron-manager.php       # Central cron registration
│   │   ├── class-price-update-job.php   # Daily price recording
│   │   ├── class-cache-rebuild-job.php  # Finder cache rebuild
│   │   ├── class-search-json-job.php    # Search JSON generation
│   │   └── class-notification-job.php   # Price alert checks
│   │
│   ├── user/
│   │   ├── class-rate-limiter.php       # Transient-based rate limiting
│   │   ├── class-user-repository.php    # User data access & meta constants
│   │   ├── class-auth-handler.php       # Login, register, password reset
│   │   ├── class-user-preferences.php   # Email settings, profile updates
│   │   ├── class-user-tracker.php       # Price tracker CRUD for users
│   │   ├── interface-oauth-provider.php # OAuth provider contract
│   │   ├── class-oauth-google.php       # Google OAuth 2.0
│   │   ├── class-oauth-facebook.php     # Facebook OAuth 2.0
│   │   ├── class-oauth-reddit.php       # Reddit OAuth 2.0
│   │   └── class-social-auth.php        # OAuth orchestrator
│   │
│   ├── reviews/
│   │   ├── class-review-handler.php     # Submit, moderate reviews
│   │   └── class-review-query.php       # Get reviews, calculate ratings
│   │
│   ├── email/
│   │   ├── class-mailchimp-sync.php     # Mailchimp API + webhook (implemented)
│   │   ├── class-email-template.php     # HTML email wrapper
│   │   ├── class-email-sender.php       # wp_mail wrapper
│   │   ├── class-price-alert-email.php  # Price drop notifications
│   │   └── class-deals-digest-email.php # Weekly deals email
│   │
│   ├── api/
│   │   ├── class-rest-products.php      # REST API for finder tool
│   │   ├── class-rest-reviews.php       # REST API for reviews
│   │   └── class-ajax-handler.php       # Legacy AJAX endpoints
│   │
│   ├── geo/
│   │   └── class-geo-pricing.php        # IPInfo integration (future)
│   │
│   └── admin/
│       ├── class-settings-page.php      # Settings > ERideHero (implemented)
│       ├── class-admin-menu.php         # Admin menu pages
│       ├── class-price-tracker-admin.php # Tracker management
│       └── class-product-metabox.php    # Product edit screen additions
│
├── shortcodes/                          # Migrate existing shortcodes
│   ├── class-shortcode-base.php         # Abstract base class
│   ├── accordion/
│   ├── buying-guide-table/
│   ├── top3/
│   ├── toppicks/
│   └── ... (migrate all existing)
│
└── assets/
    ├── js/
    │   ├── view-tracker.js
    │   └── ... (migrate existing)
    └── css/
        └── admin-style.css
```

### Theme: `erh-theme`

```
erh-theme/
├── assets/
│   ├── css/
│   │   ├── src/
│   │   │   └── main.css               # Tailwind directives + custom
│   │   └── dist/
│   │       └── style.css              # Compiled (gitignore)
│   └── js/
│       ├── services/
│       │   └── geo-price.js           # Geo detection, price formatting, caching
│       ├── components/
│       │   ├── header.js              # Header interactions
│       │   ├── search.js              # Search functionality
│       │   ├── finder.js              # Finder tool (geo-aware)
│       │   ├── comparison.js          # Comparison tool (geo-aware)
│       │   ├── deals.js               # Deals section (geo-aware)
│       │   ├── price-tracker.js       # Price tracker modal
│       │   └── reviews.js             # Review submission
│       └── dist/
│           └── main.min.js            # Bundled (gitignore)
│
├── template-parts/
│   ├── header.php                     # Main header
│   ├── footer.php                     # Main footer
│   ├── product/
│   │   ├── card.php                   # Product card (grid/list)
│   │   ├── specs-table.php            # Specifications table
│   │   ├── price-box.php              # Price display with offers
│   │   ├── offers-modal.php           # All offers modal
│   │   └── related.php                # Related products
│   ├── finder/
│   │   ├── filters.php                # Filter sidebar
│   │   ├── results.php                # Results grid
│   │   └── pagination.php
│   ├── review/
│   │   ├── form.php                   # Review submission form
│   │   ├── list.php                   # Reviews list
│   │   └── item.php                   # Single review
│   ├── account/
│   │   ├── nav.php                    # Account navigation
│   │   ├── reviews.php                # User's reviews
│   │   ├── trackers.php               # User's price trackers
│   │   └── settings.php               # User settings
│   └── components/
│       ├── deals-list.php
│       ├── comparison-table.php
│       └── newsletter-form.php
│
├── templates/
│   ├── single-products.php            # Single product page
│   ├── archive-products.php           # Products archive
│   ├── page-finder.php                # Finder tool
│   ├── page-comparison.php            # Comparison tool
│   ├── page-deals.php                 # Deals page
│   └── page-account.php               # User account
│
├── inc/
│   ├── enqueue.php                    # Asset registration
│   ├── theme-setup.php                # Theme supports, menus
│   ├── template-functions.php         # Helper functions
│   └── acf-fields.php                 # ACF field exports (optional)
│
├── functions.php                      # Minimal, includes inc/*
├── style.css                          # WP theme header only
├── tailwind.config.js
├── postcss.config.js
└── package.json
```

---

## Tailwind Configuration

```js
// tailwind.config.js
module.exports = {
  content: [
    './**/*.php',
    './assets/js/src/**/*.js'
  ],
  theme: {
    extend: {
      colors: {
        'clmain': '#5e2ced',
        'cldark': '#21273a',
        'clow': '#f9fafe',
        'clbody': '#3d4668',
        'clgreen': '#2ea961',
        'clred': '#dc3545',
        'clyellow': '#ffc107',
        'clorange': '#fd7e14',
      },
      fontFamily: {
        'poppins': ['Poppins', 'sans-serif'],
      },
      boxShadow: {
        'header': '0px 4px 24px 0px rgba(14, 0, 40, 0.12)',
        'card': '0px 2px 8px 0px rgba(14, 0, 40, 0.08)',
        'dropdown': '0px 4px 24px 0px rgba(14, 0, 40, 0.12)',
      }
    }
  },
  plugins: [],
}
```

---

## Code Standards

### PHP
- PSR-4 autoloading via Composer
- Type hints on method parameters and returns
- DocBlocks on all public methods
- No global functions except in bootstrap
- Dependency injection over globals/singletons
- Prepared statements for ALL database queries
- Nonce verification on ALL AJAX handlers
- Capability checks where appropriate

### JavaScript
- ES6+ syntax
- No jQuery dependency (except DataTables if needed)
- Event delegation where possible
- Async/await for AJAX calls

### CSS/Tailwind
- Utility-first, custom CSS only when necessary
- Custom CSS in `@layer components` or `@layer utilities`
- No `!important` except edge cases
- Mobile-first responsive approach

---

## Migration Strategy

### Phase 1: Plugin Scaffold (Days 1-2)
1. Create plugin directory structure
2. Set up Composer autoloading
3. Create main plugin class with hook registration
4. Migrate CPT registrations
5. Create database schema class

### Phase 2: Core Classes (Days 3-6)
1. `PriceFetcher` - abstract Content Egg dependency
2. `AffiliateResolver` - port `extractDomain()` logic
3. `RetailerRegistry` - port domain/logo mappings
4. `ProductCache` - wp_product_data operations
5. `PriceHistory` - wp_product_daily_prices operations
6. `DealsFinder` - port `getDeals()` logic

### Phase 3: User System (Days 7-8)
1. `AuthHandler` - login, register, password reset
2. `UserPreferences` - email settings
3. `UserTracker` - price tracker CRUD
4. `ReviewHandler` - review submission

### Phase 4: Cron & Email (Days 9-10)
1. `CronManager` - central registration
2. All job classes
3. Email template and sender classes
4. Price alert and deals digest emails

### Phase 5: Theme Scaffold (Days 11-12)
1. Theme structure with Tailwind
2. Build process (npm scripts)
3. Header (already built!) and footer
4. Basic templates

### Phase 6: Templates (Days 13-16)
1. Single product template
2. Finder tool page
3. Account pages
4. Component partials

### Phase 7: Shortcodes & Polish (Days 17-19)
1. Migrate shortcodes systematically
2. JavaScript bundling
3. Testing and bug fixes

### Phase 8: Launch (Days 20-21)
1. Final QA
2. Performance testing
3. Switchover plan

---

## Key Functions to Preserve

These functions are called throughout the codebase. Ensure compatibility:

```php
// Pricing
getPrices($product_id)           // Returns array of offers
afflink($url)                    // Adds tracking to affiliate URL
extractDomain($url)              // Resolves affiliate URL to domain
prettydomain($domain)            // Domain to display name
getShopImg($domain, $class)      // Retailer logo HTML

// Reviews  
getReviews($product_id)          // Returns reviews + ratings distribution
truncate_review($text, $length)  // Truncate with "show more"

// Deals
getDeals($type, $threshold)      // Products below avg price
splitPrice($price)               // Split into whole/fractional

// Utilities
time_elapsed_string($datetime)   // "2 hours ago" format
format_spec_value($spec)         // Format spec for display
esc_smart($content)              // Smart HTML entity handling

// Email
get_email_template($content)     // Wrap in branded template
```

---

## What NOT to Change

1. **Database schemas** - Data already exists, migration is risky
2. **ACF field names/structure** - Would require content migration
3. **URL structure** - SEO impact
4. **User meta keys** - Existing users have data
5. **Core business logic** - Price tracking, notifications work fine

---

## HFT Plugin Integration

The HFT (Housefresh Tools) plugin handles all price data. ERH-Core reads from it.

### Key Integration Points

```php
// Check if HFT is active
if (!defined('HFT_VERSION') || !class_exists('HFT_Loader')) {
    // Fallback or error handling
}

// Get prices for a product (with optional geo targeting)
$links = $wpdb->get_results($wpdb->prepare(
    "SELECT tl.*, s.name as retailer_name, s.affiliate_link_format
     FROM {$wpdb->prefix}hft_tracked_links tl
     LEFT JOIN {$wpdb->prefix}hft_scrapers s ON tl.scraper_id = s.id
     WHERE tl.product_post_id = %d 
     AND tl.current_price IS NOT NULL
     AND tl.current_price > 0
     ORDER BY 
       CASE WHEN tl.current_status = 'In Stock' THEN 0 ELSE 1 END,
       tl.current_price ASC",
    $product_id
));

// Use HFT REST API for frontend
// GET /wp-json/housefresh-tools/v1/product/{id}/prices
// GET /wp-json/housefresh-tools/v1/product/{id}/affiliate-links?geo=US

// Listen for price updates
add_action('hft_price_updated', function($tracked_link_id, $new_price) {
    // Invalidate product cache, check price alerts, etc.
}, 10, 2);
```

### Affiliate Link Generation

HFT handles affiliate link formatting via `hft_scrapers.affiliate_link_format`:
- `{URL}` - Original URL
- `{URLE}` - URL-encoded original URL  
- `{ID}` - Product/ASIN ID

No need for `afflink()` function - HFT generates final affiliate URLs.

---

## Testing Checklist

Before switching themes:

- [ ] All product pages render correctly
- [ ] Finder tool filters and sorts work
- [ ] Price tracker create/update/delete works
- [ ] User registration and login work
- [ ] Password reset flow works
- [ ] Review submission works
- [ ] Email notifications send correctly
- [ ] Deals page shows correct data
- [ ] Search returns expected results
- [ ] Mobile responsive on all pages
- [ ] Cron jobs execute without errors
- [ ] No PHP errors in error log
- [ ] Page speed acceptable (< 3s LCP)

---

## Claude Code Instructions

When working on this project:

1. **Read this file first** before making changes
2. **Preserve existing function signatures** when refactoring
3. **Use prepared statements** for all database queries
4. **Add nonce verification** to all AJAX handlers
5. **Test with sample data** before marking complete
6. **Document breaking changes** if unavoidable
7. **Keep backwards compatibility** during transition
8. **Follow the file structure** defined above
9. **Use type hints** on all new code
10. **Write DocBlocks** for public methods

### Common Patterns

**Database query**:
```php
global $wpdb;
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}table WHERE id = %d",
        $id
    ),
    ARRAY_A
);
```

**AJAX handler**:
```php
public function handle_ajax_request(): void {
    check_ajax_referer('erh_nonce', '_wpnonce');
    
    if (!current_user_can('read')) {
        wp_send_json_error('Unauthorized');
    }
    
    $data = $this->process_request($_POST);
    wp_send_json_success($data);
}
```

**Cron job**:
```php
public function register(): void {
    if (!wp_next_scheduled('erh_daily_job')) {
        wp_schedule_event(time(), 'daily', 'erh_daily_job');
    }
    add_action('erh_daily_job', [$this, 'execute']);
}
```

---

## Questions to Ask Before Starting

1. Which component should I build first?
2. Are there any existing tests I should be aware of?
3. Should I create a staging environment workflow?
4. Is the HFT plugin already installed and configured with scrapers?
5. What's the deployment process (Git, FTP, etc.)?
5. What's the deployment process (Git, FTP, etc.)?
