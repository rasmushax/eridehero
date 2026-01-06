# ERideHero Implementation Checklist

Use this file alongside CLAUDE.md to track progress.

---

## Phase 1: Plugin Scaffold (Days 1-2) - COMPLETE

### Day 1: Setup
- [x] Create `erh-core/` directory structure
- [x] Create `composer.json` with PSR-4 autoloading
- [x] Create `erh-core.php` main plugin file
- [x] Create `includes/class-core.php` main class
- [x] Run `composer dump-autoload`

### Day 2: Foundation
- [x] Create `includes/post-types/class-product.php`
- [x] Create `includes/post-types/class-review.php`
- [x] Create `includes/database/class-schema.php` (all table creation)
- [x] Register activation hook for schema creation
- [x] Test: Plugin activates without errors

---

## Phase 2: Core Pricing Classes (Days 3-6) - COMPLETE

### Day 3: Affiliate Resolution
- [x] Create `includes/pricing/class-affiliate-resolver.php`
- [x] Create `includes/pricing/class-retailer-registry.php`
- [x] Create `includes/pricing/class-retailer-logos.php` (HFT integration)
- [x] Test: Domain resolution works for all networks

### Day 4: Price Fetcher
- [x] Create `includes/pricing/class-price-fetcher.php` (reads from HFT tables)
- [x] Include priority sorting
- [x] Include geo-targeting support
- [x] Test: `$fetcher->get_prices($product_id)` returns offers

### Day 5: Database Classes
- [x] Create `includes/database/class-product-cache.php` (wp_product_data)
- [x] Create `includes/database/class-price-history.php` (wp_product_daily_prices)
- [x] Create `includes/database/class-price-tracker.php` (wp_price_trackers)
- [x] Create `includes/database/class-view-tracker.php` (wp_product_views)
- [x] Test: CRUD operations work on all tables

### Day 6: Deals Finder
- [x] Create `includes/pricing/class-deals-finder.php`
- [x] Test: Returns products below avg price

---

## Phase 3: User System (Days 7-8) - COMPLETE

**Key Decision**: Use REST API endpoints instead of AJAX for all user operations.

### Phase 3A: Authentication (class-auth-handler.php)
- [x] Login handler with rate limiting
- [x] Register handler with DNS email validation
- [x] Forgot password handler
- [x] Reset password handler
- [x] Logout handler
- [x] Status check endpoint
- [x] wp-login.php redirect for non-admin users (bypass: `?wpadmin=true`)

**REST Endpoints:**
- `POST /erh/v1/auth/login`
- `POST /erh/v1/auth/register`
- `POST /erh/v1/auth/forgot-password`
- `POST /erh/v1/auth/reset-password`
- `POST /erh/v1/auth/logout`
- `GET /erh/v1/auth/status`

### Phase 3B: User Features
- [x] Create `includes/user/class-user-preferences.php`
  - Email preference management (price alerts, deals digest, newsletter)
  - Profile updates (display name, email, password)
- [x] Create `includes/user/class-user-tracker.php`
  - Price tracker CRUD with live price enrichment
  - Target price / price drop options

**REST Endpoints:**
- `GET/PUT /erh/v1/user/preferences`
- `PUT /erh/v1/user/email`
- `PUT /erh/v1/user/password`
- `PUT /erh/v1/user/profile`
- `GET /erh/v1/user/trackers`
- `DELETE /erh/v1/user/trackers/{id}`
- `GET/POST/DELETE /erh/v1/products/{id}/tracker`
- `GET /erh/v1/products/{id}/price-data`

### Phase 3.5: Social Login (OAuth)
- [x] Create `includes/user/interface-oauth-provider.php`
- [x] Create `includes/user/class-oauth-google.php`
- [x] Create `includes/user/class-oauth-facebook.php`
- [x] Create `includes/user/class-oauth-reddit.php`
- [x] Create `includes/user/class-social-auth.php` (orchestrator)
- [x] CSRF protection via transient-based state tokens
- [x] Auto-link accounts when email matches existing user

**REST Endpoints:**
- `GET /erh/v1/auth/social/{provider}` - Initiate OAuth flow
- `GET /erh/v1/auth/social/{provider}/callback` - Handle callback
- `GET /erh/v1/auth/social/providers` - List available providers

### Phase 3C: Mailchimp Integration
- [x] Create `includes/email/class-mailchimp-sync.php`
- [x] Subscribe/unsubscribe sync
- [x] Webhook for Mailchimp unsubscribe events
- [x] Email change handling

**REST Endpoints:**
- `POST /erh/v1/webhooks/mailchimp`

### Phase 3D: Shared Services
- [x] Create `includes/user/class-rate-limiter.php` (transient-based)
- [x] Create `includes/user/class-user-repository.php` (meta key constants, user data access)

### Phase 3E: Admin Settings
- [x] Create `includes/admin/class-settings-page.php`
  - Social Login tab (Google, Facebook, Reddit credentials)
  - Mailchimp tab (API key, list ID, connection test)
  - General tab (preferences page, system info)

**Admin Location:** Settings > ERideHero

### Phase 3F: HFT Integration
- [x] Add `hft_product_post_types` filter to use `products` CPT
- [x] Add `hft_register_product_cpt` filter to disable HFT's built-in CPT

---

## Phase 4: Reviews, Cron & Email (Days 9-11) - COMPLETE

**Key Decisions:**
- Email system pulled forward from Phase 5 (used by notification job)
- Reviews use REST API (consistent with user system)
- Notifications run every 6 hours (not daily)
- Admin "Run Now" buttons for cron jobs (in Settings > ERideHero > Cron Jobs)

### Reviews
- [x] Create `includes/reviews/class-review-query.php`
  - Get reviews by product or user
  - Calculate ratings distribution
  - Check if user has reviewed product
- [x] Create `includes/reviews/class-review-handler.php`
  - REST API for review submission with image upload
  - Admin notification when new review submitted
  - Reviews always pending (require moderation)

**REST Endpoints:**
- `POST /erh/v1/reviews` - Submit review
- `GET /erh/v1/products/{id}/reviews` - Get product reviews
- `GET /erh/v1/user/reviews` - Get user's reviews
- `DELETE /erh/v1/reviews/{id}` - Delete own review

### Email System
- [x] Create `includes/email/class-email-template.php`
  - Branded HTML email wrapper (logo, footer)
  - Helper methods: paragraph, heading, button, link, divider
  - Product card and price drop card templates
- [x] Create `includes/email/class-email-sender.php`
  - wp_mail wrapper with HTML headers
  - Templates: price drop notification, deals digest, password reset, welcome

### Cron System
- [x] Create `includes/cron/interface-cron-job.php` (contract)
- [x] Create `includes/cron/class-cron-manager.php`
  - Central registration with job locking (transients)
  - Custom schedules: `erh_six_hours`, `erh_twelve_hours`
  - WP-CLI commands: `wp erh cron run/list/status`
- [x] Create `includes/cron/class-price-update-job.php`
  - Daily price snapshots to `wp_product_daily_prices`
  - 2-year retention cleanup
- [x] Create `includes/cron/class-cache-rebuild-job.php`
  - Rebuild `wp_product_data` cache (twice daily)
  - Price history stats (3m, 6m, 12m averages, z-scores)
  - Popularity scoring algorithm
  - Computed specs (price_per_watt, range_per_lb, etc.)
- [x] Create `includes/cron/class-search-json-job.php`
  - Generate `/uploads/search_items.json` (twice daily)
  - Posts, products, tools
- [x] Create `includes/cron/class-notification-job.php`
  - Check price trackers every 6 hours
  - Send email alerts for price drops
  - 24-hour cooldown per product per user

### Admin Integration
- [x] Add Cron Jobs tab to Settings > ERideHero
  - Job status with last run time
  - "Run Now" buttons with AJAX handler
  - Next scheduled run display

---

## Phase 4.5: Geo-Aware Pricing System - COMPLETE

### Database Schema Updates
- [x] Update `wp_product_data` table - removed `price`, `instock`, `bestlink` columns
- [x] Add geo/currency columns to `wp_product_daily_prices`
- [x] Schema migration to v1.2.1 (forces column drops on plugin reactivation)

### Cache Rebuild Job Enhancements
- [x] Add `is_genuine_geo_price()` validation (prevents US fallbacks bleeding into other geos)
- [x] Expand `price_history` to store per-geo pricing data:
  - Current price, currency, stock status, retailer, best link
  - Period averages: 3m, 6m, 12m, all-time
  - Period lows: 3m, 6m, 12m, all-time
  - Period highs: 3m, 6m, 12m, all-time
- [x] Currency mapping for geos (US→USD, GB→GBP, DE→EUR, CA→CAD, AU→AUD)

### JSON Generator Jobs
- [x] Create `includes/cron/class-finder-json-job.php`
  - Generates per-product-type JSON files with full geo pricing
  - Files: `finder_escooter.json`, `finder_ebike.json`, etc.
- [x] Create `includes/cron/class-comparison-json-job.php`
  - Generates `comparison_products.json` with geo-keyed prices
  - Lightweight format for head-to-head comparison widgets

### Frontend Geo Service (Theme)
- [x] Create `erh-theme/assets/js/services/geo-config.js`
  - 5 supported regions: US, GB, EU, CA, AU
  - Country→region mapping (IPInfo country codes)
  - Region configuration (currency, symbol, flag, label)
- [x] Create `erh-theme/assets/js/services/geo-price.js`
  - `getUserGeo()` - IPInfo detection + country→region mapping
  - `setUserRegion()` - Manual region override for region selector
  - `formatPrice()` - Currency symbol formatting
  - ES6 module exports
- [x] Update `erh-theme/assets/js/components/comparison.js`
  - Import geo-price service
  - Read from region-keyed `prices` object
  - Format prices with correct currency symbols
- [x] Verify `erh-theme/assets/js/components/deals.js` compatibility
  - Already uses `getUserGeo()` - works with new region system

### Geo-Aware Optimizations (Session 2025-01-06)
- [x] Fixed DEV_COUNTRY_OVERRIDE bug (was permanently set to 'DE', now `null`)
- [x] Added window-level caching (`window.erhUserGeoData`) for getUserGeo()
- [x] Added Promise deduplication (`geoDetectionPromise`) to prevent concurrent IPInfo calls
- [x] Updated `setUserRegion()` and `clearUserRegion()` to clear window cache + priceCache
- [x] Consolidated DRY utilities:
  - `formatPrice()` - single source in geo-price.js
  - `getCurrencySymbol()` - single source in geo-price.js
  - `splitPrice()` - single source in product-card.js
  - Updated `account-trackers.js` to import from geo-price.js (removed local duplicate)
- [x] Fixed currency consistency in comparison.js:
  - Removed US fallback (was mixing currencies in comparisons)
  - Now matches finder.js behavior: show user's region price only, null if unavailable
  - Comment: "Users from unmapped countries already default to US at geo-detection level"

### 5-Region Architecture
- **Regions**: US, GB, EU, CA, AU
- **HFT stores**: Per-country (DE, FR, IT, ES, etc.)
- **Cache groups**: Country→Region (DE/FR/IT → EU)
- **Validation**: Currency match required (EU = EUR only)
- **Frontend fallback**: Unmapped countries default to US at geo-detection level (NOT at product level)
- **No currency mixing**: All components show prices in user's region currency only

---

## Phase 5: Theme Scaffold (Days 12-13) - COMPLETE

### Day 12: Theme Setup
- [x] Create `erh-theme/` directory structure
- [x] Create `package.json` with dependencies
- [x] Create CSS architecture with modular partials
- [x] Create `functions.php` with includes
- [x] Create `inc/enqueue.php` for asset loading
- [x] Create `inc/theme-setup.php` for theme supports
- [x] Create `inc/template-functions.php` for helper functions
- [x] Create `inc/acf-options.php` for ACF field registration

### Day 13: Header & Footer
- [x] Create `template-parts/header.php` with mega menu dropdowns
- [x] Create `template-parts/footer.php`
- [x] Create `header.php` and `footer.php` wrappers
- [x] Create SVG sprite system (`svg-sprite.php`)
- [x] Header works with search, mobile menu, dropdowns

---

## Phase 6: Single Review Template - COMPLETE

### Core Components
- [x] Create `template-parts/single-review.php` - Main review layout
- [x] Create `template-parts/components/gallery.php` - Image gallery with video lightbox
- [x] Create `template-parts/components/byline.php` - Author, date, updated date
- [x] Create `template-parts/components/quick-take.php` - Score badge + summary
- [x] Create `template-parts/components/pros-cons.php` - Pros/cons lists
- [x] Create `template-parts/components/price-intel.php` - Price intelligence section
- [x] Create `template-parts/components/tested-performance.php` - Performance data
- [x] Create `template-parts/components/full-specs.php` - Full specifications table
- [x] Create `template-parts/components/author-box.php` - Author bio with socials
- [x] Create `template-parts/components/related-reviews.php` - Related reviews grid
- [x] Create `template-parts/components/sticky-buy-bar.php` - Sticky purchase CTA

### Sidebar Components
- [x] Create `template-parts/sidebar/tools.php` - Finder, Deals, Compare links with product counts
- [x] Create `template-parts/sidebar/comparison.php` - Head-to-head comparison widget
- [x] Create `template-parts/sidebar/toc.php` - Table of contents

### JavaScript Components
- [x] Create `assets/js/app.js` - Main entry with dynamic imports
- [x] Create `assets/js/components/gallery.js` - Gallery with keyboard nav
- [x] Create `assets/js/components/price-intel.js` - Price data loading
- [x] Create `assets/js/components/mobile-menu.js`
- [x] Create `assets/js/components/search.js`
- [x] Create `assets/js/components/dropdown.js`
- [x] Create `assets/js/components/custom-select.js`
- [x] Create `assets/js/components/header-scroll.js`
- [x] Create `assets/js/components/popover.js`
- [x] Create `assets/js/components/modal.js`
- [x] Create `assets/js/components/tooltip.js`
- [x] Create `assets/js/components/toast.js`
- [x] Create `assets/js/components/toc.js`
- [x] Create `assets/js/components/sticky-buy-bar.js`
- [x] Create `assets/js/components/chart.js`
- [x] Create `assets/js/components/comparison.js`
- [x] Create `assets/js/components/deals.js`
- [x] Create `assets/js/components/deals-tabs.js`
- [x] Create `assets/js/components/finder-tabs.js`
- [x] Create `assets/js/components/archive-filter.js`
- [x] Create `assets/js/components/archive-sort.js`
- [x] Create `assets/js/components/auth-modal.js`
- [x] Create `assets/js/components/price-alert.js`

### CSS Architecture
- [x] Modular CSS with partials (`_variables.css`, `_base.css`, `_header.css`, etc.)
- [x] Component-specific stylesheets
- [x] Rating color system (`--color-rating-excellent`, etc.)

### Optimizations
- [x] Dynamic imports for conditional component loading
- [x] Components only load when their DOM elements exist
- [x] Removed all debug logging from production code

---

## Phase 6A: Homepage - COMPLETE

### Components
- [x] Deals section with geo-aware pricing
- [x] Head-to-head comparison widget
- [x] Hero section

### Fixes Applied
- [x] Fixed deals selector mismatch (`data-deals-section` → `#deals-section`)
- [x] Consolidated deals + counts into single API call
- [x] Removed unnecessary static imports (auth-modal, price-alert on homepage)

---

## Performance Optimizations - COMPLETE

### Server-Side Caching (REST API)
- [x] Extended price cache from 1 hour to 6 hours (`class-rest-prices.php`)
- [x] Added HFT invalidation hook (`hft_price_updated` → clears ERH transients)
- [x] Added transient caching to deals endpoint with debug header (`X-ERH-Cache: HIT/MISS`)
- [x] Consolidated deals + counts into single API response

### Client-Side Caching (JavaScript)
- [x] Extended JS in-memory cache from 5 minutes to 1 hour (`geo-price.js`)
- [x] Added request deduplication (`pendingRequests` Map prevents duplicate concurrent fetches)
- [x] Updated `price-intel.js` to use shared `getProductPrices()` function
- [x] Updated `sticky-buy-bar.js` to use shared `getProductPrices()` function

### Caching Architecture
```
Browser Request
     │
     ▼
┌─────────────────────────────┐
│ JS Request Deduplication    │  ← pendingRequests Map
└─────────────────────────────┘
     │
     ▼
┌─────────────────────────────┐
│ JS In-Memory Cache (1 hr)   │  ← priceCache Map
└─────────────────────────────┘
     │ (cache miss)
     ▼
┌─────────────────────────────┐
│ Server Transient (6 hrs)    │  ← wp_options / object cache
│ Invalidated by HFT scraper  │
└─────────────────────────────┘
     │ (cache miss)
     ▼
┌─────────────────────────────┐
│ Database Query              │
└─────────────────────────────┘
```

### Result
- Review page: 1 price API call instead of 2 (deduplicated)
- Homepage: 1 deals API call instead of 2 (consolidated)
- Second visits: ~10-20ms response (transient hit) vs ~150ms (cache miss)

---

## Phase 6B: Product Page (single-products.php) - IN PROGRESS

### Product Page Structure (Plan Created)
See plan file: `/home/rasmu/.claude/plans/resilient-jumping-feather.md`

**Layout:** Full-width, no sidebar - product database hub for specs/pricing

### Template Files
- [x] Create `single-products.php` - Main template with full layout
- [x] Create `template-parts/product/hero.php` - Product image, name, brand, score, review link
- [x] Create `template-parts/product/performance-profile.php` - Radar chart + highlights (placeholder)
- [x] Create `template-parts/product/comparison-widget.php` - Dark H2H widget (locked product)
- [x] Create `template-parts/product/specs-grouped.php` - Full specs by category
- [x] Create `template-parts/product/related.php` - Related products grid

### Section Order (Updated 2025-01-06)
1. Breadcrumb
2. Hero (product image, name, score, review/video links)
3. Price Intelligence (reuses `components/price-intel.php`)
4. Performance Profile (radar chart + "Great for..." highlights)
5. H2H Comparison Widget (dark style, current product locked)
6. **Related Products** (moved above specs)
7. Full Specifications (grouped by category)

### CSS & JavaScript
- [ ] Create `_single-product.css`
- [ ] Import in `style.css`
- [ ] Create `product-page.js` orchestrator
- [ ] Extract `RadarChart` from compare-results.js to `radar-chart.js`
- [ ] Add "locked" mode to comparison.js

### ACF Fields Required
- [ ] Add `review.review_post` (Post Object) - Links to review post
- [ ] Add `review.youtube_video` (URL) - YouTube video URL

---

## Phase 6C: Remaining Templates - PENDING

### Tool Pages
- [ ] Create `template-parts/finder/filters.php`
- [ ] Create `template-parts/finder/results.php`
- [ ] Create `templates/page-finder.php`
- [ ] Create `templates/page-deals.php`
- [ ] Test: Finder tool works

### Account Pages
- [ ] Create `template-parts/account/nav.php`
- [ ] Create `template-parts/account/reviews.php`
- [ ] Create `template-parts/account/trackers.php`
- [ ] Create `template-parts/account/settings.php`
- [ ] Create `templates/page-account.php`
- [ ] Test: All account views work

---

## Phase 7: Shortcodes (Days 17-18) - PENDING

- [ ] Create `shortcodes/class-shortcode-base.php`
- [ ] Migrate shortcodes (prioritize by usage):
  - [ ] `accordion`
  - [ ] `top3`
  - [ ] `toppicks`
  - [ ] `buying-guide-table`
  - [ ] `listicle-item`
  - [ ] `relatedproducts`
  - [ ] `bgoverview`
  - [ ] `thisprodprice`
  - [ ] `simplebgitem`
  - [ ] `super-accordion`
  - [ ] `video`
  - [ ] `checklist`
  - [ ] `jumplinks`
  - [ ] `bfdeal`
- [ ] Test: Each shortcode renders correctly

---

## Phase 8: JavaScript & Polish (Day 19) - PENDING

- [ ] Bundle all JS into `dist/main.min.js`
- [ ] Create `assets/js/src/search.js` (JSON-based search)
- [ ] Create `assets/js/src/price-tracker.js` (modal)
- [ ] Create `assets/js/src/reviews.js` (submission)
- [ ] Test: All interactive features work

---

## Phase 9: Testing & Launch (Days 20-21) - PENDING

### Testing Checklist
- [ ] All product pages render
- [ ] Finder tool filters/sorts correctly
- [ ] Price tracker create/update/delete
- [ ] User registration flow
- [ ] User login flow
- [ ] Password reset flow
- [ ] Review submission
- [ ] Email notifications (test mode)
- [ ] Search functionality
- [ ] Mobile responsive (test 3 breakpoints)
- [ ] No PHP errors in log
- [ ] Performance < 3s LCP

### Launch Prep
- [ ] Backup current site
- [ ] Create staging copy
- [ ] Deploy new theme to staging
- [ ] Full QA on staging
- [ ] Plan rollback procedure
- [ ] Switch to production
- [ ] Monitor error logs
- [ ] Clear all caches

---

## Quick Commands

```bash
# Plugin development
cd wp-content/plugins/erh-core
composer dump-autoload

# Theme development
cd wp-content/themes/erh-theme
npm install
npm run dev    # Watch mode
npm run build  # Production

# Test cron jobs manually
wp erh run-cron price-update
wp erh run-cron cache-rebuild

# Clear transients
wp transient delete --all
```

---

## Current Plugin Structure

```
erh-core/
├── erh-core.php                      # Bootstrap
├── composer.json                     # PSR-4 autoloading
├── includes/
│   ├── class-core.php               # Main orchestrator
│   │
│   ├── admin/
│   │   └── class-settings-page.php  # Settings > ERideHero (incl. Cron Jobs tab)
│   │
│   ├── post-types/
│   │   ├── class-product.php
│   │   └── class-review.php
│   │
│   ├── database/
│   │   ├── class-schema.php
│   │   ├── class-product-cache.php
│   │   ├── class-price-history.php
│   │   ├── class-price-tracker.php
│   │   └── class-view-tracker.php
│   │
│   ├── pricing/
│   │   ├── class-price-fetcher.php
│   │   ├── class-affiliate-resolver.php
│   │   ├── class-retailer-registry.php
│   │   ├── class-retailer-logos.php
│   │   └── class-deals-finder.php
│   │
│   ├── user/
│   │   ├── class-rate-limiter.php
│   │   ├── class-user-repository.php
│   │   ├── class-auth-handler.php
│   │   ├── class-user-preferences.php
│   │   ├── class-user-tracker.php
│   │   ├── interface-oauth-provider.php
│   │   ├── class-oauth-google.php
│   │   ├── class-oauth-facebook.php
│   │   ├── class-oauth-reddit.php
│   │   └── class-social-auth.php
│   │
│   ├── reviews/
│   │   ├── class-review-query.php
│   │   └── class-review-handler.php
│   │
│   ├── email/
│   │   ├── class-mailchimp-sync.php
│   │   ├── class-email-template.php
│   │   └── class-email-sender.php
│   │
│   └── cron/
│       ├── interface-cron-job.php
│       ├── class-cron-manager.php
│       ├── class-price-update-job.php
│       ├── class-cache-rebuild-job.php   # Geo-aware pricing, period stats
│       ├── class-finder-json-job.php     # Per-type finder JSON files
│       ├── class-comparison-json-job.php # Comparison widget JSON
│       ├── class-search-json-job.php
│       └── class-notification-job.php
│
└── vendor/                           # Composer autoload


## Current Theme Structure

```
erh-theme/
├── assets/
│   ├── css/
│   │   ├── _variables.css          # Design tokens, colors, spacing
│   │   ├── _base.css               # Reset, typography, global styles
│   │   ├── _header.css             # Header, navigation, search
│   │   ├── _footer.css             # Footer styles
│   │   ├── _buttons.css            # Button variants
│   │   ├── _forms.css              # Form elements
│   │   ├── _components.css         # Shared components (cards, badges, etc.)
│   │   ├── _single-review.css      # Review page layout
│   │   ├── _gallery.css            # Image gallery
│   │   ├── _price-intel.css        # Price intelligence section
│   │   ├── _pros-cons.css          # Pros/cons component
│   │   ├── _author-box.css         # Author box
│   │   ├── _content-grid.css       # Related content grids
│   │   ├── _latest-reviews.css     # Review cards, sidebar cards
│   │   └── style.css               # Main compiled stylesheet
│   ├── js/
│   │   ├── app.js                  # Main entry, dynamic imports
│   │   ├── services/
│   │   │   ├── geo-config.js       # 5-region config, country→region mapping
│   │   │   └── geo-price.js        # Region detection, price formatting, caching
│   │   ├── utils/
│   │   │   ├── dom.js              # DOM utilities (escapeHtml, etc.)
│   │   │   ├── product-card.js     # Product card utilities (splitPrice, createProductCard)
│   │   │   └── carousel.js         # Carousel navigation utility
│   │   └── components/
│   │       ├── gallery.js          # Image gallery with lightbox
│   │       ├── price-intel.js      # Price data loading (skeleton states)
│   │       ├── mobile-menu.js      # Mobile navigation
│   │       ├── search.js           # Search functionality
│   │       ├── dropdown.js         # Dropdown menus
│   │       ├── comparison.js       # H2H comparison widget (geo-aware)
│   │       ├── deals.js            # Deals section (geo-aware)
│   │       ├── finder.js           # Finder tool (geo-aware)
│   │       ├── finder-table.js     # Finder table view (geo-aware)
│   │       ├── chart.js            # Price history charts
│   │       ├── price-chart.js      # Price chart component
│   │       ├── toc.js              # Table of contents
│   │       ├── modal.js            # Modal dialogs
│   │       ├── tooltip.js          # Tooltips
│   │       ├── popover.js          # Popovers
│   │       ├── toast.js            # Toast notifications
│   │       ├── auth-modal.js       # Login/register modal
│   │       ├── price-alert.js      # Price alert modal
│   │       ├── account-trackers.js # User tracker management
│   │       └── ... (more components)
│   └── images/
│       └── logos/                  # Retailer logos
├── inc/
│   ├── enqueue.php                 # Asset registration
│   ├── theme-setup.php             # Theme supports, menus
│   ├── template-functions.php      # Helper functions (icons, scores, specs)
│   ├── finder-config.php           # Finder tool configuration
│   └── acf-options.php             # ACF options pages & user fields
├── template-parts/
│   ├── header.php                  # Site header with mega menu
│   ├── footer.php                  # Site footer
│   ├── svg-sprite.php              # Inlined SVG sprite
│   ├── single-review.php           # Review post template
│   ├── components/
│   │   ├── gallery.php             # Image gallery
│   │   ├── byline.php              # Author & dates
│   │   ├── quick-take.php          # Score + summary
│   │   ├── pros-cons.php           # Pros/cons lists
│   │   ├── price-intel.php         # Price intelligence
│   │   ├── tested-performance.php  # Performance data
│   │   ├── full-specs.php          # Specifications table
│   │   ├── author-box.php          # Author bio with socials
│   │   ├── related-reviews.php     # Related reviews grid
│   │   └── sticky-buy-bar.php      # Sticky purchase CTA bar
│   ├── product/                    # Product page components (IN PROGRESS)
│   │   ├── hero.php                # Product hero section
│   │   ├── performance-profile.php # Radar chart + highlights
│   │   ├── comparison-widget.php   # H2H comparison (locked mode)
│   │   ├── specs-grouped.php       # Grouped specifications
│   │   └── related.php             # Related products
│   ├── sidebar/
│   │   ├── tools.php               # Finder, Deals, Compare links
│   │   ├── comparison.php          # H2H comparison widget
│   │   └── toc.php                 # Table of contents
│   └── home/
│       ├── hero.php                # Homepage hero
│       ├── deals.php               # Deals section
│       └── comparison.php          # Comparison widget
├── functions.php
├── header.php
├── footer.php
├── single.php                      # Routes to single-review.php
├── single-products.php             # Product database page
├── page-finder.php                 # Finder tool
├── front-page.php                  # Homepage
└── style.css                       # Theme header
```

---

## Key Decisions Made

| Decision | Choice | Rationale |
|----------|--------|-----------|
| User API | REST (not AJAX) | Modern, stateless, easier to test |
| Rate Limiting | Transients | Simple, no external deps, ~100 users |
| Social Login | Custom OAuth | Replaces Nextend plugin, full control |
| Account Linking | Same email = same account | Better UX, auto-links social |
| Mailchimp | API v3 + Webhook | Two-way sync for unsubscribes |
| HFT Integration | Filters | Use ERH `products` CPT, disable HFT CPT |
| Settings | WP Settings API | Native, proper sanitization |
| Reviews API | REST (not AJAX) | Consistent with user system |
| Notifications | Every 6 hours | Balance responsiveness vs server load |
| Cron Admin | "Run Now" buttons | User prefers GUI over WP-CLI |
| Job Locking | Transients | Prevent duplicate cron execution |
| Geo Pricing | 5 regions (US, GB, EU, CA, AU) | Simple UX, covers ~90%+ of revenue potential |
| EU Region | Aggregate DE/FR/IT/ES/etc. | One region for EUR, HFT stores granular country data |
| Geo Validation | Currency match required | EU = EUR only, prevents US fallback pollution |
| JSON Generation | Static files in uploads | Fast frontend loading, CDN-friendly |
| JS Loading | Dynamic imports | Components only load when DOM elements exist |
| Author Socials | ACF user fields (PHP) | Registered alongside existing profile fields |
| Score Badges | Square with rounded corners | Rating colors based on score tier |
| Price Cache TTL | 6 hours + HFT invalidation | Prices update 2x daily, invalidate on scrape |
| JS Request Dedup | pendingRequests Map | Prevents duplicate concurrent API calls |
| Deals API | Combined deals + counts | Single request instead of two |
| Geo Detection Cache | Window-level + Promise dedup | Single IPInfo call per page load |
| Currency Consistency | No product-level US fallback | Unmapped countries default at detection level |
| DRY Utilities | formatPrice/getCurrencySymbol in geo-price.js | All components import from single source |

---

## WordPress Options Used

### Social Login
- `erh_google_client_id`
- `erh_google_client_secret`
- `erh_facebook_app_id`
- `erh_facebook_app_secret`
- `erh_reddit_client_id`
- `erh_reddit_client_secret`

### Mailchimp
- `erh_mailchimp_api_key`
- `erh_mailchimp_list_id`

### General
- `erh_email_preferences_page_id`
- `erh_db_version`

---

## Cron Jobs

| Job | Hook | Schedule | Description |
|-----|------|----------|-------------|
| Price Update | `erh_cron_price-update` | Daily | Record daily prices to wp_product_daily_prices |
| Cache Rebuild | `erh_cron_cache-rebuild` | Every 2 hours | Rebuild wp_product_data with geo-aware stats |
| Finder JSON | `erh_cron_finder-json` | Every 2 hours | Generate finder_*.json files per product type |
| Comparison JSON | `erh_cron_comparison-json` | Every 2 hours | Generate comparison_products.json |
| Search JSON | `erh_cron_search-json` | Twice daily | Generate search_items.json |
| Notifications | `erh_cron_notifications` | Every 6 hours | Check price trackers, send alerts |

### Custom Schedules
- `erh_two_hours` - Every 2 hours
- `erh_six_hours` - Every 6 hours
- `erh_twelve_hours` - Every 12 hours

### Job Transients (Locking)
- `erh_cron_lock_{job-id}` - Prevents concurrent execution (5 min TTL)
- `erh_cron_last_run_{job-id}` - Last successful run timestamp

---

## User Meta Keys

```php
// Email preferences
UserRepository::META_PRICE_TRACKER_EMAILS    // 'price_trackers_emails'
UserRepository::META_SALES_ROUNDUP_EMAILS    // 'sales_roundup_emails'
UserRepository::META_SALES_ROUNDUP_FREQUENCY // 'sales_roundup_frequency'
UserRepository::META_NEWSLETTER_SUBSCRIPTION // 'newsletter_subscription'

// Social login
UserRepository::META_GOOGLE_ID               // 'erh_google_id'
UserRepository::META_FACEBOOK_ID             // 'erh_facebook_id'
UserRepository::META_REDDIT_ID               // 'erh_reddit_id'

// User tracking
UserRepository::META_PREFERENCES_SET         // 'erh_preferences_set'
UserRepository::META_LAST_DEALS_EMAIL        // 'last_deals_email_sent'
UserRepository::META_REGISTRATION_IP         // 'registration_ip'
```

---

## Phase 10: ACF Field Restructuring (Post-Launch)

**Do this AFTER the main rebuild is complete and stable.**

See `ACF_RESTRUCTURE_PLAN.md` for full details.

**Estimated time**: 2 days focused work
**Risk**: Low (can be done incrementally, old fields work as fallback)

---

## Phase 11: Performance Profile Relative Insights (Post-Launch)

**Do this AFTER populating complete price data for all products.**

### Overview
Replace the hardcoded "What to Know" section on product pages with data-driven relative insights based on category-wide statistics.

### Requirements
- [ ] Complete price data for all products (required for "value" comparisons)
- [ ] Category averages computed in cache rebuild job:
  - Average price per category
  - Average weight per category
  - Average range per category
  - Average battery capacity per category
  - Price-to-spec ratios ($/Wh, $/mile range, $/lb, etc.)
- [ ] Percentile calculations for key specs

### Insight Types to Implement

**Relative Value Insights:**
- "Range is 23% above average for this price range"
- "Battery capacity is in the top 25% for e-scooters"
- "Good value: $1.20/Wh vs category average $1.45/Wh"
- "Lightweight for its range (0.8 lbs/mile)"

**Comparison-Based:**
- "Heavier than 75% of e-scooters in this price range"
- "Faster charging than average (4h vs 6h typical)"
- "More range per dollar than competitors"

### Implementation Steps
1. [ ] Add category statistics to `class-cache-rebuild-job.php`
   - Compute averages, percentiles per product type
   - Store in wp_options or dedicated table
2. [ ] Create `includes/insights/class-product-insights.php`
   - Rules engine for generating insight text
   - Thresholds for "above average", "top 25%", etc.
3. [ ] Update `template-parts/product/performance-profile.php`
   - Call insights generator
   - Replace hardcoded content
4. [ ] Add insights to finder JSON for preview cards (optional)

### Example Output
```
✓ Great range for the price — 23% above average
✓ Lightweight at 42 lbs — easier to carry
⚠ Longer charge time — 6 hours vs 4.5h average
⚠ Lower top speed — 20 mph vs 25 mph typical
```

**Estimated time**: 3-4 days
**Dependencies**: Complete price data, category-wide product specs
