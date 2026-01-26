This document is audit-only. No markup or styling changes are included.

# RetroTube Child v3 — Audit Unnamed Links (Top Bar Social + “More Videos”)

## Scope
PageSpeed flags unnamed links for:
- Top bar social-share anchors (Facebook / Instagram / Reddit / Twitter).
- Sidebar video widgets “More videos” link for `filter=latest` and `filter=random`.

## Findings

### Top bar social-share links
The child theme does not define the top bar template. It delegates to a template part that is expected to exist in the parent theme:

```php
<?php get_template_part( 'template-parts/content', 'top-bar' ); ?>
```

The above call in `header.php` loads `template-parts/content-top-bar.php` from the parent theme (since the child theme does not include that file). This is the template that must contain the social-share `<a>` tags referenced in the PageSpeed report. The child theme itself does not output the social links, so there is no local markup to confirm whether the anchors have visible text, an `aria-label`, or screen-reader-only text. Review the parent theme’s `template-parts/content-top-bar.php` for the exact anchors and any accessible label text.

### Sidebar video widget “More videos” links
The “More videos” anchor is rendered by the parent theme’s `wpst_WP_Widget_Videos_Block` widget. The child theme only wraps the widget output to normalize `filter` URLs:

```php
class TMW_WP_Widget_Videos_Block_Fixed extends wpst_WP_Widget_Videos_Block {
    public function widget($args, $instance) {
        ob_start();
        parent::widget($args, $instance);
        $html = ob_get_clean();
        echo tmw_rewrite_video_filter_hrefs_to_videos_page($html);
    }
}
```

The widget markup (including the `a.more-videos.label` link) comes from the parent class. Because the child theme does not contain the widget template, it cannot confirm whether the “More videos” anchor includes visible text, an `aria-label`, or screen-reader-only text, nor whether the label is provided via an icon or CSS pseudo-element. Inspect the parent theme’s `wpst_WP_Widget_Videos_Block::widget` output for the exact anchor markup.
