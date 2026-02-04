( function ( wp ) {
  const { registerBlockType } = wp.blocks;
  const { RichText, InnerBlocks, useBlockProps } = wp.blockEditor;

  registerBlockType( 'tmw/home-accordion-frame', {
    attributes: {
      title: {
        type: 'string',
        default: ''
      },
      openByDefault: {
        type: 'boolean',
        default: false
      }
    },
    edit: ( props ) => {
      const { attributes, setAttributes } = props;
      const { title } = attributes;
      const blockProps = useBlockProps();

      return wp.element.createElement(
        'div',
        blockProps,
        wp.element.createElement( RichText, {
          tagName: 'h2',
          className: 'widget-title',
          value: title,
          allowedFormats: [],
          placeholder: 'Accordion title...',
          onChange: ( nextTitle ) => setAttributes( { title: nextTitle } )
        } ),
        wp.element.createElement( InnerBlocks, {
          template: [
            [ 'core/paragraph', {} ],
            [ 'core/heading', { level: 2 } ],
            [ 'core/paragraph', {} ]
          ]
        } )
      );
    },
    save: () => null
  } );
} )( window.wp );
