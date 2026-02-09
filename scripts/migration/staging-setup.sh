#!/bin/bash
# ============================================================
# ERideHero Staging Setup Script
# ============================================================
# Automates the staging deployment process.
# Run this on the STAGING server after fresh WordPress install.
#
# Prerequisites:
#   - Fresh WordPress installed on staging
#   - MySQL/MariaDB access configured
#   - WP-CLI installed
#   - SSH access to production server (for rsync)
#
# Usage:
#   chmod +x staging-setup.sh
#   ./staging-setup.sh
#
# The script will prompt for confirmation at each step.
# ============================================================

set -euo pipefail

# --- Configuration ---
# Update these values for your environment
PRODUCTION_URL="https://eridehero.com"
STAGING_URL="https://staging.eridehero.com"
PRODUCTION_SSH="user@production-server"
PRODUCTION_WP_PATH="/var/www/eridehero/public_html"
STAGING_WP_PATH="/var/www/staging.eridehero.com/public_html"
PRODUCTION_DB="eridehero_prod"
STAGING_DB="eridehero_staging"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

confirm() {
    read -p "$(echo -e "${YELLOW}$1 [y/N]${NC} ")" -n 1 -r
    echo
    [[ $REPLY =~ ^[Yy]$ ]]
}

# ============================================================
# Step 1: Verify WordPress installation
# ============================================================
echo ""
echo "============================================"
echo "  ERideHero Staging Setup"
echo "============================================"
echo ""

log_info "Step 1: Verifying WordPress installation..."

if ! wp core is-installed --path="$STAGING_WP_PATH" 2>/dev/null; then
    log_error "WordPress not found at $STAGING_WP_PATH"
    log_info "Install WordPress first, then re-run this script."
    exit 1
fi

WP_VERSION=$(wp core version --path="$STAGING_WP_PATH")
log_info "WordPress ${WP_VERSION} found at ${STAGING_WP_PATH}"

# ============================================================
# Step 2: Selective Database Import
# ============================================================
echo ""
log_info "Step 2: Selective database import..."

if confirm "Export and import database tables from production?"; then
    # Core WordPress tables
    CORE_TABLES="wp_users wp_usermeta wp_posts wp_postmeta wp_terms wp_termmeta wp_term_taxonomy wp_term_relationships wp_comments wp_commentmeta"

    # ERH custom tables
    ERH_TABLES="wp_product_data wp_product_daily_prices wp_price_trackers wp_product_views wp_erh_clicks wp_comparison_views wp_erh_email_queue"

    # HFT tables
    HFT_TABLES="wp_hft_tracked_links wp_hft_price_history wp_hft_scrapers wp_hft_scraper_rules"

    # NSL table (for social login migration)
    NSL_TABLES="wp_social_users"

    ALL_TABLES="$CORE_TABLES $ERH_TABLES $HFT_TABLES $NSL_TABLES"

    log_info "Exporting tables from production..."
    ssh "$PRODUCTION_SSH" "mysqldump $PRODUCTION_DB $ALL_TABLES --single-transaction --quick" > /tmp/erh_staging_tables.sql

    log_info "Exporting selective wp_options..."
    ssh "$PRODUCTION_SSH" "mysqldump $PRODUCTION_DB wp_options --where=\"
        option_name LIKE 'rank_math%'
        OR option_name LIKE 'acf_%'
        OR option_name LIKE 'erh_%'
        OR option_name LIKE 'hft_%'
        OR option_name IN (
            'siteurl', 'home', 'blogname', 'blogdescription',
            'permalink_structure', 'active_plugins', 'template',
            'stylesheet', 'current_theme', 'rewrite_rules',
            'uploads_use_yearmonth_folders',
            'thumbnail_size_w', 'thumbnail_size_h',
            'medium_size_w', 'medium_size_h',
            'large_size_w', 'large_size_h',
            'date_format', 'time_format', 'timezone_string',
            'default_role', 'users_can_register',
            'show_on_front', 'page_on_front', 'page_for_posts',
            'posts_per_page', 'blog_public', 'wp_user_roles'
        )
    \" --single-transaction" > /tmp/erh_staging_options.sql

    log_info "Importing into staging database..."
    mysql "$STAGING_DB" < /tmp/erh_staging_tables.sql
    mysql "$STAGING_DB" < /tmp/erh_staging_options.sql

    log_info "Database import complete."
    rm -f /tmp/erh_staging_tables.sql /tmp/erh_staging_options.sql
else
    log_warn "Skipping database import."
fi

# ============================================================
# Step 3: Copy Uploads Directory
# ============================================================
echo ""
log_info "Step 3: Copy uploads directory..."

if confirm "Rsync uploads from production?"; then
    log_info "Syncing uploads (this may take a while)..."
    rsync -avz --progress \
        "${PRODUCTION_SSH}:${PRODUCTION_WP_PATH}/wp-content/uploads/" \
        "${STAGING_WP_PATH}/wp-content/uploads/"
    log_info "Uploads sync complete."
else
    log_warn "Skipping uploads sync."
fi

# ============================================================
# Step 4: Install Theme + Plugins
# ============================================================
echo ""
log_info "Step 4: Install theme and plugins..."

if confirm "Copy theme and plugins to staging?"; then
    # These should be deployed from your git repo, not rsync from production.
    # Adjust paths to match your deployment method.

    log_info "Theme and plugins should be deployed from git."
    log_info "Manual steps:"
    echo "  1. Copy erh-theme/ → ${STAGING_WP_PATH}/wp-content/themes/"
    echo "  2. Copy erh-core/  → ${STAGING_WP_PATH}/wp-content/plugins/"
    echo "  3. Copy HFT plugin → ${STAGING_WP_PATH}/wp-content/plugins/"
    echo "  4. Install ACF Pro and RankMath Pro"
    echo ""

    if confirm "Run composer dump-autoload for erh-core?"; then
        cd "${STAGING_WP_PATH}/wp-content/plugins/erh-core"
        composer dump-autoload --no-dev
        log_info "Autoloader regenerated."
    fi

    log_info "Activating theme and plugins..."
    wp theme activate erh-theme --path="$STAGING_WP_PATH"
    wp plugin activate erh-core --path="$STAGING_WP_PATH"
    wp plugin activate advanced-custom-fields-pro --path="$STAGING_WP_PATH"
    wp plugin activate rank-math-seo-pro --path="$STAGING_WP_PATH"

    log_info "Theme and plugins activated."
else
    log_warn "Skipping theme/plugin setup."
fi

# ============================================================
# Step 5: ACF Field Sync
# ============================================================
echo ""
log_info "Step 5: ACF field sync..."
log_info "ACF JSON files in acf-json/ will be synced automatically."
log_info "Visit WP Admin → ACF → Field Groups → click 'Sync' on any pending groups."
log_warn "This step requires manual action in the admin UI."

# ============================================================
# Step 6: Run ERH Migrator (Product ACF Restructuring)
# ============================================================
echo ""
log_info "Step 6: Product ACF restructuring..."

if confirm "Run the product migrator to restructure ACF fields?"; then
    log_info "Running migration (dry-run first)..."
    wp erh migrate --source="$PRODUCTION_URL" --batch-size=50 --dry-run --path="$STAGING_WP_PATH"

    if confirm "Dry run complete. Proceed with actual migration?"; then
        wp erh migrate --source="$PRODUCTION_URL" --batch-size=50 --path="$STAGING_WP_PATH"
        log_info "Product migration complete."
    fi
else
    log_warn "Skipping product migration."
fi

# ============================================================
# Step 7: Social Login Migration
# ============================================================
echo ""
log_info "Step 7: Social login migration (NSL → ERH)..."

if confirm "Migrate social login data from wp_social_users to ERH user meta?"; then
    log_info "Running social login migration (dry-run first)..."
    wp erh migrate social --dry-run --path="$STAGING_WP_PATH"

    if confirm "Dry run complete. Proceed with actual migration?"; then
        wp erh migrate social --path="$STAGING_WP_PATH"
        log_info "Social login migration complete."
    fi
else
    log_warn "Skipping social login migration."
fi

# ============================================================
# Step 8: Update Site URLs
# ============================================================
echo ""
log_info "Step 8: Update site URLs..."

if confirm "Replace '${PRODUCTION_URL}' with '${STAGING_URL}' across all tables?"; then
    wp search-replace "$PRODUCTION_URL" "$STAGING_URL" --all-tables --path="$STAGING_WP_PATH"
    log_info "URL replacement complete."
else
    log_warn "Skipping URL replacement."
fi

# ============================================================
# Step 9: Regenerate Caches
# ============================================================
echo ""
log_info "Step 9: Regenerate caches..."

if confirm "Run all cache rebuild jobs?"; then
    log_info "Rebuilding product data cache..."
    wp erh cron run cache-rebuild --path="$STAGING_WP_PATH"

    log_info "Generating finder JSON files..."
    wp erh cron run finder-json --path="$STAGING_WP_PATH"

    log_info "Generating comparison JSON..."
    wp erh cron run comparison-json --path="$STAGING_WP_PATH"

    log_info "Generating search index..."
    wp erh cron run search-json --path="$STAGING_WP_PATH"

    log_info "Flushing caches..."
    wp cache flush --path="$STAGING_WP_PATH"
    wp rewrite flush --path="$STAGING_WP_PATH"

    log_info "All caches rebuilt."
else
    log_warn "Skipping cache rebuild."
fi

# ============================================================
# Step 10: Verify ERH_DISABLE_EMAILS
# ============================================================
echo ""
log_info "Step 10: Staging safety checks..."
log_warn "IMPORTANT: Add this to wp-config.php on staging:"
echo ""
echo "  define('ERH_DISABLE_EMAILS', true);"
echo ""
log_warn "This prevents notification emails to real users."

# ============================================================
# Done!
# ============================================================
echo ""
echo "============================================"
echo "  Staging Setup Complete!"
echo "============================================"
echo ""
log_info "Next steps:"
echo "  1. Add ERH_DISABLE_EMAILS to wp-config.php"
echo "  2. Sync ACF field groups in admin"
echo "  3. Run verification queries (04-post-import-verification.sql)"
echo "  4. Check legacy blocks (02-find-legacy-blocks.sql)"
echo "  5. Test all page types (see IMPLEMENTATION_CHECKLIST.md)"
echo ""
