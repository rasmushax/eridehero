-- ============================================================
-- List All Production Tables
-- ============================================================
-- Run on PRODUCTION to inventory all tables before migration.
-- Helps identify what to import vs skip.
-- ============================================================

-- Show all tables with row counts
SELECT
    table_name AS 'Table',
    table_rows AS 'Rows (approx)',
    ROUND(data_length / 1024 / 1024, 2) AS 'Data (MB)',
    ROUND(index_length / 1024 / 1024, 2) AS 'Index (MB)'
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name LIKE 'wp_%'
ORDER BY table_name;

-- ============================================================
-- Tables to IMPORT (mark with ✓):
-- ============================================================
-- wp_users                    ✓  Core
-- wp_usermeta                 ✓  Core
-- wp_posts                    ✓  Core (products, pages, media, tools, comparisons)
-- wp_postmeta                 ✓  Core (ACF fields, RankMath meta)
-- wp_terms                    ✓  Core
-- wp_termmeta                 ✓  Core
-- wp_term_taxonomy            ✓  Core
-- wp_term_relationships       ✓  Core
-- wp_comments                 ✓  Core (if needed)
-- wp_commentmeta              ✓  Core (if needed)
-- wp_options                  ✓  SELECTIVE (see 01-export-selective-options.sql)
--
-- wp_product_data             ✓  ERH custom (finder cache)
-- wp_product_daily_prices     ✓  ERH custom (price history)
-- wp_price_trackers           ✓  ERH custom (user alerts)
-- wp_product_views            ✓  ERH custom (popularity)
-- wp_erh_clicks               ✓  ERH custom (affiliate tracking)
-- wp_comparison_views         ✓  ERH custom (compare popularity)
-- wp_erh_email_queue          ✓  ERH custom (email queue)
--
-- wp_hft_tracked_links        ✓  HFT (retailer links)
-- wp_hft_price_history        ✓  HFT (price scraper history)
-- wp_hft_scrapers             ✓  HFT (scraper configs)
-- wp_hft_scraper_rules        ✓  HFT (scraper rules)
--
-- wp_social_users             ✓  NSL (for social login migration, then drop)
--
-- ============================================================
-- Tables to SKIP:
-- ============================================================
-- wp_links                    ✗  WordPress legacy (empty)
-- wp_*_oxygen_*               ✗  Oxygen Builder
-- wp_nsl_*                    ✗  Nextend Social Login (migrated to ERH)
-- wp_actionscheduler_*        ✗  Action Scheduler (WooCommerce leftover)
-- Any other old plugin tables  ✗
