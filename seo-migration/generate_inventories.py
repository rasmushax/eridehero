#!/usr/bin/env python3
"""
Generate old-site and new-site URL inventories for SEO migration.

Parses sitemaps, GSC exports, RankMath redirections, and ThirstyAffiliates
data to produce two CSV files:

  - old_site_inventory.csv  — every URL known to exist on the old site
  - new_site_inventory.csv  — every URL on the new (staging) site

Run from the seo-migration/ directory:
    python3 generate_inventories.py
"""

import csv
import os
import re
import xml.etree.ElementTree as ET
from collections import defaultdict
from urllib.parse import urlparse, parse_qs

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
OLD_DIR = os.path.join(BASE_DIR, "old site")
NEW_DIR = os.path.join(BASE_DIR, "new site")
GSC_DIR = os.path.join(OLD_DIR, "GSC")

OLD_DOMAIN = "eridehero.com"
NEW_DOMAIN = "staging.eridehero.com"

# ─── Helpers ────────────────────────────────────────────────────────────

SITEMAP_NS = {"sm": "http://www.sitemaps.org/schemas/sitemap/0.9"}


def parse_sitemap_urls(filepath, skip_sub_sitemaps=False):
    """Extract <loc> URLs from a sitemap XML file."""
    urls = []
    try:
        tree = ET.parse(filepath)
        root = tree.getroot()
        for url_elem in root.findall(".//sm:url/sm:loc", SITEMAP_NS):
            if url_elem.text:
                urls.append(url_elem.text.strip())
        # Also handle sitemapindex entries (unless told to skip)
        if not skip_sub_sitemaps:
            for loc_elem in root.findall(".//sm:sitemap/sm:loc", SITEMAP_NS):
                if loc_elem.text:
                    urls.append(loc_elem.text.strip())
    except ET.ParseError:
        print(f"  WARNING: Could not parse {filepath}")
    return urls


def parse_gsc_table(filepath):
    """Parse a GSC Table.csv → list of (url, last_crawled)."""
    rows = []
    if not os.path.isfile(filepath):
        return rows
    with open(filepath, "r", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            url = row.get("URL", "").strip()
            crawled = row.get("Last crawled", "").strip()
            if url:
                rows.append((url, crawled))
    return rows


def normalise_path(url):
    """Extract path from a full URL, normalising to canonical form."""
    parsed = urlparse(url)
    path = parsed.path.rstrip("/") or "/"
    # Normalise to lowercase path
    path = path.lower()
    # Strip trailing slash for comparison (except root)
    if path != "/":
        path = path.rstrip("/")
    return path


def normalise_url(url):
    """Normalise URL: lowercase domain, strip protocol, strip trailing slash."""
    parsed = urlparse(url)
    host = parsed.hostname or ""
    path = parsed.path
    if path != "/":
        path = path.rstrip("/")
    return f"{host}{path}".lower()


def classify_old_url(path):
    """Classify an old-site path into a page type and group."""
    p = path.lower()

    # Products
    if p.startswith("/products/"):
        return "product", "Products"

    # Tools
    if p.startswith("/tool/") or p.startswith("/tools/"):
        if "finder" in p:
            return "tool", "Tools — Finders"
        if "comparison" in p:
            return "tool", "Tools — Comparison"
        if "deals" in p:
            return "tool", "Tools — Deals"
        if "coupons" in p:
            return "tool", "Tools — Coupons"
        if "calculator" in p:
            return "tool", "Tools — Calculators"
        return "tool", "Tools — Other"

    # Affiliate redirects
    if p.startswith("/go/"):
        return "affiliate-redirect", "Affiliate Redirects (/go/)"
    if p.startswith("/recommends/"):
        return "affiliate-redirect", "Affiliate Redirects (/recommends/)"

    # Author pages
    if p.startswith("/author/"):
        if "/page/" in p:
            return "author-pagination", "Author Pages — Pagination"
        return "author", "Author Pages"

    # Categories
    if p in ("/electric-scooters", "/hoverboards", "/electric-skateboards",
             "/electric-unicycles", "/skating", "/electric-bikes"):
        return "category", "Category Archives"

    # Sub-category archives
    if re.match(r"^/electric-scooters/(reviews|articles|buying-guides)", p):
        return "archive", "Sub-category Archives"

    # Pagination
    if "/page/" in p:
        return "pagination", "Pagination"

    # Compare
    if p.startswith("/compare/"):
        return "compare", "Compare Pages"

    # Deals
    if p.startswith("/deals/"):
        return "deals", "Deals Pages"

    # Legal / info pages
    if p in ("/about", "/contact", "/how-we-test", "/disclaimers",
             "/privacy-policy", "/privacy", "/terms-conditions", "/terms",
             "/editorial-policy", "/editorial", "/cookies",
             "/opt-out-preferences"):
        return "page", "Info / Legal Pages"

    # Login
    if p.startswith("/login") or p.startswith("/log-in"):
        return "page", "Login / Auth Pages"

    # Blog prefix (old URLs)
    if p.startswith("/blog/"):
        return "post", "Posts (blog/ prefix — old URL)"

    # Review prefix (old URLs)
    if p.startswith("/review/"):
        return "post", "Posts (review/ prefix — old URL)"

    # Feeds
    if p.endswith("/feed") or p.endswith("/feed/") or "feed/" in p:
        return "feed", "RSS Feeds"

    # Homepage
    if p == "/" or p == "":
        return "page", "Homepage"

    # Reviews (slug pattern)
    if p.endswith("-review") or p.endswith("-review/"):
        return "post", "Reviews"

    # Listicles / guides
    if any(p.startswith(f"/{prefix}") for prefix in (
        "best-", "fastest-", "how-to-", "how-", "what-", "where-", "can-you-",
        "are-"
    )):
        return "post", "Guides / Listicles"

    # Remaining posts (articles, guides, etc.)
    # If it's a simple path with no sub-directories, likely a post
    parts = p.strip("/").split("/")
    if len(parts) == 1 and parts[0]:
        return "post", "Posts — Other"

    return "unknown", "Uncategorised"


def classify_new_url(path, sitemap_source):
    """Classify a new-site path based on sitemap source and path."""
    p = path.lower()

    source_map = {
        "post-sitemap.xml": ("post", "Posts"),
        "page-sitemap.xml": ("page", "Pages"),
        "tool-sitemap.xml": ("tool", "Tools"),
        "category-sitemap.xml": ("category", "Category Archives"),
        "compare-sitemap.xml": ("compare", "Compare Pages"),
        "comparison-sitemap.xml": ("comparison-cpt", "Curated Comparisons"),
        "coupons-sitemap.xml": ("coupons", "Coupons"),
        "local-sitemap.xml": ("local", "Local Sitemap"),
    }
    if sitemap_source.startswith("products-sitemap"):
        return "product", "Products"
    if sitemap_source in source_map:
        return source_map[sitemap_source]

    return "unknown", "Uncategorised"


# ─── Main ───────────────────────────────────────────────────────────────

def main():
    print("=" * 60)
    print("SEO Migration — URL Inventory Generator")
    print("=" * 60)

    # ── 1. Parse old-site sitemaps ──────────────────────────────────
    print("\n[1/7] Parsing old-site sitemaps...")
    old_sitemap_urls = {}  # path → url
    old_sitemap_files = [
        "Posts XML Sitemap - ERideHero.xml",
        "Pages XML Sitemap - ERideHero.xml",
        "Category XML Sitemap - ERideHero.xml",
    ]
    for fname in old_sitemap_files:
        fpath = os.path.join(OLD_DIR, fname)
        if os.path.isfile(fpath):
            urls = parse_sitemap_urls(fpath)
            for u in urls:
                p = normalise_path(u)
                old_sitemap_urls[p] = u
            print(f"  {fname}: {len(urls)} URLs")
    print(f"  Total unique old sitemap paths: {len(old_sitemap_urls)}")

    # ── 2. Parse GSC data ───────────────────────────────────────────
    print("\n[2/7] Parsing GSC data...")
    gsc_statuses = {}  # normalised_url → {status, last_crawled}

    gsc_folders = {
        "Indexed pages": "indexed",
        "Page with redirect": "redirect",
        "Not found (404)": "404",
        "Excluded by noindex tag": "noindex",
        "Crawled - currently not indexed": "crawled-not-indexed",
        "Discovered – currently not indexed": "discovered-not-indexed",
        "Blocked by robots.txt": "blocked-robots",
        "Alternative page with proper canonical tag": "canonical-alt",
    }

    for folder_name, status_label in gsc_folders.items():
        table_path = os.path.join(GSC_DIR, folder_name, "Table.csv")
        rows = parse_gsc_table(table_path)
        for url, crawled in rows:
            norm = normalise_url(url)
            # Only store the highest-priority status
            existing = gsc_statuses.get(norm)
            if not existing:
                gsc_statuses[norm] = {
                    "status": status_label,
                    "last_crawled": crawled,
                    "raw_url": url,
                }
        print(f"  {folder_name}: {len(rows)} URLs")
    print(f"  Total unique GSC entries: {len(gsc_statuses)}")

    # ── 3. Parse RankMath redirections ──────────────────────────────
    print("\n[3/7] Parsing RankMath redirections...")
    rankmath_redirects = {}  # source_path → {destination, type, status}
    rm_files = [f for f in os.listdir(OLD_DIR)
                if f.startswith("eridehero_rank-math-redirections")]
    for fname in rm_files:
        fpath = os.path.join(OLD_DIR, fname)
        with open(fpath, "r", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            for row in reader:
                source = row.get("source", "").strip()
                dest = row.get("destination", "").strip()
                rtype = row.get("type", "").strip()
                rstatus = row.get("status", "").strip()
                if source:
                    # Normalise source to path form
                    src_path = "/" + source.strip("/")
                    rankmath_redirects[src_path.lower()] = {
                        "source_raw": source,
                        "destination": dest,
                        "type": rtype,
                        "status": rstatus,
                    }
    print(f"  RankMath redirect rules: {len(rankmath_redirects)}")

    # ── 4. Parse ThirstyAffiliates ──────────────────────────────────
    print("\n[4/7] Parsing ThirstyAffiliates export...")
    ta_links = {}  # slug → {name, destination_url, category, geo_links}
    ta_files = [f for f in os.listdir(OLD_DIR)
                if f.startswith("thirstyaffiliates-export")]
    for fname in ta_files:
        fpath = os.path.join(OLD_DIR, fname)
        with open(fpath, "r", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            for row in reader:
                slug = row.get("Slug", "").strip()
                name = row.get("Name", "").strip()
                dest = row.get("Destination URL", "").strip()
                cat = row.get("Categories (separated by semicolons)", "").strip()
                geo = row.get("Geolocations Links (format: AU:http://google.com.au separated by semicolon)", "").strip()
                if slug:
                    ta_links[slug] = {
                        "name": name,
                        "destination_url": dest,
                        "category": cat,
                        "has_geo_links": "yes" if geo else "no",
                    }
    print(f"  ThirstyAffiliates links: {len(ta_links)}")

    # ── 5. Parse new-site sitemaps ──────────────────────────────────
    print("\n[5/7] Parsing new-site sitemaps...")
    new_urls = {}  # path → {url, sitemap_source}
    new_sitemap_files = [f for f in os.listdir(NEW_DIR) if f.endswith(".xml")]
    for fname in sorted(new_sitemap_files):
        fpath = os.path.join(NEW_DIR, fname)
        # Skip sub-sitemap references from sitemap-index to avoid
        # polluting the inventory with .xml URLs
        is_index = fname == "sitemap-index.xml"
        urls = parse_sitemap_urls(fpath, skip_sub_sitemaps=False)
        for u in urls:
            p = normalise_path(u)
            # Skip .xml/.kml URLs (sub-sitemap references, not real pages)
            if p.endswith(".xml") or p.endswith(".kml"):
                continue
            new_urls[p] = {
                "url": u,
                "sitemap_source": fname,
            }
        real_count = sum(1 for u in urls
                         if not normalise_path(u).endswith((".xml", ".kml")))
        if real_count:
            print(f"  {fname}: {real_count} URLs")
    print(f"  Total unique new site paths: {len(new_urls)}")

    # ── 6. Build old-site inventory ─────────────────────────────────
    print("\n[6/7] Building old-site inventory...")

    # Collect ALL unique old-site paths from every source
    all_old = {}  # normalised_path → record dict

    def ensure_record(path, raw_url=""):
        if path not in all_old:
            all_old[path] = {
                "path": path,
                "raw_url": raw_url,
                "sources": set(),
                "page_type": "",
                "group": "",
                "gsc_status": "",
                "gsc_last_crawled": "",
                "has_rankmath_redirect": "",
                "rankmath_dest": "",
                "rankmath_type": "",
                "is_thirstyaffiliate": "",
                "ta_name": "",
                "ta_dest_url": "",
                "exists_on_new_site": "",
                "new_site_path": "",
                "notes": "",
            }
        return all_old[path]

    # From sitemaps
    for path, url in old_sitemap_urls.items():
        rec = ensure_record(path, url)
        rec["sources"].add("sitemap")

    # From GSC (only eridehero.com URLs, not webmail/cpanel)
    for norm_url, data in gsc_statuses.items():
        raw = data["raw_url"]
        parsed = urlparse(raw)
        host = (parsed.hostname or "").lower()
        # Skip non-main-site URLs
        if host not in ("eridehero.com", "www.eridehero.com"):
            continue
        path = normalise_path(raw)

        # Skip junk URLs (query-only variants, Bing redirect junk, etc.)
        if "ck/a?" in path or "cx_tag_filter" in raw:
            continue
        # Skip URLs with moderation hashes, unapproved comments
        if "unapproved=" in raw or "moderation-hash=" in raw:
            continue
        # Skip search URLs
        if parsed.path == "/" and parsed.query:
            qs = parse_qs(parsed.query)
            if "s" in qs or "page_posts" in qs:
                continue
            # Skip UTM-only homepage variants, affiliate tracking, etc.
            param_keys = set(qs.keys())
            junk_params = {"utm_source", "utm_medium", "utm_campaign",
                           "affiliate", "gspk", "gsxid", "trk",
                           "ref", "fbclid", "stream"}
            if param_keys and param_keys.issubset(junk_params | {"utm_source", "utm_medium", "utm_campaign"}):
                continue

        rec = ensure_record(path, raw)
        rec["sources"].add(f"gsc:{data['status']}")
        # Set GSC status (prefer indexed over others)
        if data["status"] == "indexed" or not rec["gsc_status"]:
            rec["gsc_status"] = data["status"]
            rec["gsc_last_crawled"] = data["last_crawled"]

    # From RankMath redirects (as source URLs)
    for src_path, rdata in rankmath_redirects.items():
        rec = ensure_record(src_path, "")
        rec["sources"].add("rankmath-redirect-source")
        rec["has_rankmath_redirect"] = "yes"
        rec["rankmath_dest"] = rdata["destination"]
        rec["rankmath_type"] = rdata["type"]

    # From ThirstyAffiliates (as /recommends/ URLs)
    for slug, tdata in ta_links.items():
        path = f"/recommends/{slug}"
        rec = ensure_record(path, "")
        rec["sources"].add("thirstyaffiliates")
        rec["is_thirstyaffiliate"] = "yes"
        rec["ta_name"] = tdata["name"]
        rec["ta_dest_url"] = tdata["destination_url"]

    # Classify and enrich each record
    for path, rec in all_old.items():
        # Classify
        ptype, group = classify_old_url(path)
        rec["page_type"] = ptype
        rec["group"] = group

        # Check for RankMath redirect (if not already set via source)
        if rec["has_rankmath_redirect"] != "yes":
            if path in rankmath_redirects:
                rdata = rankmath_redirects[path]
                rec["has_rankmath_redirect"] = "yes"
                rec["rankmath_dest"] = rdata["destination"]
                rec["rankmath_type"] = rdata["type"]

        # Check if path exists on new site
        if path in new_urls:
            rec["exists_on_new_site"] = "yes"
            rec["new_site_path"] = path
        else:
            rec["exists_on_new_site"] = "no"

        # Add notes for special cases
        notes = []

        # /1000 suffix
        if path.endswith("/1000"):
            notes.append("Has /1000 suffix (Oxygen Builder artifact)")
            base = path.rsplit("/1000", 1)[0]
            if base in new_urls:
                notes.append(f"Base path {base} exists on new site")

        # Double-slash URLs
        if "//" in path and not path.startswith("//"):
            notes.append("Contains double-slash (malformed URL)")

        # Query param URLs from GSC
        raw = rec.get("raw_url", "")
        parsed = urlparse(raw)
        if parsed.query:
            qs_keys = list(parse_qs(parsed.query).keys())
            if "ids" in qs_keys:
                notes.append(f"Comparison tool URL with product IDs")
            elif "tag" in qs_keys:
                notes.append("Has affiliate ?tag= parameter")
            elif "ref" in qs_keys:
                notes.append("Has ?ref= tracking parameter")
            elif qs_keys:
                notes.append(f"Has query params: {', '.join(qs_keys)}")

        # OPT suffix on product URLs
        if path.endswith("/opt"):
            notes.append("Product URL with /OPT/ suffix (likely Oxygen)")

        # Browser name suffixes
        for browser in ("firefox", "chrome", "edge", "crios", "webkit",
                        "opr", "version"):
            if path.endswith(f"/{browser}"):
                notes.append(f"Product URL with /{browser}/ suffix (bot/junk)")
                break

        rec["notes"] = "; ".join(notes)

        # Convert sources set to sorted string
        rec["sources"] = ", ".join(sorted(rec["sources"]))

    # Sort and write old-site CSV
    old_records = sorted(all_old.values(), key=lambda r: (r["group"], r["path"]))

    old_csv_path = os.path.join(BASE_DIR, "old_site_inventory.csv")
    old_fields = [
        "path", "page_type", "group", "sources",
        "gsc_status", "gsc_last_crawled",
        "has_rankmath_redirect", "rankmath_type", "rankmath_dest",
        "is_thirstyaffiliate", "ta_name", "ta_dest_url",
        "exists_on_new_site", "new_site_path",
        "notes",
    ]
    with open(old_csv_path, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=old_fields, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(old_records)

    print(f"  Wrote {len(old_records)} rows → {old_csv_path}")

    # ── 7. Build new-site inventory ─────────────────────────────────
    print("\n[7/7] Building new-site inventory...")

    new_records = []
    for path, data in sorted(new_urls.items()):
        ptype, group = classify_new_url(path, data["sitemap_source"])

        # Check if old site has an equivalent
        has_old = "yes" if path in old_sitemap_urls else "no"

        # Try to find the old path if different
        old_match = ""
        if has_old == "no":
            # Check common pattern changes
            candidates = find_old_equivalent(path, old_sitemap_urls, all_old)
            if candidates:
                old_match = candidates[0]

        new_records.append({
            "path": path,
            "staging_url": data["url"],
            "page_type": ptype,
            "group": group,
            "sitemap_source": data["sitemap_source"],
            "has_old_equivalent": has_old,
            "old_site_path": path if has_old == "yes" else old_match,
            "notes": "",
        })

    new_csv_path = os.path.join(BASE_DIR, "new_site_inventory.csv")
    new_fields = [
        "path", "page_type", "group", "sitemap_source",
        "has_old_equivalent", "old_site_path", "notes",
    ]
    with open(new_csv_path, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=new_fields, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(new_records)

    print(f"  Wrote {len(new_records)} rows → {new_csv_path}")

    # ── Summary ─────────────────────────────────────────────────────
    print("\n" + "=" * 60)
    print("SUMMARY")
    print("=" * 60)

    # Group counts for old site
    group_counts = defaultdict(int)
    gsc_indexed_count = 0
    redirect_count = 0
    ta_count = 0
    exists_on_new = 0
    for rec in old_records:
        group_counts[rec["group"]] += 1
        if rec["gsc_status"] == "indexed":
            gsc_indexed_count += 1
        if rec["has_rankmath_redirect"] == "yes":
            redirect_count += 1
        if rec["is_thirstyaffiliate"] == "yes":
            ta_count += 1
        if rec["exists_on_new_site"] == "yes":
            exists_on_new += 1

    print(f"\nOld site: {len(old_records)} total URLs")
    print(f"  GSC-indexed: {gsc_indexed_count}")
    print(f"  Have RankMath redirect: {redirect_count}")
    print(f"  ThirstyAffiliates links: {ta_count}")
    print(f"  Also exist on new site (same path): {exists_on_new}")
    print(f"\n  By group:")
    for group, count in sorted(group_counts.items()):
        print(f"    {group}: {count}")

    # New site counts
    new_group_counts = defaultdict(int)
    new_has_old = 0
    for rec in new_records:
        new_group_counts[rec["group"]] += 1
        if rec["has_old_equivalent"] == "yes":
            new_has_old += 1

    print(f"\nNew site: {len(new_records)} total URLs")
    print(f"  Have old-site equivalent (same path): {new_has_old}")
    print(f"\n  By group:")
    for group, count in sorted(new_group_counts.items()):
        print(f"    {group}: {count}")

    print(f"\nFiles written:")
    print(f"  {old_csv_path}")
    print(f"  {new_csv_path}")
    print()


def find_old_equivalent(new_path, old_sitemap_urls, all_old):
    """Try to find the old-site equivalent for a new-site path."""
    candidates = []
    p = new_path.lower()

    # Pattern: /tools/X → /tool/X (singular vs plural)
    if p.startswith("/tools/"):
        old_p = "/tool/" + p[7:]
        if old_p in old_sitemap_urls or old_p in all_old:
            candidates.append(old_p)
        # Also check for renamed tools
        slug = p[7:]  # strip /tools/
        renames = {
            "battery-charging-time-calculator": "battery-charge-time-calculator",
            "electric-scooter-range-calculator": "electric-scooter-range-calculator",
            "electric-scooter-laws-by-state": None,  # new, no old equivalent
            "electric-bike-laws-by-state": None,  # new, no old equivalent
        }
        if slug in renames and renames[slug]:
            old_p = f"/tool/{renames[slug]}"
            if old_p in old_sitemap_urls or old_p in all_old:
                candidates.append(old_p)

    # Pattern: /reviews/ → /electric-scooters/reviews/
    if p == "/reviews":
        old_p = "/electric-scooters/reviews"
        if old_p in old_sitemap_urls or old_p in all_old:
            candidates.append(old_p)

    # Pattern: /articles/ → /electric-scooters/articles/
    if p == "/articles":
        old_p = "/electric-scooters/articles"
        if old_p in old_sitemap_urls or old_p in all_old:
            candidates.append(old_p)

    # Pattern: /buying-guides/ → /electric-scooters/buying-guides/
    if p == "/buying-guides":
        old_p = "/electric-scooters/buying-guides"
        if old_p in old_sitemap_urls or old_p in all_old:
            candidates.append(old_p)

    # Pattern: /editorial/ → /editorial-policy/
    if p == "/editorial":
        for old_p in ("/editorial-policy",):
            if old_p in old_sitemap_urls or old_p in all_old:
                candidates.append(old_p)

    # Pattern: /terms/ → /terms-conditions/
    if p == "/terms":
        for old_p in ("/terms-conditions",):
            if old_p in old_sitemap_urls or old_p in all_old:
                candidates.append(old_p)

    # Pattern: /privacy/ → /privacy-policy/
    if p == "/privacy":
        for old_p in ("/privacy-policy",):
            if old_p in old_sitemap_urls or old_p in all_old:
                candidates.append(old_p)

    # Pattern: /deals/electric-scooters/ → /tool/electric-scooter-deals/
    if p.startswith("/deals/"):
        category_slug = p.split("/deals/")[1].rstrip("/")
        # electric-scooters → electric-scooter
        singular = category_slug.rstrip("s") if category_slug.endswith("s") else category_slug
        old_p = f"/tool/{singular}-deals"
        if old_p in old_sitemap_urls or old_p in all_old:
            candidates.append(old_p)

    # Pattern: /electric-scooter-finder/ → /tool/electric-scooter-finder/
    if p.endswith("-finder") and not p.startswith("/tool"):
        slug = p.strip("/")
        old_p = f"/tool/{slug}"
        if old_p in old_sitemap_urls or old_p in all_old:
            candidates.append(old_p)

    # Pattern: /tools/electric-scooter-range-calculator → /electric-scooter-range-calculator (was a post)
    if p == "/tools/electric-scooter-range-calculator":
        old_p = "/electric-scooter-range-calculator"
        if old_p in old_sitemap_urls or old_p in all_old:
            candidates.append(old_p)

    # Pattern: /coupons/electric-scooters → /tool/electric-scooter-coupons
    if p.startswith("/coupons/"):
        category_slug = p.split("/coupons/")[1].rstrip("/")
        singular = category_slug.rstrip("s") if category_slug.endswith("s") else category_slug
        old_p = f"/tool/{singular}-coupons"
        if old_p in old_sitemap_urls or old_p in all_old:
            candidates.append(old_p)

    # Pattern: /compare/electric-scooters/ → /tool/electric-scooter-comparison/
    if p.startswith("/compare/"):
        category_slug = p.split("/compare/")[1].rstrip("/")
        singular = category_slug.rstrip("s") if category_slug.endswith("s") else category_slug
        old_p = f"/tool/{singular}-comparison"
        if old_p in old_sitemap_urls or old_p in all_old:
            candidates.append(old_p)

    return candidates


if __name__ == "__main__":
    main()
