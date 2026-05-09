<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor Integration Manager
 * 
 * Handles registration and integration of custom Elementor widgets.
 */
class w91099ch_Elementor_Integration {

	/**
	 * Instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Check if Elementor is installed and activated
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Initialize Elementor integration.
	 */
	public function init() {
		// Check if Elementor is installed and activated
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		// Register widgets
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

		// Register widget categories (optional)
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_widget_categories' ) );
	}

	/**
	 * Register custom Elementor widgets.
	 */
	public function register_widgets( $widgets_manager ) {
		// Include widget files
		require_once w91099ch_PLUGIN_PATH . 'includes/elementor/class-elementor-w9-form-widget.php';
		require_once w91099ch_PLUGIN_PATH . 'includes/elementor/class-elementor-mypowerly-widget.php';

		// Register W-9 Form widget
		$widgets_manager->register( new w91099ch_Elementor_W9_Form_Widget() );

		// Register MyPowerly Widget
		$widgets_manager->register( new w91099ch_Elementor_MyPowerly_Widget() );
	}

	/**
	 * Register custom widget categories (optional).
	 */
	public function register_widget_categories( $elements_manager ) {
		$elements_manager->add_category(
			'w9-1099-chaser',
			array(
				'title' => esc_html__( 'W9-1099 Chaser', 'w9-1099-chaser' ),
				'icon'  => 'fa fa-plug',
			)
		);
	}
}

// Initialize Elementor integration
w91099ch_Elementor_Integration::get_instance();
