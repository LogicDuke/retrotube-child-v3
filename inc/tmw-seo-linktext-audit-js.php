<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) { exit; }

add_action('wp_enqueue_scripts', function () {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return;
    }

    if (!isset($_GET['tmw_seo']) || $_GET['tmw_seo'] !== '1') {
        return;
    }

    $handle = 'tmw-seo-linktext-audit';
    wp_register_script($handle, '', [], null, true);
    wp_enqueue_script($handle);

    $nonce = wp_create_nonce('tmw_seo_linktext_audit_log');
    $ajax_url = admin_url('admin-ajax.php');
    $ajax_url_json = wp_json_encode($ajax_url);
    $nonce_json = wp_json_encode($nonce);

    $inline = <<<JS
(() => {
    const ajaxUrl = {$ajax_url_json};
    const nonce = {$nonce_json};
    const tag = '[TMW-SEO-LINKTEXT]';
    const selector = 'a[href*="technologies/cookies"]';
    const seen = new Set();
    let foundCount = 0;

    const buildPath = (node) => {
        const parts = [];
        let current = node;
        while (current && parts.length < 4 && current.nodeType === 1) {
            const tagName = current.tagName.toLowerCase();
            const id = current.id ? `#${current.id}` : '';
            const classes = current.classList && current.classList.length
                ? `.${Array.from(current.classList).slice(0, 2).join('.')}`
                : '';
            parts.unshift(`${tagName}${id}${classes}`);
            current = current.parentElement;
        }
        return parts.join(' > ');
    };

    const postLog = (payload) => {
        const body = new URLSearchParams();
        body.set('action', 'tmw_seo_linktext_audit_log');
        body.set('nonce', nonce);
        Object.entries(payload).forEach(([key, value]) => body.set(key, value));
        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
        }).catch(() => {});
    };

    const scan = () => {
        document.querySelectorAll(selector).forEach((link) => {
            const href = link.getAttribute('href') || '';
            const text = (link.textContent || '').trim();
            const path = buildPath(link);
            const key = `${href}||${text}||${path}`;
            if (seen.has(key)) {
                return;
            }
            seen.add(key);
            foundCount += 1;
            postLog({
                message: 'FOUND',
                href,
                text,
                path,
            });
        });
    };

    scan();
    const observer = new MutationObserver(() => scan());
    observer.observe(document.documentElement, { childList: true, subtree: true });

    setTimeout(() => {
        if (foundCount === 0) {
            postLog({ message: '0 matches after 5s' });
        }
    }, 5000);
})();
JS;

    wp_add_inline_script($handle, $inline, 'after');
});

add_action('wp_ajax_tmw_seo_linktext_audit_log', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('forbidden', 403);
    }

    check_ajax_referer('tmw_seo_linktext_audit_log', 'nonce');

    $message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
    if ($message === '') {
        wp_send_json_error('missing', 400);
    }

    $prefix = '[TMW-SEO-LINKTEXT]';
    if ($message === 'FOUND') {
        $href = isset($_POST['href']) ? esc_url_raw(wp_unslash($_POST['href'])) : '';
        $text = isset($_POST['text']) ? sanitize_text_field(wp_unslash($_POST['text'])) : '';
        $path = isset($_POST['path']) ? sanitize_text_field(wp_unslash($_POST['path'])) : '';
        error_log(sprintf('%s FOUND href=%s text=%s path=%s', $prefix, $href, $text, $path));
    } else {
        error_log(sprintf('%s %s', $prefix, $message));
    }

    wp_send_json_success();
});
