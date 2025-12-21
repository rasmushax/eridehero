# ERideHero Website Redesign

## Project Overview

This is a visual redesign of the ERideHero website - a review and comparison platform for electric personal vehicles (e-scooters, e-bikes, EUCs, etc.). Phase 1 (static HTML/CSS templates) is complete. Ready for Phase 2 WordPress integration.

## Current Scope

**Phase 1 (Complete): Static Visual Design**
- All page layouts built as static HTML/CSS
- Comprehensive design system with reusable components
- Visual polish, interactions, and responsive design
- No backend functionality

**Phase 2 (Next): WordPress Integration**
- Convert static templates to WordPress theme
- Make components dynamic
- Integrate with existing content/database

## Pages

| File | Purpose |
|------|---------|
| `index.html` | Main homepage |
| `single-review.html` | Product review page with gallery, pricing, specs |
| `escooter-hub.html` | E-scooter category hub page |
| `buying-guides.html` | Buying guides archive (all categories) |
| `articles.html` | Articles archive with ride-type filters |
| `reviews.html` | Reviews archive (all categories) |
| `escooter-reviews.html` | E-scooter reviews archive (dedicated for SEO) |
| `contact.html` | Contact page |
| `login.html` | Authentication page |
| `about.html` | About page with hero, stats, mission, methodology, team |
| `privacy-policy.html` | Privacy policy (content page template) |
| `design-library.html` | Component showcase/documentation |

## Design System Architecture

The CSS follows a modular, DRY approach with partials:

```
css/
├── style.css           # Main file that imports all partials
├── _variables.css      # Design tokens (colors, spacing, typography, shadows)
├── _base.css           # Reset, global styles, layout utilities
├── _typography.css     # Headings, body text, links
├── _buttons.css        # Button system (.btn + variants + sizes)
├── _forms.css          # Form inputs, selects, checkboxes, search
├── _components.css     # Reusable UI components (cards, badges, tabs, etc.)
├── _modal.css          # Modal dialog system
│
│   # Reusable Content Components
├── _breadcrumb.css     # Breadcrumb navigation with responsive collapse
├── _page-title.css     # Large page titles (.page-title)
├── _gallery.css        # Image gallery with thumbnails and video lightbox
├── _byline.css         # Author attribution with social sharing
├── _author-box.css     # Full author bio card with avatar and links
├── _pros-cons.css      # Two-column pros/cons lists
├── _price-intel.css    # Price intelligence component (where to buy, chart, stats)
├── _inline-price-bar.css # Inline and sticky price CTAs
├── _content-grid.css   # Related content grids (.related-grid)
├── _sidebar.css        # Sidebar components (TOC, tools, compare widget)
│
│   # Layout Sections
├── _header.css         # Site header, navigation, mobile menu
├── _hero.css           # Hero section styles
├── _features.css       # Features section (price history, alerts, database)
├── _deals.css          # Deals section and deal cards
├── _buying-guides.css  # Buying guides section
├── _latest-reviews.css # Reviews section and sidebar cards
├── _articles.css       # Articles section
├── _youtube.css        # YouTube section
├── _cta.css            # CTA section
├── _footer.css         # Footer styles
├── _comparison.css     # Product comparison tool
├── _content-split.css  # Split content layouts
├── _social-proof.css   # Social proof elements
│
│   # Page Templates
├── _hub.css            # Category hub pages
├── _archive.css        # Archive/listing pages (buying guides, articles, reviews)
├── _single-review.css  # Single review page (page-specific styles only)
├── _auth.css           # Login/register pages
├── _contact.css        # Contact page
├── _content-page.css   # Simple content pages (privacy, terms, etc.)
└── _about.css          # About page (hero, stats, team, approach sections)
```

### Key Design Tokens (`_variables.css`)

**Colors:**
- Primary: `#5e2ced` (purple)
- Primary hover: `#4a1fd4`
- Primary light: `rgba(94, 44, 237, 0.08)`
- Primary subtle: `#f4f1fd`
- Primary glow: `rgba(94, 44, 237, 0.25)` / strong: `0.35`
- Dark: `#21273a` / lighter: `#2d3554`
- Body text: `#3d4668`
- Muted: `#6f768f`
- Success: `#00b572`
- Success light: `rgba(0, 181, 114, 0.08)`
- Warning: `#ffd700`
- Error: `#dc3545`
- Info: `#fd7e14`
- Border: `#f3f2f5` / hover: `#e6e4ea`
- Background: `#f9fafe`
- YouTube: `#FF0000` / hover: `#CC0000`
- Overlays: `--color-overlay-light`, `--color-shadow`, `--color-white-5/18/75`, `--color-dark-80`

**Spacing:** 4px base unit scale (`--space-1` through `--space-16`)

**Typography:**
- Font family: Figtree
- Sizes: `--text-tiny` (12px) through `--text-7xl` (52px)
- Weights: `--font-normal` (400), `--font-medium` (500), `--font-semibold` (600), `--font-bold` (700), `--font-extrabold` (800)
- Line heights: `--leading-tight` (1.2), `--leading-snug` (1.35), `--leading-normal` (1.5), `--leading-relaxed` (1.65)
- Letter spacing: `--tracking-tight` (-0.02em), `--tracking-snug` (-0.01em), `--tracking-wide` (0.02em)

**Shadows:**
- Primary colored: `--shadow-sm/md/lg/xl`
- Neutral: `--shadow-subtle/soft/elevated/card/dropdown`
- Mobile dropdown: `--shadow-dropdown-mobile`
- Focus ring: `--shadow-focus`

**Border radius:** `--radius-sm` (6px), `--radius-md` (8px), `--radius-lg` (10px), `--radius-xl` (12px), `--radius-2xl` (16px), `--radius-3xl` (20px), `--radius-full` (100px)

**Borders:** `--border-width` (1px), `--border-width-2` (2px)

**Transitions:**
- `--transition-fast`: 0.2s ease
- `--transition-base`: 0.3s ease
- `--transition-slow`: 0.4s ease

**Layout:**
- `--container-max`: 1200px
- `--header-height`: 72px (mobile: 60px)

**Z-Index Scale:**
- `--z-dropdown`: 100
- `--z-mobile-menu`: 999
- `--z-modal-backdrop`: 999
- `--z-header`: 1000
- `--z-modal`: 1001
- `--z-search-dropdown`: 1002
- `--z-skip-link`: 10000

### Reusable Components (`_components.css`)

**Cards:**
- `.card` - Content containers with variants (`.card-sm`, `.card-lg`, `.card-flat`)
- `.card-header`, `.card-body`, `.card-footer` - Card internal structure
- `.content-card` - Shared card styles for reviews/articles/guides with hover effects
- `.card-category` - Positioned category labels on cards
- `.sidebar-card` - Sidebar cards (How we test, About ERideHero)

**UI Elements:**
- `.icon-box` - Centered icon containers (`.icon-box-gradient`, `.icon-box-primary`, sizes: `-sm/-md/-lg`)
- `.badge` - Status indicators (`.badge-success`, `.badge-warning`, `.badge-error`, `.badge-neutral`, sizes: `-sm/-lg`)
- `.tag` - Clickable labels (`.tag-primary`)
- `.tabs` / `.tab` - Tab navigation (`.tabs-flush`, `.tabs-auto`, `.tab-sm`)
- `.filter-pill` - Filter/tab pills

**Typography:**
- `.eyebrow` - Small label text above headings (`.eyebrow-muted`, `.eyebrow-dark`)
- `.label` - Form labels and small descriptive text

**Lists:**
- `.check-list` / `.check-item` - Feature lists with checkmarks (`.check-list-vertical`)
- `.list` / `.list-item` - Generic lists (`.list-dividers` for separator lines)

**Data Display:**
- `.stat` - Statistics display (`.stat-value`, `.stat-label`, `.stat-vertical`)
- `.avatar` - User avatars (sizes: `-sm`, `-lg`, `-xl`)

**Layout Helpers:**
- `.divider` - Horizontal rules (`.divider-sm`, `.divider-lg`, `.divider-vertical`)
- `.empty-state` - Empty state placeholder

**Loading States:**
- `.skeleton` - Skeleton loaders (`.skeleton-text`, `.skeleton-heading`, `.skeleton-avatar`, `.skeleton-button`)

### Layout Utilities (`_base.css`)

- `.container` - Max-width container (1200px) with responsive padding
- `.content-with-sidebar` - 2fr/1fr grid layout for content + sidebar
- `.card-scroll-grid` - Horizontal scroll grid at mobile breakpoints
- `.scroll-section` - Enables horizontal scroll behavior for sections
- `.grid-4` - 4-column responsive grid

### Button System (`_buttons.css`)

Composable classes: `.btn` + variant + size

**Variants:**
- `.btn-primary` - Filled purple with hover glow
- `.btn-secondary` - White with 1px border (turns purple on hover)
- `.btn-outline` - Transparent with purple border
- `.btn-ghost` - Subtle gray background
- `.btn-dark` - Dark background
- `.btn-link` - No padding/background, just styled text (muted → purple on hover)
- `.btn-youtube` - YouTube red

**Sizes (5 sizes):**
| Size | Class | Padding | Font | Icon |
|------|-------|---------|------|------|
| XS | `.btn-xs` | 8px 12px | 12px | 12px |
| SM | `.btn-sm` | 12px 16px | 14px | 14px |
| MD | (default) | 20px 32px | 16px | 18px |
| LG | `.btn-lg` | 20px 32px | 18px | 20px |
| XL | `.btn-xl` | 24px 40px | 20px | 24px |

**Modifiers:**
- `.btn-block` - Full width
- `.btn-icon-right` - Icon on right side

**Icon-only buttons:**
- `.btn-icon` - Square icon button (standalone, no `.btn` needed)
- Combine with `.btn-sm` or `.btn-lg` for sizes

### Tooltip System (`tooltip.js` + `_components.css`)

Lightweight tooltips with smart positioning. Auto-initializes on page load.

**Declarative usage:**
```html
<!-- Hover tooltip (default) -->
<button data-tooltip="Helpful text" data-tooltip-position="top">Hover me</button>

<!-- Click tooltip (for .info-trigger or explicit) -->
<span class="info-trigger" data-tooltip="Click to see info">
    <svg class="icon"><use href="#icon-info"></use></svg>
</span>
```

**Positions:** `top` (default), `bottom`, `left`, `right` - auto-flips when near viewport edges.

**Trigger modes:**
- Hover (default) - for quick hints
- Click - for `.info-trigger` elements or `data-tooltip-trigger="click"`

### Popover System (`popover.js` + `_components.css`)

Interactive popovers with rich HTML content. Click to toggle, click outside to close.

**Usage:**
```html
<div class="popover-wrapper">
    <button data-popover-trigger="my-popover">Open</button>
    <div id="my-popover" class="popover popover--top" aria-hidden="true">
        <div class="popover-arrow"></div>
        <h4 class="popover-title">Title</h4>
        <p class="popover-text">Description text here.</p>
        <a href="/page" class="popover-link">
            Learn more
            <svg class="icon"><use href="#icon-arrow-right"></use></svg>
        </a>
    </div>
</div>
```

**Features:**
- Click outside to close
- Escape key to close
- White background with border and shadow
- Position variants: `.popover--top`, `.popover--bottom`

## JavaScript Architecture

ES modules for modern browsers with legacy fallback:

```
js/
├── app.js                    # Main entry, imports and initializes components
└── components/
    ├── mobile-menu.js        # Mobile navigation with body scroll lock
    ├── search.js             # Search functionality
    ├── dropdown.js           # Desktop dropdown menus
    ├── finder-tabs.js        # Quick Finder tab switching
    ├── deals-tabs.js         # Deals category filtering
    ├── custom-select.js      # Custom select dropdowns
    ├── header-scroll.js      # Header hide/show on scroll
    ├── modal.js              # Modal dialog system
    ├── chart.js              # SVG price chart library
    ├── gallery.js            # Image gallery with lightbox
    ├── tooltip.js            # Lightweight tooltip system (auto-init)
    ├── popover.js            # Interactive popovers (auto-init)
    ├── toc.js                # Table of contents with scroll spy (auto-init)
    ├── comparison.js         # Product comparison tool
    ├── price-alert.js        # Price alert modal interactions
    ├── sticky-buy-bar.js     # Sticky purchase CTA bar
    ├── archive-filter.js     # Archive page category filtering (auto-init)
    ├── archive-sort.js       # Archive page sorting (auto-init)
    └── price-chart.js        # Legacy chart (deprecated, use chart.js)
```

### Modal System (`modal.js` + `_modal.css`)

A production-ready modal dialog system with both declarative and programmatic APIs.

**Declarative Usage (HTML attributes):**
```html
<!-- Trigger -->
<button data-modal-trigger="my-modal">Open Modal</button>

<!-- Modal -->
<div class="modal" id="my-modal" role="dialog" aria-modal="true" aria-labelledby="my-modal-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title" id="my-modal-title">Title</h2>
            <button class="modal-close" data-modal-close aria-label="Close">
                <svg>...</svg>
            </button>
        </div>
        <div class="modal-body">Content</div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-modal-close>Cancel</button>
            <button class="btn btn-primary">Confirm</button>
        </div>
    </div>
</div>
```

**Programmatic API:**
```javascript
import { Modal, initModals } from './components/modal.js';

// Static methods
Modal.openById('my-modal');
Modal.closeAll();

// Instance methods
const modal = new Modal(element);
modal.open();
modal.close();
modal.toggle();
modal.updateContent({ title, body, footer });

// Dynamic modal creation
const modal = Modal.create({
    id: 'confirm-modal',
    title: 'Confirm',
    body: '<p>Are you sure?</p>',
    footer: '<button class="btn btn-primary">Yes</button>'
});
```

**Events:**
- `modal:beforeOpen`, `modal:afterOpen`
- `modal:beforeClose`, `modal:afterClose`

**Features:**
- Focus trapping within modal
- Body scroll lock when open
- ESC key to close
- Click backdrop to close
- ARIA attributes for accessibility
- CSS animations (fade + slide)
- Mobile: slides up from bottom

### Chart System (`chart.js`)

Custom SVG chart library for price history visualization.

**Usage:**
```javascript
// Via data attributes (auto-init)
<div class="price-intel-chart-visual" data-erh-chart="main"></div>

// Programmatic
import { createChart } from './components/chart.js';
const chart = createChart('#container', options);
chart.setData({ values: [...], dates: [...], stores: [...] });
```

**Data Structure:**
```javascript
window.ERideHero = {
    chartData: {
        main: {
            data: {
                values: [749, 799, 779, ...],
                dates: ['Nov 1, 2024', 'Nov 2, 2024', ...],
                stores: ['Amazon', 'Walmart', ...]
            },
            periods: {
                '3m': { values: [...], dates: [...], stores: [...] },
                '6m': { ... },
                '1y': { ... },
                'all': { ... }
            },
            options: { ... }
        }
    }
};
```

**Features:**
- Line charts with area fill
- Smooth or linear curves
- Interactive tooltips (price, store, date)
- Y-axis labels inside chart area
- X-axis with adaptive date formatting
- Period toggle buttons (3M, 6M, 1Y, All)
- Dynamic stats update (average, low) when period changes
- Visibility-triggered animations
- Responsive resize handling

**Period Toggle Stats:**
When period changes, these elements auto-update:
- `[data-period-label]` - Label text (e.g., "6-month avg")
- `[data-period-avg]` - Calculated average value
- `[data-period-low-label]` - Low label text
- `[data-period-low]` - Minimum value from period
- `[data-period-low-meta]` - Date and store of lowest price

### Header Scroll Behavior

Smart scroll-based visibility system:
- **Static by default** at the top of the page (transparent background)
- **Becomes fixed + hidden** after scrolling past 200px threshold
- **Shows on scroll up** with white background and shadow
- **Stays visible** when scrolling back up through 200px threshold
- **Transitions to static** at scrollY = 0 (background fades out first)

Key constants in `header-scroll.js`:
- `SCROLL_THRESHOLD`: 10px - minimum scroll to trigger hide/show
- `TOP_THRESHOLD`: 200px - when header becomes fixed
- `TRANSITION_DURATION`: 300ms - must match CSS transition

### Table of Contents (`toc.js`)

Scroll-spy enabled table of contents with responsive behavior.

**Usage:**
```javascript
import { initToc } from './components/toc.js';
initToc('.sidebar-toc', { offset: 100 });
```

**Features:**
- Desktop: Sticky sidebar with scroll-spy highlighting
- Mobile: Collapsible dropdown
- Syncs with header visibility state
- Auto-highlights current section based on scroll position

## Single Review Page (`single-review.html`)

### Structure

1. **Breadcrumb** - Navigation path
2. **Title** - Product name with H1
3. **Gallery** - Main image + thumbnails + video lightbox + score badge
4. **Actions** - Primary CTA, compare, track price buttons
5. **Quick Take** - Brief summary
6. **Pros & Cons** - Two-column grid (auto-wraps at 250px min)
7. **Price Intelligence** - Where to buy section (see below)
8. **Key Specs** - Grid of specification cards
9. **Video Review** - Embedded YouTube
10. **Full Review Body** - Long-form content
11. **Full Specifications** - Expandable spec groups
12. **Related Reviews** - Grid of similar products
13. **Sidebar** - TOC, actions, tools (sticky on desktop)

### Price Intelligence Section

The "Where to Buy" section provides comprehensive pricing information:

```
┌─────────────────────────────────────────────────────────┐
│ Best price                              [Buy at Amazon] │
│ $749 at [Amazon logo] ↓ 4% below avg                    │
├─────────────────────────────────────────────────────────┤
│ Compare prices                        Updated 2h ago    │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ [Amazon]  Amazon     In stock     $749 Best price → │ │
│ │ [Walmart] Walmart    In stock     $799            → │ │
│ │ [BestBuy] Best Buy   Out of stock $799            → │ │
│ │ [eBay]    eBay       Available    $819            → │ │
│ └─────────────────────────────────────────────────────┘ │
│ We may earn a commission from purchases.                │
├─────────────────────────────────────────────────────────┤
│ Price history                    [3M] [6M] [1Y] [All]   │
│ ┌─────────────────────────────────────────────────────┐ │
│ │              📈 Price Chart                         │ │
│ └─────────────────────────────────────────────────────┘ │
│ ┌──────────────┬──────────────┬──────────────────────┐ │
│ │ 6-month avg  │ 6-month low  │ [Set price alert]    │ │
│ │ $784         │ $699         │                      │ │
│ │              │ Dec 2023 ·   │                      │ │
│ │              │ Amazon       │                      │ │
│ └──────────────┴──────────────┴──────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

**Key Classes:**
- `.price-intel` - Main container
- `.price-intel-header` - Best price + CTA button
- `.price-intel-price-link` - Clickable price with retailer logo
- `.price-intel-verdict` - Green pill showing price vs average
- `.price-intel-retailers` - Compare prices section
- `.price-intel-retailer-row` - Individual retailer link
- `.price-intel-retailer-row--best` - Highlighted best price row
- `.price-intel-history` - Chart section
- `.price-intel-stats` - Stats row below chart
- `.price-intel-stat` - Individual stat (avg, low, alert button)
- `.price-intel-stat--action` - Stat containing a button
- `.price-intel-disclosure` - Affiliate disclosure footnote

### Price Alert Modal

Triggered by `data-modal-trigger="price-alert-modal"`:
- Product context (image, name, current price)
- Toggle between "Target price" and "Drop by %"
- Quick suggestion buttons
- Email input
- Success confirmation state

### Sticky Buy Bar

Appears 50px after scrolling past the price section. Two-section layout:

**Left side** (`.sticky-buy-bar-left`):
- Thumbnail image
- Product name (bold)
- Action buttons: Track price, Compare

**Right side** (`.sticky-buy-bar-right`):
- Verdict badge with arrow + percentage (red for above avg, green for below)
- Price block ("Best price" label + amount)
- CTA button

**Responsive breakpoints**:
- **768px**: Hide verdict badge
- **570px**: Hide thumbnail, keep product name + action buttons
- **480px**: Hide entire left side; price moves to left, CTA stays right

**Verdict styling**:
- Default: red (`--color-error`) for "above avg"
- `data-verdict-type="below"`: green (`--color-success`) with rotated arrow icon

No close button — hides automatically when scrolling back up.

## Homepage Structure (`index.html`)

1. **Header** - Smart fixed navigation with mega menus, search, mobile menu
2. **Hero Section** - Two-column layout with Quick Finder card
3. **Features Section** - Price history, price alerts, product database cards
4. **Deals Section** - Horizontal scroll carousel with category tabs
5. **Buying Guides** - 4-column grid of guide cards
6. **Latest Reviews** - 2-column grid + "How we test" sidebar
7. **Latest Articles** - 2-column grid + "About ERideHero" sidebar
8. **YouTube Section** - Horizontal scroll video cards
9. **CTA Section** - Sign up call-to-action
10. **Footer** - Links and social icons

## About Page Structure (`about.html`)

Uses transparent header with purple/teal orbs (same as homepage hero).

1. **Hero** - Eyebrow + H1 + subtitle with staggered fade-in animation
2. **Stats Bar** - 4 metrics in white card (2019 founded, 127 products, 8.5K+ miles, 3 contributors)
3. **Our Mission** - Text left, image right. Founding story and achievements
4. **How We Test** - Image left, text right (flipped grid). Teaser with link to methodology page
5. **Editorial Standards** - Text left, image right. Transparency about affiliate model
6. **Meet the Team** - Centered header + 3 team members (photo, socials, name, role, bio)
7. **Bottom Section** - Two-column layout (3fr/2fr): CTA on left, social follow on right

**Key Classes:**
- `.about-hero` - Hero with transparent background and orbs via `::before`/`::after`
- `.about-hero-grid` - Dot pattern overlay
- `.about-stats` / `.about-stats-grid` - 4-column stats bar
- `.about-approach` - Text + image section layout
- `.about-approach-grid` - 2-column grid, use `--flipped` modifier for image-left
- `.about-approach-link` - Arrow link to related page
- `.about-team` - Centered team section
- `.about-team-grid` - 3-column grid for team cards
- `.about-team-card` - Individual team member (no border, just content)
- `.about-bottom` - Bottom section with CTA and social links
- `.about-social-links` - Social icon buttons

**Transparent Header:**
Pages using `body.transparent-header` get:
- Header background transparent at top (static position)
- Header gets white background when scrolled/fixed
- `overflow-x: clip` on main to prevent orb overflow

## Archive Pages (`buying-guides.html`, `articles.html`, `reviews.html`, `escooter-reviews.html`)

Reusable template for content listing pages. Shows all content across categories with on-page JS filtering and/or sorting.

### Structure

1. **Archive Header** - Title, subtitle, optional filter pills or sort dropdown
2. **Content Grid** - 4-column (or 3-column) responsive grid
3. **Pagination** - Previous/Next + page numbers
4. **CTA Section** - Sign up call-to-action
5. **Footer**

### Key Classes (`_archive.css`)

**Header:**
- `.archive-header` - Contains title, subtitle, and filters/controls
- `.archive-title` - Large page heading (matches `.hub-title`)
- `.archive-subtitle` - Descriptive text below title
- `.archive-header-row` - Flex container for title + controls side-by-side
- `.archive-header-controls` - Container for count + sort dropdown
- `.archive-count` - Item count text (e.g., "32 reviews")
- `.archive-sort` - Sort dropdown wrapper (min-width: 160px)
- `.archive-filters` - Horizontal scrollable filter pills
- `.archive-filter` - Filter button, use `.is-active` for selected
- `.archive-filter-count` - Badge showing item count per category

**Grid:**
- `.archive-grid` - 4-column responsive grid (gap: space-8)
- `.archive-grid--3col` - 3-column variant for richer cards

**Card Variants:**
- `.archive-card` - Base card: image + title (buying guides style)
- `.archive-card--article` - Article cards with smaller titles (text-base, leading-normal)
- Cards with `.archive-card-excerpt` auto-get tighter gap and larger titles (text-lg) via `:has()` selector
- `.archive-card-img` - Image container with 16:9 aspect ratio
- `.archive-card-tag` - White category label positioned top-left
- `.archive-card-score` - Purple score badge positioned top-right (reviews)
- `.archive-card-title` - Card heading
- `.archive-card-excerpt` - 2-line clamped description
- `.archive-card-meta` - Date/metadata row
- `.archive-empty` - Empty state when no items match filter

**Data Attributes:**
- `data-archive-filters` - Container for filter buttons
- `data-filter="category"` - On filter buttons (e.g., "e-scooters", "all")
- `data-archive-grid` - Container for filterable/sortable cards
- `data-category="category"` - On cards for JS filtering
- `data-archive-empty` - Empty state element
- `data-archive-sort` - On sort `<select>` element
- `data-rating="8.7"` - On cards for rating sort
- `data-date="2024-12-10"` - On cards for date sort

### Pagination Component (Reusable)

Defined in `_archive.css` but usable anywhere.

**Structure:**
```html
<nav class="pagination" aria-label="Pagination">
    <a href="#" class="pagination-btn pagination-prev" aria-disabled="true">
        <svg class="icon">...</svg>
        <span>Previous</span>
    </a>
    <div class="pagination-pages">
        <a href="#" class="pagination-page is-active" aria-current="page">1</a>
        <a href="#" class="pagination-page">2</a>
        <span class="pagination-ellipsis">...</span>
        <a href="#" class="pagination-page">12</a>
    </div>
    <a href="#" class="pagination-btn pagination-next">
        <span>Next</span>
        <svg class="icon">...</svg>
    </a>
</nav>
```

**Classes:**
- `.pagination` - Main wrapper (centered, border-top separator)
- `.pagination-btn` - Previous/Next pill buttons
- `.pagination-prev` / `.pagination-next` - Direction modifiers
- `.pagination-pages` - Container for page numbers
- `.pagination-page` - Individual page number, use `.is-active` for current
- `.pagination-ellipsis` - Dots for skipped pages

**Variants:**
- `.pagination--compact` - Smaller size
- `.pagination--simple` - Prev/next only, hides page numbers
- `.pagination--bordered` - Card-style container with border

**States:**
- `aria-disabled="true"` on buttons disables them (e.g., prev on page 1)
- `aria-current="page"` on active page number

### JavaScript

**Filtering (`archive-filter.js`):**

Auto-initializes on pages with `[data-archive-filters]`. Handles:
- Filter button click → toggle `.is-active`
- Show/hide cards based on `data-category` matching `data-filter`
- Staggered fade-in animation for filtered results
- Empty state toggle when no matches

**Sorting (`archive-sort.js`):**

Auto-initializes on pages with `[data-archive-sort]`. Handles:
- Sort by rating (highest first) via `data-rating`
- Sort by date (newest/oldest) via `data-date`
- Staggered reorder animation (fade out → reorder DOM → fade in)
- Works with custom-select component

### Responsive Behavior

- **1024px**: 4-col grid → 3 columns, 3-col grid → 2 columns
- **820px**: All grids → 2 columns, header row stacks, prev/next text hidden
- **600px**: Title shrinks, filter pills horizontal scroll
- **480px**: All grids → 1 column

## Design Decisions

1. **DRY CSS**: Shared component classes reduce duplication
2. **Semantic HTML**: ARIA labels, proper heading hierarchy, skip links
3. **Mobile-first responsive**: Breakpoints at 400px, 480px, 600px, 700px, 820px, 1024px
4. **Sentence case**: All headings and buttons use sentence case (not Title Case)
5. **Subtle visual effects**: Soft shadows, gentle gradients, restrained animations
6. **Accessible**: Keyboard navigation, focus states, screen reader support
7. **Auto-fit grids**: Use `repeat(auto-fit, minmax(Xpx, 1fr))` for responsive grids

## Development Notes

- Opening HTML directly shows CORS errors for ES modules - use a local server (`python -m http.server 8000`) or the `nomodule` fallback works
- Border colors are intentionally light (`#f3f2f5`) for a soft, modern feel
- Dropdown shadows use subtle rgba values (0.11 opacity)
- Header scroll JS transition duration (300ms) must match CSS `_header.css` transition
- Chart period toggle button text must match keys in periodsData (lowercase: '3m', '6m', '1y', 'all')
- Modal IDs must match `data-modal-trigger` values exactly

## Phase 1 Summary

All static templates complete:
- **Homepage** - Hero with Quick Finder, features, deals carousel, guides, reviews, articles, YouTube section, CTA
- **Single Review** - Gallery with lightbox, price intelligence with chart, specs, pros/cons, sticky buy bar, sidebar with ToC and compare widget
- **Category Hub** - Filters, card grids, category navigation
- **Archive Pages** - Buying guides, articles, reviews archives with filtering, sorting, pagination
- **E-Scooter Reviews** - Dedicated reviews archive with sort dropdown (SEO-focused)
- **Contact** - Form with validation styling
- **Login** - Authentication form
- **About** - Hero with stats, mission/methodology sections, team grid, CTA + social
- **Privacy Policy** - Content page template (reusable for terms, cookies, editorial, etc.)
- **Design Library** - Component documentation and showcase

## Known Issues

- **Popover mobile viewport**: Horizontal overflow detection/correction not fully working on mobile. The shift calculation applies but doesn't always constrain correctly.
