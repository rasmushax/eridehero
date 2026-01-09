# ERideHero API Reference

REST API endpoints provided by erh-core plugin.

---

## Authentication (`class-auth-handler.php`)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/erh/v1/auth/login` | Email/password login |
| POST | `/erh/v1/auth/register` | Registration with DNS validation |
| POST | `/erh/v1/auth/forgot-password` | Password reset request |
| POST | `/erh/v1/auth/reset-password` | Password reset completion |
| POST | `/erh/v1/auth/logout` | Logout |
| GET | `/erh/v1/auth/status` | Check auth status |

---

## Social Login (`class-social-auth.php`)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/erh/v1/auth/social/{provider}` | Initiate OAuth (google/facebook/reddit) |
| GET | `/erh/v1/auth/social/{provider}/callback` | OAuth callback |
| GET | `/erh/v1/auth/social/providers` | List available providers |

---

## User Features (`class-user-preferences.php`, `class-user-tracker.php`)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/PUT | `/erh/v1/user/preferences` | Email preferences |
| PUT | `/erh/v1/user/email` | Change email |
| PUT | `/erh/v1/user/password` | Change password |
| PUT | `/erh/v1/user/profile` | Update profile |
| GET | `/erh/v1/user/trackers` | List user's price trackers |
| DELETE | `/erh/v1/user/trackers/{id}` | Delete tracker |
| GET/POST/DELETE | `/erh/v1/products/{id}/tracker` | Tracker CRUD |
| GET | `/erh/v1/products/{id}/price-data` | Get price data for product |

---

## Prices (`class-rest-prices.php`)

| Method | Endpoint | Description | Cache |
|--------|----------|-------------|-------|
| GET | `/erh/v1/prices/{id}?geo=US` | Product prices + history | 6hr |
| GET | `/erh/v1/prices/{id}/history?geo=US` | Price history only | 6hr |
| GET | `/erh/v1/prices/best?ids=1,2,3&geo=US` | Best prices for multiple products | 6hr |

**Cache Invalidation**: Via `hft_price_updated` action when HFT scraper updates prices.

**Debug Header**: `X-ERH-Cache: HIT/MISS` indicates cache status.

---

## Deals (`class-rest-deals.php`)

| Method | Endpoint | Description | Cache |
|--------|----------|-------------|-------|
| GET | `/erh/v1/deals?category=all&limit=12&threshold=-5&geo=US` | Deals with counts | 1hr |

**Parameters**:
- `category`: `all`, `escooter`, `ebike`, etc.
- `limit`: Number of deals to return
- `threshold`: Price below average threshold (e.g., `-5` = 5% below)
- `geo`: Region code (US, GB, EU, CA, AU)

---

## Products (`class-rest-products.php`)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/erh/v1/products` | List products with filtering |
| GET | `/erh/v1/products/{id}` | Single product details |
| GET | `/erh/v1/products/{id}/similar?limit=10&geo=US` | Similar products (cached 2hr) |

---

## Listicle (`class-rest-listicle.php`)

| Method | Endpoint | Description | Cache |
|--------|----------|-------------|-------|
| GET | `/erh/v1/listicle/specs?product_id={id}&category_key=escooter` | Specs HTML for listicle item | 6hr |

**Cache Invalidation**: Via `acf/save_post` hook when product is updated in admin.

---

## Reviews (`class-review-handler.php`)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/erh/v1/reviews` | Submit review (with image upload) |
| GET | `/erh/v1/products/{id}/reviews` | Get product reviews |
| GET | `/erh/v1/user/reviews` | Get user's reviews |
| DELETE | `/erh/v1/reviews/{id}` | Delete own review |

---

## Webhooks

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/erh/v1/webhooks/mailchimp` | Mailchimp unsubscribe sync |

---

## HFT Plugin Endpoints

Base URL: `/wp-json/housefresh-tools/v1`

See `HFT_INTEGRATION.md` for full documentation. Key endpoints:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/housefresh-tools/v1/get-affiliate-link?product_id={id}&target_geo=US` | Geo-targeted affiliate links |
| GET | `/housefresh-tools/v1/detect-geo` | IP-based geo detection |
| GET | `/housefresh-tools/v1/product/{id}/price-history-chart?target_geo=US` | Price history for charts |

---

## Response Patterns

### Success Response
```json
{
  "success": true,
  "data": { ... }
}
```

### Error Response
```json
{
  "code": "error_code",
  "message": "Human readable message",
  "data": { "status": 400 }
}
```

### Rate Limiting
- Auth endpoints: 5 requests/minute per IP
- Price endpoints: 60 requests/minute per IP
- Returns `429 Too Many Requests` when exceeded

---

## JavaScript Usage

```javascript
// Base config available via window.erhData
const { restUrl, nonce } = window.erhData;

// Example: Fetch prices
const response = await fetch(`${restUrl}prices/${productId}?geo=${geo}`, {
    headers: { 'X-WP-Nonce': nonce }
});
const data = await response.json();
```

---

*For HFT-specific endpoints and integration details, see `HFT_INTEGRATION.md`.*
