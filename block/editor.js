/**
 * Editor side of the "Adoptable animals" block.
 *
 * The block is rendered by PHP, so the editor shows a server-side preview
 * and the settings that map to the [asm_adoptable] shortcode attributes.
 * Written without a build step on purpose.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var components = wp.components;
	var blockEditor = wp.blockEditor;
	var ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'asm-wp-plugin/adoptable', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var set = function ( name ) {
				return function ( value ) {
					var update = {};
					update[ name ] = value;
					props.setAttributes( update );
				};
			};

			return el(
				'div',
				blockEditor.useBlockProps(),
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Listing', 'asm-wp-plugin' ) },
						el( components.TextControl, {
							label: __( 'Species', 'asm-wp-plugin' ),
							help: __( 'Only show these species. Separate several with commas. Leave empty for all.', 'asm-wp-plugin' ),
							value: attributes.species,
							onChange: set( 'species' ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true
						} ),
						el( components.SelectControl, {
							label: __( 'Order', 'asm-wp-plugin' ),
							value: attributes.sort,
							options: [
								{ label: __( 'Name', 'asm-wp-plugin' ), value: 'name' },
								{ label: __( 'Newest arrivals', 'asm-wp-plugin' ), value: 'newest' },
								{ label: __( 'Youngest first', 'asm-wp-plugin' ), value: 'youngest' },
								{ label: __( 'Oldest first', 'asm-wp-plugin' ), value: 'oldest' }
							],
							onChange: set( 'sort' ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true
						} ),
						el( components.RangeControl, {
							label: __( 'Most animals to show (0 for all)', 'asm-wp-plugin' ),
							value: attributes.limit,
							min: 0,
							max: 100,
							onChange: function ( value ) {
								props.setAttributes( { limit: value || 0 } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true
						} ),
						el( components.RangeControl, {
							label: __( 'Columns (0 for automatic)', 'asm-wp-plugin' ),
							value: attributes.columns,
							min: 0,
							max: 8,
							onChange: function ( value ) {
								props.setAttributes( { columns: value || 0 } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true
						} ),
						el( components.SelectControl, {
							label: __( 'Search and filter bar', 'asm-wp-plugin' ),
							value: attributes.filters,
							options: [
								{ label: __( 'Follow the plugin setting', 'asm-wp-plugin' ), value: '' },
								{ label: __( 'Show', 'asm-wp-plugin' ), value: 'all' },
								{ label: __( 'Hide', 'asm-wp-plugin' ), value: 'none' }
							],
							onChange: set( 'filters' ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true
						} )
					)
				),
				el(
					components.Disabled,
					null,
					el( ServerSideRender, {
						block: 'asm-wp-plugin/adoptable',
						attributes: attributes
					} )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp );
