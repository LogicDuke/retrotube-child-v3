(function (plugins, editPost, element, components, data, i18n) {
    'use strict';

    var createElement = element.createElement;
    var PluginDocumentSettingPanel = editPost.PluginDocumentSettingPanel;
    var CheckboxControl = components.CheckboxControl;
    var RadioControl = components.RadioControl;
    var TextareaControl = components.TextareaControl;
    var __ = i18n.__;
    var DEFAULT_SHORTCODE = '[tmw_slot_machine]';

    function editMeta(key, value) {
        var meta = {};
        meta[key] = value;
        data.dispatch('core/editor').editPost({ meta: meta });
    }

    function SlotBannerPanel(props) {
        var postType = props.postType;
        var meta = props.meta;
        var mode = meta._tmw_slot_mode === 'widget' ? 'widget' : 'shortcode';
        var shortcode = typeof meta._tmw_slot_shortcode === 'string' && meta._tmw_slot_shortcode !== ''
            ? meta._tmw_slot_shortcode
            : DEFAULT_SHORTCODE;

        if (postType !== 'model' && postType !== 'video') {
            return null;
        }

        return createElement(
            PluginDocumentSettingPanel,
            {
                name: 'tmw-slot-banner',
                title: __('Slot Banner', 'retrotube-child')
            },
            createElement(CheckboxControl, {
                label: postType === 'video'
                    ? __('Enable slot banner on this video page', 'retrotube-child')
                    : __('Enable slot banner on this model page', 'retrotube-child'),
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
        var editor = select('core/editor');

        return {
            postType: editor.getCurrentPostType(),
            meta: editor.getEditedPostAttribute('meta') || {}
        };
    })(SlotBannerPanel);

    plugins.registerPlugin('tmw-slot-banner', {
        render: ConnectedSlotBannerPanel
    });
}(wp.plugins, wp.editPost, wp.element, wp.components, wp.data, wp.i18n));
