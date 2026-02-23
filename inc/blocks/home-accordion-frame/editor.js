( function ( wp ) {
  const { registerBlockType } = wp.blocks;
  const { InspectorControls, InnerBlocks, useBlockProps } = wp.blockEditor;
  const { PanelBody, TextControl, ToggleControl } = wp.components;
  const { __ } = wp.i18n;

  registerBlockType( 'tmw/home-accordion-frame', {
    edit: ( props ) => {
      const { attributes, setAttributes } = props;
      const { title, collapsed } = attributes;
      const blockProps = useBlockProps();

      return wp.element.createElement(
        'div',
        blockProps,
        wp.element.createElement(
          InspectorControls,
          null,
          wp.element.createElement(
            PanelBody,
            { title: __( 'Frame Settings', 'retrotube-child' ), initialOpen: true },
            wp.element.createElement( TextControl, {
              label: __( 'Title', 'retrotube-child' ),
              value: title || '',
              onChange: ( nextTitle ) => setAttributes( { title: nextTitle } ),
              placeholder: __( 'Models', 'retrotube-child' )
            } ),
            wp.element.createElement( ToggleControl, {
              label: __( 'Collapsed by default', 'retrotube-child' ),
              checked: collapsed !== false,
              onChange: ( nextCollapsed ) => setAttributes( { collapsed: !! nextCollapsed } )
            } )
          )
        ),
        wp.element.createElement( InnerBlocks, {
          allowedBlocks: [
            'core/paragraph',
            'core/heading',
            'core/list',
            'core/buttons',
            'core/html'
          ],
          template: [
            [ 'core/paragraph', {} ]
          ]
        } )
      );
    },
    save: () => wp.element.createElement( InnerBlocks.Content )
  } );
} )( window.wp );
