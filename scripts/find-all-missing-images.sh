#!/bin/bash
# Find ALL missing images across all post types + postmeta
# Usage: Run on staging server from WP root

WPROOT="/home/haxholmw/staging.eridehero.com"
cd "$WPROOT" || exit 1

echo "Scanning all post content and postmeta for image references..."

# Extract image paths from ALL published post content (all post types)
wp db query "SELECT post_content FROM wpdg_posts WHERE post_status = 'publish' AND post_content LIKE '%wp-content/uploads/%'" --skip-column-names 2>/dev/null \
  | grep -oP 'wp-content/uploads/[^"\\s<>)]+\.(jpg|jpeg|png|webp|gif)' \
  | sort -u > /tmp/erh_all_images.txt

# Also check postmeta (featured images reference via attachment IDs, but some meta has direct URLs)
wp db query "SELECT meta_value FROM wpdg_postmeta WHERE meta_value LIKE '%wp-content/uploads/%'" --skip-column-names 2>/dev/null \
  | grep -oP 'wp-content/uploads/[^"\\s<>)]+\.(jpg|jpeg|png|webp|gif)' \
  | sort -u >> /tmp/erh_all_images.txt

# Deduplicate
sort -u -o /tmp/erh_all_images.txt /tmp/erh_all_images.txt

total=$(wc -l < /tmp/erh_all_images.txt)
missing=0

echo "Checking $total unique image URLs..."
echo ""

while IFS= read -r path; do
  full="$WPROOT/$path"
  if [ ! -f "$full" ]; then
    echo "MISSING: $path"
    missing=$((missing + 1))
  fi
done < /tmp/erh_all_images.txt

echo ""
echo "=== Summary ==="
echo "Total image URLs: $total"
echo "Missing files: $missing"

rm -f /tmp/erh_all_images.txt
