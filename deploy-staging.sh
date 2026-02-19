#!/usr/bin/env bash
# Deploy erh-theme, erh-core, and housefresh-tools to staging.
# Usage: bash deploy-staging.sh [theme|core|hft|all]
#   No argument = theme + core only (most common).
#   "all" includes housefresh-tools.

set -euo pipefail

REMOTE_USER="haxholmw"
REMOTE_HOST="162.19.222.172"
REMOTE_PORT="1988"
REMOTE_WP="/home/haxholmw/staging.eridehero.com"
SSH_CMD="ssh -p $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST"
SCP_CMD="scp -P $REMOTE_PORT"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ZIP_DIR="$SCRIPT_DIR"

# What to deploy
DEPLOY_THEME=false
DEPLOY_CORE=false
DEPLOY_HFT=false

case "${1:-default}" in
    theme)   DEPLOY_THEME=true ;;
    core)    DEPLOY_CORE=true ;;
    hft)     DEPLOY_HFT=true ;;
    all)     DEPLOY_THEME=true; DEPLOY_CORE=true; DEPLOY_HFT=true ;;
    default) DEPLOY_THEME=true; DEPLOY_CORE=true ;;
esac

# Step 1: Build zips
echo "==> Building zips..."
powershell.exe -ExecutionPolicy Bypass -File "$SCRIPT_DIR/build-zips.ps1"
echo ""

# Step 2: Upload selected zips
ZIPS_TO_UPLOAD=()
$DEPLOY_THEME && ZIPS_TO_UPLOAD+=("erh-theme.zip")
$DEPLOY_CORE  && ZIPS_TO_UPLOAD+=("erh-core.zip")
$DEPLOY_HFT   && ZIPS_TO_UPLOAD+=("housefresh-tools.zip")

echo "==> Uploading: ${ZIPS_TO_UPLOAD[*]}"
UPLOAD_PATHS=()
for zip in "${ZIPS_TO_UPLOAD[@]}"; do
    UPLOAD_PATHS+=("$ZIP_DIR/$zip")
done
$SCP_CMD "${UPLOAD_PATHS[@]}" "$REMOTE_USER@$REMOTE_HOST:/tmp/"
echo ""

# Step 3: Extract on server (remove old, unzip new)
echo "==> Deploying on server..."
REMOTE_COMMANDS=""

if $DEPLOY_THEME; then
    REMOTE_COMMANDS+="
echo '  -> erh-theme'
rm -rf $REMOTE_WP/wp-content/themes/erh-theme
unzip -qo /tmp/erh-theme.zip -d $REMOTE_WP/wp-content/themes/
rm /tmp/erh-theme.zip
"
fi

if $DEPLOY_CORE; then
    REMOTE_COMMANDS+="
echo '  -> erh-core'
rm -rf $REMOTE_WP/wp-content/plugins/erh-core
unzip -qo /tmp/erh-core.zip -d $REMOTE_WP/wp-content/plugins/
rm /tmp/erh-core.zip
"
fi

if $DEPLOY_HFT; then
    REMOTE_COMMANDS+="
echo '  -> housefresh-tools'
rm -rf $REMOTE_WP/wp-content/plugins/housefresh-tools
unzip -qo /tmp/housefresh-tools.zip -d $REMOTE_WP/wp-content/plugins/
rm /tmp/housefresh-tools.zip
"
fi

# Flush caches after deploy
REMOTE_COMMANDS+="
echo '  -> Flushing caches'
cd $REMOTE_WP && wp cache flush --quiet 2>/dev/null || true
"

$SSH_CMD bash -s <<< "$REMOTE_COMMANDS"

echo ""
echo "==> Done! Deployed to staging.eridehero.com"
