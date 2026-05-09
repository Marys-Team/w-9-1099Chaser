<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MyPowerly Widget Gutenberg Block Class.
 *
 * Registers and renders a dynamic block for the configured MyPowerly widget.
 * Includes deduplication logic to prevent multiple widget displays on the same page.
 */
class w91099ch_MyPowerly_Widget_Block {

	// Track if block has been rendered on current page load
	private static $block_rendered = false;

	/**
	 * Register hooks.
	 */
	public function init() {
		$this->register_block();
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'wp', array( $this, 'reset_block_flag' ), 1 );
	}

	/**
	 * Reset block flag at the beginning of each page load
	 */
	public function reset_block_flag() {
		self::$block_rendered = false;
		if ( class_exists( 'w91099ch_Widget_Manager' ) ) {
			w91099ch_Widget_Manager::reset_widget_render_state();
		}
	}

	/**
	 * Register the dynamic Gutenberg block.
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'w91099ch-mypowerly-widget-block-editor',
			w91099ch_PLUGIN_URL . 'blocks/mypowerly-widget/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			w91099ch_VERSION,
			true
		);

		wp_register_style(
			'w91099ch-mypowerly-widget-block-editor',
			w91099ch_PLUGIN_URL . 'blocks/mypowerly-widget/editor.css',
			array(),
			w91099ch_VERSION
		);

		wp_register_style(
			'w91099ch-mypowerly-widget-block',
			w91099ch_PLUGIN_URL . 'blocks/mypowerly-widget/style.css',
			array(),
			w91099ch_VERSION
		);

		register_block_type(
			'w9-1099-chaser/mypowerly-widget',
			array(
				'api_version'     => 3,
				'title'           => __( 'MyPowerly Widget', 'w9-1099-chaser' ),
				'description'     => __( 'Display the configured MyPowerly chat widget with duplicate protection.', 'w9-1099-chaser' ),
				'category'        => 'widgets',
				'icon'            => 'admin-comments',
				'keywords'        => array( 'mypowerly', 'widget', 'chat', 'support' ),
				'supports'        => array(
					'html'     => false,
					'multiple' => false,
				),
				'editor_script'   => 'w91099ch-mypowerly-widget-block-editor',
				'editor_style'    => 'w91099ch-mypowerly-widget-block-editor',
				'style'           => 'w91099ch-mypowerly-widget-block',
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Ensure block assets load in editor.
	 */
	public function enqueue_editor_assets() {
		wp_enqueue_script( 'w91099ch-mypowerly-widget-block-editor' );
		wp_enqueue_style( 'w91099ch-mypowerly-widget-block-editor' );
	}

	/**
	 * Render the block on frontend.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content (unused).
	 * @return string
	 */
	public function render_block( $attributes, $content ) {
		unset( $attributes, $content );

		// Only render on frontend
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return '';
		}

		// Check if widget has already been rendered
		if ( self::$block_rendered ) {
			return '<!-- MyPowerly Widget: Already displayed on this page -->';
		}

		// Mark that block has been rendered
		self::$block_rendered = true;

		if ( ! class_exists( 'w91099ch_Widget_Manager' ) ) {
			return '<!-- MyPowerly Widget: Widget manager unavailable -->';
		}

		$widget_manager = new w91099ch_Widget_Manager();
		$widget_markup  = $widget_manager->render_for_display( 'block', true, true );

		if ( '' === $widget_markup ) {
			return '<!-- MyPowerly Widget: Unable to render -->';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized in widget manager renderer.
		return '<div class="wp-block-w9-1099-chaser-mypowerly-widget">' . $widget_markup . '</div>';
	}

	/**
	 * Check if the block has been rendered
	 * 
	 * @return bool True if block has been rendered.
	 */
	public static function is_block_rendered() {
		return self::$block_rendered;
	}
}
