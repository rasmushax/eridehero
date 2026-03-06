# CSS Code Splitting Design

## Problem

The ERH theme loads a single ~130KB minified CSS bundle on every page. ~40% of CSS is unused on any given page (e.g., finder CSS on product pages, compare CSS on the homepage). LiteSpeed's CCSS (Critical CSS) feature attempts to compensate but causes FOUC/CLS on complex pages like product reviews.

## Solution

Split the monolithic bundle into a base bundle (loaded everywhere) + 8 page-specific bundles (loaded conditionally). No partials are modified — just new entry files that import existing partials in different combinations.

## Bundle Definitions

### base.css (~100KB source, ~55-65KB minified)

Loaded on every page. Contains global UI, layout, and JS-triggered components.

```
_variables.css          — CSS custom properties (foundation)
_base.css               — Reset, container, utilities
_typography.css         — Headings, text, links
_buttons.css            — Button variants
_forms.css              — Inputs, validation, toggles
_components.css         — Cards, badges, skeletons
_modal.css              — Modal base
_toast.css              — Toast notifications
_auth-modal.css         — Login/register modal (JS-triggered, any page)
_select-drawer.css      — Dropdown component
_header.css             — Navigation, search, mobile menu
_footer.css             — Footer
_breadcrumb.css         — Breadcrumb nav
_page-title.css         — Page title
_page-sections.css      — Section spacing
_shortcode-tables.css   — Shortcode tables (can appear in any content)
```

Moved OUT of base (page-specific only):
- _price-alert-modal.css (11KB) — only product + finder pages
- _sidebar.css (16KB) — only product + archive pages

### home.css — front-page.php

```
_hero.css
_features.css
_comparison.css
_youtube.css
_cta.css
_buying-guides.css
_articles.css
_hub.css
_social-proof.css
_latest-reviews.css
_deals.css
_content-grid.css
```

### product.css — single-products.php

```
_single-product.css
_content-split.css
_single-review.css
_gallery.css
_byline.css
_author-box.css
_pros-cons.css
_price-intel.css
_inline-price-bar.css
_coupons.css
_content-grid.css
_sidebar.css
_price-alert-modal.css
```

### finder.css — page-finder.php

```
_finder.css
_finder-table.css
_price-alert-modal.css
```

### compare.css — page-compare.php

```
_compare-hub.css
_compare-results.css
_radar-chart.css
```

### deals.css — page-deals.php, page-deals-category.php

```
_deals.css
_content-page.css
_content-grid.css
```

### archive.css — page-search.php, page-articles.php, page-buying-guides.php, page-reviews.php, page-escooter-reviews.php, author.php, archive-tool.php

```
_archive.css
_search-page.css
_byline.css
_content-grid.css
_articles.css
_sidebar.css
```

### tools.css — single-tool*.php

```
_tools.css
_laws-map.css
```

### account.css — page-account.php, page-email-preferences.php, page-complete-profile.php, page-login.php, page-reset-password.php

```
_account.css
_onboarding.css
_auth.css
```

## Build System

### Entry Files

Create `assets/css/bundles/` directory with 9 entry files. Each file contains only @import statements pointing to the parent partials (e.g., `@import '../_variables.css';`).

### Build Script

Replace the single PostCSS call with a script that processes all entry files:

```json
"build:css": "node build-css.js"
```

A small Node script that runs PostCSS on each `bundles/*.css` file, outputting to `dist/<name>.min.css`. Same PostCSS pipeline (postcss-import + autoprefixer + cssnano).

### Dev Mode

The existing `style.css` (all-in-one) stays for local development. Splitting only applies to production builds. Dev mode detection already exists in `enqueue.php`.

## Enqueue Logic

In `enqueue.php`, production mode:
1. Always enqueue `dist/base.min.css`
2. Conditionally enqueue page bundle based on template:
   - `is_front_page()` → `home.min.css`
   - `is_singular('products')` → `product.min.css`
   - Page template checks for finder, compare, deals, account, etc.
   - `is_singular('tool')` → `tools.min.css`
   - Fallback: pages with no specific bundle just get base (sufficient for simple content pages, 404, etc.)

Dev mode: unchanged — loads single `style.css` with all partials.

## Expected Impact

| Page | Before (minified) | After (minified) | Savings |
|------|--------------------|-------------------|---------|
| Product | ~130KB (all CSS) | ~65KB base + ~60KB product = ~125KB | ~5KB less, 100% relevant |
| Finder | ~130KB | ~65KB + ~58KB = ~123KB | ~7KB less, 100% relevant |
| Compare | ~130KB | ~65KB + ~85KB = ~150KB* | *compare is CSS-heavy |
| Homepage | ~130KB | ~65KB + ~70KB = ~135KB | Similar but all relevant |
| Account | ~130KB | ~65KB + ~40KB = ~105KB | ~25KB less |
| 404/simple | ~130KB | ~65KB only | ~65KB less (50%) |

The primary win is eliminating FOUC/CLS (no more CCSS needed) and ensuring every byte of CSS loaded is actually used on that page. The secondary win is reduced CSS on simpler pages.

## Rollback

- Old `style.css` entry point stays intact
- No partials are modified
- Reverting = change enqueue.php back to load single `style.min.css`
- No data migration, no URL changes, no breaking changes
