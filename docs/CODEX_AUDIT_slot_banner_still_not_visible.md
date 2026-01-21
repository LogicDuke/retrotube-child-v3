# Slot Banner Visibility Audit (Widget Active + Model Enabled)

## A) Confirm render call is **not** gated by tag count

`template-parts/content-model.php` shows the `tmw_render_model_slot_banner_zone()` call **before** the `if ($tmw_model_tags_count !== null)` tag block. The call is **not** wrapped by the tag-count gate; therefore the banner render function should run regardless of tag query vars. (No gating found.)

- Render call: `tmw_render_model_slot_banner_zone((int) get_the_ID())`.
- Tag gate appears **after** the call.

## B) Renderer return-path audit (`tmw_render_model_slot_banner_zone()`)

File: `inc/frontend/tmw-slot-banner.php`

Return-`''` conditions and what must be true at runtime:

1. **Enabled meta check**
   - Condition: `if (!$enabled) { return ''; }`
   - Runtime trigger: Model meta `_tmw_slot_enabled` is not `'1'`.

2. **Shortcode mode + empty shortcode**
   - Condition: `if ($mode === 'shortcode') { $shortcode = tmw_model_slot_get_shortcode(); if ($shortcode === '') { return ''; } }`
   - Runtime trigger: Slot mode resolves to `shortcode` (either explicit meta or auto-resolve) **and** `_tmw_slot_shortcode` meta is empty.

3. **Shortcode mode + empty output**
   - Condition: `if ($output_len === 0) { return ''; }` after `do_shortcode($shortcode)`
   - Runtime trigger: Shortcode callback returns empty/whitespace-only output.

4. **Widget mode + inactive sidebar**
   - Condition: `if (!is_active_sidebar('tmw-model-slot-banner-global')) { return ''; }`
   - Runtime trigger: Sidebar ID `tmw-model-slot-banner-global` has zero active widgets in WP, or is filtered/disabled by theme/plugin filters (none found in this repo).

5. **Widget mode + empty sidebar output**
   - Condition: `if ($output_len === 0) { return ''; }` after `dynamic_sidebar()` output trim.
   - Runtime trigger: Widgets render empty/whitespace-only output (e.g., shortcode widget renders nothing, or widget callback outputs empty string).

## C) Sidebar/widgets filtering audit (MOST LIKELY)

Searched for:
- `sidebars_widgets`
- `widget_display_callback`
- `dynamic_sidebar_params`
- `pre_option_sidebars_widgets` / `option_sidebars_widgets`
- `the_widget`
- `WP_Widget` / block widget filters

### Results

1. **`inc/tmw-admin-tools.php`**
   - Filter: `widget_display_callback` (lines ~377+)
   - Behavior: Only targets `wpst_WP_Widget_Videos_Block` widgets; captures output, rewrites URLs, then returns `false` (prevents double render).
   - Impact on `tmw-model-slot-banner-global`: **None** (only affects `wpst_WP_Widget_Videos_Block`, not slot banner widgets).

2. **`taxonomy-models.php`, `page-videos.php`**
   - Uses `the_widget()` to render `wpst_WP_Widget_Videos_Block` directly in templates.
   - Impact on `tmw-model-slot-banner-global`: **None**; not tied to sidebars or slot banner.

**No filters or actions were found** in this repo that modify `sidebars_widgets`, `option_sidebars_widgets`, `pre_option_sidebars_widgets`, `dynamic_sidebar_params`, or any block-widget-specific sidebars behavior that could hide or empty `tmw-model-slot-banner-global` on model pages.

## D) Shortcode behavior audit (`[tmw_slot_machine]`)

- **Registration search:** This repo does **not** register the shortcode via `add_shortcode('tmw_slot_machine', ...)`.
- **Logging only:** `inc/frontend/shortcodes.php` only logs whether the shortcode exists on `init`, plus pre/post execution output length **if** the shortcode is registered elsewhere.

**Conclusion:** The shortcode handler is **external to this repo** (likely parent theme or plugin). If that external registration is missing or conditional, the shortcode output could be empty in widgets or template contexts, which would produce an empty widget output and trigger the renderer’s empty-output return path.

## E) CSS/JS hiding audit

Search results:
- CSS for `.tmw-slot-banner-zone` and `.tmw-slot-banner` exists in `style.css`.
- No JS references were found targeting `.tmw-slot-banner-zone`, `.tmw-slot-banner`, or `#tmw-slot-banner`.

### CSS findings

`style.css` provides **layout and spacing only**, not hiding:
- `.single-model .tmw-slot-banner-zone { width: 100%; margin: 20px 0 25px; }`
- `.single-model .tmw-slot-banner-zone .tmw-slot-banner { max-width: 1035px; text-align: center; }`

No `display:none`, `visibility:hidden`, zero-height, or off-screen positioning was found for these selectors.

## F) Root cause conclusion (pick ONE primary)

**Primary cause:** The render function returns empty because **the widget sidebar output is empty after `dynamic_sidebar()`**, most plausibly due to the **`[tmw_slot_machine]` shortcode not being registered/returning output** in the widget context (external to this repo). This leads to `output_len === 0` and the renderer returns `''`.

**Secondary suspects (max 2):**
1. Sidebar `tmw-model-slot-banner-global` is not truly active at runtime (e.g., widget assignment mismatch or filtered by an external plugin).
2. Model meta `_tmw_slot_enabled` or `_tmw_slot_mode` is not persisted as expected (mode resolves to `shortcode` with empty shortcode).

**Minimal fix plan (no code):**
1. Verify the shortcode provider plugin/parent theme registers `tmw_slot_machine` on model pages and in widgets; ensure it returns non-empty markup.
2. Confirm the widget is assigned to sidebar ID `tmw-model-slot-banner-global` (exact ID) and outputs content when `dynamic_sidebar()` runs.
3. If shortcode output is empty, coordinate with the shortcode provider to make it return a placeholder or content in widget contexts.

## Verification (docs-only change)

Only documentation was added in `docs/` (no PHP/CSS/JS/templates modified).
