# Compare Hub Implementation Plan

Build out the comparison tool pages with curated SEO comparisons and automated popular tracking.

---

## Overview

Three types of compare pages:

| URL | Purpose |
|-----|---------|
| `/compare/` | Main hub - featured comparisons, category links |
| `/compare/electric-scooters/` | Category landing - popular matchups in category |
| `/compare/product-a-vs-product-b/` | Comparison result (existing, needs SEO enhancements) |

---

## Curated Comparisons: Recommendation

**Approach: Custom Post Type `comparison`**

This is the cleanest solution because:
1. Each comparison has its own URL (SEO-friendly)
2. Native WordPress editing experience
3. Can store custom intro content, SEO meta
4. Easy to query and display
5. Scales indefinitely
6. Can mark as "featured" for hub pages

### Alternative Approaches Considered

| Approach | Pros | Cons |
|----------|------|------|
| **CPT (recommended)** | Native WP, SEO-friendly URLs, scales well | Adds another CPT |
| ACF Options Repeater | Centralized, simple | No individual URLs, doesn't scale |
| Product Taxonomy | Lightweight | No comparison-specific content |
| Manual Page Creation | Full control | Tedious, no automation |

---

## Data Structure

### 1. Comparison CPT

```php
// Register CPT
register_post_type('comparison', [
    'labels' => [
        'name' => 'Comparisons',
        'singular_name' => 'Comparison',
    ],
    'public' => true,
    'has_archive' => false,
    'show_in_rest' => true,
    'supports' => ['title', 'editor', 'thumbnail'],
    'rewrite' => [
        'slug' => 'compare',
        'with_front' => false,
    ],
    'menu_icon' => 'dashicons-columns',
]);
```

### 2. ACF Fields for Comparison

```
Field Group: Comparison Details
├── product_1 (Relationship → products, single)
├── product_2 (Relationship → products, single)
├── category (Select: escooter, ebike, etc.) - auto-filled from products
├── intro_text (Textarea) - SEO intro paragraph
├── verdict (Textarea) - Summary/winner callout
├── is_featured (True/False) - Show on hub pages
└── comparison_tags (Text) - Keywords for internal linking
```

### 3. Auto-generated Slug

Post slug auto-generates from products: `apollo-city-pro-vs-segway-ninebot-max`

---

## Popular Comparisons Tracking

### Database Table: `wp_comparison_views`

```sql
CREATE TABLE wp_comparison_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_1_id BIGINT UNSIGNED NOT NULL,
    product_2_id BIGINT UNSIGNED NOT NULL,
    view_count INT UNSIGNED DEFAULT 1,
    last_viewed DATETIME,
    UNIQUE KEY pair (product_1_id, product_2_id)
);
```

**Note:** Always store with lower ID first to avoid duplicates (A vs B = B vs A).

### Tracking Logic

In `compare-routes.php` or JS:
```php
// Track comparison view (called on result page load)
function track_comparison_view($product_1_id, $product_2_id) {
    global $wpdb;

    // Normalize order (lower ID first)
    $ids = [$product_1_id, $product_2_id];
    sort($ids);

    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$wpdb->prefix}comparison_views
         (product_1_id, product_2_id, view_count, last_viewed)
         VALUES (%d, %d, 1, NOW())
         ON DUPLICATE KEY UPDATE
         view_count = view_count + 1,
         last_viewed = NOW()",
        $ids[0], $ids[1]
    ));
}
```

---

## URL Routing

### Current State (in `compare-routes.php`)

```
/compare/                    → page-compare.php (hub)
/compare/{slug-vs-slug}/     → Parses slugs, loads comparison
```

### Enhanced Routing

```
/compare/                         → Hub page (page-compare.php)
/compare/electric-scooters/       → Category landing (taxonomy template)
/compare/{comparison-cpt-slug}/   → Curated comparison (single-comparison.php)
/compare/{slug}-vs-{slug}/        → Dynamic comparison (existing logic)
```

**Priority:** Curated CPT slugs take precedence over dynamic slug-vs-slug parsing.

---

## Page Templates

### 1. Hub Page (`/compare/`)

**Content:**
- Hero: "Compare Electric Vehicles"
- Category cards: E-Scooters, E-Bikes, EUCs, etc.
- Featured Comparisons grid (from CPT where is_featured = true)
- Popular Comparisons (from tracking table)
- CTA: Link to Finder tool

**Data Sources:**
- Featured: `WP_Query` on `comparison` CPT
- Popular: Query `wp_comparison_views` ORDER BY view_count DESC

### 2. Category Landing (`/compare/electric-scooters/`)

**Content:**
- Hero: "Compare Electric Scooters"
- Curated comparisons for category (from CPT)
- Popular comparisons for category (from tracking)
- Top products to compare (from finder data)
- CTA: Link to Finder

### 3. Curated Comparison (`single-comparison.php`)

**Content:**
- Custom intro text (from ACF)
- Full comparison UI (existing compare-results.js)
- Verdict/winner section (from ACF)
- Related comparisons
- Structured data (FAQ schema, Product schema)

### 4. Dynamic Comparison (existing)

**Keep as-is but enhance:**
- Add view tracking
- Add "Save this comparison" CTA
- Link to curated version if exists

---

## Implementation Steps

### Phase 1: CPT & Fields

1. Create `comparison` CPT in `erh-core/includes/cpt/class-comparison-cpt.php`
2. Add ACF field group for comparison details
3. Auto-generate slug from product names on save
4. Add admin columns (products, category, featured)

### Phase 2: View Tracking

1. Create `wp_comparison_views` table
2. Add tracking function in compare-routes.php
3. Call tracking on comparison page load

### Phase 3: Hub Pages

1. Update `page-compare.php` with featured/popular sections
2. Create category landing template
3. Add `single-comparison.php` template

### Phase 4: SEO & Schema

1. Auto-generate meta title/description
2. Add FAQ schema for curated comparisons
3. Add Product comparison schema

---

## Admin Workflow

### Creating a Curated Comparison

1. Go to Comparisons → Add New
2. Select Product 1 (relationship picker)
3. Select Product 2 (relationship picker)
4. Write intro text (SEO content)
5. Write verdict (winner/summary)
6. Check "Featured" if for hub page
7. Publish → Auto-generates slug

### Viewing Popular Comparisons

- Dashboard widget showing top 10 comparisons by views
- Or: Comparisons list table with "Views" column

---

## Files to Create/Modify

| File | Action | Purpose |
|------|--------|---------|
| `erh-core/includes/cpt/class-comparison-cpt.php` | Create | Register CPT, admin columns |
| `acf-json/acf-fields-comparison.json` | Create | Comparison fields |
| `erh-core/includes/database/class-comparison-views.php` | Create | View tracking |
| `erh-theme/compare-routes.php` | Modify | Enhanced routing, tracking |
| `erh-theme/page-compare.php` | Modify | Hub page with featured/popular |
| `erh-theme/single-comparison.php` | Create | Curated comparison template |
| `erh-theme/template-parts/compare/category.php` | Create | Category landing |

---

## Structured Data

### Curated Comparison Page

```json
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Which is better: Apollo City Pro or Segway Max?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "{verdict from ACF}"
      }
    }
  ]
}
```

---

## Finder Integration

Both tools serve different intents:

| Tool | Intent | Entry Point |
|------|--------|-------------|
| **Finder** | "Find me a scooter" (discovery) | Category hubs, reviews |
| **Compare** | "A vs B" (decision) | Product pages, search |

**Cross-links:**
- Finder results → "Compare selected" button
- Compare results → "Find similar" link to Finder
- Product page → "Compare with..." section

---

## Questions to Resolve

1. **Canonical URLs:** If both curated and dynamic comparison exist for same pair, which is canonical?
   - Recommendation: Curated is canonical, dynamic redirects to it

2. **Reverse slugs:** `/compare/a-vs-b/` and `/compare/b-vs-a/` should show same content
   - Recommendation: Redirect B-vs-A to A-vs-B (alphabetical order)

3. **Cross-category comparisons:** Currently not supported in UI
   - Recommendation: Keep unsupported for now (specs don't overlap well)

---

## Current Compare Implementation Reference

Key files in the current codebase:

- `erh-theme/page-compare.php` - Main compare page template
- `erh-theme/inc/compare-routes.php` - URL routing with SEO slugs
- `erh-theme/assets/js/components/compare-results.js` - Core comparison JS (1,043 lines)
- `erh-theme/assets/js/config/compare-config.js` - Specs + scoring config (1,830 lines)
- `erh-theme/assets/css/_compare-results.css` - Compare page styles
- `erh-core/includes/api/class-rest-products.php` - Products API (has comparison endpoint)

---

## H2H Results Page Redesign

**Status**: In Progress

Redesign of the head-to-head comparison results page with improved UX, cleaner specs display, and better mobile experience.

### Design Decisions

| Area | Decision |
|------|----------|
| **Hero cards** | Finder-style cards + score ring + geo-aware CTA + remove button |
| **Hero grid** | CSS Grid: 5 columns desktop → 4 → 3 → 2 on mobile |
| **Sticky nav** | Disable header smart-sticky on compare pages, nav at top:0 |
| **Mini-header** | Table structure (aligns with specs), thumb 40x40, score ring, "$X at Y" link |
| **Differences toggle** | iOS-style toggle in mini-header to show only differing specs |
| **Accordions** | Removed - all categories expanded with h3 titles |
| **Spec tables** | `table-layout: fixed` with `<colgroup>`, no thead |
| **Winner styling** | Purple badge for numeric winners |
| **Boolean specs** | Green check (Yes) / red X (No) badges |
| **Feature arrays** | Expanded into individual rows with green/red per product |
| **Tooltips** | Click-activated, info circle icon |
| **Share section** | Removed entirely |
| **Verdict** | Own nav section at bottom (curated only) |
| **Mobile specs** | Stacked card layout |
| **4+ products** | Specs section own container + full-width + horizontal scroll + sticky labels |

### Completed Work

#### Hero Section

**Grid Layout**:
```css
.compare-header-products {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: var(--space-4);
    justify-items: center;
}
/* Breakpoints: 5 → 4 (1000px) → 3 (768px) → 2 (600px) */
```

**Product Card Actions** (track + remove buttons stacked):
```html
<div class="compare-product-actions">
    <button class="compare-product-track" data-track="123">...</button>
    <button class="compare-product-remove" data-remove="123">...</button>
</div>
```

#### Mini-Header (Sticky in Specs)

Uses table structure to align columns with spec tables:
- First `<td>` contains "Differences only" toggle
- Product cells: thumb (40x40), score ring, name, "$X at Y" link with external icon
- `top: 44px`, `padding: var(--space-6) 0`, border top + bottom
- Hidden on mobile (< 768px)

#### Differences Toggle

**Purpose**: Hide spec rows where all products have identical values.

**Implementation**:
1. Rows with same values get `data-same-values` attribute
2. Toggle adds `show-diff-only` class to `.compare-specs` container
3. CSS: `.compare-specs.show-diff-only tr[data-same-values] { display: none; }`

#### Specs Section

**No Accordions** - All categories expanded with h3 titles:
- `.compare-spec-category { margin-bottom: var(--space-14); }`
- `.compare-spec-category-title { margin: var(--space-8) 0 var(--space-4); }`
- `table-layout: fixed` for equal column widths
- First column: `padding-left: 0` for alignment

#### Winner & Feature Styling

**Three types of highlighting**:

1. **Numeric spec winners** (speed, range, weight) - Purple badge:
   - `.compare-spec-badge` - 20x20px circle, primary-light bg, check icon

2. **Boolean specs** (Regen Braking, Turn Signals) - Green/Red:
   - `.feature-yes .compare-feature-badge` - success-light bg, check icon
   - `.feature-no .compare-feature-badge` - error-light bg, x icon

3. **Feature arrays** - Expanded into individual rows:
   ```
   App Control      ✓ Yes    ✗ No
   Cruise Control   ✓ Yes    ✓ Yes
   NFC Unlock       ✗ No     ✓ Yes
   ```

#### Removed Features

- Share section (CSS and JS removed)
- Value metric special styling and emojis

#### Horizontal Scroll (4+ Products)

**Purpose**: Gracefully handle many-product comparisons (4-8 products) with horizontal scroll.

**Implementation**:
1. **Restructured page layout** - Specs section in its own container:
   - PHP determines product count and applies appropriate classes
   - `<section class="compare-section--full">` for 4+ products
   - Inner `<div class="compare-section-full">` replaces `.container`
   - Clean separation instead of CSS "breakout" hack

2. **Single scroll wrapper** (`<div class="compare-specs-scroll">`) for entire specs section:
   - NOT per-table scroll (confusing which columns are which)
   - Contains all category titles and spec tables

3. **Sticky label column**:
   - First column (spec names) stays fixed during horizontal scroll
   - Category titles (`h3`) also sticky to left
   - Uses `position: sticky; left: 0` with `border-collapse: separate`

4. **Mini-header scroll sync**:
   - Mini-header is OUTSIDE scroll wrapper (for vertical sticky to work)
   - `setupScrollSync()` function syncs `scrollLeft` between mini-header and specs-scroll
   - Uses `requestAnimationFrame` to prevent feedback loops

5. **Column alignment**:
   - Both tables use same `<colgroup>` with `.compare-spec-col-label` class
   - Label column: 200px fixed, Product columns: 200px minimum
   - Table min-width = 200px + (200px × product_count)
   - For 12 products: 2600px total, triggering scroll on most viewports
   - Mini-header scrollbar hidden (syncs via JS)
   - Mini-header label cell (diff toggle) also sticky left
   - Category titles (h3) sticky left with 200px width

**PHP Structure** (`page-compare.php`):
```php
<?php $specs_full_width = count( $product_ids ) >= 4; ?>

<!-- Specs section in own container for width control -->
<section class="compare-section compare-section--specs<?php echo $specs_full_width ? ' compare-section--full' : ''; ?>">
    <div class="<?php echo $specs_full_width ? 'compare-section-full' : 'container'; ?>">
        <h2>Specifications</h2>
        <div class="compare-specs" data-compare-specs></div>
    </div>
</section>
```

**CSS Key Rules**:
```css
/* Full-width inner container (replaces .container for 4+ products) */
.compare-section-full {
    width: 100%;
    max-width: none;
    padding: 0 var(--space-6);
}

/* Table minimum width: 200px label + 200px per product column */
.compare-section--full .compare-spec-table,
.compare-section--full .compare-mini-table {
    min-width: calc(200px + (200px * var(--product-count, 4)));
}

/* Scroll wrapper triggers horizontal scroll when content exceeds viewport */
.compare-section--full .compare-specs-scroll {
    overflow-x: auto;
}

/* Mini-header syncs scroll via JS, hide its scrollbar */
.compare-section--full .compare-mini-header {
    overflow-x: auto;
    scrollbar-width: none;
}

/* Sticky label column */
.compare-specs-scroll .compare-spec-table td:first-child {
    position: sticky;
    left: 0;
    z-index: 2;
    background: var(--color-white);
    box-shadow: 2px 0 4px -2px rgba(0,0,0,0.1);
}

/* Category titles sticky - needs fixed width */
.compare-specs-scroll .compare-spec-category-title {
    position: sticky;
    left: 0;
    z-index: 2;
    background: var(--color-white);
    width: 200px;
}

/* Mini-header label (diff toggle) sticky */
.compare-section--full .compare-mini-label {
    position: sticky;
    left: 0;
    z-index: 3;
    background: var(--color-white);
}
```

**JS Scroll Sync**:
```javascript
function setupScrollSync() {
    const miniHeader = document.querySelector('.compare-mini-header');
    const specsScroll = document.querySelector('.compare-specs-scroll');
    let isSyncing = false;

    miniHeader.addEventListener('scroll', () => {
        if (isSyncing) return;
        isSyncing = true;
        specsScroll.scrollLeft = miniHeader.scrollLeft;
        requestAnimationFrame(() => { isSyncing = false; });
    }, { passive: true });

    // ... symmetric listener for specsScroll
}
```

### Remaining Work

- **Overview Section**: Radar chart, advantage lists, score display
- **Pricing Section**: Price table, history integration, track buttons
- **Verdict Section** (Curated Only): Add to nav, winner badge, editor's text
- **Mobile Optimization**: Stacked cards for specs, touch interactions
- **Testing**: Different categories (escooter, ebike, etc.)

### Design Tokens Reference

```css
/* Spacing */
--space-4: 16px   /* gaps, padding */
--space-6: 24px   /* mini-header padding */
--space-8: 32px   /* category title margin */
--space-14: 56px  /* category bottom margin */

/* Sizes */
Mini-header thumb: 40x40px
Score ring: 36x36px
Toggle switch: 36x20px
Badge circle: 20x20px, icon 12x12px
```
