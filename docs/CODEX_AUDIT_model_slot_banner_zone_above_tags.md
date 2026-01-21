# AUDIT — Model Slot Banner Zone Above Tags (Widget + Per-Model Control)

**Scope**: Audit-only. No runtime changes. This report captures current slot banner implementations, sanitization touchpoints, the exact anchor for “above Tags,” and the files that must change for the follow-up implementation.

## 1) Current slot renderer locations (and duplicates)

### A) Inline renderer (template)
- **File**: `template-parts/content-model.php`
- **Block**: `// === TMW SLOT BANNER ZONE (v4.5.1) ===`
- **Notes**:
  - Full inline render logic exists inside the template (duplicate renderer).
  - Outputs `tmw-slot-banner-zone` / `tmw-slot-banner` wrapper with direct shortcode output.

### B) Module renderer (shared function)
- **File**: `inc/frontend/tmw-slot-banner.php`
- **Function**: `tmw_render_model_slot_banner(int $post_id): string`
- **Notes**:
  - Produces wrapper `<div class="tmw-slot-banner">` and sanitizes output.
  - Intended for reuse but currently duplicates inline template renderer.

## 2) Every place `wp_kses_post()` touches slot output

1. **Template debug block** (slot output length comparison)
   - **File**: `template-parts/content-model.php`
   - **Context**: Debug-only block near the top of the template uses `wp_kses_post($slot_output)` to compare raw vs sanitized output length.

2. **Module renderer** (output wrapper)
   - **File**: `inc/frontend/tmw-slot-banner.php`
   - **Context**: `tmw_render_model_slot_banner()` returns `'<div class="tmw-slot-banner">' . wp_kses_post($out) . '</div>'`.

## 3) Exact template anchor for “above Tags” (red pill labels row)

- **File**: `template-parts/content-model.php`
- **Anchor block**: `<!-- === TMW-TAGS-BULLETPROOF-RESTORE === -->`
- **Exact target**:
  - The tags row includes `<a class="label">` elements for each tag.
  - The new banner zone must be placed **above** this tags row (the “red pill labels row”).

## 4) Exact files that must change for the follow-up implementation

> **NOTE**: This audit PR does not modify any of these files.

1. `template-parts/content-model.php`
   - Remove inline `TMW SLOT BANNER ZONE (v4.5.1)` block.
   - Insert the new banner zone **above** the `TMW-TAGS-BULLETPROOF-RESTORE` block.
2. `inc/frontend/tmw-slot-banner.php`
   - Update the renderer to remove `wp_kses_post()` usage on trusted shortcode/widget output.
3. `inc/admin/tmw-slot-banner-metabox.php`
   - Remove default shortcode autofill behavior in the UI (no automatic placeholder -> real value).
4. `inc/frontend/` (new or existing widget/shortcode integration file)
   - Add the widget-controlled zone renderer (if not already present).
5. Any **new** widget registration or UI settings file required for per-model control.

## 5) Exact deletion checklist (for follow-up PR)

- [ ] Remove the **entire** inline `v4.5.1` slot zone block from `template-parts/content-model.php` (no commented remnants).
- [ ] Remove `wp_kses_post()` from the module renderer path.
- [ ] Remove default shortcode autofill behavior in the metabox UI.

## Zero-defect checklist

- [ ] Zero footprint when disabled/empty.
- [ ] Only **one** visual on frontend.
- [ ] No `wp_kses_post` on trusted shortcode/widget output.
- [ ] Preserve layout **100%**.

## Verification

- This audit PR is **docs-only** and does **not** change PHP, CSS, or templates.
