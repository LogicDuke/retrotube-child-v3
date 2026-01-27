(() => {
    if (!window.wp || !window.wp.hooks || !window.wp.data) {
        return;
    }

    const settings = window.tmwRankMathHome || {};
    const frontPageId = Number(settings.frontPageId || 0);
    const currentPostId = Number(settings.currentPostId || 0);
    const isFrontPage = frontPageId > 0 && frontPageId === currentPostId;

    const escapeHtml = (value) => {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const parseShortcodes = (content) => {
        if (!content) {
            return [];
        }

        const matches = [];
        const shortcodeRegex = /\[tmw_home_accordion([^\]]*)\]([\s\S]*?)\[\/tmw_home_accordion\]/gi;
        let match = shortcodeRegex.exec(content);
        while (match) {
            const attrs = match[1] || '';
            const inner = match[2] || '';
            const titleMatch = /title\s*=\s*(?:"([^"]*)"|'([^']*)')/i.exec(attrs);
            const title = titleMatch ? (titleMatch[1] || titleMatch[2] || '') : '';
            matches.push({
                title,
                inner,
            });
            match = shortcodeRegex.exec(content);
        }

        return matches;
    };

    const buildAccordionHtml = (shortcodes) => {
        if (!shortcodes.length) {
            return '';
        }

        return shortcodes
            .map((shortcode) => {
                const title = shortcode.title || 'Top Models';
                const headingTag = isFrontPage ? 'h1' : 'h2';
                const level = title.toLowerCase().includes('models') ? 'h1' : 'h2';
                const autoHeading = `${title} Webcam Directory`;
                const innerText = shortcode.inner
                    .replace(/\[[^\]]*\]/g, '')
                    .replace(/<[^>]*>/g, '')
                    .trim();
                const paragraph = innerText ? `<p>${escapeHtml(innerText)}</p>` : '';

                return [
                    `<${headingTag}>${escapeHtml(title)}</${headingTag}>`,
                    `<${level} class="tmw-accordion-auto-${level}">${escapeHtml(autoHeading)}</${level}>`,
                    paragraph,
                ].join('');
            })
            .join('\n');
    };

    const addRankMathContentFilter = () => {
        window.wp.hooks.addFilter(
            'rank_math_content',
            'tmw/home-accordion',
            (content) => {
                const editorContent = window.wp.data
                    .select('core/editor')
                    .getEditedPostContent();
                const shortcodes = parseShortcodes(editorContent);
                const appendedHtml = buildAccordionHtml(shortcodes);
                if (!appendedHtml) {
                    return content;
                }

                return `${content}\n${appendedHtml}`;
            }
        );
    };

    const setupRefresh = () => {
        if (!window.rankMathEditor || typeof window.rankMathEditor.refresh !== 'function') {
            return;
        }

        let lastContent = window.wp.data.select('core/editor').getEditedPostContent();
        let refreshTimeout;

        window.wp.data.subscribe(() => {
            const currentContent = window.wp.data.select('core/editor').getEditedPostContent();
            if (currentContent === lastContent) {
                return;
            }

            lastContent = currentContent;
            if (refreshTimeout) {
                clearTimeout(refreshTimeout);
            }

            refreshTimeout = setTimeout(() => {
                window.rankMathEditor.refresh('content');
            }, 300);
        });
    };

    addRankMathContentFilter();
    setupRefresh();
})();
