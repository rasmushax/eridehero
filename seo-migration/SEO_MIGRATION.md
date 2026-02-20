# SEO Migration

Redirect mapping and URL audit for the Oxygen Builder → custom theme migration. The goal is to ensure every indexed URL on the old site either maps 1:1 to the new site or gets a proper 301 redirect, preserving search rankings and link equity.

## Folder Structure

```
seo-migration/
├── old site/                        # Data exported from the live (old) site
│   ├── XML Sitemap - ERideHero.xml  # Sitemap index (links to post, page, tool, category sitemaps)
│   ├── Posts XML Sitemap - ERideHero.xml   # All post URLs (reviews, guides, listicles)
│   ├── Pages XML Sitemap - ERideHero.xml   # All page URLs
│   ├── Category XML Sitemap - ERideHero.xml # Category archive URLs
│   ├── eridehero_rank-math-redirections-*.csv  # RankMath redirect rules (source → destination, type, status)
│   ├── thirstyaffiliates-export-*.csv          # ThirstyAffiliates links (name, destination URL, slug, geo links)
│   └── GSC/                         # Google Search Console index coverage report
│       ├── Indexed pages/           # URLs Google has indexed (Table.csv = URL list)
│       ├── Page with redirect/      # URLs GSC sees as redirected
│       ├── Excluded by noindex tag/ # URLs blocked by noindex
│       ├── Crawled - currently not indexed/  # Crawled but not indexed
│       ├── Discovered – currently not indexed/ # Known but not crawled
│       ├── Blocked by robots.txt/   # Blocked by robots.txt
│       ├── Not found (404)/         # URLs returning 404
│       ├── Alternative page with proper canonical tag/ # Canonical duplicates
│       ├── Chart.csv                # Overview chart data
│       ├── Critical issues.csv      # Critical indexing issues
│       ├── Non-critical issues.csv  # Non-critical issues
│       └── Metadata.csv            # Export metadata
│
└── new site/                        # Sitemaps from staging (new custom theme)
    ├── sitemap-index.xml            # Sitemap index (12 sub-sitemaps)
    ├── post-sitemap.xml             # Blog posts — 114 URLs
    ├── page-sitemap.xml             # Pages — 24 URLs
    ├── products-sitemap1.xml        # Products batch 1 — 11 URLs
    ├── products-sitemap2.xml        # Products batch 2 — 2 URLs
    ├── products-sitemap3.xml        # Products batch 3 — 7 URLs
    ├── tool-sitemap.xml             # Tools & calculators — 7 URLs
    ├── comparison-sitemap.xml       # Comparison CPT — 1 URL
    ├── category-sitemap.xml         # Category archives — 5 URLs
    ├── compare-sitemap.xml          # Compare pages — 6 URLs
    ├── coupons-sitemap.xml          # Coupon pages — 1 URL
    └── local-sitemap.xml            # Local sitemap — 1 URL
```

**New site URL totals:** ~179 URLs across all sitemaps (downloaded 2026-02-20 from staging.eridehero.com).

## Key Files Explained

| File | What it contains |
|------|-----------------|
| **RankMath redirections CSV** | All 301/302 redirects currently configured in RankMath. Columns: id, source, matching type, destination, HTTP type, status. These must be preserved or remapped in the new site. |
| **ThirstyAffiliates CSV** | Affiliate link slugs and destinations. The old site uses ThirstyAffiliates (`/recommends/slug/`); the new site uses HFT's `/go/` system. Each TA slug needs mapping to the equivalent `/go/` URL or direct affiliate link. |
| **XML Sitemaps** | Complete URL inventory of the old site — posts, pages, tools, categories. Primary source for building the old→new URL map. |
| **GSC Indexed pages** | URLs Google has actually indexed. These are the highest priority for redirect mapping — losing these means losing rankings. |
| **GSC Page with redirect** | URLs already redirecting. Useful to check for redirect chains (old redirect → old URL → new URL = 2 hops, bad). |
| **GSC Not found (404)** | Already-broken URLs. Low priority but good to audit for any that still get traffic. |

## Migration Workflow

1. Parse old site sitemaps → full URL list
2. Cross-reference with GSC indexed pages → prioritized list
3. Generate new site URL list from staging
4. Diff old vs new → identify matches, changes, and missing URLs
5. Map RankMath redirects → port to new site (update destinations if URL structure changed)
6. Map ThirstyAffiliates slugs → HFT `/go/` equivalents
7. Generate final redirect map (nginx/WordPress format)
8. Validate: no redirect chains, no loops, all indexed URLs covered
