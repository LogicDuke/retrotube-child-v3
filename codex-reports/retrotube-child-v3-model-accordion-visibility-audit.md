# TMW Theme Audit — Model Accordion Content Visibility

Tags: `[TMW-SEO-AUDIT]` `[TMW-MODEL-RANKING]` `[TMW-ACCORDION]` `[TMW-CONTENT-VISIBILITY]`

## Summary

This audit reviewed the model page body content accordion only. The singular model template outputs `the_content()` directly into the initial server-rendered HTML when the Retrotube/Xbox `truncate-description` option is enabled. The initial collapsed state does **not** use `display:none`, `visibility:hidden`, `height:0`, or `max-height:0` on the accordion content body.

The collapsed CSS uses one-line visual truncation via `display: -webkit-box`, `-webkit-line-clamp`, `max-height: calc(1.6em * var(--tmw-accordion-lines, 1))`, and `overflow: hidden`. JavaScript is used to set the CSS custom property from `data-tmw-accordion-lines`, determine whether a toggle is needed, and add/remove the `tmw-accordion-collapsed` class on click. The content is present before JavaScript runs, and the first line of body text is intended to be visible on initial load.

**SEO risk classification: Low.** Content is present in initial HTML, one line is visible by default, and no inspected accordion body rule hides the body with `display:none` or `visibility:hidden`. The only caveat is that the configured model body accordion shows only one line initially, so a future PR could consider increasing visible lines if the team wants stronger above-the-fold body-content prominence.

## Files inspected

- `template-parts/content-model.php`
- `template-parts/content-video.php` (parallel singular description accordion pattern)
- `retrotube-template-parts/template-parts/content-video.php` (duplicate/alternate template copy found by search)
- `inc/models/tmw-model-page.php` (model taxonomy/term bio accordion, not the singular `the_content()` path)
- `inc/frontend/shortcodes.php` (shared accordion renderer for category/page descriptions)
- `inc/frontend/tmw-category-accordion-inject.php` (category accordion caller)
- `inc/enqueue.php` (accordion CSS/JS enqueue)
- `css/tmw-accordion.css`
- `style.css`
- `assets/flipboxes.css`
- `js/tmw-accordion.js`
- JS files under `js/` were searched for accordion/toggle/class behavior.
- CSS files under `css/` and `assets/` were searched for accordion and hiding/clipping rules.

## Exact model markup found

In `template-parts/content-model.php`, singular model body output is gated by the Retrotube/Xbox `show-description-video-about` option and the accordion is gated by the `truncate-description` option:

```php
<?php if ( xbox_get_field_value( 'wpst-options', 'show-description-video-about' ) == 'on' ) : ?>
    <?php if ( xbox_get_field_value( 'wpst-options', 'truncate-description' ) == 'on' ) : ?>
        <!-- CUSTOM TMW ACCORDION -->
        <div class="tmw-accordion tmw-accordion--video-desc">
            <div id="tmw-model-desc-<?php echo (int) get_the_ID(); ?>" class="tmw-accordion-content tmw-accordion-collapsed" data-tmw-accordion-lines="1">
                <?php the_content(); ?>
            </div>
            <div class="tmw-accordion-toggle-wrap">
                <button class="tmw-accordion-toggle" type="button" data-tmw-accordion-toggle aria-controls="tmw-model-desc-<?php echo (int) get_the_ID(); ?>" aria-expanded="false" data-readmore-text="<?php echo esc_attr__( 'Read more', 'retrotube-child' ); ?>" data-close-text="<?php echo esc_attr__( 'Close', 'retrotube-child' ); ?>">
                    <span class="tmw-accordion-text"><?php esc_html_e( 'Read more', 'retrotube-child' ); ?></span>
                    <i class="fa fa-chevron-down"></i>
                </button>
            </div>
        </div>
    <?php else : ?>
        <?php the_content(); ?>
    <?php endif; ?>
<?php endif; ?>
```

Confirmed wrapper classes and attributes:

- Outer wrapper: `tmw-accordion tmw-accordion--video-desc`
- Content wrapper: `tmw-accordion-content tmw-accordion-collapsed`
- Content id pattern: `tmw-model-desc-{post_id}`
- Line setting: `data-tmw-accordion-lines="1"`
- Toggle attribute: `data-tmw-accordion-toggle`
- Toggle initial ARIA state: `aria-expanded="false"`

### Is `the_content()` inside the collapsed area?

Yes. When `truncate-description` is `on`, `the_content()` is directly inside:

```php
<div id="tmw-model-desc-<?php echo (int) get_the_ID(); ?>" class="tmw-accordion-content tmw-accordion-collapsed" data-tmw-accordion-lines="1">
    <?php the_content(); ?>
</div>
```

When `truncate-description` is not `on`, the template renders `the_content()` without the accordion wrapper.

## Exact CSS rules found

Primary accordion CSS is enqueued from `css/tmw-accordion.css` and contains the relevant body-content behavior:

```css
.tmw-accordion-content {
    font-size: 14px;
    line-height: 1.6;
    color: #ccc;
    margin: 0;
    padding: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

/* Collapsed state */
.tmw-accordion-content.tmw-accordion-collapsed {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: var(--tmw-accordion-lines, 1);
    max-height: calc(1.6em * var(--tmw-accordion-lines, 1));
}

/* Expanded state */
.tmw-accordion-content:not(.tmw-accordion-collapsed) {
    -webkit-line-clamp: unset;
    max-height: 5000px;
}

.tmw-accordion-content p {
    margin: 0 0 10px;
}

.tmw-accordion-content p:first-child {
    display: inline;
}
```

Additional related CSS found in `css/tmw-accordion.css`:

```css
.tmw-accordion-toggle .tmw-accordion-text::after {
    display: none;
}
```

That `display: none` applies only to the toggle text pseudo-element, not to `.tmw-accordion-content` or the model body content.

```css
.video-description,
.tmw-accordion,
.tmw-accordion--video-desc,
.tab-content,
#video-about,
.width70,
.width100 {
    overflow: visible !important;
    position: relative;
}
```

This later rule makes wrapper containers visible/positioned so the toggle remains clickable. It does not remove the accordion content's own `.tmw-accordion-content { overflow: hidden; }` truncation rule.

### Other hiding/clipping rules found by repo search

The broader search found unrelated hiding/clipping rules in `style.css`, `assets/flipboxes.css`, admin CSS, and other modules. Notable examples:

- `style.css` has banner/frame `overflow: hidden`, unrelated model bio clamp styles, hidden tag clouds, hidden video/date/actor/embed elements, and other layout-specific `display: none` rules.
- `assets/flipboxes.css` has flipbox/card clipping and an embed-container hide rule.
- Admin files have preview/admin-only `display:none` rules.

No searched rule was found that applies `display:none`, `visibility:hidden`, `height:0`, or `max-height:0` to `.tmw-accordion-content.tmw-accordion-collapsed` for the model body content accordion.

## Exact JavaScript behavior found

Accordion JavaScript is enqueued globally from `js/tmw-accordion.js` by `inc/enqueue.php`:

```php
$accordion_style_path = get_stylesheet_directory() . '/css/tmw-accordion.css';
wp_enqueue_style(
  'tmw-accordion',
  get_stylesheet_directory_uri() . '/css/tmw-accordion.css',
  ['retrotube-child-style'],
  $accordion_style_ver
);

$accordion_script_path = get_stylesheet_directory() . '/js/tmw-accordion.js';
wp_enqueue_script(
  'tmw-accordion',
  get_stylesheet_directory_uri() . '/js/tmw-accordion.js',
  [],
  $accordion_script_ver,
  true
);
```

Relevant `js/tmw-accordion.js` behavior:

```js
var accordions = document.querySelectorAll('.tmw-accordion');

accordions.forEach(function(accordion) {
    var toggle = accordion.querySelector('.tmw-accordion-toggle');
    var content = accordion.querySelector('.tmw-accordion-content');
    if (!toggle || !content) return;

    var lines = parseInt(content.getAttribute('data-tmw-accordion-lines'), 10);
    if (!lines || lines < 1) {
        lines = CONFIG.defaultLines;
    }
    content.style.setProperty('--tmw-accordion-lines', lines);

    var lineHeight = parseFloat(window.getComputedStyle(content).lineHeight);
    if (!lineHeight) {
        lineHeight = CONFIG.fallbackLineHeight;
    }

    var maxHeight = lineHeight * lines;
    var needsToggle = content.scrollHeight > maxHeight + 1;

    if (!needsToggle) {
        content.classList.remove('tmw-accordion-collapsed');
        setToggleState(toggle, textSpan, icon, true, readMoreText, closeText);
        if (toggleWrap) {
            toggleWrap.setAttribute('hidden', 'hidden');
        }
        return;
    }

    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var isCollapsed = content.classList.contains('tmw-accordion-collapsed');

        if (isCollapsed) {
            content.classList.remove('tmw-accordion-collapsed');
            setToggleState(toggle, textSpan, icon, true, readMoreText, closeText);
        } else {
            content.classList.add('tmw-accordion-collapsed');
            setToggleState(toggle, textSpan, icon, false, readMoreText, closeText);
            scrollToElement(accordion);
        }

        toggle.blur();
    });
});
```

The script:

- Initializes on DOM ready and again on `load`.
- Selects existing `.tmw-accordion` markup from the initial DOM.
- Reads `data-tmw-accordion-lines` and sets the CSS variable `--tmw-accordion-lines`.
- Checks whether the content exceeds the configured visible height.
- Removes `tmw-accordion-collapsed` and hides the toggle only when no toggle is needed.
- On click, toggles `tmw-accordion-collapsed` on the existing content element.
- Does not fetch, inject, lazy-load, or replace `the_content()`.

## Is content present in initial HTML?

Yes. For the audited singular model template path, `the_content()` is server-rendered directly inside the `.tmw-accordion-content.tmw-accordion-collapsed` element. JavaScript does not load the content; it only controls the visual collapsed/expanded state.

## Is the first paragraph/body text visible on initial page load?

Yes, based on the repo CSS. The initial collapsed state uses one visible line:

- `data-tmw-accordion-lines="1"` in `template-parts/content-model.php`
- `-webkit-line-clamp: var(--tmw-accordion-lines, 1)` in `css/tmw-accordion.css`
- `max-height: calc(1.6em * var(--tmw-accordion-lines, 1))` in `css/tmw-accordion.css`

The content is clipped after one line, but it is not hidden with `display:none`, `visibility:hidden`, `height:0`, or `max-height:0`. No-JS users/bots should still receive the full content in HTML and see at least the first line visually because the CSS variable fallback is `1` even before JS sets `--tmw-accordion-lines`.

## SEO risk classification

**Low risk.**

Rationale:

- Content is present in initial HTML.
- The first body line is visible by default.
- Collapsed state uses normal visual truncation with line clamp and non-zero max height.
- No inspected CSS rule hides `.tmw-accordion-content` with `display:none` or `visibility:hidden`.
- JavaScript is not required for Google/bots to receive the body content in the HTML.
- JavaScript only changes class/state on the existing DOM element.

Caveat:

- Because the singular model template configures only one initial line, most of a longer model body is visually clipped until interaction. This is not a high-risk hidden-content pattern, but it is a potential content-weight prominence concern if the strategy is to show more body context without interaction.

## Screenshots or source snippets

No browser screenshot was taken because this audit is source-only and documentation-only. Source snippets are included above from the repository context.

## Recommendation

**Small future PR optional.**

No urgent fix is needed for hidden-content/indexability risk because the model body content is present in initial HTML and the first line is visible. If the team wants to reduce the remaining MEDIUM audit concern around content prominence, a narrow future PR could consider increasing `data-tmw-accordion-lines` for singular model body content from `1` to `2` or `3`, or otherwise showing a more meaningful excerpt before the toggle. That future PR should be separate because this PR is audit-only.

## Verification commands and results

### Command 1

```bash
rg -n "tmw-accordion|accordion-collapsed|data-tmw-accordion-lines|truncate-description|display:\s*none|visibility:\s*hidden|height:\s*0|max-height:\s*0|overflow:\s*hidden|line-clamp|-webkit-line-clamp" .
```

Result: passed. The search found the singular model accordion in `template-parts/content-model.php`, the primary rules in `css/tmw-accordion.css`, the global enqueue in `inc/enqueue.php`, related shared accordion renderers in `inc/frontend/shortcodes.php`, model bio accordion markup in `inc/models/tmw-model-page.php`, and unrelated hiding/clipping rules elsewhere. No result showed `display:none`, `visibility:hidden`, `height:0`, or `max-height:0` on the model body `.tmw-accordion-content.tmw-accordion-collapsed` element.

### Command 2

```bash
rg -n "the_content\(|tmw-accordion-content|tmw-accordion-collapsed" template-parts inc assets .
```

Result: passed. The search confirmed `the_content()` is inside `template-parts/content-model.php` line-level accordion markup when truncation is on and outside the accordion when truncation is off. It also found parallel video and shared renderer accordion patterns.

## Functional-change confirmation

This PR made documentation-only changes by adding this audit report. It did not modify frontend layout, CSS, PHP behavior, JavaScript behavior, SEO metadata, indexing settings, Rank Math settings, robots, noindex, canonical, sitemap, IndexNow, or generated model content.
