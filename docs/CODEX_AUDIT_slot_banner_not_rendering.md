# Slot Banner Not Rendering — Audit Report (Widget + Per-Model Mode)

**Scope:** audit-only documentation. No runtime changes.

## A) Template Call Audit

**Call exists:** `template-parts/content-model.php` calls `tmw_render_model_slot_banner_zone()`.

**Exact placement:** the call is **immediately above** the marker comment:

```
<!-- === TMW-TAGS-BULLETPROOF-RESTORE === -->
```

**Function name + location:**
- Function: `tmw_render_model_slot_banner_zone((int) get_the_ID())`
- File/line range: `template-parts/content-model.php` (inside the `if ($tmw_model_tags_count !== null)` block, immediately before the marker).

**Important note:** the renderer call is wrapped by:

```
if ( $tmw_model_tags_count !== null ) :
    ... tmw_render_model_slot_banner_zone() ...
endif;
```

If `$tmw_model_tags_count` is **null**, the template **never calls the banner renderer**.

---

## B) Loader Audit

**Frontend renderer file:** `inc/frontend/tmw-slot-banner.php`.

**Where it is included:** `inc/bootstrap.php` includes the file directly:

```
require_once __DIR__ . '/frontend/tmw-slot-banner.php';
```

**Bootstrap inclusion on frontend:** `functions.php` includes `inc/bootstrap.php`, which loads the slot banner renderer on all frontend requests.

**Conclusion:** the renderer file **is loaded** on model pages (not an include/loader issue).

---

## C) Widget Area Audit

**Widget area registration (sidebar):** `inc/frontend/tmw-slot-banner.php`

- Sidebar ID: `tmw-model-slot-banner-global`
- Name: `Model Page – Slot Banner (Global)`

**Renderer usage:** `tmw_render_model_slot_banner_zone()` uses the **same** sidebar ID:

```
is_active_sidebar('tmw-model-slot-banner-global')

dynamic_sidebar('tmw-model-slot-banner-global')
```

**Conclusion:** sidebar registration and renderer ID **match**.

---

## D) Meta Key/Value Contract Audit

### Metabox (admin) saves
File: `inc/admin/tmw-slot-banner-metabox.php`

- **Enabled key**: `_tmw_slot_enabled`
  - Saved value: `'1'` when enabled; meta deleted when unchecked.
- **Mode key**: `_tmw_slot_mode`
  - Allowed values: `'widget'` or `'shortcode'`.
  - Defaults to `'shortcode'` if shortcode field is non-empty, else `'widget'`.
- **Shortcode key**: `_tmw_slot_shortcode`
  - Saved string (trimmed), deleted when empty.

### Frontend renderer reads
File: `inc/frontend/tmw-slot-banner.php`

- Reads `_tmw_slot_enabled` and checks `=== '1'`.
- Reads `_tmw_slot_mode` and accepts only `'widget'` or `'shortcode'`.
- Reads `_tmw_slot_shortcode` and trims; falls back to `'widget'` if empty.

### Mismatch check
- **Key names:** match exactly (`_tmw_slot_enabled`, `_tmw_slot_mode`, `_tmw_slot_shortcode`).
- **Values:** match expected formats (`'1'`, `'widget'`, `'shortcode'`).

**Conclusion:** meta contract **matches**; no key/value mismatch detected.

---

## E) Decision Logic Audit

**Decision tree in `tmw_render_model_slot_banner_zone()` (frontend):**

1. **Disabled:** if `_tmw_slot_enabled !== '1'` → returns `''` (renders nothing).
2. **Mode = shortcode:**
   - If shortcode empty → returns `''`.
   - Else runs `do_shortcode($shortcode)` and wraps in:
     ```
     <div class="tmw-slot-banner-zone"><div class="tmw-slot-banner">…</div></div>
     ```
   - Empty output from the shortcode also returns `''`.
3. **Mode = widget:**
   - Requires `is_active_sidebar('tmw-model-slot-banner-global')`.
   - If inactive → returns `''`.
   - Else `dynamic_sidebar()` output is wrapped in the same container.

**Widget mode support:** yes — code explicitly supports **widget mode** and uses the widget area ID above.

---

## F) Sanitization Audit

**Renderer output path:**
- The renderer returns raw shortcode/widget HTML and the template echoes it **without sanitization**.

**Observed sanitizers in the chain:**
- **None** for the slot banner output (`wp_kses_post`, `wp_kses`, `strip_tags`, `esc_html` are **not applied** to the slot banner output).

**Conclusion:** sanitization is **not** the cause of data-* attribute loss; output is already unescaped.

---

## Root Cause Conclusion

The slot banner **does not render on many model pages because the template call is gated** by the tags audit condition:

```
if ( $tmw_model_tags_count !== null ) :
    ... tmw_render_model_slot_banner_zone() ...
endif;
```

If `tmw_model_tags_count` is **not set** in query vars (i.e., `null`), the template **never calls the renderer at all**, so the chain breaks at the **template call** stage.

---

## Minimal Fix Plan (no code yet)

1. **template-parts/content-model.php**
   - Move the slot banner render call **outside** the `$tmw_model_tags_count !== null` gate, or ensure the query var is always defined.
2. **(Optional) inc/frontend/tmw-slot-banner.php**
   - No change needed unless a fallback mode is desired for missing shortcode output.
3. **(Optional) inc/admin/tmw-slot-banner-metabox.php**
   - No change needed; meta contract already matches frontend expectations.

---

**Verification:** docs-only change.
