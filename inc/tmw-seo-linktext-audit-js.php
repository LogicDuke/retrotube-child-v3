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

    if (wp_doing_ajax() || is_feed()) {
        return;
    }

    wp_register_script('tmw-seo-linktext-audit', '', [], null, true);
    wp_enqueue_script('tmw-seo-linktext-audit');

    $ajax_url_json = wp_json_encode(admin_url('admin-ajax.php'));
    $nonce_json = wp_json_encode(wp_create_nonce('tmw_seo_linktext_audit'));

    $js = <<<'JS'
(() => {
  const ajaxUrl = __AJAX_URL__;
  const nonce = __NONCE__;
  const selector = 'a[href*="technologies/cookies"]';
  const seen = new Set();
  const startedAt = Date.now();

  const buildPath = (node) => {
    const parts = [];
    let current = node;
    while (current && parts.length < 4 && current.nodeType === 1) {
      const tag = (current.tagName || '').toLowerCase();
      const id = current.id ? `#${current.id}` : '';
      const classes = current.classList && current.classList.length
        ? `.${Array.from(current.classList).slice(0, 2).join('.')}`
        : '';
      parts.unshift(`${tag}${id}${classes}`);
      current = current.parentElement;
    }
    return parts.join(' > ');
  };

  const postLog = (payload) => {
    try {
      const body = new URLSearchParams();
      body.set('action', 'tmw_seo_linktext_audit_log');
      body.set('nonce', nonce);
      Object.entries(payload).forEach(([k, v]) => body.set(k, String(v)));
      fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body
      }).catch(() => {});
    } catch (_) {}
  };

  const scan = (reason) => {
    const nodes = document.querySelectorAll(selector);
    nodes.forEach((a) => {
      const href = (a.getAttribute('href') || '').trim();
      const text = (a.textContent || '').trim();
      const key = `${href}||${text}`;
      if (!href || seen.has(key)) return;
      seen.add(key);
      postLog({
        reason,
        href,
        text: text || '(empty)',
        path: buildPath(a)
      });
    });
  };

  const finalizeIfNone = () => {
    if (seen.size === 0) {
      postLog({ reason: 'final', href: '(none)', text: '0 matches after 5s', path: location.pathname });
    }
  };

  const start = () => {
    scan('domready');
    const obs = new MutationObserver(() => {
      scan('mutation');
      if (seen.size > 0) obs.disconnect();
    });
    obs.observe(document.documentElement || document.body, { childList: true, subtree: true });
    setTimeout(() => { scan('timeout'); finalizeIfNone(); }, 5000);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();
JS;

    $js = str_replace(
        ['__AJAX_URL__', '__NONCE__'],
        [$ajax_url_json, $nonce_json],
        $js
    );

    wp_add_inline_script('tmw-seo-linktext-audit', $js, 'after');
});

add_action('wp_ajax_tmw_seo_linktext_audit_log', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('forbidden', 403);
    }

    check_ajax_referer('tmw_seo_linktext_audit', 'nonce');

    $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';
    if ($reason === '') {
        wp_send_json_error('missing', 400);
    }

    $prefix = '[TMW-SEO-LINKTEXT]';
    $href = isset($_POST['href']) ? esc_url_raw(wp_unslash($_POST['href'])) : '';
    $text = isset($_POST['text']) ? sanitize_text_field(wp_unslash($_POST['text'])) : '';
    $path = isset($_POST['path']) ? sanitize_text_field(wp_unslash($_POST['path'])) : '';
    error_log(sprintf('%s reason=%s href=%s text=%s path=%s', $prefix, $reason, $href, $text, $path));

    wp_send_json_success();
});
