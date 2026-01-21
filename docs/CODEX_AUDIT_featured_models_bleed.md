# Audit — Featured Models full-width bleed after category fix

## Scope
Audit-only summary for the Featured Models global injection introduced for category archives, documenting why the block now bleeds full width on non-category pages and how to scope the fix back to category-only output without altering existing layouts elsewhere.

## Root cause (summary)
The Featured Models block is injected via a global output buffer and is currently inserted **after** the first `</main>` tag in the buffered output. In templates where `</main>` closes the main content wrapper but the sidebar/content column has already ended (or `</main>` is the last structural tag before the sidebar wrapper), inserting **after** `</main>` places the block outside the left column container, which causes full-width bleed into the sidebar region. The category-only change made the injection global, so the regression appears on single posts, pages, and author archives as well.

## Render path confirmation (global buffer)
* Output buffer starts at `template_redirect` and injects during `get_footer` for all front-end requests that pass `tmw_should_output_featured_block()`. This is the same path across category archives, pages, singles, and author templates.
* Injection token: `</main>` is replaced with `</main>` + `$markup` (i.e., markup is inserted **after** `</main>`). If the token is not found, the markup is appended to the buffer.

## Template structure & container boundaries (child templates)
### Main content wrapper boundaries
All child theme templates that render the two-column layout (and the `tmw_render_sidebar_layout()` helper) wrap the left column as:
```
<div id="content" class="site-content row">
  <div id="primary" class="content-area with-sidebar-right ...">
    <main id="main" class="site-main with-sidebar-right" role="main">
      ...content...
    </main>
  </div>
  <aside id="sidebar" ...>
```
* In the child fallback templates (`single.php`, `page.php`, and `tmw_render_sidebar_layout()` used by author/category/tag/archive/search), `</main>` closes the main content but is still **inside** `#primary`.
* Because the injection currently inserts **after** `</main>`, the Featured Models block sits between `</main>` and `</div><!-- #primary -->` in the child fallback templates. This is still inside the left column container, which would normally keep it constrained.

### Parent theme templates (unknown in repo)
The parent theme templates are not present in this repository. However, the global buffer injection uses the parent template output whenever `tmw_try_parent_template()` succeeds (e.g., in `author.php`, `category.php`, and `archive.php`). If the parent template closes `#primary` (or the content column wrapper) **before** `</main>`, or if `</main>` is the final structural tag before the sidebar/layout wrapper closes, then inserting **after** `</main>` places the block **outside** the left column. This would explain the full-width bleed seen on non-category pages.

## Exact insertion point tokens discovered in child templates
* `</main>` — present in:
  * `tmw_render_sidebar_layout()` output (author/category/tag/archive/search fallbacks).
  * `page.php` and `single.php` fallback templates.
* If `</main>` is not present in the buffered HTML, the block is appended to the end of the buffer (post-footer).

## Category-only solution options
### Option A (preferred)
Scope the buffer-based injection to category archives **only**, and restore the previous insertion behavior (inside the main content column) for all non-category templates.
* Keep global buffer injection **only** for `is_category()` (optionally `is_tax('category')` if custom taxonomies are involved).
* For all other templates: revert to the old insertion point (before `</main>` or a container close like `</div><!-- #primary -->`) or do not inject at all.

### Option B
Stop using global buffering for categories and instead insert Featured Models directly in `category.php` after the loop, but still inside the same `tmw_render_sidebar_layout()` callback and before the `</main>` close. This would leave non-category pages untouched and avoid token-based buffer insertion for categories altogether.

## Why inserting after `</main>` causes full-width bleed (non-category pages)
When the token replacement inserts after `</main>`, the Featured Models markup is outside the `<main>` element. If the parent theme closes the left column container at or before `</main>`, the injected markup is no longer inside the left column wrapper and thus spans the full-width layout container, visibly overlapping the sidebar region.

## Exclusion policy pages (unchanged)
`tmw_should_output_featured_block()` already excludes the four policy pages by slug and does not rely on the buffer token. This is still intact and should remain unchanged during the fix.

## Fix plan (next PR)
1. **Confirm the parent template boundaries** on the affected templates (single, page, author, and generic archive) to find the correct insertion boundary inside the left column. The primary target should be **before** `</main>` (preferred) or before `</div><!-- #primary -->` if `</main>` cannot be reliably targeted.
2. **Scope Featured Models injection to category archives only**, keeping the previous layout behavior for non-category templates.
3. **Retain policy-page exclusions** as-is to avoid regressions.
