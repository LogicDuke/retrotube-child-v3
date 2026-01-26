<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_footer', function () {
    if (wp_doing_ajax() || is_feed()) {
        return;
    }
    ?>
    <script>
    (() => {
      const selector = 'a[href*="technologies/cookies"]';
      const desiredLabel = 'Cookie information';
      const needsRel = (node) => node && node.getAttribute && node.getAttribute('target') === '_blank';

      const ensureRel = (node) => {
        if (!needsRel(node)) {
          return;
        }
        const current = (node.getAttribute('rel') || '').trim();
        const parts = current ? current.split(/\s+/) : [];
        if (!parts.includes('noopener')) {
          parts.push('noopener');
        }
        if (!parts.includes('noreferrer')) {
          parts.push('noreferrer');
        }
        node.setAttribute('rel', parts.join(' ').trim());
      };

      const updateLink = (node) => {
        if (!node) {
          return false;
        }
        const text = (node.textContent || '').trim();
        if (text === 'More information') {
          node.textContent = desiredLabel;
        }
        node.setAttribute('aria-label', desiredLabel);
        ensureRel(node);
        return true;
      };

      const tryFix = () => updateLink(document.querySelector(selector));

      const startObserver = () => {
        if (!document.body) {
          return;
        }
        const obs = new MutationObserver(() => {
          if (tryFix()) {
            obs.disconnect();
          }
        });
        obs.observe(document.body, { childList: true, subtree: true });
      };

      const start = () => {
        if (!tryFix()) {
          startObserver();
        }
      };

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
      } else {
        start();
      }
    })();
    </script>
    <?php
}, 100);
