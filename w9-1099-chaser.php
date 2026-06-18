<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name: Vendor Onboarding W9-1099 Chaser by Mypowerly
 * Description: Automate vendor onboarding - W-9 compliance & 1099 Electronic Filing (in minutes). Secure connection between WordPress and MyPowerly platform for W9 form generation, 1099 electronic filing, vendor onboarding, contractor compliance, and affiliate management.
 * Version: 1.0.14
 * Author: 1099automation
 * Plugin URI: https://wordpress.org/plugins/w9-1099-chaser
 * Text Domain: w9-1099-chaser
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Tags: W9, 1099, Affiliate, Tax compliance, Vendor Management Affiliate Tax, Onboarding, Contractor, Vendor, W-9 form, 1099 electronic filing, vendor onboarding, contractor compliance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase
define( 'w91099ch_VERSION', '1.0.14' );
define( 'w91099ch_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'w91099ch_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'w91099ch_PLUGIN_FILE', __FILE__ );
// phpcs:enable Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase

add_action(
	'plugins_loaded',
	function () {
		// load_plugin_textdomain removed; WP.org auto-loads translations for hosted plugins.
		return;
	}
);


// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed

function w91099ch_log( $message ) {
	// Logging disabled to prevent activation output
	return;
}

try {
	$required_files = [
		'includes/class-connector-core.php',
		'includes/class-api-handler.php',
		'includes/class-encryption-handler.php',
		'includes/class-database.php',
		'includes/class-affiliate-manager.php',
		'includes/class-webhook-dispatcher.php',
		'includes/class-my-powerly-card-rest.php',
		'includes/class-widget-manager.php',
		'includes/class-w9-form-shortcode.php',
		'includes/class-w9-form-block.php',
		'includes/class-mypowerly-widget-block.php',
		'includes/class-elementor-integration.php',
		'includes/class-aliases.php',
		'includes/class-update-checker.php',
		'includes/class-sync-modules-shortcode.php'
	];
	
	foreach ($required_files as $file) {
		$full_path = w91099ch_PLUGIN_PATH . $file;
		if (file_exists($full_path)) {
			require_once $full_path;
		}
	}
		
		// Include plugin detector init files (with safe loading)
		$detector_inits = [
			'includes/form-plugin-detector-init.php',
			'includes/contractor-plugin-detector-init.php',
			'includes/freelancer-contractor-plugin-detector-init.php',
			'includes/accounting-bookkeeping-plugin-detector-init.php',
			'includes/wallet-payout-plugin-detector-init.php',
			'includes/ecommerce-plugin-detector-init.php'
		];

		foreach ($detector_inits as $init_file) {
			$full_path = w91099ch_PLUGIN_PATH . $init_file;
			if (file_exists($full_path)) {
				require_once $full_path;
			}
		}
	
	// Include AJAX handlers for all plugin cards (with safe loading)
	$ajax_handlers = [
		'includes/form-plugin-ajax-handlers.php',
		'includes/contractor-plugin-ajax-handlers.php',
		'includes/freelancer-contractor-plugin-ajax-handlers.php',
		'includes/accounting-bookkeeping-plugin-ajax-handlers.php',
		'includes/wallet-payout-plugin-ajax-handlers.php',
		'includes/ecommerce-plugin-ajax-handlers.php'
	];

	foreach ($ajax_handlers as $handler_file) {
		$full_path = w91099ch_PLUGIN_PATH . $handler_file;
		if (file_exists($full_path)) {
			require_once $full_path;
		}
	}

	if ( function_exists( 'is_admin' ) && is_admin() ) {
		require_once w91099ch_PLUGIN_PATH . 'admin/class-admin.php';
	}
} catch ( Throwable $e ) {
	return;
}

// phpcs:ignore PEAR.NamingConventions.ValidClassName.StartWithCapital
class w91099ch_Plugin {

	private static $instance = null;
	public $core;
	public $admin;
	public $api;
	public $encryption;
	public $w9_form;
	public $w9_form_block;
	public $mypowerly_widget_block;
	public $widget_manager;
	public $my_powerly_card_rest;
	public $update_checker;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		try {
			$this->encryption = new w91099ch_Encryption_Handler();
			$this->api        = new w91099ch_API_Handler( $this->encryption );
			$this->core       = new w91099ch_Core( $this->api, $this->encryption );

			$this->admin = null;
			if ( function_exists( 'is_admin' ) && is_admin() && class_exists( 'w91099ch_Admin' ) ) {
				$this->admin = new w91099ch_Admin( $this->core );
			}
			$this->w9_form        = new w91099ch_W9_Form_Shortcode();
			$this->w9_form_block  = new w91099ch_W9_Form_Block();
			$this->mypowerly_widget_block = new w91099ch_MyPowerly_Widget_Block();
			$this->widget_manager = new w91099ch_Widget_Manager();
			if ( class_exists( 'w91099ch_Sync_Modules_Shortcode' ) ) {
				$sync_modules = new w91099ch_Sync_Modules_Shortcode();
				$sync_modules->init();
			}
			if ( class_exists( 'w91099ch_My_Powerly_Card_Rest_Controller' ) ) {
				$this->my_powerly_card_rest = new w91099ch_My_Powerly_Card_Rest_Controller( $this->core );
			}

			// Initialize update checker
			$this->update_checker = w91099ch_Update_Checker::get_instance();

			add_action( 'init', array( $this, 'early_intercept_credentials' ), 1 );
			add_action( 'init', array( $this, 'init' ) );
			register_activation_hook( __FILE__, array( $this, 'activate' ) );
			register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		} catch ( Throwable $e ) {
			return;
		}
	}

	public function early_intercept_credentials() {
		// Handle new External Connect API authorization code callback
		$authorization_code = filter_input( INPUT_GET, 'authorization_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$status             = filter_input( INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( $authorization_code && 'connected' === $status ) {
			if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
				set_transient( 'w91099ch_connection_error', 'Consent required. Please sign in as an administrator.', 300 );
				return;
			}

			// Delegate consent check to core
			if ( ! isset( $this->core ) || ! $this->core->has_admin_consent() ) {
				set_transient( 'w91099ch_connection_error', 'Consent required. Please accept the data handling notice before connecting.', 300 );
				return;
			}

			$signing_secret = filter_input( INPUT_GET, 'X-Powerly-Signature', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( is_string( $signing_secret ) && '' !== $signing_secret ) {
				update_option( 'w91099ch_powerly_signing_secret', $signing_secret );
			}

			try {
				$credentials = $this->api->exchange_authorization_code( $authorization_code );

				if ( $credentials ) {
					// Delegate storage to core
					$this->core->store_connection_data( $credentials );
					set_transient( 'w91099ch_connection_success', true, 300 );

					// If the user opted in to auto-sync, flag it for the next page load.
					if ( get_option( 'w91099ch_auto_sync_on_connect', false ) ) {
						set_transient( 'w91099ch_pending_auto_sync', true, 300 );
					}

					$redirect_url = admin_url( 'admin.php?page=w91099ch&status=success' );
					wp_safe_redirect( $redirect_url );
					exit;
				} else {
					set_transient( 'w91099ch_connection_error', 'Connection failed. Please retry.', 300 );
				}
			} catch ( Throwable $e ) {
				set_transient( 'w91099ch_connection_error', 'Connection failed. Please retry.', 300 );
			}
			return;
		}

		// Legacy encrypted credentials handling (fallback)
		$encrypted_param = filter_input( INPUT_GET, 'encrypted_credentials', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( null === $encrypted_param ) {
			return;
		}

		$nonce_param_raw = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$nonce_raw       = is_string( $nonce_param_raw ) ? sanitize_text_field( wp_unslash( $nonce_param_raw ) ) : '';
		if ( '' === $nonce_raw || ! wp_verify_nonce( $nonce_raw, 'w91099ch_credentials_callback' ) ) {
			set_transient( 'w91099ch_connection_error', 'Connection failed. Please retry.', 300 );
			return;
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			set_transient( 'w91099ch_connection_error', 'Consent required. Please sign in as an administrator and accept the data handling notice before connecting.', 300 );
			return;
		}

		if ( isset( $this->core ) && method_exists( $this->core, 'has_admin_consent' ) && ! $this->core->has_admin_consent() ) {
			set_transient( 'w91099ch_connection_error', 'Consent required. Please accept the data handling notice before connecting.', 300 );
			return;
		}

		try {
			$encrypted_credentials_json = is_string( $encrypted_param ) ? wp_unslash( $encrypted_param ) : '';

			$direct_parse = json_decode( $encrypted_credentials_json, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $direct_parse ) ) {
				$encrypted_credentials = $direct_parse;
			} else {
				$decoded_json = urldecode( $encrypted_credentials_json );

				$encrypted_credentials = json_decode( $decoded_json, true );

				if ( json_last_error() !== JSON_ERROR_NONE ) {
					$fixed_json            = stripslashes( $decoded_json );
					$encrypted_credentials = json_decode( $fixed_json, true );
				}
			}

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				set_transient( 'w91099ch_connection_error', 'Connection failed. Please retry.', 300 );
				return;
			}

			if ( ! $encrypted_credentials || ! is_array( $encrypted_credentials ) ) {
				set_transient( 'w91099ch_connection_error', 'Connection failed. Please retry.', 300 );
				return;
			}

			$required_fields = array( 'enc_key', 'ciphertext', 'iv' );
			foreach ( $required_fields as $field ) {
				if ( ! isset( $encrypted_credentials[ $field ] ) || ! is_string( $encrypted_credentials[ $field ] ) || '' === trim( $encrypted_credentials[ $field ] ) ) {
					set_transient( 'w91099ch_connection_error', 'Connection failed. Please retry.', 300 );
					return;
				}
			}

			if ( isset( $encrypted_credentials['enc_key'] ) ) {
				$encrypted_credentials['enc_key'] = str_replace( ' ', '+', $encrypted_credentials['enc_key'] );
			}
			if ( isset( $encrypted_credentials['ciphertext'] ) ) {
				$encrypted_credentials['ciphertext'] = str_replace( ' ', '+', $encrypted_credentials['ciphertext'] );
			}
			if ( isset( $encrypted_credentials['iv'] ) ) {
				$encrypted_credentials['iv'] = str_replace( ' ', '+', $encrypted_credentials['iv'] );
			}

			$required_fields = array( 'enc_key', 'ciphertext', 'iv' );
			$missing_fields  = array_diff( $required_fields, array_keys( $encrypted_credentials ) );

			if ( ! empty( $missing_fields ) ) {
				set_transient( 'w91099ch_connection_error', 'Connection failed. Please retry.', 300 );
				return;
			}

			$credentials = $this->core->process_encrypted_credentials( $encrypted_credentials );

			if ( $credentials ) {
				$this->core->store_connection_data( $credentials );
				set_transient( 'w91099ch_connection_success', true, 300 );

				// If the user opted in to auto-sync, flag it for the next page load.
				if ( get_option( 'w91099ch_auto_sync_on_connect', false ) ) {
					set_transient( 'w91099ch_pending_auto_sync', true, 300 );
				}

				$redirect_url = admin_url( 'admin.php?page=w91099ch&status=success' );
				wp_safe_redirect( $redirect_url );
				exit;
			} else {
				set_transient( 'w91099ch_connection_error', 'Connection failed. Please retry.', 300 );
			}
		} catch ( Throwable $e ) {
			set_transient( 'w91099ch_connection_error', 'Connection failed. Please retry.', 300 );
		}
	}

	public function activate() {
		// Start output buffering to catch any unexpected output
		ob_start();
		
		try {
			w91099ch_Database::create_tables();
			update_option( 'w91099ch_enable_auto_sync', 0 );
			update_option( 'w91099ch_admin_consent', 0 );
			update_option( 'w91099ch_w9_form_enabled', 0 );
			update_option( 'w91099ch_enable_social_sharing', 0 );
			update_option( 'w91099ch_enable_secure_w9', 0 );
			update_option( 'w91099ch_reward_section_visible', 'false' );
			update_option( 'w91099ch_auto_sync_on_connect', false );
			
			// Set W-9 form to display everywhere by default
			update_option( 'w91099ch_w9_display_method', 'all' );
			update_option( 'w91099ch_w9_display_position', 'bottom' );
			update_option( 'w91099ch_w9_selected_pages', array() );
			update_option( 'w91099ch_w9_page_positions', array() );
			update_option( 'w91099ch_w9_floating_settings', array() );
			
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( 'w91099ch_auto_sync_cron' );
			}
			flush_rewrite_rules();
		} catch ( Throwable $e ) {
			// Silent activation - don't output anything
		}
		
		// Clean any output that might have been generated
		$output = ob_get_clean();
		if ( $output ) {
			// Log the output for debugging but don't display it
			error_log( 'W9-1099-Chaser activation output: ' . $output );
		}
	}

	public function deactivate() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( 'w91099ch_daily_cleanup' );
			wp_clear_scheduled_hook( 'w91099ch_sync_affiliates_cron' );
			wp_clear_scheduled_hook( 'w91099ch_auto_sync_cron' );
		}
		flush_rewrite_rules();
	}

	public function init() {
		// Initialize each subsystem independently so one admin-side failure
		// does not stop Gutenberg block registration.
		if ( isset( $this->core ) && is_object( $this->core ) && method_exists( $this->core, 'init' ) ) {
			try {
				$this->core->init();
			} catch ( Throwable $e ) {
				// Keep plugin resilient.
			}
		}

		if ( $this->admin && is_object( $this->admin ) && method_exists( $this->admin, 'init' ) ) {
			try {
				$this->admin->init();
			} catch ( Throwable $e ) {
				// Keep plugin resilient.
			}
		}

		if ( isset( $this->w9_form ) && is_object( $this->w9_form ) && method_exists( $this->w9_form, 'init' ) ) {
			try {
				$this->w9_form->init();
			} catch ( Throwable $e ) {
				// Keep plugin resilient.
			}
		}

		if ( isset( $this->w9_form_block ) && is_object( $this->w9_form_block ) && method_exists( $this->w9_form_block, 'init' ) ) {
			try {
				$this->w9_form_block->init();
			} catch ( Throwable $e ) {
				// Keep plugin resilient.
			}
		}

		if ( isset( $this->mypowerly_widget_block ) && is_object( $this->mypowerly_widget_block ) && method_exists( $this->mypowerly_widget_block, 'init' ) ) {
			try {
				$this->mypowerly_widget_block->init();
			} catch ( Throwable $e ) {
				// Keep plugin resilient.
			}
		}

		if ( isset( $this->widget_manager ) && is_object( $this->widget_manager ) && method_exists( $this->widget_manager, 'init' ) ) {
			try {
				$this->widget_manager->init();
			} catch ( Throwable $e ) {
				// Keep plugin resilient.
			}
		}

		if ( isset( $this->core ) && is_object( $this->core ) && method_exists( $this->core, 'add_query_vars' ) ) {
			add_filter( 'query_vars', array( $this->core, 'add_query_vars' ) );
		}
		
		// Set default display method for existing installations (if not already set)
		if ( get_option( 'w91099ch_w9_display_method' ) === false ) {
			update_option( 'w91099ch_w9_display_method', 'all' );
			update_option( 'w91099ch_w9_display_position', 'bottom' );
			update_option( 'w91099ch_w9_selected_pages', array() );
			update_option( 'w91099ch_w9_page_positions', array() );
			update_option( 'w91099ch_w9_floating_settings', array() );
		}
	}
}

try {
	// Silent initialization - no logging during activation
	
	function w91099ch() {
		return w91099ch_Plugin::get_instance();
	}

	function w9_1099_chaser_render_header_support( $status = 'disconnected' ) {
		if ( $status === 'disconnected' ) {
			echo '<button type="button" id="mp-hero-goto-connect" class="mp-btn-secondary" style="font-size: 14px;" onclick="(function(){var t=document.getElementById(\'mypowerly-connect-block\')||document.getElementById(\'connect-mypowerly-cta\');if(t){t.scrollIntoView({behavior:\'smooth\',block:\'center\'});setTimeout(function(){var b=document.getElementById(\'connect-mypowerly-cta\');if(b){b.classList.add(\'mp-connect-highlight\');setTimeout(function(){b.classList.remove(\'mp-connect-highlight\');},2000);}},600);}})();">Connect to Mypowerly</button>';
		} else {
			echo '<span class="support-status">Support Available</span>';
		}
	}

	w91099ch();
	// Silent completion
} catch ( Throwable $e ) {
	// Silent error handling
	return;
}

// phpcs:enable Universal.Files.SeparateFunctionsFromOO.Mixed
