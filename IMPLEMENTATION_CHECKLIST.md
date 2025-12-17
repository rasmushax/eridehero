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
- [x] Create `erh-theme/assets/js/services/geo-price.js`
  - `getUserGeo()` - IPInfo integration with localStorage caching
  - `formatPrice()` - Currency symbol formatting
  - ES6 module exports
- [x] Update `erh-theme/assets/js/components/comparison.js`
  - Import geo-price service
  - Read from geo-keyed `prices` object
  - Format prices with correct currency symbols

### Geo Configuration
- Supported geos: US, GB, DE, CA, AU
- Price validation: Geo match OR currency match OR US default
- Frontend fallback: Show US price if user's geo has no data

---

## Phase 5: Theme Scaffold (Days 12-13) - PENDING

### Day 12: Theme Setup
- [ ] Create `erh-theme/` directory structure
- [ ] Create `package.json` with Tailwind dependencies
- [ ] Create `tailwind.config.js` with ERH colors
- [ ] Create `postcss.config.js`
- [ ] Create `assets/css/src/main.css` with Tailwind directives
- [ ] Run `npm run build` - verify CSS compiles
- [ ] Create `functions.php` with includes
- [ ] Create `inc/enqueue.php` for asset loading
- [ ] Create `inc/theme-setup.php` for theme supports

### Day 13: Header & Footer
- [ ] Create `template-parts/header.php` (port from header-menu.html we built!)
- [ ] Create `template-parts/footer.php`
- [ ] Create `header.php` and `footer.php` wrappers
- [ ] Test: Header renders correctly with dropdowns working

---

## Phase 6: Templates (Days 14-16) - PENDING

### Day 14: Product Templates
- [ ] Create `template-parts/product/card.php`
- [ ] Create `template-parts/product/specs-table.php`
- [ ] Create `template-parts/product/price-box.php`
- [ ] Create `template-parts/product/offers-modal.php`
- [ ] Create `templates/single-products.php`
- [ ] Test: Single product page renders

### Day 15: Tool Pages
- [ ] Create `template-parts/finder/filters.php`
- [ ] Create `template-parts/finder/results.php`
- [ ] Create `templates/page-finder.php`
- [ ] Create `templates/page-deals.php`
- [ ] Create JS: `assets/js/src/finder.js`
- [ ] Test: Finder tool works

### Day 16: Account Pages
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


## Current Theme Structure (Partial)

```
erh-theme/
├── assets/
│   ├── css/
│   │   ├── src/
│   │   │   └── main.css
│   │   └── dist/
│   │       └── style.css
│   └── js/
│       ├── services/
│       │   └── geo-price.js              # Geo detection & price formatting
│       └── components/
│           └── comparison.js             # H2H comparison widget (geo-aware)
├── functions.php
└── style.css
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
| Geo Pricing | Per-geo in price_history | US fallbacks only on frontend, not in data layer |
| Geo Validation | Currency + geo_target check | Prevents US prices polluting non-US geo buckets |
| JSON Generation | Static files in uploads | Fast frontend loading, CDN-friendly |

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
