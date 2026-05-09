<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * W9 Form Gutenberg Block Class
 * 
 * Handles registration and rendering of the W9 Form Gutenberg block.
 * Includes deduplication logic to prevent multiple form displays on the same page.
 */
class w91099ch_W9_Form_Block {

	// Track if block has been rendered on current page load
	private static $block_rendered = false;

	public function init() {
		// This class is initialized from the main plugin init() callback.
		// Register the block immediately here, otherwise adding another `init`
		// callback at this point may never run in the same request.
		$this->register_block();
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'wp', array( $this, 'reset_block_flag' ), 1 );
	}

	/**
	 * Reset block flag at the beginning of each page load
	 */
	public function reset_block_flag() {
		self::$block_rendered = false;
		w91099ch_reset_w9_form_render_state();
	}

	/**
	 * Register the Gutenberg block
	 */
	public function register_block() {
		// Check if Gutenberg is available (WordPress 5.0+)
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Register block script for editor
		wp_register_script(
			'w9-form-block-editor',
			w91099ch_PLUGIN_URL . 'blocks/w9-form/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			w91099ch_VERSION,
			true
		);

		// Register editor styles
		wp_register_style(
			'w9-form-block-editor',
			w91099ch_PLUGIN_URL . 'blocks/w9-form/editor.css',
			array(),
			w91099ch_VERSION
		);

		// Register frontend styles
		wp_register_style(
			'w9-form-block',
			w91099ch_PLUGIN_URL . 'blocks/w9-form/style.css',
			array(),
			w91099ch_VERSION
		);

		// Register the block type explicitly so the editor always sees it.
		register_block_type( 'w9-1099-chaser/w9-form', array(
			'api_version'     => 3,
			'title'           => __( 'W-9 Form', 'w9-1099-chaser' ),
			'description'     => __( 'Display the W-9 form for vendor onboarding and tax compliance.', 'w9-1099-chaser' ),
			'category'        => 'widgets',
			'icon'            => 'feedback',
			'keywords'        => array( 'w9', 'w-9', 'form', 'tax', 'vendor', 'onboarding' ),
			'supports'        => array(
				'html'     => false,
				'multiple' => false,
			),
			'editor_script'   => 'w9-form-block-editor',
			'editor_style'    => 'w9-form-block-editor',
			'style'           => 'w9-form-block',
			'render_callback' => array( $this, 'render_block' ),
		) );
	}

	/**
	 * Ensure block assets are loaded in the post editor.
	 */
	public function enqueue_editor_assets() {
		wp_enqueue_script( 'w9-form-block-editor' );
		wp_enqueue_style( 'w9-form-block-editor' );
	}

	/**
	 * Render the block on frontend
	 * 
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content (unused for dynamic block).
	 * @return string Rendered block HTML.
	 */
	public function render_block( $attributes, $content ) {
		// Only render on frontend
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return '';
		}

		if ( ! $this->is_w9_form_enabled() ) {
			return '';
		}

		// Check if current page is password protected
		if ( $this->is_page_password_protected() ) {
			return '';
		}

		// Deduplication: Check if form has already been rendered via shortcode or auto-display
		if ( $this->is_form_already_rendered() ) {
			return '<!-- W-9 Form: Already displayed on this page -->';
		}

		// Mark that block has been rendered
		self::$block_rendered = true;

		// Mark that the form has been rendered (for other methods to check)
		$this->mark_form_as_rendered();

		// Enqueue required assets
		$this->enqueue_block_assets();

		// Get the form HTML from the existing shortcode class
		$form_html = $this->get_form_html();

		if ( empty( $form_html ) ) {
			return '<!-- W-9 Form: Unable to render form -->';
		}

		return '<div class="wp-block-w9-1099-chaser-w9-form">' . $form_html . '</div>';
	}

	/**
	 * Check if the W-9 form is enabled globally
	 */
	private function is_w9_form_enabled() {
		return (bool) get_option( 'w91099ch_w9_form_enabled', false );
	}

	/**
	 * Check if the form has already been rendered by another method
	 * 
	 * @return bool True if form is already rendered, false otherwise.
	 */
	private function is_form_already_rendered() {
		return self::$block_rendered || w91099ch_is_w9_form_rendered();
	}

	/**
	 * Mark the form as rendered (for other methods to check)
	 */
	private function mark_form_as_rendered() {
		w91099ch_mark_w9_form_rendered( 'block' );
	}

	/**
	 * Enqueue required assets for the block
	 */
	private function enqueue_block_assets() {
		// Get the shortcode class instance to use its asset registration
		if ( class_exists( 'w91099ch_W9_Form_Shortcode' ) ) {
			$shortcode = new w91099ch_W9_Form_Shortcode();
			$shortcode->register_assets();
			$shortcode->enqueue_assets();
		}
	}

	/**
	 * Get form HTML content
	 * 
	 * @return string Form HTML.
	 */
	private function get_form_html() {
		ob_start();

		// Use the unified form template
		$form_template = w91099ch_PLUGIN_PATH . 'includes/views/w9-form-template.php';
		if ( file_exists( $form_template ) ) {
			// Pass default attributes for block
			$atts = array(
				'title' => 'W-9 Form',
				'hide_tools' => false,
			);
			include $form_template;
		} else {
			// Fallback message
			echo '<p>' . esc_html__( 'W-9 Form template not found.', 'w9-1099-chaser' ) . '</p>';
		}

		return ob_get_clean();
	}

	/**
	 * Check if current page is password protected
	 * 
	 * @return bool True if page is password protected.
	 */
	private function is_page_password_protected() {
		$post = get_post();
		if ( ! $post ) {
			return false;
		}

		// Check if post requires password
		if ( post_password_required( $post->ID ) ) {
			return true;
		}

		// Check for password protected meta
		$post_meta = get_post_meta( $post->ID );
		if ( ! empty( $post_meta['_post_password_required'][0] ) && '1' === $post_meta['_post_password_required'][0] ) {
			return true;
		}

		return false;
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

/**
 * Helper function to check if form has been rendered (for use by other classes)
 * 
 * @return bool True if form has been rendered.
 */
function w91099ch_is_w9_form_rendered() {
	$state = w91099ch_get_w9_form_render_state();

	return ! empty( $state['rendered'] );
}

/**
 * Reset the single-render state for the current request.
 */
function w91099ch_reset_w9_form_render_state() {
	$GLOBALS['w91099ch_w9_form_render_state'] = array(
		'rendered' => false,
		'source'   => '',
	);
}

/**
 * Track which display method rendered the form first.
 *
 * @param string $source Render source identifier.
 */
function w91099ch_mark_w9_form_rendered( $source ) {
	$GLOBALS['w91099ch_w9_form_render_state'] = array(
		'rendered' => true,
		'source'   => sanitize_key( (string) $source ),
	);
}

/**
 * Get current render state for the request.
 *
 * @return array<string, mixed>
 */
function w91099ch_get_w9_form_render_state() {
	if ( ! isset( $GLOBALS['w91099ch_w9_form_render_state'] ) || ! is_array( $GLOBALS['w91099ch_w9_form_render_state'] ) ) {
		w91099ch_reset_w9_form_render_state();
	}

	return $GLOBALS['w91099ch_w9_form_render_state'];
}

/**
 * Return the method that rendered the form first on this request.
 *
 * @return string
 */
function w91099ch_get_w9_form_render_source() {
	$state = w91099ch_get_w9_form_render_state();

	return isset( $state['source'] ) ? (string) $state['source'] : '';
}
