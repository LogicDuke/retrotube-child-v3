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

  function loadPlayer(container) {
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
    loadPlayer(container);
  }

  document.addEventListener('click', handleActivation, false);
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    handleActivation(event);
  }, false);
}());
