# [TMW-FRONTEND] Audit — Category Page Templates and Frontend SEO Structure

## Scope and Guardrails
- Audit-only pass of RetroTube Child v3 frontend templates and rendering flow.
- No template/CSS/layout behavior changes were made.
- Plugin logic (TMW SEO Engine / Rank Math behavior) is documented only where it affects frontend output ownership.

## 1) File Map: Category/Archive/Breadcrumb/Grid/Sidebar Surfaces

### Core archive templates
- `archive.php` (generic archive shell, title/description loop/navigation).
- `tag.php` (tag archive shell, title/description loop/navigation).
- `archive-model.php` (Models CPT archive page).
- `taxonomy-models.php` (models taxonomy archive override).

### Page templates that function like archive hubs
- `page-categories.php` (Categories hub page content + intro accordion behavior).
- `page-videos.php` (Videos listing/filter hub with widget grids).
- `page-models-grid.php` (models flipbox grid page).

### Template parts / frontend partials
- `template-parts/breadcrumbs.php` (Rank Math + model breadcrumb fallback renderer).
- `template-parts/content-video.php` (single video body/related/share sections).
- `template-parts/content-model.php` (single model body/related/share + model videos block).
- `partials/featured-models-block.php` (injected featured models block).

### Rendering infrastructure impacting archives
- `header.php` (`do_action('tmw_render_breadcrumbs')` breadcrumb output location).
- `inc/breadcrumbs.php` (legacy/custom breadcrumb trail logic).
- `functions.php` (breadcrumb action wiring, Rank Math/bootstrap includes).
- `inc/frontend/tmw-featured-models-inject.php` (global featured-model block insertion on category/tag/categories page).
- `inc/frontend/tmw-category-hub-mirror-tag.php` (category query broadening via mirrored tag).

### SEO/meta/schema/canonical ownership layers
- `inc/tmw-filter-canonical.php` (canonical authority layer).
- `inc/tmw-archive-schema.php` and `inc/seo/schema.php` (JSON-LD emitters/filtering).
- `inc/tmw-rankmath-category-pages.php` + `inc/tmw-category-pages.php` (Rank Math/category-page CPT integration).

### Model taxonomy/post type mapping
- `inc/models/tmw-model-register.php` (model CPT + models taxonomy registration).
- `inc/models/tmw-model-page.php` (template_redirect custom render for `is_tax('models')`).

## 2) Frontend URL → Template Mapping (Current Behavior)

## 3) Category Page SEO Structure Audit

### Category archives (`/category/{slug}/`)
- **Template used:** `archive.php` in child theme (unless parent fallback intercepts; current file is active shell).
- **H1 source:** `get_the_archive_title()` via `tmw_render_title_bar(..., 1)`.
- **Breadcrumb source:** Header action `tmw_render_breadcrumbs` → `wpst_breadcrumbs()`; plus Rank Math breadcrumb template availability.
- **Grid/card source:** `get_template_part('template-parts/content', get_post_type())` per loop item (post-type-specific cards from theme/parent).
- **Pagination:** `the_posts_navigation()` (prev/next style, no numeric pager in this file).
- **Sidebar behavior:** Through `tmw_render_sidebar_layout('generic-archive', ...)` wrapper.
- **Intro/description text:** `the_archive_description()` rendered directly under title.
- **Term description shown?:** Yes, if category description exists.
- **Custom meta/fields rendered?:** Not directly in `archive.php`; can be indirectly through content templates/widgets/filters.

### Tag archives (`/tag/{slug}/`)
- Same structural behavior as category via `tag.php` with title from `single_term_title()` fallback to archive title, description output, loop cards, and `the_posts_navigation()`.

### Models CPT archive (`/models/`)
- **Template used:** `archive-model.php`.
- **Post type:** `model` CPT (`has_archive => 'models'`).
- **H1 source:** `post_type_archive_title('', false)` inside explicit `<h1>`.
- **Breadcrumb source:** Header global breadcrumb hook (not in local body template).
- **Grid/card source:** `[actors_flipboxes ... show_pagination="true"]` shortcode output.
- **Pagination:** shortcode-managed (not WordPress loop pager in template).
- **Sidebar:** explicit `<aside class="tmw-sidebar"><?php get_sidebar(); ?>`.
- **Intro text:** comes from `models` page post content, sanitized, rendered above grid in accordion.
- **Term description/meta:** N/A (CPT archive, not term archive), though page-content-driven SEO text is present.

### Models taxonomy archive (`/models/{term}/` for taxonomy `models`)
- **Registration:** taxonomy slug `models` bound to `model` and additionally bridged to other types in registration/helpers.
- **Template behavior:** `inc/models/tmw-model-page.php` hooks `template_redirect` and fully renders custom layout for `is_tax('models')`, effectively superseding `taxonomy-models.php` runtime in many flows.
- **H1 source:** taxonomy term name in custom renderer (`<h1 class="tmw-model-title">`).
- **H2 sections:** optional widget headings/sections in composed blocks.
- **Grid/card source:** featured models shortcode block in custom model page template; plus model/video related blocks depending on context.
- **Pagination:** not explicit WP numeric pager in `tmw-model-page.php`; dependent on embedded shortcodes/widgets.
- **Sidebar:** model sidebar dynamic area with fallback `get_sidebar()`.
- **Intro/description:** biography text from ACF-like field (`bio`) shown in accordion (not native `term_description()`).

### Videos hub (`/videos/` and `/videos/?filter=...`)
- **Template:** `page-videos.php` (page template).
- **H1:** static “Videos”.
- **Grid/card:** widget-based video blocks (`the_widget` with `widget_videos_block`).
- **Pagination:** widget-controlled; no `paginate_links` call in template.
- **Intro text:** content split around `<!--more-->`; intro above listings (accordionized) + rest content below intro.

### Categories hub page (`/categories/`)
- **Template:** `page-categories.php`.
- **H1:** page title through `tmw_render_title_bar(..., 1)`.
- **Intro text:** excerpt or pre-`<!--more-->` chunk in accordion.
- **Below-intro content:** remaining content or full `the_content()`.
- **Sidebar:** through `tmw_render_sidebar_layout` wrapper.

## 4) Findings: SEO/Frontend Risks and Gaps

### Positive signals
- Category/tag archives already render archive descriptions (term-description capable) above loop grids.
- Archive title wrappers are explicitly rendered as H1 in archive shells.
- Category/tag pages have server-rendered loop items (not JS-only), plus server-rendered prev/next pagination links.
- Breadcrumb container is server-rendered in header and can be Rank Math-driven.

### Potential problems
- **Potential duplicate H1 risk** where parent/content templates could emit another top-level heading inside cards or widget wrappers (needs staging HTML snapshot validation).
- **Pagination quality risk** on category/tag archives: `the_posts_navigation()` is crawlable but less index-friendly than numeric pagination for deep archives.
- **Thin-page risk** for categories with little/no term description and sparse item counts.
- **Inconsistent taxonomy template path**: `taxonomy-models.php` exists, but `template_redirect` in `inc/models/tmw-model-page.php` can bypass normal taxonomy template hierarchy.
- **Schema ownership overlap risk**: theme contains multiple schema emitters and Rank Math filters; plugin injection must avoid duplicate CollectionPage/ItemList graphs.
- **Canonical ownership overlap risk**: `inc/tmw-filter-canonical.php` and Rank Math filters already govern canonical URLs; plugin must integrate, not override blindly.
- **Featured-model injection side effects**: global output buffering insertion on category/tag/categories pages can affect predictable insertion zones if plugin adds new above/below-grid blocks.

## 5) Safe Insertion Points (Future Plugin Injection Targets)

> Audit-only recommendation; no implementation performed.

### A) Category intro text (safest)
1. **Primary:** existing archive description slot in `archive.php` directly below title (`the_archive_description(...)`).
2. **Alternative:** prepend in archive wrapper callback before loop start via existing theme wrapper pattern.

### B) Category FAQ / related links below grid
1. **After loop, before pagination** in `archive.php` callback.
2. **After pagination** as final content block in archive wrapper.

### C) Related model/video/photo link clusters
1. Inject as server-rendered block between loop and navigation in `archive.php`/`tag.php`.
2. Reuse existing card/list styling containers from widget/section patterns used by `page-videos.php` for layout safety.

### D) Breadcrumb improvements
1. Use `template-parts/breadcrumbs.php` and existing action hook `tmw_render_breadcrumbs` in `header.php`.
2. Keep markup class names and container IDs intact to preserve CSS/JS expectations.

### E) Model taxonomy intro enhancements
1. In `inc/models/tmw-model-page.php`, append additional safe sections below bio accordion and above featured block.
2. Avoid altering banner/title/sidebar scaffolding.

## 6) Theme vs Plugin Responsibility Split (Recommended)

### Keep in Theme
- Layout containers (`tmw-layout`, sidebar/main split, card grid shell).
- Archive/page visual wrappers, heading chrome, accordion presentation.
- Existing widget/shortcode placement and responsive styling.

### Move/Keep in Plugin (TMW SEO Engine)
- Category intelligence and relationship resolution (model/video/photo/category graphing).
- Category intro/FAQ/related-link content generation.
- Canonical/meta decisions and Rank Math interfacing.
- Structured data ownership and deduplication policy.
- Crawl/index strategy logic (pagination directives, template context decisions).

## 7) First Safe Frontend PR Recommendation (Future)
1. Introduce **one non-invasive output hook point** in `archive.php`:
   - `do_action('tmw_category_archive_after_description', $term)` directly after `the_archive_description`.
2. Introduce **one non-invasive output hook point** before navigation:
   - `do_action('tmw_category_archive_before_pagination', $term)`.
3. Keep default behavior unchanged when no plugin callback is attached.

## 8) Verification Checklist for Next Implementation PR
- [ ] Confirm one visible H1 per category archive URL in rendered HTML.
- [ ] Confirm intro text renders above grid and does not break card flow on mobile/desktop.
- [ ] Confirm injected FAQ/related blocks are server-rendered and crawlable.
- [ ] Confirm pagination links remain present and crawlable on paged archives (`/page/2/`).
- [ ] Confirm no duplicate canonical tags after plugin integration.
- [ ] Confirm no duplicate JSON-LD ItemList/CollectionPage nodes.
- [ ] Confirm breadcrumb path remains stable for category/tag/model/video contexts.
- [ ] Confirm featured-model injection still lands in expected location.
- [ ] Confirm no layout shifts or CSS regressions in archive/sidebar areas.

