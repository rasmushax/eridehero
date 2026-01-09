# Listicle Item Block Optimizations

Code quality and performance improvements identified during listicle-item block review.

---

## 1. Extract Shared Pricing UI Utilities (HIGH)

### Problem
Four components duplicate retailer/pricing rendering logic (~200 lines total):
- `erh-theme/assets/js/components/listicle-item.js` - `renderRetailers()`
- `erh-theme/assets/js/components/price-intel.js` - `renderRetailerList()`
- `erh-theme/assets/js/components/sticky-buy-bar.js` - price + verdict display
- `erh-theme/assets/js/utils/deals-utils.js` - `createDealCard()`

### Solution
Create `erh-theme/assets/js/utils/pricing-ui.js` with shared functions:

```javascript
/**
 * Pricing UI Utilities
 * Shared rendering functions for retailer lists, price verdicts, etc.
 */

import { formatPrice, getCurrencySymbol } from '../services/geo-price.js';

/**
 * Calculate price verdict (% vs average)
 * @param {number} currentPrice
 * @param {number} avgPrice
 * @returns {{ percent: number, type: 'below'|'above'|'neutral', text: string }|null}
 */
export function calculatePriceVerdict(currentPrice, avgPrice) {
    if (!avgPrice || avgPrice <= 0) return null;

    const diff = ((currentPrice - avgPrice) / avgPrice) * 100;

    if (Math.abs(diff) < 3) {
        return { percent: 0, type: 'neutral', text: '' };
    }

    if (diff < 0) {
        return {
            percent: Math.abs(Math.round(diff)),
            type: 'below',
            text: `${Math.abs(Math.round(diff))}% below avg`
        };
    }

    return {
        percent: Math.round(diff),
        type: 'above',
        text: `${Math.round(diff)}% above avg`
    };
}

/**
 * Render a single retailer row HTML
 * @param {Object} offer - Offer data from API
 * @param {Object} options - { isBest, currency }
 * @returns {string} HTML string
 */
export function renderRetailerRow(offer, options = {}) {
    const { isBest = false, currency = 'USD' } = options;

    const bestClass = isBest ? 'is-best' : '';
    const stockClass = offer.in_stock ? 'in-stock' : 'out-of-stock';
    const stockIcon = offer.in_stock ? 'check' : 'x';
    const stockText = offer.in_stock ? 'In stock' : 'Out of stock';

    const logoHtml = offer.logo_url
        ? `<img src="${offer.logo_url}" alt="${offer.retailer}" class="retailer-logo">`
        : `<span class="retailer-logo-text">${offer.retailer}</span>`;

    const badgeHtml = isBest ? '<span class="retailer-badge">Best price</span>' : '';

    return `
        <a href="${offer.url || '#'}" class="retailer-row ${bestClass}" target="_blank" rel="nofollow noopener">
            ${logoHtml}
            <div class="retailer-info">
                <span class="retailer-name">${offer.retailer}</span>
                <span class="retailer-stock ${stockClass}">
                    <svg class="icon" aria-hidden="true"><use href="#icon-${stockIcon}"></use></svg>
                    ${stockText}
                </span>
            </div>
            <div class="retailer-price">
                <span class="retailer-amount">${formatPrice(offer.price, currency)}</span>
                ${badgeHtml}
            </div>
            <svg class="icon retailer-arrow" aria-hidden="true"><use href="#icon-external-link"></use></svg>
        </a>
    `;
}

/**
 * Render price verdict badge HTML
 * @param {Object} verdict - From calculatePriceVerdict()
 * @returns {string} HTML string
 */
export function renderVerdictBadge(verdict) {
    if (!verdict || verdict.type === 'neutral') return '';

    const icon = verdict.type === 'below' ? 'arrow-down' : 'arrow-up';
    const className = `price-verdict price-verdict--${verdict.type}`;

    return `
        <span class="${className}">
            <svg class="icon" aria-hidden="true"><use href="#icon-${icon}"></use></svg>
            ${verdict.text}
        </span>
    `;
}

export default {
    calculatePriceVerdict,
    renderRetailerRow,
    renderVerdictBadge,
};
```

### Files to Update
1. `listicle-item.js` - Import and use in `renderRetailers()`
2. `price-intel.js` - Import and use in `renderRetailerList()`
3. `sticky-buy-bar.js` - Import `calculatePriceVerdict()`
4. `deals-utils.js` - Import for deal cards

---

## 2. Use Shared filterOffersForGeo (HIGH)

### Problem
`sticky-buy-bar.js` lines 99-103 duplicates offer filtering inline:

```javascript
// CURRENT - duplicated logic
const filteredOffers = data.offers.filter(offer => {
    const offerCurrency = offer.currency || 'USD';
    return offerCurrency === currency;
});
```

### Solution
Import and use the shared function from geo-price.js:

```javascript
// sticky-buy-bar.js
import { getUserGeo, formatPrice, getProductPrices, filterOffersForGeo } from '../services/geo-price.js';

// In loadPriceData():
const filteredOffers = filterOffersForGeo(data.offers, { geo, currency });
```

---

## 3. Batch Specs Endpoint (MEDIUM)

### Problem
Each listicle item fetches specs individually. While we want lazy loading (don't load all specs on page load), having a batch endpoint available is good for future use cases.

### Solution
Add batch endpoint to `erh-core/includes/api/class-rest-listicle.php`:

```php
// Register additional route in register_routes()
register_rest_route($this->namespace, '/' . $this->rest_base . '/specs/batch', [
    [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => [$this, 'get_specs_html_batch'],
        'permission_callback' => '__return_true',
        'args'                => [
            'products' => [
                'description' => 'Array of {product_id, category_key}',
                'type'        => 'array',
                'required'    => true,
            ],
        ],
    ],
]);

/**
 * Get specs HTML for multiple products.
 */
public function get_specs_html_batch(WP_REST_Request $request) {
    $products = $request->get_param('products');

    if (empty($products) || !is_array($products)) {
        return new WP_Error('invalid_products', 'Products array required', ['status' => 400]);
    }

    // Limit batch size
    if (count($products) > 20) {
        return new WP_Error('too_many', 'Maximum 20 products per batch', ['status' => 400]);
    }

    $results = [];
    foreach ($products as $item) {
        $product_id = (int) ($item['product_id'] ?? 0);
        $category_key = sanitize_key($item['category_key'] ?? 'escooter');

        if ($product_id <= 0) continue;

        // Check cache first
        $cache_key = CacheKeys::listicleSpecs($product_id, $category_key);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            $results[$product_id] = $cached;
            continue;
        }

        // Generate and cache
        $html = $this->render_specs_html($product_id, $category_key);
        set_transient($cache_key, $html, 6 * HOUR_IN_SECONDS);
        $results[$product_id] = $html;
    }

    return new WP_REST_Response(['specs' => $results], 200);
}
```

**Note**: Don't change listicle-item.js to use batch - keep lazy loading per-item. This endpoint is for future use cases.

---

## 4. Centralize Cache Keys (MEDIUM)

### Problem
Cache keys are scattered across multiple files with inconsistent patterns.

### Solution
Create `erh-core/includes/class-cache-keys.php`:

```php
<?php
/**
 * Centralized cache key management.
 *
 * @package ERH
 */

declare(strict_types=1);

namespace ERH;

/**
 * Cache key generator for consistent naming and easy invalidation.
 */
class CacheKeys {

    /** @var string Prefix for all ERH cache keys */
    private const PREFIX = 'erh_';

    /**
     * Price intel cache key (retailers + history).
     */
    public static function priceIntel(int $product_id, string $geo): string {
        return self::PREFIX . "price_intel_{$product_id}_{$geo}";
    }

    /**
     * Price history cache key.
     */
    public static function priceHistory(int $product_id, string $geo): string {
        return self::PREFIX . "price_history_{$product_id}_{$geo}";
    }

    /**
     * Listicle specs HTML cache key.
     */
    public static function listicleSpecs(int $product_id, string $category_key): string {
        return self::PREFIX . "listicle_specs_{$product_id}_{$category_key}";
    }

    /**
     * Similar products cache key.
     */
    public static function similarProducts(int $product_id, int $limit, string $geo): string {
        return self::PREFIX . "similar_{$product_id}_{$limit}_{$geo}";
    }

    /**
     * Deals API cache key.
     */
    public static function deals(string $category, int $limit, string $geo, int $period, int $threshold): string {
        return self::PREFIX . "deals_api_{$category}_{$limit}_{$geo}_{$period}_{$threshold}";
    }

    /**
     * Clear all caches for a product.
     */
    public static function clearProduct(int $product_id): void {
        global $wpdb;

        // Delete transients matching product ID
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
             AND option_name LIKE %s",
            '_transient_erh_%',
            "%_{$product_id}_%"
        ));
    }

    /**
     * Clear listicle specs cache for a product.
     */
    public static function clearListicleSpecs(int $product_id): void {
        // Clear all category variants
        $categories = ['escooter', 'ebike', 'euc', 'skateboard', 'hoverboard'];
        foreach ($categories as $cat) {
            delete_transient(self::listicleSpecs($product_id, $cat));
        }
    }
}
```

### Files to Update
- `class-rest-prices.php` - Use `CacheKeys::priceIntel()`, `CacheKeys::priceHistory()`
- `class-rest-listicle.php` - Use `CacheKeys::listicleSpecs()`
- `class-rest-products.php` - Use `CacheKeys::similarProducts()`
- `class-rest-deals.php` - Use `CacheKeys::deals()`

---

## 5. Share VideoLightbox Singleton (LOW)

### Problem
`gallery.js` has a singleton `VideoLightbox` class, but `listicle-item.js` doesn't use it.

### Solution
Export the lightbox from gallery.js and import in listicle-item.js:

```javascript
// gallery.js - add export
export const videoLightbox = new VideoLightbox();

// listicle-item.js
import { videoLightbox } from './gallery.js';

// In setupVideoCard():
this.el.querySelector('.listicle-item-video-card')?.addEventListener('click', (e) => {
    const videoId = e.currentTarget.dataset.video;
    if (videoId) {
        videoLightbox.open(videoId);
    }
});
```

---

## 6. Price Bar Coordinator for Multi-Item Pages (MEDIUM)

### Problem
On pages with multiple listicle items, each fetches prices independently on load.

### Solution
Create a coordinator that batches price fetching:

```javascript
// erh-theme/assets/js/components/listicle-coordinator.js

import { getUserGeo, getBestPrices } from '../services/geo-price.js';

/**
 * Coordinates initialization of multiple listicle items on a page.
 * Batches price fetching for better performance.
 */
export class ListicleCoordinator {
    constructor() {
        this.items = new Map(); // productId -> ListicleItem instance
    }

    /**
     * Register a listicle item for coordinated initialization.
     */
    register(productId, instance) {
        this.items.set(productId, instance);
    }

    /**
     * Initialize all registered items with batched data fetching.
     */
    async initAll() {
        if (this.items.size === 0) return;

        const productIds = Array.from(this.items.keys());

        // Get geo once
        const userGeo = await getUserGeo();

        // Batch fetch prices for all products
        const prices = await getBestPrices(productIds, userGeo.geo);

        // Hydrate each item with its price data
        for (const [productId, instance] of this.items) {
            const priceData = prices[productId];
            if (priceData) {
                instance.hydratePriceBarWithData(priceData, userGeo);
            }
        }
    }
}

// Singleton
export const coordinator = new ListicleCoordinator();
```

Update `listicle-item.js`:
```javascript
import { coordinator } from './listicle-coordinator.js';

class ListicleItem {
    constructor(element) {
        // ... existing code ...

        // Register with coordinator instead of fetching immediately
        coordinator.register(this.productId, this);
    }

    /**
     * Hydrate price bar with pre-fetched data (called by coordinator).
     */
    hydratePriceBarWithData(priceData, userGeo) {
        // Move hydration logic here
    }
}

// In initListicleItems():
export function initListicleItems() {
    const items = document.querySelectorAll('[data-listicle-item]');
    items.forEach(el => new ListicleItem(el));

    // Trigger coordinated initialization
    coordinator.initAll();
}
```

---

## 7. Specs Cache Invalidation (MEDIUM)

### Problem
Listicle specs are cached for 6 hours but never invalidated when product data changes.

### Solution
Hook into ACF save to clear specs cache:

```php
// In erh-core/includes/class-plugin.php or a dedicated hooks file

add_action('acf/save_post', function($post_id) {
    // Only for products
    if (get_post_type($post_id) !== 'products') {
        return;
    }

    // Clear listicle specs cache
    CacheKeys::clearListicleSpecs($post_id);

}, 20); // Priority 20 = after ACF saves
```

---

## Implementation Order

1. **HIGH** - Extract shared pricing UI utilities (pricing-ui.js)
2. **HIGH** - Use filterOffersForGeo in sticky-buy-bar.js
3. **MEDIUM** - Create CacheKeys class and update REST endpoints
4. **MEDIUM** - Add specs cache invalidation hook
5. **MEDIUM** - Implement ListicleCoordinator for batched price fetching
6. **LOW** - Share VideoLightbox singleton
7. **LOW** - Add batch specs endpoint (for future use)

---

## Testing Checklist

- [ ] Price display works in listicle items
- [ ] Price display works in price-intel component
- [ ] Price display works in sticky buy bar
- [ ] Deal cards render correctly
- [ ] Video lightbox opens from listicle items
- [ ] Video lightbox opens from gallery
- [ ] Specs cache clears when product is updated in admin
- [ ] Multiple listicle items on same page batch their price requests
- [ ] Cache keys are consistent across all endpoints
