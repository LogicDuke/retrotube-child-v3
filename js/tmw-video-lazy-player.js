(function () {
  'use strict';

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

  function playNativeVideos(container) {
    var videos = container.querySelectorAll('video');

    videos.forEach(function (video) {
      if (typeof video.play !== 'function') {
        return;
      }

      try {
        var playResult = video.play();
        if (playResult && typeof playResult.catch === 'function') {
          playResult.catch(function () {});
        }
      } catch (error) {
        // [TMW-PLAYER-AUTOSTART] External/browser playback blocks should leave the player visible and usable.
      }
    });
  }

  function startInjectedPlayer(container) {
    // [TMW-VIDEO-LAZY] [TMW-PLAYER-AUTOSTART] Native videos can still honor
    // the same activation; cross-origin provider controls must rely on their
    // own autoplay hint and remain manually usable if autoplay is ignored.
    playNativeVideos(container);
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
    if (!container || container.getAttribute('data-tmw-video-preloaded') === '1') {
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
      // [TMW-PLAYER-AUTOSTART] Preload is only a best-effort user-interaction hint.
    }
  }

  function loadPlayer(container, shouldAutoStart) {
    if (!container || container.getAttribute('data-tmw-video-loaded') === '1') {
      return;
    }

    var markup = decodeMarkup(container.getAttribute('data-player-markup'));
    if (!markup) {
      return;
    }

    container.setAttribute('data-tmw-video-loaded', '1');
    container.innerHTML = markup;
    rehydrateScripts(container);

    if (shouldAutoStart) {
      startInjectedPlayer(container);
    }
  }

  function handleActivation(event) {
    if (!event.target || typeof event.target.closest !== 'function') {
      return;
    }

    var trigger = event.target.closest('[data-tmw-video-lazy-trigger]');
    if (!trigger) {
      return;
    }

    var container = trigger.closest('[data-tmw-video-lazy="1"]');
    if (!container) {
      return;
    }

    event.preventDefault();
    loadPlayer(container, true);
  }

  document.addEventListener('pointerdown', function (event) {
    if (!event.target || typeof event.target.closest !== 'function') {
      return;
    }

    var trigger = event.target.closest('[data-tmw-video-lazy-trigger]');
    if (!trigger) {
      return;
    }

    preloadTbplyrScript(trigger.closest('[data-tmw-video-lazy="1"]'));
  }, false);

  document.addEventListener('click', handleActivation, false);
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    handleActivation(event);
  }, false);
}());
