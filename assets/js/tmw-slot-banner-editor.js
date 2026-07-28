/**
 * TMW Slot Banner — Video Gutenberg sidebar panel.
 *
 * Registers a dedicated plugin sidebar for eligible video entries.
 * Model keeps its legacy side metabox; this script is never enqueued there
 * and requires the server-localized eligibility flag at render time.
 *
 * Sidebar components resolve from wp.editor first, then wp.editPost.
 * If either is unavailable the script logs (WP_DEBUG only) and exits without
 * registering anything — createElement is never called with a null component.
 */
(function () {
    'use strict';

    var settings = window.tmwSlotBannerEditorSettings || {};
    var ELIGIBLE = settings.eligible === true;
    var DEBUG = !!settings.debug;

    if (!ELIGIBLE) {
        return;
    }

    function debugLog(message) {
        if (DEBUG && window.console && window.console.error) {
            window.console.error('[TMW-SLOT-VIDEO] ' + message);
        }
    }

    if (!window.wp || !wp.plugins || !wp.element || !wp.components || !wp.data || !wp.i18n) {
        debugLog('Required wp.* packages are missing; panel not registered.');
        return;
    }

    var Sidebar =
        wp.editor && wp.editor.PluginSidebar
            ? wp.editor.PluginSidebar
            : wp.editPost && wp.editPost.PluginSidebar
                ? wp.editPost.PluginSidebar
                : null;

    var SidebarMenuItem =
        wp.editor && wp.editor.PluginSidebarMoreMenuItem
            ? wp.editor.PluginSidebarMoreMenuItem
            : wp.editPost && wp.editPost.PluginSidebarMoreMenuItem
                ? wp.editPost.PluginSidebarMoreMenuItem
                : null;

    if (!Sidebar || !SidebarMenuItem) {
        debugLog('PluginSidebar APIs are unavailable; panel not registered.');
        return;
    }

    if (
        wp.editor &&
        Sidebar === wp.editor.PluginSidebar &&
        SidebarMenuItem === wp.editor.PluginSidebarMoreMenuItem
    ) {
        debugLog('Using wp.editor PluginSidebar-only path');
    } else {
        debugLog('Using wp.editPost PluginSidebar-only path');
    }

    var PLUGIN_NAME = 'tmw-slot-banner-video';

    if (wp.plugins.getPlugin && wp.plugins.getPlugin(PLUGIN_NAME)) {
        debugLog('Plugin "' + PLUGIN_NAME + '" is already registered; skipping duplicate registration.');
        return;
    }

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var CheckboxControl = wp.components.CheckboxControl;
    var RadioControl = wp.components.RadioControl;
    var TextareaControl = wp.components.TextareaControl;
    var PanelBody = wp.components.PanelBody;
    var __ = wp.i18n.__;
    var DEFAULT_SHORTCODE = '[tmw_slot_machine]';

    if (!CheckboxControl || !RadioControl || !TextareaControl || !PanelBody) {
        debugLog('Required wp.components controls are missing; panel not registered.');
        return;
    }

    function editMeta(key, value) {
        var meta = {};
        meta[key] = value;
        wp.data.dispatch('core/editor').editPost({ meta: meta });
    }

    function SlotBannerControls(props) {
        var meta = props.meta || {};
        var mode = meta._tmw_slot_mode === 'widget' ? 'widget' : 'shortcode';
        var shortcode = typeof meta._tmw_slot_shortcode === 'string' && meta._tmw_slot_shortcode !== ''
            ? meta._tmw_slot_shortcode
            : DEFAULT_SHORTCODE;

        return el(
            Fragment,
            null,
            el(CheckboxControl, {
                label: __('Enable slot banner on this video page', 'retrotube-child'),
                checked: String(meta._tmw_slot_enabled) === '1',
                onChange: function (enabled) {
                    editMeta('_tmw_slot_enabled', enabled ? '1' : '');
                }
            }),
            el(RadioControl, {
                label: __('Banner source', 'retrotube-child'),
                selected: mode,
                options: [
                    { label: __('Use Global Widget Area', 'retrotube-child'), value: 'widget' },
                    { label: __('Use Custom Shortcode', 'retrotube-child'), value: 'shortcode' }
                ],
                onChange: function (value) {
                    editMeta('_tmw_slot_mode', value === 'widget' ? 'widget' : 'shortcode');
                }
            }),
            el(TextareaControl, {
                label: __('Shortcode', 'retrotube-child'),
                value: shortcode,
                help: __('Default: [tmw_slot_machine]', 'retrotube-child'),
                onChange: function (value) {
                    editMeta('_tmw_slot_shortcode', value);
                }
            })
        );
    }

    function SlotBannerPanel(props) {
        debugLog('SlotBannerPanel render invoked.');
        debugLog('props.postType: ' + String(props.postType));

        if (!ELIGIBLE || (props.postType !== 'video' && props.postType !== 'post')) {
            return null;
        }

        var title = __('Slot Banner', 'retrotube-child');

        debugLog('Returning Sidebar branch');
        return el(
            Fragment,
            null,
            el(SidebarMenuItem, { target: PLUGIN_NAME }, title),
            el(
                Sidebar,
                { name: PLUGIN_NAME, title: title },
                el(PanelBody, { initialOpen: true }, el(SlotBannerControls, { meta: props.meta }))
            )
        );
    }

    var ConnectedSlotBannerPanel = wp.data.withSelect(function (select) {
        var editorStore = select('core/editor');

        if (!editorStore || !editorStore.getCurrentPostType || !editorStore.getEditedPostAttribute) {
            return { postType: null, meta: {} };
        }

        return {
            postType: editorStore.getCurrentPostType(),
            meta: editorStore.getEditedPostAttribute('meta') || {}
        };
    })(SlotBannerPanel);

    wp.plugins.registerPlugin(PLUGIN_NAME, {
        render: ConnectedSlotBannerPanel
    });
}());
