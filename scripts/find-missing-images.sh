#!/bin/bash
# Find missing images referenced in published posts (all image URLs)
# Usage: Run on staging server from WP root

WPROOT="/home/haxholmw/staging.eridehero.com"
cd "$WPROOT" || exit 1

# Extract all image paths from published posts - broad regex
wp db query "SELECT post_content FROM wpdg_posts WHERE post_type = 'post' AND post_status = 'publish'" --skip-column-names 2>/dev/null \
  | grep -oP 'wp-content/uploads/[^"'"'"'\s<>)]+\.(jpg|jpeg|png|webp|gif)' \
  | sort -u > /tmp/erh_image_urls.txt

total=$(wc -l < /tmp/erh_image_urls.txt)
missing=0

echo "Checking $total unique image URLs in published posts..."
echo ""

while IFS= read -r path; do
  full="$WPROOT/$path"
  if [ ! -f "$full" ]; then
    echo "MISSING: $path"
    echo "$path" >> /tmp/erh_missing_images.txt
    missing=$((missing + 1))
  fi
done < /tmp/erh_image_urls.txt

echo ""
echo "=== Summary ==="
echo "Total image URLs: $total"
echo "Missing files: $missing"

rm -f /tmp/erh_image_urls.txt

if [ "$missing" -gt 0 ]; then
  echo ""
  echo "Downloading missing files from production..."
  echo ""

  downloaded=0
  failed=0

  while IFS= read -r path; do
    url="https://eridehero.com/$path"
    dest="$WPROOT/$path"
    mkdir -p "$(dirname "$dest")"
    http_code=$(curl -sS -w '%{http_code}' -o "$dest" "$url")
    if [ "$http_code" = "200" ]; then
      echo "  OK: $path"
      downloaded=$((downloaded + 1))
    else
      echo "  FAIL ($http_code): $path"
      rm -f "$dest"
      failed=$((failed + 1))
    fi
  done < /tmp/erh_missing_images.txt

  echo ""
  echo "Downloaded: $downloaded"
  echo "Failed: $failed"
fi

rm -f /tmp/erh_missing_images.txt
