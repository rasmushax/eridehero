#!/usr/bin/env bash
# Deploy erh-theme, erh-core, and housefresh-tools to staging.
# Usage: bash deploy-staging.sh [theme|core|hft|all]
#   No argument = theme + core + hft (most common).

set -euo pipefail

REMOTE_USER="haxholmw"
REMOTE_HOST="162.19.222.172"
REMOTE_PORT="1988"
REMOTE_WP="/home/haxholmw/staging.eridehero.com"

# Detect environment: WSL uses /mnt/c/, Git Bash uses /c/
if [ -d "/mnt/c" ]; then
    WIN_HOME="/mnt/c/Users/rasmu"
else
    WIN_HOME="/c/Users/rasmu"
fi
# Copy key to WSL-native path with correct permissions (Windows NTFS can't do 600)
SSH_KEY="/tmp/.deploy-staging-key"
cp "$WIN_HOME/.ssh/deploy-staging" "$SSH_KEY" 2>/dev/null
chmod 600 "$SSH_KEY"
SSH_CMD="ssh -p $REMOTE_PORT -i $SSH_KEY $REMOTE_USER@$REMOTE_HOST"
SCP_CMD="scp -P $REMOTE_PORT -i $SSH_KEY"

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
    default) DEPLOY_THEME=true; DEPLOY_CORE=true; DEPLOY_HFT=true ;;
esac

# Step 1: Build zips
echo "==> Building zips..."
# Convert WSL/Git Bash path to Windows path for PowerShell
WIN_SCRIPT_DIR="$(echo "$SCRIPT_DIR" | sed -e 's|^/mnt/c/|C:\\|' -e 's|^/c/|C:\\|' -e 's|/|\\|g')"
powershell.exe -ExecutionPolicy Bypass -File "$WIN_SCRIPT_DIR\\build-zips.ps1"
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
