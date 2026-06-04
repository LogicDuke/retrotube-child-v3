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

  function findPlayControl(container) {
    return container.querySelector([
      'button',
      '[role="button"]',
      '.play',
      '.play-button',
      '[class*="play"]',
      '[id*="play"]'
    ].join(','));
  }

  function clickPlayControl(control) {
    if (!control) {
      return;
    }

    try {
      if (typeof control.click === 'function') {
        control.click();
        return;
      }

      control.dispatchEvent(new MouseEvent('click', {
        bubbles: true,
        cancelable: true,
        view: window
      }));
    } catch (error) {
      // [TMW-PLAYER-AUTOSTART] Ignore external control errors; the loaded player remains clickable.
    }
  }

  function startInjectedPlayer(container) {
    var retryDelays = [0, 250, 500, 1000];
    var clickedControl = false;

    function attemptStart() {
      // [TMW-VIDEO-LAZY] [TMW-PLAYER-AUTOSTART] Only invoked from an explicit placeholder activation.
      playNativeVideos(container);

      if (clickedControl) {
        return;
      }

      var control = findPlayControl(container);
      if (!control) {
        return;
      }

      clickedControl = true;
      clickPlayControl(control);
    }

    retryDelays.forEach(function (delay) {
      if (delay === 0) {
        attemptStart();
        return;
      }

      window.setTimeout(attemptStart, delay);
    });
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

  document.addEventListener('click', handleActivation, false);
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    handleActivation(event);
  }, false);
}());
