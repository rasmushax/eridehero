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
| Plugin | my-custom-functionality (monolithic) | erh-core (structured) |
| Price Data | HFT Plugin (Housefresh Tools) | HFT Plugin (integrated) |
| Fields | ACF Pro | ACF Pro (keep) |
| Tables | Custom tables | Same (keep schema) |
| Caching | LiteSpeed Cache | LiteSpeed + Cloudflare |

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
    price decimal(10,2),
    rating decimal(3,1),
    popularity_score int(8),
    instock tinyint(1),
    permalink varchar(255),
    image_url varchar(255),
    price_history longtext,            -- Serialized: avg prices, z-scores, etc.
    bestlink varchar(500),
    last_updated datetime,
    PRIMARY KEY (id),
    UNIQUE KEY product_id (product_id),
    KEY product_type (product_type)
);
```

#### `wp_product_daily_prices` - Price History
```sql
CREATE TABLE wp_product_daily_prices (
    id bigint(20) AUTO_INCREMENT,
    product_id bigint(20) NOT NULL,
    price decimal(10,2) NOT NULL,
    domain varchar(255) NOT NULL,
    date date NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY product_date (product_id, date)
);
```

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
| `PriceFetcher` | `get_best_price($product_id, $geo)` | Returns lowest in-stock price |
| `AffiliateResolver` | `resolve_domain($url)` | Extracts actual domain from affiliate network URLs |
| `AffiliateResolver` | `get_display_name($domain)` | Maps domain to retailer name |
| `AffiliateResolver` | `get_logo($domain)` | Returns retailer logo URL |
| `DealsFinder` | `get_deals($product_type, $threshold)` | Products below 6-month average |
| `DailyPriceJob` | `execute()` | Records daily snapshot for price history charts |

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

### User System

| File | Function | Description |
|------|----------|-------------|
| `login.php` | AJAX handler | Custom login with `wp_signon()` |
| `register.php` | AJAX handler | Registration with rate limiting, honeypot, DNS validation |
| `lost_password.php` | AJAX handler | Password reset email with custom template |
| `reset-password.php` | AJAX handler | Password reset processing |
| `settings.php` | Template | User preferences (email settings, password change) |

**User Meta Keys**:
- `price_trackers_emails` - Boolean, receive price drop alerts
- `sales_roundup_emails` - Boolean, receive deals digest
- `sales_roundup_frequency` - `weekly`, `bi-weekly`, or `monthly`
- `newsletter_subscription` - Boolean, general newsletter
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

| Hook | Schedule | Function |
|------|----------|----------|
| `update_product_daily_prices_cron` | Daily | Record daily prices |
| `product_data_cron_job` | Twice daily | Rebuild finder cache |
| `generate_search_items_json_hook` | Twice daily | Generate search JSON |
| `pf_price_tracker_cron` | Daily | Check price alerts |
| Deals email | Weekly | Send deals digest |

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
│   │   ├── class-auth-handler.php       # Login, register, password reset
│   │   ├── class-user-preferences.php   # Email settings, etc.
│   │   └── class-user-tracker.php       # Price tracker CRUD for users
│   │
│   ├── reviews/
│   │   ├── class-review-handler.php     # Submit, moderate reviews
│   │   └── class-review-query.php       # Get reviews, calculate ratings
│   │
│   ├── email/
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
│       ├── src/
│       │   ├── header.js              # Header interactions
│       │   ├── search.js              # Search functionality  
│       │   ├── finder.js              # Finder tool
│       │   ├── comparison.js          # Comparison tool
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
