-- ============================================================
-- Selective wp_options Export
-- ============================================================
-- Run this on the PRODUCTION database to export only needed
-- wp_options rows. Excludes Oxygen, old theme, old plugins.
--
-- Usage (from production server):
--   mysql production_db < 01-export-selective-options.sql
--
-- Or use mysqldump with WHERE clause:
--   mysqldump production_db wp_options --where="
--     option_name LIKE 'rank_math%'
--     OR option_name LIKE 'acf_%'
--     OR option_name LIKE 'erh_%'
--     OR option_name LIKE 'hft_%'
--     OR option_name IN (
--       'siteurl', 'home', 'blogname', 'blogdescription',
--       'permalink_structure', 'active_plugins', 'template',
--       'stylesheet', 'rewrite_rules', 'uploads_use_yearmonth_folders',
--       'thumbnail_size_w', 'thumbnail_size_h',
--       'medium_size_w', 'medium_size_h',
--       'large_size_w', 'large_size_h',
--       'date_format', 'time_format', 'timezone_string',
--       'default_role', 'users_can_register',
--       'show_on_front', 'page_on_front', 'page_for_posts',
--       'posts_per_page', 'blog_public'
--     )
--   " > wp_options_selective.sql
-- ============================================================

-- This query shows which options WILL be kept (for verification):
SELECT option_name, LEFT(option_value, 80) AS value_preview
FROM wp_options
WHERE
    -- ERH plugin settings
    option_name LIKE 'erh_%'

    -- HFT plugin settings
    OR option_name LIKE 'hft_%'

    -- ACF settings
    OR option_name LIKE 'acf_%'

    -- RankMath SEO settings
    OR option_name LIKE 'rank_math%'

    -- Core WordPress settings
    OR option_name IN (
        'siteurl',
        'home',
        'blogname',
        'blogdescription',
        'permalink_structure',
        'active_plugins',
        'template',
        'stylesheet',
        'current_theme',
        'rewrite_rules',
        'uploads_use_yearmonth_folders',
        'thumbnail_size_w',
        'thumbnail_size_h',
        'medium_size_w',
        'medium_size_h',
        'large_size_w',
        'large_size_h',
        'date_format',
        'time_format',
        'timezone_string',
        'default_role',
        'users_can_register',
        'show_on_front',
        'page_on_front',
        'page_for_posts',
        'posts_per_page',
        'blog_public',
        'default_comment_status',
        'default_ping_status',
        'comment_moderation',
        'comment_registration',
        'wp_user_roles'
    )
ORDER BY option_name;

-- ============================================================
-- Options that should be EXCLUDED (for reference):
-- ============================================================
-- option_name LIKE 'oxygen_%'        -- Oxygen Builder
-- option_name LIKE 'ct_%'            -- Oxygen CT
-- option_name LIKE 'widget_%'        -- Old widget settings
-- option_name LIKE 'theme_mods_%'    -- Old theme mods
-- option_name LIKE 'nsl_%'           -- Nextend Social Login (migrated to ERH)
-- option_name LIKE 'nextend%'        -- Nextend Social Login
-- option_name = 'sidebars_widgets'   -- Old sidebar config
