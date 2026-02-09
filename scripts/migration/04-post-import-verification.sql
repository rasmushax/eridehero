-- ============================================================
-- Post-Import Verification Queries
-- ============================================================
-- Run on STAGING after database import to verify data integrity.
-- ============================================================

-- 1. Product counts by type
SELECT
    t.name AS product_type,
    COUNT(*) AS count
FROM wp_posts p
JOIN wp_term_relationships tr ON p.ID = tr.object_id
JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
JOIN wp_terms t ON tt.term_id = t.term_id
WHERE p.post_type = 'products'
AND p.post_status = 'publish'
AND tt.taxonomy = 'product_type'
GROUP BY t.name
ORDER BY count DESC;

-- 2. User count
SELECT COUNT(*) AS total_users FROM wp_users;

-- 3. Price tracker counts
SELECT COUNT(*) AS total_trackers FROM wp_price_trackers;
SELECT geo, COUNT(*) AS count FROM wp_price_trackers GROUP BY geo;

-- 4. Price history records
SELECT COUNT(*) AS total_price_records FROM wp_product_daily_prices;
SELECT geo, COUNT(*) AS count FROM wp_product_daily_prices GROUP BY geo;

-- 5. HFT tracked links
SELECT COUNT(*) AS total_tracked_links FROM wp_hft_tracked_links;

-- 6. Click tracking
SELECT COUNT(*) AS total_clicks FROM wp_erh_clicks;

-- 7. Media attachments
SELECT COUNT(*) AS total_media
FROM wp_posts
WHERE post_type = 'attachment'
AND post_status = 'inherit';

-- 8. Comparison CPT posts
SELECT COUNT(*) AS total_comparisons
FROM wp_posts
WHERE post_type = 'comparison'
AND post_status = 'publish';

-- 9. Tool CPT posts
SELECT COUNT(*) AS total_tools
FROM wp_posts
WHERE post_type = 'tool'
AND post_status = 'publish';

-- 10. Blog posts
SELECT COUNT(*) AS total_posts
FROM wp_posts
WHERE post_type = 'post'
AND post_status = 'publish';

-- 11. Pages
SELECT COUNT(*) AS total_pages
FROM wp_posts
WHERE post_type = 'page'
AND post_status = 'publish';

-- 12. Spot-check: verify specific product IDs match
-- (Replace these IDs with known products from production)
SELECT ID, post_title, post_name, post_status
FROM wp_posts
WHERE post_type = 'products'
AND post_status = 'publish'
ORDER BY ID
LIMIT 10;

-- 13. Check RankMath meta exists
SELECT COUNT(*) AS products_with_rankmath
FROM wp_postmeta
WHERE meta_key = 'rank_math_title'
AND post_id IN (
    SELECT ID FROM wp_posts WHERE post_type = 'products'
);

-- 14. Social login records (if wp_social_users was imported)
SELECT type, COUNT(*) AS count
FROM wp_social_users
GROUP BY type;

-- 15. ERH-specific options
SELECT option_name, LEFT(option_value, 60) AS value_preview
FROM wp_options
WHERE option_name LIKE 'erh_%'
ORDER BY option_name;
