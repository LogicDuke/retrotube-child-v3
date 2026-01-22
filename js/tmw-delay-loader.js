(function () {
    'use strict';

    var hasRun = false;
    var timerId = null;

    function activateAsyncStyles() {
        var asyncLinks = document.querySelectorAll('link[rel="preload"][as="style"][data-tmw-async]');
        if (!asyncLinks.length) {
            return;
        }

        asyncLinks.forEach(function (link) {
            if (link.getAttribute('data-tmw-async-applied')) {
                return;
            }
            link.setAttribute('data-tmw-async-applied', '1');
            var applyStylesheet = function () {
                link.rel = 'stylesheet';
            };
            link.addEventListener('load', applyStylesheet);
            link.addEventListener('error', applyStylesheet);

            if (link.sheet) {
                applyStylesheet();
                return;
            }

            window.setTimeout(function () {
                if (link.rel !== 'stylesheet') {
                    applyStylesheet();
                }
            }, 3000);
        });
    }

    function runDelayedScripts() {
        if (hasRun) {
            return;
        }
        hasRun = true;

        if (timerId) {
            clearTimeout(timerId);
            timerId = null;
        }

        var delayedScripts = document.querySelectorAll('script[type="text/plain"][data-tmw-delay][data-src]');
        if (!delayedScripts.length) {
            return;
        }

        var queue = Array.prototype.slice.call(delayedScripts);
        function loadNext() {
            var placeholder = queue.shift();
            if (!placeholder) {
                return;
            }

            var src = placeholder.getAttribute('data-src');
            if (!src) {
                loadNext();
                return;
            }

            var script = document.createElement('script');
            script.src = src;
            script.async = false;

            Array.prototype.slice.call(placeholder.attributes).forEach(function (attr) {
                var name = attr.name;
                if (name === 'type' || name === 'data-src' || name === 'data-tmw-delay') {
                    return;
                }
                script.setAttribute(name, attr.value);
            });

            script.onload = loadNext;
            script.onerror = loadNext;
            document.head.appendChild(script);
        }

        loadNext();
    }

    function onFirstInteraction() {
        runDelayedScripts();
    }

    function addInteractionListeners() {
        var options = { passive: true, once: true };
        ['touchstart', 'scroll', 'mousedown', 'keydown'].forEach(function (eventName) {
            window.addEventListener(eventName, onFirstInteraction, options);
        });
    }

    addInteractionListeners();
    window.addEventListener('load', runDelayedScripts, { once: true });
    timerId = window.setTimeout(runDelayedScripts, 2500);
    activateAsyncStyles();
})();
