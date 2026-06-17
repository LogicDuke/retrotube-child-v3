# Flipbox Mobile Turning Regression Audit

## Scope

Audit-only review of the RetroTube Child v3 / Flipbox Edition theme code that renders or loads model flipboxes on the homepage, Models page, single model page, single video page, Categories page, and category archives.

## Root cause

The mobile card-turn behavior is split across two pieces:

1. Flipbox markup/CSS rendered by the shared shortcode/renderers.
2. The mobile tap guard JavaScript (`tmw-flip-guard`) that toggles the `.flipped` class on `.tmw-flip` cards for touch-only devices.

The regression is page-template-specific because `tmw-flip-guard` is not enqueued for all pages that render flipboxes.

Working pages load the guard in one of two ways:

- **Models page / model taxonomy contexts:** `inc/enqueue.php` sets `$is_flip_context` only for model taxonomy/archive/page-template contexts, then enqueues `rt-child-flip`, `tmw-flip-guard`, and `tmw-flip-a11y`.
- **Homepage:** the `[tmw_featured_models]` shortcode path explicitly enqueues `tmw-flip-guard` when the featured model block is rendered.

Affected pages render featured model flipboxes through the global featured-models injector/partial, but those page templates are not included in the global `$is_flip_context` conditional. The injected/shortcode path should enqueue the guard, but the global enqueue conditional still documents the underlying mismatch: only model archive/template pages are recognized as flip contexts, while category pages and singular pages can also receive flipboxes.

## Old fix location

The previous mobile fix exists as a small, standalone script:

- `js/tmw-flip-guard.js`
  - Runs only on touch/coarse-pointer devices.
  - Listens for captured document clicks.
  - Finds `.tmw-flip` cards inside `.tmw-grid` or `.tmwfm-grid`.
  - Prevents first-tap navigation and toggles `.flipped`.
  - Allows navigation when the CTA (`.tmw-view`) is tapped after the card is already flipped.

The script is registered globally in:

- `inc/enqueue.php`

It is conditionally enqueued in:

- `inc/enqueue.php` via `$is_flip_context`.
- `inc/tmw-video-hooks.php` inside both `tmw_featured_models_shortcode()` and `tmw_models_flipboxes_cb()`.

Git history confirms the old fix was already present in earlier commits (`fc93a0f`, `b29a581`, `0b67a95`, `d71bbb9`) as `js/tmw-flip-guard.js`, with the same enqueue condition limited to model archive/template contexts. The current implementation in `e819556` changed the guard internals from `pointerdown`/`tmw-flip-armed` to click toggling with `.flipped`, but it did not widen the page-level enqueue context.

## Missing/different code

### Working model archive/page contexts

`inc/enqueue.php` currently recognizes only model taxonomy/archive/template pages as flip contexts:

```php
$is_flip_context = (
  is_tax('models') ||
  is_post_type_archive('models') ||
  is_page_template('page-models-grid.php') ||
  is_page_template('template-models-flipboxes.php')
);

if ($is_flip_context) {
  wp_enqueue_style('rt-child-flip');
  wp_enqueue_script('tmw-flip-guard');
}
```

This explains why the Models page/template path works.

### Affected featured-model injection contexts

`inc/frontend/tmw-featured-models-inject.php` explicitly injects featured models into category and Categories page contexts:

```php
if (is_category() || is_tag()) {
    return true;
}

if (is_page('categories')) {
    return true;
}
```

The same injector defaults to `[tmw_featured_models]`, which renders `.tmwfm-grid` containing `.tmw-flip` cards via `tmw_render_flipbox_card()`.

### Shared markup is consistent

Both featured-model cards and model-grid cards use the same important classes:

```html
<div class="tmw-flip">
  <div class="tmw-flip-inner">
    <div class="tmw-flip-front">...</div>
    <div class="tmw-flip-back">...</div>
  </div>
</div>
```

The guard targets those shared selectors, so the issue is not a new markup system requirement.

### Guard dependency

`assets/flipboxes.css` defines the mobile/JS state through `.tmw-flip.flipped .tmw-flip-inner`, while desktop hover is scoped separately under a fine-pointer hover media query:

```css
.tmw-flip.flipped .tmw-flip-inner { transform: rotateY(180deg); }

@media (hover: hover) and (pointer: fine) {
  .tmw-flip:hover .tmw-flip-inner { transform: rotateY(180deg); }
}
```

Therefore, mobile needs JavaScript to add/remove `.flipped`; CSS hover alone cannot reliably turn cards on touch pages.

## Minimal patch recommendation

Do **not** add a new flipbox system or duplicate event handlers.

Smallest safe fix:

1. Add a helper such as `tmw_page_may_render_flipboxes()` or extend the existing `$is_flip_context` condition in `inc/enqueue.php` to include only pages that can actually render flipboxes outside model archives:
   - `is_front_page()` / `is_home()` only if homepage featured models are not guaranteed by shortcode enqueue timing.
   - `is_singular('model')`.
   - single video/post contexts that receive the featured-model block.
   - `is_page('categories')`.
   - `is_category()` and possibly `is_tag()` because the injector supports both.
2. Enqueue the already-registered `tmw-flip-guard` and `tmw-flip-a11y` scripts/styles in that widened context.
3. Keep `js/tmw-flip-guard.js` unchanged unless testing proves its current click handler is the regression. The old fix already targets `.tmw-flip` in `.tmw-grid, .tmwfm-grid`, which covers the shared markup.

Example direction only, not applied in this audit PR:

```php
$is_flip_context = (
  is_tax('models') ||
  is_post_type_archive('models') ||
  is_page_template('page-models-grid.php') ||
  is_page_template('template-models-flipboxes.php') ||
  is_singular('model') ||
  is_singular('post') ||
  is_page('categories') ||
  is_category() ||
  is_tag()
);
```

If the project has a reliable function that decides whether the featured-model injector will run (`tmw_featured_models_should_inject()`), prefer reusing that helper after it is loaded, plus explicit model archive/grid conditions, so assets are only loaded where flipboxes can appear.

## Risk assessment

- **Desktop behavior:** Low risk if only enqueue scope changes. The guard exits unless `(hover: none) and (pointer: coarse)` matches, and desktop hover remains controlled by CSS.
- **Layout/image ratios:** Low risk. No markup, aspect-ratio, grid, or image CSS changes are needed.
- **Duplicate handlers:** Medium risk if a new script is added. Avoid this by reusing `tmw-flip-guard` only and checking `wp_script_is()`/enqueueing the existing handle.
- **Performance:** Low risk. The guard is small and exits early on non-touch devices, but the enqueue condition should still stay limited to pages that render flipboxes.
- **Template coupling:** Low-to-medium risk if the condition hard-codes too many page types. Prefer centralizing the page decision near existing featured-model injection logic.

## Verification checklist

- [ ] Mobile homepage flipbox still turns.
- [ ] Mobile Models page flipbox still turns.
- [ ] Mobile single Model page flipbox turns.
- [ ] Mobile single Video page flipbox turns.
- [ ] Mobile Categories page flipbox turns.
- [ ] Mobile category archive page flipbox turns.
- [ ] Desktop hover/flip behavior unchanged.
- [ ] No layout shift.
- [ ] No image ratio changes.
- [ ] No console errors.
- [ ] No PHP warnings/fatal errors.
- [ ] No duplicate event handlers.

## Files inspected

- `inc/enqueue.php`
- `js/tmw-flip-guard.js`
- `js/tmw-flip-a11y.js`
- `assets/flipboxes.css`
- `inc/tmw-video-hooks.php`
- `inc/frontend/tmw-featured-models-inject.php`
- `partials/featured-models-block.php`
- `archive-model.php`
- `page-models-grid.php`
- `template-models-flipboxes.php`
- `taxonomy-models.php`
- `page-categories.php`
- `archive.php`
- `template-parts/content-model.php`
- `template-parts/content-video.php`

## Commands used

- `rg -n "flipbox|flip-box|flip card|flip-card|is-flipped|flipped|touch|tap|hover|model-card|video-card|category-card|mobile" -S . --glob '!node_modules' --glob '!vendor'`
- `rg -n "featured_models|tmw_featured|actors_flipboxes|models_flipboxes|data-mobile-guard|tmw_render_flipbox_card|tmw-flip-guard|rt-child-flip" --glob '*.php' .`
- `git log --oneline --all -- js/tmw-flip-guard.js inc/enqueue.php inc/tmw-video-hooks.php inc/frontend/tmw-featured-models-inject.php`
- `git log -S"tmw-flip-guard" --oneline --all -- .`
