# CSS Code Splitting Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Split the monolithic CSS bundle into base + 8 page-specific bundles to eliminate FOUC/CLS and remove unused CSS per page.

**Architecture:** Create PostCSS entry files per bundle in `assets/css/bundles/`, a Node build script to process them all, and conditional `wp_enqueue_style()` logic in `enqueue.php`. Dev mode unchanged (loads single `style.css`). No partials are modified.

**Tech Stack:** PostCSS (postcss-import, autoprefixer, cssnano), Node.js build script, WordPress `wp_enqueue_style`.

**Design doc:** `docs/plans/2026-03-06-css-code-splitting-design.md`

---

### Task 1: Create Bundle Entry Files

**Files:**
- Create: `erh-theme/assets/css/bundles/base.css`
- Create: `erh-theme/assets/css/bundles/home.css`
- Create: `erh-theme/assets/css/bundles/product.css`
- Create: `erh-theme/assets/css/bundles/finder.css`
- Create: `erh-theme/assets/css/bundles/compare.css`
- Create: `erh-theme/assets/css/bundles/deals.css`
- Create: `erh-theme/assets/css/bundles/archive.css`
- Create: `erh-theme/assets/css/bundles/tools.css`
- Create: `erh-theme/assets/css/bundles/account.css`

**Step 1: Create bundles directory and all 9 entry files**

Each file contains only `@import` statements pointing to parent partials. Import order matters for cascade — follow the same order as `style.css`.

`bundles/base.css`:
```css
/* Base bundle — loaded on every page */
@import '../_variables.css';
@import '../_base.css';
@import '../_typography.css';
@import '../_buttons.css';
@import '../_forms.css';
@import '../_select-drawer.css';
@import '../_components.css';
@import '../_modal.css';
@import '../_toast.css';
@import '../_auth-modal.css';
@import '../_page-sections.css';
@import '../_breadcrumb.css';
@import '../_page-title.css';
@import '../_shortcode-tables.css';
@import '../_header.css';
@import '../_footer.css';
```

`bundles/home.css`:
```css
/* Homepage — front-page.php */
@import '../_hero.css';
@import '../_features.css';
@import '../_comparison.css';
@import '../_deals.css';
@import '../_buying-guides.css';
@import '../_latest-reviews.css';
@import '../_articles.css';
@import '../_content-grid.css';
@import '../_youtube.css';
@import '../_cta.css';
@import '../_social-proof.css';
@import '../_hub.css';
```

`bundles/product.css`:
```css
/* Product pages — single-products.php */
@import '../_gallery.css';
@import '../_byline.css';
@import '../_author-box.css';
@import '../_pros-cons.css';
@import '../_price-intel.css';
@import '../_inline-price-bar.css';
@import '../_content-grid.css';
@import '../_sidebar.css';
@import '../_price-alert-modal.css';
@import '../_coupons.css';
@import '../_content-split.css';
@import '../_single-review.css';
@import '../_single-product.css';
```

`bundles/finder.css`:
```css
/* Finder tool — page-finder.php */
@import '../_finder.css';
@import '../_finder-table.css';
@import '../_price-alert-modal.css';
```

`bundles/compare.css`:
```css
/* Compare tool — page-compare.php */
@import '../_compare-results.css';
@import '../_compare-hub.css';
@import '../_radar-chart.css';
```

`bundles/deals.css`:
```css
/* Deals pages — page-deals.php, page-deals-category.php */
@import '../_deals.css';
@import '../_content-page.css';
@import '../_content-grid.css';
```

`bundles/archive.css`:
```css
/* Archive/listing pages */
@import '../_archive.css';
@import '../_search-page.css';
@import '../_byline.css';
@import '../_content-grid.css';
@import '../_articles.css';
@import '../_sidebar.css';
```

`bundles/tools.css`:
```css
/* Tool pages — single-tool*.php */
@import '../_tools.css';
@import '../_laws-map.css';
```

`bundles/account.css`:
```css
/* Account/auth pages */
@import '../_account.css';
@import '../_onboarding.css';
@import '../_auth.css';
```

**Step 2: Commit**

```bash
git add erh-theme/assets/css/bundles/
git commit -m "Add CSS bundle entry files for code splitting"
```

---

### Task 2: Create Build Script

**Files:**
- Create: `erh-theme/build-css.js`
- Modify: `erh-theme/package.json`

**Step 1: Create `build-css.js`**

This script finds all `bundles/*.css` entry files, runs each through PostCSS with the existing config, and outputs to `dist/<name>.min.css`. Also builds the legacy `style.min.css` for rollback safety.

```js
const fs = require('fs');
const path = require('path');
const postcss = require('postcss');
const postcssImport = require('postcss-import');
const autoprefixer = require('autoprefixer');
const cssnano = require('cssnano');

const CSS_DIR = path.join(__dirname, 'assets/css');
const BUNDLES_DIR = path.join(CSS_DIR, 'bundles');
const DIST_DIR = path.join(CSS_DIR, 'dist');

const plugins = [
    postcssImport(),
    autoprefixer(),
    cssnano({ preset: ['default', { discardComments: { removeAll: true } }] }),
];

async function buildFile(inputPath, outputPath) {
    const css = fs.readFileSync(inputPath, 'utf8');
    const result = await postcss(plugins).process(css, {
        from: inputPath,
        to: outputPath,
    });
    fs.writeFileSync(outputPath, result.css);
    const sizeKB = (result.css.length / 1024).toFixed(1);
    console.log(`  ${path.basename(outputPath)} (${sizeKB} KB)`);
}

async function main() {
    // Ensure dist directory exists.
    if (!fs.existsSync(DIST_DIR)) {
        fs.mkdirSync(DIST_DIR, { recursive: true });
    }

    console.log('Building CSS bundles...');

    // Build split bundles.
    const entries = fs.readdirSync(BUNDLES_DIR).filter(f => f.endsWith('.css'));
    for (const entry of entries) {
        const name = path.basename(entry, '.css');
        await buildFile(
            path.join(BUNDLES_DIR, entry),
            path.join(DIST_DIR, `${name}.min.css`)
        );
    }

    // Build legacy all-in-one bundle (rollback safety).
    console.log('Building legacy bundle...');
    await buildFile(
        path.join(CSS_DIR, 'style.css'),
        path.join(DIST_DIR, 'style.min.css')
    );

    console.log('Done.');
}

main().catch(err => {
    console.error(err);
    process.exit(1);
});
```

**Step 2: Update `package.json` build:css script**

Replace line 7 in `erh-theme/package.json`:

```json
"build:css": "node build-css.js",
```

Keep `watch:css` as-is — it watches the single `style.css` for dev mode. No changes needed.

**Step 3: Test the build locally**

Run: `cd erh-theme && npm run build:css`

Expected output:
```
Building CSS bundles...
  base.min.css (XX.X KB)
  home.min.css (XX.X KB)
  product.min.css (XX.X KB)
  finder.min.css (XX.X KB)
  compare.min.css (XX.X KB)
  deals.min.css (XX.X KB)
  archive.min.css (XX.X KB)
  tools.min.css (XX.X KB)
  account.min.css (XX.X KB)
Building legacy bundle...
  style.min.css (XX.X KB)
Done.
```

Verify all 10 files exist in `assets/css/dist/`.

**Step 4: Commit**

```bash
git add erh-theme/build-css.js erh-theme/package.json
git commit -m "Add CSS build script for split bundles"
```

---

### Task 3: Update Enqueue Logic

**Files:**
- Modify: `erh-theme/inc/enqueue.php` (lines 19-54, the STYLES section)

**Step 1: Replace the styles section in `erh_enqueue_assets()`**

Replace lines 19-54 (from `// Check if we have a production build` through the closing `}` of the dev branch) with new logic that loads base + page-specific bundles in production, and the single `style.css` in dev.

```php
    // Check if we have split production builds.
    $dist_base = ERH_THEME_DIR . '/assets/css/dist/base.min.css';
    $use_split = file_exists( $dist_base );

    // Fallback: check for legacy single bundle.
    $dist_css  = ERH_THEME_DIR . '/assets/css/dist/style.min.css';
    $use_dist  = ! $use_split && file_exists( $dist_css );

    // =========================================
    // STYLES
    // =========================================

    // Self-hosted Figtree font.
    wp_enqueue_style(
        'erh-fonts',
        ERH_THEME_URI . '/assets/fonts/figtree.css',
        array(),
        $version
    );

    if ( $use_split ) {
        // Production: split bundles.
        wp_enqueue_style(
            'erh-base',
            ERH_THEME_URI . '/assets/css/dist/base.min.css',
            array( 'erh-fonts' ),
            filemtime( $dist_base )
        );

        // Page-specific bundle.
        $page_bundle = erh_get_page_css_bundle();
        if ( $page_bundle ) {
            $bundle_path = ERH_THEME_DIR . '/assets/css/dist/' . $page_bundle;
            if ( file_exists( $bundle_path ) ) {
                wp_enqueue_style(
                    'erh-page',
                    ERH_THEME_URI . '/assets/css/dist/' . $page_bundle,
                    array( 'erh-base' ),
                    filemtime( $bundle_path )
                );
            }
        }
    } elseif ( $use_dist ) {
        // Fallback: legacy single bundle.
        wp_enqueue_style(
            'erh-style',
            ERH_THEME_URI . '/assets/css/dist/style.min.css',
            array( 'erh-fonts' ),
            filemtime( $dist_css )
        );
    } else {
        // Development: load all-in-one source file.
        $css_dir   = ERH_THEME_DIR . '/assets/css';
        $css_mtime = max( array_map( 'filemtime', glob( $css_dir . '/*.css' ) ) );

        wp_enqueue_style(
            'erh-style',
            ERH_THEME_URI . '/assets/css/style.css',
            array( 'erh-fonts' ),
            $css_mtime
        );
    }
```

**Step 2: Add `erh_get_page_css_bundle()` helper**

Add this function at the bottom of `enqueue.php` (before the closing of the file, after the `erh_admin_styles` function):

```php
/**
 * Determine which page-specific CSS bundle to load.
 *
 * @return string|null Bundle filename (e.g. 'product.min.css') or null for base-only pages.
 */
function erh_get_page_css_bundle(): ?string {
    // Homepage.
    if ( is_front_page() ) {
        return 'home.min.css';
    }

    // Single product.
    if ( is_singular( 'products' ) ) {
        return 'product.min.css';
    }

    // Single tool.
    if ( is_singular( 'tool' ) ) {
        return 'tools.min.css';
    }

    // Page templates (matched by Template Name header).
    if ( is_page_template( 'page-finder.php' ) ) {
        return 'finder.min.css';
    }

    if ( is_page_template( 'page-compare.php' ) ) {
        return 'compare.min.css';
    }

    if ( is_page_template( 'page-deals.php' ) || is_page_template( 'page-deals-category.php' ) ) {
        return 'deals.min.css';
    }

    if ( is_page_template( 'page-account.php' )
        || is_page_template( 'page-email-preferences.php' )
        || is_page_template( 'page-complete-profile.php' )
        || is_page_template( 'page-login.php' )
        || is_page_template( 'page-reset-password.php' )
    ) {
        return 'account.min.css';
    }

    if ( is_page_template( 'page-search.php' )
        || is_page_template( 'page-articles.php' )
        || is_page_template( 'page-buying-guides.php' )
        || is_page_template( 'page-reviews.php' )
    ) {
        return 'archive.min.css';
    }

    // Slug-based pages (no Template Name header).
    if ( is_page( 'escooter-reviews' ) ) {
        return 'archive.min.css';
    }

    // Author and post type archives.
    if ( is_author() || is_post_type_archive( 'tool' ) ) {
        return 'archive.min.css';
    }

    // About page.
    if ( is_page_template( 'page-about.php' ) ) {
        return 'home.min.css';
    }

    // Contact page.
    if ( is_page_template( 'page-contact.php' ) ) {
        return 'account.min.css';
    }

    // 404, index, generic pages — base only.
    return null;
}
```

**Step 3: Commit**

```bash
git add erh-theme/inc/enqueue.php
git commit -m "Add conditional CSS bundle loading in enqueue.php"
```

---

### Task 4: Build, Verify Sizes, and Sanity Check

**Step 1: Run production build**

```bash
cd erh-theme && npm run build
```

Verify all files in `assets/css/dist/`:
```bash
ls -la assets/css/dist/*.min.css
```

Expected: 10 files (9 bundles + 1 legacy `style.min.css`).

**Step 2: Compare total sizes**

```bash
wc -c assets/css/dist/*.min.css
```

Verify:
- `base.min.css` should be the largest (~55-70KB)
- Sum of base + any page bundle should be <= `style.min.css`
- No bundle should be 0 bytes

**Step 3: Quick visual check**

Open a product page locally (should load base + product bundles via dev mode — but since dev mode loads single `style.css`, this verifies no partials were broken).

**Step 4: Commit all dist files are gitignored — nothing to commit here. Final commit.**

```bash
git add -A
git commit -m "CSS code splitting: bundles, build script, conditional enqueue

Split monolithic CSS into base + 8 page-specific bundles.
- assets/css/bundles/: 9 PostCSS entry files
- build-css.js: builds all bundles via PostCSS API
- enqueue.php: loads base.min.css + page bundle conditionally
- Dev mode unchanged (loads single style.css)
- Legacy style.min.css still built for rollback safety"
```

---

### Task 5: Deploy and Verify Production

**Step 1: Push to main**

```bash
git push origin main
```

GitHub Actions will run `npm run build` which now executes `build-css.js`, producing all split bundles.

**Step 2: Verify on production**

After deploy, check these pages in browser DevTools (Network tab, filter CSS):

- **Homepage** (`eridehero.com`): should load `base.min.css` + `home.min.css`
- **Product page** (`eridehero.com/apollo-go-review/`): should load `base.min.css` + `product.min.css`
- **Finder** (`eridehero.com/electric-scooter-finder/`): should load `base.min.css` + `finder.min.css`
- **404 page**: should load `base.min.css` only
- **Account page**: should load `base.min.css` + `account.min.css`

Check each page for:
- No FOUC (page renders styled immediately)
- No missing styles (header, footer, modals all styled)
- Auth modal opens correctly (any page)
- Toast notifications work (any page)

**Step 3: Disable LiteSpeed CCSS**

Once verified, disable CCSS in LiteSpeed Cache settings since it's no longer needed.

---

### Task 6: Handle Edge Cases (if needed post-deploy)

These are not pre-planned — handle only if visual issues are found during Task 5 verification:

- **Missing styles on a page**: Add the missing partial to that page's bundle entry file, rebuild, redeploy.
- **Contact page needs form styles**: Already in base via `_forms.css`. If it needs more, add `_contact.css` to the account bundle or create a dedicated bundle.
- **About page**: Currently mapped to `home.min.css` since it uses similar section layouts (hero, features, CTA). If styles are wrong, create a small `about.min.css` bundle instead.
