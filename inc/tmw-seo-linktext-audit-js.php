<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return;
    }

    $get_flag = isset($_GET['tmw_seo']) ? sanitize_text_field(wp_unslash($_GET['tmw_seo'])) : '';
    $cookie_flag = isset($_COOKIE['tmw_seo']) ? sanitize_text_field(wp_unslash($_COOKIE['tmw_seo'])) : '';
    $enabled = ($get_flag === '1' || $cookie_flag === '1');
    if (!$enabled) {
        return;
    }

    if ($get_flag === '1') {
        setcookie(
            'tmw_seo',
            '1',
            time() + 1800,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
        $_COOKIE['tmw_seo'] = '1';
        $cookie_flag = '1';
    }

    nocache_headers();
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    if (!defined('DONOTCACHEOBJECT')) {
        define('DONOTCACHEOBJECT', true);
    }
    if (!defined('DONOTCACHEDB')) {
        define('DONOTCACHEDB', true);
    }

    $prefix = '[TMW-SEO-LINKTEXT]';
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $current_user = wp_get_current_user();
    $user_label = $current_user && $current_user->exists() ? $current_user->user_login : 'unknown';
    error_log(sprintf(
        '%s enabled=1 uri=%s get=%s cookie=%s user=%s',
        $prefix,
        $request_uri,
        $get_flag,
        $cookie_flag,
        $user_label
    ));
    error_log(sprintf('%s file_loaded=1', $prefix));

    if (wp_doing_ajax() || is_feed()) {
        return;
    }

    $handle = 'tmw-seo-linktext-audit';
    wp_register_script($handle, '', [], null, true);
    wp_enqueue_script($handle);

    $ajax_url_json = wp_json_encode(admin_url('admin-ajax.php'));
    $nonce_json = wp_json_encode(wp_create_nonce('tmw_seo_linktext_audit'));

    $js = <<<'JS'
(() => {
  const ajaxUrl = __AJAX_URL__;
  const nonce = __NONCE__;
  const selector = 'a[href*="technologies/cookies"]';
  const seen = new Set();

  console.log('[TMW-SEO-LINKTEXT] audit js active');

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
      }).catch(() => {
        if (navigator.sendBeacon) {
          const beaconBody = new URLSearchParams(body);
          navigator.sendBeacon(ajaxUrl, beaconBody);
          return;
        }
        const params = new URLSearchParams(body);
        const img = new Image();
        img.src = `${ajaxUrl}?${params.toString()}`;
      });
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
    postLog({ reason: 'boot', href: location.href, text: 'boot', path: 'boot' });
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

    wp_add_inline_script($handle, $js, 'after');
});

add_action('wp_ajax_tmw_seo_linktext_audit_log', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('forbidden', 403);
    }

    check_ajax_referer('tmw_seo_linktext_audit', 'nonce');

    $reason = isset($_REQUEST['reason']) ? sanitize_text_field(wp_unslash($_REQUEST['reason'])) : '';
    $href = isset($_REQUEST['href']) ? esc_url_raw(wp_unslash($_REQUEST['href'])) : '';
    $text = isset($_REQUEST['text']) ? sanitize_text_field(wp_unslash($_REQUEST['text'])) : '';
    $path = isset($_REQUEST['path']) ? sanitize_text_field(wp_unslash($_REQUEST['path'])) : '';

    $prefix = '[TMW-SEO-LINKTEXT]';
    error_log(sprintf(
        '%s ajax_hit reason=%s href=%s text=%s path=%s',
        $prefix,
        $reason,
        $href,
        $text,
        $path
    ));

    wp_send_json_success(['ok' => true]);
});
