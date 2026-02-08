(function (wp) {
    if (!wp || !wp.blocks || !wp.blockEditor || !wp.components || !wp.element) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function (s) { return s; };
    var InspectorControls = wp.blockEditor.InspectorControls;
    var InnerBlocks = wp.blockEditor.InnerBlocks;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var SelectControl = wp.components.SelectControl;
    var RangeControl = wp.components.RangeControl;
    var el = wp.element.createElement;

    registerBlockType('tmw/home-accordion', {
        title: __('TMW Home Accordion', 'retrotube-child'),
        icon: 'menu',
        category: 'widgets',
        attributes: {
            title: {
                type: 'string',
                default: ''
            },
            headingLevel: {
                type: 'string',
                default: 'auto'
            },
            lines: {
                type: 'number',
                default: 1
            }
        },
        supports: {
            html: false
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el(
                'div',
                blockProps,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Accordion Settings', 'retrotube-child'), initialOpen: true },
                        el(TextControl, {
                            label: __('Title', 'retrotube-child'),
                            value: attributes.title,
                            onChange: function (value) { setAttributes({ title: value }); }
                        }),
                        el(SelectControl, {
                            label: __('Heading level', 'retrotube-child'),
                            value: attributes.headingLevel,
                            options: [
                                { label: __('Auto (homepage first accordion uses H1)', 'retrotube-child'), value: 'auto' },
                                { label: __('Force H2', 'retrotube-child'), value: 'h2' }
                            ],
                            onChange: function (value) { setAttributes({ headingLevel: value }); }
                        }),
                        el(RangeControl, {
                            label: __('Collapsed lines', 'retrotube-child'),
                            value: attributes.lines,
                            onChange: function (value) { setAttributes({ lines: value || 1 }); },
                            min: 1,
                            max: 10
                        })
                    )
                ),
                el('h2', { className: 'widget-title' }, attributes.title || __('Accordion title…', 'retrotube-child')),
                el(InnerBlocks)
            );
        },
        save: function () {
            return el(InnerBlocks.Content);
        }
    });
})(window.wp);
