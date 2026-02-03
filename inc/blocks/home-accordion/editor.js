( function ( wp ) {
  const { registerBlockType } = wp.blocks;
  const { RichText, InnerBlocks } = wp.blockEditor;

  registerBlockType( 'tmw/home-accordion', {
    attributes: {
      title: {
        type: 'string',
        default: ''
      }
    },
    edit: ( props ) => {
      const { attributes, setAttributes, className } = props;
      const { title } = attributes;

      return wp.element.createElement(
        'div',
        { className },
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
            [ 'core/heading', { level: 2 } ]
          ]
        } )
      );
    },
    save: () => null
  } );
} )( window.wp );
