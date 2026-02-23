<?php
if (!defined('ABSPATH')) { exit; }

/** Lightweight autoload for namespaced classes (optional future use) */
spl_autoload_register(function($class){
    $pfx = 'TMW\\Child\\';
    if (strpos($class, $pfx) !== 0) return;
    $rel = str_replace('\\\\', '/', substr($class, strlen($pfx)));
    $file = __DIR__ . '/classes/' . $rel . '.php';
    if (is_readable($file)) require $file;
});

/** Constants shared across modules */
require_once __DIR__ . '/constants.php';

// WP-CLI only: hybrid model scan commands/helpers.
if (defined('WP_CLI') && WP_CLI) {
    $hybrid_scan = TMW_CHILD_PATH . '/assets/php/tmw-hybrid-model-scan.php';
    if (is_readable($hybrid_scan)) {
        require_once $hybrid_scan;
    }
}

/** Setup & assets */
require_once __DIR__ . '/setup.php';
require_once __DIR__ . '/enqueue.php';
require_once __DIR__ . '/frontend/performance.php';
require_once __DIR__ . '/frontend/banner-performance.php';
require_once __DIR__ . '/frontend/perf-buffer-rewrite.php';

/** Category Pages CPT system */
require_once __DIR__ . '/tmw-category-pages.php';

/** Front-end features */
require_once __DIR__ . '/frontend/accessibility.php';
require_once __DIR__ . '/frontend/model-banner.php';
require_once __DIR__ . '/frontend/flipboxes.php';
require_once __DIR__ . '/frontend/comments.php';
require_once __DIR__ . '/frontend/taxonomies.php';
require_once __DIR__ . '/frontend/shortcodes.php';
require_once __DIR__ . '/frontend/template-tags.php';
require_once __DIR__ . '/frontend/model-stats.php';
require_once __DIR__ . '/frontend/tmw-slot-banner.php';
require_once __DIR__ . '/frontend/tmw-video-widget-links-fix.php';
require_once __DIR__ . '/frontend/tmw-category-hub-mirror-tag.php';
require_once __DIR__ . '/frontend/tmw-category-mirror-tag-inventory.php';
require_once __DIR__ . '/frontend/tmw-hub-tag-redirect.php';
require_once __DIR__ . '/frontend/tmw-hub-tag-link-rewrite.php';
require_once __DIR__ . '/blocks/tmw-home-accordion-block.php';
require_once __DIR__ . '/blocks/home-accordion-frame/index.php';
$tmw_injector = __DIR__ . '/frontend/tmw-featured-models-inject.php';
if (file_exists($tmw_injector)) {
    require_once $tmw_injector;
}
require_once __DIR__ . '/admin/tmw-slot-banner-meta.php';

/** SEO helpers */
require_once __DIR__ . '/seo/schema.php';

/** Admin-only */
if (is_admin()) {
    require_once __DIR__ . '/admin/metabox-model-banner.php';
    require_once __DIR__ . '/admin/tmw-slot-banner-metabox.php';
    require_once __DIR__ . '/admin/editor-tweaks.php';
}
