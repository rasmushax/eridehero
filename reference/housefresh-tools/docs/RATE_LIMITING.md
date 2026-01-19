# Rate Limiting Documentation

## Overview

The Housefresh Tools plugin implements professional-grade rate limiting for its public REST API endpoints. This protects your site from abuse, prevents resource exhaustion, and ensures fair usage across all visitors.

## Features

✅ **Sliding Window Algorithm** - More accurate than fixed windows
✅ **Per-Endpoint Limits** - Different limits for different endpoints
✅ **IP & User-Based** - Tracks both anonymous and logged-in users
✅ **Proxy-Aware** - Respects X-Forwarded-For and X-Real-IP headers
✅ **Standard HTTP Headers** - Returns X-RateLimit-* headers
✅ **Proper HTTP 429 Responses** - Includes Retry-After header
✅ **WordPress Transient Storage** - Automatic cache integration
✅ **Configurable via Filters** - Customize limits per endpoint
✅ **Logging Support** - Track violations when WP_DEBUG enabled

---

## Protected Endpoints

### 1. `/get-affiliate-link`
**Default Limit:** 60 requests per minute
**Purpose:** Fetches affiliate links for products based on user geo-location

### 2. `/detect-geo`
**Default Limit:** 30 requests per minute
**Purpose:** Detects user's geographic location via IP address

### 3. `/price-history-chart`
**Default Limit:** 30 requests per minute
**Purpose:** Retrieves price history data for product charts

---

## How It Works

### Sliding Window Algorithm

The rate limiter uses a **sliding window** approach:

1. Each request timestamp is stored in a transient
2. When checking limits, only requests within the last N seconds are counted
3. Older requests automatically expire from the window
4. More accurate than fixed windows (prevents burst at window boundaries)

### Example Timeline

```
Limit: 3 requests per minute

Time    Request    In Window    Status
0:00    #1         [#1]         ✅ Allowed (1/3)
0:20    #2         [#1,#2]      ✅ Allowed (2/3)
0:40    #3         [#1,#2,#3]   ✅ Allowed (3/3)
0:50    #4         [#1,#2,#3]   ❌ BLOCKED (3/3)
1:10    #5         [#2,#3]      ✅ Allowed (2/3) - #1 expired
```

---

## Response Headers

Every API response includes rate limit information:

```http
X-RateLimit-Limit: 60          # Maximum requests allowed
X-RateLimit-Remaining: 45      # Requests remaining in window
X-RateLimit-Reset: 1699564820  # Unix timestamp when limit resets
```

When rate limited, you'll also receive:

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 25                # Seconds until you can retry
```

### Error Response

```json
{
  "code": "rest_rate_limit_exceeded",
  "message": "Rate limit exceeded. Please try again in 25 seconds.",
  "data": {
    "status": 429,
    "limit": 60,
    "retry_after": 25
  }
}
```

---

## Configuration

### Default Limits

Defined in `HFT_Rate_Limiter::DEFAULT_LIMITS`:

```php
[
    'get-affiliate-link'  => ['requests' => 60, 'period' => 60],
    'detect-geo'          => ['requests' => 30, 'period' => 60],
    'price-history-chart' => ['requests' => 30, 'period' => 60],
    'default'             => ['requests' => 100, 'period' => 60],
]
```

### Customizing Limits

#### Global Filter

Modify all endpoints:

```php
add_filter( 'hft_rate_limit_config', function( $limits, $endpoint ) {
    // Double all limits
    $limits['requests'] = $limits['requests'] * 2;
    return $limits;
}, 10, 2 );
```

#### Per-Endpoint Filter

Modify specific endpoint:

```php
// Increase affiliate link limit for high-traffic sites
add_filter( 'hft_rate_limit_config_get-affiliate-link', function( $limits ) {
    return [
        'requests' => 120,  // 120 requests
        'period' => 60,     // per minute
    ];
} );
```

#### Example: Different Limits for Logged-in Users

```php
add_filter( 'hft_rate_limit_config', function( $limits, $endpoint ) {
    if ( is_user_logged_in() ) {
        // Give logged-in users 5x the limit
        $limits['requests'] = $limits['requests'] * 5;
    }
    return $limits;
}, 10, 2 );
```

---

## Monitoring

### Enable Logging

Set `WP_DEBUG` to `true` in `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Violations will be logged to `/wp-content/debug.log`:

```
[HFT Rate Limit] Endpoint: get-affiliate-link, Identifier: ip_192_168_1_100, Requests: 61/60
```

### Custom Monitoring Hook

Hook into rate limit events:

```php
add_action( 'hft_rate_limit_exceeded', function( $endpoint, $identifier, $request_count, $limit ) {
    // Send alert to monitoring service
    error_log( "ALERT: Rate limit exceeded on $endpoint by $identifier" );

    // Example: Send to external monitoring
    // wp_remote_post( 'https://monitoring.example.com/alerts', [...] );
}, 10, 4 );
```

---

## Testing

### Manual Testing

```bash
# Test rate limiting with curl
for i in {1..65}; do
    curl -i "https://yoursite.com/wp-json/housefresh-tools/v1/get-affiliate-link?product_id=123&target_geo=US"
    echo "Request $i"
    sleep 0.5
done
```

Watch for the `429` response after request #60.

### Clear Rate Limits (for testing)

```php
// Clear rate limit for specific endpoint
$rate_limiter = new HFT_Rate_Limiter();
$rate_limiter->clear_rate_limit( 'get-affiliate-link' );

// Or via WordPress CLI
wp transient delete --all
```

### Check Rate Limit Status (without affecting limit)

```php
$rate_limiter = new HFT_Rate_Limiter();
$status = $rate_limiter->get_rate_limit_status( 'get-affiliate-link' );

var_dump( $status );
// Output:
// array(3) {
//   'limit' => 60
//   'remaining' => 45
//   'reset' => 1699564820
// }
```

---

## Best Practices

### For Plugin Users

1. **Monitor Your Logs** - Check for excessive rate limiting that might indicate legitimate traffic issues
2. **Adjust Limits for Your Traffic** - High-traffic sites should increase limits
3. **Consider CDN** - Use a CDN to cache API responses when possible
4. **Inform API Consumers** - Document rate limits in your API documentation

### For Developers Integrating with the API

1. **Respect Headers** - Always check `X-RateLimit-*` headers
2. **Implement Backoff** - Wait the `Retry-After` seconds when receiving 429
3. **Cache Responses** - Don't repeatedly request the same data
4. **Handle 429 Gracefully** - Show user-friendly messages, not errors

---

## Performance Impact

✅ **Minimal Overhead** - Rate limiting adds ~1-2ms per request
✅ **Uses WordPress Object Cache** - Benefits from persistent cache (Redis, Memcached)
✅ **Transient Auto-Expiration** - Old data automatically cleaned up
✅ **No Database Queries** - Everything stored in cache

### Memory Usage

- ~500 bytes per IP/endpoint combination
- Automatic cleanup after rate limit window expires
- No persistent storage required

---

## Troubleshooting

### Issue: Legitimate Users Being Blocked

**Solution:** Increase rate limits for the affected endpoint

```php
add_filter( 'hft_rate_limit_config_get-affiliate-link', function( $limits ) {
    return ['requests' => 120, 'period' => 60];
} );
```

### Issue: Rate Limits Not Working

**Check:**
1. Verify transients are working: `wp transient set test 1 60 && wp transient get test`
2. Check if object cache is properly configured
3. Ensure `HFT_Rate_Limiter` class is loaded before REST API init

### Issue: All Requests Showing Same IP

**Problem:** Your site is behind a proxy/CDN
**Solution:** Configure your web server to pass X-Forwarded-For header

**Nginx:**
```nginx
proxy_set_header X-Real-IP $remote_addr;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
```

**CloudFlare:** Automatically includes CF-Connecting-IP (handled by plugin)

---

## Security Considerations

### IP Spoofing Protection

The rate limiter uses this IP detection order:
1. `HTTP_X_REAL_IP` (most trustworthy)
2. `HTTP_X_FORWARDED_FOR` (first IP only)
3. `REMOTE_ADDR` (fallback)

### DDoS Protection

Rate limiting provides **application-level** protection but is not a substitute for:
- Network-level DDoS protection (Cloudflare, AWS Shield)
- Web Application Firewall (WAF)
- Proper server configuration

### Bypass for Localhost

The rate limiter treats invalid IPs (including localhost during development) as `0.0.0.0`. Consider adding a filter to bypass rate limits in development:

```php
if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE ) {
    add_filter( 'hft_rate_limit_config', function( $limits ) {
        $limits['requests'] = 999999;
        return $limits;
    } );
}
```

---

## API Reference

### `HFT_Rate_Limiter` Class Methods

#### `check_rate_limit( string $endpoint, ?int $user_id = null ): array`

Check if request exceeds rate limits and increment counter.

**Returns:**
```php
[
    'allowed'     => bool,   // Whether request is allowed
    'limit'       => int,    // Maximum requests allowed
    'remaining'   => int,    // Requests remaining
    'reset'       => int,    // Unix timestamp when limit resets
    'retry_after' => int,    // (if blocked) Seconds until retry
]
```

#### `get_rate_limit_status( string $endpoint, ?int $user_id = null ): array`

Get current status **without** incrementing counter (for status checks).

#### `clear_rate_limit( string $endpoint, ?int $user_id = null ): bool`

Clear rate limit data for testing or admin resets.

#### `add_rate_limit_headers( array $rate_limit_result ): void`

Add X-RateLimit-* headers to response.

#### `create_rate_limit_error( array $rate_limit_result ): WP_Error`

Create properly formatted 429 error response.

---

## Changelog

### Version 1.0.0 - 2024-11-14
- Initial implementation of rate limiting
- Sliding window algorithm
- Per-endpoint configuration
- IP and user-based tracking
- Standard HTTP headers and error responses
- WordPress filter integration
- Logging and monitoring support

---

## Support

For issues or questions about rate limiting:
- Check error logs (`wp-content/debug.log`)
- Review this documentation
- Test with the provided examples
- Check filter implementation

---

## License

Rate limiting implementation follows the same license as the Housefresh Tools plugin.
