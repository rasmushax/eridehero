# ERideHero Implementation Checklist

Use this file alongside CLAUDE.md to track progress.

## Phase 1: Plugin Scaffold (Days 1-2)

### Day 1: Setup
- [ ] Create `erh-core/` directory structure
- [ ] Create `composer.json` with PSR-4 autoloading:
  ```json
  {
    "name": "eridehero/erh-core",
    "autoload": {
      "psr-4": {
        "ERH\\": "includes/"
      }
    }
  }
  ```
- [ ] Create `erh-core.php` main plugin file
- [ ] Create `includes/class-erh-core.php` main class
- [ ] Run `composer dump-autoload`

### Day 2: Foundation
- [ ] Create `includes/post-types/class-product.php`
- [ ] Create `includes/post-types/class-review.php`
- [ ] Create `includes/database/class-schema.php` (all table creation)
- [ ] Register activation hook for schema creation
- [ ] Test: Plugin activates without errors

## Phase 2: Core Pricing Classes (Days 3-6)

### Day 3: Affiliate Resolution
- [ ] Create `includes/pricing/class-affiliate-resolver.php`
  - Port `extractDomain()` logic from `affiliatelinks.php`
  - Handle: ShareASale, Avantlink, CJ, Awin, Impact, PartnerBoost, Sovrn
- [ ] Create `includes/pricing/class-retailer-registry.php`
  - Port `prettydomain()` mapping
  - Port `getShopImg()` logo mapping
- [ ] Test: Domain resolution works for all networks

### Day 4: Price Fetcher
- [ ] Create `includes/pricing/interface-price-fetcher.php`
- [ ] Create `includes/pricing/class-content-egg-fetcher.php`
  - Port `getPrices()` logic
  - Include priority sorting
  - Include domain priority from ACF options
- [ ] Test: `$fetcher->getPrices($product_id)` returns same as old function

### Day 5: Database Classes
- [ ] Create `includes/database/class-product-cache.php` (wp_product_data)
- [ ] Create `includes/database/class-price-history.php` (wp_product_daily_prices)
- [ ] Create `includes/database/class-price-tracker.php` (wp_price_trackers)
- [ ] Create `includes/database/class-view-tracker.php` (wp_product_views)
- [ ] Test: CRUD operations work on all tables

### Day 6: Deals Finder
- [ ] Create `includes/pricing/class-deals-finder.php`
  - Port `getDeals()` logic
  - Use new database classes
- [ ] Test: Returns same results as current implementation

## Phase 3: User System (Days 7-8)

### Day 7: Authentication
- [ ] Create `includes/user/class-auth-handler.php`
  - Login handler (port `login.php`)
  - Register handler (port `register.php`) with rate limiting
  - Lost password handler (port `lost_password.php`)
  - Reset password handler (port `reset-password.php`)
- [ ] Register AJAX actions
- [ ] Test: Full auth flow works

### Day 8: User Features
- [ ] Create `includes/user/class-user-preferences.php`
  - Email preference management
  - Port settings form handlers
- [ ] Create `includes/user/class-user-tracker.php`
  - Port `price_tracker.php` AJAX handlers
  - Check price data
  - Check/set/delete trackers
- [ ] Test: Price tracker CRUD works

## Phase 4: Reviews & Cron (Days 9-10)

### Day 9: Reviews
- [ ] Create `includes/reviews/class-review-handler.php`
  - Port `submit_review.php`
  - Image upload handling
- [ ] Create `includes/reviews/class-review-query.php`
  - Port `getReviews()` function
  - Ratings distribution calculation
- [ ] Test: Review submission and display works

### Day 10: Cron System
- [ ] Create `includes/cron/class-cron-manager.php`
- [ ] Create `includes/cron/class-price-update-job.php` (daily prices)
- [ ] Create `includes/cron/class-cache-rebuild-job.php` (product data)
- [ ] Create `includes/cron/class-search-json-job.php`
- [ ] Create `includes/cron/class-notification-job.php` (price alerts)
- [ ] Test: Manual trigger works for each job

## Phase 5: Email System (Day 11)

- [ ] Create `includes/email/class-email-template.php`
- [ ] Create `includes/email/class-email-sender.php`
- [ ] Create `includes/email/class-price-alert-email.php`
- [ ] Create `includes/email/class-deals-digest-email.php`
- [ ] Test: Emails render correctly and send

## Phase 6: Theme Scaffold (Days 12-13)

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

## Phase 7: Templates (Days 14-16)

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

## Phase 8: Shortcodes (Days 17-18)

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

## Phase 9: JavaScript & Polish (Day 19)

- [ ] Bundle all JS into `dist/main.min.js`
- [ ] Create `assets/js/src/search.js` (JSON-based search)
- [ ] Create `assets/js/src/price-tracker.js` (modal)
- [ ] Create `assets/js/src/reviews.js` (submission)
- [ ] Test: All interactive features work

## Phase 10: Testing & Launch (Days 20-21)

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

## Files to Create First

Start with these files in order:

1. `erh-core/erh-core.php`
2. `erh-core/composer.json`
3. `erh-core/includes/class-erh-core.php`
4. `erh-core/includes/pricing/class-price-fetcher.php` (reads from HFT tables)
5. `erh-core/includes/pricing/class-affiliate-resolver.php`

Get the pricing foundation working first - everything else depends on it.

---

## Phase 10: ACF Field Restructuring (Post-Launch)

**Do this AFTER the main rebuild is complete and stable.**

See `ACF_RESTRUCTURE_PLAN.md` for full details.

### Summary
- [ ] Create new `e-scooters` nested field group
- [ ] Create new `e-skateboards` nested field group  
- [ ] Create new `euc` nested field group
- [ ] Create new `hoverboards` nested field group
- [ ] Enhance existing `e-bikes` group
- [ ] Write data migration script
- [ ] Run migration on staging
- [ ] Update theme templates to use new field paths
- [ ] Update cron jobs to use new field paths
- [ ] Test everything thoroughly
- [ ] Run migration on production
- [ ] Remove old flat fields

**Estimated time**: 2 days focused work
**Risk**: Low (can be done incrementally, old fields work as fallback)
