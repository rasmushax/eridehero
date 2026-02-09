-- ============================================================
-- Find Posts with Legacy ACF Blocks
-- ============================================================
-- Run on staging AFTER import to identify posts that need
-- manual block conversion.
--
-- These old blocks will render as empty/unsupported in the
-- new theme. They need to be manually replaced with new
-- equivalents in the block editor.
--
-- Legacy → New mapping:
--   acf/top3           → acf/buying-guide-table
--   acf/toppicks       → acf/listicle-item
--   acf/simplebgitem   → acf/listicle-item
--   acf/bgoverview     → acf/listicle-item
--   acf/thisprodprice  → Built into product page (price-intel.php)
--   acf/super-accordion → acf/accordion
--   acf/bfdeal         → Not needed (removed)
--   acf/relatedproducts → Not needed (removed)
-- ============================================================

-- Count posts per legacy block type
SELECT
    CASE
        WHEN post_content LIKE '%wp:acf/simplebgitem%' THEN 'acf/simplebgitem'
        WHEN post_content LIKE '%wp:acf/top3%' THEN 'acf/top3'
        WHEN post_content LIKE '%wp:acf/toppicks%' THEN 'acf/toppicks'
        WHEN post_content LIKE '%wp:acf/bgoverview%' THEN 'acf/bgoverview'
        WHEN post_content LIKE '%wp:acf/thisprodprice%' THEN 'acf/thisprodprice'
        WHEN post_content LIKE '%wp:acf/super-accordion%' THEN 'acf/super-accordion'
        WHEN post_content LIKE '%wp:acf/bfdeal%' THEN 'acf/bfdeal'
        WHEN post_content LIKE '%wp:acf/relatedproducts%' THEN 'acf/relatedproducts'
    END AS legacy_block,
    COUNT(*) AS post_count
FROM wp_posts
WHERE post_status = 'publish'
AND (
    post_content LIKE '%wp:acf/simplebgitem%'
    OR post_content LIKE '%wp:acf/top3%'
    OR post_content LIKE '%wp:acf/toppicks%'
    OR post_content LIKE '%wp:acf/bgoverview%'
    OR post_content LIKE '%wp:acf/thisprodprice%'
    OR post_content LIKE '%wp:acf/super-accordion%'
    OR post_content LIKE '%wp:acf/bfdeal%'
    OR post_content LIKE '%wp:acf/relatedproducts%'
)
GROUP BY legacy_block
ORDER BY post_count DESC;

-- ============================================================
-- Detailed list: posts with legacy blocks (prioritize by type)
-- ============================================================
SELECT
    p.ID,
    p.post_title,
    p.post_type,
    p.post_date,
    CASE
        WHEN p.post_content LIKE '%wp:acf/simplebgitem%' THEN 'simplebgitem'
        ELSE ''
    END AS has_simplebgitem,
    CASE
        WHEN p.post_content LIKE '%wp:acf/top3%' THEN 'top3'
        ELSE ''
    END AS has_top3,
    CASE
        WHEN p.post_content LIKE '%wp:acf/toppicks%' THEN 'toppicks'
        ELSE ''
    END AS has_toppicks,
    CASE
        WHEN p.post_content LIKE '%wp:acf/bgoverview%' THEN 'bgoverview'
        ELSE ''
    END AS has_bgoverview,
    CASE
        WHEN p.post_content LIKE '%wp:acf/thisprodprice%' THEN 'thisprodprice'
        ELSE ''
    END AS has_thisprodprice,
    CASE
        WHEN p.post_content LIKE '%wp:acf/super-accordion%' THEN 'super-accordion'
        ELSE ''
    END AS has_accordion
FROM wp_posts p
WHERE p.post_status = 'publish'
AND (
    p.post_content LIKE '%wp:acf/simplebgitem%'
    OR p.post_content LIKE '%wp:acf/top3%'
    OR p.post_content LIKE '%wp:acf/toppicks%'
    OR p.post_content LIKE '%wp:acf/bgoverview%'
    OR p.post_content LIKE '%wp:acf/thisprodprice%'
    OR p.post_content LIKE '%wp:acf/super-accordion%'
)
ORDER BY p.post_type, p.post_date DESC;
