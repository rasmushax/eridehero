# Comprehensive Code Quality Analysis: erh-core & erh-theme

**Date:** 2026-02-08
**Scope:** Full codebase audit — code quality, DRY violations, consistency, performance, security, and architecture across `erh-core/` and `erh-theme/`.

---

## CRITICAL Issues (Fix First)

### 1. Category/Product Type Mappings Duplicated in 6 Files
**DRY violation — single biggest maintenance risk**

The same category slug-to-product-type mapping is independently hardcoded in:
- `erh-core/includes/api/class-rest-products.php:63-69` — `$category_map`
- `erh-core/includes/api/class-rest-deals.php:53-60` — `$category_map` (adds `'all'`)
- `erh-core/includes/email/class-email-sender.php:111-117` — category names for email subjects
- `erh-core/includes/cron/class-cache-rebuild-job.php:56-62` — `PRODUCT_TYPES` constant
- `erh-core/includes/user/class-user-repository.php:46-52` — `VALID_ROUNDUP_TYPES`
- `erh-core/includes/comparison/calculators/class-advantage-calculator-factory.php:114-134` — `normalize_product_type()`

**All should use `CategoryConfig` as single source of truth.** Any new product type requires updating 6 files today.

---

### 2. Hardcoded REST URL Fallbacks Break Subfolder Installs
**Violates CLAUDE.md's "CRITICAL" subfolder-safe URL rule**

11 JS files have hardcoded `/wp-json/erh/v1/` fallback instead of exclusively using `erhData.restUrl`:

| File | Line |
|------|------|
| `erh-theme/assets/js/services/geo-price.js` | 76 |
| `erh-theme/assets/js/components/similar-products.js` | 41 |
| `erh-theme/assets/js/utils/deals-utils.js` | 18 |
| `erh-theme/assets/js/components/account-trackers.js` | 14 |
| `erh-theme/assets/js/components/account-settings.js` | 10 |
| `erh-theme/assets/js/components/complete-profile.js` | 10 |
| `erh-theme/assets/js/components/auth-modal.js` | 26 |
| `erh-theme/assets/js/components/price-alert.js` | 25 |
| `erh-theme/assets/js/components/contact.js` | 234 |
| `erh-theme/assets/js/components/onboarding.js` | 10 |
| `erh-theme/assets/js/components/deals-page.js` | 29 |

These will all silently break in any subfolder WordPress install (e.g., `localhost/eridehero/`).

---

### 3. TIE_THRESHOLD Mismatch Between PHP and JS
**Compare system renders differently on server vs client**

- **PHP** (`erh-core/includes/config/class-spec-config.php:92`): `TIE_THRESHOLD = 3` (3% difference = tie)
- **JS** (`erh-theme/assets/js/config/compare-config.js:90`): `TIE_THRESHOLD = 0` (any difference = winner)

SSR comparison may show a tie while JS hydration shows a winner for the same products.

---

### 4. EU Countries List Differs Between PHP and JS

- **PHP** (`class-geo-config.php:29-33`): 27 EU countries (correct)
- **JS** (`geo-price.js:713`): 29 entries — includes Norway (`NO`) and Switzerland (`CH`), which are NOT EU members

Users in Norway/Switzerland get EUR pricing in JS but may be rejected by PHP geo logic.

---

### 5. No Rate Limiting on Public REST Endpoints

All read-only endpoints use `'permission_callback' => '__return_true'` with no throttling:
- `/products/{id}/similar`
- `/products/{id}/analysis`
- `/prices/best` (accepts up to 100 IDs per request)
- `/deals`

Any script can hammer these at unlimited speed.

---

## HIGH Issues

### 6. REST API Field Name Inconsistency: `instock` vs `in_stock`

- **PHP REST response** (`class-rest-products.php:469`): Returns `'instock'`
- **JS consumers** (`pricing-ui.js`, `listicle-item.js`, `price-intel.js`): Expect `'in_stock'`

This is either a live bug or there's a transformation layer not visible in the main code path. Needs verification.

---

### 7. Missing Input Validation on REST Endpoints

| Endpoint | Issue | File:Line |
|----------|-------|-----------|
| `/prices/best` | No max limit on `ids` array — can send thousands | `class-rest-prices.php:193` |
| `/products/{cat}/listicle` | `category_key` sanitized but not validated against allowed values | `class-rest-listicle.php:76` |
| Multiple endpoints | `geo` param uppercased without null check — `strtoupper(null)` | `class-rest-products.php:182`, `class-rest-deals.php:155` |
| `/prices/{id}` | No validation that `geo` is a valid region before querying | `class-price-fetcher.php:123` |

---

### 8. XSS Risk in Offer Rendering

`erh-theme/assets/js/utils/pricing-ui.js:95` uses template literals with unsanitized HFT data:

```javascript
`<img src="${offer.logo_url}" alt="${offer.retailer}" ...>`
```

`offer.retailer` and `offer.logo_url` come from external HFT API. If HFT data is compromised, this is injectable. Should use `textContent` for retailer name and validate URL format.

---

### 9. IPInfo API Token Exposed in Frontend HTML

`erh-theme/inc/enqueue.php:138-144` injects the IPInfo token directly into page source via `wp_add_inline_script`. Any visitor can read and abuse the token. Should proxy geo detection through a backend endpoint instead.

---

## MODERATE Issues

### 10. DRY: Debug Logger Duplicated 4x in JS

Identical debug/logging pattern independently implemented in:
- `erh-theme/assets/js/components/price-intel.js:14-20`
- `erh-theme/assets/js/components/listicle-item.js:21-27`
- `erh-theme/assets/js/components/similar-products.js:18-30`
- `erh-theme/assets/js/services/geo-price.js:38-55`

**Fix:** Extract to shared `utils/logger.js` with `createLogger(prefix)` factory.

---

### 11. DRY: `getRestUrl()` Function Duplicated

Same function in `geo-price.js:70-77` and `similar-products.js:37-42`.

**Fix:** Move to a shared `utils/api.js`.

---

### 12. DRY: `PERIOD_LABELS` Dictionary Duplicated

Identical object in `price-intel.js:22-28` and `listicle-item.js:21-27`.

**Fix:** Move to `config/periods.js`.

---

### 13. DRY: Boolean Truthiness Check Repeated 6+ Times

`value === true || value === 'Yes' || value === 'yes' || value === '1' || value === 1` appears in:
- `erh-theme/assets/js/components/compare/renderers.js` (lines 172, 198, 281)
- `erh-theme/template-parts/compare/components/spec-value.php` (lines 66, 103, 147)
- `erh-theme/template-parts/compare/components/product-thumb.php` (lines 103, 107)

PHP and JS implementations differ slightly (PHP has `is_bool()` check first). Should be extracted to shared utility in both languages.

---

### 14. Hardcoded Geo Region in erh-core

`class-rest-products.php:56`: `SUPPORTED_REGIONS = ['US', 'GB', 'EU', 'CA', 'AU']` hardcoded instead of using `GeoConfig::REGIONS`.

---

### 15. Currency/Region Config Duplicated in JS

Two separate definitions of currency data:
- `erh-theme/assets/js/services/geo-price.js:663-669` — Currency symbols/locales
- `erh-theme/assets/js/services/geo-config.js:16-47` — Region config with currencies

No single source of truth on the JS side.

---

### 16. Inconsistent Error Handling Across REST Endpoints

| Pattern | Files |
|---------|-------|
| Returns `WP_Error` with i18n `__()` | `class-rest-products.php`, `class-contact-handler.php` |
| Returns `WP_Error` without i18n | `class-rest-prices.php` |
| Returns 200 for all cases, no errors | `class-rest-deals.php` |
| Different HTTP status codes for similar errors | Mixed across all |

---

### 17. Inconsistent Error Return Types in Database Classes

- `PriceTracker::create()` returns `int|false`
- `ProductCache::get()` returns `array|null`
- `EmailQueueRepository::queue()` returns `int|false`

No consistent contract for "operation failed."

---

### 18. God Class: `CacheRebuildJob`

Constructor takes 7 dependencies (`PriceFetcher`, `ProductCache`, `PriceHistory`, `PriceTracker`, `ViewTracker`, `CronManager`, `ProductScorer`), suggesting it does too much in one place. Consider splitting into focused job classes.

---

### 19. Long Method: `calculate_head_to_head()`

`erh-core/includes/comparison/calculators/class-escooter-advantages.php:47-180+` — 130+ lines with deep nesting. Should be decomposed into smaller methods like `process_composite_advantage()`, `process_spec_comparison()`, `build_advantages_map()`.

---

### 20. Cache TTL Inconsistency

| Cache | TTL | Cron Refresh |
|-------|-----|-------------|
| Price Intel | 6 hours | 2 hours |
| Price History | 6 hours | daily |
| Similar Products | 2 hours | 2 hours |
| Deals | 1 hour | 2 hours |
| Listicle Specs | 6 hours | 2 hours |

Price Intel and Listicle Specs have 6-hour TTL but underlying data refreshes every 2 hours. Caches serve stale data for up to 4 hours after a cron run.

---

### 21. PHP/JS Spec Rendering Drift

`erh_format_spec_value()` in `spec-value.php` handles arrays (suspension front/rear), feature arrays with "show 3 + N more" truncation, and objects. The JS `formatSpecValue` in `compare-config.js` delegates to a simpler `formatValue` that may not handle all these edge cases. Risk of SSR/hydration mismatch on compare pages.

---

### 22. Missing `async/await` Error Guards

`erh-theme/assets/js/components/price-intel.js:833-846` — `initAlternatives()` calls `getUserGeo()` and destructures result without null check. If geo detection fails, `userGeo.geo` throws.

---

### 23. Cache Invalidation Race Condition

When HFT updates prices, PHP caches are cleared but the hook timing isn't guaranteed. Frontend may continue to see old cached data if the invalidation hasn't fully propagated. No documented verification path for cache coherence.

---

### 24. Product Analysis Caching Disabled But Code Left Behind

`class-rest-products.php:274-282, 363-364` — Cache code commented out with "TEMPORARILY DISABLED." `CacheKeys::productAnalysis()` may still exist unused. Clean up or re-enable.

---

### 25. Overfetch in Product Filtering

`erh-core/includes/database/class-product-cache.php:148` fetches `$limit * 3` rows when geo filtering is needed, then filters in PHP. Should push geo filter into SQL WHERE clause instead.

---

## MINOR Issues

### 26. `CacheKeys` Uses camelCase Methods

`CacheKeys::listicleSpecs()`, `CacheKeys::priceIntel()`, etc. use camelCase while all other PHP classes use snake_case per PSR-2/WordPress convention.

---

### 27. Dead Code in `class-core.php`

`init_ajax()` method (lines 676-681) is empty with a comment placeholder. Remove or implement.

---

### 28. Hardcoded Magic Numbers

- `class-view-tracker.php:267` — cleanup probability `100`
- `class-view-tracker.php:282` — retention period `90` days
- Various TTL values throughout

Should be class constants.

---

### 29. Missing Database Row Casting Abstraction

`PriceTracker`, `ProductCache`, `PriceHistory` all implement nearly identical `cast_row()` methods for type conversion. Could share a trait or base class.

---

### 30. Inconsistent REST Route Naming

- `/products/{id}/similar` — nested resource (good)
- `/prices/{id}` — separate top-level (inconsistent, could be `/products/{id}/prices`)
- `/deals` — flat (OK for collections, but breaks RESTful hierarchy)

---

### 31. No Persistent JS Price Cache

`geo-price.js:57` uses in-memory `Map()` for price cache. Lost on every page navigation. Could use `sessionStorage` with TTL for cross-page persistence.

---

### 32. CSS Hardcoded rgba Values

`erh-theme/assets/css/_comparison.css` lines 43, 52 use `rgba(94, 44, 237, 0.22)` and `rgba(56, 189, 180, 0.22)` instead of CSS custom properties defined in `_variables.css`.

---

## Summary

| Priority | Count | Key Themes |
|----------|-------|-----------|
| **CRITICAL** | 5 | DRY category maps, hardcoded URLs, PHP/JS config mismatch, no rate limiting |
| **HIGH** | 4 | API field mismatch, missing validation, XSS risk, token exposure |
| **MODERATE** | 16 | DRY in JS, error handling inconsistency, cache issues, long methods |
| **MINOR** | 7 | Naming conventions, dead code, magic numbers, CSS variables |

## Recommended Fix Order

1. **Category mappings** — Consolidate all 6 files to use `CategoryConfig` (highest ROI, eliminates whole class of bugs)
2. **Hardcoded REST URLs** — Replace 11 JS fallbacks with error throw or strict `erhData` requirement
3. **TIE_THRESHOLD + EU countries** — Sync PHP/JS constants (quick fixes with big impact)
4. **Input validation** — Add `geo` null checks, ID array limits, category validation to REST endpoints
5. **XSS sanitization** — Escape HFT offer data in `pricing-ui.js`
6. **Rate limiting** — Add IP-based throttling to public endpoints
7. **JS DRY extraction** — Create `utils/logger.js`, `utils/api.js`, `config/periods.js`
8. **Error handling standardization** — Consistent `WP_Error` codes + i18n across all REST endpoints
9. **Cache TTL alignment** — Match TTLs to cron refresh intervals
10. **Everything else** — Minor naming, dead code, refactoring
