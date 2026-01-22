/**
 * TMW Global Accordion v5.0 - BULLETPROOF FINAL
 * 100% identical behavior on ALL pages
 *
 * @package RetrotubeChild
 * @version 5.0.0
 */

(function() {
    'use strict';

    // Config
    var CONFIG = {
        readMoreText: 'Read more',
        closeText: 'Close',
        headerOffset: 120,
        defaultLines: 1,
        fallbackLineHeight: 16
    };

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        initTMWAccordions();
    }

    /* =============================================
       CUSTOM TMW ACCORDIONS
       Used on: Homepage, Model taxonomy, Video/Model pages
       ============================================= */
    function initTMWAccordions() {
        var accordions = document.querySelectorAll('.tmw-accordion');

        accordions.forEach(function(accordion) {
            var toggle = accordion.querySelector('.tmw-accordion-toggle');
            var content = accordion.querySelector('.tmw-accordion-content');
            if (!toggle || !content) return;

            if (toggle.getAttribute('data-tmw-init') === 'done') return;
            toggle.setAttribute('data-tmw-init', 'done');

            var toggleWrap = toggle.closest('.tmw-accordion-toggle-wrap');

            var lines = parseInt(content.getAttribute('data-tmw-accordion-lines'), 10);
            if (!lines || lines < 1) {
                lines = CONFIG.defaultLines;
            }
            content.style.setProperty('--tmw-accordion-lines', lines);

            if (content.id && !toggle.getAttribute('aria-controls')) {
                toggle.setAttribute('aria-controls', content.id);
            }

            var readMoreText = toggle.getAttribute('data-readmore-text') || CONFIG.readMoreText;
            var closeText = toggle.getAttribute('data-close-text') || CONFIG.closeText;

            var textSpan = toggle.querySelector('.tmw-accordion-text');
            if (!textSpan) {
                textSpan = document.createElement('span');
                textSpan.className = 'tmw-accordion-text';
                textSpan.textContent = readMoreText;
                toggle.insertBefore(textSpan, toggle.firstChild);
            }

            var icon = toggle.querySelector('i, .fa');

            var lineHeight = parseFloat(window.getComputedStyle(content).lineHeight);
            if (!lineHeight) {
                lineHeight = CONFIG.fallbackLineHeight;
            }

            var maxHeight = lineHeight * lines;
            var needsToggle = content.scrollHeight > maxHeight + 1;

            if (!needsToggle) {
                content.classList.remove('tmw-accordion-collapsed');
                setToggleState(toggle, textSpan, icon, true, readMoreText, closeText);
                if (toggleWrap) {
                    toggleWrap.setAttribute('hidden', 'hidden');
                }
                return;
            }

            if (toggleWrap) {
                toggleWrap.removeAttribute('hidden');
            }

            var isExpanded = !content.classList.contains('tmw-accordion-collapsed');
            setToggleState(toggle, textSpan, icon, isExpanded, readMoreText, closeText);

            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var isCollapsed = content.classList.contains('tmw-accordion-collapsed');

                if (isCollapsed) {
                    content.classList.remove('tmw-accordion-collapsed');
                    setToggleState(toggle, textSpan, icon, true, readMoreText, closeText);
                } else {
                    content.classList.add('tmw-accordion-collapsed');
                    setToggleState(toggle, textSpan, icon, false, readMoreText, closeText);
                    scrollToElement(accordion);
                }

                toggle.blur();
            });
        });
    }

    /* =============================================
       HELPER FUNCTIONS
       ============================================= */

    /**
     * Swap icon between up and down
     */
    function swapIcon(icon, direction) {
        if (!icon) return;

        if (direction === 'up') {
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        } else {
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    }

    /**
     * Sync toggle state (text + icon)
     */
    function setToggleState(toggle, textSpan, icon, isExpanded, readMoreText, closeText) {
        toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        toggle.classList.toggle('tmw-accordion-expanded', isExpanded);
        toggle.classList.remove('expanded');

        if (textSpan) {
            textSpan.textContent = isExpanded ? closeText : readMoreText;
        }

        if (icon) {
            swapIcon(icon, isExpanded ? 'up' : 'down');
        }
    }

    /**
     * Scroll to element (only if above viewport)
     */
    function scrollToElement(element) {
        if (!element) return;

        var rect = element.getBoundingClientRect();

        // Only scroll if element is above viewport
        if (rect.top < 0) {
            var scrollY = window.pageYOffset + rect.top - CONFIG.headerOffset;

            window.scrollTo({
                top: scrollY,
                behavior: 'smooth'
            });
        }
    }

    /* =============================================
       MUTATION OBSERVER for dynamic content
       ============================================= */
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            var shouldInit = false;

            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        if (node.classList && node.classList.contains('tmw-accordion')) {
                            shouldInit = true;
                        } else if (node.querySelector && node.querySelector('.tmw-accordion')) {
                            shouldInit = true;
                        }
                    }
                });
            });

            if (shouldInit) {
                initTMWAccordions();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

})();
