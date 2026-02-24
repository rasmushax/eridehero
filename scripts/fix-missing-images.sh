#!/bin/bash
# Download missing images from production
# Usage: Run on staging server from WP root

WPROOT="/home/haxholmw/staging.eridehero.com"
PROD="https://eridehero.com"

cd "$WPROOT" || exit 1

# Get missing files list
bash find-missing-images.sh 2>/dev/null | grep '^MISSING:' | sed 's/MISSING: //' > /tmp/erh_missing.txt

count=$(wc -l < /tmp/erh_missing.txt)
echo "Downloading $count missing images from production..."
echo ""

downloaded=0
failed=0

while IFS= read -r path; do
    url="$PROD/$path"
    dest="$WPROOT/$path"

    # Ensure directory exists
    mkdir -p "$(dirname "$dest")"

    # Download
    http_code=$(curl -sS -w '%{http_code}' -o "$dest" "$url")

    if [ "$http_code" = "200" ]; then
        echo "OK: $path"
        downloaded=$((downloaded + 1))
    else
        echo "FAIL ($http_code): $path"
        rm -f "$dest"
        failed=$((failed + 1))
    fi
done < /tmp/erh_missing.txt

echo ""
echo "=== Summary ==="
echo "Downloaded: $downloaded"
echo "Failed: $failed"

rm -f /tmp/erh_missing.txt
