( function( wp ) {
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var Notice = wp.components.Notice;
	var Icon = wp.components.Icon;
	var __ = wp.i18n.__;
	var el = wp.element.createElement;

	registerBlockType( 'w9-1099-chaser/mypowerly-widget', {
		title: __( 'MyPowerly Widget', 'w9-1099-chaser' ),
		description: __( 'Display the configured MyPowerly chat widget with duplicate protection.', 'w9-1099-chaser' ),
		category: 'widgets',
		icon: 'admin-comments',
		keywords: [ 'mypowerly', 'widget', 'chat', 'support' ],
		supports: {
			html: false,
			multiple: false,
		},
		edit: function() {
			var blockProps = useBlockProps( {
				className: 'mypowerly-widget-block-editor',
			} );

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'MyPowerly Widget Settings', 'w9-1099-chaser' ),
							initialOpen: true,
						},
						el(
							Notice,
							{
								status: 'info',
								isDismissible: false,
							},
							__( 'This block displays the MyPowerly widget. The widget will only appear once per page, even if added multiple times.', 'w9-1099-chaser' )
						)
					)
				),
				el(
					'div',
					{ className: 'mypowerly-widget-block-preview' },
					el(
						'div',
						{ className: 'mypowerly-widget-block-icon' },
						el( Icon, { icon: 'admin-comments' } )
					),
					el( 'h3', null, __( 'MyPowerly Widget', 'w9-1099-chaser' ) ),
					el( 'p', null, __( 'The MyPowerly widget will be displayed here on the frontend.', 'w9-1099-chaser' ) ),
					el(
						'div',
						{ className: 'mypowerly-widget-block-notice' },
						el( 'span', { className: 'dashicons dashicons-info' } ),
						__( 'Note: If the widget is already displayed via shortcode or auto-display settings, this block will be hidden.', 'w9-1099-chaser' )
					)
				)
			);
		},
		save: function() {
			return null;
		},
	} );
}( window.wp ) );
