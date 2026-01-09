# Legacy Functions Reference

Functions from the old plugins (`reference/my-custom-functionality-master/` and `reference/product-functionality/`) that must be preserved or have compatible replacements in erh-core.

---

## Pricing Functions

```php
// Get all prices for a product
getPrices($product_id)
// Returns: array of offers with price, status, retailer, link

// Add tracking parameters to affiliate URL
afflink($url)
// Returns: URL with affiliate tracking appended

// Resolve affiliate network URL to actual domain
extractDomain($url)
// Returns: domain string (e.g., 'amazon.com')

// Convert domain to display name
prettydomain($domain)
// Returns: formatted name (e.g., 'Amazon')

// Get retailer logo HTML
getShopImg($domain, $class = '')
// Returns: <img> tag with retailer logo
```

### Affiliate Network Resolution

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

**New location**: `erh-core/includes/pricing/class-affiliate-resolver.php`

---

## Review Functions

```php
// Get reviews for a product with ratings distribution
getReviews($product_id)
// Returns: [
//   'reviews' => array of review objects,
//   'distribution' => [5 => count, 4 => count, ...],
//   'average' => float,
//   'total' => int
// ]

// Truncate review text with "show more" support
truncate_review($text, $length = 200)
// Returns: truncated text with ellipsis
```

**New location**: `erh-core/includes/reviews/class-review-query.php`

---

## Deals Functions

```php
// Get products priced below average
getDeals($type = 'all', $threshold = -5)
// Parameters:
//   $type: 'all', 'escooter', 'ebike', etc.
//   $threshold: percentage below avg (e.g., -5 = 5% below)
// Returns: array of product objects with deal info

// Split price into whole and decimal parts
splitPrice($price)
// Returns: ['whole' => '499', 'decimal' => '99']
```

**New location**: `erh-core/includes/pricing/class-deals-finder.php`

---

## Utility Functions

```php
// Format datetime as "2 hours ago"
time_elapsed_string($datetime)
// Returns: human-readable time string

// Format spec value for display
format_spec_value($spec, $unit = '')
// Returns: formatted string with unit

// Smart HTML entity handling
esc_smart($content)
// Returns: safely escaped content
```

**New location**: `erh-theme/inc/template-functions.php`

---

## Email Functions

```php
// Wrap content in branded HTML email template
get_email_template($content)
// Returns: full HTML email string

// Helper methods (in Product_Functionality class):
generate_email_paragraph($text)
generate_email_button($url, $text)
generate_email_link($url, $text, $style = '')
send_html_email($to, $subject, $content)
```

**New location**: `erh-core/includes/email/class-email-template.php`

---

## Theme Helper Functions (New)

Located in `erh-theme/inc/template-functions.php`:

```php
// Icons
erh_icon($name, $class = '')      // Get SVG icon from sprite
erh_the_icon($name, $class = '')  // Echo SVG icon

// Product types
erh_product_type_slug($type)      // "Electric Scooter" → "e-scooters"
erh_get_product_type_short_name($type)  // "Electric Scooter" → "E-Scooter"

// Scores & ratings
erh_get_score_label($score)       // 9.0 → "Excellent", 8.0 → "Great"
erh_get_score_attr($score)        // 9.0 → "excellent" (for data attributes)

// Specifications
erh_get_spec_groups($product_id, $product_type)  // Get organized spec groups
erh_filter_specs($specs)          // Remove empty specs
erh_format_boolean($value)        // true → "Yes"
erh_format_tire_sizes($front, $rear)  // "10" / "10" → '10"'
erh_format_range($min, $max, $unit)   // "38–42""
erh_format_dimensions($l, $w, $h)     // "45 × 22 × 48""

// Breadcrumbs
erh_review_breadcrumb($slug, $name)  // Category > Reviews > Title

// Formatting
erh_format_price($price, $currency)  // 499.99, "USD" → "$500"
erh_split_price($price)          // ['whole' => '499', 'decimal' => '99']
erh_time_elapsed($datetime)      // "2 hours ago"
erh_truncate_text($text, $length)  // With "show more" support

// YouTube
erh_extract_youtube_id($url)     // Extract video ID from URL
```

---

## Price Tracker Functions

```php
// AJAX endpoints (old):
pf_check_price_data()      // Check if product has price data
pf_check_price_tracker()   // Get user's tracker for product
pf_set_price_tracker()     // Create/update tracker
pf_delete_price_tracker()  // Delete tracker

// Cron:
Price_Tracker_Cron::run()  // Check all trackers, send notifications
```

**New location**: REST API endpoints in `class-user-tracker.php`

---

## View Tracking

```php
// AJAX endpoint tracks unique views per product per IP per day
// Bot detection via user agent
// Used in popularity scoring
// 90-day retention with probabilistic cleanup
```

**New location**: `erh-core/includes/database/class-view-tracker.php`

---

## Popularity Scoring Algorithm

Calculated in `CacheRebuildJob`:

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

## Reference Plugin Locations

When looking for original implementations:

- **`reference/my-custom-functionality-master/`** - Shortcodes, blocks, general utilities
- **`reference/product-functionality/`** - Core product logic: pricing, reviews, price trackers, user system, cron jobs

---

*This file documents legacy functions for reference when porting old code. The new implementations in erh-core should maintain compatible behavior.*
