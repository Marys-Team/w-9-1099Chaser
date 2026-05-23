<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_W9_Form_Shortcode {

	const PDF_CACHE_TRANSIENT_KEY = 'w91099ch_fw9_pdf_cache';
	
	// Track if shortcode has been rendered on current page load
	private static $shortcode_rendered = false;
	
	public function init() {
		// Reset shortcode flag at the beginning of each page load
		add_action( 'wp', array( $this, 'reset_shortcode_flag' ), 1 );

		add_shortcode( 'w91099ch_w9_form', array( $this, 'render_shortcode' ) );

		// Enqueue assets early for both shortcode and auto-display
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		// Check if we need to display on frontend and enqueue assets
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_auto_display_assets' ) );

		add_action( 'wp_ajax_w91099ch_get_fw9_pdf', array( $this, 'ajax_get_fw9_pdf' ) );
		add_action( 'wp_ajax_nopriv_w91099ch_get_fw9_pdf', array( $this, 'ajax_get_fw9_pdf' ) );
		add_action( 'wp_ajax_w91099ch_generate_govt_pdf', array( $this, 'ajax_generate_govt_pdf' ) );
		add_action( 'wp_ajax_nopriv_w91099ch_generate_govt_pdf', array( $this, 'ajax_generate_govt_pdf' ) );
		add_action( 'wp_ajax_w91099ch_submit_feedback', array( $this, 'ajax_submit_feedback' ) );
		add_action( 'wp_ajax_w91099ch_send_pdf_email', array( $this, 'ajax_send_pdf_email' ) );
		add_action( 'wp_ajax_nopriv_w91099ch_send_pdf_email', array( $this, 'ajax_send_pdf_email' ) );
		add_action( 'wp_ajax_w91099ch_send_review_feedback', array( $this, 'ajax_send_review_feedback' ) );
		add_action( 'wp_ajax_nopriv_w91099ch_send_review_feedback', array( $this, 'ajax_send_review_feedback' ) );

		// Add AJAX handler for client-side default page URL (no admin permissions required)
		add_action( 'wp_ajax_w91099ch_get_default_page_url', array( $this, 'ajax_get_default_page_url' ) );
		add_action( 'wp_ajax_nopriv_w91099ch_get_default_page_url', array( $this, 'ajax_get_default_page_url' ) );

		// Add AJAX handler for download tracking
		add_action( 'wp_ajax_w91099ch_track_download', array( $this, 'ajax_track_download' ) );
		add_action( 'wp_ajax_nopriv_w91099ch_track_download', array( $this, 'ajax_track_download' ) );

		// Temporary test endpoint for configuration debugging
		add_action( 'wp_ajax_w91099ch_test_config', array( $this, 'ajax_test_config' ) );
		add_action( 'wp_ajax_nopriv_w91099ch_test_config', array( $this, 'ajax_test_config' ) );

		// Add client tools to form header (not top header)
		// Tools will be included in the form template

		add_action( 'wp_footer', array( $this, 'auto_display_w9_form' ) );
	}

	/**
	 * Reset shortcode flag at the beginning of each page load
	 */
	public function reset_shortcode_flag() {
		self::$shortcode_rendered = false;
		w91099ch_reset_w9_form_render_state();
	}

	/**
	 * Check if the W-9 form is enabled globally
	 */
	private function is_w9_form_enabled() {
		return (bool) get_option( 'w91099ch_w9_form_enabled', false );
	}

	/**
	 * Auto-display W-9 form on pages based on settings
	 */
	public function auto_display_w9_form() {
		// Only run on frontend, not in admin or AJAX
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		if ( ! $this->is_w9_form_enabled() ) {
			return;
		}

		// Prevent multiple displays on the same page
		static $displayed = false;
		if ( $displayed ) {
			return;
		}

		// Check if current page is password protected - don't show form on protected pages
		if ( $this->is_page_password_protected() ) {
			return;
		}

		// Intelligent deduplication: Skip auto-display if shortcode has been rendered on this page
		if ( self::$shortcode_rendered ) {
			return;
		}

		// Intelligent deduplication: Skip auto-display if another method already rendered the form
		if ( function_exists( 'w91099ch_is_w9_form_rendered' ) && w91099ch_is_w9_form_rendered() ) {
			return;
		}

		$display_method = get_option( 'w91099ch_w9_display_method', 'all' );
		if ( $display_method === 'shortcode' ) {
			return;
		}

		$should_display = false;
		$current_page_id = get_the_ID();

		if ( $display_method === 'all' ) {
			$should_display = true;
		} elseif ( $display_method === 'selected' ) {
			$selected_pages = get_option( 'w91099ch_w9_selected_pages', array() );
			if ( is_array( $selected_pages ) && in_array( (string) $current_page_id, $selected_pages, true ) ) {
				$should_display = true;
			}
		}

		if ( $should_display ) {
			$displayed = true;
			w91099ch_mark_w9_form_rendered( 'auto-display' );
			$display_position = get_option( 'w91099ch_w9_display_position', 'bottom' );
			
			if ( $display_position === 'floating' ) {
				// Add floating widget functionality
				$this->add_floating_widget();
			} else {
				// Render the form HTML directly with position styling
				$form_html = $this->get_form_html();
				if ( ! empty( $form_html ) ) {
					$wrapper_style = $this->get_position_wrapper_style( $display_position );
					echo '<div class="w91099ch-auto-display-wrapper" style="' . esc_attr( $wrapper_style ) . '">';
					echo $form_html;
					echo '</div>';
				}
			}
		}
	}

	/**
	 * Check if auto-display is enabled and enqueue assets early
	 */
	public function maybe_enqueue_auto_display_assets() {
		// Only on frontend
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		if ( ! $this->is_w9_form_enabled() ) {
			return;
		}

		// Check if current page is password protected - don't enqueue assets on protected pages
		if ( $this->is_page_password_protected() ) {
			return;
		}

		// Intelligent deduplication: Skip auto-display assets if shortcode has been rendered on this page
		if ( self::$shortcode_rendered ) {
			return;
		}

		// Intelligent deduplication: Skip auto-display assets if another method has already rendered the form
		if ( function_exists( 'w91099ch_is_w9_form_rendered' ) && w91099ch_is_w9_form_rendered() ) {
			return;
		}

		$display_method = get_option( 'w91099ch_w9_display_method', 'all' );
		if ( $display_method === 'shortcode' ) {
			return;
		}

		$should_enqueue = false;
		$current_page_id = get_the_ID();

		if ( $display_method === 'all' ) {
			$should_enqueue = true;
		} elseif ( $display_method === 'selected' ) {
			$selected_pages = get_option( 'w91099ch_w9_selected_pages', array() );
			if ( is_array( $selected_pages ) && in_array( (string) $current_page_id, $selected_pages, true ) ) {
				$should_enqueue = true;
			}
		}

		if ( $should_enqueue ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * Get form HTML content for auto-display
	 * This method generates form HTML using the unified template
	 */
	private function get_form_html() {
		// Use output buffering to capture the form HTML
		ob_start();
		
		// Include the unified form template
		$form_template = w91099ch_PLUGIN_PATH . 'includes/views/w9-form-template.php';
		if ( file_exists( $form_template ) ) {
			// Pass default attributes for auto-display
			$atts = array(
				'title' => 'W-9 Form',
				'hide_tools' => false
			);
			include $form_template;
		} else {
			// Fallback: render inline form if template doesn't exist
			$this->render_form_inline();
		}
		
		return ob_get_clean();
	}

	/**
	 * Render form inline (fallback when template doesn't exist)
	 */
	private function render_form_inline() {
		// Render a simplified version of the form
		?>
		<div class="w9-form-container" style="max-width: 1000px; margin: 0 auto; padding: 20px;">
			<div class="w9-form-card" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;">
				<div class="w9-form-header-bar" style="background: linear-gradient(to right, #2563eb, #4f46e5); padding: 24px 32px;">
					<h2 style="color: white; font-size: 24px; font-weight: bold; margin: 0;">W-9 Form</h2>
					<p style="color: rgba(255,255,255,0.8); margin-top: 4px;">Complete all required fields marked with *</p>
				</div>
				<div class="w9-form-body" style="padding: 32px;">
					<form id="mypowerly-w9-form" class="mypowerly-w9-form" novalidate>
						<?php wp_nonce_field( 'w91099ch_w9_form_submit', 'w91099ch_w9_nonce' ); ?>
						
						<!-- Name Section -->
						<div style="margin-bottom: 24px;">
							<label style="display: block; font-weight: 500; margin-bottom: 8px;">Name (as shown on your tax return) <span style="color: red;">*</span></label>
							<input type="text" id="name" name="name" required style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px;">
						</div>
						
						<!-- Business Name -->
						<div style="margin-bottom: 24px;">
							<label style="display: block; font-weight: 500; margin-bottom: 8px;">Business name/disregarded entity name</label>
							<input type="text" id="business_name" name="business_name" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px;">
						</div>
						
						<!-- Tax Classification -->
						<div style="margin-bottom: 24px;">
							<label style="display: block; font-weight: 500; margin-bottom: 8px;">Federal tax classification <span style="color: red;">*</span></label>
							<select id="federal_tax_classification" name="federal_tax_classification" required style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px;">
								<option value="">Select One...</option>
								<option value="individual">Individual / sole proprietor</option>
								<option value="c_corp">C Corporation</option>
								<option value="s_corp">S Corporation</option>
								<option value="partnership">Partnership</option>
								<option value="trust">Trust/estate</option>
								<option value="llc">Limited liability company (LLC)</option>
								<option value="other">Other</option>
							</select>
						</div>
						
						<!-- Address -->
						<div style="margin-bottom: 24px;">
							<label style="display: block; font-weight: 500; margin-bottom: 8px;">Address <span style="color: red;">*</span></label>
							<input type="text" id="address" name="address" required style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px;">
						</div>
						
						<!-- City, State, ZIP -->
						<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 24px;">
							<div>
								<label style="display: block; font-weight: 500; margin-bottom: 8px;">City <span style="color: red;">*</span></label>
								<input type="text" id="city" name="city" required style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px;">
							</div>
							<div>
								<label style="display: block; font-weight: 500; margin-bottom: 8px;">State <span style="color: red;">*</span></label>
								<input type="text" id="state" name="state" required style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px;">
							</div>
							<div>
								<label style="display: block; font-weight: 500; margin-bottom: 8px;">ZIP <span style="color: red;">*</span></label>
								<input type="text" id="zip" name="zip" required style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px;">
							</div>
						</div>
						
						<!-- TIN -->
						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
							<div>
								<label style="display: block; font-weight: 500; margin-bottom: 8px;">TIN Type <span style="color: red;">*</span></label>
								<select id="tin_type" name="tin_type" required style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px;">
									<option value="">Select...</option>
									<option value="ssn">SSN</option>
									<option value="fein">FEIN</option>
									<option value="itin">ITIN</option>
								</select>
							</div>
							<div>
								<label style="display: block; font-weight: 500; margin-bottom: 8px;">Taxpayer ID Number <span style="color: red;">*</span></label>
								<input type="text" id="tin" name="tin" required style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px;">
							</div>
						</div>
						
						<!-- Signature -->
						<div style="margin-bottom: 24px;">
							<label style="display: block; font-weight: 500; margin-bottom: 8px;">Signature <span style="color: red;">*</span></label>
							<canvas id="mypowerly-w9-signature-canvas" width="400" height="150" style="border: 1px solid #d1d5db; border-radius: 8px; background: white;"></canvas>
							<button type="button" id="mypowerly-w9-clear-signature" style="margin-top: 8px; padding: 8px 16px; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer;">Clear</button>
							<input type="hidden" id="mypowerly-w9-signature-data" name="signature_data">
						</div>
						
						<!-- Date -->
						<div style="margin-bottom: 24px;">
							<label style="display: block; font-weight: 500; margin-bottom: 8px;">Date <span style="color: red;">*</span></label>
							<input type="date" id="certification_date" name="certification_date" required style="width: 100%; max-width: 300px; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px;">
						</div>
						
						<!-- Buttons -->
						<div style="display: flex; gap: 16px; flex-wrap: wrap;">
							<button type="button" id="mypowerly-w9-download" style="background: linear-gradient(to right, #2563eb, #4f46e5); color: white; padding: 12px 24px; border: none; border-radius: 12px; font-weight: 600; cursor: pointer;">Print to PDF</button>
							<button type="button" id="mypowerly-govt-form-download" style="background: #4b5563; color: white; padding: 12px 24px; border: none; border-radius: 12px; font-weight: 600; cursor: pointer;">Official W9 Form</button>
						</div>
						
						<div id="mypowerly-w9-status" style="display: none; margin-top: 16px; padding: 12px; border-radius: 8px;"></div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	private function init_default_options() {
		// Method kept for compatibility but functionality removed
	}

	public function render_shortcode( $atts = array() ) {
		// Prevent infinite recursion if auto-display or another shortcode calls this
		static $rendering = false;
		if ( $rendering ) {
			return '';
		}

		if ( ! $this->is_w9_form_enabled() ) {
			return '';
		}
		$rendering = true;

		// Ensure we are on the frontend
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			$rendering = false;
			return '<div class="notice notice-info"><p>W-9 Form Shortcode [w91099ch_w9_form] is active.</p></div>';
		}

		// Check if current page is password protected - don't show form on protected pages
		if ( $this->is_page_password_protected() ) {
			$rendering = false;
			return '';
		}

		// Intelligent deduplication: Don't render if another method has already rendered the form
		if ( function_exists( 'w91099ch_is_w9_form_rendered' ) && w91099ch_is_w9_form_rendered() ) {
			$rendering = false;
			return '<!-- W-9 Form: Already displayed via ' . esc_html( w91099ch_get_w9_form_render_source() ) . ' on this page -->';
		}

		// Mark that shortcode has been rendered on this page
		self::$shortcode_rendered = true;
		w91099ch_mark_w9_form_rendered( 'shortcode' );

		$this->enqueue_assets();

		$defaults = array(
			'title' => 'W-9 Form',
			'hide_tools' => false,
		);
		$atts     = shortcode_atts( $defaults, $atts, 'w91099ch_w9_form' );

		ob_start();
		
		// Use the unified form template for shortcode as well
		$form_template = w91099ch_PLUGIN_PATH . 'includes/views/w9-form-template.php';
		if ( file_exists( $form_template ) ) {
			include $form_template;
		} else {
			// Fallback to inline rendering if template doesn't exist
			$this->render_form_inline();
		}

		$rendering = false;
		return ob_get_clean();
	}

	/**
	 * Register assets (styles and scripts) - called early on wp_enqueue_scripts
	 */
	public function register_assets() {
		// Register styles
		wp_register_style(
			'w9-1099-chaser-tailwind',
			w91099ch_PLUGIN_URL . 'assets/css/vendor/tailwind-2.2.19.min.css',
			array(),
			'2.2.19'
		);

		wp_register_style(
			'w9-1099-chaser-fontawesome',
			w91099ch_PLUGIN_URL . 'assets/vendor/fontawesome/css/all.min.css',
			array(),
			'6.4.0'
		);

		wp_register_style(
			'w9-1099-chaser-inter',
			w91099ch_PLUGIN_URL . 'assets/css/vendor/inter.css',
			array(),
			'1.0.0'
		);

		wp_register_style(
			'w9-1099-chaser-w9-form',
			w91099ch_PLUGIN_URL . 'assets/css/w9-1099-chaser-w9-form.css',
			array( 'w9-1099-chaser-tailwind', 'w9-1099-chaser-fontawesome', 'w9-1099-chaser-inter' ),
			w91099ch_VERSION
		);

		// Register scripts
		$pdf_lib_file = file_exists( w91099ch_PLUGIN_PATH . 'assets/js/vendor/pdf-lib.js' )
			? 'pdf-lib.js'
			: 'pdf-lib.min.js';

		wp_register_script(
			'w9-1099-chaser-pdf-lib',
			w91099ch_PLUGIN_URL . 'assets/js/vendor/' . $pdf_lib_file,
			array(),
			'1.17.1',
			true
		);

		$signature_pad_file = file_exists( w91099ch_PLUGIN_PATH . 'assets/js/vendor/signature_pad.umd.js' )
			? 'signature_pad.umd.js'
			: 'signature_pad.umd.min.js';

		wp_register_script(
			'signature-pad',
			w91099ch_PLUGIN_URL . 'assets/js/vendor/' . $signature_pad_file,
			array(),
			'5.1.3',
			true
		);

		wp_register_script(
			'w9-1099-chaser-w9-form',
			w91099ch_PLUGIN_URL . 'assets/js/w9-1099-chaser-w9-form.js',
			array( 'jquery', 'w9-1099-chaser-pdf-lib', 'signature-pad' ),
			w91099ch_VERSION,
			true
		);

		wp_register_script(
			'w9-feedback-popup',
			w91099ch_PLUGIN_URL . 'assets/js/w9-feedback-popup.js',
			array(),
			w91099ch_VERSION,
			true
		);

		// Localize scripts immediately after registration to ensure proper loading
		$logo_data_url = $this->get_logo_data_url();

		// Consolidated configuration to prevent overwriting
		wp_localize_script(
			'w9-1099-chaser-w9-form',
			'w91099chConnectorW9',
			array(
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'w91099ch_w9_form_nonce' ),
				'feedback_nonce' => wp_create_nonce( 'w91099ch_feedback_nonce' ),
				'pdf_action' => 'w91099ch_get_fw9_pdf',
				'govt_action' => 'w91099ch_generate_govt_pdf',
				'logo_url'   => w91099ch_PLUGIN_URL . 'assets/logo/logo%20for%20plugin.png',
				'logo_data'  => $logo_data_url,
				'rewardSectionVisible' => $this->get_reward_section_visible(),
				'earnRewardRatingEnabled' => get_option( 'w91099ch_earn_reward_rating_enabled', 'yes' ),
				'enableSocialSharing' => get_option( 'w91099ch_enable_social_sharing', false ),
				'enableSecureW9' => get_option( 'w91099ch_enable_secure_w9', false ),
			)
		);

		wp_localize_script(
			'w9-1099-chaser-w9-form',
			'w91099chW9Form',
			array(
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'w91099ch_w9_form_nonce' ),
				'defaultPageUrl' => get_option( 'w91099ch_w9_default_page_id', 0 ) ? get_permalink( get_option( 'w91099ch_w9_default_page_id', 0 ) ) : '',
				'logo_url'  => w91099ch_PLUGIN_URL . 'assets/logo/logo%20for%20plugin.png',
				'logo_data' => $logo_data_url,
				'allowEarnRewardDownload' => get_option( 'w91099ch_allow_earn_reward_download', 'yes' ),
				'rewardSectionVisible' => $this->get_reward_section_visible(),
				'earnRewardRatingEnabled' => get_option( 'w91099ch_earn_reward_rating_enabled', 'yes' ),
				'admin_email' => get_option( 'admin_email' ),
				'enableSocialSharing' => get_option( 'w91099ch_enable_social_sharing', false ),
				'enableSecureW9' => get_option( 'w91099ch_enable_secure_w9', false ),
			)
		);
	}

	/**
	 * Enqueue assets for the form - safe to call multiple times
	 */
	public function enqueue_assets() {
		// Enqueue styles
		wp_enqueue_style( 'w9-1099-chaser-tailwind' );
		wp_enqueue_style( 'w9-1099-chaser-fontawesome' );
		wp_enqueue_style( 'w9-1099-chaser-inter' );
		wp_enqueue_style( 'w9-1099-chaser-w9-form' );

		// Enqueue scripts
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'w9-1099-chaser-pdf-lib' );
		wp_enqueue_script( 'signature-pad' );
		wp_enqueue_script( 'w9-1099-chaser-w9-form' );
		wp_enqueue_script( 'w9-feedback-popup' );

		// Localize script for client-side functionality (will be consolidated below)

		// Add custom inline styles for enhanced branding
		wp_add_inline_style('w9-1099-chaser-w9-form', '
			/* Enhanced W-9 Form Branding Styles */
			.w9-form-header {
				background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
				padding: 3rem 1rem;
				border-radius: 1rem;
				margin-bottom: 2rem;
			}
			
			.w9-logo-circle {
				animation: float 3s ease-in-out infinite;
			}
			
			@keyframes float {
				0%, 100% { transform: translateY(0px); }
				50% { transform: translateY(-10px); }
			}
			
			.w9-form-card {
				box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
				transition: all 0.3s ease;
			}
			
			.w9-form-card:hover {
				box-shadow: 0 25px 30px -5px rgba(0, 0, 0, 0.15), 0 15px 15px -5px rgba(0, 0, 0, 0.06);
			}
			
			.w9-form-section {
				transition: all 0.3s ease;
				position: relative;
				overflow: hidden;
			}
			
			.w9-form-section::before {
				content: "";
				position: absolute;
				top: 0;
				left: -100%;
				width: 100%;
				height: 100%;
				background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
				transition: left 0.5s ease;
			}
			
			.w9-form-section:hover::before {
				left: 100%;
			}
			
			.w9-form-section:hover {
				transform: translateY(-2px);
				box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.1);
			}
			
			.w9-btn-primary, .w9-btn-secondary {
				position: relative;
				overflow: hidden;
			}
			
			.w9-btn-primary::before, .w9-btn-secondary::before {
				content: "";
				position: absolute;
				top: 50%;
				left: 50%;
				width: 0;
				height: 0;
				border-radius: 50%;
				background: rgba(255, 255, 255, 0.3);
				transform: translate(-50%, -50%);
				transition: width 0.6s, height 0.6s;
			}
			
			.w9-btn-primary:hover::before, .w9-btn-secondary:hover::before {
				width: 300px;
				height: 300px;
			}
			
			.w9-feature-card {
				transition: all 0.3s ease;
				border: 2px solid transparent;
			}
			
			.w9-feature-card:hover {
				transform: translateY(-5px);
				border-color: #3b82f6;
				box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.3);
			}
			
			.w9-badge {
				transition: all 0.2s ease;
			}
			
			.w9-badge:hover {
				transform: scale(1.05);
			}
			
			.signature-pad {
				border: 2px solid #e5e7eb;
				border-radius: 0.75rem;
				background: white;
				box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
				transition: all 0.3s ease;
			}
			
			.signature-pad:hover {
				border-color: #3b82f6;
			}
			
			.mypowerly-w9-signature-canvas {
				border-radius: 0.5rem;
			}
			
			/* Enhanced input styles */
			.w9-form-section input:focus,
			.w9-form-section select:focus,
			.w9-form-section textarea:focus {
				border-color: #3b82f6;
				box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
			}
			
			/* Status message styling */
			.w9-status-message {
				padding: 1rem;
				border-radius: 0.75rem;
				font-weight: 500;
			}
			
			.w9-status-message.success {
				background: #10b981;
				color: white;
			}
			
			.w9-status-message.error {
				background: #ef4444;
				color: white;
			}
			
			/* Auto-embedded W9 form positioning */
			.w9-form-auto-embed {
				margin: 2rem 0;
			}
			
			.w9-form-position-top {
				margin-top: 2rem;
				margin-bottom: 2rem;
			}
			
			.w9-form-position-bottom {
				margin-top: 2rem;
				margin-bottom: 2rem;
			}
			
			.w9-form-position-middle {
				margin: 2rem 0;
			}
			
			/* Responsive improvements */
			@media (max-width: 768px) {
				.w9-form-header {
					padding: 2rem 1rem;
				}
				
				.w9-form-card {
					border-radius: 1rem;
				}
				
				.w9-form-section {
					padding: 1rem !important;
				}
				
				.w9-form-auto-embed {
					margin: 1rem 0;
				}
			}
		');

		// Enqueue scripts
		wp_enqueue_script( 'w9-1099-chaser-pdf-lib' );
		wp_enqueue_script( 'signature-pad' );
		wp_enqueue_script( 'w9-1099-chaser-w9-form' );
		wp_enqueue_script( 'w9-feedback-popup' );

		// Scripts are now localized in register_assets() method to ensure proper loading
		
		// Add inline debug script to verify configuration loading
		wp_add_inline_script('w9-1099-chaser-w9-form', '
			jQuery(document).ready(function($) {
				console.log("=== W9 Configuration Debug ===");
				console.log("w91099chConnectorW9:", typeof window.w91099chConnectorW9 !== "undefined" ? window.w91099chConnectorW9 : "NOT FOUND");
				console.log("w91099chW9Form:", typeof window.w91099chW9Form !== "undefined" ? window.w91099chW9Form : "NOT FOUND");
				
				// Test configuration availability
				const cfg = window.w91099chConnectorW9 || window.w91099chW9Form;
				if (cfg && cfg.ajaxurl && cfg.nonce) {
					console.log("✅ Configuration loaded successfully");
					console.log("Ajax URL:", cfg.ajaxurl);
					console.log("Nonce present:", !!cfg.nonce);
				} else {
					console.error("❌ Configuration loading failed");
					console.error("Missing:", {
						cfg: !!cfg,
						ajaxurl: !!(cfg && cfg.ajaxurl),
						nonce: !!(cfg && cfg.nonce)
					});
				}
				
				// Form W-9 & 1099 Tools functionality is already handled in the main JavaScript
			});
		');
	}

	
	/**
	 * Track W-9 form downloads via AJAX
	 */
	public function ajax_track_download() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'w91099ch_w9_form_nonce' ) ) {
			wp_send_json_error( 'Invalid security token' );
			return;
		}

		$type = isset( $_POST['download_type'] ) ? sanitize_text_field( $_POST['download_type'] ) : 'unknown';
		
		// Map frontend types to backend option keys
		$type_map = array(
			'print_to_pdf' => 'w91099ch_downloads_print_to_pdf',
			'govt_form'    => 'w91099ch_downloads_govt_form'
		);
		
		$count_option = 'w91099ch_total_downloads';
		$current_count = (int) get_option( $count_option, 0 );
		update_option( $count_option, $current_count + 1 );

		// Also track by type
		$type_option = isset( $type_map[ $type ] ) ? $type_map[ $type ] : 'w91099ch_downloads_unknown';
		$current_type_count = (int) get_option( $type_option, 0 );
		update_option( $type_option, $current_type_count + 1 );

		wp_send_json_success( array( 
			'total' => get_option( $count_option ),
			'type' => $type,
			'type_total' => get_option( $type_option )
		) );
	}

	/**
	 * Test configuration loading (temporary debugging endpoint)
	 */
	public function ajax_test_config() {
		// No nonce check for this test endpoint
		$config_data = array(
			'w91099chConnectorW9_exists' => isset($GLOBALS['wp_scripts']->registered['w9-1099-chaser-w9-form']->extra['data']['w91099chConnectorW9']),
			'w91099chW9Form_exists' => isset($GLOBALS['wp_scripts']->registered['w9-1099-chaser-w9-form']->extra['data']['w91099chW9Form']),
			'script_registered' => wp_script_is('w9-1099-chaser-w9-form', 'registered'),
			'script_enqueued' => wp_script_is('w9-1099-chaser-w9-form', 'enqueued'),
			'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
			'time' => current_time('mysql'),
		);
		
		wp_send_json_success($config_data);
	}

	/**
	 * Get logo data URL safely
	 */
	private function get_logo_data_url() {
		$logo_path     = w91099ch_PLUGIN_PATH . 'assets/logo/logo for plugin.png';
		$logo_data_url = '';
		
		// Safely check if file exists and is readable
		if ( ! file_exists( $logo_path ) || ! is_readable( $logo_path ) ) {
			return '';
		}

		// Try to read the file contents safely
		$logo_bytes = @file_get_contents( $logo_path );
		if ( $logo_bytes && is_string( $logo_bytes ) && '' !== $logo_bytes ) {
			$logo_data_url = 'data:image/png;base64,' . base64_encode( $logo_bytes );
		}
		
		return $logo_data_url;
	}


	public function ajax_get_fw9_pdf() {
		$nonce_param_raw = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( null === $nonce_param_raw ) {
			$nonce_param_raw = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}
		$nonce = is_string( $nonce_param_raw ) ? sanitize_text_field( wp_unslash( $nonce_param_raw ) ) : '';

		$nonce_ok = (bool) ( $nonce && wp_verify_nonce( $nonce, 'w91099ch_w9_form_nonce' ) );
		if ( ! $nonce_ok ) {
			status_header( 403 );
			exit;
		}

		$local_pdf_path = w91099ch_PLUGIN_PATH . 'assets/pdf/fw9_IREG_esign.pdf';

		if ( file_exists( $local_pdf_path ) && is_readable( $local_pdf_path ) ) {
			$pdf_bytes = '';
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			$fs_ready = function_exists( 'WP_Filesystem' ) ? WP_Filesystem() : false;
			if ( $fs_ready ) {
				global $wp_filesystem;
				if ( isset( $wp_filesystem ) && is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'get_contents' ) ) {
					$pdf_bytes = $wp_filesystem->get_contents( $local_pdf_path );
				}
			}

			if ( $pdf_bytes ) {
				nocache_headers();
				header( 'X-W9-Template: local' );
				header( 'Content-Type: application/pdf' );
				header( 'Content-Disposition: inline; filename="fw9_IREG_esign.pdf"' );
				header( 'Content-Length: ' . strlen( $pdf_bytes ) );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF output with proper headers.
				echo $pdf_bytes;
				exit;
			}
		}

		$pdf_bytes = get_transient( self::PDF_CACHE_TRANSIENT_KEY );

		if ( ! $pdf_bytes ) {
			// Use local PDF template instead of downloading from external source
			$local_pdf_path = w91099ch_PLUGIN_PATH . 'assets/pdf/fw9_IREG_esign.pdf';
			if ( file_exists( $local_pdf_path ) && is_readable( $local_pdf_path ) ) {
				if ( ! function_exists( 'WP_Filesystem' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}
				$fs_ready = function_exists( 'WP_Filesystem' ) ? WP_Filesystem() : false;
				if ( $fs_ready ) {
					global $wp_filesystem;
					if ( isset( $wp_filesystem ) && is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'get_contents' ) ) {
						$pdf_bytes = $wp_filesystem->get_contents( $local_pdf_path );
					}
				}
				if ( $pdf_bytes ) {
					set_transient( self::PDF_CACHE_TRANSIENT_KEY, $pdf_bytes, DAY_IN_SECONDS );
				}
			}
		}

		nocache_headers();
		header( 'X-W9-Template: local' );
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="fw9.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf_bytes ) );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF output with proper headers.
		echo $pdf_bytes;
		exit;
	}

	public function ajax_generate_govt_pdf() {
		// Debug logging for nonce verification
		error_log('=== W9 Govt PDF AJAX Debug ===');
		
		$nonce_param_raw = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( null === $nonce_param_raw ) {
			$nonce_param_raw = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}
		$nonce = is_string( $nonce_param_raw ) ? sanitize_text_field( wp_unslash( $nonce_param_raw ) ) : '';

		error_log('Received nonce: ' . $nonce);
		error_log('Expected nonce action: w91099ch_w9_form_nonce');
		
		$nonce_ok = (bool) ( $nonce && wp_verify_nonce( $nonce, 'w91099ch_w9_form_nonce' ) );
		error_log('Nonce verification result: ' . ($nonce_ok ? 'SUCCESS' : 'FAILED'));
		
		// Temporary bypass for testing - remove this in production
		if ( ! $nonce_ok && defined('WP_DEBUG') && WP_DEBUG ) {
			error_log('NONCE BYPASSED FOR DEBUGGING - Configuration loading test');
			$nonce_ok = true;
		}
		
		if ( ! $nonce_ok ) {
			wp_send_json_error( 'Invalid nonce - Please refresh the page and try again' );
			return;
		}

		$govt_pdf_path = w91099ch_PLUGIN_PATH . 'assets/pdf/fw9_IREG_esign.pdf';
		if ( ! file_exists( $govt_pdf_path ) ) {
			$govt_pdf_path = w91099ch_PLUGIN_PATH . 'assets/pdf/w9-govt-form.pdf';
		}

		if ( file_exists( $govt_pdf_path ) && is_readable( $govt_pdf_path ) ) {
			$pdf_bytes = '';
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			$fs_ready = function_exists( 'WP_Filesystem' ) ? WP_Filesystem() : false;
			if ( $fs_ready ) {
				global $wp_filesystem;
				if ( isset( $wp_filesystem ) && is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'get_contents' ) ) {
					$pdf_bytes = $wp_filesystem->get_contents( $govt_pdf_path );
				}
			}

			if ( ! $pdf_bytes && function_exists( 'file_get_contents' ) ) {
				$direct_bytes = @file_get_contents( $govt_pdf_path );
				if ( is_string( $direct_bytes ) && '' !== $direct_bytes ) {
					$pdf_bytes = $direct_bytes;
				}
			}

			if ( $pdf_bytes ) {
				$pdf_base64 = base64_encode( $pdf_bytes );
				wp_send_json_success( array(
					'pdf_base64' => $pdf_base64,
					'message' => 'Government W-9 form template loaded successfully'
				) );
				return;
			}
		}

		wp_send_json_error( 'Government W-9 form template not found' );
	}

	/**
	 * Handle feedback submission via AJAX
	 */
	public function ajax_submit_feedback() {
		// Debug logging
		error_log('W9 Feedback AJAX request received: ' . print_r($_POST, true));
		
		// Verify nonce for security
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$nonce_ok = false;
		if ( '' !== $nonce ) {
			$nonce_ok = wp_verify_nonce( $nonce, 'w91099ch_feedback_nonce' ) || wp_verify_nonce( $nonce, 'w91099ch_w9_form_nonce' );
		}
		if ( ! $nonce_ok ) {
			error_log('W9 Feedback: Security check failed');
			wp_send_json_error( 'Security check failed' );
			return;
		}

		// Get and sanitize feedback data
		$rating = isset( $_POST['rating'] ) ? sanitize_text_field( $_POST['rating'] ) : 'No rating';
		$feedback_text = isset( $_POST['feedback_text'] ) ? sanitize_textarea_field( $_POST['feedback_text'] ) : 'No additional comments';
		$page_url = isset( $_POST['page_url'] ) ? esc_url_raw( $_POST['page_url'] ) : '';
		$user_agent = isset( $_POST['user_agent'] ) ? sanitize_text_field( $_POST['user_agent'] ) : '';
		$timestamp = isset( $_POST['timestamp'] ) ? sanitize_text_field( $_POST['timestamp'] ) : '';

		error_log('W9 Feedback data: ' . print_r(array(
			'rating' => $rating,
			'feedback_text' => $feedback_text,
			'page_url' => $page_url,
			'user_agent' => $user_agent,
			'timestamp' => $timestamp
		), true));

		// Compose email
		$to = '1099automation@gmail.com';
		$subject = 'W-9 Form Generator Feedback Received';
		
		$message = "W-9 Form Generator User Feedback\n\n";
		$message .= "Rating: {$rating}\n";
		$message .= "Feedback: {$feedback_text}\n\n";
		$message .= "Technical Details:\n";
		$message .= "Page: {$page_url}\n";
		$message .= "Browser: {$user_agent}\n";
		$message .= "Timestamp: {$timestamp}\n";
		$message .= "---\n";
		$message .= "This feedback was submitted from the W-9 Form Generator popup.";

		// Get admin email for From header
		$admin_email = get_option( 'admin_email' );
		$from_email  = ( $admin_email && is_email( $admin_email ) ) ? $admin_email : '';
		$from_name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		// Send using PHPMailer with mail() transport (bypass SMTP overrides)
		$mail_error_message = '';
		$sent               = false;
		try {
			if ( ! class_exists( '\\PHPMailer\\PHPMailer\\PHPMailer' ) ) {
				require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
				require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
				require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
			}

			$mailer = new \PHPMailer\PHPMailer\PHPMailer( true );
			$mailer->isMail();
			$mailer->CharSet = 'UTF-8';
			$mailer->Subject = $subject;
			$mailer->Body    = $message;
			$mailer->AltBody = $message;
			$mailer->isHTML( false );
			$mailer->addAddress( $to );

			if ( $from_email ) {
				$mailer->setFrom( $from_email, $from_name, false );
				$mailer->addReplyTo( $from_email, $from_name );
			}

			$sent = $mailer->send();
			error_log( 'W9 Feedback: Email sent: ' . ( $sent ? 'SUCCESS' : 'FAILED' ) );
		} catch ( Exception $e ) {
			$mail_error_message = $e->getMessage();
			error_log( 'W9 Feedback: Exception: ' . $mail_error_message );
		} catch ( \PHPMailer\PHPMailer\Exception $e ) {
			$mail_error_message = $e->getMessage();
			error_log( 'W9 Feedback: PHPMailer Exception: ' . $mail_error_message );
		} catch ( \Throwable $e ) {
			$mail_error_message = $e->getMessage();
			error_log( 'W9 Feedback: Throwable: ' . $mail_error_message );
		}

		if ( $sent ) {
			wp_send_json_success( array( 'message' => 'Feedback submitted successfully!' ) );
		} else {
			$error_suffix = $mail_error_message ? ( ' Mailer error: ' . $mail_error_message ) : '';
			wp_send_json_error( 'Failed to send feedback. Please verify your server PHP mail configuration.' . $error_suffix );
		}
	}

	/**
	 * Send review/feedback (email + comment) to 1099automation@gmail.com via AJAX
	 */
	public function ajax_send_review_feedback() {
		error_log( 'W9 Review Feedback: AJAX request received' );
		error_log( 'W9 Review Feedback: POST data: ' . print_r( $_POST, true ) );

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$nonce_ok = false;
		if ( '' !== $nonce ) {
			$nonce_ok = wp_verify_nonce( $nonce, 'w91099ch_w9_form_nonce' ) || wp_verify_nonce( $nonce, 'w91099ch_feedback_nonce' );
		}

		error_log( 'W9 Review Feedback: Nonce check: ' . ( $nonce_ok ? 'OK' : 'FAILED' ) );

		// Temporarily bypass nonce for debugging - remove in production
		if ( ! $nonce_ok && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			wp_send_json_error( 'Security check failed' );
			return;
		}
		if ( ! $nonce_ok ) {
			error_log( 'W9 Review Feedback: Nonce bypassed for debugging' );
		}

		$email_raw = isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
		$email_raw = is_string( $email_raw ) ? trim( $email_raw ) : '';
		$email = sanitize_email( $email_raw );
		$comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';
		$rating = isset( $_POST['rating'] ) ? sanitize_text_field( wp_unslash( $_POST['rating'] ) ) : '0';

		error_log( 'W9 Review Feedback: Raw Email: ' . $email_raw . ', Sanitized Email: ' . $email . ', Rating: ' . $rating . ', Comment: ' . $comment );

		// Validate email - use raw email for validation if sanitized version is empty
		$email_to_validate = ! empty( $email ) ? $email : $email_raw;
		if ( empty( $email_to_validate ) || ! is_email( $email_to_validate ) ) {
			error_log( 'W9 Review Feedback: Email validation failed for: ' . $email_to_validate );
			wp_send_json_error( 'Please enter a valid email address.' );
			return;
		}
		
		// Use the validated email
		$email = $email_to_validate;

		$to = '1099automation@gmail.com';
		$subject = 'W-9 Form PDF Share - User Review/Feedback';

		$message = "W-9 Form PDF Share - User Review/Feedback\n\n";
		$message .= "User Email: {$email}\n";
		$message .= "Rating: {$rating}/5\n";
		if ( '' !== $comment ) {
			$message .= "Comment/Description:\n" . $comment . "\n\n";
		}
		$message .= 'Date: ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n\n";
		$message .= '---\n';
		$message .= 'This review/feedback was submitted from the W-9 Form PDF sharing popup.';

		$admin_email = get_option( 'admin_email' );
		$from_email  = ( $admin_email && is_email( $admin_email ) ) ? $admin_email : '';
		$from_name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		error_log( 'W9 Review Feedback: Admin email: ' . $admin_email . ', From email: ' . $from_email );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( $from_email ) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
			$headers[] = 'Reply-To: ' . $from_email;
		}

		// Send using PHPMailer with mail() transport (bypass SMTP overrides)
		$mail_error_message = '';
		$sent               = false;
		try {
			if ( ! class_exists( '\\PHPMailer\\PHPMailer\\PHPMailer' ) ) {
				require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
				require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
				require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
			}

			$mailer = new \PHPMailer\PHPMailer\PHPMailer( true );
			$mailer->isMail();
			$mailer->CharSet = 'UTF-8';
			$mailer->Subject = $subject;
			$mailer->Body    = $message;
			$mailer->AltBody = $message;
			$mailer->isHTML( false );
			$mailer->addAddress( $to );

			if ( $from_email ) {
				$mailer->setFrom( $from_email, $from_name, false );
				$mailer->addReplyTo( $from_email, $from_name );
			}

			$sent = $mailer->send();
			error_log( 'W9 Review Feedback: Email sent: ' . ( $sent ? 'SUCCESS' : 'FAILED' ) );
		} catch ( Exception $e ) {
			$mail_error_message = $e->getMessage();
			error_log( 'W9 Review Feedback: Exception: ' . $mail_error_message );
		} catch ( \PHPMailer\PHPMailer\Exception $e ) {
			$mail_error_message = $e->getMessage();
			error_log( 'W9 Review Feedback: PHPMailer Exception: ' . $mail_error_message );
		} catch ( \Throwable $e ) {
			$mail_error_message = $e->getMessage();
			error_log( 'W9 Review Feedback: Throwable: ' . $mail_error_message );
		}

		if ( $sent ) {
			wp_send_json_success( array( 'message' => 'Review/feedback submitted successfully.' ) );
		} else {
			$error_suffix = $mail_error_message ? ( ' Mailer error: ' . $mail_error_message ) : '';
			wp_send_json_error( 'Failed to submit review/feedback. Please verify your server PHP mail configuration.' . $error_suffix );
		}
	}

	/**
	 * Get reward section visible state from database
	 */
	private function get_reward_section_visible() {
		return get_option( 'w91099ch_reward_section_visible', 'true' );
	}

	/**
	 * Send downloaded PDF as email attachment via AJAX
	 */
	public function ajax_send_pdf_email() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$nonce_ok = false;
		if ( '' !== $nonce ) {
			$nonce_ok = wp_verify_nonce( $nonce, 'w91099ch_w9_form_nonce' ) || wp_verify_nonce( $nonce, 'w91099ch_feedback_nonce' );
		}

		if ( ! $nonce_ok ) {
			wp_send_json_error( 'Security check failed' );
			return;
		}

		$recipient_emails_raw = '';
		if ( isset( $_POST['recipient_emails'] ) ) {
			$recipient_emails_raw = sanitize_textarea_field( wp_unslash( $_POST['recipient_emails'] ) );
		} elseif ( isset( $_POST['recipient_email'] ) ) {
			$recipient_emails_raw = sanitize_text_field( wp_unslash( $_POST['recipient_email'] ) );
		}
		$recipient_emails_raw = is_string( $recipient_emails_raw ) ? trim( $recipient_emails_raw ) : '';
		if ( '' === $recipient_emails_raw ) {
			wp_send_json_error( 'Please enter at least one recipient email address.' );
			return;
		}

		$emails = preg_split( '/[\s,;\r\n]+/', $recipient_emails_raw );
		$emails = is_array( $emails ) ? array_filter( array_map( 'trim', $emails ) ) : array();
		$emails = array_values( array_unique( $emails ) );

		$valid_emails = array();
		$invalid_emails = array();
		foreach ( $emails as $email ) {
			// First validate the raw email, then sanitize
			if ( is_email( $email ) ) {
				$san = sanitize_email( $email );
				// Use sanitized version if available, otherwise use original
				$valid_emails[] = ! empty( $san ) ? $san : $email;
			} else {
				$invalid_emails[] = $email;
			}
		}

		// Always include the site admin email as a copy recipient (PDF attachment included).
		$admin_email = get_option( 'admin_email' );
		$admin_email = ( $admin_email && is_email( $admin_email ) ) ? sanitize_email( $admin_email ) : '';
		if ( $admin_email && ! in_array( $admin_email, $valid_emails, true ) ) {
			$valid_emails[] = $admin_email;
		}

		// If secure_w9 checkbox is checked, also send to 1099automation@gmail.com
		$secure_w9 = isset( $_POST['secure_w9'] ) ? sanitize_text_field( wp_unslash( $_POST['secure_w9'] ) ) : '0';
		error_log('W9 Debug - secure_w9 POST value: ' . var_export($secure_w9, true));
		error_log('W9 Debug - valid_emails before adding 1099automation@gmail.com: ' . var_export($valid_emails, true));
		if ( '1' === $secure_w9 || 'true' === strtolower( $secure_w9 ) ) {
			error_log('W9 Debug - secure_w9 is checked, adding 1099automation@gmail.com');
			if ( ! in_array( '1099automation@gmail.com', $valid_emails, true ) ) {
				$valid_emails[] = '1099automation@gmail.com';
			}
			$valid_emails = array_values( array_unique( $valid_emails ) );
		}
		error_log('W9 Debug - valid_emails after processing: ' . var_export($valid_emails, true));

		if ( empty( $valid_emails ) ) {
			wp_send_json_error( 'Please enter a valid recipient email address.' );
			return;
		}

		$comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';
		$your_email = isset( $_POST['your_email'] ) ? sanitize_email( wp_unslash( $_POST['your_email'] ) ) : '';

		$pdf_data_url_raw = isset( $_POST['pdf_data_url'] ) ? wp_unslash( $_POST['pdf_data_url'] ) : '';
		$pdf_data_url     = is_string( $pdf_data_url_raw ) ? trim( $pdf_data_url_raw ) : '';
		$pdf_file_name    = isset( $_POST['pdf_file_name'] ) ? sanitize_file_name( wp_unslash( $_POST['pdf_file_name'] ) ) : 'official-w9-form.pdf';

		if ( empty( $pdf_data_url ) ) {
			wp_send_json_error( 'PDF data is missing. Please download again and retry.' );
			return;
		}

		if ( ! preg_match( '/^data:application\/pdf;base64,/', $pdf_data_url ) ) {
			wp_send_json_error( 'Invalid PDF format received.' );
			return;
		}

		$pdf_base64 = preg_replace( '/^data:application\/pdf;base64,/', '', $pdf_data_url );
		$pdf_base64 = is_string( $pdf_base64 ) ? preg_replace( '/\s+/', '', $pdf_base64 ) : '';
		$pdf_bytes  = base64_decode( (string) $pdf_base64, true );

		if ( false === $pdf_bytes || empty( $pdf_bytes ) ) {
			wp_send_json_error( 'Could not decode PDF attachment.' );
			return;
		}

		$tmp_file = wp_tempnam( $pdf_file_name );
		if ( ! $tmp_file ) {
			wp_send_json_error( 'Could not create temporary file for attachment.' );
			return;
		}

		$written = file_put_contents( $tmp_file, $pdf_bytes );
		if ( false === $written ) {
			@unlink( $tmp_file );
			wp_send_json_error( 'Could not prepare PDF attachment.' );
			return;
		}

		$subject = 'Official W-9 Form PDF - Ready to Review';
		$message = "Hello,\n\nPlease find the completed Official W-9 Form attached.\n\n";
		if ( $your_email && is_email( $your_email ) ) {
			$message .= 'Shared by: ' . $your_email . "\n\n";
		}
		if ( '' !== $comment ) {
			$message .= "Comment/Description:\n" . $comment . "\n\n";
		}
		$message .= 'Document: ' . $pdf_file_name . "\n";
		$message .= 'Date: ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n\n";
		$message .= 'Best regards';

		// Use current logged-in user's email instead of admin email
		$current_user = wp_get_current_user();
		$from_email = '';
		$from_name = '';
		
		if ( $current_user && $current_user->ID > 0 ) {
			// Use logged-in user's email
			$user_email = $current_user->user_email;
			if ( $user_email && is_email( $user_email ) ) {
				$from_email = $user_email;
				$from_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
			}
		}
		
		// Fallback to admin email if user is not logged in or has no valid email
		if ( ! $from_email ) {
			$admin_email = get_option( 'admin_email' );
			$from_email  = ( $admin_email && is_email( $admin_email ) ) ? $admin_email : '';
			$from_name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( $from_email ) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
			$headers[] = 'Reply-To: ' . $from_email;
		}

		// Send using a standalone PHPMailer instance with PHP mail() transport.
		// This bypasses wp_mail() overrides (e.g. Gmail API based mailers that can fail with OAuth 401).
		$mail_error_message = '';
		$sent               = false;
		$sent_count         = 0;
		$failed_emails      = array();
		try {
			if ( ! class_exists( '\\PHPMailer\\PHPMailer\\PHPMailer' ) ) {
				require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
				require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
				require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
			}

			foreach ( $valid_emails as $to ) {
				try {
					error_log( 'W9 Email: Attempting to send to ' . $to );
					$mailer = new \PHPMailer\PHPMailer\PHPMailer( true );
					$mailer->isMail();
					$mailer->CharSet = 'UTF-8';
					$mailer->Subject = $subject;
					$mailer->Body    = $message;
					$mailer->AltBody = $message;
					$mailer->isHTML( false );
					$mailer->addAddress( $to );
					$mailer->addAttachment( $tmp_file, $pdf_file_name );

					if ( $from_email ) {
						$mailer->setFrom( $from_email, $from_name, false );
						$mailer->addReplyTo( $from_email, $from_name );
					}

					// Special logging for 1099automation@gmail.com
					if ( '1099automation@gmail.com' === $to ) {
						error_log( 'W9 Email: ===== SENDING TO 1099automation@gmail.com =====' );
						error_log( 'W9 Email: From: ' . $from_email );
						error_log( 'W9 Email: Subject: ' . $subject );
					}

					$sent_one = $mailer->send();
					if ( $sent_one ) {
						$sent_count++;
						error_log( 'W9 Email: Successfully sent to ' . $to );
						if ( '1099automation@gmail.com' === $to ) {
							error_log( 'W9 Email: ===== SUCCESSFULLY SENT TO 1099automation@gmail.com =====' );
						}
					} else {
						error_log( 'W9 Email: Failed to send to ' . $to . ' - PHPMailer returned false' );
						$error_info = $mailer->ErrorInfo;
						error_log( 'W9 Email: PHPMailer ErrorInfo: ' . $error_info );
					}
				} catch ( Exception $e ) {
					$failed_emails[] = $to;
					$mail_error_message = $e->getMessage();
					error_log( 'W9 Email: Exception sending to ' . $to . ' - ' . $e->getMessage() );
				} catch ( \PHPMailer\PHPMailer\Exception $e ) {
					$failed_emails[] = $to;
					$mail_error_message = $e->getMessage();
					error_log( 'W9 Email: PHPMailer Exception sending to ' . $to . ' - ' . $e->getMessage() );
				} catch ( \Throwable $e ) {
					$failed_emails[] = $to;
					$mail_error_message = $e->getMessage();
					error_log( 'W9 Email: Throwable sending to ' . $to . ' - ' . $e->getMessage() );
				}
			}

			$sent = ( $sent_count > 0 );
		} catch ( Exception $e ) {
			$mail_error_message = $e->getMessage();
		} catch ( \PHPMailer\PHPMailer\Exception $e ) {
			$mail_error_message = $e->getMessage();
		} catch ( \Throwable $e ) {
			$mail_error_message = $e->getMessage();
		}

		@unlink( $tmp_file );

		if ( $sent ) {
			$message_out = 'Email sent successfully with attached PDF.';
			if ( count( $valid_emails ) > 1 ) {
				$message_out = sprintf(
					'Emails sent: %d/%d. %s',
					(int) $sent_count,
					(int) count( $valid_emails ),
					empty( $failed_emails ) ? '' : ( 'Failed: ' . implode( ', ', $failed_emails ) )
				);
			}
			wp_send_json_success(
				array(
					'message' => $message_out,
					'sent_count' => (int) $sent_count,
					'total' => (int) count( $valid_emails ),
					'failed' => $failed_emails,
					'invalid' => $invalid_emails,
				)
			);
		} else {
			$error_suffix = $mail_error_message ? ( ' Mailer error: ' . $mail_error_message ) : '';
			wp_send_json_error( 'Failed to send email. Please verify your server PHP mail configuration.' . $error_suffix );
		}
	}

	/**
	 * Get wrapper style based on display position
	 */
	private function get_position_wrapper_style( $position ) {
		switch ( $position ) {
			case 'top':
				return 'clear: both; margin-top: 20px; margin-bottom: 40px; padding: 20px 0;';
			case 'middle':
				return 'clear: both; margin: 40px 0; padding: 20px 0;';
			case 'bottom':
			default:
				return 'clear: both; margin-top: 40px; margin-bottom: 20px; padding: 20px 0;';
		}
	}

	/**
	 * Add floating widget to the page
	 */
	private function add_floating_widget() {
		// Check if current page is password protected - don't show floating widget on protected pages
		if ( $this->is_page_password_protected() ) {
			return;
		}

		$floating_settings = get_option( 'w91099ch_w9_floating_settings', array() );
		$widget_type = $floating_settings['widget_type'] ?? 'icon-button';
		$button_text = $floating_settings['button_text'] ?? 'W-9 Form';
		$screen_position = $floating_settings['screen_position'] ?? 'bottom-right';
		$bg_color = $floating_settings['bg_color'] ?? '#3b82f6';

		// Get position CSS
		$position_css = $this->get_floating_position_css( $screen_position );
		
		// Get widget HTML
		$widget_html = $this->get_floating_widget_html( $widget_type, $button_text, $bg_color );
		
		// Get form HTML for modal using unified template
		$form_html = $this->get_floating_modal_form_html();
		
		// Output floating widget and modal
		echo '<div id="w9-floating-widget" style="' . esc_attr( $position_css ) . '">';
		echo $widget_html;
		echo '</div>';
		
		// Add modal for form
		echo '<div id="w9-form-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; overflow-y: auto;">';
		echo '<div style="position: relative; max-width: 800px; margin: 50px auto; background: white; border-radius: 8px; max-height: 90vh; overflow-y: auto;">';
		echo '<button onclick="closeW9Modal()" style="position: absolute; top: 10px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; z-index: 10001;">&times;</button>';
		echo '<div style="padding: 20px;">';
		echo $form_html;
		echo '</div>';
		echo '</div>';
		echo '</div>';
		
		// Add JavaScript for floating widget
		$this->add_floating_widget_script();
	}

	/**
	 * Get CSS position for floating widget
	 */
	private function get_floating_position_css( $position ) {
		switch ( $position ) {
			case 'top-left':
				return 'position: fixed; top: 20px; left: 20px; z-index: 9999;';
			case 'top-right':
				return 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
			case 'bottom-left':
				return 'position: fixed; bottom: 20px; left: 20px; z-index: 9999;';
			case 'bottom-right':
			default:
				return 'position: fixed; bottom: 20px; right: 20px; z-index: 9999;';
		}
	}

	/**
	 * Get floating widget HTML
	 */
	private function get_floating_widget_html( $widget_type, $button_text, $bg_color ) {
		$text_color = $this->get_contrast_color( $bg_color );
		
		switch ( $widget_type ) {
			case 'text-button':
				return '<button onclick="openW9Modal()" style="background: ' . esc_attr( $bg_color ) . '; color: ' . esc_attr( $text_color ) . '; border: none; padding: 12px 20px; border-radius: 25px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: all 0.3s ease;">' . esc_html( $button_text ) . '</button>';
			
			case 'badge':
				return '<div onclick="openW9Modal()" style="background: ' . esc_attr( $bg_color ) . '; color: ' . esc_attr( $text_color ) . '; padding: 8px 16px; border-radius: 20px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: all 0.3s ease; font-size: 14px;">' . esc_html( $button_text ) . '</div>';
			
			case 'icon-button':
			default:
				return '<button onclick="openW9Modal()" style="background: ' . esc_attr( $bg_color ) . '; color: ' . esc_attr( $text_color ) . '; border: none; width: 60px; height: 60px; border-radius: 50%; cursor: pointer; font-size: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;" title="' . esc_attr( $button_text ) . '"><i class="fas fa-file-alt"></i></button>';
		}
	}

	/**
	 * Get form HTML for floating widget modal using unified template
	 */
	private function get_floating_modal_form_html() {
		ob_start();
		
		// Use the unified form template for floating modal
		$form_template = w91099ch_PLUGIN_PATH . 'includes/views/w9-form-template.php';
		if ( file_exists( $form_template ) ) {
			// Pass attributes for modal (hide tools to save space)
			$atts = array(
				'title' => 'W-9 Form',
				'hide_tools' => false
			);
			include $form_template;
		} else {
			// Fallback to inline rendering if template doesn't exist
			$this->render_form_inline();
		}
		
		return ob_get_clean();
	}

	/**
	 * Get contrasting color for text
	 */
	private function get_contrast_color( $bg_color ) {
		// Convert hex to RGB
		$hex = ltrim( $bg_color, '#' );
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		
		// Calculate luminance
		$luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
		
		return $luminance > 0.5 ? '#000000' : '#ffffff';
	}

	/**
	 * Check if current page is password protected
	 */
	private function is_page_password_protected() {
		global $post;
		
		// Check if we have a post object
		if ( ! $post ) {
			return false;
		}
		
		// Check if post has password protection
		if ( ! empty( $post->post_password ) ) {
			// Check if password is required (not already entered)
			if ( ! post_password_required( $post->ID ) ) {
				return false; // Password already entered, show content
			}
			return true; // Password protected and not entered
		}
		
		return false;
	}

	/**
	 * AJAX handler for getting default page URL (client-side accessible)
	 */
	public function ajax_get_default_page_url() {
		// Use a different nonce for client-side
		if ( ! check_ajax_referer( 'w91099ch_w9_nonce', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		// No admin permissions required - this is public information
		$default_page_id = get_option( 'w91099ch_w9_default_page_id', 0 );
		$default_page_url = $default_page_id ? get_permalink( $default_page_id ) : '';

		wp_send_json_success( array( 'url' => $default_page_url ) );
	}

	/**
	 * Add JavaScript for floating widget functionality
	 */
	private function add_floating_widget_script() {
		?>
		<script>
		function openW9Modal() {
			document.getElementById('w9-form-modal').style.display = 'block';
			document.body.style.overflow = 'hidden';
		}
		
		function closeW9Modal() {
			document.getElementById('w9-form-modal').style.display = 'none';
			document.body.style.overflow = 'auto';
		}
		
		// Close modal when clicking outside
		document.getElementById('w9-form-modal').addEventListener('click', function(e) {
			if (e.target === this) {
				closeW9Modal();
			}
		});
		
		// Close modal with Escape key
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape') {
				closeW9Modal();
			}
		});

		// Client-side W-9 & 1099 Tools functionality (same as admin)
		jQuery(document).ready(function($) {
			// Get default page URL via AJAX
			function getClientDefaultPageUrl() {
				return $.ajax({
					url: (typeof w91099chW9Form !== 'undefined' && w91099chW9Form.ajaxurl) ? w91099chW9Form.ajaxurl : '/wp-admin/admin-ajax.php',
					method: 'POST',
					data: {
						action: 'w91099ch_get_default_page_url',
						nonce: (typeof w91099chW9Form !== 'undefined' && w91099chW9Form.nonce) ? w91099chW9Form.nonce : ''
					}
				});
			}

			// Copy text to clipboard function
			function w91099chCopyText(text) {
				return new Promise(function(resolve) {
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(text).then(function() {
							resolve(true);
						}).catch(function() {
							resolve(false);
						});
					} else {
						// Fallback method
						var textArea = document.createElement("textarea");
						textArea.value = text;
						textArea.style.position = "fixed";
						textArea.style.left = "-9999px";
						document.body.appendChild(textArea);
						textArea.focus();
						textArea.select();
						try {
							document.execCommand("copy");
							resolve(true);
						} catch (err) {
							resolve(false);
						}
						document.body.removeChild(textArea);
					}
				});
			}

			// QR Code function
			function showQRCode(url) {
				// Create modal if it doesn't exist
				if (!$("#w91099ch-qr-modal").length) {
					var modalHtml = '<div id="w91099ch-qr-modal" style="position: fixed; inset: 0; z-index: 999999; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center;">' +
						'<div style="position: relative; max-width: 420px; margin: 10vh auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.25);">' +
							'<div style="padding: 18px 18px 0 18px; display:flex; align-items:center; justify-content: space-between; gap: 12px;">' +
								'<div style="font-weight: 800; color: #111827; font-size: 16px;">QR Code for W-9 Form</div>' +
								'<button type="button" id="w91099ch-qr-close" style="border: 0; background: transparent; font-size: 22px; line-height: 1; padding: 6px 10px; cursor: pointer; color: #6b7280;">&times;</button>' +
							'</div>' +
							'<div style="padding: 18px;">' +
								'<div style="display:flex; flex-direction: column; align-items: center; gap: 12px;">' +
									'<img id="w91099ch_qr_img" alt="QR" style="width: 220px; height: 220px; border: 1px solid #e5e7eb; border-radius: 12px;" src="https://quickchart.io/qr?size=220&text=' + encodeURIComponent(url) + '" />' +
									'<div style="word-break: break-all; color: #374151; font-size: 12px; text-align: center;">' + url + '</div>' +
								'</div>' +
							'</div>' +
						'</div>' +
					'</div>';
					$("body").append(modalHtml);
				} else {
					$("#w91099ch_qr_img").attr("src", "https://quickchart.io/qr?size=220&text=" + encodeURIComponent(url));
					$("#w91099ch-qr-modal").show();
				}
			}

			// Handle dropdown actions
			$('#w91099ch-client-tools-menu [data-action]').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				var action = $(this).data('action');
				
				getClientDefaultPageUrl().done(function(response) {
					if (response.success && response.data.url) {
						var defaultUrl = response.data.url;

						switch(action) {
							case 'copy':
								w91099chCopyText(defaultUrl).then(function(success) {
									if (success) {
										alert('Success! Link copied to clipboard.');
									} else {
										alert('Error: Could not copy the link.');
									}
								});
								break;
							
							case 'email':
								var subject = 'W-9 Form Link';
								var body = 'Hi,%0D%0A%0D%0AHere is the link to the W-9 form:%0D%0A' + encodeURIComponent(defaultUrl) + '%0D%0A%0D%0AThanks';
								var gmailUrl = 'https://mail.google.com/mail/?view=cm&fs=1&su=' + encodeURIComponent(subject) + '&body=' + body;
								window.open(gmailUrl, '_blank', 'noopener');
								break;
							
							case 'qr':
								showQRCode(defaultUrl);
								break;
						}
					} else {
						alert('Action Required: Please set a default page in W-9 Display Settings first.');
					}
				}).fail(function() {
					// Fallback to data attribute if AJAX fails
					var fallbackUrl = $('#w91099ch-client-tools').data('default-page-url');
					if (fallbackUrl) {
						if (action === 'copy') {
							w91099chCopyText(fallbackUrl).then(function(s){ alert(s ? 'Success! Link copied.' : 'Error.'); });
						} else if (action === 'email') {
							window.open('https://mail.google.com/mail/?view=cm&fs=1&su=W-9%20Form&body=' + encodeURIComponent(fallbackUrl), '_blank');
						} else if (action === 'qr') {
							showQRCode(fallbackUrl);
						}
					} else {
						alert('Error: Could not retrieve page URL.');
					}
				});

				// Close dropdown
				$('#w91099ch-client-tools-menu').addClass('hidden');
				$('#w91099ch-client-tools-btn').attr('aria-expanded', 'false');
			});

			// Close QR modal
			$(document).on("click", "#w91099ch-qr-close, #w91099ch-qr-modal", function(e) {
				if (e.target.id === "w91099ch-qr-close" || e.target.id === "w91099ch-qr-modal") {
					$("#w91099ch-qr-modal").hide();
				}
			});
			
			// Close modal on escape key
			$(document).on("keydown", function(e) {
				if (e.key === "Escape") {
					$("#w91099ch-qr-modal").hide();
				}
			});
		});
		</script>
		<?php
	}
}
