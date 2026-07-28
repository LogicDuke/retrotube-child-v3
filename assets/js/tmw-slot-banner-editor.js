/**
 * Native Slot Banner document-sidebar panel for the VIDEO block editor.
 *
 * Model posts use the classic side metabox + inline wp.data sync instead;
 * this script is only enqueued on video editor screens.
 *
 * Uses wp.editor.PluginDocumentSettingPanel (canonical since WP 6.6).
 */
(function (plugins, editor, element, components, data, i18n) {
    'use strict';

    var createElement = element.createElement;
    var PluginDocumentSettingPanel = editor.PluginDocumentSettingPanel;
    var CheckboxControl = components.CheckboxControl;
    var RadioControl = components.RadioControl;
    var TextareaControl = components.TextareaControl;
    var __ = i18n.__;
    var DEFAULT_SHORTCODE = '[tmw_slot_machine]';

    if (!PluginDocumentSettingPanel) {
        // Fail silently — the classic metabox fallback is still available
        // in Classic Editor, and model never loads this script.
        return;
    }

    function editMeta(key, value) {
        var meta = {};
        meta[key] = value;
        data.dispatch('core/editor').editPost({ meta: meta });
    }

    function SlotBannerPanel(props) {
        var postType = props.postType;
        var meta = props.meta;

        // Only render for video; model uses the classic side metabox.
        if (postType !== 'video') {
            return null;
        }

        var mode = meta._tmw_slot_mode === 'widget' ? 'widget' : 'shortcode';
        var shortcode = typeof meta._tmw_slot_shortcode === 'string' && meta._tmw_slot_shortcode !== ''
            ? meta._tmw_slot_shortcode
            : DEFAULT_SHORTCODE;

        return createElement(
            PluginDocumentSettingPanel,
            {
                name: 'tmw-slot-banner',
                title: __('Slot Banner', 'retrotube-child')
            },
            createElement(CheckboxControl, {
                label: __('Enable slot banner on this video page', 'retrotube-child'),
                checked: meta._tmw_slot_enabled === '1',
                onChange: function (enabled) {
                    editMeta('_tmw_slot_enabled', enabled ? '1' : '');
                }
            }),
            createElement(RadioControl, {
                label: __('Banner source', 'retrotube-child'),
                selected: mode,
                options: [
                    { label: __('Use Global Widget Area', 'retrotube-child'), value: 'widget' },
                    { label: __('Use Custom Shortcode', 'retrotube-child'), value: 'shortcode' }
                ],
                onChange: function (value) {
                    editMeta('_tmw_slot_mode', value);
                }
            }),
            createElement(TextareaControl, {
                label: __('Shortcode', 'retrotube-child'),
                value: shortcode,
                help: __('Default: [tmw_slot_machine]', 'retrotube-child'),
                onChange: function (value) {
                    editMeta('_tmw_slot_shortcode', value);
                }
            })
        );
    }

    var ConnectedSlotBannerPanel = data.withSelect(function (select) {
        var ed = select('core/editor');

        return {
            postType: ed.getCurrentPostType(),
            meta: ed.getEditedPostAttribute('meta') || {}
        };
    })(SlotBannerPanel);

    plugins.registerPlugin('tmw-slot-banner', {
        render: ConnectedSlotBannerPanel
    });
}(wp.plugins, wp.editor, wp.element, wp.components, wp.data, wp.i18n));
