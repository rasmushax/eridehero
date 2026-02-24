# Oxygen Block Migration

> **Status:** In progress — Phase 3 content blocks partially done
> **Last updated:** 2026-02-24
> **Inventory:** `oxygen-blocks-inventory.md` (full block-by-block details)

---

## Overview

201 Oxygen Builder block instances across 18 block types need to be removed or migrated. The blocks are invisible in the new theme (they render as empty HTML comments).

---

## Progress

### Phase 3: Content Block Migration (the hard part)

| Block Type | Instances | Posts | Target | Status |
|---|---|---|---|---|
| `ovsb-tip` | 12 | 8 | `acf/callout` | **DONE** |
| `ovsb-tip-rich-text` | 30 | 18 | `acf/callout` | **DONE** |
| `ovsb-icon-rich-text` | 5 | 3 | `acf/callout` | **DONE** |
| `ovsb-greybox-with-icon` | 6 | 2 | `acf/greybox` | **DONE** |
| `ovsb-discount-code` | 12 | 3 | Removed entirely | **DONE** |
| `ovsb-commuting-scooter-specs` | 3 | 1 | Manual table | TODO |
| `ovsb-highlights` | 1 | 1 | Convert to pros-cons | TODO |

**52 blocks migrated, 4 remaining in this phase.**

### Phase 1: Remove "Already Handled" Blocks (56 instances)

These blocks have native ACF equivalents or dedicated pages already — just strip the old Oxygen comments.

| Block Type | Instances | Posts | Replacement | Status |
|---|---|---|---|---|
| `ovsb-pros-cons` | 23 | 12 | `acf/proscons` block | **DONE** |
| `ovsb-performance-summary-2` | 8 | 8 | Removed (ACF performance profile on product page) | **DONE** |
| `ovsb-how-i-test` | 5 | 5 | Removed (no content, buying guides don't need it) | **DONE** |
| `ovsb-electric-scooter-quiz-block` | 11 | 11 | Removed (quiz page exists at `/tools/quiz/`) | **DONE** |
| `ovsb-electric-scooter-comparison-tool` | 8 | 8 | Removed (compare system exists at `/compare/`) | **DONE** |
| `ovsb-watt-calculator` | 1 | 1 | Removed (new `/tools/watt-calculator/` built) | **DONE** |
| `ovsb-e-scooter-savings-calculator` | 1 | 1 | Removed (new `/tools/electric-scooter-cost-calculator/` built) | **DONE** |

**Action:** Simple regex removal. Optionally add inline links to the replacement pages.

### Phase 2: Remove "Delete/Skip" Blocks (75 instances)

No content value — these served Oxygen-specific layout purposes or are superseded.

| Block Type | Instances | Posts | Why | Status |
|---|---|---|---|---|
| `ovsb-cta-button` | 61 | 20 | HFT/price-intel handles affiliate links | **DONE** |
| `ovsb-icon-h3` | 8 | 1 | `acf/icon-heading` | **DONE** |
| `ovsb-two-image-row` | 5 | 5 | Removed (decorative, already invisible) | **DONE** |
| `ovsb-wp-forms-icon-row` | 1 | 1 | Removed (WPForms deprecated) | **DONE** |

**Action:** Simple regex removal. Low risk since these blocks are already invisible.

### Phase 4: Verification

- [ ] Search all `post_content` for remaining `ovsb-` references
- [ ] Deactivate Oxygen Builder plugin
- [ ] Test all affected posts on staging

---

## What We Built

### ACF Blocks Created

**`acf/callout`** — Consolidates tip, note, warning, summary callouts.

| Field | Key | Type | Options |
|---|---|---|---|
| Style | `callout_style` | Select | tip, note, warning, summary |
| Title | `callout_title` | Text | Optional (defaults based on style) |
| Body | `callout_body` | WYSIWYG | Required |

Style → visual mapping:
- **tip** → lightbulb icon, purple (`--color-primary`)
- **note** → info icon, orange (`--color-info`)
- **warning** → alert-triangle icon, red (`--color-error`)
- **summary** → clipboard-check icon, green (`--color-success`)

Files: `erh-core/includes/blocks/callout/template.php`, `callout.css`

**`acf/greybox`** — Bordered box with icon + heading + body.

| Field | Key | Type | Options |
|---|---|---|---|
| Icon | `greybox_icon` | Select | x, info, zap, check |
| Color | `greybox_color` | Select | default, primary, error, success, info |
| Heading | `greybox_heading` | Text | Required |
| Body | `greybox_body` | WYSIWYG | Required |

Files: `erh-core/includes/blocks/greybox/template.php`, `greybox.css`

**`acf/proscons`** — Standalone pros & cons block with customizable headers.

| Field | Key | Type | Options |
|---|---|---|---|
| Pros Header | `proscons_pro_header` | Text | Default "Pros" |
| Pros | `proscons_pros` | Textarea | One item per line |
| Cons Header | `proscons_con_header` | Text | Default "Cons" |
| Cons | `proscons_cons` | Textarea | One item per line |
| Heading Level | `proscons_heading` | Select | h2, h3, h4, h5 |

Reuses existing `.pros-cons` CSS classes from `_pros-cons.css`. Supports custom headers like "Buy It If:" / "Skip It If:".

Files: `erh-core/includes/blocks/proscons/template.php`, `proscons.css`

**`acf/icon-heading`** — Heading with icon prefix (e.g., checkmark + "Pros").

| Field | Key | Type | Options |
|---|---|---|---|
| Icon | `icon_heading_icon` | Select | check, x, info, zap, lightbulb, alert-triangle, star, thumbs-up, thumbs-down |
| Heading Level | `icon_heading_level` | Select | h2, h3, h4, h5 |
| Title | `icon_heading_title` | Text | Required |

Files: `erh-core/includes/blocks/icon-heading/template.php`, `icon-heading.css`

All four blocks registered in `erh-core/includes/blocks/class-block-manager.php`.

### SVG Icons Added

Added to `erh-theme/template-parts/components/svg-sprite.php`:
- `icon-lightbulb` (Feather lightbulb)
- `icon-alert-triangle` (Feather alert-triangle)
- `icon-thumbs-up` (Feather thumbs-up)
- `icon-thumbs-down` (Feather thumbs-down)

### Migration Script

`scripts/migrate-oxygen-blocks.php` — WP-CLI eval-file script that converts Oxygen blocks to ACF blocks.

---

## Lessons Learned (Read Before Doing More Migration)

### 1. NEVER use `wp_update_post()` to save block JSON

`wp_update_post()` calls `wp_slash()` which adds backslashes, then `wp_unslash()` strips them on read. This destroys JSON escaping inside block comments.

**Problem:** `json_encode` produces `\"` for quotes in body HTML (like `href="..."`). `wp_update_post` strips the backslashes, leaving unescaped `"` inside the JSON string → invalid JSON → block silently fails to render.

**Solution:** Use `$wpdb->update()` directly:
```php
$wpdb->update(
    $wpdb->posts,
    ['post_content' => $content],
    ['ID' => $post->ID],
    ['%s'],
    ['%d']
);
clean_post_cache($post->ID);
```

### 2. Oxygen block attributes are FLAT, not nested

Oxygen stores attributes at the top level of the JSON, NOT under a `"data"` key:
```
<!-- wp:oxygen-vsb/ovsb-tip {"text_block-7-85_string":"The text..."} /-->
```

ACF blocks need them nested under `"data"` with field key mappings:
```
<!-- wp:acf/callout {"name":"acf/callout","data":{"callout_body":"<p>The text...</p>","_callout_body":"field_erh_callout_body"}} /-->
```

### 3. HTML entities in body content break JSON

Characters like `&rsquo;` (curly apostrophe), `&nbsp;`, `&gt;` in the Oxygen source data can end up as `\&rsquo;` in the stored JSON (single backslash before `&`). Since `\&` is not a valid JSON escape sequence, `json_decode()` fails silently and the block renders empty.

**Prevention:** Run body content through `html_entity_decode()` before encoding, or ensure no bare backslashes end up before `&` in the final output.

**Fix after the fact:** `wp search-replace '\&rsquo;' "'" wpdg_posts --include-columns=post_content` (and similar for `\&nbsp;`, `\&gt;`).

### 4. Newlines in body HTML become literal "n" in rendered output

When body HTML contains `\n` newlines (like `</p>\n<p>`), `json_encode` stores them as `\n` in the JSON. But through the slashing/unslashing process, the `\` gets consumed, leaving a literal `n` character between tags: `</p>n<p>`.

**Prevention:** Strip newlines from body HTML before JSON encoding:
```php
$body = str_replace(["\n", "\r"], '', $body);
```

### 5. `--dry-run` is a WP-CLI reserved flag

WP-CLI intercepts `--dry-run` as its own parameter. Use a positional argument instead:
```php
$dry_run = in_array('dry-run', $args ?? [], true);
```
Run as: `wp eval-file script.php dry-run`

### 6. Oxygen field names have variable numeric IDs

Field names like `text_block-7-85_string` have a numeric component (`85`) that varies per post. Use regex to match:
```php
preg_match('/^text_block-7-\d+_string$/', $key)
```

### 7. DB queries are fine for one-time fixes

For encoding issues in stored content, direct SQL or `wp search-replace` is faster and safer than writing PHP scripts that go through WordPress's content pipeline (which can introduce new escaping issues). These are one-time data fixes, not reusable migration logic.

### 8. Always verify with `json_decode()` after migration

After any batch content update, run a verification pass:
```php
$blocks = parse_blocks($post->post_content);
foreach ($blocks as $b) {
    if ($b['blockName'] === 'acf/callout') {
        $body = $b['attrs']['data']['callout_body'] ?? '';
        if (empty($body)) echo "BROKEN: post {$post->ID}";
    }
}
```

### 9. The fix-broken-json.php approach works for unescaped quotes

When JSON is broken by unescaped inner quotes, you can't use `json_decode`. Instead, extract field values by string position between known delimiters (e.g. between `"callout_body":"` and `","_callout_body"`), then rebuild valid JSON with `wp_json_encode`.

Script: `scripts/fix-broken-json.php`

---

## Remaining Work Estimate

| Phase | Remaining | Complexity | Approach |
|---|---|---|---|
| Phase 1: Remove handled blocks | **DONE** | — | — |
| Phase 2: Remove delete/skip blocks | 14 remaining | Easy | Single regex-removal script |
| Phase 3: Remaining content | 4 instances | Low | Manual: 1 table + 1 pros-cons |
| Phase 4: Verify + deactivate Oxygen | — | Easy | DB search + plugin toggle |

Phase 2 can be done in one script that strips all `<!-- wp:oxygen-vsb/ovsb-* ... /-->` comments for the targeted block types. No content conversion needed — just deletion.

---

## Scripts Reference

| Script | Purpose | Status |
|---|---|---|
| `scripts/migrate-oxygen-blocks.php` | Convert tip/greybox/discount blocks | Done (ran on staging) |
| `scripts/fix-block-newlines.php` | Fix literal "n" between HTML tags | Done (ran on staging) |
| `scripts/fix-broken-json.php` | Fix broken JSON from unescaped quotes | Done (ran on staging) |
| `scripts/migrate-proscons.php` | Convert pros-cons blocks to `acf/proscons` | Done (ran on staging) |
| `scripts/migrate-icon-heading.php` | Convert icon-h3 blocks to `acf/icon-heading` | Ready to run |

---

## Verification URLs (Staging)

| Block | URL | What to check |
|---|---|---|
| Greybox (5 blocks) | `/how-to-stop-on-a-longboard/` | 5 "Cons of..." grey boxes with X icon |
| Callout (tip) | `/how-to-stop-on-a-longboard/` | Purple tip about slide gloves |
| Callout (note) | `/can-you-ride-an-electric-scooter-in-the-rain/` | Orange note about IP rating digits |
| Callout (summary) | `/turboant-v8-electric-scooter-review/` | Green summary callout |
| Callout (warning) | `/electric-scooter-winter-guide/` | Red warning callout |
| Discount removed | `/skatebolt-tornado-ii-electric-skateboard-review/` | No coupon blocks |
| Greybox (note) | `/apollo-go-review/` | Grey box with "Note" heading |
| Pros-cons (standard) | `/electric-scooter-buying-guide/` | 7 blocks with "Pros"/"Cons" headers |
| Pros-cons (custom) | `/segway-ninebot-max-g2-review/` | "Buy It If" / "Skip It If" headers |
| Pros-cons (custom) | `/apollo-go-review/` | "Who Should Buy It" / "Who Should Look Elsewhere" |
| Icon heading | `/electric-scooter-throttles/` | 4 "Pros" with check, 4 "Cons" with x icon |
