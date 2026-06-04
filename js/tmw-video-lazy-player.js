(function () {
  'use strict';

  var lazySelector = '[data-tmw-video-lazy="1"]';
  var triggerSelector = '[data-tmw-video-lazy-trigger]';

  function decodeMarkup(payload) {
    try {
      return window.atob(payload || '');
    } catch (error) {
      return '';
    }
  }

  function rehydrateScripts(container) {
    var scripts = container.querySelectorAll('script');

    scripts.forEach(function (script) {
      var replacement = document.createElement('script');

      Array.prototype.forEach.call(script.attributes, function (attribute) {
        replacement.setAttribute(attribute.name, attribute.value);
      });

      replacement.text = script.text || script.textContent || '';
      script.parentNode.replaceChild(replacement, script);
    });
  }

  function isLoaded(container) {
    return container && container.getAttribute('data-tmw-video-loaded') === '1';
  }

  function isLoading(container) {
    return container && container.getAttribute('data-tmw-video-loading') === '1';
  }

  function setLoadingState(container) {
    var trigger = container.querySelector(triggerSelector);
    var overlay = container.querySelector('.tmw-video-lazy-overlay');

    container.setAttribute('data-tmw-video-loading', '1');

    if (trigger) {
      trigger.setAttribute('aria-label', 'Loading video player…');
      trigger.setAttribute('aria-busy', 'true');
    }

    if (overlay && !overlay.querySelector('.tmw-video-lazy-loading-text')) {
      var loadingText = document.createElement('span');
      loadingText.className = 'tmw-video-lazy-loading-text';
      loadingText.textContent = 'Loading video player…';
      overlay.appendChild(loadingText);
    }
  }

  function findFirstFocusable(container) {
    return container.querySelector('a[href], button:not([disabled]), iframe, input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
  }

  function focusInjectedPlayer(container) {
    var focusTarget = findFirstFocusable(container);

    if (!focusTarget) {
      focusTarget = container;
      if (!focusTarget.hasAttribute('tabindex')) {
        focusTarget.setAttribute('tabindex', '-1');
      }
    }

    if (typeof focusTarget.focus === 'function') {
      focusTarget.focus({ preventScroll: true });
    }
  }

  function isTbplyrScriptUrl(src) {
    if (typeof src !== 'string' || src === '') {
      return false;
    }

    try {
      var url = new URL(src, window.location.href);
      var host = url.hostname.toLowerCase();

      return (host === 'atwmcd.com' || host.endsWith('.atwmcd.com')) &&
        url.pathname.indexOf('/embed/tbplyr') === 0;
    } catch (error) {
      return /(?:^|\/\/|\.)atwmcd\.com\/embed\/tbplyr/i.test(src);
    }
  }

  function preloadTbplyrScript(container) {
    if (!container || isLoaded(container) || isLoading(container) || container.getAttribute('data-tmw-video-preloaded') === '1') {
      return;
    }

    var playerSrc = container.getAttribute('data-player-src') || '';
    if (!isTbplyrScriptUrl(playerSrc)) {
      return;
    }

    container.setAttribute('data-tmw-video-preloaded', '1');

    try {
      var preload = document.createElement('link');
      preload.rel = 'preload';
      preload.as = 'script';
      preload.href = playerSrc;
      document.head.appendChild(preload);
    } catch (error) {
      // [TMW-VIDEO-LAZY] Focus warmup is best-effort and must not affect activation.
    }
  }

  function loadPlayer(container, options) {
    options = options || {};

    if (!container) {
      return;
    }

    if (isLoaded(container)) {
      if (options.focusAfterLoad) {
        focusInjectedPlayer(container);
      }
      return;
    }

    if (isLoading(container)) {
      return;
    }

    var markup = decodeMarkup(container.getAttribute('data-player-markup'));
    if (!markup) {
      return;
    }

    setLoadingState(container);
    container.setAttribute('data-tmw-video-loaded', '1');
    container.innerHTML = markup;
    rehydrateScripts(container);
    container.removeAttribute('data-tmw-video-loading');

    if (options.focusAfterLoad) {
      focusInjectedPlayer(container);
    }
  }

  function getLazyContainerFromTarget(target) {
    if (!target || typeof target.closest !== 'function') {
      return null;
    }

    return target.closest(lazySelector);
  }

  function getTriggerContainerFromTarget(target) {
    if (!target || typeof target.closest !== 'function') {
      return null;
    }

    var trigger = target.closest(triggerSelector);
    if (!trigger) {
      return null;
    }

    return trigger.closest(lazySelector);
  }

  function handleActivation(event) {
    var container = getTriggerContainerFromTarget(event.target);
    if (!container) {
      return;
    }

    event.preventDefault();
    loadPlayer(container, { focusAfterLoad: event.type === 'keydown' });
  }

  function handlePointerIntent(event) {
    var container = getLazyContainerFromTarget(event.target);
    if (!container) {
      return;
    }

    if (event.pointerType && event.pointerType === 'mouse') {
      return;
    }

    loadPlayer(container);
  }

  document.addEventListener('mouseover', function (event) {
    var container = getLazyContainerFromTarget(event.target);
    if (!container) {
      return;
    }

    if (event.relatedTarget && container.contains(event.relatedTarget)) {
      return;
    }

    loadPlayer(container);
  }, false);

  document.addEventListener('focus', function (event) {
    var container = getTriggerContainerFromTarget(event.target);
    if (!container) {
      return;
    }

    preloadTbplyrScript(container);
  }, true);

  document.addEventListener('pointerdown', handlePointerIntent, false);

  document.addEventListener('touchstart', function (event) {
    loadPlayer(getLazyContainerFromTarget(event.target));
  }, { passive: true });

  document.addEventListener('click', handleActivation, false);
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    handleActivation(event);
  }, false);
}());
