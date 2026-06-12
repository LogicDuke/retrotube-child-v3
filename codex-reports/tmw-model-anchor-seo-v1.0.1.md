# TMW Model Anchor SEO v1.0.1

**[TMW-MODEL-ANCHOR]**  
PR Branch: `feature/tmw-model-anchor-seo-v1-0-1`  
Supersedes: `codex-reports/tmw-model-anchor-seo-v1.0.0.md`  
Commit: `fix: harden semantic model anchor output`

---

## Problems Fixed vs v1.0.0

| # | Problem | Fix |
|---|---------|-----|
| 1 | `<span class="screen-reader-text">` used — class not guaranteed in this theme | Replaced with `tmw_sr_text()` which emits `<span class="tmw-sr-only">` — the project's own helper |
| 2 | Both `$sr_label` and semantic hidden text output inside same anchor — duplicate hidden text | Removed `$sr_label` entirely from anchor output; semantic phrase via `$semantic_sr` is the only hidden text |
| 3 | `mb_strtolower()` called without availability guard — fatal on servers without mbstring | Wrapped in `function_exists('mb_strtolower')` with `strtolower()` fallback |
| 4 | Dead `$sr_label` variable assigned but never used | Removed both dead assignments |

---

## Files Changed

| File | Change |
|------|--------|
| `inc/tmw-video-hooks.php` | 2 helpers added; 2 anchor locations updated |

No other files touched.

---

## Helper Functions

### `tmw_get_model_primary_platform_label( int $post_id ): string`

Reads `_tmwseo_platform_primary` (set by TMW SEO Engine admin), falls back to first non-empty `_tmwseo_platform_username_{slug}`. Returns human label (`"LiveJasmin"`) or `''`. Defensive, no throws.

### `tmw_get_model_semantic_anchor_text( int $post_id, string $model_title ): string`

Returns:
- With platform: `View {Name} {Platform} webcam profile`
- Without: `View {Name} webcam model profile`

Hardened `mb_strtolower` guard:

```php
if (function_exists('mb_strtolower')) {
    $name_lc     = mb_strtolower($name, 'UTF-8');
    $platform_lc = mb_strtolower($platform, 'UTF-8');
} else {
    $name_lc     = strtolower($name);
    $platform_lc = strtolower($platform);
}
```

---

## Anchor Markup: Before / After

### Before (v1.0.0 — wrong)

```html
<a href="/model/anisyia/" class="tmw-view">
    <span class="tmw-sr-only">Open Anisyia profile</span>
    <span class="screen-reader-text">View Anisyia LiveJasmin webcam profile</span>
    <span aria-hidden="true">View profile</span>
</a>
```

Problems: two hidden phrases, wrong CSS class on second span.

### After (v1.0.1 — correct)

```html
<a href="/model/anisyia/" class="tmw-view">
    <span class="tmw-sr-only">View Anisyia LiveJasmin webcam profile</span>
    <span aria-hidden="true">View profile</span>
</a>
```

One hidden phrase. Uses `tmw_sr_text()` → `tmw-sr-only` class. No duplication.

### Non-linked fallback span

```html
<span class="tmw-view">
    <span aria-hidden="true">View profile</span>
</span>
```

No semantic hidden text when there is no link.

---

## Example Output for 3 Model Cards

### Anisyia — LiveJasmin primary platform

```html
<a href="/model/anisyia/" data-href="/model/anisyia/" class="tmw-view">
    <span class="tmw-sr-only">View Anisyia LiveJasmin webcam profile</span>
    <span aria-hidden="true">View profile</span>
</a>
```

### Abby Murray — no platform data

```html
<a href="/model/abby-murray/" data-href="/model/abby-murray/" class="tmw-view">
    <span class="tmw-sr-only">View Abby Murray webcam model profile</span>
    <span aria-hidden="true">View profile</span>
</a>
```

### Alice Schuster — Chaturbate username stored, no explicit primary

```html
<a href="/model/alice-schuster/" data-href="/model/alice-schuster/" class="tmw-view">
    <span class="tmw-sr-only">View Alice Schuster Chaturbate webcam profile</span>
    <span aria-hidden="true">View profile</span>
</a>
```

---

## Confirmation Checklist

- [x] Visible button text is `View profile` — unchanged for all users
- [x] No `screen-reader-text` class used anywhere
- [x] No duplicate hidden phrases in anchor
- [x] `$sr_label` variable removed — no dead code
- [x] `tmw_sr_text()` used for semantic hidden text — consistent with project
- [x] `mb_strtolower()` guarded with `function_exists()` + `strtolower()` fallback
- [x] No CSS changed
- [x] No layout, grid, flipbox animation, or mobile behavior changed
- [x] No URLs, sitemap, or Rank Math settings touched
- [x] No unrelated files modified

---

## Manual Test Checklist

- [ ] Open `/models/` — layout identical to before
- [ ] Visual: button reads **View profile** on every card
- [ ] DevTools → inspect Anisyia `a.tmw-view`:
  - One `<span class="tmw-sr-only">` containing `View Anisyia LiveJasmin webcam profile`
  - One `<span aria-hidden="true">` containing `View profile`
  - No `screen-reader-text` span present
- [ ] DevTools → inspect Abby Murray card: `tmw-sr-only` contains `View Abby Murray webcam model profile`
- [ ] DevTools → inspect a card with no platform: fallback phrase `webcam model profile` present
- [ ] PHP error log: no warnings or notices
- [ ] Mobile: cards flip correctly, layout unchanged
- [ ] Screaming Frog anchor text report: model-specific phrases visible in internal link report

---

## Rollback

```bash
git checkout HEAD~1 -- inc/tmw-video-hooks.php
git commit -m "Revert: harden semantic model anchor output"
```

No database changes. Rollback instant.
