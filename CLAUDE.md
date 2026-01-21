# ERideHero Platform

Electric mobility review platform (scooters, e-bikes, EUCs, skateboards, hoverboards) with ~50K monthly visitors. Rebuilding from Oxygen Builder to custom WordPress theme.

## When to Read Other Files

| If working on... | Read |
|------------------|------|
| Pricing, HFT integration, geo system | `HFT_INTEGRATION.md` |
| ACF fields, product specs | `ACF_RESTRUCTURE_PLAN.md` |
| REST API endpoints | `API_REFERENCE.md` |
| Porting old plugin code | `LEGACY_FUNCTIONS.md` |
| Progress tracking, phase status | `IMPLEMENTATION_CHECKLIST.md` |
| Static HTML/CSS reference | `eridehero-redesign/CLAUDE.md` |
| Compare system refactoring plan | `.claude/plans/playful-whistling-thompson.md` |

---

## Technical Stack

| Component | Technology |
|-----------|------------|
| Theme | `erh-theme/` - Custom PHP + modular CSS |
| Plugin | `erh-core/` - Unified functionality plugin |
| Price Data | HFT Plugin (Housefresh Tools) |
| Fields | ACF Pro |
| SEO | RankMath Pro |
| Caching | LiteSpeed + Cloudflare |

---

## Project Structure

```
eridehero/
├── erh-core/                    # Main plugin (PSR-4 autoloading)
│   ├── includes/
│   │   ├── admin/               # Admin pages (settings, click stats, link populator)
│   │   ├── amazon/              # Amazon API integration (locales, AWS signer)
│   │   ├── api/                 # REST endpoints
│   │   ├── blocks/              # ACF block templates
│   │   ├── config/              # Configuration classes
│   │   │   └── class-spec-config.php  # Single source of truth for spec metadata
│   │   ├── cron/                # Scheduled jobs
│   │   ├── database/            # Table operations
│   │   ├── email/               # Email templates
│   │   ├── pricing/             # Price fetching, deals
│   │   ├── scoring/             # Product scoring
│   │   ├── tracking/            # Click tracking & stats
│   │   ├── user/                # Auth, preferences, trackers
│   │   ├── class-cache-keys.php # Centralized cache key management
│   │   ├── class-geo-config.php # Geo constants (regions, currencies, EU countries)
│   │   └── class-core.php       # Main orchestrator
│   └── vendor/                  # Composer autoload
│
├── erh-theme/                   # WordPress theme
│   ├── assets/
│   │   ├── css/                 # Modular CSS partials
│   │   ├── js/
│   │   │   ├── components/      # UI components
│   │   │   │   ├── calculators/ # Calculator modules (battery-*, escooter-*)
│   │   │   │   └── compare/     # Compare system (renderers.js, constants.js, utils.js)
│   │   │   ├── config/          # Configuration (compare-config.js)
│   │   │   ├── services/        # geo-price.js, geo-config.js, search-client.js
│   │   │   └── utils/           # Shared utilities (pricing-ui.js, calculator-utils.js, etc.)
│   │   └── images/logos/        # Retailer logos
│   ├── inc/                     # PHP includes
│   └── template-parts/          # Component templates
│       └── compare/components/  # Shared PHP renderers (mirrored in JS)
│
├── reference/                   # Old plugins (READ ONLY - for reference)
│   ├── my-custom-functionality-master/
│   ├── product-functionality/
│   └── housefresh-tools/
│
└── eridehero-redesign/          # Static HTML prototypes (READ ONLY - for reference)
```

---

## Code Standards

### PHP
- PSR-4 autoloading via Composer
- Type hints on parameters and returns
- Prepared statements for ALL database queries
- Nonce verification on ALL AJAX/REST handlers

### JavaScript
- ES6+ modules with dynamic imports
- No jQuery (except DataTables if needed)
- Components only load when DOM elements exist

### CSS
- Modular partials (`_variables.css`, `_header.css`, etc.)
- Custom properties for design tokens
- Mobile-first responsive

### URL Generation (Subfolder-Safe)
**CRITICAL:** Site may be installed in a subfolder (e.g., `localhost/eridehero/`). Always use WordPress URL functions instead of hardcoded paths.

```php
// ✅ CORRECT - Works in any installation
home_url('/go/product-slug/123/')     // → https://eridehero.com/go/product-slug/123/
                                       // → http://localhost/eridehero/go/product-slug/123/

// ❌ WRONG - Breaks in subfolder installs
'/go/product-slug/123/'               // → Missing domain and subfolder
```

**PHP URL Functions:**
- `home_url($path)` - For frontend URLs (links, redirects)
- `admin_url($path)` - For admin URLs
- `rest_url($path)` - For REST API URLs
- `get_permalink($post_id)` - For post/page URLs

**In JavaScript:**
```javascript
// Use ensureAbsoluteUrl() for any URL that may be relative
import { ensureAbsoluteUrl } from '../utils/dom.js';

link.href = ensureAbsoluteUrl(offer.tracked_url || offer.url);
// Handles: "/go/..." → "http://localhost/eridehero/go/..."
// Passes through: "https://..." → "https://..."
```

### Affiliate Links (SEO Compliance)
All affiliate/retailer links MUST include proper `rel` attributes for SEO and security.

**Required attributes:**
```html
<!-- Affiliate links (buy buttons, retailer links, /go/ redirects) -->
<a href="..." target="_blank" rel="sponsored noopener">

<!-- Non-affiliate external links (YouTube, social, reference docs) -->
<a href="..." target="_blank" rel="noopener">
```

**Attribute meanings:**
- `sponsored` - Indicates commercial relationship (Google requirement for affiliate links)
- `noopener` - Security: prevents reverse tabnabbing

**Note:** We use `sponsored` without `nofollow` because `sponsored` already tells Google to treat the link appropriately. Adding `nofollow` is redundant and can look over-cautious to search engines.

**Files with affiliate links:**
- `erh-theme/assets/js/components/compare-results.js`
- `erh-theme/assets/js/components/price-intel.js`
- `erh-theme/assets/js/components/listicle-item.js`
- `erh-theme/assets/js/components/sticky-buy-bar.js`
- `erh-theme/assets/js/utils/pricing-ui.js`
- `erh-theme/assets/js/services/geo-price.js`
- `erh-theme/template-parts/components/price-intel.php`
- `erh-theme/template-parts/components/sticky-buy-bar.php`
- `erh-core/includes/blocks/listicle-item/template.php`

**Shared utility:** `erh-theme/assets/js/utils/dom.js` - Contains `ensureAbsoluteUrl()` used by all JS files above.

### RankMath SEO Integration

We use **RankMath Pro** for SEO. All meta tags, Open Graph, and schema are managed through RankMath - do NOT output these manually.

**Integration pattern for dynamic pages:**
```php
// Override RankMath title for custom/dynamic pages
add_filter('rank_math/frontend/title', function(string $title): string {
    if (!is_my_custom_page()) {
        return $title;
    }
    return 'My Custom Title | ERideHero';
}, 20);

// Override RankMath description
add_filter('rank_math/frontend/description', function(string $desc): string {
    if (!is_my_custom_page()) {
        return $desc;
    }
    return 'Custom meta description for this page.';
}, 20);
```

**Key RankMath filters:**
| Filter | Purpose |
|--------|---------|
| `rank_math/frontend/title` | Override `<title>` tag |
| `rank_math/frontend/description` | Override meta description |
| `rank_math/json_ld` | Modify schema/JSON-LD output |
| `rank_math/opengraph/facebook` | Modify Open Graph tags |
| `rank_math/opengraph/twitter` | Modify Twitter Card tags |

**Current integrations:**
- `erh-theme/inc/compare-routes.php` - Dynamic titles/descriptions for compare pages
- `erh-core/includes/tracking/class-click-redirector.php` - Excludes /go/ URLs from indexing

**Important:**
- Never use `wp_head` to output meta tags manually - RankMath handles this
- For schema, use RankMath's schema builder or filter `rank_math/json_ld`
- Product schema is managed via RankMath's WooCommerce-style product schema on `products` CPT

### robots.txt

Add the following to your robots.txt (via RankMath > General Settings > Edit robots.txt):

```
# Block affiliate redirect URLs from crawling
Disallow: /go/
```

This prevents search engines from wasting crawl budget on redirect URLs.

---

## JavaScript Data Injection (`erhData`)

**Base config** (set in `inc/enqueue.php`):
```php
wp_localize_script('erh-app', 'erhData', [
    'siteUrl'       => home_url(),
    'ajaxUrl'       => admin_url('admin-ajax.php'),
    'restUrl'       => rest_url('erh/v1/'),
    'hftRestUrl'    => rest_url('housefresh-tools/v1/'),
    'nonce'         => wp_create_nonce('wp_rest'),
    'ajaxNonce'     => wp_create_nonce('erh_nonce'),
    'themeUrl'      => ERH_THEME_URI,
    'isLoggedIn'    => is_user_logged_in(),
    'searchJsonUrl' => $upload_dir['baseurl'] . '/search_items.json?v=' . filemtime(...),
]);
```

**Page-specific data** (MUST be after `get_footer()`):
```php
get_footer();
?>
<script>
window.erhData.finderProducts = <?php echo wp_json_encode($products); ?>;
</script>
```

**JS access**:
```javascript
const { restUrl, nonce } = window.erhData;
```

---

## Key Patterns

### Database Query
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

### REST Endpoint
```php
register_rest_route('erh/v1', '/endpoint', [
    'methods'  => 'GET',
    'callback' => [$this, 'handle_request'],
    'permission_callback' => function() {
        return current_user_can('read');
    },
]);
```

### Dynamic Component Loading
```javascript
// In app.js - components only load when needed
if (document.querySelector('[data-gallery]')) {
    import('./components/gallery.js').then(m => m.initGallery());
}
```

### Cache Keys (ERH\CacheKeys)
```php
// All transient cache keys go through CacheKeys class for consistency
use ERH\CacheKeys;

// Generate keys
$key = CacheKeys::priceIntel($product_id, $geo);      // erh_price_intel_{id}_{geo}
$key = CacheKeys::priceHistory($product_id, $geo);    // erh_price_history_{id}_{geo}
$key = CacheKeys::listicleSpecs($product_id, $cat);   // erh_listicle_specs_{id}_{cat}
$key = CacheKeys::productSpecs($product_id, $cat);    // erh_product_specs_{id}_{cat}
$key = CacheKeys::similarProducts($id, $limit, $geo); // erh_similar_{id}_{limit}_{geo}
$key = CacheKeys::deals($cat, $limit, $geo, $period, $threshold);
$key = CacheKeys::dealCounts($geo, $period, $threshold);
$key = CacheKeys::productHasPricing($product_id);     // erh_has_pricing_{id}

// Invalidation (called on ACF save, HFT price updates)
CacheKeys::clearPriceCaches($product_id);    // All geo variants
CacheKeys::clearListicleSpecs($product_id);  // All category variants
CacheKeys::clearProductSpecs($product_id);   // All category variants
CacheKeys::clearProduct($product_id);        // Everything for a product
```

### Shared Pricing UI (JavaScript)
```javascript
// erh-theme/assets/js/utils/pricing-ui.js
import { calculatePriceVerdict, renderRetailerRow, renderVerdictBadge } from '../utils/pricing-ui.js';

// Verdict thresholds: +/-3% is neutral zone
const verdict = calculatePriceVerdict(currentPrice, avgPrice);
// Returns: { percent, type: 'below'|'above'|'neutral', text, shouldShow }
```

---

## Single-Product Analysis System

REST endpoint for analyzing a product's strengths and weaknesses by comparing it against other products in the same price bracket.

### Endpoint

```
GET /erh/v1/products/{id}/analysis?geo=US
```

Returns advantages and weaknesses for a single product based on price bracket comparison within its category (escooter only for now).

### Price Brackets

Products are compared against others in the same price range:

| Bracket | Range | Label |
|---------|-------|-------|
| `budget` | $0-$500 | Budget |
| `midrange` | $500-$1,000 | Mid-Range |
| `performance` | $1,000-$1,500 | Performance |
| `premium` | $1,500-$2,500 | Premium |
| `ultra` | $2,500+ | Ultra |

**Minimum bracket size**: 5 products. Falls back to category-wide percentile if fewer.

### Analysis Thresholds

A spec qualifies as a **strength** if:
- Product is in top 20% (percentile ≥ 80), OR
- Product is 15%+ better than bracket average

A spec qualifies as a **weakness** if:
- Product is in bottom 20% (percentile ≤ 20), OR
- Product is 15%+ worse than bracket average

### Analyzed Specs

| Type | Examples |
|------|----------|
| Value Metrics | Battery Value ($/Wh), Motor Value ($/W), Range Value ($/mi), Speed Value ($/mph) |
| Raw Specs | Tested top speed, tested range, battery capacity, motor power, weight, charging time |
| Efficiency | Speed-to-weight ratio (mph/lb), energy density (Wh/lb), range efficiency (mi/lb) |
| Composite Scores | Ride quality, maintenance (compared to bracket average) |
| Absolute Quality | IP rating (not bracket-compared, absolute thresholds) |

### Key Files

| File | Purpose |
|------|---------|
| `erh-core/includes/comparison/class-price-bracket-config.php` | Bracket definitions and threshold constants |
| `erh-core/includes/comparison/calculators/class-escooter-advantages.php` | Main analysis calculator (`calculate_single()`) |
| `erh-core/includes/api/class-rest-products.php` | REST endpoint handler |
| `erh-theme/assets/js/components/product-analysis.js` | Frontend rendering |
| `erh-theme/assets/js/components/product-page.js` | Page orchestrator (single API call for radar + analysis) |
| `erh-theme/template-parts/product/performance-profile.php` | PHP template with skeleton states |

### Response Structure

```json
{
  "product": { "id": 123, "name": "NIU KQi 300X", "scores": {...} },
  "price_context": {
    "geo": "US",
    "current_price": 749,
    "bracket": { "key": "midrange", "label": "Mid-Range", "min": 500, "max": 1000 },
    "products_in_bracket": 23,
    "comparison_mode": "bracket"
  },
  "bracket_scores": { "motor_performance": 72, ... },
  "advantages": [
    {
      "spec_key": "value_metrics.price_per_wh",
      "label": "Battery Value",
      "text": "Excellent battery value",
      "comparison": "$1.23/Wh vs $1.65/Wh avg",
      "percentile": 92,
      "pct_vs_avg": -25.5
    }
  ],
  "weaknesses": [...]
}
```

### Current Status

- **Caching**: Intentionally disabled during testing for fast iteration
- **Future work**: Add caching after price data is complete and system is tuned
- **Category support**: Escooter only (other categories can be added later)

---

## Compare System Architecture

The compare system uses SSR (PHP) + JS hydration for SEO + interactivity.

### Single Source of Truth: SpecConfig

All spec metadata (groups, weights, thresholds, rankings) lives in one PHP class:

```php
use ERH\Config\SpecConfig;

// Get spec groups for a category
$groups = SpecConfig::get_spec_groups('escooter');

// Export config for JS (inject after get_footer())
window.erhData.specConfig = <?php echo wp_json_encode(
    SpecConfig::export_compare_config($category)
); ?>;
```

**JS Access:**
```javascript
const { specGroups, categoryWeights, advantageThreshold } = window.erhData.specConfig;
```

### Shared UI Renderers (Mirrored PHP/JS)

Components have matching PHP and JS functions for consistent rendering:

| PHP Component | JS Function | Purpose |
|--------------|-------------|---------|
| `erh_score_ring()` | `renderScoreRing()` | Circular score indicator |
| `erh_winner_badge()` | `renderWinnerBadge()` | Winner check badge |
| `erh_spec_value()` | `renderSpecValue()` | Spec cell content |
| `erh_spec_cell()` | `renderSpecCell()` | Full `<td>` with value |
| `erh_mobile_spec_value()` | `renderMobileSpecValue()` | Mobile card value |

**File Locations:**
```
erh-theme/
├── template-parts/compare/components/
│   ├── score-ring.php      # Score ring SVG
│   ├── winner-badge.php    # Winner/feature badges
│   ├── spec-value.php      # Spec formatting + cells
│   └── product-thumb.php   # Mobile card thumbnails
│
└── assets/js/components/compare/
    ├── renderers.js        # Matching JS functions
    ├── constants.js        # Shared constants
    ├── utils.js            # Comparison utilities
    └── state.js            # State management
```

**SYNC Rule:** When modifying rendering logic, update BOTH the PHP component AND the JS renderer.

### Compare Templates (Consolidated)

All compare URLs use a single template for consistency:

| URL Pattern | Template | Mode |
|-------------|----------|------|
| `/compare/` | `page-compare.php` | Hub page |
| `/compare/electric-scooters/` | `category.php` | Category hub |
| `/compare/?products=123,456` | `page-compare.php` | Dynamic comparison |
| `/compare/product-a-vs-product-b/` | `page-compare.php` | Dynamic or curated |
| Curated comparison CPT | `page-compare.php` | Curated (adds intro + verdict) |

**Curated comparisons** (comparison CPT) use the same template but conditionally show:
- Intro section with H1 and intro text
- Verdict section with winner pick
- Related comparisons section

### Compare Config Files

| File | Purpose |
|------|---------|
| `page-compare.php` | Consolidated template (hub, dynamic, curated) |
| `template-parts/compare/category.php` | Category landing pages |
| `erh-core/includes/config/class-spec-config.php` | Single source of truth for spec metadata |
| `erh-theme/assets/js/config/compare-config.js` | Utility functions (formatSpecValue, compareValues) |
| `erh-theme/inc/compare-routes.php` | URL routing, RankMath SEO integration |

### Adding a New Spec

1. Add to `SpecConfig::SPEC_GROUPS` in the correct category group
2. Include: `key`, `label`, `unit`, `format`, `higherBetter`, `tooltip`
3. Run cache rebuild cron to populate new spec in product data

### Adding a New Product Category

1. Add category config to `SpecConfig::SPEC_GROUPS['newcategory']`
2. Add category weights to `SpecConfig::CATEGORY_WEIGHTS['newcategory']`
3. Add scorer class (see `scoring/class-escooter-scorer.php` pattern)
4. Update `CategoryConfig` class with new category mapping

---

## Custom Post Types

| CPT | Purpose | Registered By |
|-----|---------|---------------|
| `products` | All product types (scooter, bike, etc.) | erh-core |
| `tool` | Calculator tools (range, battery, etc.) | erh-core |

Product type stored as ACF field `product_type` (values: Electric Scooter, Electric Bike, Electric Skateboard, Electric Unicycle, Hoverboard).

### Tool CPT Details
- **Archive**: `/tools/` - Grid of all calculators with category filters
- **Single**: `/tools/{slug}/` - Two-column layout with calculator + sidebar
- **Taxonomy**: `tool_category` (hidden, non-public) - For categorization and filtering
- **ACF Fields**: `tool_icon` (select), `tool_description` (textarea)
- **JS Pattern**: Dynamic import via `data-calculator="{slug}"` loads `/components/calculators/{slug}.js`

### Tool Category Taxonomy
- Non-public (no archive pages, no URLs)
- Shows in admin with column
- ACF field links to `product_type` taxonomy (for related content)

---

## Custom Tables

| Table | Purpose |
|-------|---------|
| `wp_product_data` | Finder tool cache (specs, geo-keyed pricing) |
| `wp_product_daily_prices` | Daily price snapshots per geo |
| `wp_price_trackers` | User price alerts |
| `wp_product_views` | View tracking for popularity |

HFT plugin tables: `wp_hft_tracked_links`, `wp_hft_price_history`, `wp_hft_scrapers`, `wp_hft_scraper_rules`

---

## Geo Pricing (5 Regions)

| Region | Currency | Notes |
|--------|----------|-------|
| US | USD | Default for unmapped countries |
| GB | GBP | United Kingdom |
| EU | EUR | Aggregates DE/FR/IT/ES/etc. (27 countries) |
| CA | CAD | Canada |
| AU | AUD | Australia, New Zealand |

**Rule**: No currency mixing. Products without regional pricing show "no price" for actionable UI (buy buttons, price alerts), but US prices shown as reference.

### Reference Pricing (Non-Regional Products)
When a user's region has no pricing data:
- REST API returns `fallback` field with up to 4 US offers
- UI shows "No [region] pricing available" message
- Displays US prices in clickable reference cards (logo, price, stock)
- Price alert buttons hidden (can't track prices you can't buy)
- Warning: "These retailers may not ship to your region"

### GeoConfig Class (Single Source of Truth)
```php
use ERH\GeoConfig;

// Constants
GeoConfig::REGIONS        // ['US', 'GB', 'EU', 'CA', 'AU']
GeoConfig::EU_COUNTRIES   // ['AT', 'BE', 'BG', ...] (27 countries)
GeoConfig::CURRENCIES     // ['US' => 'USD', 'GB' => 'GBP', ...]

// Methods
GeoConfig::get_region('DK')      // Returns 'EU'
GeoConfig::get_currency('EU')    // Returns 'EUR'
GeoConfig::get_symbol('EUR')     // Returns '€'
GeoConfig::is_eu_country('FR')   // Returns true
GeoConfig::is_valid_region('US') // Returns true
```

### Cookies Set by geo-price.js
- `erh_geo` - Region code (US, GB, EU, CA, AU) - 1 year TTL
- `erh_country` - Specific country code (DK, FR, DE, etc.) - 1 year TTL

See `HFT_INTEGRATION.md` for full geo architecture.

---

## Cron Jobs

| Job | Schedule | Purpose |
|-----|----------|---------|
| `cache-rebuild` | Every 2 hours | Rebuild wp_product_data with geo pricing |
| `finder-json` | Every 2 hours | Generate finder_*.json files |
| `comparison-json` | Every 2 hours | Generate comparison_products.json |
| `search-json` | Twice daily | Generate search_items.json |
| `price-update` | Daily | Record daily price snapshots |
| `notifications` | Every 6 hours | Check price alerts, send emails |

---

## HFT Integration

ERH-Core uses filters to integrate with HFT plugin:

```php
// Use ERH 'products' CPT instead of HFT's 'hf_product'
add_filter('hft_product_post_types', fn() => ['products']);

// Disable HFT's built-in CPT registration
add_filter('hft_register_product_cpt', '__return_false');
```

**Check if HFT active**:
```php
if (defined('HFT_VERSION') && class_exists('HFT_Loader')) {
    // HFT is available
}
```

---

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| User API | REST (not AJAX) | Modern, stateless |
| Geo Pricing | 5 regions | Simple UX, covers 90%+ revenue |
| JS Loading | Dynamic imports | Only load when needed |
| Price Cache | 6hr + HFT invalidation | Balance freshness vs performance |
| Currency | No fallbacks | Prevents mixed currencies |

---

## Theme Helper Functions

```php
// Icons
erh_icon($name, $class)           // Get SVG from sprite
erh_the_icon($name, $class)       // Echo SVG

// Scores
erh_get_score_label($score)       // 9.0 → "Excellent"
erh_get_score_attr($score)        // 9.0 → "excellent"

// Product types
erh_product_type_slug($type)      // "Electric Scooter" → "e-scooters"

// Formatting
erh_format_price($price, $currency)
erh_split_price($price)           // ['whole' => '499', 'decimal' => '99']
erh_time_elapsed($datetime)       // "2 hours ago"
```

---

## What NOT to Change

1. **Database schemas** - Data already exists
2. **ACF field names** - Would require migration
3. **URL structure** - SEO impact
4. **User meta keys** - Existing user data
5. **`eridehero-redesign/`** - Reference only, theme already built from it
6. **`reference/`** - Old plugins for reference only

---

## Quick Commands

```bash
# Plugin autoload
cd erh-core && composer dump-autoload

# Check PHP syntax
php -l path/to/file.php

# WP-CLI cron
wp erh cron run cache-rebuild
wp erh cron list
```
