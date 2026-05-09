<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.PHP.YodaConditions.NotYoda
// phpcs:ignore PEAR.NamingConventions.ValidClassName.StartWithCapital
class w91099ch_Core {

	private $api;
	private $encryption;
	private $database;
	private $affiliate_manager;
	private $admin;

	public function __construct( $api_handler, $encryption_handler ) {
		$this->api               = $api_handler;
		$this->encryption        = $encryption_handler;
		$this->database          = w91099ch_Database::get_instance();
		$this->affiliate_manager = new w91099ch_Affiliate_Manager();
	}

	private function log( $message ) {
		if ( function_exists( 'w91099ch_log' ) ) {
			w91099ch_log( $message );
		}
	}

	private function get_excluded_affiliate_ids() {
		$raw = get_option( 'w91099ch_excluded_affiliate_ids', array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$out = array();
		foreach ( $raw as $v ) {
			$id = sanitize_text_field( (string) $v );
			if ( '' === $id ) {
				continue;
			}
			$out[ $id ] = true;
		}

		return $out;
	}

	public function ajax_get_excluded_affiliates() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$excluded = $this->get_excluded_affiliate_ids();
		wp_send_json_success(
			array(
				'excluded_ids' => array_keys( $excluded ),
			)
		);
	}

	public function ajax_set_excluded_affiliates() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitized immediately below (supports array or JSON string).
		$raw = isset( $_POST['excluded_ids'] ) ? wp_unslash( $_POST['excluded_ids'] ) : array();
		if ( is_string( $raw ) ) {
			$raw = sanitize_text_field( $raw );
		} elseif ( is_array( $raw ) ) {
			$raw = array_map( 'sanitize_text_field', $raw );
		}
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				$raw = $decoded;
			} else {
				$raw = array();
			}
		}

		$normalized = array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $v ) {
				$id = sanitize_text_field( (string) $v );
				if ( '' === $id ) {
					continue;
				}
				$normalized[ $id ] = true;
			}
		}

		$stored = array_keys( $normalized );
		update_option( 'w91099ch_excluded_affiliate_ids', $stored );

		wp_send_json_success(
			array(
				'excluded_ids' => $stored,
			)
		);
	}

	public function ajax_validate_promo_code() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		wp_send_json_success(
			array(
				'valid' => false,
				'error' => esc_html__( 'Promo code validation is disabled.', 'w9-1099-chaser' ),
			)
		);
	}

	public function has_admin_consent() {
		return (bool) get_option( 'w91099ch_admin_consent', false );
	}

	private function enforce_admin_consent_or_fail() {
		if ( $this->has_admin_consent() ) {
			return;
		}

		status_header( 403 );
		wp_send_json_error( esc_html__( 'Consent required', 'w9-1099-chaser' ) );
	}

	private function scrub_prohibited_w9_fields( $value ) {
		$deny_keys = array(
			'tin',
			'taxpayer_identification_number',
			'taxpayer_id',
			'tax_id',
			'tax_id_number',
			'taxid',
			'ssn',
			'social_security_number',
			'social_security',
			'fein',
			'employer_identification_number',
			'employer_id_number',
			'fein_number',
		);

		$deny_lookup = array();
		foreach ( $deny_keys as $k ) {
			$deny_lookup[ strtolower( (string) $k ) ] = true;
		}

		$scrub = function ( $v ) use ( &$scrub, $deny_lookup ) {
			if ( ! is_array( $v ) ) {
				return $v;
			}

			$out = array();
			foreach ( $v as $k => $vv ) {
				$k_str = is_string( $k ) ? strtolower( $k ) : '';
				if ( '' !== $k_str && isset( $deny_lookup[ $k_str ] ) ) {
					continue;
				}
				$out[ $k ] = $scrub( $vv );
			}

			return $out;
		};

		return $scrub( $value );
	}

	private function dispatch_webhook_event( $event_type, $event_data = array(), $context = array() ) {
		if ( ! class_exists( 'w91099ch_Webhook_Dispatcher' ) ) {
			return array(
				'attempted' => 0,
				'sent'      => 0,
				'errors'    => array(
					array(
						'target' => 'system',
						'error'  => 'Webhook dispatcher not available',
					),
				),
			);
		}

		if ( ! is_array( $event_data ) ) {
			$event_data = array();
		}

		if ( ! is_array( $context ) ) {
			$context = array();
		}

		$safe_event_data = $this->scrub_prohibited_w9_fields( $event_data );

		try {
			$result = w91099ch_Webhook_Dispatcher::dispatch_event( $event_type, $safe_event_data, $context );
			if ( ! empty( $result['errors'] ) && is_array( $result['errors'] ) ) {
				$this->log( 'Webhook dispatch warnings for "' . sanitize_text_field( (string) $event_type ) . '": ' . wp_json_encode( $result['errors'] ) );
			}
			return $result;
		} catch ( Throwable $e ) {
			$this->log( 'Webhook dispatch error for "' . sanitize_text_field( (string) $event_type ) . '": ' . $e->getMessage() );
			return array(
				'attempted' => 0,
				'sent'      => 0,
				'errors'    => array(
					array(
						'target' => 'system',
						'error'  => sanitize_text_field( $e->getMessage() ),
					),
				),
			);
		}
	}

	private function dispatch_raw_webhook_event( $event_type, $event_data = array(), $context = array() ) {
		if ( ! class_exists( 'w91099ch_Webhook_Dispatcher' ) ) {
			return array(
				'attempted' => 0,
				'sent'      => 0,
				'errors'    => array(
					array(
						'target' => 'system',
						'error'  => 'Webhook dispatcher not available',
					),
				),
			);
		}

		if ( ! is_array( $event_data ) ) {
			$event_data = array();
		}
		if ( ! is_array( $context ) ) {
			$context = array();
		}

		$event_type = sanitize_key( (string) $event_type );
		if ( '' === $event_type ) {
			$event_type = 'wordpress_event';
		}

		$payload = array(
			'event_type'  => $event_type,
			'created'     => gmdate( 'Y-m-d' ),
			'timestamp'   => gmdate( 'c' ),
			'source'      => 'wordpress_w9_1099_chaser',
			'site_url'    => (string) get_site_url(),
			'site_name'   => (string) get_bloginfo( 'name' ),
			'admin_email' => sanitize_email( (string) get_option( 'admin_email', '' ) ),
		);

		$event_data = $this->scrub_prohibited_w9_fields( $event_data );
		foreach ( $event_data as $key => $value ) {
			$payload[ $key ] = $value;
		}

		if ( isset( $context['action'] ) && is_string( $context['action'] ) && '' !== trim( $context['action'] ) ) {
			$payload['context_action'] = sanitize_text_field( $context['action'] );
		} elseif ( ! empty( $context ) ) {
			$payload['context'] = $context;
		}

		return w91099ch_Webhook_Dispatcher::dispatch_raw_payload( $payload, $event_type );
	}

	private function build_card_payload( $event_type, $card_key = '', $extra = array(), $context_action = '' ) {
		$event_type = sanitize_key( (string) $event_type );
		if ( '' === $event_type ) {
			$event_type = 'wordpress_event';
		}

		if ( function_exists( 'w91099ch_build_full_webhook_payload' ) ) {
			$payload = w91099ch_build_full_webhook_payload( $event_type, array() );
		} else {
			$payload = array(
				'event_type'  => $event_type,
				'event_id'    => function_exists( 'wp_generate_uuid4' ) ? 'wp_' . wp_generate_uuid4() : 'wp_' . uniqid( '', true ),
				'created'     => gmdate( 'Y-m-d' ),
				'timestamp'   => gmdate( 'c' ),
				'source'      => 'wordpress_w9_1099_chaser',
				'site_url'    => (string) get_site_url(),
				'site_name'   => (string) get_bloginfo( 'name' ),
				'admin_email' => sanitize_email( (string) get_option( 'admin_email', '' ) ),
			);
		}

		$base_keys = array(
			'event_type',
			'event_id',
			'created',
			'timestamp',
			'source',
			'site_url',
			'site_name',
			'admin_email',
		);

		$filtered = array();
		foreach ( $base_keys as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$filtered[ $key ] = $payload[ $key ];
			}
		}

		if ( is_string( $card_key ) && '' !== $card_key && array_key_exists( $card_key, $payload ) ) {
			$card_payload = array(
				'data' => $payload[ $card_key ],
			);

			if ( is_array( $extra ) ) {
				foreach ( $extra as $key => $value ) {
					$card_payload[ $key ] = $value;
				}
			}

			$filtered[ $card_key ] = $card_payload;
			$card_payload_json = wp_json_encode( $card_payload );
			if ( ! is_string( $card_payload_json ) ) {
				$card_payload_json = '{}';
			}
			if ( strlen( $card_payload_json ) > 30000 ) {
				$card_payload_json = wp_json_encode(
					array(
						'truncated' => true,
						'reason'    => 'payload_too_large',
						'card_key'  => sanitize_key( (string) $card_key ),
					)
				);
			}
			$filtered['card_payload_full_json'] = $card_payload_json;

			$sheet_tab = $this->get_sheet_tab_from_card_key( $card_key );
			if ( '' !== $sheet_tab ) {
				$filtered['sheet_tab'] = $sheet_tab;
			}
			$filtered['card_key']  = sanitize_key( (string) $card_key );
			$filtered['sync_scope'] = 'card';

			$flat_card_data = $this->flatten_sheet_row_fields( $card_payload, sanitize_key( (string) $card_key ) . '_' );
			foreach ( $flat_card_data as $flat_key => $flat_value ) {
				$filtered[ $flat_key ] = $flat_value;
			}
		} elseif ( is_array( $extra ) ) {
			foreach ( $extra as $key => $value ) {
				$filtered[ $key ] = $value;
			}
		}

		if ( '' !== trim( (string) $context_action ) ) {
			$filtered['context_action'] = sanitize_text_field( (string) $context_action );
		}

		return $filtered;
	}

	private function flatten_sheet_row_fields( $data, $prefix = '' ) {
		$out = array();
		if ( ! is_array( $data ) ) {
			return $out;
		}

		foreach ( $data as $key => $value ) {
			$flat_key = sanitize_key( $prefix . (string) $key );
			if ( '' === $flat_key ) {
				continue;
			}

			if ( is_scalar( $value ) || null === $value ) {
				if ( null === $value ) {
					$out[ $flat_key ] = '';
				} elseif ( is_bool( $value ) ) {
					$out[ $flat_key ] = $value ? 'true' : 'false';
				} else {
					$out[ $flat_key ] = (string) $value;
				}
				continue;
			}

			$out[ $flat_key ] = wp_json_encode( $value );
		}

		return $out;
	}

	private function get_sheet_tab_from_card_key( $card_key ) {
		$key = sanitize_key( (string) $card_key );
		$map = array(
			'user_profile'    => 'profile',
			'plugin_data'     => 'plugins',
			'team'            => 'team',
			'forms_plugin'    => 'forms',
			'membership_data' => 'contractors',
			'freelancer_data' => 'freelancer_contractors',
			'accounting_data' => 'accounting_bookkeeping',
			'payout_data'     => 'wallet_payout',
			'affiliates_data' => 'affiliates',
			'w9_payee'        => 'w9_form_data',
			'w9_form_data'    => 'w9_form_data',
		);

		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}

	private function normalize_card_rows( $rows ) {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$is_sequential = ( array_values( $rows ) === $rows );
		$normalized    = array();

		if ( $is_sequential ) {
			foreach ( $rows as $row ) {
				if ( is_array( $row ) ) {
					$normalized[] = $row;
				} else {
					$normalized[] = array( 'value' => is_scalar( $row ) ? (string) $row : wp_json_encode( $row ) );
				}
			}
			return $normalized;
		}

		foreach ( $rows as $key => $row ) {
			if ( is_array( $row ) ) {
				if ( ! isset( $row['row_key'] ) ) {
					$row['row_key'] = sanitize_text_field( (string) $key );
				}
				$normalized[] = $row;
				continue;
			}

			$normalized[] = array(
				'row_key' => sanitize_text_field( (string) $key ),
				'value'   => is_scalar( $row ) ? (string) $row : wp_json_encode( $row ),
			);
		}

		return $normalized;
	}

	private function dispatch_card_rows_webhook( $event_type, $card_key, $rows, $summary = array(), $context_action = '', $card_payload_full = array() ) {
		$event_type        = sanitize_key( (string) $event_type );
		$card_key          = sanitize_key( (string) $card_key );
		$summary           = is_array( $summary ) ? $summary : array();
		$card_payload_full = is_array( $card_payload_full ) ? $card_payload_full : array();
		$rows              = $this->normalize_card_rows( $rows );

		$sheet_tab = $this->get_sheet_tab_from_card_key( $card_key );
		$attempted = 0;
		$sent      = 0;
		$errors    = array();

		$base_payload = array(
			'event_type'     => $event_type,
			'timestamp'      => gmdate( 'c' ),
			'site_url'       => (string) get_site_url(),
			'site_name'      => (string) get_bloginfo( 'name' ),
			'admin_email'    => sanitize_email( (string) get_option( 'admin_email', '' ) ),
			'sheet_tab'      => $sheet_tab,
			'tab'            => $sheet_tab,
			'sheet'          => $sheet_tab,
			'tab_name'       => $sheet_tab,
			'sheet_name'     => $sheet_tab,
			'worksheet'      => $sheet_tab,
			'target_tab'     => $sheet_tab,
			'card_key'       => $card_key,
			'context_action' => sanitize_text_field( (string) $context_action ),
		);

		$summary_flat = $this->flatten_sheet_row_fields( $summary, 'summary_' );
		$card_payload_full_json = wp_json_encode( $card_payload_full );
		if ( ! is_string( $card_payload_full_json ) ) {
			$card_payload_full_json = '{}';
		}
		if ( strlen( $card_payload_full_json ) > 30000 ) {
			$card_payload_full_json = wp_json_encode(
				array(
					'truncated' => true,
					'reason'    => 'payload_too_large',
					'card_key'  => $card_key,
				)
			);
		}

		if ( empty( $rows ) ) {
			$summary_payload = $base_payload;
			$summary_payload['sync_scope']        = 'summary';
			$summary_payload['card_payload_full_json'] = $card_payload_full_json;
			$summary_payload[ $card_key ] = array(
				'data'    => array(),
				'summary' => $summary,
			);
			foreach ( $summary_flat as $k => $v ) {
				$summary_payload[ $k ] = $v;
			}

			$result = w91099ch_Webhook_Dispatcher::dispatch_raw_payload( $summary_payload, $event_type );
			return array(
				'attempted' => isset( $result['attempted'] ) ? (int) $result['attempted'] : 0,
				'sent'      => isset( $result['sent'] ) ? (int) $result['sent'] : 0,
				'errors'    => isset( $result['errors'] ) && is_array( $result['errors'] ) ? $result['errors'] : array(),
			);
		}

		foreach ( $rows as $index => $row ) {
			$row_payload                     = $base_payload;
			$row_payload['sync_scope']       = 'row';
			$row_payload['row_index']        = (int) $index + 1;
			$row_payload['row_data_json']    = wp_json_encode( $row );
			$row_payload['card_payload_full_json'] = $card_payload_full_json;
			$row_payload[ $card_key ] = array(
				'data'      => $row,
				'summary'   => $summary,
				'row_index' => (int) $index + 1,
			);

			$row_flat = $this->flatten_sheet_row_fields( $row, 'row_' );
			foreach ( $row_flat as $k => $v ) {
				$row_payload[ $k ] = $v;
			}
			foreach ( $summary_flat as $k => $v ) {
				$row_payload[ $k ] = $v;
			}

			$result     = w91099ch_Webhook_Dispatcher::dispatch_raw_payload( $row_payload, $event_type );
			$attempted += isset( $result['attempted'] ) ? (int) $result['attempted'] : 0;
			$sent      += isset( $result['sent'] ) ? (int) $result['sent'] : 0;
			if ( isset( $result['errors'] ) && is_array( $result['errors'] ) && ! empty( $result['errors'] ) ) {
				$errors = array_merge( $errors, $result['errors'] );
			}
		}

		return array(
			'attempted' => $attempted,
			'sent'      => $sent,
			'errors'    => $errors,
		);
	}

	public function init() {
		try {
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( 'w91099ch_sync_affiliates_cron' );
			}

			add_action( 'wp_ajax_w91099ch_refresh_affiliate_plugins', array( $this, 'ajax_refresh_affiliate_plugins' ) );
			add_action( 'wp_ajax_w91099ch_get_plugin_affiliates', array( $this, 'ajax_get_plugin_affiliates' ) );
			add_action( 'wp_ajax_w91099ch_get_all_affiliates', array( $this, 'ajax_get_all_affiliates' ) );
			add_action( 'wp_ajax_w91099ch_initiate_connection', array( $this, 'ajax_initiate_connection' ) );
			add_action( 'wp_ajax_w91099ch_process_credentials', array( $this, 'ajax_process_credentials' ) );
			add_action( 'wp_ajax_w91099ch_disconnect', array( $this, 'ajax_disconnect' ) );
			add_action( 'wp_ajax_w91099ch_test_connection', array( $this, 'ajax_test_connection' ) );
			add_action( 'wp_ajax_w91099ch_validate_credentials', array( $this, 'ajax_validate_credentials' ) );
			add_action( 'wp_ajax_w91099ch_refresh_credentials', array( $this, 'ajax_refresh_credentials' ) );
			add_action( 'wp_ajax_w91099ch_sync_profile', array( $this, 'ajax_sync_profile' ) );
			add_action( 'wp_ajax_w91099ch_sync_plugin_data', array( $this, 'ajax_sync_plugin_data' ) );
			add_action( 'wp_ajax_w91099ch_sync_ecommerce_data', array( $this, 'ajax_sync_ecommerce_data' ) );
			add_action( 'wp_ajax_w91099ch_sync_affiliates', array( $this, 'ajax_sync_affiliates' ) );
			add_action( 'wp_ajax_w91099ch_save_auto_sync_setting', array( $this, 'ajax_save_auto_sync_setting' ) );
			add_action( 'wp_ajax_w91099ch_sync_w9_payee', array( $this, 'ajax_sync_w9_payee' ) );
			add_action( 'wp_ajax_w91099ch_get_workspaces', array( $this, 'ajax_get_workspaces' ) );
			add_action( 'wp_ajax_w91099ch_get_detected_plugins', array( $this, 'ajax_get_detected_plugins' ) );
			add_action( 'wp_ajax_w91099ch_collect_affiliate_data', array( $this, 'ajax_collect_affiliate_data' ) );
			add_action( 'wp_ajax_w91099ch_invite_team_members', array( $this, 'ajax_invite_team_members' ) );
			add_action( 'wp_ajax_w91099ch_sync_all_webhook', array( $this, 'ajax_sync_all_webhook' ) );
			add_action( 'wp_ajax_w91099ch_get_user_count', array( $this, 'ajax_get_user_count' ) );
			add_action( 'wp_ajax_w91099ch_get_excluded_affiliates', array( $this, 'ajax_get_excluded_affiliates' ) );
			add_action( 'wp_ajax_w91099ch_set_excluded_affiliates', array( $this, 'ajax_set_excluded_affiliates' ) );
			add_action( 'wp_ajax_w91099ch_validate_promo_code', array( $this, 'ajax_validate_promo_code' ) );

			add_filter( 'cron_schedules', array( $this, 'add_cron_interval_15min' ) );
			add_action( 'w91099ch_auto_sync_cron', array( $this, 'handle_auto_sync_cron' ) );
			add_action( 'activated_plugin', array( $this, 'handle_plugin_activated' ) );
			add_action( 'deactivated_plugin', array( $this, 'handle_plugin_deactivated' ) );
			add_action( 'upgrader_process_complete', array( $this, 'handle_plugin_installed' ), 10, 2 );
			add_action( 'delete_plugin', array( $this, 'handle_plugin_deleted' ), 10, 1 );
			add_action( 'user_register', array( $this, 'handle_user_registered' ) );
			add_action( 'user_register', array( $this, 'handle_sales_tax_nexus_warning_on_user_registered' ), 20, 1 );
			add_action( 'profile_update', array( $this, 'handle_profile_updated' ), 10, 2 );
			add_action( 'added_option', array( $this, 'handle_option_added' ), 10, 2 );
			add_action( 'updated_option', array( $this, 'handle_option_updated' ), 10, 3 );
			add_action( 'deleted_option', array( $this, 'handle_option_deleted' ), 10, 1 );

			// Immediate affiliate sync hooks (when auto sync is enabled).
			add_action( 'affwp_insert_affiliate', array( $this, 'handle_affiliate_event' ), 10, 1 );
			add_action( 'affwp_update_affiliate', array( $this, 'handle_affiliate_event' ), 10, 1 );
			add_action( 'slicewp_register_affiliate', array( $this, 'handle_affiliate_event' ), 10, 1 );
			add_action( 'wpam_front_end_registration_form_submitted', array( $this, 'handle_affiliate_event_wpam' ), 10, 2 );
			add_action( 'wpam_affiliate_application_approved', array( $this, 'handle_affiliate_event' ), 10, 1 );
			add_action( 'wpam_affiliate_application_activated', array( $this, 'handle_affiliate_event' ), 10, 1 );
			add_action( 'wpam_affiliate_application_declined', array( $this, 'handle_affiliate_event' ), 10, 1 );
			add_action( 'wpam_affiliate_application_blocked', array( $this, 'handle_affiliate_event' ), 10, 1 );
			add_action( 'wpam_affiliate_application_deactivated', array( $this, 'handle_affiliate_event' ), 10, 1 );
			add_action( 'yith_wcaf_affiliate_created', array( $this, 'handle_affiliate_event' ), 10, 1 );
			add_action( 'yith_wcaf_affiliate_updated', array( $this, 'handle_affiliate_event' ), 10, 1 );
			add_action( 'yith_wcaf_affiliate_deleted', array( $this, 'handle_affiliate_event' ), 10, 1 );
			add_action( 'yith_wcaf_commission_created', array( $this, 'handle_affiliate_event' ), 10, 1 );
			add_action( 'yith_wcaf_commission_status_changed', array( $this, 'handle_affiliate_event' ), 10, 1 );
			add_action( 'yith_wcaf_commission_deleted', array( $this, 'handle_affiliate_event' ), 10, 1 );

			// Form submission hooks for auto-sync
			add_action( 'fluentform_submission_inserted', array( $this, 'handle_form_submission' ), 10, 3 );
			add_action( 'wpforms_process_complete', array( $this, 'handle_form_submission' ), 10, 4 );
			add_action( 'frm_after_create_entry', array( $this, 'handle_form_submission' ), 10, 2 );
			add_action( 'wpcf7_mail_sent', array( $this, 'handle_form_submission' ), 10, 1 );
			add_action( 'forminator_form_after_save_entry', array( $this, 'handle_form_submission' ), 10, 2 );
			add_action( 'ninja_forms_after_submission', array( $this, 'handle_form_submission' ), 10, 1 );
			add_action( 'everest_forms_process_complete', array( $this, 'handle_form_submission' ), 10, 4 );

			if ( $this->is_auto_sync_enabled() ) {
				$this->schedule_auto_sync_cron();
			}

			add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
			add_action( 'init', array( $this, 'add_rewrite_rules' ) );
			add_action( 'template_redirect', array( $this, 'handle_public_callback' ), 1 );
			add_action( 'admin_init', array( $this, 'handle_public_callback' ), 1 );

			add_filter( 'query_vars', array( $this, 'add_query_vars' ) );

		} catch ( Exception $e ) {
			$this->log( 'Core init failed: ' . $e->getMessage() );
		}
	}

	private function normalize_us_state_code( $state_raw ) {
		$state_raw = is_string( $state_raw ) ? trim( $state_raw ) : '';
		if ( '' === $state_raw ) {
			return '';
		}
		$upper = strtoupper( $state_raw );
		if ( 2 === strlen( $upper ) ) {
			return $upper;
		}
		$lower = strtolower( $state_raw );
		$map   = array(
			'connecticut'    => 'CT',
			'rhode island'   => 'RI',
			'arkansas'       => 'AR',
			'missouri'       => 'MO',
			'new york'       => 'NY',
			'north carolina' => 'NC',
			'georgia'        => 'GA',
			'pennsylvania'   => 'PA',
		);
		return $map[ $lower ] ?? '';
	}

	private function user_looks_like_vendor_or_affiliate( $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return false;
		}
		$roles = is_array( $user->roles ) ? $user->roles : array();
		foreach ( $roles as $role ) {
			$role = is_string( $role ) ? strtolower( $role ) : '';
			if ( '' === $role ) {
				continue;
			}
			if ( false !== strpos( $role, 'vendor' ) ) {
				return true;
			}
			if ( false !== strpos( $role, 'seller' ) ) {
				return true;
			}
			if ( false !== strpos( $role, 'affiliate' ) ) {
				return true;
			}
		}
		return false;
	}

	private function user_looks_like_affiliate( $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return false;
		}
		$roles = is_array( $user->roles ) ? $user->roles : array();
		foreach ( $roles as $role ) {
			$role = is_string( $role ) ? strtolower( $role ) : '';
			if ( '' === $role ) {
				continue;
			}
			if ( false !== strpos( $role, 'affiliate' ) ) {
				return true;
			}
		}
		return false;
	}

	private function user_looks_like_agency( $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return false;
		}
		$roles = is_array( $user->roles ) ? $user->roles : array();
		foreach ( $roles as $role ) {
			$role = is_string( $role ) ? strtolower( $role ) : '';
			if ( '' === $role ) {
				continue;
			}
			if ( false !== strpos( $role, 'agency' ) ) {
				return true;
			}
			if ( false !== strpos( $role, 'contractor' ) ) {
				return true;
			}
			if ( false !== strpos( $role, 'vendor' ) ) {
				return true;
			}
			if ( false !== strpos( $role, 'seller' ) ) {
				return true;
			}
		}
		return false;
	}

	public function handle_sales_tax_nexus_warning_on_user_registered( $user_id ) {
		if ( ! (bool) get_option( 'w91099ch_connected', false ) ) {
			return;
		}

		$master_enabled = (bool) get_option( 'w91099ch_warn_sales_tax_nexus_on_signup', false );
		$affiliate_enabled = (bool) get_option( 'w91099ch_sales_tax_nexus_affiliate_enabled', false );
		$click_through_enabled = (bool) get_option( 'w91099ch_sales_tax_nexus_click_through_enabled', false );
		$agency_enabled = (bool) get_option( 'w91099ch_sales_tax_nexus_agency_enabled', false );
		$any_enabled = ( $affiliate_enabled || $click_through_enabled || $agency_enabled || $master_enabled );
		if ( ! $any_enabled ) {
			return;
		}

		$user = get_user_by( 'id', (int) $user_id );
		if ( ! $user || ! ( $user instanceof WP_User ) ) {
			return;
		}

		$user_is_affiliate = $this->user_looks_like_affiliate( $user );
		$user_is_agency = $this->user_looks_like_agency( $user );
		$user_is_vendor_or_affiliate = $this->user_looks_like_vendor_or_affiliate( $user );

		$should_warn_for_user = false;
		if ( $master_enabled ) {
			$should_warn_for_user = $user_is_vendor_or_affiliate;
		} else {
			if ( $affiliate_enabled && $user_is_affiliate ) {
				$should_warn_for_user = true;
			}
			if ( $click_through_enabled && $user_is_affiliate ) {
				$should_warn_for_user = true;
			}
			if ( $agency_enabled && $user_is_agency ) {
				$should_warn_for_user = true;
			}
		}
		if ( ! $should_warn_for_user ) {
			return;
		}

		$state_meta_keys = array(
			'billing_state',
			'shipping_state',
			'state',
			'user_state',
			'w9_state',
		);

		$state_code = '';
		foreach ( $state_meta_keys as $k ) {
			$val = get_user_meta( (int) $user_id, $k, true );
			if ( is_string( $val ) && '' !== trim( $val ) ) {
				$state_code = $this->normalize_us_state_code( $val );
				if ( '' !== $state_code ) {
					break;
				}
			}
		}

		if ( '' === $state_code ) {
			return;
		}

		$nexus_states = array( 'CT', 'RI', 'AR', 'MO', 'NY', 'NC', 'GA', 'PA' );
		if ( ! in_array( $state_code, $nexus_states, true ) ) {
			return;
		}

		$roles = is_array( $user->roles ) ? $user->roles : array();
		$role_label = ! empty( $roles ) ? implode( ', ', array_map( 'strval', $roles ) ) : '';
		$display = $user->display_name ? $user->display_name : $user->user_login;
		$message = sprintf(
			__( 'Sales tax nexus warning: New affiliate/vendor signup detected (%1$s, ID %2$d) in state %3$s. Role(s): %4$s.', 'w9-1099-chaser' ),
			sanitize_text_field( (string) $display ),
			(int) $user_id,
			esc_html( (string) $state_code ),
			sanitize_text_field( (string) $role_label )
		);

		set_transient(
			'w91099ch_sales_tax_nexus_warning',
			array(
				'message' => $message,
				'user_id' => (int) $user_id,
				'state'   => (string) $state_code,
			),
			DAY_IN_SECONDS
		);
	}

	public function ajax_refresh_affiliate_plugins() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		try {
			$result = $this->affiliate_manager->refresh_detection();

			wp_send_json_success(
				array(
					'plugins'          => $result['plugins'],
					'total_affiliates' => $result['total_affiliates'],
					'message'          => esc_html__( 'Successfully refreshed affiliate plugin detection', 'w9-1099-chaser' ),
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error( esc_html__( 'Request failed. Please retry.', 'w9-1099-chaser' ) );
		}
	}

	public function ajax_get_plugin_affiliates() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$plugin_slug_raw = filter_input( INPUT_POST, 'plugin_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$plugin_slug     = is_string( $plugin_slug_raw ) ? sanitize_text_field( wp_unslash( $plugin_slug_raw ) ) : '';
		$limit_raw       = filter_input( INPUT_POST, 'limit', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$limit           = is_string( $limit_raw ) ? absint( wp_unslash( $limit_raw ) ) : 50;
		$offset_raw      = filter_input( INPUT_POST, 'offset', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$offset          = is_string( $offset_raw ) ? absint( wp_unslash( $offset_raw ) ) : 0;

		try {
			$result = $this->affiliate_manager->get_affiliates_for_display( $plugin_slug, $limit, $offset );

			wp_send_json_success(
				array(
					'affiliates'  => $result['affiliates'],
					'total_count' => $result['total_count'],
					'plugin_slug' => $plugin_slug,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error( esc_html__( 'Request failed. Please retry.', 'w9-1099-chaser' ) );
		}
	}

	public function ajax_get_all_affiliates() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$limit_raw  = filter_input( INPUT_POST, 'limit', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$limit      = is_string( $limit_raw ) ? absint( wp_unslash( $limit_raw ) ) : 50;
		$offset_raw = filter_input( INPUT_POST, 'offset', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$offset     = is_string( $offset_raw ) ? absint( wp_unslash( $offset_raw ) ) : 0;

		try {
			$result = $this->affiliate_manager->get_affiliates_for_display( '', $limit, $offset );

			wp_send_json_success(
				array(
					'affiliates'  => $result['affiliates'],
					'total_count' => $result['total_count'],
					'plugin_slug' => 'all',
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error( esc_html__( 'Request failed. Please retry.', 'w9-1099-chaser' ) );
		}
	}

	public function ajax_sync_plugin_data() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();

		try {
			if ( ! $this->affiliate_manager ) {
				throw new Exception( 'Affiliate manager is not available' );
			}

			$result           = $this->affiliate_manager->refresh_detection();
			$plugins          = isset( $result['plugins'] ) ? $result['plugins'] : array();
			$total_affiliates = isset( $result['total_affiliates'] ) ? (int) $result['total_affiliates'] : 0;

			$affiliate_slugs         = is_array( $plugins ) ? array_keys( $plugins ) : array();
			$all_plugins_snapshot    = $this->get_plugins_snapshot( $affiliate_slugs, false );
			$active_plugins_snapshot = $this->get_plugins_snapshot( $affiliate_slugs, true );

			$snapshot_payload = array(
				'captured_at' => time(),
				'admin_email' => (string) get_option( 'admin_email', '' ),
				'site_url'    => (string) get_site_url(),
				'plugins'     => $active_plugins_snapshot,
			);
			update_option( 'w91099ch_active_plugins_snapshot', $snapshot_payload );

			update_option( 'w91099ch_plugins_last_sync', time() );

			$plugin_stats = array(
				'plugins_count'        => is_array( $plugins ) ? count( $plugins ) : 0,
				'all_plugins_count'    => is_array( $all_plugins_snapshot ) ? count( $all_plugins_snapshot ) : 0,
				'total_affiliates'     => $total_affiliates,
				'active_plugins_count' => is_array( $active_plugins_snapshot ) ? count( $active_plugins_snapshot ) : 0,
			);
			$plugin_full_payload = array(
				'data' => array(
					'plugins'        => is_array( $all_plugins_snapshot ) ? $all_plugins_snapshot : array(),
					'active_plugins' => is_array( $active_plugins_snapshot ) ? $active_plugins_snapshot : array(),
				),
				'stats'          => $plugin_stats,
				'plugins'        => is_array( $all_plugins_snapshot ) ? $all_plugins_snapshot : array(),
				'active_plugins' => is_array( $active_plugins_snapshot ) ? $active_plugins_snapshot : array(),
			);
			$webhook_status = $this->api->send_to_webhook(
				'ecommerce',
				$all_plugins_snapshot,
				array( 'total' => count( $all_plugins_snapshot ) ),
				'w91099ch_sync_ecommerce_data',
				array( 'ecommerce_plugins' => $all_plugins_snapshot )
			);

			wp_send_json_success(
				array(
					'message'        => esc_html__( 'Plugins synced successfully', 'w9-1099-chaser' ),
					'plugins'        => $plugins,
					'active_plugins' => $active_plugins_snapshot,
					'webhook_status' => $webhook_status,
					'stats'          => array(
						'plugins_count'        => is_array( $plugins ) ? count( $plugins ) : 0,
						'all_plugins_count'    => is_array( $all_plugins_snapshot ) ? count( $all_plugins_snapshot ) : 0,
						'total_affiliates'     => $total_affiliates,
						'active_plugins_count' => is_array( $active_plugins_snapshot ) ? count( $active_plugins_snapshot ) : 0,
					),
				)
			);
		} catch ( Throwable $e ) {
			$this->log( 'Plugin sync error: ' . $e->getMessage() );
			wp_send_json_error( esc_html__( 'Request failed. Please retry.', 'w9-1099-chaser' ) );
		}
	}

	private function get_plugins_snapshot( $affiliate_slugs = array(), $only_active = false ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$affiliate_lookup = array();
		foreach ( (array) $affiliate_slugs as $slug ) {
			$s = sanitize_title( (string) $slug );
			if ( '' === $s ) {
				continue;
			}
			$affiliate_lookup[ $s ] = true;
		}

		$active_plugins = get_option( 'active_plugins', array() );
		if ( ! is_array( $active_plugins ) ) {
			$active_plugins = array();
		}
		$active_lookup = array_fill_keys( array_map( 'strval', $active_plugins ), true );

		$network_active_lookup = array();
		if ( is_multisite() ) {
			$sitewide = get_site_option( 'active_sitewide_plugins', array() );
			if ( is_array( $sitewide ) ) {
				foreach ( $sitewide as $plugin_file => $v ) {
					$network_active_lookup[ (string) $plugin_file ] = true;
				}
			}
		}

		$plugins = get_plugins();
		$out     = array();
		foreach ( (array) $plugins as $plugin_file => $data ) {
			$plugin_file = (string) $plugin_file;

			$is_active = isset( $active_lookup[ $plugin_file ] ) || isset( $network_active_lookup[ $plugin_file ] );
			if ( $only_active && ! $is_active ) {
				continue;
			}

			$folder = dirname( $plugin_file );
			$slug   = ( $folder && $folder !== '.' ) ? $folder : sanitize_title( (string) ( $data['Name'] ?? $plugin_file ) );

			$out[] = array(
				'plugin_file'         => $plugin_file,
				'slug'                => $slug,
				'name'                => (string) ( $data['Name'] ?? $slug ),
				'version'             => (string) ( $data['Version'] ?? '' ),
				'description'         => (string) ( $data['Description'] ?? '' ),
				'author'              => (string) ( $data['AuthorName'] ?? $data['Author'] ?? '' ),
				'plugin_uri'          => (string) ( $data['PluginURI'] ?? '' ),
				'active'              => $is_active,
				'network_active'      => isset( $network_active_lookup[ $plugin_file ] ),
				'is_affiliate_vendor' => isset( $affiliate_lookup[ sanitize_title( (string) $slug ) ] ),
			);
		}

		return $out;
	}

	private function get_active_plugins_snapshot( $affiliate_slugs = array() ) {
		return $this->get_plugins_snapshot( $affiliate_slugs, true );
	}

	private function build_affiliate_row_payload( $affiliate, $plugin_slug, $stats, $context_action = '' ) {
		$affiliate = is_array( $affiliate ) ? $affiliate : array();
		$stats     = is_array( $stats ) ? $stats : array();

		$affiliate_id = '';
		if ( isset( $affiliate['id'] ) ) {
			$affiliate_id = sanitize_text_field( (string) $affiliate['id'] );
		} elseif ( isset( $affiliate['affiliate_id'] ) ) {
			$affiliate_id = sanitize_text_field( (string) $affiliate['affiliate_id'] );
		}

		return $this->scrub_prohibited_w9_fields(
			array(
				'event_type'              => 'affiliates_synced',
				'timestamp'               => gmdate( 'c' ),
				'site_url'                => (string) get_site_url(),
				'site_name'               => (string) get_bloginfo( 'name' ),
				'admin_email'             => sanitize_email( (string) get_option( 'admin_email', '' ) ),
				'sheet_tab'              => 'affiliates',
				'card_key'               => 'affiliates_data',
				'sync_scope'              => 'row',
				'plugin_slug'             => sanitize_text_field( (string) $plugin_slug ),
				'affiliate_id'            => $affiliate_id,
				'affiliate_name'          => sanitize_text_field( (string) ( $affiliate['name'] ?? '' ) ),
				'affiliate_email'         => sanitize_email( (string) ( $affiliate['email'] ?? '' ) ),
				'affiliate_company'       => sanitize_text_field( (string) ( $affiliate['company'] ?? ( $affiliate['company_name'] ?? '' ) ) ),
				'affiliate_status'        => sanitize_text_field( (string) ( $affiliate['status'] ?? '' ) ),
				'affiliate_plugin'        => sanitize_text_field( (string) ( $affiliate['plugin'] ?? '' ) ),
				'affiliate_plugin_slug'   => sanitize_text_field( (string) ( $affiliate['plugin_slug'] ?? '' ) ),
				'affiliate_registered_at' => sanitize_text_field( (string) ( $affiliate['date_registered'] ?? '' ) ),
				'affiliate_amount'        => is_numeric( $affiliate['amount'] ?? null ) ? (string) $affiliate['amount'] : '',
				'successful'              => isset( $stats['successful'] ) ? (int) $stats['successful'] : 0,
				'total_affiliates'        => isset( $stats['total_affiliates'] ) ? (int) $stats['total_affiliates'] : 0,
				'excluded'                => isset( $stats['excluded'] ) ? (int) $stats['excluded'] : 0,
				'context_action'          => sanitize_text_field( (string) $context_action ),
				'affiliates_data'         => array(
					'data'    => array(
						'id'              => $affiliate_id,
						'name'            => sanitize_text_field( (string) ( $affiliate['name'] ?? '' ) ),
						'email'           => sanitize_email( (string) ( $affiliate['email'] ?? '' ) ),
						'company'         => sanitize_text_field( (string) ( $affiliate['company'] ?? ( $affiliate['company_name'] ?? '' ) ) ),
						'status'          => sanitize_text_field( (string) ( $affiliate['status'] ?? '' ) ),
						'plugin'          => sanitize_text_field( (string) ( $affiliate['plugin'] ?? '' ) ),
						'plugin_slug'     => sanitize_text_field( (string) ( $affiliate['plugin_slug'] ?? '' ) ),
						'date_registered' => sanitize_text_field( (string) ( $affiliate['date_registered'] ?? '' ) ),
						'amount'          => is_numeric( $affiliate['amount'] ?? null ) ? (string) $affiliate['amount'] : '',
					),
					'summary' => array(
						'successful'       => isset( $stats['successful'] ) ? (int) $stats['successful'] : 0,
						'total_affiliates' => isset( $stats['total_affiliates'] ) ? (int) $stats['total_affiliates'] : 0,
						'excluded'         => isset( $stats['excluded'] ) ? (int) $stats['excluded'] : 0,
					),
				),
			)
		);
	}

	private function dispatch_affiliate_rows_webhook( $included_affiliates, $plugin_slug, $stats, $context_action = '' ) {
		$included_affiliates = is_array( $included_affiliates ) ? $included_affiliates : array();
		$stats               = is_array( $stats ) ? $stats : array();

		$attempted = 0;
		$sent      = 0;
		$errors    = array();

		if ( empty( $included_affiliates ) ) {
			$summary_payload = $this->scrub_prohibited_w9_fields(
				array(
					'event_type'       => 'affiliates_synced',
					'timestamp'        => gmdate( 'c' ),
					'site_url'         => (string) get_site_url(),
					'site_name'        => (string) get_bloginfo( 'name' ),
					'admin_email'      => sanitize_email( (string) get_option( 'admin_email', '' ) ),
					'sheet_tab'        => 'affiliates',
					'card_key'         => 'affiliates_data',
					'sync_scope'       => 'summary',
					'plugin_slug'      => sanitize_text_field( (string) $plugin_slug ),
					'successful'       => isset( $stats['successful'] ) ? (int) $stats['successful'] : 0,
					'total_affiliates' => isset( $stats['total_affiliates'] ) ? (int) $stats['total_affiliates'] : 0,
					'excluded'         => isset( $stats['excluded'] ) ? (int) $stats['excluded'] : 0,
					'context_action'   => sanitize_text_field( (string) $context_action ),
					'affiliates_data'  => array(
						'data'    => array(),
						'summary' => array(
							'successful'       => isset( $stats['successful'] ) ? (int) $stats['successful'] : 0,
							'total_affiliates' => isset( $stats['total_affiliates'] ) ? (int) $stats['total_affiliates'] : 0,
							'excluded'         => isset( $stats['excluded'] ) ? (int) $stats['excluded'] : 0,
						),
					),
				)
			);
			$result = w91099ch_Webhook_Dispatcher::dispatch_raw_payload( $summary_payload, 'affiliates_synced' );
			return array(
				'attempted' => isset( $result['attempted'] ) ? (int) $result['attempted'] : 0,
				'sent'      => isset( $result['sent'] ) ? (int) $result['sent'] : 0,
				'errors'    => isset( $result['errors'] ) && is_array( $result['errors'] ) ? $result['errors'] : array(),
			);
		}

		foreach ( $included_affiliates as $affiliate ) {
			$row_payload = $this->build_affiliate_row_payload( $affiliate, $plugin_slug, $stats, $context_action );
			$result      = w91099ch_Webhook_Dispatcher::dispatch_raw_payload( $row_payload, 'affiliates_synced' );
			$attempted  += isset( $result['attempted'] ) ? (int) $result['attempted'] : 0;
			$sent       += isset( $result['sent'] ) ? (int) $result['sent'] : 0;
			if ( isset( $result['errors'] ) && is_array( $result['errors'] ) && ! empty( $result['errors'] ) ) {
				$errors = array_merge( $errors, $result['errors'] );
			}
		}

		return array(
			'attempted' => $attempted,
			'sent'      => $sent,
			'errors'    => $errors,
		);
	}

	public function ajax_sync_ecommerce_data() {
		$nonce_ok = (bool) check_ajax_referer( 'w91099ch_nonce', 'nonce', false );
		if ( ! $nonce_ok ) {
			$nonce_ok = (bool) check_ajax_referer( 'w91099ch_sync_nonce', 'nonce', false );
		}
		if ( ! $nonce_ok ) {
			wp_send_json_error( esc_html__( 'Invalid security nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		try {
			if ( ! class_exists( 'w91099ch_Ecommerce_Plugin_Detector' ) ) {
				require_once w91099ch_PLUGIN_PATH . 'includes/ecommerce-plugin-detector-init.php';
			}

			$detector = new w91099ch_Ecommerce_Plugin_Detector();
			$plugins  = $detector->get_ecommerce_plugins_data();

			$payload = array(
				'event_type'  => 'ecommerce_synced',
				'timestamp'   => gmdate( 'c' ),
				'site_url'    => (string) get_site_url(),
				'site_name'   => (string) get_bloginfo( 'name' ),
				'admin_email' => sanitize_email( (string) get_option( 'admin_email', '' ) ),
				'sheet_tab'   => 'ecommerce',
				'card_key'    => 'ecommerce_data',
				'data'        => array_values( $plugins ),
				'summary'     => array(
					'total_detected' => count( $plugins ),
				),
			);

			$webhook_status = $this->api->send_to_webhook(
				'ecommerce',
				array_values( $plugins ),
				array( 'total' => count( $plugins ) ),
				'w91099ch_sync_ecommerce_data',
				$payload
			);

			update_option( 'w91099ch_ecommerce_last_sync', time() );

			wp_send_json_success(
				array(
					'message'        => esc_html__( 'Ecommerce data synced successfully', 'w9-1099-chaser' ),
					'webhook_status' => $webhook_status,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function ajax_sync_affiliates() {
		$nonce_ok = (bool) check_ajax_referer( 'w91099ch_nonce', 'nonce', false );
		if ( ! $nonce_ok ) {
			$nonce_ok = (bool) check_ajax_referer( 'w91099ch_sync_nonce', 'nonce', false );
		}
		if ( ! $nonce_ok ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();

		$plugin_slug_raw = filter_input( INPUT_POST, 'plugin_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$plugin_slug     = is_string( $plugin_slug_raw ) ? sanitize_text_field( wp_unslash( $plugin_slug_raw ) ) : '';

		try {
			if ( ! $this->affiliate_manager ) {
				throw new Exception( 'Affiliate manager is not available' );
			}

			$affiliates = array();
			if ( method_exists( $this->affiliate_manager, 'get_all_affiliates_for_sync' ) ) {
				$affiliates = $this->affiliate_manager->get_all_affiliates_for_sync( $plugin_slug );
			}

			if ( empty( $affiliates ) ) {
				// Ensure we have fresh data.
				if ( method_exists( $this->affiliate_manager, 'refresh_detection' ) ) {
					$this->affiliate_manager->refresh_detection();
				}
				if ( method_exists( $this->affiliate_manager, 'get_all_affiliates_for_sync' ) ) {
					$affiliates = $this->affiliate_manager->get_all_affiliates_for_sync( $plugin_slug );
				}
			}

			$total_affiliates = is_array( $affiliates ) ? count( $affiliates ) : 0;

			$excluded            = $this->get_excluded_affiliate_ids();
			$included_affiliates = array();
			if ( is_array( $affiliates ) && ! empty( $affiliates ) ) {
				foreach ( $affiliates as $affiliate ) {
					$affiliate_id = '';
					if ( is_array( $affiliate ) && isset( $affiliate['id'] ) ) {
						$affiliate_id = (string) $affiliate['id'];
					} elseif ( is_array( $affiliate ) && isset( $affiliate['affiliate_id'] ) ) {
						$affiliate_id = (string) $affiliate['affiliate_id'];
					}

					if ( '' !== $affiliate_id && isset( $excluded[ $affiliate_id ] ) ) {
						continue;
					}

					$included_affiliates[] = $affiliate;
				}
			}

			$included_count = is_array( $included_affiliates ) ? count( $included_affiliates ) : 0;
			$excluded_count = max( 0, $total_affiliates - $included_count );
			update_option( 'w91099ch_affiliates_last_sync', time() );
			update_option( 'w91099ch_affiliates_count', $included_count );

			$stats = array(
				'successful'       => $included_count,
				'total_affiliates' => $total_affiliates,
				'excluded'         => $excluded_count,
			);
			$webhook_status = $this->dispatch_affiliate_rows_webhook(
				$included_affiliates,
				$plugin_slug,
				$stats,
				'w91099ch_sync_affiliates'
			);

			wp_send_json_success(
				array(
					'message' => esc_html__( 'Affiliates synced successfully', 'w9-1099-chaser' ),
					'webhook_status' => $webhook_status,
					'stats'   => array(
						'successful'       => $included_count,
						'total_affiliates' => $total_affiliates,
						'excluded'         => $excluded_count,
					),
				)
			);
		} catch ( Throwable $e ) {
			$this->log( 'Affiliates sync error: ' . $e->getMessage() );
			wp_send_json_error( esc_html__( 'Request failed. Please retry.', 'w9-1099-chaser' ) );
		}
	}

	public function ajax_get_detected_plugins() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();

		try {
			if ( ! $this->affiliate_manager ) {
				throw new Exception( 'Affiliate manager is not available' );
			}

			$plugins          = $this->affiliate_manager->detect_affiliate_plugins( true );
			$total_affiliates = method_exists( $this->affiliate_manager, 'get_total_affiliates_count' )
				? (int) $this->affiliate_manager->get_total_affiliates_count()
				: 0;

			wp_send_json_success(
				array(
					'plugins'          => $plugins,
					'total_affiliates' => $total_affiliates,
				)
			);
		} catch ( Throwable $e ) {
			$this->log( 'Get detected plugins error: ' . $e->getMessage() );
			wp_send_json_error( esc_html__( 'Request failed. Please retry.', 'w9-1099-chaser' ) );
		}
	}

	public function ajax_collect_affiliate_data() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();

		try {
			if ( ! $this->affiliate_manager ) {
				throw new Exception( 'Affiliate manager is not available' );
			}

			$result = $this->affiliate_manager->refresh_detection();
			$all_affiliates = method_exists( $this->affiliate_manager, 'get_all_affiliates_for_sync' )
				? $this->affiliate_manager->get_all_affiliates_for_sync( '' )
				: array();
			if ( ! is_array( $all_affiliates ) ) {
				$all_affiliates = array();
			}

			$excluded            = $this->get_excluded_affiliate_ids();
			$included_affiliates = array();
			foreach ( $all_affiliates as $affiliate ) {
				$affiliate_id = '';
				if ( is_array( $affiliate ) && isset( $affiliate['id'] ) ) {
					$affiliate_id = (string) $affiliate['id'];
				} elseif ( is_array( $affiliate ) && isset( $affiliate['affiliate_id'] ) ) {
					$affiliate_id = (string) $affiliate['affiliate_id'];
				}

				if ( '' !== $affiliate_id && isset( $excluded[ $affiliate_id ] ) ) {
					continue;
				}

				$included_affiliates[] = $affiliate;
			}

			$total_affiliates = count( $all_affiliates );
			$included_count   = count( $included_affiliates );
			$excluded_count   = max( 0, $total_affiliates - $included_count );
			$stats            = array(
				'successful'       => $included_count,
				'total_affiliates' => $total_affiliates,
				'excluded'         => $excluded_count,
			);

			$webhook_status = $this->dispatch_affiliate_rows_webhook(
				$included_affiliates,
				'',
				$stats,
				'w91099ch_collect_affiliate_data'
			);

			wp_send_json_success(
				array(
					'message'          => esc_html__( 'Affiliate/vendor data collected successfully', 'w9-1099-chaser' ),
					'plugins'          => $result['plugins'] ?? array(),
					'total_affiliates' => $total_affiliates,
					'included'         => $included_count,
					'excluded'         => $excluded_count,
					'collected_at'     => time(),
					'webhook_status'   => $webhook_status,
				)
			);
		} catch ( Throwable $e ) {
			$this->log( 'Collect affiliate data error: ' . $e->getMessage() );
			wp_send_json_error( esc_html__( 'Request failed. Please retry.', 'w9-1099-chaser' ) );
		}
	}

	public function ajax_invite_team_members() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();

		try {
			$success = 0;
			$failed  = 0;
			$errors  = array();

			$credentials  = $this->ensure_valid_token();
			$access_token = isset( $credentials['access_token'] ) ? $credentials['access_token'] : '';
			if ( empty( $access_token ) ) {
				throw new Exception( 'No access token available' );
			}

			$normalize_role = function ( $raw_role ) {
				$role = strtoupper( trim( (string) $raw_role ) );

				if ( $role === 'ADMIN' ) {
					$role = 'ADMINISTRATOR';
				}
				if ( $role === 'SHOP MANAGER' ) {
					$role = 'SHOP_MANAGER';
				}

				if ( $role === 'ADMINISTRATOR' || $role === 'CONTRIBUTOR' || $role === 'AUTHOR' || $role === 'SHOP_MANAGER' || $role === 'EDITOR' || $role === 'VIEWER' ) {
					return $role;
				}

				return 'VIEWER';
			};

			$raw_users = isset( $_POST['users'] ) ? wp_unslash( $_POST['users'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below (supports array or JSON string).
			if ( is_string( $raw_users ) ) {
				$raw_users = sanitize_text_field( $raw_users );
			}
			if ( is_string( $raw_users ) ) {
				$decoded = json_decode( $raw_users, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
					$raw_users = $decoded;
				} else {
					$raw_users = array();
				}
			}

			if ( ! is_array( $raw_users ) || empty( $raw_users ) ) {
				throw new Exception( 'No users provided' );
			}

			$deduped = array();
			foreach ( $raw_users as $idx => $u ) {
				$email = '';
				$role  = '';

				if ( is_array( $u ) ) {
					$email = sanitize_email( $u['email'] ?? '' );
					$role  = (string) ( $u['role'] ?? '' );
				} else {
					$email = sanitize_email( (string) $u );
				}

				if ( ! $email || ! is_email( $email ) ) {
					++$failed;
					$errors[] = array(
						'index' => $idx,
						'email' => $email,
						'error' => esc_html__( 'Invalid email', 'w9-1099-chaser' ),
					);
					continue;
				}

				$email_key             = strtolower( $email );
				$deduped[ $email_key ] = array(
					'email' => $email,
					'role'  => $normalize_role( $role ),
				);
			}

			$invitations = array_values( $deduped );
			if ( empty( $invitations ) ) {
				throw new Exception( 'No valid invitations to send' );
			}

			$endpoint = apply_filters(
				'w91099ch_team_invite_endpoint',
				'/api/w9-1099-chaser/team/invitations/'
			);

			if ( ! is_string( $endpoint ) ) {
				$endpoint = '';
			}
			$endpoint = trim( $endpoint );
			if ( '' === $endpoint ) {
				throw new Exception( 'Team invite endpoint is not configured' );
			}

			$payload = array(
				'invitations' => $invitations,
			);

			$base_url      = (string) $this->api->get_api_base_url();
			$base_url      = rtrim( $base_url, '/' );
			$fallback_base = preg_replace( '#/v1$#', '', $base_url );
			if ( ! is_string( $fallback_base ) || $fallback_base === '' ) {
				$fallback_base = $base_url;
			}

			$urls = array_values(
				array_unique(
					array(
						$base_url . $endpoint,
						rtrim( (string) $fallback_base, '/' ) . $endpoint,
					)
				)
			);

			$last_code = 0;
			$last_body = '';
			$response  = null;

			foreach ( $urls as $api_url ) {
				$sslverify = apply_filters( 'w91099ch_sslverify', true, $api_url, $endpoint, 'POST' );
				$timeout   = (int) apply_filters( 'w91099ch_api_timeout', 15, $api_url, $endpoint, 'POST' );
				if ( 0 >= $timeout ) {
					$timeout = 15;
				}

				$response = wp_remote_post(
					$api_url,
					array(
						'headers'     => array(
							'Authorization'   => 'Bearer ' . $access_token,
							'Content-Type'    => 'application/json',
							'Accept'          => 'application/json',
							'User-Agent'      => 'w9-1099-chaser-WordPress/1.0',
							'Referer'         => get_site_url(),
							'Origin'          => wp_parse_url( get_site_url(), PHP_URL_HOST ),
							'Idempotency-Key' => hash( 'sha256', $api_url . '|POST|' . wp_json_encode( $payload ) . '|' . (string) get_site_url() ),
						),
						'body'        => wp_json_encode( $payload ),
						'timeout'     => $timeout,
						'sslverify'   => (bool) $sslverify,
						'redirection' => 2,
					)
				);

				if ( is_wp_error( $response ) ) {
					$last_body = $response->get_error_message();
					$last_code = 0;
					continue;
				}

				$last_code = (int) wp_remote_retrieve_response_code( $response );
				$last_body = (string) wp_remote_retrieve_body( $response );

				if ( $last_code >= 200 && $last_code < 300 ) {
					break;
				}
			}

			$code = $last_code;
			$body = $last_body;

			if ( $code >= 200 && $code < 300 ) {
				$success = count( $invitations );

				$decoded_body = array();
				if ( is_string( $body ) && '' !== $body ) {
					$maybe_decoded = json_decode( $body, true );
					if ( json_last_error() === JSON_ERROR_NONE && is_array( $maybe_decoded ) ) {
						$decoded_body = $maybe_decoded;
					}
				}

				if ( isset( $decoded_body['invited'] ) ) {
					$success = (int) $decoded_body['invited'];
				}
				if ( isset( $decoded_body['failed'] ) ) {
					$failed = (int) $decoded_body['failed'];
				}
				if ( isset( $decoded_body['errors'] ) && is_array( $decoded_body['errors'] ) ) {
					$errors = array_merge( $errors, $decoded_body['errors'] );
				}
			} else {
				$service_message = '';
				if ( is_string( $body ) && '' !== $body ) {
					$decoded_body = json_decode( $body, true );
					if ( is_array( $decoded_body ) ) {
						if ( isset( $decoded_body['message'] ) && is_string( $decoded_body['message'] ) && '' !== $decoded_body['message'] ) {
							$service_message = (string) $decoded_body['message'];
						} elseif ( isset( $decoded_body['detail'] ) && is_string( $decoded_body['detail'] ) && '' !== $decoded_body['detail'] ) {
							$service_message = (string) $decoded_body['detail'];
						} elseif ( isset( $decoded_body['error'] ) && is_string( $decoded_body['error'] ) && '' !== $decoded_body['error'] ) {
							$service_message = (string) $decoded_body['error'];
						}
					}
				}

				$failed  += count( $invitations );
				$errors[] = array(
					'error'   => 'HTTP ' . $code,
					'message' => $service_message,
				);

				$msg = 'Request failed (HTTP ' . (int) $code . ')';
				if ( '' !== $service_message ) {
					$msg .= ': ' . $service_message;
				}
				throw new Exception( $msg );
			}

			update_option( 'w91099ch_team_last_sync', time() );

			$team_invite_summary = array(
				'sent'    => count( $invitations ),
				'invited' => $success,
				'failed'  => $failed,
			);
			$team_invite_full_payload = array(
				'data'        => $invitations,
				'invitations' => $invitations,
				'errors'      => $errors,
				'sent'        => count( $invitations ),
				'invited'     => $success,
				'failed'      => $failed,
			);
			$webhook_status = $this->dispatch_card_rows_webhook(
				'team_members_invited',
				'team',
				$invitations,
				$team_invite_summary,
				'w91099ch_invite_team_members',
				$team_invite_full_payload
			);

			wp_send_json_success(
				array(
					'message' => esc_html__( 'Team members invited', 'w9-1099-chaser' ),
					'webhook_status' => $webhook_status,
					'sent'    => count( $invitations ),
					'invited' => $success,
					'failed'  => $failed,
					'errors'  => $errors,
				)
			);
		} catch ( Throwable $e ) {
			$this->log( 'Invite team members error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => sanitize_text_field( $e->getMessage() ),
				)
			);
		}
	}

	public function ajax_sync_all_webhook() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();

		if ( ! class_exists( 'w91099ch_Webhook_Dispatcher' ) ) {
			wp_send_json_error( esc_html__( 'Webhook dispatcher not available', 'w9-1099-chaser' ) );
		}

		try {
			$payload = $this->build_sync_all_payload();

			$webhook_status = array();
			$events = array(
				'plugin_data_synced',
				'affiliates_synced',
				'team_members_synced',
				'form_plugins_synced',
				'membership_plugins_synced',
				'contractor_plugins_synced',
				'freelancer_contractor_plugins_synced',
				'accounting_plugins_synced',
				'accounting_bookkeeping_plugins_synced',
				'payout_plugins_synced',
				'wallet_payout_plugins_synced',
			);

			foreach ( $events as $event_type ) {
				$webhook_status[ $event_type ] = w91099ch_Webhook_Dispatcher::dispatch_raw_payload(
					$payload,
					$event_type
				);
			}

			wp_send_json_success(
				array(
					'message'        => esc_html__( 'Sync-all webhook sent successfully', 'w9-1099-chaser' ),
					'webhook_status' => $webhook_status,
					'event_id'       => isset( $payload['event_id'] ) ? $payload['event_id'] : '',
				)
			);
		} catch ( Throwable $e ) {
			$this->log( 'Sync-all webhook error: ' . $e->getMessage() );
			wp_send_json_error( esc_html__( 'Sync-all webhook failed. Please retry.', 'w9-1099-chaser' ) );
		}
	}

	private function build_sync_all_payload() {
		if ( function_exists( 'w91099ch_build_full_webhook_payload' ) ) {
			return w91099ch_build_full_webhook_payload( 'plugin_data_synced', array() );
		}

		$event_id = 'wp_' . preg_replace( '/[^a-zA-Z0-9_]/', '', uniqid( '', true ) );

		$user_profile = array();
		if ( function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
			if ( $user && isset( $user->ID ) ) {
				$role = '';
				if ( isset( $user->roles ) && is_array( $user->roles ) && ! empty( $user->roles ) ) {
					$role = (string) reset( $user->roles );
				}
				$user_profile = array(
					'user_id'    => (int) $user->ID,
					'username'   => (string) $user->user_login,
					'email'      => (string) $user->user_email,
					'role'       => $role,
					'registered' => (string) $user->user_registered,
				);
			}
		}

		$plugin_data = array();
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins    = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$active_lookup  = array_fill_keys( array_map( 'strval', $active_plugins ), true );
		$sitewide       = get_site_option( 'active_sitewide_plugins', array() );
		if ( is_array( $sitewide ) ) {
			foreach ( $sitewide as $plugin_file => $val ) {
				$active_lookup[ (string) $plugin_file ] = true;
			}
		}
		foreach ( (array) $all_plugins as $plugin_file => $plugin_info ) {
			$slug = strtolower( (string) strtok( (string) $plugin_file, '/' ) );
			$plugin_data[] = array(
				'slug'    => $slug,
				'name'    => isset( $plugin_info['Name'] ) ? (string) $plugin_info['Name'] : '',
				'version' => isset( $plugin_info['Version'] ) ? (string) $plugin_info['Version'] : '',
				'active'  => isset( $active_lookup[ (string) $plugin_file ] ),
				'author'  => isset( $plugin_info['Author'] ) ? wp_strip_all_tags( (string) $plugin_info['Author'] ) : '',
			);
		}

		$team = array();
		$users = get_users( array( 'fields' => array( 'ID', 'user_login', 'user_email', 'roles', 'user_registered' ) ) );
		foreach ( (array) $users as $user ) {
			$role = '';
			if ( isset( $user->roles ) && is_array( $user->roles ) && ! empty( $user->roles ) ) {
				$role = (string) reset( $user->roles );
			}
			$team[] = array(
				'user_id'  => (int) $user->ID,
				'username' => (string) $user->user_login,
				'role'     => $role,
				'email'    => (string) $user->user_email,
			);
		}

		$form_plugins = array();
		$membership_plugins = array();
		$accounting_plugins = array();
		$payout_plugins = array();

		if ( $this->affiliate_manager ) {
			if ( method_exists( $this->affiliate_manager, 'detect_form_plugins' ) ) {
				$form_plugins = $this->affiliate_manager->detect_form_plugins( true );
			}
			if ( method_exists( $this->affiliate_manager, 'detect_membership_plugins' ) ) {
				$membership_plugins = $this->affiliate_manager->detect_membership_plugins( true );
			}
			if ( method_exists( $this->affiliate_manager, 'detect_accounting_plugins' ) ) {
				$accounting_plugins = $this->affiliate_manager->detect_accounting_plugins( true );
			}
			if ( method_exists( $this->affiliate_manager, 'detect_payout_plugins' ) ) {
				$payout_plugins = $this->affiliate_manager->detect_payout_plugins( true );
			}
		}

		$form_active = 0;
		foreach ( (array) $form_plugins as $p ) {
			if ( is_array( $p ) && ! empty( $p['active'] ) ) {
				$form_active++;
			}
		}

		$membership_active = 0;
		foreach ( (array) $membership_plugins as $p ) {
			if ( is_array( $p ) && ! empty( $p['active'] ) ) {
				$membership_active++;
			}
		}

		$accounting_active = 0;
		foreach ( (array) $accounting_plugins as $p ) {
			if ( is_array( $p ) && ! empty( $p['active'] ) ) {
				$accounting_active++;
			}
		}

		// Get affiliate data for both affiliates_data and payout_data cards
		$affiliates_data = array();
		$payout_records = array();
		
		if ( $this->affiliate_manager && method_exists( $this->affiliate_manager, 'get_all_affiliates_for_sync' ) ) {
			$affiliates = $this->affiliate_manager->get_all_affiliates_for_sync( '' );
			foreach ( (array) $affiliates as $affiliate ) {
				if ( ! is_array( $affiliate ) ) {
					continue;
				}
				
				// Build affiliate data for affiliates_data card
				$affiliates_data[] = array(
					'id'              => isset( $affiliate['id'] ) ? (string) $affiliate['id'] : ( isset( $affiliate['affiliate_id'] ) ? (string) $affiliate['affiliate_id'] : '' ),
					'name'            => isset( $affiliate['name'] ) ? (string) $affiliate['name'] : '',
					'first_name'      => isset( $affiliate['first_name'] ) ? (string) $affiliate['first_name'] : '',
					'last_name'       => isset( $affiliate['last_name'] ) ? (string) $affiliate['last_name'] : '',
					'email'           => isset( $affiliate['email'] ) ? (string) $affiliate['email'] : '',
					'company'         => isset( $affiliate['company'] ) ? (string) $affiliate['company'] : ( isset( $affiliate['company_name'] ) ? (string) $affiliate['company_name'] : '' ),
					'status'          => isset( $affiliate['status'] ) ? (string) $affiliate['status'] : 'active',
					'plugin'          => isset( $affiliate['plugin'] ) ? (string) $affiliate['plugin'] : '',
					'plugin_slug'     => isset( $affiliate['plugin_slug'] ) ? (string) $affiliate['plugin_slug'] : '',
					'date_registered' => isset( $affiliate['date_registered'] ) ? (string) $affiliate['date_registered'] : current_time( 'mysql' ),
					'amount'          => isset( $affiliate['amount'] ) && is_numeric( $affiliate['amount'] ) ? (float) $affiliate['amount'] : 0,
				);
				
				// Build payout data for payout_data card
				$commission = null;
				$commission_fields = array( 'earnings', 'total_earnings', 'net_amount', 'payout_amount', 'amount', 'commission' );
				foreach ( $commission_fields as $field ) {
					if ( isset( $affiliate[ $field ] ) && is_numeric( $affiliate[ $field ] ) ) {
						$commission = (float) $affiliate[ $field ];
						break;
					}
				}
				$payout_records[] = array(
					'affiliate_id' => isset( $affiliate['id'] ) ? (string) $affiliate['id'] : ( isset( $affiliate['affiliate_id'] ) ? (string) $affiliate['affiliate_id'] : '' ),
					'user_name'   => isset( $affiliate['name'] ) ? (string) $affiliate['name'] : '',
					'user_email'  => isset( $affiliate['email'] ) ? (string) $affiliate['email'] : '',
					'commission'  => $commission,
					'status'      => isset( $affiliate['status'] ) ? (string) $affiliate['status'] : 'active',
					'date'        => isset( $affiliate['date_registered'] ) ? (string) $affiliate['date_registered'] : ( isset( $affiliate['registration_date'] ) ? (string) $affiliate['registration_date'] : current_time( 'mysql' ) ),
					'source'      => 'affiliate',
				);
			}
		}

		// Add wallet/payout plugin data to payout_records
		if ( class_exists( 'w91099ch_Wallet_Payout_Plugin_Detector' ) ) {
			$detector = new w91099ch_Wallet_Payout_Plugin_Detector();
			foreach ( (array) $payout_plugins as $slug => $plugin ) {
				$rows = $detector->get_wallet_entries_preview( (string) $slug, 500 );
				foreach ( (array) $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$payout_records[] = array(
						'user_email'       => isset( $row['user_email'] ) ? (string) $row['user_email'] : '',
						'user_name'        => isset( $row['user_name'] ) ? (string) $row['user_name'] : '',
						'commission'       => isset( $row['amount'] ) && is_numeric( $row['amount'] ) ? (float) $row['amount'] : null,
						'status'           => isset( $row['status'] ) ? (string) $row['status'] : '',
						'date'             => isset( $row['created_date'] ) ? (string) $row['created_date'] : current_time( 'mysql' ),
						'transaction_type' => isset( $row['transaction_type'] ) ? (string) $row['transaction_type'] : '',
						'plugin_slug'      => (string) $slug,
						'source'           => 'wallet',
					);
				}
			}
		}

		return array(
			'event_type'      => 'plugin_data_synced',
			'event_id'        => $event_id,
			'timestamp'       => gmdate( 'c' ),
			'site_url'        => (string) get_site_url(),
			'site_name'       => (string) get_bloginfo( 'name' ),
			'admin_email'     => sanitize_email( (string) get_option( 'admin_email', '' ) ),
			'user_profile'    => $user_profile,
			'plugin_data'     => $plugin_data,
			'team'            => $team,
			'forms_plugin'    => array(
				'total_forms'       => 0,
				'submissions_today' => 0,
				'active_forms'      => $form_active,
				'plugins'           => $form_plugins,
			),
			'membership_data' => array(
				'total_members'         => 0,
				'active_subscriptions'  => 0,
				'revenue_this_month'    => 0,
				'plugins'               => $membership_plugins,
			),
			'accounting_data' => array(
				'total_orders'     => 0,
				'revenue_today'    => 0,
				'pending_payments' => 0,
				'plugins'          => $accounting_plugins,
			),
			'affiliates_data' => $affiliates_data,
			'payout_data'     => $payout_records,
		);
	}

	public function get_webhook_configuration_status() {
		if ( ! class_exists( 'w91099ch_Webhook_Dispatcher' ) ) {
			return array(
				'configured' => false,
				'message' => 'Webhook dispatcher not available'
			);
		}
		
		return w91099ch_Webhook_Dispatcher::get_webhook_status();
	}

	public function ensure_webhook_configuration() {
		$webhook_url = get_option( 'w91099ch_master_webhook_url', '' );
		$webhook_secret = get_option( 'w91099ch_master_webhook_secret', '' );
		
		if ( '' !== $webhook_url && '' === $webhook_secret ) {
			// Auto-configure webhook secret for automatic authentication
			update_option( 'w91099ch_master_webhook_secret', 'auto_auth_webhook' );
			$this->log( 'Auto-configured webhook secret for automatic authentication' );
			return true;
		}
		
		return '' !== $webhook_url;
	}

	public function ajax_get_user_count() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$count = count_users();
		$total = isset( $count['total_users'] ) ? (int) $count['total_users'] : 0;

		wp_send_json_success(
			array(
				'total_users' => $total,
			)
		);
	}

	public function add_rewrite_rules() {
		add_rewrite_rule(
			'^w9-1099-chaser/callback/?$',
			'index.php?w91099ch_callback=1&w91099ch_connector_callback=1',
			'top'
		);

		add_rewrite_rule(
			'^w91099ch/callback/?$',
			'index.php?w91099ch_callback=1&w91099ch_connector_callback=1',
			'top'
		);
	}

	public function add_query_vars( $vars ) {
		if ( ! is_array( $vars ) ) {
			$vars = array();
		}

		if ( ! in_array( 'w91099ch_callback', $vars, true ) ) {
			$vars[] = 'w91099ch_callback';
		}

		if ( ! in_array( 'w91099ch_connector_callback', $vars, true ) ) {
			$vars[] = 'w91099ch_connector_callback';
		}

		return $vars;
	}

	public function handle_public_callback() {
		try {
			// Check for new External Connect API authorization code
			$authorization_code = filter_input( INPUT_GET, 'authorization_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$status = filter_input( INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( $authorization_code && 'connected' === $status ) {
				if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
					status_header( 403 );
					echo esc_html__( 'Forbidden', 'w9-1099-chaser' );
					exit;
				}

				$this->log( 'Processing authorization code callback' );
				$this->process_authorization_code_callback( $authorization_code );
				exit;
			}

			// Legacy encrypted credentials handling
			$encrypted_param = filter_input( INPUT_GET, 'encrypted_credentials', FILTER_UNSAFE_RAW );
			$has_credentials = is_string( $encrypted_param ) && '' !== $encrypted_param;

			$nonce_param_raw = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$nonce_param     = is_string( $nonce_param_raw ) ? sanitize_text_field( wp_unslash( $nonce_param_raw ) ) : '';

			// Check if this is a callback with credentials
			if ( ! $has_credentials || ! $nonce_param ) {
				return;
			}

			$this->log( 'handle_public_callback called with credentials' );

			if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
				status_header( 403 );
				echo esc_html__( 'Forbidden', 'w9-1099-chaser' );
				exit;
			}

			$this->log( 'Processing public callback' );
			$this->process_public_callback();
			exit;
		} catch ( Throwable $e ) {
			$this->log( 'handle_public_callback error: ' . $e->getMessage() );
			return;
		}
	}

	private function process_authorization_code_callback( $authorization_code ) {
		$this->log( 'Processing authorization code callback' );

		if ( ! $this->has_admin_consent() ) {
			status_header( 403 );
			echo esc_html__( 'Consent required', 'w9-1099-chaser' );
			exit;
		}

		try {
			$credentials = $this->api->exchange_authorization_code( $authorization_code );

			if ( ! $credentials ) {
				$this->log( 'Failed to exchange authorization code for credentials' );
				status_header( 400 );
				echo esc_html__( 'Failed to exchange authorization code', 'w9-1099-chaser' );
				exit;
			}

			$this->store_connection_data( $credentials );
			$this->ensure_webhook_configuration();
			set_transient( 'w91099ch_connection_success', true, 5 * MINUTE_IN_SECONDS );

			$redirect_url = add_query_arg(
				array(
					'page'   => 'w91099ch',
					'status' => 'connected',
				),
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $redirect_url );
			exit;
		} catch ( Throwable $e ) {
			$this->log( 'Authorization code callback error: ' . $e->getMessage() );
			status_header( 400 );
			echo esc_html__( 'Connection failed', 'w9-1099-chaser' );
			exit;
		}
	}

	private function process_public_callback() {
		$this->log( 'Processing public callback' );

		$nonce_param_raw = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$nonce_raw       = is_string( $nonce_param_raw ) ? sanitize_text_field( wp_unslash( $nonce_param_raw ) ) : '';
		if ( '' === $nonce_raw || ! wp_verify_nonce( $nonce_raw, 'w91099ch_credentials_callback' ) ) {
			status_header( 403 );
			echo esc_html__( 'Forbidden', 'w9-1099-chaser' );
			exit;
		}

		$encrypted_credentials_json_raw = filter_input( INPUT_GET, 'encrypted_credentials', FILTER_UNSAFE_RAW ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitized immediately below; value is URL-encoded JSON string.
		$encrypted_credentials_json     = is_string( $encrypted_credentials_json_raw ) ? sanitize_text_field( $encrypted_credentials_json_raw ) : '';

		if ( ! $encrypted_credentials_json ) {
			status_header( 400 );
			echo esc_html__( 'No credentials received', 'w9-1099-chaser' );
			exit;
		}

		$decoded_json          = rawurldecode( (string) $encrypted_credentials_json );
		$encrypted_credentials = json_decode( $decoded_json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $encrypted_credentials ) ) {
			status_header( 400 );
			echo esc_html__( 'Invalid credentials format', 'w9-1099-chaser' );
			exit;
		}

		$required_fields = array( 'enc_key', 'ciphertext', 'iv' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $encrypted_credentials[ $field ] ) || ! is_string( $encrypted_credentials[ $field ] ) || '' === trim( $encrypted_credentials[ $field ] ) ) {
				status_header( 400 );
				echo esc_html__( 'Invalid credentials format', 'w9-1099-chaser' );
				exit;
			}
		}

		$this->log( 'Received encrypted credentials via public/template_redirect callback' );

		$credentials = $this->process_encrypted_credentials( $encrypted_credentials );
		if ( ! $credentials ) {
			// Retry once with fresh key (handles timing issues)
			$this->log( 'First decryption attempt failed, retrying with fresh key...' );
			
			// Clear any existing key and generate a fresh one
			if ( isset( $this->encryption ) && method_exists( $this->encryption, 'clear_temporary_keys' ) ) {
				$this->encryption->clear_temporary_keys( array( get_current_user_id() ) );
			}
			
			// Generate new key pair
			if ( isset( $this->encryption ) && method_exists( $this->encryption, 'generate_key_pair' ) ) {
				$this->encryption->generate_key_pair();
			}
			
			// Retry decryption
			$credentials = $this->process_encrypted_credentials( $encrypted_credentials );
			if ( ! $credentials ) {
				// Log detailed debugging information
				$this->log( 'Failed to decrypt credentials after retry. Received data structure: ' . print_r( $encrypted_credentials, true ) );
				$this->log( 'Encryption handler available: ' . ( isset( $this->encryption ) && is_object( $this->encryption ) ? 'yes' : 'no' ) );
				
				status_header( 400 );
				echo esc_html__( 'Failed to decrypt credentials', 'w9-1099-chaser' );
				exit;
			}
			$this->log( 'Decryption succeeded on retry' );
		}

		if ( ! $this->has_admin_consent() ) {
			status_header( 403 );
			echo esc_html__( 'Consent required', 'w9-1099-chaser' );
			exit;
		}

		$this->store_connection_data( $credentials );
		set_transient( 'w91099ch_connection_success', true, 5 * MINUTE_IN_SECONDS );

		$redirect_url = add_query_arg(
			array(
				'page'   => 'w91099ch',
				'status' => 'connected',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function register_rest_routes() {
		$namespaces = array( 'w91099ch/v1', 'w9-1099-chaser/v1' );

		foreach ( $namespaces as $namespace ) {
			register_rest_route(
				$namespace,
				'/callback',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_rest_callback' ),
					'permission_callback' => array( $this, 'rest_callback_permission_check' ),
				)
			);

			register_rest_route(
				$namespace,
				'/callback',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'handle_rest_callback' ),
					'permission_callback' => array( $this, 'rest_callback_permission_check' ),
				)
			);
		}
	}

	public function rest_callback_permission_check( $request ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Check for new External Connect API authorization code
		$authorization_code = '';
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$authorization_code = (string) $request->get_param( 'authorization_code' );
		}

		if ( $authorization_code ) {
			// Authorization code callbacks don't require nonce verification
			return true;
		}

		// Legacy encrypted credentials handling
		$nonce_raw = '';
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$nonce_raw = (string) $request->get_param( 'nonce' );
		}
		$nonce = sanitize_text_field( (string) $nonce_raw );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'w91099ch_credentials_callback' ) ) {
			return new WP_Error( 'w91099ch_forbidden', esc_html__( 'Forbidden', 'w9-1099-chaser' ), array( 'status' => 403 ) );
		}

		// Allow the callback only while a connection handshake is in progress.
		// During handshake, the plugin sets a short-lived transient.
		if ( (bool) get_transient( 'w91099ch_handshake_active' ) ) {
			return true;
		}

		return new WP_Error( 'w91099ch_forbidden', esc_html__( 'Forbidden', 'w9-1099-chaser' ), array( 'status' => 403 ) );
	}

	public function handle_rest_callback( $request ) {
		$this->log( 'Handling REST API callback' );

		// Check for new External Connect API authorization code
		$authorization_code = '';
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$authorization_code = (string) $request->get_param( 'authorization_code' );
		}

		if ( $authorization_code ) {
			if ( ! $this->has_admin_consent() ) {
				return new WP_Error( 'consent_required', esc_html__( 'Consent required', 'w9-1099-chaser' ), array( 'status' => 403 ) );
			}

			try {
				$credentials = $this->api->exchange_authorization_code( $authorization_code );

				if ( ! $credentials ) {
					return new WP_Error( 'exchange_failed', esc_html__( 'Failed to exchange authorization code', 'w9-1099-chaser' ), array( 'status' => 400 ) );
				}

				$this->store_connection_data( $credentials );
				set_transient( 'w91099ch_connection_success', true, 5 * MINUTE_IN_SECONDS );

				return rest_ensure_response(
					array(
						'success'      => true,
						'redirect_url' => admin_url( 'admin.php?page=w91099ch&status=success' ),
					)
				);
			} catch ( Throwable $e ) {
				$this->log( 'REST authorization code callback error: ' . $e->getMessage() );
				return new WP_Error( 'connection_failed', esc_html__( 'Connection failed', 'w9-1099-chaser' ), array( 'status' => 400 ) );
			}
		}

		// Legacy encrypted credentials handling
		$nonce_raw = '';
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$nonce_raw = (string) $request->get_param( 'nonce' );
		}
		$nonce = sanitize_text_field( (string) $nonce_raw );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'w91099ch_credentials_callback' ) ) {
			return new WP_Error( 'w91099ch_forbidden', esc_html__( 'Forbidden', 'w9-1099-chaser' ), array( 'status' => 403 ) );
		}

		$parameters                 = $request->get_params();
		$encrypted_credentials_json = isset( $parameters['encrypted_credentials'] ) ? (string) $parameters['encrypted_credentials'] : '';

		if ( ! $encrypted_credentials_json ) {
			return new WP_Error( 'no_credentials', esc_html__( 'No credentials provided', 'w9-1099-chaser' ), array( 'status' => 400 ) );
		}

		$encrypted_credentials_json = is_string( $encrypted_credentials_json ) ? wp_unslash( $encrypted_credentials_json ) : '';
		$decoded_json               = rawurldecode( (string) $encrypted_credentials_json );
		$encrypted_credentials      = json_decode( $decoded_json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $encrypted_credentials ) ) {
			return new WP_Error( 'invalid_credentials', esc_html__( 'Invalid credentials format', 'w9-1099-chaser' ), array( 'status' => 400 ) );
		}

		$required_fields = array( 'enc_key', 'ciphertext', 'iv' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $encrypted_credentials[ $field ] ) || ! is_string( $encrypted_credentials[ $field ] ) || '' === trim( $encrypted_credentials[ $field ] ) ) {
				return new WP_Error( 'invalid_credentials', esc_html__( 'Invalid credentials format', 'w9-1099-chaser' ), array( 'status' => 400 ) );
			}
		}

		$this->log( 'Processing encrypted credentials from REST API' );

		$credentials = $this->process_encrypted_credentials( $encrypted_credentials );
		if ( ! $credentials ) {
			// Retry once with fresh key (handles timing issues)
			$this->log( 'First decryption attempt failed in REST API, retrying with fresh key...' );
			
			// Clear any existing key and generate a fresh one
			if ( isset( $this->encryption ) && method_exists( $this->encryption, 'clear_temporary_keys' ) ) {
				$this->encryption->clear_temporary_keys( array( get_current_user_id() ) );
			}
			
			// Generate new key pair
			if ( isset( $this->encryption ) && method_exists( $this->encryption, 'generate_key_pair' ) ) {
				$this->encryption->generate_key_pair();
			}
			
			// Retry decryption
			$credentials = $this->process_encrypted_credentials( $encrypted_credentials );
			if ( ! $credentials ) {
				// Log detailed debugging information
				$this->log( 'Failed to decrypt credentials in REST API after retry. Received data structure: ' . print_r( $encrypted_credentials, true ) );
				$this->log( 'Encryption handler available: ' . ( isset( $this->encryption ) && is_object( $this->encryption ) ? 'yes' : 'no' ) );
				
				return new WP_Error( 'decrypt_failed', esc_html__( 'Failed to decrypt credentials', 'w9-1099-chaser' ), array( 'status' => 400 ) );
			}
			$this->log( 'Decryption succeeded on retry in REST API' );
		}

		if ( ! $this->has_admin_consent() ) {
			return new WP_Error( 'consent_required', esc_html__( 'Consent required', 'w9-1099-chaser' ), array( 'status' => 403 ) );
		}

		$this->store_connection_data( $credentials );
		set_transient( 'w91099ch_connection_success', true, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response(
			array(
				'success'      => true,
				'redirect_url' => admin_url( 'admin.php?page=w91099ch&status=success' ),
			)
		);
	}

	public function ajax_initiate_connection() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();

		try {
			$discount_code_raw = filter_input( INPUT_POST, 'discount_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$discount_code     = is_string( $discount_code_raw ) ? sanitize_text_field( wp_unslash( $discount_code_raw ) ) : '';
			$discount_code   = is_string( $discount_code ) ? preg_replace( '/\s+/', '', $discount_code ) : '';
			$discount_code   = is_string( $discount_code ) ? substr( $discount_code, 0, 32 ) : '';
			$connection_data = $this->api->prepare_connection_request( $discount_code );

			wp_send_json_success(
				array(
					'api_url'   => $connection_data['api_url'],
					'post_data' => $connection_data['post_data'],
					'message'   => esc_html__( 'Ready to connect to MyPowerly platform', 'w9-1099-chaser' ),
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error( esc_html__( 'Connection failed. Your information was not saved. Please retry.', 'w9-1099-chaser' ) );
		}
	}

	public function ajax_get_workspaces() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();

		try {
			$credentials  = $this->ensure_valid_token();
			$access_token = isset( $credentials['access_token'] ) ? (string) $credentials['access_token'] : '';
			$access_token = trim( $access_token );
			if ( '' === $access_token ) {
				throw new Exception( 'No access token available' );
			}

			$endpoint = (string) apply_filters( 'w91099ch_workspaces_endpoint', '/api/workspaces/' );
			$endpoint = trim( $endpoint );
			if ( '' === $endpoint ) {
				$endpoint = '/api/workspaces/';
			}
			if ( $endpoint[0] !== '/' ) {
				$endpoint = '/' . $endpoint;
			}

			$base_url = rtrim( (string) $this->api->get_api_base_url(), '/' );
			if ( '' === $base_url ) {
				throw new Exception( 'Service configuration is invalid' );
			}
			$fallback_base = preg_replace( '#/v1$#', '', $base_url );
			if ( ! is_string( $fallback_base ) || '' === $fallback_base ) {
				$fallback_base = $base_url;
			}

			$urls = array_values(
				array_unique(
					array(
						$base_url . $endpoint,
						rtrim( (string) $fallback_base, '/' ) . $endpoint,
					)
				)
			);

			$response  = null;
			$last_code = 0;
			$last_body = '';

			foreach ( $urls as $api_url ) {
				$sslverify = apply_filters( 'w91099ch_sslverify', true, $api_url, $endpoint, 'GET' );
				$timeout   = (int) apply_filters( 'w91099ch_api_timeout', 15, $api_url, $endpoint, 'GET' );
				if ( $timeout <= 0 ) {
					$timeout = 15;
				}

				$response = wp_remote_get(
					$api_url,
					array(
						'headers'     => array(
							'Authorization' => 'Bearer ' . $access_token,
							'Accept'        => 'application/json',
							'User-Agent'    => 'w9-1099-chaser-WordPress/' . (string) w91099ch_VERSION,
							'Referer'       => get_site_url(),
							'Origin'        => wp_parse_url( get_site_url(), PHP_URL_HOST ),
						),
						'timeout'     => $timeout,
						'sslverify'   => (bool) $sslverify,
						'redirection' => 2,
					)
				);

				if ( is_wp_error( $response ) ) {
					$last_body = $response->get_error_message();
					$last_code = 0;
					continue;
				}

				$last_code = (int) wp_remote_retrieve_response_code( $response );
				$last_body = (string) wp_remote_retrieve_body( $response );
				if ( $last_code >= 200 && $last_code < 300 ) {
					break;
				}
			}

			if ( $last_code < 200 || $last_code >= 300 ) {
				throw new Exception( 'Request failed (HTTP ' . (int) $last_code . ')' );
			}

			$decoded = json_decode( $last_body, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new Exception( 'Unexpected response from service' );
			}

			wp_send_json_success(
				array(
					'workspaces' => $decoded,
				)
			);
		} catch ( Throwable $e ) {
			wp_send_json_error( sanitize_text_field( $e->getMessage() ) );
		}
	}

	public function process_encrypted_credentials( $encrypted_credentials ) {
		// Handle new External Connect API authorization code
		if ( is_array( $encrypted_credentials ) && isset( $encrypted_credentials['authorization_code'] ) ) {
			return $this->api->exchange_authorization_code( $encrypted_credentials['authorization_code'] );
		}

		// Validate input structure before attempting decryption (legacy)
		if ( ! is_array( $encrypted_credentials ) ) {
			$this->log( 'Invalid credentials format: Expected array, got ' . gettype( $encrypted_credentials ) );
			return false;
		}

		$required_fields = array( 'enc_key', 'ciphertext', 'iv' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $encrypted_credentials[ $field ] ) || ! is_string( $encrypted_credentials[ $field ] ) || '' === trim( $encrypted_credentials[ $field ] ) ) {
				$this->log( 'Missing or empty required field: ' . $field );
				return false;
			}
		}

		return $this->api->process_encrypted_credentials( $encrypted_credentials );
	}

	public function ajax_process_credentials() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();

		$encrypted_param_raw = filter_input( INPUT_POST, 'encrypted_credentials', FILTER_UNSAFE_RAW );
		if ( null === $encrypted_param_raw ) {
			wp_send_json_error( esc_html__( 'No credentials provided', 'w9-1099-chaser' ) );
		}

		try {
			$encrypted_json_param  = sanitize_textarea_field( wp_unslash( (string) $encrypted_param_raw ) );
			$encrypted_credentials = json_decode( (string) $encrypted_json_param, true );

			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $encrypted_credentials ) ) {
				throw new Exception( 'Invalid credentials format' );
			}

			$required_fields = array( 'enc_key', 'ciphertext', 'iv' );
			foreach ( $required_fields as $field ) {
				if ( ! isset( $encrypted_credentials[ $field ] ) || ! is_string( $encrypted_credentials[ $field ] ) || '' === trim( $encrypted_credentials[ $field ] ) ) {
					throw new Exception( 'Invalid credentials format' );
				}
			}

			$credentials = $this->process_encrypted_credentials( $encrypted_credentials );

			if ( $credentials ) {
				$this->store_connection_data( $credentials );
				wp_send_json_success(
					array(
						'message' => esc_html__( 'Successfully connected to MyPowerly platform!', 'w9-1099-chaser' ),
					)
				);
			} else {
				throw new Exception( 'Failed to decrypt credentials' );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( esc_html__( 'Connection failed. Your information was not saved. Please retry.', 'w9-1099-chaser' ) );
		}
	}

	public function store_connection_data( $credentials ) {
		$credentials = $this->sanitize_credentials_for_storage( $credentials );
		$stored      = ( isset( $this->encryption ) && is_object( $this->encryption ) && method_exists( $this->encryption, 'encrypt_credentials_array' ) )
			? $this->encryption->encrypt_credentials_array( $credentials )
			: $credentials;
		update_option( 'w91099ch_credentials', $stored );
		update_option( 'w91099ch_connected', true );
		update_option( 'w91099ch_site_url', $credentials['site_url'] );
		update_option( 'w91099ch_connected_at', current_time( 'mysql' ) );
		update_option( 'w91099ch_user_email', $credentials['user_email'] );
		update_option( 'w91099ch_last_checked', time() );
		update_option( 'w91099ch_credentials_valid', true );

		if ( isset( $credentials['client_id'] ) ) {
			$val = ( isset( $stored['client_id'] ) && is_string( $stored['client_id'] ) ) ? $stored['client_id'] : $credentials['client_id'];
			update_option( 'w91099ch_client_id', $val );
		}
		if ( isset( $credentials['client_secret'] ) ) {
			$val = ( isset( $stored['client_secret'] ) && is_string( $stored['client_secret'] ) ) ? $stored['client_secret'] : $credentials['client_secret'];
			update_option( 'w91099ch_client_secret', $val );
		}
		if ( isset( $credentials['access_token'] ) ) {
			$val = ( isset( $stored['access_token'] ) && is_string( $stored['access_token'] ) ) ? $stored['access_token'] : $credentials['access_token'];
			update_option( 'w91099ch_access_token', $val );
		}
		if ( isset( $credentials['refresh_token'] ) ) {
			$val = ( isset( $stored['refresh_token'] ) && is_string( $stored['refresh_token'] ) ) ? $stored['refresh_token'] : $credentials['refresh_token'];
			update_option( 'w91099ch_refresh_token', $val );
		}
		if ( isset( $credentials['workspace_id'] ) ) {
			update_option( 'w91099ch_workspace_id', sanitize_text_field( (string) $credentials['workspace_id'] ) );
		}
		if ( isset( $credentials['team_id'] ) ) {
			update_option( 'w91099ch_team_id', sanitize_text_field( (string) $credentials['team_id'] ) );
		}
		if ( isset( $credentials['workflow_id'] ) ) {
			update_option( 'w91099ch_workflow_id', sanitize_text_field( (string) $credentials['workflow_id'] ) );
		}
		if ( isset( $credentials['webhook_url'] ) ) {
			$webhook_url = esc_url_raw( (string) $credentials['webhook_url'] );
			update_option( 'w91099ch_webhook_url', $webhook_url );
			// Keep automatic webhook credentials separate from manual webhook settings.
			$webhook_secret = isset( $credentials['webhook_secret'] ) ? sanitize_text_field( (string) $credentials['webhook_secret'] ) : '';
			if ( '' !== $webhook_secret ) {
				update_option( 'w91099ch_webhook_secret', $webhook_secret );
			} else {
				delete_option( 'w91099ch_webhook_secret' );
			}

			// Backward compatibility: only seed master webhook values if user has not configured them yet.
			$current_master_url = esc_url_raw( (string) get_option( 'w91099ch_master_webhook_url', '' ) );
			if ( '' === $current_master_url ) {
				update_option( 'w91099ch_master_webhook_url', $webhook_url );
			}

			$current_master_secret = sanitize_text_field( (string) get_option( 'w91099ch_master_webhook_secret', '' ) );
			if ( '' === $current_master_secret && '' !== $webhook_secret ) {
				update_option( 'w91099ch_master_webhook_secret', $webhook_secret );
			}
		}

		$user_id = get_current_user_id();
		delete_transient( 'w91099ch_pending_credentials_' . $user_id );
		$this->encryption->clear_temporary_keys();
	}

	private function sanitize_credentials_for_storage( $credentials ) {
		if ( ! is_array( $credentials ) ) {
			return array();
		}

		$out = $credentials;

		if ( isset( $out['site_url'] ) ) {
			$out['site_url'] = esc_url_raw( (string) $out['site_url'] );
		}
		if ( isset( $out['user_email'] ) ) {
			$out['user_email'] = sanitize_email( (string) $out['user_email'] );
		}

		$token_fields = array(
			'client_id',
			'client_secret',
			'access_token',
			'refresh_token',
			'api_key',
		);
		foreach ( $token_fields as $field ) {
			if ( isset( $out[ $field ] ) ) {
				$out[ $field ] = sanitize_text_field( (string) $out[ $field ] );
			}
		}

		if ( isset( $out['expires_in'] ) ) {
			$out['expires_in'] = absint( $out['expires_in'] );
		}
		if ( isset( $out['expires_at'] ) ) {
			$out['expires_at'] = sanitize_text_field( (string) $out['expires_at'] );
		}
		if ( isset( $out['webhook_url'] ) ) {
			$out['webhook_url'] = esc_url_raw( (string) $out['webhook_url'] );
		}
		if ( isset( $out['webhook_secret'] ) ) {
			$out['webhook_secret'] = sanitize_text_field( (string) $out['webhook_secret'] );
		}

		return $out;
	}

	public function ajax_disconnect() {
		$nonce_param_raw = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( null === $nonce_param_raw ) {
			$nonce_param_raw = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}
		$nonce = is_string( $nonce_param_raw ) ? sanitize_text_field( wp_unslash( $nonce_param_raw ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'w91099ch_disconnect_nonce' ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->clear_connection_data();
		wp_send_json_success( array( 'message' => esc_html__( 'Disconnected successfully', 'w9-1099-chaser' ) ) );
	}

	private function clear_connection_data() {
		// Try to disconnect from External Connect API
		$admin_email = get_option( 'admin_email' );
		$site_url = get_site_url();
		if ( $admin_email && $site_url ) {
			$this->api->disconnect_external_connection( $admin_email, $site_url );
		}

		delete_option( 'w91099ch_credentials' );
		delete_option( 'w91099ch_connected' );
		delete_option( 'w91099ch_site_url' );
		delete_option( 'w91099ch_connected_at' );
		delete_option( 'w91099ch_user_email' );
		delete_option( 'w91099ch_client_id' );
		delete_option( 'w91099ch_client_secret' );
		delete_option( 'w91099ch_access_token' );
		delete_option( 'w91099ch_refresh_token' );
		delete_option( 'w91099ch_last_checked' );
		delete_option( 'w91099ch_credentials_valid' );
		delete_option( 'w91099ch_workspace_id' );
		delete_option( 'w91099ch_team_id' );
		delete_option( 'w91099ch_workflow_id' );
		delete_option( 'w91099ch_webhook_url' );
		delete_option( 'w91099ch_master_webhook_url' );

		// Automatically disable payout limits when disconnecting
		update_option( 'w91099ch_payment_limit_enabled', false );

		$this->encryption->clear_temporary_keys();
		$user_id = get_current_user_id();
		delete_transient( 'w91099ch_pending_credentials_' . $user_id );

		delete_transient( 'w91099ch_handshake_active' );
	}

	public function is_connected() {
		$flag = (bool) get_option( 'w91099ch_connected', false );
		if ( ! $flag ) {
			return false;
		}

		$credentials      = $this->get_credentials();
		$has_access_token = is_array( $credentials )
			&& isset( $credentials['access_token'] )
			&& is_string( $credentials['access_token'] )
			&& '' !== trim( (string) $credentials['access_token'] );

		$legacy_access_token     = (string) get_option( 'w91099ch_access_token', '' );
		if ( isset( $this->encryption ) && is_object( $this->encryption ) && method_exists( $this->encryption, 'decrypt_string' ) ) {
			$legacy_access_token = (string) $this->encryption->decrypt_string( $legacy_access_token );
		}
		$has_legacy_access_token = '' !== trim( $legacy_access_token );

		if ( $has_access_token || $has_legacy_access_token ) {
			return true;
		}

		$this->log( 'Connected flag set but no usable token found; resetting connection state.' );
		update_option( 'w91099ch_connected', false );
		return false;
	}

	public function get_pending_credentials() {
		$user_id       = get_current_user_id();
		$transient_key = 'w91099ch_pending_credentials_' . $user_id;
		$creds         = get_transient( $transient_key );

		if ( ! $creds ) {
			$creds = get_transient( 'w91099ch_pending_credentials_' . $user_id );
		}

		if ( $creds ) {
			$this->log( 'Retrieved pending credentials from transient' );
			delete_transient( $transient_key );
		}

		return $creds;
	}

	public function get_credentials() {
		$creds = get_option( 'w91099ch_credentials', array() );
		if ( is_array( $creds ) && ! empty( $creds ) ) {
			if ( isset( $this->encryption ) && is_object( $this->encryption ) && method_exists( $this->encryption, 'decrypt_credentials_array' ) ) {
				$decrypted = $this->encryption->decrypt_credentials_array( $creds );
				$decrypted = $this->normalize_webhook_fields( $decrypted );
				// Opportunistic migration: if secrets are still plaintext, re-save encrypted.
				if ( method_exists( $this->encryption, 'encrypt_credentials_array' ) ) {
					$reenc = $this->encryption->encrypt_credentials_array( $decrypted );
					if ( is_array( $reenc ) && $reenc !== $creds ) {
						update_option( 'w91099ch_credentials', $reenc );
					}
				}
				return $decrypted;
			}
			return $this->normalize_webhook_fields( $creds );
		}

		$legacy_access_token  = (string) get_option( 'w91099ch_access_token', '' );
		$legacy_refresh_token = (string) get_option( 'w91099ch_refresh_token', '' );
		$legacy_client_id     = (string) get_option( 'w91099ch_client_id', '' );
		$legacy_client_secret = (string) get_option( 'w91099ch_client_secret', '' );
		if ( isset( $this->encryption ) && is_object( $this->encryption ) && method_exists( $this->encryption, 'decrypt_string' ) ) {
			$legacy_access_token  = (string) $this->encryption->decrypt_string( $legacy_access_token );
			$legacy_refresh_token = (string) $this->encryption->decrypt_string( $legacy_refresh_token );
			$legacy_client_id     = (string) $this->encryption->decrypt_string( $legacy_client_id );
			$legacy_client_secret = (string) $this->encryption->decrypt_string( $legacy_client_secret );
		}

		$legacy_access_token  = trim( $legacy_access_token );
		$legacy_refresh_token = trim( $legacy_refresh_token );
		$legacy_client_id     = trim( $legacy_client_id );
		$legacy_client_secret = trim( $legacy_client_secret );

		if ( '' === $legacy_access_token && '' === $legacy_refresh_token ) {
			return is_array( $creds ) ? $creds : array();
		}

		$out = array();
		if ( '' !== $legacy_access_token ) {
			$out['access_token'] = $legacy_access_token;
		}
		if ( '' !== $legacy_refresh_token ) {
			$out['refresh_token'] = $legacy_refresh_token;
		}
		if ( '' !== $legacy_client_id ) {
			$out['client_id'] = $legacy_client_id;
		}
		if ( '' !== $legacy_client_secret ) {
			$out['client_secret'] = $legacy_client_secret;
		}

		return $this->normalize_webhook_fields( $out );
	}

	private function normalize_webhook_fields( $credentials ) {
		if ( ! is_array( $credentials ) ) {
			return array();
		}

		if ( ! isset( $credentials['webhook_url'] ) || '' === trim( (string) $credentials['webhook_url'] ) ) {
			$url_candidates = array(
				isset( $credentials['webhook_endpoint'] ) ? $credentials['webhook_endpoint'] : '',
				isset( $credentials['wordpress_webhook_url'] ) ? $credentials['wordpress_webhook_url'] : '',
				isset( $credentials['webhook']['url'] ) ? $credentials['webhook']['url'] : '',
				isset( $credentials['webhook']['webhook_url'] ) ? $credentials['webhook']['webhook_url'] : '',
			);
			foreach ( $url_candidates as $candidate ) {
				if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
					$credentials['webhook_url'] = trim( $candidate );
					break;
				}
			}
		}

		if ( ! isset( $credentials['webhook_secret'] ) || '' === trim( (string) $credentials['webhook_secret'] ) ) {
			$secret_candidates = array(
				isset( $credentials['webhook_signing_secret'] ) ? $credentials['webhook_signing_secret'] : '',
				isset( $credentials['webhook']['secret'] ) ? $credentials['webhook']['secret'] : '',
				isset( $credentials['webhook']['webhook_secret'] ) ? $credentials['webhook']['webhook_secret'] : '',
			);
			foreach ( $secret_candidates as $candidate ) {
				if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
					$credentials['webhook_secret'] = trim( $candidate );
					break;
				}
			}
		}

		return $credentials;
	}

	private function ensure_valid_token() {
		if ( ! $this->has_admin_consent() ) {
			throw new Exception( 'Consent required' );
		}

		$credentials = $this->get_credentials();

		if ( empty( $credentials ) ) {
			throw new Exception( 'No credentials found' );
		}

		$expires_at = isset( $credentials['expires_at'] ) ? $credentials['expires_at'] : null;
		if ( $expires_at && strtotime( $expires_at ) <= ( time() + 300 ) ) {
			$this->log( 'Token near expiry, refreshing...' );
			if ( ! $this->refresh_access_token() ) {
				throw new Exception( 'Token refresh failed' );
			}
			return $this->get_credentials();
		}

		if ( ! $expires_at && ! $this->validate_credentials() ) {
			throw new Exception( 'Token validation failed' );
		}

		return $credentials;
	}

	public function refresh_access_token() {
		if ( ! $this->has_admin_consent() ) {
			throw new Exception( 'Consent required' );
		}

		$credentials = $this->get_credentials();

		if ( empty( $credentials['refresh_token'] ) ) {
			$this->log( 'Cannot refresh token - missing refresh_token' );
			return false;
		}

		try {
			$refresh_url = $this->api->get_api_base_url() . '/api/auth/token/refresh/';

			$this->log( 'Refreshing token at: ' . $refresh_url );

			$response = wp_remote_post(
				$refresh_url,
				array(
					'headers'   => array(
						'Content-Type' => 'application/json',
					),
					'body'      => wp_json_encode(
						array(
							'refresh' => $credentials['refresh_token'],
						)
					),
					'timeout'   => 10,
					'sslverify' => true,
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->log( 'Token refresh failed - ' . $response->get_error_message() );
				return false;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			$this->log( 'Token refresh response - HTTP ' . $response_code );

			$token_data = json_decode( $response_body, true );

			if ( JSON_ERROR_NONE === json_last_error() && is_array( $token_data ) && 200 === $response_code && isset( $token_data['access'] ) ) {
				$credentials['access_token'] = sanitize_text_field( (string) $token_data['access'] );

				if ( isset( $token_data['refresh'] ) ) {
					$credentials['refresh_token'] = sanitize_text_field( (string) $token_data['refresh'] );
				}

				$credentials['expires_in'] = isset( $token_data['expires_in'] ) ? absint( $token_data['expires_in'] ) : 3600;
				$credentials['expires_at'] = gmdate( 'Y-m-d H:i:s', time() + (int) $credentials['expires_in'] );

				$credentials = $this->sanitize_credentials_for_storage( $credentials );
				$stored      = ( isset( $this->encryption ) && is_object( $this->encryption ) && method_exists( $this->encryption, 'encrypt_credentials_array' ) )
					? $this->encryption->encrypt_credentials_array( $credentials )
					: $credentials;
				update_option( 'w91099ch_credentials', $stored );
				$stored_access = isset( $stored['access_token'] ) ? (string) $stored['access_token'] : (string) $credentials['access_token'];
				update_option( 'w91099ch_access_token', $stored_access );

				if ( isset( $token_data['refresh'] ) ) {
					$stored_refresh = isset( $stored['refresh_token'] ) ? (string) $stored['refresh_token'] : (string) $credentials['refresh_token'];
					update_option( 'w91099ch_refresh_token', $stored_refresh );
				}

				$this->log( 'Access token refreshed successfully' );
				return true;
			} else {
				$this->log( 'Token refresh failed with HTTP ' . $response_code );

				if ( 401 === $response_code || 400 === $response_code ) {
					update_option( 'w91099ch_credentials_valid', false );
				}
				return false;
			}
		} catch ( Exception $e ) {
			$this->log( 'Token refresh exception - ' . $e->getMessage() );
			return false;
		}
	}

	public function ajax_sync_profile() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();

		try {
			$this->log( 'Profile sync request received' );
			$credentials  = $this->ensure_valid_token();
			$access_token = $credentials['access_token'];

			$current_user = wp_get_current_user();
			$user_email   = $current_user->user_email;

			$client_type_raw = filter_input( INPUT_POST, 'client_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$client_type     = is_string( $client_type_raw ) ? sanitize_text_field( wp_unslash( $client_type_raw ) ) : 'individual';
			$this->log( 'Received client_type from POST: ' . $client_type );

			$allowed_client_types = array( 'individual', 'c-corporation', 's-corporation', 'partnership', 'fiduciary', 'exempt_organization' );
			if ( ! in_array( $client_type, $allowed_client_types, true ) ) {
				$this->log( 'Invalid client type received: ' . $client_type );
				throw new Exception( 'Invalid client type selected' );
			}

			$this->log( 'Starting profile sync for user: ' . $user_email . ' with client type: ' . $client_type );

			$search_url = $this->api->get_api_base_url() . '/api/clients/contacts/search/?search=' . rawurlencode( $user_email );

			$this->log( 'Searching for existing client: ' . $search_url );

			$search_response = wp_remote_get(
				$search_url,
				array(
					'headers'   => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
						'User-Agent'    => 'w9-1099-chaser-WordPress/1.0.0',
					),
					'timeout'   => 10,
					'sslverify' => true,
				)
			);

			$existing_client_id = null;

			if ( ! is_wp_error( $search_response ) ) {
				$search_body = wp_remote_retrieve_body( $search_response );
				$search_data = json_decode( $search_body, true );

				if ( json_last_error() === JSON_ERROR_NONE && is_array( $search_data ) && count( $search_data ) > 0 && is_array( $search_data[0] ) && isset( $search_data[0]['id'] ) ) {
					$existing_client_id = $search_data[0]['id'];
					$this->log( 'Found existing client ID: ' . $existing_client_id );
				}
			}

			$first_name = ( '' !== (string) $current_user->first_name ) ? $current_user->first_name : 'WordPress';
			$last_name  = ( '' !== (string) $current_user->last_name ) ? $current_user->last_name : 'User';

			$profile_data = array(
				'client_type'     => $client_type,
				'email'           => $user_email,
				'first_name'      => $first_name,
				'last_name'       => $last_name,
				'status'          => 'incomplete',
				'nickname'        => get_bloginfo( 'name' ),
				'company_name'    => get_site_url(),
				'fictitious_name' => get_bloginfo( 'description' ),
				'company_email'   => get_option( 'admin_email' ),
				'office_tel'      => 'WP-' . get_bloginfo( 'version' ),
				'cell'            => 'PHP-' . phpversion(),
				'fax'             => 'TZ-' . get_option( 'timezone_string' ),
				'extension'       => get_bloginfo( 'language' ),
				'notes'           => wp_json_encode(
					array(
						'wordpress_details' => array(
							'site_title'     => get_bloginfo( 'name' ),
							'tagline'        => get_bloginfo( 'description' ),
							'home_url'       => home_url(),
							'admin_email'    => get_option( 'admin_email' ),
							'version'        => get_bloginfo( 'version' ),
							'language'       => get_bloginfo( 'language' ),
							'timezone'       => get_option( 'timezone_string' ),
							'date_format'    => get_option( 'date_format' ),
							'time_format'    => get_option( 'time_format' ),
							'start_of_week'  => get_option( 'start_of_week' ),
							'membership'     => get_option( 'users_can_register' ) ? 'open' : 'closed',
							'default_role'   => get_option( 'default_role' ),
							'total_users'    => count_users()['total_users'],
							'post_count'     => wp_count_posts()->publish,
							'page_count'     => wp_count_posts( 'page' )->publish,
							'plugin_count'   => count( get_option( 'active_plugins', array() ) ),
							'theme'          => get_stylesheet(),
							'is_multisite'   => is_multisite(),
							'is_ssl'         => is_ssl(),
							'sync_timestamp' => time(),
							'sync_version'   => w91099ch_VERSION,
						),
						'user_info'         => array(
							'user_id'         => $current_user->ID,
							'user_roles'      => $current_user->roles,
							'registered_date' => $current_user->user_registered,
						),
					)
				),
				'fiscal_year_end' => gmdate( 'Y-m-d', time() ),
			);

			if ( $existing_client_id ) {
				$api_url         = $this->api->get_api_base_url() . '/api/clients/contacts/' . $existing_client_id . '/';
				$method          = 'PUT';
				$success_message = 'Profile updated successfully!';
				$this->log( 'Updating existing client: ' . $api_url );
			} else {
				$api_url         = $this->api->get_api_base_url() . '/api/clients/contacts/';
				$method          = 'POST';
				$success_message = 'Profile created successfully!';
				$this->log( 'Creating new client: ' . $api_url );
			}

			$response = wp_remote_request(
				$api_url,
				array(
					'method'    => $method,
					'headers'   => array(
						'Authorization'   => 'Bearer ' . $access_token,
						'Content-Type'    => 'application/json',
						'User-Agent'      => 'w9-1099-chaser-WordPress/1.0.0',
						'Referer'         => get_site_url(),
						'Origin'          => wp_parse_url( get_site_url(), PHP_URL_HOST ),
						'Idempotency-Key' => hash( 'sha256', $api_url . '|' . $method . '|' . wp_json_encode( $profile_data ) ),
					),
					'body'      => wp_json_encode( $profile_data ),
					'timeout'   => 10,
					'sslverify' => true,
				)
			);

			if ( is_wp_error( $response ) ) {
				$err_msg = sanitize_text_field( wp_strip_all_tags( $response->get_error_message() ) );
				throw new Exception( 'API request failed: ' . $err_msg );
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			$this->log( 'Profile sync response code: ' . $response_code );

			if ( 200 === $response_code || 201 === $response_code ) {
				update_option( 'w91099ch_profile_last_sync', time() );

				$profile_action = $existing_client_id ? 'updated' : 'created';
				$profile_summary = array(
					'action'      => $profile_action,
					'client_id'   => $existing_client_id,
					'client_type' => $client_type,
					'user_email'  => $user_email,
				);
				$profile_full_payload = array(
					'data'    => $profile_data,
					'profile' => $profile_summary,
				);
				$webhook_status = $this->dispatch_card_rows_webhook(
					'profile_synced',
					'user_profile',
					array( $profile_data ),
					$profile_summary,
					'w91099ch_sync_profile',
					$profile_full_payload
				);

				wp_send_json_success(
					array(
						'message'   => $success_message,
						'action'    => $profile_action,
						'client_id' => $existing_client_id,
						'webhook_status' => $webhook_status,
					)
				);
			}

			throw new Exception( 'Request failed (HTTP ' . (int) $response_code . '). Your information was not saved. Please retry.' );

		} catch ( Exception $e ) {
			wp_send_json_error( esc_html__( 'Connection failed. Your information was not saved. Please retry.', 'w9-1099-chaser' ) );
		}
	}

	public function ajax_sync_w9_payee() {
		$nonce_param_raw = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( null === $nonce_param_raw ) {
			$nonce_param_raw = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}
		$nonce = is_string( $nonce_param_raw ) ? sanitize_text_field( wp_unslash( $nonce_param_raw ) ) : '';
		$nonce_ok = (bool) ( $nonce && wp_verify_nonce( $nonce, 'w91099ch_w9_form_nonce' ) );
		if ( ! $nonce_ok ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();

		try {
			$credentials  = $this->ensure_valid_token();
			$access_token = isset( $credentials['access_token'] ) ? $credentials['access_token'] : '';
			if ( empty( $access_token ) ) {
				throw new Exception( 'No access token available' );
			}

			// phpcs:disable WordPress.Security.ValidatedSanitizedInput
			$raw_data = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitized immediately below (supports array or JSON string).
			// phpcs:enable WordPress.Security.ValidatedSanitizedInput
			if ( is_string( $raw_data ) ) {
				$raw_data = sanitize_text_field( $raw_data );
			}
			if ( is_string( $raw_data ) ) {
				$decoded  = json_decode( $raw_data, true );
				$raw_data = is_array( $decoded ) ? $decoded : array();
			}
			$data = is_array( $raw_data ) ? $raw_data : array();

			$name_on_tax_return         = sanitize_text_field( $data['name_on_tax_return'] ?? '' );
			$federal_tax_classification = sanitize_text_field( $data['federal_tax_classification'] ?? '' );
			$address                    = sanitize_text_field( $data['address'] ?? '' );
			$city                       = sanitize_text_field( $data['city'] ?? '' );
			$state                      = sanitize_text_field( $data['state'] ?? '' );
			$zip_code                   = sanitize_text_field( $data['zip_code'] ?? '' );
			$tin_type                   = sanitize_text_field( $data['tin_type'] ?? '' );
			$signature_data             = isset( $data['signature_data'] ) ? (string) $data['signature_data'] : '';
			$date                       = sanitize_text_field( $data['date'] ?? '' );
			$llc_classification         = sanitize_text_field( $data['llc_classification'] ?? '' );
			$certification_name         = sanitize_text_field( $data['certification_name'] ?? '' );

			$missing = array();
			if ( $name_on_tax_return === '' ) {
				$missing[] = 'name_on_tax_return';
			}
			if ( $federal_tax_classification === '' ) {
				$missing[] = 'federal_tax_classification';
			}
			if ( $address === '' ) {
				$missing[] = 'address';
			}
			if ( $city === '' ) {
				$missing[] = 'city';
			}
			if ( $state === '' ) {
				$missing[] = 'state';
			}
			if ( $zip_code === '' ) {
				$missing[] = 'zip_code';
			}
			if ( $tin_type === '' ) {
				$missing[] = 'tin_type';
			}
			if ( $signature_data === '' ) {
				$missing[] = 'signature';
			}
			if ( $date === '' ) {
				$missing[] = 'date';
			}

			if ( ! empty( $missing ) ) {
				wp_send_json_error(
					array(
						'message'        => 'Missing required fields',
						'missing_fields' => $missing,
					)
				);
			}

			$invalid = array();

			$state_norm = strtoupper( preg_replace( '/[^A-Za-z]/', '', $state ) );
			if ( ! preg_match( '/^[A-Z]{2}$/', $state_norm ) ) {
				$invalid[] = 'state';
			}

			$zip_digits = preg_replace( '/\D+/', '', $zip_code );
			if ( ! preg_match( '/^(\d{5}|\d{9})$/', $zip_digits ) ) {
				$invalid[] = 'zip_code';
			}

			$date_norm = $date;
			$date_dt   = DateTime::createFromFormat( 'Y-m-d', $date_norm );
			if ( ! ( $date_dt && $date_dt->format( 'Y-m-d' ) === $date_norm ) ) {
				$date_dt = DateTime::createFromFormat( 'm/d/Y', $date_norm );
			}
			if ( ! ( $date_dt && $date_dt->format( 'Y-m-d' ) ) ) {
				$date_dt = DateTime::createFromFormat( 'n/j/Y', $date_norm );
			}
			$date_ok = (bool) ( $date_dt && $date_dt->format( 'Y-m-d' ) );
			if ( ! $date_ok ) {
				$invalid[] = 'date';
			}

			if ( ! empty( $invalid ) ) {
				wp_send_json_error(
					array(
						'message'        => 'Invalid field values',
						'invalid_fields' => $invalid,
					)
				);
			}

			$state    = $state_norm;
			$zip_code = $zip_digits;
			$date     = $date_ok && $date_dt ? $date_dt->format( 'Y-m-d' ) : $date_norm;

			$signature_format = 'image/png';
			$signature_base64 = $signature_data;
			if ( strpos( $signature_data, 'data:' ) === 0 ) {
				$parts = explode( ',', $signature_data, 2 );
				if ( count( $parts ) === 2 ) {
					$meta             = $parts[0];
					$signature_base64 = $parts[1];
					if ( preg_match( '#^data:([^;]+);base64$#', $meta, $m ) ) {
						$signature_format = $m[1];
					}
				}
			}

			$payload = array(
				'data'   => array(
					'name_on_tax_return'         => $name_on_tax_return,
					'business_name'              => sanitize_text_field( $data['business_name'] ?? '' ),
					'federal_tax_classification' => $federal_tax_classification,
					'llc_classification'         => $llc_classification,
					'exempt_payee_code'          => sanitize_text_field( $data['exempt_payee_code'] ?? '' ),
					'exemption_from_fatca_code'  => sanitize_text_field( $data['exemption_from_fatca_code'] ?? '' ),
					'address'                    => $address,
					'city'                       => $city,
					'state'                      => $state,
					'zip_code'                   => $zip_code,
					'requester_name_address'     => sanitize_text_field( $data['requester_name_address'] ?? '' ),
					'account_numbers'            => sanitize_text_field( $data['account_numbers'] ?? '' ),
					'tin_type'                   => strtoupper( $tin_type ),
					'certification_name'         => $certification_name,
					'signature'                  => array(
						'type'   => 'digital',
						'data'   => $signature_base64,
						'format' => $signature_format,
					),
					'date'                       => $date,
				),
				'source' => 'wordpress_W9',
			);

			$payload = $this->scrub_prohibited_w9_fields( $payload );

			$api_url   = $this->api->get_api_base_url() . '/api/w9-1099-chaser/payees/';
			$sslverify = apply_filters( 'w91099ch_sslverify', true, $api_url, '/api/w9-1099-chaser/payees/', 'POST' );
			$timeout   = (int) apply_filters( 'w91099ch_api_timeout', 15, $api_url, '/api/w9-1099-chaser/payees/', 'POST' );
			if ( 0 >= $timeout ) {
				$timeout = 15;
			}

			$response = wp_remote_post(
				$api_url,
				array(
					'headers'   => array(
						'Authorization'   => 'Bearer ' . $access_token,
						'Content-Type'    => 'application/json',
						'User-Agent'      => 'w9-1099-chaser-WordPress/1.0',
						'Referer'         => get_site_url(),
						'Origin'          => wp_parse_url( get_site_url(), PHP_URL_HOST ),
						'Idempotency-Key' => hash( 'sha256', $api_url . '|POST|' . wp_json_encode( $payload ) ),
					),
					'body'      => wp_json_encode( $payload ),
					'timeout'   => $timeout,
					'sslverify' => (bool) $sslverify,
				)
			);

			if ( is_wp_error( $response ) ) {
				$err_msg = sanitize_text_field( wp_strip_all_tags( $response->get_error_message() ) );
				throw new Exception( 'API request failed: ' . $err_msg );
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
			$this->log( 'W-9 sync response code: ' . (int) $response_code );

			if ( 200 === $response_code || 201 === $response_code ) {
				$webhook_w9_data = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();
				if ( isset( $webhook_w9_data['signature'] ) ) {
					unset( $webhook_w9_data['signature'] );
				}

				$w9_summary = array(
					'payee_name'  => isset( $webhook_w9_data['name_on_tax_return'] ) ? (string) $webhook_w9_data['name_on_tax_return'] : '',
					'payee_email' => isset( $webhook_w9_data['company_email'] ) ? (string) $webhook_w9_data['company_email'] : '',
					'tin_type'    => isset( $webhook_w9_data['tin_type'] ) ? (string) $webhook_w9_data['tin_type'] : '',
				);

				$w9_payload = array(
					'event_type'     => 'w9_payee_synced',
					'timestamp'      => gmdate( 'c' ),
					'site_url'       => (string) get_site_url(),
					'site_name'      => (string) get_bloginfo( 'name' ),
					'admin_email'    => sanitize_email( (string) get_option( 'admin_email', '' ) ),
					'sheet_tab'      => 'w9_form_data',
					'tab'            => 'w9_form_data',
					'sheet'          => 'w9_form_data',
					'tab_name'       => 'w9_form_data',
					'sheet_name'     => 'w9_form_data',
					'worksheet'      => 'w9_form_data',
					'target_tab'     => 'w9_form_data',
					'card_key'       => 'w9_form_data',
					'context_action' => 'w91099ch_sync_w9_payee',
					'sync_scope'     => 'row',
					'row_index'      => 1,
					'w9_form_data'   => $webhook_w9_data,
				);

				$row_flat = $this->flatten_sheet_row_fields( $webhook_w9_data, 'row_' );
				foreach ( $row_flat as $flat_key => $flat_value ) {
					$w9_payload[ $flat_key ] = $flat_value;
				}
				$summary_flat = $this->flatten_sheet_row_fields( $w9_summary, 'summary_' );
				foreach ( $summary_flat as $flat_key => $flat_value ) {
					$w9_payload[ $flat_key ] = $flat_value;
				}

				$webhook_status = w91099ch_Webhook_Dispatcher::dispatch_raw_payload(
					$w9_payload,
					'w9_payee_synced'
				);

				wp_send_json_success(
					array(
						'message' => esc_html__( 'W-9 data synced successfully!', 'w9-1099-chaser' ),
						'webhook_status' => $webhook_status,
					)
				);
			}

			$service_message = '';
			if ( is_string( $response_body ) && '' !== $response_body ) {
				$decoded_body = json_decode( $response_body, true );
				if ( is_array( $decoded_body ) ) {
					if ( isset( $decoded_body['message'] ) && is_string( $decoded_body['message'] ) ) {
						$service_message = $decoded_body['message'];
					} elseif ( isset( $decoded_body['detail'] ) && is_string( $decoded_body['detail'] ) ) {
						$service_message = $decoded_body['detail'];
					} elseif ( isset( $decoded_body['error'] ) && is_string( $decoded_body['error'] ) ) {
						$service_message = $decoded_body['error'];
					}
				}
			}

			if ( '' !== $service_message ) {
				throw new Exception( 'Request failed (HTTP ' . (int) $response_code . '): ' . $service_message );
			}

			throw new Exception( 'Request failed (HTTP ' . (int) $response_code . '). Your information was not saved. Please retry.' );

		} catch ( Throwable $e ) {
			$this->log( 'W-9 sync error: ' . $e->getMessage() );
			$msg   = sanitize_text_field( wp_strip_all_tags( trim( (string) $e->getMessage() ) ) );
			$error = array(
				'message' => '' !== $msg ? $msg : esc_html__( 'Connection failed. Your information was not saved. Please retry.', 'w9-1099-chaser' ),
			);
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$error['debug'] = sanitize_text_field( wp_strip_all_tags( (string) $e->getMessage() ) );
			}
			wp_send_json_error( $error );
		}
	}

	public function auto_validate_credentials() {
		if ( (bool) apply_filters( 'w91099ch_disable_auto_validate_credentials', true ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page_param   = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$page         = is_string( $page_param ) ? sanitize_key( wp_unslash( $page_param ) ) : '';
		$should_check = ( 'w9-1099-chaser' === $page ) || ( 1 === wp_rand( 1, 10 ) );

		if ( $should_check && $this->is_connected() ) {
			$last_checked   = get_option( 'w91099ch_last_checked', 0 );
			$check_interval = HOUR_IN_SECONDS;

			if ( $check_interval < ( time() - $last_checked ) ) {
				$is_valid = $this->validate_credentials();
				update_option( 'w91099ch_last_checked', time() );
				update_option( 'w91099ch_credentials_valid', $is_valid );

				if ( ! $is_valid ) {
					$this->log( 'Auto-validation failed - credentials may need attention' );
				}
			}
		}
	}

	public function validate_credentials() {
		$credentials = $this->get_credentials();

		if ( empty( $credentials ) || ! is_array( $credentials ) ) {
			$this->log( 'No credentials found for validation' );
			return false;
		}

		try {
			$access_token = isset( $credentials['access_token'] ) ? (string) $credentials['access_token'] : '';
			if ( '' === $access_token ) {
				throw new Exception( 'No access token available' );
			}

			$api_url = $this->api->get_api_base_url() . '/api/auth/token/verify/';

			$response = wp_remote_post(
				$api_url,
				array(
					'headers'   => array(
						'Content-Type' => 'application/json',
					),
					'body'      => wp_json_encode( array( 'token' => $access_token ) ),
					'timeout'   => 15,
					'sslverify' => true,
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->log( 'API validation failed - ' . $response->get_error_message() );
				if ( ! empty( $credentials['refresh_token'] ) ) {
					return $this->refresh_access_token();
				}
				return false;
			}

			$response_code = wp_remote_retrieve_response_code( $response );

			$this->log( 'Token verify response - HTTP ' . $response_code );

			if ( 200 === $response_code ) {
				$this->log( 'Credentials validation successful' );
				return true;
			}

			if ( 401 === $response_code ) {
				$this->log( 'Token expired, attempting refresh' );
				return $this->refresh_access_token();
			}

			$this->log( 'First verify attempt failed, trying alternative format' );
			$response2 = wp_remote_post(
				$api_url,
				array(
					'headers'   => array(
						'Content-Type' => 'application/json',
					),
					'body'      => wp_json_encode( $access_token ),
					'timeout'   => 15,
					'sslverify' => true,
				)
			);

			if ( is_wp_error( $response2 ) ) {
				$this->log( 'API validation failed (alternative) - ' . $response2->get_error_message() );
				return $this->refresh_access_token();
			}

			$response2_code = wp_remote_retrieve_response_code( $response2 );
			if ( 200 === $response2_code ) {
				$this->log( 'Credentials validation successful (alternative format)' );
				return true;
			}

			$this->log( 'API validation returned HTTP ' . $response_code );
			return $this->refresh_access_token();

		} catch ( Exception $e ) {
			$this->log( 'Credential validation exception - ' . $e->getMessage() );
			return false;
		}
	}

	public function ajax_test_connection() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();
		wp_send_json_success( array( 'message' => esc_html__( 'Test connection functionality not implemented', 'w9-1099-chaser' ) ) );
	}

	public function ajax_validate_credentials() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();
		$result = $this->validate_credentials();
		if ( $result ) {
			wp_send_json_success( array( 'message' => esc_html__( 'Credentials are valid', 'w9-1099-chaser' ) ) );
		} else {
			wp_send_json_error( esc_html__( 'Credentials validation failed', 'w9-1099-chaser' ) );
		}
	}

	public function ajax_refresh_credentials() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$this->enforce_admin_consent_or_fail();
		$result = $this->refresh_access_token();
		if ( $result ) {
			wp_send_json_success( array( 'message' => esc_html__( 'Credentials refreshed successfully', 'w9-1099-chaser' ) ) );
		} else {
			wp_send_json_error( esc_html__( 'Failed to refresh credentials', 'w9-1099-chaser' ) );
		}
	}

	public function ajax_save_auto_sync_setting() {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$enabled_raw = filter_input( INPUT_POST, 'enabled', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$enabled = in_array( $enabled_raw, array( '1', 'true', 'on' ), true ) ? 1 : 0;

		update_option( 'w91099ch_enable_auto_sync', $enabled );
		update_option( 'w91099ch_admin_consent', $enabled ? 1 : 0 );

		if ( $enabled ) {
			$this->schedule_auto_sync_cron();
			$this->ensure_webhook_configuration();
			// Trigger initial sync when auto-sync is enabled
			$this->trigger_initial_auto_sync();
		} else {
			$this->unschedule_auto_sync_cron();
		}

		$this->log( 'Auto-sync setting updated: ' . ( $enabled ? 'enabled' : 'disabled' ) );

		wp_send_json_success(
			array(
				'message' => $enabled ? 'Auto-sync enabled.' : 'Auto-sync disabled.',
			)
		);
	}

	private function is_auto_sync_enabled() {
		return (bool) get_option( 'w91099ch_enable_auto_sync', false );
	}

	private function is_debounced( $key, $interval_seconds = 60 ) {
		$last = (int) get_option( 'w91099ch_debounce_' . $key, 0 );
		$now = time();
		if ( $now - $last < $interval_seconds ) {
			return true;
		}
		update_option( 'w91099ch_debounce_' . $key, $now );
		return false;
	}

	private function touch_debounce( $key ) {
		update_option( 'w91099ch_debounce_' . $key, time() );
	}

	private function schedule_auto_sync_cron() {
		if ( ! wp_next_scheduled( 'w91099ch_auto_sync_cron' ) ) {
			wp_schedule_event( time(), 'w91099ch_15min', 'w91099ch_auto_sync_cron' );
		}
	}

	private function unschedule_auto_sync_cron() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( 'w91099ch_auto_sync_cron' );
			return;
		}

		$timestamp = wp_next_scheduled( 'w91099ch_auto_sync_cron' );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'w91099ch_auto_sync_cron' );
			$timestamp = wp_next_scheduled( 'w91099ch_auto_sync_cron' );
		}
	}

	public function handle_auto_sync_cron() {
		if ( ! $this->is_auto_sync_enabled() ) {
			return;
		}
		if ( ! $this->has_admin_consent() ) {
			$this->log( 'Auto-sync skipped: admin consent missing' );
			return;
		}
		if ( $this->is_debounced( 'auto_sync_cron', 900 ) ) {
			return;
		}

		$this->log( 'Running auto-sync cron job for all 9 cards' );

		$this->auto_sync_profile();
		$this->auto_sync_plugins();
		$this->auto_sync_affiliates();
		$this->auto_sync_team();
		$this->auto_sync_form_plugins();
		$this->auto_sync_membership_plugins();
		$this->auto_sync_contractor_plugins();
		$this->auto_sync_accounting_plugins();
		$this->auto_sync_payout_plugins();
	}

	private function auto_sync_profile() {
		if ( $this->is_debounced( 'auto_sync_profile', 300 ) ) {
			return;
		}
		try {
			$profile_data = $this->collect_profile_data();
			update_option( 'w91099ch_profile_last_sync', time() );

			// Always dispatch webhook for auto sync (consistent with manual sync)
			if ( $this->is_auto_sync_enabled() ) {
				$profile_summary = array(
					'action'      => 'auto_synced',
					'client_type' => $profile_data['client_type'] ?? 'wordpress',
					'user_email'  => $profile_data['email'] ?? '',
				);
				$profile_full_payload = array(
					'data'    => $profile_data,
					'profile' => $profile_summary,
				);
				$this->dispatch_card_rows_webhook(
					'profile_synced',
					'user_profile',
					array( $profile_data ),
					$profile_summary,
					'auto_sync_profile',
					$profile_full_payload
				);
				$this->log( 'Auto-sync profile webhook dispatched' );
			}
			$this->log( 'Auto-sync profile succeeded' );
		} catch ( Throwable $e ) {
			$this->log( 'Auto-sync profile error: ' . $e->getMessage() );
		}
	}

	private function auto_sync_plugins( $force = false ) {
		if ( ! $force && $this->is_debounced( 'auto_sync_plugins', 300 ) ) {
			$this->log( 'Auto-sync plugins skipped: debounced' );
			return;
		}
		try {
			if ( ! $this->affiliate_manager ) {
				$this->log( 'Auto-sync plugins skipped: affiliate manager not available' );
				return;
			}
			$result = $this->affiliate_manager->refresh_detection();
			$plugins = isset( $result['plugins'] ) ? $result['plugins'] : array();
			$total_affiliates = isset( $result['total_affiliates'] ) ? (int) $result['total_affiliates'] : 0;

			$affiliate_slugs = is_array( $plugins ) ? array_keys( $plugins ) : array();
			$all_plugins_snapshot = $this->get_plugins_snapshot( $affiliate_slugs, false );
			$active_plugins_snapshot = $this->get_plugins_snapshot( $affiliate_slugs, true );

			$snapshot_payload = array(
				'captured_at' => time(),
				'admin_email' => (string) get_option( 'admin_email', '' ),
				'site_url'    => (string) get_site_url(),
				'plugins'     => $active_plugins_snapshot,
			);
			update_option( 'w91099ch_active_plugins_snapshot', $snapshot_payload );
			update_option( 'w91099ch_plugins_last_sync', time() );

			// Always dispatch webhook for auto sync (consistent with manual sync)
			if ( $this->is_auto_sync_enabled() ) {
				$plugin_stats = array(
					'plugins_count'        => is_array( $plugins ) ? count( $plugins ) : 0,
					'all_plugins_count'    => is_array( $all_plugins_snapshot ) ? count( $all_plugins_snapshot ) : 0,
					'total_affiliates'     => $total_affiliates,
					'active_plugins_count' => is_array( $active_plugins_snapshot ) ? count( $active_plugins_snapshot ) : 0,
				);
				$plugin_full_payload = array(
					'data' => array(
						'plugins'        => is_array( $all_plugins_snapshot ) ? $all_plugins_snapshot : array(),
						'active_plugins' => is_array( $active_plugins_snapshot ) ? $active_plugins_snapshot : array(),
					),
					'stats'          => $plugin_stats,
					'plugins'        => is_array( $all_plugins_snapshot ) ? $all_plugins_snapshot : array(),
					'active_plugins' => is_array( $active_plugins_snapshot ) ? $active_plugins_snapshot : array(),
				);
				$this->dispatch_card_rows_webhook(
					'plugin_data_synced',
					'plugin_data',
					$all_plugins_snapshot,
					$plugin_stats,
					'auto_sync_plugins',
					$plugin_full_payload
				);
				$this->log( 'Auto-sync plugins webhook dispatched' );
			}

			if ( $force ) {
				$this->touch_debounce( 'auto_sync_plugins' );
			}
			$this->log( 'Auto-sync plugins succeeded' );
		} catch ( Throwable $e ) {
			$this->log( 'Auto-sync plugins error: ' . $e->getMessage() );
		}
	}

	private function auto_sync_affiliates( $force = false ) {
		if ( ! $force && $this->is_debounced( 'auto_sync_affiliates', 300 ) ) {
			return;
		}
		try {
			if ( ! $this->affiliate_manager ) {
				return;
			}
			$affiliates = array();
			if ( method_exists( $this->affiliate_manager, 'get_all_affiliates_for_sync' ) ) {
				$affiliates = $this->affiliate_manager->get_all_affiliates_for_sync( '' );
			}

			// Ensure we have fresh data if no affiliates found
			if ( empty( $affiliates ) ) {
				if ( method_exists( $this->affiliate_manager, 'refresh_detection' ) ) {
					$this->affiliate_manager->refresh_detection();
				}
				if ( method_exists( $this->affiliate_manager, 'get_all_affiliates_for_sync' ) ) {
					$affiliates = $this->affiliate_manager->get_all_affiliates_for_sync( '' );
				}
			}

			$total_affiliates = is_array( $affiliates ) ? count( $affiliates ) : 0;
			$excluded = $this->get_excluded_affiliate_ids();
			$included_affiliates = array();

			if ( is_array( $affiliates ) && ! empty( $affiliates ) ) {
				foreach ( $affiliates as $affiliate ) {
					$affiliate_id = '';
					if ( is_array( $affiliate ) && isset( $affiliate['id'] ) ) {
						$affiliate_id = (string) $affiliate['id'];
					} elseif ( is_array( $affiliate ) && isset( $affiliate['affiliate_id'] ) ) {
						$affiliate_id = (string) $affiliate['affiliate_id'];
					}

					if ( '' !== $affiliate_id && isset( $excluded[ $affiliate_id ] ) ) {
						continue;
					}

					$included_affiliates[] = $affiliate;
				}
			}

			$included_count = is_array( $included_affiliates ) ? count( $included_affiliates ) : 0;
			$excluded_count = max( 0, $total_affiliates - $included_count );
			update_option( 'w91099ch_affiliates_last_sync', time() );
			update_option( 'w91099ch_affiliates_count', $included_count );

			// Always dispatch webhook for auto sync (consistent with manual sync)
			if ( $this->is_auto_sync_enabled() ) {
				$this->dispatch_affiliate_rows_webhook(
					$included_affiliates,
					'',
					array(
						'successful'       => $included_count,
						'total_affiliates' => $total_affiliates,
						'excluded'         => $excluded_count,
					),
					'auto_sync_affiliates'
				);
				$this->log( 'Auto-sync affiliates webhook dispatched' );
			}

			if ( $force ) {
				$this->touch_debounce( 'auto_sync_affiliates' );
			}
			$this->log( 'Auto-sync affiliates succeeded' );
		} catch ( Throwable $e ) {
			$this->log( 'Auto-sync affiliates error: ' . $e->getMessage() );
		}
	}

	private function auto_sync_team( $force = false, $reason = '' ) {
		if ( ! $force && $this->is_debounced( 'auto_sync_team', 300 ) ) {
			return;
		}
		try {
			$users = get_users( array( 'fields' => array( 'ID', 'user_email', 'roles' ) ) );
			$team_members = array();
			$current_snapshot = array();
			foreach ( $users as $user ) {
				$role = 'VIEWER';
				if ( ! empty( $user->roles ) && is_array( $user->roles ) ) {
					$r = strtoupper( reset( $user->roles ) );
					if ( $r === 'ADMINISTRATOR' || $r === 'CONTRIBUTOR' || $r === 'AUTHOR' || $r === 'SHOP_MANAGER' || $r === 'EDITOR' ) {
						$role = $r;
					}
				}
				$email = sanitize_email( (string) $user->user_email );
				if ( '' === $email ) {
					continue;
				}
				$team_members[ $email ] = array(
					'email' => $email,
					'role' => $role,
				);
				$current_snapshot[ $email ] = $role;
			}

			ksort( $current_snapshot );

			$stored_snapshot = get_option( 'w91099ch_team_members_snapshot', array() );
			$previous_snapshot = array();
			if ( is_array( $stored_snapshot ) ) {
				foreach ( $stored_snapshot as $email => $role ) {
					$normalized_email = sanitize_email( (string) $email );
					if ( '' === $normalized_email ) {
						continue;
					}
					$normalized_role = strtoupper( sanitize_text_field( (string) $role ) );
					if ( '' === $normalized_role ) {
						$normalized_role = 'VIEWER';
					}
					$previous_snapshot[ $normalized_email ] = $normalized_role;
				}
			}

			$changed_members = array();
			$added_count     = 0;
			$updated_count   = 0;
			$removed_count   = 0;

			foreach ( $current_snapshot as $email => $role ) {
				if ( ! isset( $previous_snapshot[ $email ] ) ) {
					$changed_members[] = $team_members[ $email ];
					$added_count++;
					continue;
				}

				if ( $previous_snapshot[ $email ] !== $role ) {
					$member                    = $team_members[ $email ];
					$member['previous_role']   = $previous_snapshot[ $email ];
					$member['change_type']     = 'role_updated';
					$changed_members[]         = $member;
					$updated_count++;
				}
			}

			foreach ( $previous_snapshot as $email => $role ) {
				if ( isset( $current_snapshot[ $email ] ) ) {
					continue;
				}
				$changed_members[] = array(
					'email'       => $email,
					'role'        => $role,
					'change_type' => 'removed',
				);
				$removed_count++;
			}

			update_option( 'w91099ch_team_members_snapshot', $current_snapshot );
			update_option( 'w91099ch_team_last_sync', time() );

			if ( empty( $changed_members ) ) {
				$this->log( 'Auto-sync team skipped: no team member changes detected' );
				return;
			}

			// Always dispatch webhook for auto sync (consistent with manual sync)
			if ( $this->is_auto_sync_enabled() ) {
				$team_summary = array(
					'sent'       => count( $changed_members ),
					'invited'    => $added_count,
					'added'      => $added_count,
					'updated'    => $updated_count,
					'removed'    => $removed_count,
					'sync_scope' => 'delta',
					'failed'     => 0,
				);
				if ( '' !== trim( (string) $reason ) ) {
					$team_summary['team_change_trigger'] = sanitize_key( (string) $reason );
				}
				$team_full_payload = array(
					'data'         => $changed_members,
					'team_members' => $changed_members,
					'errors'       => array(),
					'sent'         => count( $changed_members ),
					'invited'      => $added_count,
					'added'        => $added_count,
					'updated'      => $updated_count,
					'removed'      => $removed_count,
					'failed'       => 0,
				);
				$this->dispatch_card_rows_webhook(
					'team_members_synced',
					'team',
					$changed_members,
					$team_summary,
					'auto_sync_team',
					$team_full_payload
				);
				$this->log( 'Auto-sync team webhook dispatched' );
			}
			$this->log( 'Auto-sync team succeeded' );
		} catch ( Throwable $e ) {
			$this->log( 'Auto-sync team error: ' . $e->getMessage() );
		}
	}

	private function get_plugin_sync_categories( $plugin_file ) {
		$plugin_file = strtolower( (string) $plugin_file );
		$categories  = array();

		$map = array(
			'affiliates' => array( 'affiliate', 'referral', 'slicewp', 'yith-woocommerce-affiliates', 'wp-affiliate-manager', 'easy-affiliate' ),
			'forms'      => array( 'gravityforms', 'contact-form-7', 'wpforms', 'ninja-forms', 'formidable', 'fluentform', 'weforms' ),
			'membership' => array( 'memberpress', 'paid-memberships-pro', 'ultimate-member', 'wishlist-member', 'restrict-content-pro', 's2member', 'suremembers' ),
			'contractor' => array( 'freelancer', 'contractor', 'vendors', 'dokan', 'wc-vendors', 'wcfm' ),
			'accounting' => array( 'woocommerce', 'quickbooks', 'xero', 'bookkeep', 'account', 'invoice' ),
			'payout'     => array( 'payout', 'wallet', 'stripe', 'paypal', 'mangopay' ),
		);

		foreach ( $map as $category => $keywords ) {
			foreach ( $keywords as $keyword ) {
				if ( strpos( $plugin_file, $keyword ) !== false ) {
					$categories[ $category ] = true;
					break;
				}
			}
		}

		return array_keys( $categories );
	}

	private function auto_sync_related_plugin_cards( $plugin_file ) {
		$this->auto_sync_plugins( true );

		$categories = $this->get_plugin_sync_categories( $plugin_file );
		if ( in_array( 'affiliates', $categories, true ) ) {
			$this->auto_sync_affiliates( true );
		}
		if ( in_array( 'forms', $categories, true ) ) {
			$this->auto_sync_form_plugins();
		}
		if ( in_array( 'membership', $categories, true ) ) {
			$this->auto_sync_membership_plugins();
		}
		if ( in_array( 'contractor', $categories, true ) ) {
			$this->auto_sync_contractor_plugins();
		}
		if ( in_array( 'accounting', $categories, true ) ) {
			$this->auto_sync_accounting_plugins();
		}
		if ( in_array( 'payout', $categories, true ) ) {
			$this->auto_sync_payout_plugins();
		}
	}

	private function auto_sync_related_plugin_cards_batch( $plugins ) {
		$plugins = is_array( $plugins ) ? $plugins : array();
		if ( empty( $plugins ) ) {
			return;
		}

		$category_lookup = array();
		foreach ( $plugins as $plugin_file ) {
			$categories = $this->get_plugin_sync_categories( $plugin_file );
			foreach ( $categories as $category ) {
				$category_lookup[ $category ] = true;
			}
		}

		$this->auto_sync_plugins( true );

		if ( isset( $category_lookup['affiliates'] ) ) {
			$this->auto_sync_affiliates( true );
		}
		if ( isset( $category_lookup['forms'] ) ) {
			$this->auto_sync_form_plugins();
		}
		if ( isset( $category_lookup['membership'] ) ) {
			$this->auto_sync_membership_plugins();
		}
		if ( isset( $category_lookup['contractor'] ) ) {
			$this->auto_sync_contractor_plugins();
		}
		if ( isset( $category_lookup['accounting'] ) ) {
			$this->auto_sync_accounting_plugins();
		}
		if ( isset( $category_lookup['payout'] ) ) {
			$this->auto_sync_payout_plugins();
		}
	}

	public function handle_plugin_activated( $plugin ) {
		if ( ! $this->is_auto_sync_enabled() || ! $this->has_admin_consent() ) {
			return;
		}
		$this->log( 'Plugin activated: ' . $plugin );
		
		// Auto-sync only related cards for this plugin.
		$this->auto_sync_related_plugin_cards( $plugin );
	}

	public function handle_plugin_deactivated( $plugin ) {
		if ( ! $this->is_auto_sync_enabled() || ! $this->has_admin_consent() ) {
			return;
		}
		$this->log( 'Plugin deactivated: ' . $plugin );
		
		// Auto-sync only related cards for this plugin.
		$this->auto_sync_related_plugin_cards( $plugin );
	}

	public function handle_user_registered( $user_id ) {
		if ( ! $this->is_auto_sync_enabled() || ! $this->has_admin_consent() ) {
			return;
		}
		$this->log( 'User registered: ' . $user_id );

		$this->auto_sync_team( true, 'user_registered' );
	}

	public function handle_profile_updated( $user_id, $old_user_data ) {
		if ( ! $this->is_auto_sync_enabled() || ! $this->has_admin_consent() ) {
			return;
		}
		$this->log( 'Profile updated: ' . $user_id );

		$this->auto_sync_team( true, 'profile_updated' );
	}

	public function handle_option_added( $option, $value ) {
		if ( ! $this->is_auto_sync_enabled() || ! $this->has_admin_consent() ) {
			return;
		}
		$this->handle_option_change( $option, 'added' );
	}

	public function handle_option_updated( $option, $old_value, $value ) {
		if ( ! $this->is_auto_sync_enabled() || ! $this->has_admin_consent() ) {
			return;
		}
		$this->handle_option_change( $option, 'updated' );
	}

	public function handle_option_deleted( $option ) {
		if ( ! $this->is_auto_sync_enabled() || ! $this->has_admin_consent() ) {
			return;
		}
		$this->handle_option_change( $option, 'deleted' );
	}

	public function handle_affiliate_event( $affiliate_id = 0 ) {
		if ( ! $this->is_auto_sync_enabled() || ! $this->has_admin_consent() ) {
			return;
		}
		$this->log( 'Affiliate event detected. ID: ' . ( is_scalar( $affiliate_id ) ? $affiliate_id : 'n/a' ) );
		
		$this->auto_sync_affiliates( true );
	}

	public function handle_affiliate_event_wpam( $model = null, $request = null ) {
		if ( ! $this->is_auto_sync_enabled() || ! $this->has_admin_consent() ) {
			return;
		}
		$affiliate_id = 0;
		if ( is_object( $model ) && isset( $model->affiliateId ) ) {
			$affiliate_id = $model->affiliateId;
		} elseif ( is_object( $model ) && isset( $model->id ) ) {
			$affiliate_id = $model->id;
		}
		$this->log( 'WP Affiliate Manager event detected. ID: ' . ( is_scalar( $affiliate_id ) ? $affiliate_id : 'n/a' ) );
		$this->auto_sync_affiliates( true );
	}

	private function handle_option_change( $option, $action ) {
		$relevant_options = array(
			'w91099ch_payee_id' => 'w9_payee',
			'w91099ch_payee_email' => 'w9_payee',
			'w91099ch_workspaces' => 'workspaces',
			'w91099ch_team_invites' => 'invite_team_members',
			'w91099ch_excluded_affiliate_ids' => 'affiliates',
		);

		if ( isset( $relevant_options[ $option ] ) ) {
			$card = $relevant_options[ $option ];
			$this->log( "Option {$action} for {$card}: {$option}" );
			
			switch ( $card ) {
				case 'w9_payee':
					$this->auto_sync_w9_payee();
					break;
				case 'workspaces':
					$this->auto_sync_workspaces();
					break;
				case 'invite_team_members':
					$this->auto_sync_invite_team_members();
					break;
				case 'affiliates':
					$this->auto_sync_affiliates();
					break;
			}
		}
	}

	public function add_cron_interval_15min( $schedules ) {
		if ( ! isset( $schedules['w91099ch_15min'] ) ) {
			$schedules['w91099ch_15min'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'Every 15 minutes', 'w9-1099-chaser' ),
			);
		}
		return $schedules;
	}

	private function collect_profile_data() {
		$current_user = wp_get_current_user();
		$client_type = apply_filters( 'w91099ch_client_type', 'wordpress' );
		$user_email = $current_user->user_email;
		$first_name = ( '' !== (string) $current_user->first_name ) ? $current_user->first_name : 'WordPress';
		$last_name = ( '' !== (string) $current_user->last_name ) ? $current_user->last_name : 'User';

		$profile_data = array(
			'client_type' => $client_type,
			'email' => $user_email,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'status' => 'incomplete',
			'site_url' => get_site_url(),
			'wp_version' => get_bloginfo( 'version' ),
			'locale' => get_locale(),
		);

		return apply_filters( 'w91099ch_profile_data', $profile_data );
	}

	private function get_stored_credentials() {
		return $this->get_credentials();
	}

	private function auto_sync_form_plugins() {
		if ( $this->is_debounced( 'auto_sync_form_plugins', 300 ) ) {
			return;
		}
		try {
			if ( ! $this->affiliate_manager ) {
				return;
			}
			$form_plugins = method_exists( $this->affiliate_manager, 'detect_form_plugins' ) 
				? $this->affiliate_manager->detect_form_plugins( true ) 
				: array();

			$form_data = array(
				'form_plugins'   => $form_plugins,
				'sync_timestamp' => time(),
			);

			update_option( 'w91099ch_form_plugins_last_sync', time() );

			// Always dispatch webhook for auto sync (consistent with manual sync)
			if ( $this->is_auto_sync_enabled() ) {
				$form_summary = array(
					'total_forms'       => 0,
					'submissions_today' => 0,
					'active_forms'      => count(
						array_filter(
							$form_plugins,
							function ( $p ) {
								return ! empty( $p['active'] );
							}
						)
					),
				);
				$form_full_payload = array(
					'data'            => $form_data,
					'plugins'         => $form_plugins,
					'form_plugins'    => $form_plugins,
					'sync_timestamp'  => time(),
					'total_forms'     => 0,
					'submissions_today' => 0,
					'active_forms'    => $form_summary['active_forms'],
				);
				$form_rows = array_values( (array) $form_plugins );
				if ( class_exists( 'w91099ch_Form_Plugin_Detector' ) ) {
					$detector = new w91099ch_Form_Plugin_Detector();
					$limit    = (int) apply_filters( 'w91099ch_webhook_full_rows_limit', 10000 );
					if ( $limit < 1 ) {
						$limit = 1;
					}
					$detailed_rows = array();
					foreach ( (array) $form_plugins as $slug => $plugin ) {
						if ( ! is_array( $plugin ) ) {
							continue;
						}
						$entries = $detector->get_entries_preview( (string) $slug, $limit );
						if ( ! is_array( $entries ) || empty( $entries ) ) {
							$detailed_rows[] = array(
								'plugin_slug'    => (string) $slug,
								'plugin_name'    => isset( $plugin['name'] ) ? (string) $plugin['name'] : (string) $slug,
								'plugin_version' => isset( $plugin['version'] ) ? (string) $plugin['version'] : '',
								'plugin_active'  => ! empty( $plugin['active'] ) ? 'true' : 'false',
								'row_type'       => 'plugin_summary',
							);
							continue;
						}
						foreach ( $entries as $entry_row ) {
							$detailed_rows[] = array_merge(
								array(
									'plugin_slug'    => (string) $slug,
									'plugin_name'    => isset( $plugin['name'] ) ? (string) $plugin['name'] : (string) $slug,
									'plugin_version' => isset( $plugin['version'] ) ? (string) $plugin['version'] : '',
									'plugin_active'  => ! empty( $plugin['active'] ) ? 'true' : 'false',
								),
								is_array( $entry_row ) ? $entry_row : array( 'value' => is_scalar( $entry_row ) ? (string) $entry_row : wp_json_encode( $entry_row ) )
							);
						}
					}
					if ( ! empty( $detailed_rows ) ) {
						$form_rows = $detailed_rows;
					}
				}

				$this->dispatch_card_rows_webhook(
					'form_plugins_synced',
					'forms_plugin',
					$form_rows,
					$form_summary,
					'auto_sync_form_plugins',
					$form_full_payload
				);
				$this->log( 'Auto-sync Form Plugins webhook dispatched' );
			}
			$this->log( 'Auto-sync Form Plugins succeeded' );
		} catch ( Throwable $e ) {
			$this->log( 'Auto-sync Form Plugins error: ' . $e->getMessage() );
		}
	}

	private function auto_sync_membership_plugins() {
		if ( $this->is_debounced( 'auto_sync_membership_plugins', 300 ) ) {
			return;
		}
		try {
			if ( ! $this->affiliate_manager ) {
				return;
			}
			$membership_plugins = method_exists( $this->affiliate_manager, 'detect_membership_plugins' ) 
				? $this->affiliate_manager->detect_membership_plugins( true ) 
				: array();

			$membership_data = array(
				'membership_plugins' => $membership_plugins,
				'sync_timestamp'     => time(),
			);

			update_option( 'w91099ch_membership_plugins_last_sync', time() );

			// Always dispatch webhook for auto sync (consistent with manual sync)
			if ( $this->is_auto_sync_enabled() ) {
				$membership_summary = array(
					'total_members'        => 0,
					'active_subscriptions' => 0,
					'revenue_this_month'   => 0,
				);
				$membership_full_payload = array(
					'data'                 => $membership_data,
					'plugins'              => $membership_plugins,
					'membership_plugins'   => $membership_plugins,
					'total_members'        => 0,
					'active_subscriptions' => 0,
					'revenue_this_month'   => 0,
				);
				$membership_rows = array_values( (array) $membership_plugins );
				if ( class_exists( 'w91099ch_Contractor_Plugin_Detector' ) ) {
					$detector = new w91099ch_Contractor_Plugin_Detector();
					$limit    = (int) apply_filters( 'w91099ch_webhook_full_rows_limit', 10000 );
					if ( $limit < 1 ) {
						$limit = 1;
					}
					$detailed_rows = array();
					foreach ( (array) $membership_plugins as $slug => $plugin ) {
						if ( ! is_array( $plugin ) ) {
							continue;
						}
						$members = $detector->get_contractors_preview( (string) $slug, $limit );
						if ( ! is_array( $members ) || empty( $members ) ) {
							$detailed_rows[] = array(
								'plugin_slug'    => (string) $slug,
								'plugin_name'    => isset( $plugin['name'] ) ? (string) $plugin['name'] : (string) $slug,
								'plugin_version' => isset( $plugin['version'] ) ? (string) $plugin['version'] : '',
								'plugin_active'  => ! empty( $plugin['active'] ) ? 'true' : 'false',
								'row_type'       => 'plugin_summary',
							);
							continue;
						}
						foreach ( $members as $member_row ) {
							$detailed_rows[] = array_merge(
								array(
									'plugin_slug'    => (string) $slug,
									'plugin_name'    => isset( $plugin['name'] ) ? (string) $plugin['name'] : (string) $slug,
									'plugin_version' => isset( $plugin['version'] ) ? (string) $plugin['version'] : '',
									'plugin_active'  => ! empty( $plugin['active'] ) ? 'true' : 'false',
								),
								is_array( $member_row ) ? $member_row : array( 'value' => is_scalar( $member_row ) ? (string) $member_row : wp_json_encode( $member_row ) )
							);
						}
					}
					if ( ! empty( $detailed_rows ) ) {
						$membership_rows = $detailed_rows;
					}
				}

				$this->dispatch_card_rows_webhook(
					'membership_plugins_synced',
					'membership_data',
					$membership_rows,
					$membership_summary,
					'auto_sync_membership_plugins',
					$membership_full_payload
				);
				$this->log( 'Auto-sync Membership Plugins webhook dispatched' );
			}
			$this->log( 'Auto-sync Membership Plugins succeeded' );
		} catch ( Throwable $e ) {
			$this->log( 'Auto-sync Membership Plugins error: ' . $e->getMessage() );
		}
	}

	private function auto_sync_contractor_plugins() {
		if ( $this->is_debounced( 'auto_sync_contractor_plugins', 300 ) ) {
			return;
		}
		try {
			if ( ! $this->affiliate_manager ) {
				return;
			}
			$contractor_plugins = method_exists( $this->affiliate_manager, 'detect_contractor_plugins' ) 
				? $this->affiliate_manager->detect_contractor_plugins( true ) 
				: array();

			$contractor_data = array(
				'contractor_plugins' => $contractor_plugins,
				'sync_timestamp'     => time(),
			);

			update_option( 'w91099ch_contractor_plugins_last_sync', time() );

			// Always dispatch webhook for auto sync (consistent with manual sync)
			if ( $this->is_auto_sync_enabled() ) {
				$contractor_summary = array(
					'total_members' => 0,
				);
				$contractor_full_payload = array(
					'data'               => $contractor_data,
					'plugins'            => $contractor_plugins,
					'contractor_plugins' => $contractor_plugins,
					'sync_timestamp'     => time(),
				);
				$this->dispatch_card_rows_webhook(
					'contractor_plugins_synced',
					'membership_data',
					$contractor_plugins,
					$contractor_summary,
					'auto_sync_contractor_plugins',
					$contractor_full_payload
				);
				$this->log( 'Auto-sync Contractor Plugins webhook dispatched' );
			}
			$this->log( 'Auto-sync Contractor Plugins succeeded' );
		} catch ( Throwable $e ) {
			$this->log( 'Auto-sync Contractor Plugins error: ' . $e->getMessage() );
		}
	}

	private function auto_sync_accounting_plugins() {
		if ( $this->is_debounced( 'auto_sync_accounting_plugins', 300 ) ) {
			return;
		}
		try {
			if ( ! $this->affiliate_manager ) {
				return;
			}
			$accounting_plugins = method_exists( $this->affiliate_manager, 'detect_accounting_plugins' ) 
				? $this->affiliate_manager->detect_accounting_plugins( true ) 
				: array();

			$accounting_data = array(
				'accounting_plugins' => $accounting_plugins,
				'sync_timestamp'     => time(),
			);

			update_option( 'w91099ch_accounting_plugins_last_sync', time() );

			// Always dispatch webhook for auto sync (consistent with manual sync)
			if ( $this->is_auto_sync_enabled() ) {
				$accounting_summary = array(
					'total_orders'     => 0,
					'revenue_today'    => 0,
					'pending_payments' => 0,
				);
				$accounting_full_payload = array(
					'data'               => $accounting_data,
					'plugins'            => $accounting_plugins,
					'accounting_plugins' => $accounting_plugins,
					'total_orders'       => 0,
					'revenue_today'      => 0,
					'pending_payments'   => 0,
				);
				$accounting_rows = array_values( (array) $accounting_plugins );
				if ( class_exists( 'w91099ch_Accounting_Bookkeeping_Plugin_Detector' ) ) {
					$detector = new w91099ch_Accounting_Bookkeeping_Plugin_Detector();
					$limit    = (int) apply_filters( 'w91099ch_webhook_full_rows_limit', 10000 );
					if ( $limit < 1 ) {
						$limit = 1;
					}
					$detailed_rows = array();
					foreach ( (array) $accounting_plugins as $slug => $plugin ) {
						if ( ! is_array( $plugin ) ) {
							continue;
						}
						$records = $detector->get_plugins_preview( (string) $slug, $limit );
						if ( ! is_array( $records ) || empty( $records ) ) {
							$detailed_rows[] = array(
								'plugin_slug'    => (string) $slug,
								'plugin_name'    => isset( $plugin['name'] ) ? (string) $plugin['name'] : (string) $slug,
								'plugin_version' => isset( $plugin['version'] ) ? (string) $plugin['version'] : '',
								'plugin_active'  => ! empty( $plugin['active'] ) ? 'true' : 'false',
								'row_type'       => 'plugin_summary',
							);
							continue;
						}
						foreach ( $records as $record_row ) {
							$detailed_rows[] = array_merge(
								array(
									'plugin_slug'    => (string) $slug,
									'plugin_name'    => isset( $plugin['name'] ) ? (string) $plugin['name'] : (string) $slug,
									'plugin_version' => isset( $plugin['version'] ) ? (string) $plugin['version'] : '',
									'plugin_active'  => ! empty( $plugin['active'] ) ? 'true' : 'false',
								),
								is_array( $record_row ) ? $record_row : array( 'value' => is_scalar( $record_row ) ? (string) $record_row : wp_json_encode( $record_row ) )
							);
						}
					}
					if ( ! empty( $detailed_rows ) ) {
						$accounting_rows = $detailed_rows;
					}
				}

				$this->dispatch_card_rows_webhook(
					'accounting_plugins_synced',
					'accounting_data',
					$accounting_rows,
					$accounting_summary,
					'auto_sync_accounting_plugins',
					$accounting_full_payload
				);
				$this->log( 'Auto-sync Accounting Plugins webhook dispatched' );
			}
			$this->log( 'Auto-sync Accounting Plugins succeeded' );
		} catch ( Throwable $e ) {
			$this->log( 'Auto-sync Accounting Plugins error: ' . $e->getMessage() );
		}
	}

	private function auto_sync_payout_plugins() {
		if ( $this->is_debounced( 'auto_sync_payout_plugins', 300 ) ) {
			return;
		}
		try {
			if ( ! $this->affiliate_manager ) {
				return;
			}
			$payout_plugins = $this->affiliate_manager->detect_payout_plugins( true );
			$wallet_entries = array();
			$wallet_entries_by_plugin = array();
			$wallet_summary = array(
				'total_records'     => 0,
				'amount_total'      => 0,
				'amount_count'      => 0,
				'amount_positive'   => 0,
				'amount_negative'   => 0,
			);

			if ( class_exists( 'w91099ch_Wallet_Payout_Plugin_Detector' ) ) {
				$detector = new w91099ch_Wallet_Payout_Plugin_Detector();
				$limit = (int) apply_filters( 'w91099ch_payout_sync_limit', 1000 );
				if ( $limit <= 0 ) {
					$limit = 1000;
				}
				foreach ( $payout_plugins as $slug => $plugin ) {
					$rows = $detector->get_wallet_entries_preview( (string) $slug, $limit );
					if ( ! is_array( $rows ) ) {
						$rows = array();
					}
					$wallet_entries_by_plugin[ (string) $slug ] = array(
						'count' => count( $rows ),
						'rows'  => $rows,
					);
					$wallet_entries = array_merge( $wallet_entries, $rows );
				}

				$wallet_summary['total_records'] = count( $wallet_entries );
				foreach ( $wallet_entries as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					if ( isset( $row['amount'] ) && is_numeric( $row['amount'] ) ) {
						$amt = (float) $row['amount'];
						$wallet_summary['amount_total'] += $amt;
						$wallet_summary['amount_count'] += 1;
						if ( $amt >= 0 ) {
							$wallet_summary['amount_positive'] += 1;
						} else {
							$wallet_summary['amount_negative'] += 1;
						}
					}
				}
			}
			$credentials = $this->get_stored_credentials();
			if ( ! $credentials || empty( $credentials['access_token'] ) ) {
				return;
			}
			$endpoint = apply_filters( 'w91099ch_payout_plugins_sync_endpoint', '' );
			if ( ! is_string( $endpoint ) || '' === $endpoint ) {
				return;
			}
			$payout_data = array(
				'payout_plugins' => $payout_plugins,
				'wallet_entries' => $wallet_entries,
				'wallet_entries_by_plugin' => $wallet_entries_by_plugin,
				'wallet_summary' => $wallet_summary,
				'sync_timestamp' => time(),
			);
			$response = wp_remote_post(
				$endpoint,
				array(
					'timeout' => 15,
					'headers' => array(
						'Content-Type' => 'application/json',
						'Authorization' => 'Bearer ' . $credentials['access_token'],
					),
					'body' => wp_json_encode( $payout_data ),
				)
			);
			if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
				update_option( 'w91099ch_payout_plugins_last_sync', time() );
				if ( $this->is_auto_sync_enabled() ) {
					$payout_rows = ! empty( $wallet_entries ) ? $wallet_entries : $payout_plugins;
					$payout_summary = array(
						'total_wallet_plugins' => is_array( $payout_plugins ) ? count( $payout_plugins ) : 0,
						'total_records'        => isset( $wallet_summary['total_records'] ) ? (int) $wallet_summary['total_records'] : 0,
						'amount_total'         => isset( $wallet_summary['amount_total'] ) ? (float) $wallet_summary['amount_total'] : 0,
						'amount_count'         => isset( $wallet_summary['amount_count'] ) ? (int) $wallet_summary['amount_count'] : 0,
					);
					$payout_full_payload = array(
						'data'                    => $payout_data,
						'payout_plugins'          => $payout_plugins,
						'wallet_entries'          => $wallet_entries,
						'wallet_entries_by_plugin'=> $wallet_entries_by_plugin,
						'wallet_summary'          => $wallet_summary,
						'sync_timestamp'          => time(),
					);
					$this->dispatch_card_rows_webhook(
						'payout_plugins_synced',
						'payout_data',
						$payout_rows,
						$payout_summary,
						'auto_sync_payout_plugins',
						$payout_full_payload
					);
				}
				$this->log( 'Auto-sync Payout Plugins succeeded' );
			} else {
				$this->log( 'Auto-sync Payout Plugins failed' );
			}
		} catch ( Throwable $e ) {
			$this->log( 'Auto-sync Payout Plugins error: ' . $e->getMessage() );
		}
	}


	/**
	 * Handle form submission events
	 */
	public function handle_form_submission( $entry_id = null, $form_data = null, $form_id = null, $fields = null ) {
		if ( ! $this->is_auto_sync_enabled() || ! $this->has_admin_consent() ) {
			return;
		}

		$this->log( 'Form submission detected. Entry ID: ' . ( $entry_id ? $entry_id : 'n/a' ) );
		
		// Auto-sync form plugins
		$this->auto_sync_form_plugins();
	}

	/**
	 * Handle plugin installation events
	 */
	public function handle_plugin_installed( $upgrader, $hook_extra ) {
		if ( ! $this->is_auto_sync_enabled() || ! $this->has_admin_consent() ) {
			return;
		}

		if ( isset( $hook_extra['type'] ) && $hook_extra['type'] === 'plugin' ) {
			if ( isset( $hook_extra['action'] ) && $hook_extra['action'] === 'install' ) {
				$plugins = isset( $hook_extra['plugins'] ) ? $hook_extra['plugins'] : array();
				foreach ( $plugins as $plugin ) {
					$this->log( 'Plugin installed: ' . $plugin );
				}
				$this->auto_sync_related_plugin_cards_batch( $plugins );
			} elseif ( isset( $hook_extra['action'] ) && $hook_extra['action'] === 'update' ) {
				$plugins = isset( $hook_extra['plugins'] ) ? $hook_extra['plugins'] : array();
				foreach ( $plugins as $plugin ) {
					$this->log( 'Plugin updated: ' . $plugin );
				}
				$this->auto_sync_related_plugin_cards_batch( $plugins );
			}
		}
	}

	/**
	 * Handle plugin deletion events
	 */
	public function handle_plugin_deleted( $plugin_file ) {
		if ( ! $this->is_auto_sync_enabled() || ! $this->has_admin_consent() ) {
			return;
		}

		$this->log( 'Plugin deleted: ' . $plugin_file );
		
		$this->auto_sync_related_plugin_cards( $plugin_file );
	}

	/**
	 * Trigger initial auto-sync when auto-sync is first enabled
	 */
	private function trigger_initial_auto_sync() {
		try {
			$this->log( 'Triggering initial auto-sync for all cards' );
			
			// Perform initial sync of all cards
			$this->auto_sync_profile();
			$this->auto_sync_plugins( true );
			$this->auto_sync_affiliates( true );
			$this->auto_sync_team();
			$this->auto_sync_form_plugins();
			$this->auto_sync_membership_plugins();
			$this->auto_sync_contractor_plugins();
			$this->auto_sync_accounting_plugins();
			$this->auto_sync_payout_plugins();
			
			$this->log( 'Initial auto-sync completed successfully' );
		} catch ( Throwable $e ) {
			$this->log( 'Initial auto-sync error: ' . $e->getMessage() );
		}
	}
}
