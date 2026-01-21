/**
 * TMW Slot Banner - Gutenberg Metabox Sync
 * Ensures metabox values are saved when using Block Editor
 */
(function () {
    'use strict';

    if (typeof wp === 'undefined' || !wp.data) {
        return;
    }

    const { subscribe, select } = wp.data;
    let wasSaving = false;
    let saveTimeout = null;

    subscribe(function () {
        const editor = select('core/editor');
        if (!editor) {
            return;
        }

        const isSaving = editor.isSavingPost();
        const isAutosave = editor.isAutosavingPost();

        // Detect save completion (was saving, now not saving, not an autosave)
        if (wasSaving && !isSaving && !isAutosave) {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(syncMetaToREST, 500);
        }

        wasSaving = isSaving;
    });

    function syncMetaToREST() {
        const postId = select('core/editor')?.getCurrentPostId();
        if (!postId) {
            return;
        }

        const enabledEl = document.querySelector('input[name="tmw_slot_enabled"]');
        const modeEl = document.querySelector('input[name="tmw_slot_mode"]:checked');
        const shortcodeEl = document.querySelector('textarea[name="tmw_slot_shortcode"]');

        if (!enabledEl) {
            return;
        }

        const meta = {
            _tmw_slot_enabled: enabledEl.checked ? '1' : '',
            _tmw_slot_mode: modeEl ? modeEl.value : 'shortcode',
            _tmw_slot_shortcode: shortcodeEl ? shortcodeEl.value.trim() : '[tmw_slot_machine]',
        };

        // Default shortcode if empty
        if (!meta._tmw_slot_shortcode) {
            meta._tmw_slot_shortcode = '[tmw_slot_machine]';
        }

        wp.apiFetch({
            path: '/wp/v2/model/' + postId,
            method: 'POST',
            data: { meta: meta },
        }).then(function () {
        }).catch(function (err) {
            console.error('[TMW-SLOT] Sync failed:', err);
        });
    }

    // Also sync on manual checkbox/radio changes
    document.addEventListener('change', function (e) {
        if (e.target.name && e.target.name.startsWith('tmw_slot')) {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(syncMetaToREST, 1000);
        }
    });
})();
