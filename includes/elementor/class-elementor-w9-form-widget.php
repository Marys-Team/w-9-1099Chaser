<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * W9 Form Elementor Widget
 * 
 * Elementor widget for displaying W-9 form with deduplication support.
 */
class w91099ch_Elementor_W9_Form_Widget extends \Elementor\Widget_Base {

	/**
	 * Track if widget has been rendered in current request
	 */
	private static $widget_rendered = false;

	/**
	 * Get widget name.
	 */
	public function get_name() {
		return 'w91099ch_w9_form';
	}

	/**
	 * Get widget title.
	 */
	public function get_title() {
		return esc_html__( 'W-9 Form', 'w9-1099-chaser' );
	}

	/**
	 * Get widget icon.
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	/**
	 * Get widget categories.
	 */
	public function get_categories() {
		return array( 'general' );
	}

	/**
	 * Get widget keywords.
	 */
	public function get_keywords() {
		return array( 'w9', 'w-9', 'form', 'tax', 'vendor', 'onboarding', 'compliance' );
	}

	/**
	 * Show in panel - limit to single instance
	 */
	public function show_in_panel() {
		// In editor mode, check if widget already exists on page
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$document = \Elementor\Plugin::$instance->documents->get_current();
			if ( $document ) {
				$data = $document->get_elements_data();
				if ( $this->widget_exists_in_data( $data ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Check if widget exists in page data
	 */
	private function widget_exists_in_data( $elements ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['widgetType'] ) && 'w91099ch_w9_form' === $element['widgetType'] ) {
				return true;
			}
			if ( ! empty( $element['elements'] ) ) {
				if ( $this->widget_exists_in_data( $element['elements'] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Register widget controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => esc_html__( 'W-9 Form Settings', 'w9-1099-chaser' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'info_notice',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'This widget displays the W-9 form. The form will only appear once per page, even if added multiple times.', 'w9-1099-chaser' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->add_control(
			'deduplication_notice',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Note: If the form is already displayed via shortcode, Gutenberg block, or auto-display settings, this widget will be hidden to prevent duplicates.', 'w9-1099-chaser' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output on the frontend.
	 */
	protected function render() {
		// Check if the W-9 form is enabled globally
		if ( ! $this->is_w9_form_enabled() ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="padding: 15px; border: 2px dashed #6b7280; background: #f3f4f6; color: #111827; border-radius: 4px; text-align: center;">';
				echo '<strong>' . esc_html__( 'W-9 Form Widget Disabled', 'w9-1099-chaser' ) . '</strong><br>';
				echo esc_html__( 'Enable the W-9 form in W-9 Display Settings to render this widget.', 'w9-1099-chaser' );
				echo '</div>';
			}
			return;
		}

		// Check if this widget instance has already been rendered
		if ( self::$widget_rendered ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="padding: 15px; border: 2px dashed #d9534f; background: #f8d7da; color: #721c24; border-radius: 4px; text-align: center;">';
				echo '<strong>' . esc_html__( 'Duplicate W-9 Form Widget', 'w9-1099-chaser' ) . '</strong><br>';
				echo esc_html__( 'This widget is already added to this page. Only one instance is allowed.', 'w9-1099-chaser' );
				echo '</div>';
			}
			return;
		}

		// Check if form has already been rendered via other methods
		if ( function_exists( 'w91099ch_is_w9_form_rendered' ) && w91099ch_is_w9_form_rendered() ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="padding: 15px; border: 2px dashed #f0ad4e; background: #fcf8e3; color: #8a6d3b; border-radius: 4px; text-align: center;">';
				echo '<strong>' . esc_html__( 'W-9 Form Widget', 'w9-1099-chaser' ) . '</strong><br>';
				echo esc_html__( 'Note: Form already displayed on this page via another method (shortcode, Gutenberg block, or auto-display).', 'w9-1099-chaser' );
				echo '</div>';
			}
			return;
		}

		// Mark this widget as rendered
		self::$widget_rendered = true;

		// Mark form as rendered globally
		if ( function_exists( 'w91099ch_mark_w9_form_rendered' ) ) {
			w91099ch_mark_w9_form_rendered( 'elementor' );
		}

		// Enqueue required assets
		if ( class_exists( 'w91099ch_W9_Form_Shortcode' ) ) {
			$shortcode = new w91099ch_W9_Form_Shortcode();
			$shortcode->register_assets();
			$shortcode->enqueue_assets();
		}

		// Render form
		echo '<div class="w91099ch-elementor-w9-form-widget">';
		
		$form_template = w91099ch_PLUGIN_PATH . 'includes/views/w9-form-template.php';
		if ( file_exists( $form_template ) ) {
			$atts = array(
				'title'      => 'W-9 Form',
				'hide_tools' => false,
			);
			include $form_template;
		} else {
			echo '<p>' . esc_html__( 'W-9 Form template not found.', 'w9-1099-chaser' ) . '</p>';
		}
		
		echo '</div>';
	}

	/**
	 * Check if the W-9 form is enabled globally
	 */
	private function is_w9_form_enabled() {
		return (bool) get_option( 'w91099ch_w9_form_enabled', false );
	}

	/**
	 * Render widget output in the editor.
	 */
	protected function content_template() {
		?>
		<div style="padding: 20px; border: 2px dashed #ddd; background: #f9f9f9; border-radius: 8px; text-align: center;">
			<div style="margin-bottom: 15px;">
				<i class="eicon-form-horizontal" style="font-size: 48px; color: #2271b1;"></i>
			</div>
			<h3 style="margin: 0 0 10px; font-size: 18px; font-weight: 600;"><?php echo esc_html__( 'W-9 Form', 'w9-1099-chaser' ); ?></h3>
			<p style="margin: 0; color: #757575; font-size: 14px;"><?php echo esc_html__( 'The W-9 form will be displayed here on the frontend.', 'w9-1099-chaser' ); ?></p>
		</div>
		<?php
	}
}
