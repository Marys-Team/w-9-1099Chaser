( function( wp ) {
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var Notice = wp.components.Notice;
	var Icon = wp.components.Icon;
	var __ = wp.i18n.__;
	var el = wp.element.createElement;

	registerBlockType( 'w9-1099-chaser/w9-form', {
		title: __( 'W-9 Form', 'w9-1099-chaser' ),
		description: __( 'Display the W-9 form for vendor onboarding and tax compliance.', 'w9-1099-chaser' ),
		category: 'widgets',
		icon: 'feedback',
		keywords: [ 'w9', 'w-9', 'tax', 'vendor', 'onboarding' ],
		supports: {
			html: false,
			multiple: false,
		},
		edit: function() {
			var blockProps = useBlockProps( {
				className: 'w9-form-block-editor',
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
							title: __( 'W-9 Form Settings', 'w9-1099-chaser' ),
							initialOpen: true,
						},
						el(
							Notice,
							{
								status: 'info',
								isDismissible: false,
							},
							__( 'This block displays the W-9 form. The form will only appear once per page, even if added multiple times.', 'w9-1099-chaser' )
						)
					)
				),
				el(
					'div',
					{ className: 'w9-form-block-preview' },
					el(
						'div',
						{ className: 'w9-form-block-icon' },
						el( Icon, { icon: 'feedback' } )
					),
					el( 'h3', null, __( 'W-9 Form', 'w9-1099-chaser' ) ),
					el( 'p', null, __( 'The W-9 form will be displayed here on the frontend.', 'w9-1099-chaser' ) ),
					el(
						'div',
						{ className: 'w9-form-block-notice' },
						el( 'span', { className: 'dashicons dashicons-info' } ),
						__( 'Note: If the form is already displayed via shortcode or auto-display settings, this block will be hidden.', 'w9-1099-chaser' )
					)
				)
			);
		},
		save: function() {
			return null;
		},
	} );
}( window.wp ) );
