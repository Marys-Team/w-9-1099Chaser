<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MyPowerly Widget Elementor Widget
 * 
 * Elementor widget for displaying MyPowerly chat widget with deduplication support.
 */
class w91099ch_Elementor_MyPowerly_Widget extends \Elementor\Widget_Base {

	/**
	 * Track if widget has been rendered in current request
	 */
	private static $widget_rendered = false;

	/**
	 * Get widget name.
	 */
	public function get_name() {
		return 'w91099ch_mypowerly_widget';
	}

	/**
	 * Get widget title.
	 */
	public function get_title() {
		return esc_html__( 'MyPowerly Widget', 'w9-1099-chaser' );
	}

	/**
	 * Get widget icon.
	 */
	public function get_icon() {
		return 'eicon-comments';
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
		return array( 'mypowerly', 'widget', 'chat', 'support', 'customer service' );
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
			if ( isset( $element['widgetType'] ) && 'w91099ch_mypowerly_widget' === $element['widgetType'] ) {
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
				'label' => esc_html__( 'MyPowerly Widget Settings', 'w9-1099-chaser' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'info_notice',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'This widget displays the configured MyPowerly chat widget. The widget will only appear once per page, even if added multiple times.', 'w9-1099-chaser' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->add_control(
			'deduplication_notice',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Note: If the widget is already displayed via shortcode, Gutenberg block, or auto-display settings, this widget will be hidden to prevent duplicates.', 'w9-1099-chaser' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
			)
		);

		$this->add_control(
			'configuration_notice',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: %s: Link to widget settings page */
					esc_html__( 'Make sure to configure your widget code in the %s before using this widget.', 'w9-1099-chaser' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=w91099ch-widget' ) ) . '" target="_blank">' . esc_html__( 'Widget Settings', 'w9-1099-chaser' ) . '</a>'
				),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output on the frontend.
	 */
	protected function render() {
		// Check if this widget instance has already been rendered
		if ( self::$widget_rendered ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="padding: 15px; border: 2px dashed #d9534f; background: #f8d7da; color: #721c24; border-radius: 4px; text-align: center;">';
				echo '<strong>' . esc_html__( 'Duplicate MyPowerly Widget', 'w9-1099-chaser' ) . '</strong><br>';
				echo esc_html__( 'This widget is already added to this page. Only one instance is allowed.', 'w9-1099-chaser' );
				echo '</div>';
			}
			return;
		}

		// Check if Widget Manager class exists
		if ( ! class_exists( 'w91099ch_Widget_Manager' ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="padding: 15px; border: 2px solid #d9534f; background: #f2dede; color: #a94442; border-radius: 4px; text-align: center;">';
				echo '<strong>' . esc_html__( 'Error:', 'w9-1099-chaser' ) . '</strong> ';
				echo esc_html__( 'Widget Manager class not found.', 'w9-1099-chaser' );
				echo '</div>';
			}
			return;
		}

		// Check if widget has already been rendered via other methods
		if ( class_exists( 'w91099ch_Widget_Manager' ) && w91099ch_Widget_Manager::is_widget_rendered() ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="padding: 15px; border: 2px dashed #f0ad4e; background: #fcf8e3; color: #8a6d3b; border-radius: 4px; text-align: center;">';
				echo '<strong>' . esc_html__( 'MyPowerly Widget', 'w9-1099-chaser' ) . '</strong><br>';
				echo esc_html__( 'Note: Widget already displayed on this page via another method (shortcode, Gutenberg block, or auto-display).', 'w9-1099-chaser' );
				echo '</div>';
			}
			return;
		}

		// Mark this widget as rendered
		self::$widget_rendered = true;

		// Render widget
		$widget_manager = new w91099ch_Widget_Manager();
		$widget_markup  = $widget_manager->render_for_display( 'elementor', true, false );

		if ( '' === $widget_markup ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="padding: 15px; border: 2px dashed #f0ad4e; background: #fcf8e3; color: #8a6d3b; border-radius: 4px; text-align: center;">';
				echo '<strong>' . esc_html__( 'MyPowerly Widget', 'w9-1099-chaser' ) . '</strong><br>';
				echo esc_html__( 'No widget code configured. Please configure your widget in the settings.', 'w9-1099-chaser' );
				echo '</div>';
			}
			return;
		}

		echo '<div class="w91099ch-elementor-mypowerly-widget">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized in widget manager.
		echo $widget_markup;
		echo '</div>';
	}

	/**
	 * Render widget output in the editor.
	 */
	protected function content_template() {
		?>
		<div style="padding: 20px; border: 2px dashed #ddd; background: #f9f9f9; border-radius: 8px; text-align: center;">
			<div style="margin-bottom: 15px;">
				<i class="eicon-comments" style="font-size: 48px; color: #2271b1;"></i>
			</div>
			<h3 style="margin: 0 0 10px; font-size: 18px; font-weight: 600;"><?php echo esc_html__( 'MyPowerly Widget', 'w9-1099-chaser' ); ?></h3>
			<p style="margin: 0; color: #757575; font-size: 14px;"><?php echo esc_html__( 'The MyPowerly widget will be displayed here on the frontend.', 'w9-1099-chaser' ); ?></p>
		</div>
		<?php
	}
}
