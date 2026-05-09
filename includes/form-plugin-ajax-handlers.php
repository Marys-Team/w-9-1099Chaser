<?php
/**
 * Form Plugin AJAX Handlers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'w91099ch_dispatch_webhook_for_plugin_sync' ) ) {
	function w91099ch_dispatch_webhook_for_plugin_sync( $event_type, $sync_data ) {
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

		try {
			$payload = w91099ch_build_full_webhook_payload( $event_type, $sync_data );
			$event_type = isset( $payload['event_type'] ) ? (string) $payload['event_type'] : sanitize_key( (string) $event_type );

			$card_key_map = array(
				'form_plugins_synced'                  => 'forms_plugin',
				'contractor_plugins_synced'            => 'membership_data',
				'freelancer_contractor_plugins_synced' => 'freelancer_data',
				'accounting_bookkeeping_plugins_synced'=> 'accounting_data',
				'wallet_payout_plugins_synced'         => 'payout_data',
			);
			$sheet_tab_map = array(
				'forms_plugin'    => 'forms',
				'membership_data' => 'contractors',
				'freelancer_data' => 'freelancer_contractors',
				'accounting_data' => 'accounting_bookkeeping',
				'payout_data'     => 'wallet_payout',
			);

			$card_key = isset( $card_key_map[ $event_type ] ) ? $card_key_map[ $event_type ] : '';
			if ( '' === $card_key ) {
				return w91099ch_Webhook_Dispatcher::dispatch_raw_payload( $payload, $event_type );
			}

			$sheet_tab = isset( $sheet_tab_map[ $card_key ] ) ? $sheet_tab_map[ $card_key ] : '';
			$rows      = array();
			$plugin_rows = array();
			if ( is_array( $sync_data ) && isset( $sync_data['plugins'] ) && is_array( $sync_data['plugins'] ) ) {
				$plugin_rows = array_values( $sync_data['plugins'] );
			}

			// Build truly row-wise records per card (same style as frontend tables), with plugin-level fallback.
			$extract_nested_rows = static function( $plugins, $nested_key ) {
				$flattened = array();
				if ( ! is_array( $plugins ) ) {
					return $flattened;
				}

				foreach ( $plugins as $plugin ) {
					if ( ! is_array( $plugin ) ) {
						continue;
					}

					$plugin_meta = array(
						'plugin_slug'    => isset( $plugin['slug'] ) ? (string) $plugin['slug'] : '',
						'plugin_name'    => isset( $plugin['name'] ) ? (string) $plugin['name'] : '',
						'plugin_version' => isset( $plugin['version'] ) ? (string) $plugin['version'] : '',
						'plugin_active'  => ! empty( $plugin['active'] ) ? 'true' : 'false',
					);

					$nested = ( isset( $plugin[ $nested_key ] ) && is_array( $plugin[ $nested_key ] ) ) ? $plugin[ $nested_key ] : array();
					if ( empty( $nested ) ) {
						// Keep one row even when nested records are empty, so active plugin still appears row-wise.
						$flattened[] = array_merge( $plugin_meta, array( 'row_type' => 'plugin_summary' ) );
						continue;
					}

					foreach ( $nested as $nested_row ) {
						$row = is_array( $nested_row ) ? $nested_row : array( 'value' => is_scalar( $nested_row ) ? (string) $nested_row : wp_json_encode( $nested_row ) );
						$flattened[] = array_merge( $plugin_meta, $row );
					}
				}

				return $flattened;
			};

			$all_rows_are_plugin_summary = static function( $rows ) {
				if ( ! is_array( $rows ) || empty( $rows ) ) {
					return false;
				}
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						return false;
					}
					if ( ! isset( $row['row_type'] ) || 'plugin_summary' !== (string) $row['row_type'] ) {
						return false;
					}
				}
				return true;
			};

			$extract_rows_from_card_payload = static function( $card_payload_section, $row_key, $rows_by_plugin_key = '' ) {
				if ( ! is_array( $card_payload_section ) ) {
					return array();
				}

				$data = $card_payload_section;
				if ( isset( $card_payload_section['data'] ) && is_array( $card_payload_section['data'] ) ) {
					$data = $card_payload_section['data'];
				}

				$rows = array();
				if ( isset( $data[ $row_key ] ) && is_array( $data[ $row_key ] ) ) {
					$rows = array_values( $data[ $row_key ] );
				}

				if ( empty( $rows ) && '' !== $rows_by_plugin_key && isset( $data[ $rows_by_plugin_key ] ) && is_array( $data[ $rows_by_plugin_key ] ) ) {
					foreach ( $data[ $rows_by_plugin_key ] as $plugin_slug => $plugin_rows ) {
						if ( ! is_array( $plugin_rows ) ) {
							continue;
						}
						foreach ( $plugin_rows as $plugin_row ) {
							$row = is_array( $plugin_row ) ? $plugin_row : array( 'value' => is_scalar( $plugin_row ) ? (string) $plugin_row : wp_json_encode( $plugin_row ) );
							if ( ! isset( $row['plugin_slug'] ) || '' === (string) $row['plugin_slug'] ) {
								$row['plugin_slug'] = (string) $plugin_slug;
							}
							$rows[] = $row;
						}
					}
				}

				return is_array( $rows ) ? $rows : array();
			};

			$card_payload_section = ( isset( $payload[ $card_key ] ) && is_array( $payload[ $card_key ] ) ) ? $payload[ $card_key ] : array();

			switch ( $card_key ) {
				case 'forms_plugin':
					$rows = $extract_nested_rows( $plugin_rows, 'entries' );
					if ( empty( $rows ) || $all_rows_are_plugin_summary( $rows ) ) {
						$fallback_rows = $extract_rows_from_card_payload( $card_payload_section, 'entries' );
						if ( ! empty( $fallback_rows ) ) {
							$rows = $fallback_rows;
						}
					}
					break;
				case 'membership_data':
					$rows = $extract_nested_rows( $plugin_rows, 'members' );
					if ( empty( $rows ) || $all_rows_are_plugin_summary( $rows ) ) {
						$fallback_rows = $extract_rows_from_card_payload( $card_payload_section, 'members', 'members_by_plugin' );
						if ( ! empty( $fallback_rows ) ) {
							$rows = $fallback_rows;
						}
					}
					break;
				case 'freelancer_data':
					$rows = $extract_nested_rows( $plugin_rows, 'contractors' );
					if ( empty( $rows ) || $all_rows_are_plugin_summary( $rows ) ) {
						$fallback_rows = $extract_rows_from_card_payload( $card_payload_section, 'contractors', 'contractors_by_plugin' );
						if ( ! empty( $fallback_rows ) ) {
							$rows = $fallback_rows;
						}
					}
					break;
				case 'accounting_data':
					$rows = $extract_nested_rows( $plugin_rows, 'records' );
					if ( empty( $rows ) || $all_rows_are_plugin_summary( $rows ) ) {
						$fallback_rows = $extract_rows_from_card_payload( $card_payload_section, 'records', 'records_by_plugin' );
						if ( ! empty( $fallback_rows ) ) {
							$rows = $fallback_rows;
						}
					}
					break;
				case 'payout_data':
					$rows = $extract_nested_rows( $plugin_rows, 'wallet_entries' );
					if ( empty( $rows ) || $all_rows_are_plugin_summary( $rows ) ) {
						$fallback_rows = $extract_rows_from_card_payload( $card_payload_section, 'wallet_entries', 'wallet_entries_by_plugin' );
						if ( ! empty( $fallback_rows ) ) {
							$rows = $fallback_rows;
						}
					}
					break;
				default:
					$rows = $plugin_rows;
					break;
			}

			$summary = array();
			if ( is_array( $sync_data ) ) {
				foreach ( $sync_data as $k => $v ) {
					if ( 'plugins' === $k ) {
						continue;
					}
					if ( is_scalar( $v ) || null === $v ) {
						$summary[ sanitize_key( (string) $k ) ] = null === $v ? '' : (string) $v;
					}
				}
			}

			$base = array(
				'event_type'     => $event_type,
				'timestamp'      => isset( $payload['timestamp'] ) ? (string) $payload['timestamp'] : gmdate( 'c' ),
				'site_url'       => isset( $payload['site_url'] ) ? (string) $payload['site_url'] : (string) get_site_url(),
				'site_name'      => isset( $payload['site_name'] ) ? (string) $payload['site_name'] : (string) get_bloginfo( 'name' ),
				'admin_email'    => isset( $payload['admin_email'] ) ? (string) $payload['admin_email'] : sanitize_email( (string) get_option( 'admin_email', '' ) ),
				'context_action' => isset( $payload['context_action'] ) ? (string) $payload['context_action'] : 'dashboard_plugin_card_sync',
				'sheet_tab'      => $sheet_tab,
				'tab'            => $sheet_tab,
				'sheet'          => $sheet_tab,
				'tab_name'       => $sheet_tab,
				'sheet_name'     => $sheet_tab,
				'worksheet'      => $sheet_tab,
				'target_tab'     => $sheet_tab,
				'card_key'       => $card_key,
			);

			$card_payload_full = array(
				'card_payload' => isset( $payload[ $card_key ] ) ? $payload[ $card_key ] : array(),
				'sync_data'    => is_array( $sync_data ) ? $sync_data : array(),
			);
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
				$summary_payload = array_merge(
					$base,
					array(
						'sync_scope'        => 'summary',
						'card_payload_full_json' => $card_payload_full_json,
					),
					w91099ch_flatten_payload_for_sheet( $summary, 'summary_' )
				);
				$summary_payload[ $card_key ] = array(
					'data'    => array(),
					'summary' => $summary,
				);
				return w91099ch_Webhook_Dispatcher::dispatch_raw_payload( $summary_payload, $event_type );
			}

			$attempted = 0;
			$sent      = 0;
			$errors    = array();
			foreach ( $rows as $index => $row ) {
				$row_arr = is_array( $row ) ? $row : array( 'value' => is_scalar( $row ) ? (string) $row : wp_json_encode( $row ) );
				$row_payload = array_merge(
					$base,
					array(
						'sync_scope'        => 'row',
						'row_index'         => (int) $index + 1,
						'row_data_json'     => wp_json_encode( $row_arr ),
						'card_payload_full_json' => $card_payload_full_json,
					),
					w91099ch_flatten_payload_for_sheet( $row_arr, 'row_' ),
					w91099ch_flatten_payload_for_sheet( $summary, 'summary_' )
				);
				$row_payload[ $card_key ] = array(
					'data'      => $row_arr,
					'summary'   => $summary,
					'row_index' => (int) $index + 1,
				);

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
		} catch ( Throwable $e ) {
			if ( function_exists( 'w91099ch_log' ) ) {
				w91099ch_log( 'Plugin sync webhook dispatch error: ' . $e->getMessage() );
			}
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
}

if ( ! function_exists( 'w91099ch_flatten_payload_for_sheet' ) ) {
	function w91099ch_flatten_payload_for_sheet( $data, $prefix = '' ) {
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
			} else {
				$out[ $flat_key ] = wp_json_encode( $value );
			}
		}

		return $out;
	}
}

if ( ! function_exists( 'w91099ch_build_full_webhook_payload' ) ) {
	function w91099ch_build_full_webhook_payload( $event_type, $sync_data ) {
		$event_type = sanitize_key( (string) $event_type );
		if ( '' === $event_type ) {
			$event_type = 'plugin_data_synced';
		}

		$event_id = function_exists( 'wp_generate_uuid4' ) ? 'wp_' . wp_generate_uuid4() : 'wp_' . uniqid( '', true );
		$created   = gmdate( 'Y-m-d' );
		$timestamp = gmdate( 'c' );

		$site_url    = (string) get_site_url();
		$site_name   = (string) get_bloginfo( 'name' );
		$admin_email = sanitize_email( (string) get_option( 'admin_email', '' ) );
		$full_rows_limit = (int) apply_filters( 'w91099ch_webhook_full_rows_limit', 10000 );
		if ( $full_rows_limit < 1 ) {
			$full_rows_limit = 1;
		}

		$current_user = wp_get_current_user();
		$user_role    = '';
		if ( $current_user && isset( $current_user->roles ) && is_array( $current_user->roles ) && ! empty( $current_user->roles ) ) {
			$user_role = (string) reset( $current_user->roles );
		}

		$user_profile = array(
			'user_id'    => (int) ( $current_user->ID ?? 0 ),
			'username'   => (string) ( $current_user->user_login ?? '' ),
			'display_name' => (string) ( $current_user->display_name ?? '' ),
			'first_name' => (string) ( $current_user->first_name ?? '' ),
			'last_name'  => (string) ( $current_user->last_name ?? '' ),
			'email'      => (string) ( $current_user->user_email ?? '' ),
			'role'       => $user_role,
			'roles'      => isset( $current_user->roles ) && is_array( $current_user->roles ) ? array_values( array_map( 'strval', $current_user->roles ) ) : array(),
			'registered' => (string) ( $current_user->user_registered ?? '' ),
		);

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins = get_plugins();
		$active      = (array) get_option( 'active_plugins', array() );
		$active_lookup = array_fill_keys( array_map( 'strval', $active ), true );
		$network_active_lookup = array();
		if ( is_multisite() ) {
			$sitewide = get_site_option( 'active_sitewide_plugins', array() );
			if ( is_array( $sitewide ) ) {
				foreach ( $sitewide as $plugin_file => $v ) {
					$network_active_lookup[ (string) $plugin_file ] = true;
				}
			}
		}

		$plugin_data = array();
		foreach ( $all_plugins as $plugin_file => $plugin_info ) {
			$slug = dirname( (string) $plugin_file );
			if ( '.' === $slug || '' === $slug ) {
				$slug = basename( (string) $plugin_file, '.php' );
			}

			$is_active = isset( $active_lookup[ (string) $plugin_file ] ) || isset( $network_active_lookup[ (string) $plugin_file ] );
			$plugin_data[] = array(
				'plugin_file' => (string) $plugin_file,
				'slug'    => (string) $slug,
				'name'    => (string) ( $plugin_info['Name'] ?? '' ),
				'version' => (string) ( $plugin_info['Version'] ?? '' ),
				'active'  => $is_active,
				'author'  => (string) ( $plugin_info['AuthorName'] ?? $plugin_info['Author'] ?? '' ),
				'description' => (string) ( $plugin_info['Description'] ?? '' ),
				'plugin_uri'  => (string) ( $plugin_info['PluginURI'] ?? '' ),
				'network_active' => isset( $network_active_lookup[ (string) $plugin_file ] ),
			);
		}

		$team_users    = get_users(
			array(
				'fields' => array( 'ID', 'user_login', 'user_email', 'display_name', 'user_registered', 'roles' ),
			)
		);
		$team = array();
		foreach ( $team_users as $user ) {
			$role = '';
			$ud   = get_userdata( (int) $user->ID );
			if ( $ud && isset( $ud->roles ) && is_array( $ud->roles ) && ! empty( $ud->roles ) ) {
				$role = (string) reset( $ud->roles );
			}
			$team[] = array(
				'user_id'  => (int) $user->ID,
				'username' => (string) $user->user_login,
				'role'     => $role,
				'roles'    => isset( $ud->roles ) && is_array( $ud->roles ) ? array_values( array_map( 'strval', $ud->roles ) ) : array(),
				'email'    => (string) $user->user_email,
				'display_name' => (string) ( $user->display_name ?? '' ),
				'registered'   => (string) ( $user->user_registered ?? '' ),
			);
		}

		if ( ! class_exists( 'w91099ch_Form_Plugin_Detector' ) ) {
			require_once w91099ch_PLUGIN_PATH . 'includes/class-form-plugin-detector.php';
		}
		$form_detector = new w91099ch_Form_Plugin_Detector();
		$form_plugins  = $form_detector->get_form_plugins_data();
		if ( ! is_array( $form_plugins ) ) {
			$form_plugins = array();
		}

		$total_forms           = 0;
		$active_forms          = 0;
		$forms_preview         = array();
		$forms_by_plugin       = array();
		$forms_count_by_plugin = array();
		foreach ( $form_plugins as $slug => $plugin ) {
			if ( ! is_array( $plugin ) ) {
				continue;
			}
			if ( ! empty( $plugin['active'] ) ) {
				$count = isset( $plugin['forms_count'] ) ? (int) $plugin['forms_count'] : 0;
				$total_forms += $count;
				$active_forms += $count;
			}

			$plugin_forms = $form_detector->get_plugin_forms( (string) $slug );
			if ( ! is_array( $plugin_forms ) ) {
				$plugin_forms = array();
			}
			$forms_by_plugin[ (string) $slug ]       = $plugin_forms;
			$forms_count_by_plugin[ (string) $slug ] = count( $plugin_forms );
			if ( ! empty( $plugin_forms ) ) {
				foreach ( $plugin_forms as $f ) {
					if ( is_array( $f ) ) {
						$f['plugin_slug'] = (string) $slug;
						$forms_preview[] = $f;
					}
				}
			}
		}
		$form_entries_preview = $form_detector->get_entries_preview( '', $full_rows_limit );
		if ( ! is_array( $form_entries_preview ) ) {
			$form_entries_preview = array();
		}

		$forms_plugin = array(
			'plugins'               => $form_plugins,
			'total_forms'           => $total_forms,
			'active_forms'          => $active_forms,
			'forms_preview'         => $forms_preview,
			'forms_by_plugin'       => $forms_by_plugin,
			'forms_count_by_plugin' => $forms_count_by_plugin,
			'entries_preview'       => $form_entries_preview,
			'total_entries'         => count( $form_entries_preview ),
		);

		if ( ! class_exists( 'w91099ch_Contractor_Plugin_Detector' ) ) {
			require_once w91099ch_PLUGIN_PATH . 'includes/contractor-plugin-detector-init.php';
		}
		$contractor_detector = new w91099ch_Contractor_Plugin_Detector();
		$membership_plugins  = $contractor_detector->get_contractor_plugins_data();
		$members_preview     = $contractor_detector->get_contractors_preview( '', $full_rows_limit );
		if ( ! is_array( $membership_plugins ) ) {
			$membership_plugins = array();
		}
		if ( ! is_array( $members_preview ) ) {
			$members_preview = array();
		}
		$members_by_plugin = array();
		foreach ( $membership_plugins as $slug => $plugin ) {
			$rows = $contractor_detector->get_contractors_preview( (string) $slug, $full_rows_limit );
			$members_by_plugin[ (string) $slug ] = is_array( $rows ) ? $rows : array();
		}
		$membership_data = array(
			'plugins'           => $membership_plugins,
			'total_members'     => count( $members_preview ),
			'members'           => $members_preview,
			'members_by_plugin' => $members_by_plugin,
		);

		if ( ! class_exists( 'w91099ch_Freelancer_Contractor_Plugin_Detector' ) ) {
			require_once w91099ch_PLUGIN_PATH . 'includes/freelancer-contractor-plugin-detector-init.php';
		}
		$freelancer_detector = new w91099ch_Freelancer_Contractor_Plugin_Detector();
		$freelancer_plugins  = $freelancer_detector->get_freelancer_contractor_plugins_data();
		$freelancers_preview = $freelancer_detector->get_contractors_preview( '', $full_rows_limit );
		if ( ! is_array( $freelancer_plugins ) ) {
			$freelancer_plugins = array();
		}
		if ( ! is_array( $freelancers_preview ) ) {
			$freelancers_preview = array();
		}
		$contractors_by_plugin = array();
		foreach ( $freelancer_plugins as $slug => $plugin ) {
			$rows = $freelancer_detector->get_contractors_preview( (string) $slug, $full_rows_limit );
			$contractors_by_plugin[ (string) $slug ] = is_array( $rows ) ? $rows : array();
		}
		$freelancer_data = array(
			'plugins'               => $freelancer_plugins,
			'total_contractors'     => count( $freelancers_preview ),
			'contractors'           => $freelancers_preview,
			'contractors_by_plugin' => $contractors_by_plugin,
		);

		if ( ! class_exists( 'w91099ch_Accounting_Bookkeeping_Plugin_Detector' ) ) {
			require_once w91099ch_PLUGIN_PATH . 'includes/accounting-bookkeeping-plugin-detector-init.php';
		}
		$accounting_detector = new w91099ch_Accounting_Bookkeeping_Plugin_Detector();
		$accounting_plugins  = $accounting_detector->get_accounting_bookkeeping_plugins_data();
		$accounting_preview  = $accounting_detector->get_plugins_preview( '', $full_rows_limit );
		if ( ! is_array( $accounting_plugins ) ) {
			$accounting_plugins = array();
		}
		if ( ! is_array( $accounting_preview ) ) {
			$accounting_preview = array();
		}
		$accounting_records_by_plugin = array();
		foreach ( $accounting_plugins as $slug => $plugin ) {
			$rows = $accounting_detector->get_plugins_preview( (string) $slug, $full_rows_limit );
			$accounting_records_by_plugin[ (string) $slug ] = is_array( $rows ) ? $rows : array();
		}
		$accounting_data = array(
			'plugins'           => $accounting_plugins,
			'total_records'     => count( $accounting_preview ),
			'records'           => $accounting_preview,
			'records_by_plugin' => $accounting_records_by_plugin,
		);

		if ( ! class_exists( 'w91099ch_Wallet_Payout_Plugin_Detector' ) ) {
			require_once w91099ch_PLUGIN_PATH . 'includes/wallet-payout-plugin-detector-init.php';
		}
		$wallet_detector = new w91099ch_Wallet_Payout_Plugin_Detector();
		$wallet_plugins  = $wallet_detector->get_wallet_payout_plugins_data();
		$wallet_preview  = $wallet_detector->get_plugins_preview( '', $full_rows_limit );
		$wallet_entries  = $wallet_detector->get_wallet_entries_preview( '', $full_rows_limit );
		if ( ! is_array( $wallet_plugins ) ) {
			$wallet_plugins = array();
		}
		if ( ! is_array( $wallet_preview ) ) {
			$wallet_preview = array();
		}
		if ( ! is_array( $wallet_entries ) ) {
			$wallet_entries = array();
		}
		$wallet_entries_by_plugin = array();
		foreach ( $wallet_plugins as $slug => $plugin ) {
			$rows = $wallet_detector->get_wallet_entries_preview( (string) $slug, $full_rows_limit );
			$wallet_entries_by_plugin[ (string) $slug ] = is_array( $rows ) ? $rows : array();
		}
		$payout_data = array(
			'wallet_plugins'         => $wallet_plugins,
			'total_wallet_plugins'   => count( $wallet_plugins ),
			'wallet_preview'         => $wallet_preview,
			'wallet_entries'         => $wallet_entries,
			'total_wallet_records'   => count( $wallet_entries ),
			'wallet_entries_by_plugin' => $wallet_entries_by_plugin,
		);

		if ( ! class_exists( 'w91099ch_Affiliate_Manager' ) ) {
			require_once w91099ch_PLUGIN_PATH . 'includes/class-affiliate-manager.php';
		}
		$affiliate_manager = new w91099ch_Affiliate_Manager();
		$affiliate_plugins = $affiliate_manager->detect_affiliate_plugins( true );
		$affiliates_block  = $affiliate_manager->get_affiliates_for_display( '', $full_rows_limit, 0 );
		$affiliates_sync   = $affiliate_manager->get_all_affiliates_for_sync( '' );
		$formatted_affiliates = $affiliate_manager->format_affiliates_for_api( $affiliates_sync );
		$payout_summary    = $affiliate_manager->get_payout_summary();
		$excluded_affiliate_ids = get_option( 'w91099ch_excluded_affiliate_ids', array() );
		if ( ! is_array( $excluded_affiliate_ids ) ) {
			$excluded_affiliate_ids = array();
		}
		$affiliate_data = array(
			'plugins'              => $affiliate_plugins,
			'frontend_affiliates'  => $affiliates_block['affiliates'] ?? array(),
			'affiliates'           => is_array( $affiliates_sync ) ? $affiliates_sync : array(),
			'formatted_affiliates' => is_array( $formatted_affiliates ) ? $formatted_affiliates : array(),
			'total_affiliates'     => is_array( $affiliates_sync ) ? count( $affiliates_sync ) : 0,
			'frontend_total'       => isset( $affiliates_block['total_count'] ) ? (int) $affiliates_block['total_count'] : 0,
			'payout_summary'       => $payout_summary,
			'filters'              => array(
				'excluded_affiliate_ids' => array_values( array_map( 'sanitize_text_field', $excluded_affiliate_ids ) ),
			),
		);

		$payload = array(
			'event_type'      => $event_type,
			'event_id'        => $event_id,
			'created'         => $created,
			'timestamp'       => $timestamp,
			'source'          => 'wordpress_w9_1099_chaser',
			'site_url'        => $site_url,
			'site_name'       => $site_name,
			'admin_email'     => $admin_email,
			'context_action'  => 'dashboard_plugin_card_sync',
			'user_profile'    => $user_profile,
			'plugin_data'     => $plugin_data,
			'team'            => $team,
			'forms_plugin'    => $forms_plugin,
			'membership_data' => $membership_data,
			'freelancer_data' => $freelancer_data,
			'accounting_data' => $accounting_data,
			'payout_data'     => $payout_data,
			'affiliates_data' => $affiliate_data,
			'sync_data'       => is_array( $sync_data ) ? $sync_data : array(),
		);

		$card_specific_events = array(
			'form_plugins_synced'                  => array( 'forms_plugin' ),
			'contractor_plugins_synced'            => array( 'membership_data' ),
			'freelancer_contractor_plugins_synced' => array( 'freelancer_data' ),
			'accounting_bookkeeping_plugins_synced'=> array( 'accounting_data' ),
			'wallet_payout_plugins_synced'         => array( 'payout_data' ),
		);

		if ( isset( $card_specific_events[ $event_type ] ) ) {
			$keep = array_merge(
				array(
					'event_type',
					'event_id',
					'created',
					'timestamp',
					'source',
					'site_url',
					'site_name',
					'admin_email',
					'context_action',
				),
				$card_specific_events[ $event_type ]
			);

			$filtered = array();
			foreach ( $keep as $key ) {
				if ( array_key_exists( $key, $payload ) ) {
					$filtered[ $key ] = $payload[ $key ];
				}
			}

			$card_key = $card_specific_events[ $event_type ][0] ?? '';
			if ( '' !== $card_key && isset( $filtered[ $card_key ] ) ) {
				$card_payload = array(
					'data' => $filtered[ $card_key ],
				);
				if ( ! empty( $sync_data ) && is_array( $sync_data ) ) {
					$card_payload['sync_data'] = $sync_data;
				}
				$filtered[ $card_key ] = $card_payload;
			}

			return $filtered;
		}

		return $payload;
	}
}

// Refresh form plugins
add_action( 'wp_ajax_w91099ch_refresh_form_plugins', 'w91099ch_refresh_form_plugins' );
function w91099ch_refresh_form_plugins() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	$detector    = new w91099ch_Form_Plugin_Detector();
	$plugins     = $detector->get_form_plugins_data();
	$total_forms = 0;

	if ( ! is_array( $plugins ) ) {
		$plugins = array();
	}

	foreach ( $plugins as $plugin ) {
		if ( ! is_array( $plugin ) ) {
			continue;
		}
		if ( ! empty( $plugin['active'] ) ) {
			$total_forms += isset( $plugin['forms_count'] ) ? (int) $plugin['forms_count'] : 0;
		}
	}

	wp_send_json_success(
		array(
			'plugins'     => $plugins,
			'total_forms' => $total_forms,
		)
	);
}

// Get forms for a specific plugin
add_action( 'wp_ajax_w91099ch_get_plugin_forms', 'w91099ch_get_plugin_forms' );
function w91099ch_get_plugin_forms() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	$plugin_slug_raw = filter_input( INPUT_POST, 'plugin_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	$plugin_slug     = is_string( $plugin_slug_raw ) ? sanitize_text_field( wp_unslash( $plugin_slug_raw ) ) : '';

	$detector = new w91099ch_Form_Plugin_Detector();

	if ( empty( $plugin_slug ) ) {
		$plugins = $detector->get_form_plugins_data();
		$forms   = array();

		foreach ( $plugins as $slug => $plugin ) {
			if ( ! is_array( $plugin ) || empty( $plugin['active'] ) ) {
				continue;
			}

			$plugin_name  = isset( $plugin['name'] ) ? (string) $plugin['name'] : (string) $slug;
			$plugin_forms = $detector->get_plugin_forms( $slug );
			if ( ! is_array( $plugin_forms ) || empty( $plugin_forms ) ) {
				continue;
			}

			foreach ( $plugin_forms as $f ) {
				if ( ! is_array( $f ) ) {
					continue;
				}
				$title            = isset( $f['title'] ) ? (string) $f['title'] : '';
				$f['plugin_slug'] = (string) $slug;
				$f['plugin_name'] = $plugin_name;
				$f['title']       = $plugin_name . ' — ' . $title;
				$forms[]          = $f;
			}
		}
	} else {
		$forms = $detector->get_plugin_forms( $plugin_slug );
	}

	wp_send_json_success(
		array(
			'forms'       => $forms,
			'total_count' => count( $forms ),
		)
	);
}

// Get excluded forms
add_action( 'wp_ajax_w91099ch_get_excluded_forms', 'w91099ch_get_excluded_forms' );
function w91099ch_get_excluded_forms() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	$excluded = get_option( 'w91099ch_excluded_forms', array() );
	$excluded = is_array( $excluded ) ? $excluded : array();
	wp_send_json_success( array( 'excluded_ids' => $excluded ) );
}

// Set excluded forms
add_action( 'wp_ajax_w91099ch_set_excluded_forms', 'w91099ch_set_excluded_forms' );
function w91099ch_set_excluded_forms() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	$excluded_raw = isset( $_POST['excluded_ids'] ) ? wp_unslash( $_POST['excluded_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitized immediately below.
	$excluded_ids = is_array( $excluded_raw ) ? array_map( 'sanitize_text_field', $excluded_raw ) : array();

	update_option( 'w91099ch_excluded_forms', $excluded_ids );
	wp_send_json_success( array( 'message' => esc_html__( 'Excluded forms updated', 'w9-1099-chaser' ) ) );
}

// Get form entries
add_action( 'wp_ajax_w91099ch_get_form_entries', 'w91099ch_get_form_entries' );
function w91099ch_get_form_entries() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	$plugin_slug_raw = filter_input( INPUT_POST, 'plugin_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	$plugin_slug     = is_string( $plugin_slug_raw ) ? sanitize_text_field( wp_unslash( $plugin_slug_raw ) ) : '';
	$form_id_raw      = filter_input( INPUT_POST, 'form_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	$form_id          = is_string( $form_id_raw ) ? absint( wp_unslash( $form_id_raw ) ) : 0;

	if ( empty( $plugin_slug ) || empty( $form_id ) ) {
		wp_send_json_error( esc_html__( 'Plugin slug and form ID are required', 'w9-1099-chaser' ) );
	}

	$detector = new w91099ch_Form_Plugin_Detector();
	$entries  = $detector->get_form_entries( $plugin_slug, $form_id );

	wp_send_json_success(
		array(
			'entries'     => $entries,
			'total_count' => count( $entries ),
		)
	);
}

// Get entries preview (across plugins)
add_action( 'wp_ajax_w91099ch_get_form_entries_preview', 'w91099ch_get_form_entries_preview' );
function w91099ch_get_form_entries_preview() {
	try {
		check_ajax_referer( 'w91099ch_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
		}

		$plugin_slug_raw = filter_input( INPUT_POST, 'plugin_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$plugin_slug     = is_string( $plugin_slug_raw ) ? sanitize_text_field( wp_unslash( $plugin_slug_raw ) ) : '';
		$limit_raw       = filter_input( INPUT_POST, 'limit', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$limit           = is_string( $limit_raw ) ? absint( wp_unslash( $limit_raw ) ) : 25;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$detector = new w91099ch_Form_Plugin_Detector();
		$entries  = $detector->get_entries_preview( $plugin_slug, $limit );

		wp_send_json_success(
			array(
				'entries'     => $entries,
				'total_count' => count( $entries ),
			)
		);
	} catch ( Exception $e ) {
		error_log( 'W9-1099-Chaser Form Entries AJAX Error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Error loading form entries: ' . $e->getMessage() ) );
	}
}

// Reset W-9 form download statistics
add_action( 'wp_ajax_w91099ch_reset_download_stats', 'w91099ch_reset_download_stats' );
function w91099ch_reset_download_stats() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	update_option( 'w91099ch_total_downloads', 0 );
	update_option( 'w91099ch_downloads_print_to_pdf', 0 );
	update_option( 'w91099ch_downloads_govt_form', 0 );

	wp_send_json_success(
		array(
			'total_downloads' => (int) get_option( 'w91099ch_total_downloads', 0 ),
			'print_to_pdf'   => (int) get_option( 'w91099ch_downloads_print_to_pdf', 0 ),
			'official_forms' => (int) get_option( 'w91099ch_downloads_govt_form', 0 ),
		)
	);
}

// Sync Form Plugins Data
add_action( 'wp_ajax_w91099ch_sync_form_plugins', 'w91099ch_sync_form_plugins' );
function w91099ch_sync_form_plugins() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	if ( ! class_exists( 'w91099ch_Form_Plugin_Detector' ) ) {
		require_once w91099ch_PLUGIN_PATH . 'includes/class-form-plugin-detector.php';
	}

	$detector = new w91099ch_Form_Plugin_Detector();
	$plugins  = $detector->get_form_plugins_data();
	$full_rows_limit = (int) apply_filters( 'w91099ch_webhook_full_rows_limit', 10000 );
	if ( $full_rows_limit < 1 ) {
		$full_rows_limit = 1;
	}
	
	// Prepare data for sync
	$sync_data = array(
		'plugin_type' => 'form_plugins',
		'plugins' => array(),
		'total_forms' => 0,
		'total_entries' => 0,
		'sync_timestamp' => current_time( 'mysql' )
	);

	foreach ( $plugins as $slug => $plugin ) {
		if ( ! is_array( $plugin ) || empty( $plugin['active'] ) ) {
			continue;
		}

		$plugin_data = array(
			'slug' => $slug,
			'name' => isset( $plugin['name'] ) ? $plugin['name'] : $slug,
			'version' => isset( $plugin['version'] ) ? $plugin['version'] : 'unknown',
			'forms_count' => isset( $plugin['forms_count'] ) ? $plugin['forms_count'] : 0,
			'active' => true
		);

		// Get forms data for this plugin
		$forms = $detector->get_plugin_forms( $slug );
		if ( is_array( $forms ) ) {
			$plugin_data['forms'] = $forms;
		} else {
			$plugin_data['forms'] = array();
		}
		$entries = $detector->get_entries_preview( (string) $slug, $full_rows_limit );
		$plugin_data['entries'] = is_array( $entries ) ? $entries : array();
		$plugin_data['entries_count'] = count( $plugin_data['entries'] );

		$sync_data['plugins'][] = $plugin_data;
		$sync_data['total_forms'] += $plugin_data['forms_count'];
		$sync_data['total_entries'] += $plugin_data['entries_count'];
	}

	// Here you would normally send this data to MyPowerly API
	// For now, we'll just simulate the sync and store it in options
	update_option( 'w91099ch_form_plugins_last_sync', $sync_data );
	$webhook_status = w91099ch_dispatch_webhook_for_plugin_sync( 'form_plugins_synced', $sync_data );
	
	wp_send_json_success(
		array(
			'message' => esc_html__( 'Form plugins data synced successfully!', 'w9-1099-chaser' ),
			'sync_data' => $sync_data,
			'webhook_status' => $webhook_status,
			'synced_count' => count( $sync_data['plugins'] ),
			'total_forms' => $sync_data['total_forms']
		)
	);
}

// Sync Wallet/Payout Plugins Data
add_action( 'wp_ajax_w91099ch_sync_wallet_payout_plugins', 'w91099ch_sync_wallet_payout_plugins' );
function w91099ch_sync_wallet_payout_plugins() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	if ( ! class_exists( 'w91099ch_Wallet_Payout_Plugin_Detector' ) ) {
		require_once w91099ch_PLUGIN_PATH . 'includes/wallet-payout-plugin-detector-init.php';
	}

	$detector = new w91099ch_Wallet_Payout_Plugin_Detector();
	$plugins  = $detector->get_wallet_payout_plugins_data();
	$full_rows_limit = (int) apply_filters( 'w91099ch_webhook_full_rows_limit', 10000 );
	if ( $full_rows_limit < 1 ) {
		$full_rows_limit = 1;
	}
	
	$sync_data = array(
		'plugin_type' => 'wallet_payout_plugins',
		'plugins' => array(),
		'total_wallets' => 0,
		'total_records' => 0,
		'sync_timestamp' => current_time( 'mysql' )
	);

	foreach ( $plugins as $slug => $plugin ) {
		if ( ! is_array( $plugin ) ) {
			continue;
		}

		$plugin_data = array(
			'slug' => $slug,
			'name' => isset( $plugin['name'] ) ? $plugin['name'] : $slug,
			'version' => isset( $plugin['version'] ) ? $plugin['version'] : 'unknown',
			'active' => isset( $plugin['active'] ) ? $plugin['active'] : false
		);
		$wallet_entries = $detector->get_wallet_entries_preview( (string) $slug, $full_rows_limit );
		$plugin_data['wallet_entries'] = is_array( $wallet_entries ) ? $wallet_entries : array();
		$plugin_data['records_count'] = count( $plugin_data['wallet_entries'] );

		$sync_data['plugins'][] = $plugin_data;
		if ( $plugin_data['active'] ) {
			$sync_data['total_wallets']++;
		}
		$sync_data['total_records'] += $plugin_data['records_count'];
	}

	update_option( 'w91099ch_wallet_payout_plugins_last_sync', $sync_data );
	$webhook_status = w91099ch_dispatch_webhook_for_plugin_sync( 'wallet_payout_plugins_synced', $sync_data );
	
	wp_send_json_success(
		array(
			'message' => esc_html__( 'Wallet/Payout plugins data synced successfully!', 'w9-1099-chaser' ),
			'sync_data' => $sync_data,
			'webhook_status' => $webhook_status,
			'synced_count' => count( $sync_data['plugins'] ),
			'total_wallets' => $sync_data['total_wallets']
		)
	);
}

// Sync Freelancer/Contractor Plugins Data
add_action( 'wp_ajax_w91099ch_sync_freelancer_contractor_plugins', 'w91099ch_sync_freelancer_contractor_plugins' );
function w91099ch_sync_freelancer_contractor_plugins() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	if ( ! class_exists( 'w91099ch_Freelancer_Contractor_Plugin_Detector' ) ) {
		require_once w91099ch_PLUGIN_PATH . 'includes/freelancer-contractor-plugin-detector-init.php';
	}

	$detector = new w91099ch_Freelancer_Contractor_Plugin_Detector();
	$plugins  = $detector->get_freelancer_contractor_plugins_data();
	$full_rows_limit = (int) apply_filters( 'w91099ch_webhook_full_rows_limit', 10000 );
	if ( $full_rows_limit < 1 ) {
		$full_rows_limit = 1;
	}
	
	$sync_data = array(
		'plugin_type' => 'freelancer_contractor_plugins',
		'plugins' => array(),
		'total_contractors' => 0,
		'sync_timestamp' => current_time( 'mysql' )
	);

	foreach ( $plugins as $slug => $plugin ) {
		if ( ! is_array( $plugin ) ) {
			continue;
		}

		$plugin_data = array(
			'slug' => $slug,
			'name' => isset( $plugin['name'] ) ? $plugin['name'] : $slug,
			'version' => isset( $plugin['version'] ) ? $plugin['version'] : 'unknown',
			'active' => isset( $plugin['active'] ) ? $plugin['active'] : false
		);

		// Get contractors data for this plugin
		$contractors = $detector->get_contractors_preview( $slug, $full_rows_limit );
		if ( is_array( $contractors ) ) {
			$plugin_data['contractors'] = $contractors;
		} else {
			$contractors = array();
			$plugin_data['contractors'] = array();
		}
		$plugin_data['contractors_count'] = count( $plugin_data['contractors'] );

		$sync_data['plugins'][] = $plugin_data;
		if ( $plugin_data['active'] ) {
			$sync_data['total_contractors'] += count( $contractors );
		}
	}

	update_option( 'w91099ch_freelancer_contractor_plugins_last_sync', $sync_data );
	$webhook_status = w91099ch_dispatch_webhook_for_plugin_sync( 'freelancer_contractor_plugins_synced', $sync_data );
	
	wp_send_json_success(
		array(
			'message' => esc_html__( 'Freelancer/Contractor plugins data synced successfully!', 'w9-1099-chaser' ),
			'sync_data' => $sync_data,
			'webhook_status' => $webhook_status,
			'synced_count' => count( $sync_data['plugins'] ),
			'total_contractors' => $sync_data['total_contractors']
		)
	);
}

// Sync Accounting/Bookkeeping Plugins Data
add_action( 'wp_ajax_w91099ch_sync_accounting_bookkeeping_plugins', 'w91099ch_sync_accounting_bookkeeping_plugins' );
function w91099ch_sync_accounting_bookkeeping_plugins() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	if ( ! class_exists( 'w91099ch_Accounting_Bookkeeping_Plugin_Detector' ) ) {
		require_once w91099ch_PLUGIN_PATH . 'includes/accounting-bookkeeping-plugin-detector-init.php';
	}

	$detector = new w91099ch_Accounting_Bookkeeping_Plugin_Detector();
	$plugins  = $detector->get_accounting_bookkeeping_plugins_data();
	$full_rows_limit = (int) apply_filters( 'w91099ch_webhook_full_rows_limit', 10000 );
	if ( $full_rows_limit < 1 ) {
		$full_rows_limit = 1;
	}
	
	$sync_data = array(
		'plugin_type' => 'accounting_bookkeeping_plugins',
		'plugins' => array(),
		'total_plugins' => 0,
		'total_records' => 0,
		'sync_timestamp' => current_time( 'mysql' )
	);

	foreach ( $plugins as $slug => $plugin ) {
		if ( ! is_array( $plugin ) ) {
			continue;
		}

		$plugin_data = array(
			'slug' => $slug,
			'name' => isset( $plugin['name'] ) ? $plugin['name'] : $slug,
			'version' => isset( $plugin['version'] ) ? $plugin['version'] : 'unknown',
			'active' => isset( $plugin['active'] ) ? $plugin['active'] : false
		);
		$records = $detector->get_plugins_preview( (string) $slug, $full_rows_limit );
		$plugin_data['records'] = is_array( $records ) ? $records : array();
		$plugin_data['records_count'] = count( $plugin_data['records'] );

		$sync_data['plugins'][] = $plugin_data;
		if ( $plugin_data['active'] ) {
			$sync_data['total_plugins']++;
		}
		$sync_data['total_records'] += $plugin_data['records_count'];
	}

	update_option( 'w91099ch_accounting_bookkeeping_plugins_last_sync', $sync_data );
	$webhook_status = w91099ch_dispatch_webhook_for_plugin_sync( 'accounting_bookkeeping_plugins_synced', $sync_data );
	
	wp_send_json_success(
		array(
			'message' => esc_html__( 'Accounting/Bookkeeping plugins data synced successfully!', 'w9-1099-chaser' ),
			'sync_data' => $sync_data,
			'webhook_status' => $webhook_status,
			'synced_count' => count( $sync_data['plugins'] ),
			'total_plugins' => $sync_data['total_plugins']
		)
	);
}

// Sync Contractor/Membership Plugins Data
add_action( 'wp_ajax_w91099ch_sync_contractor_plugins', 'w91099ch_sync_contractor_plugins' );
function w91099ch_sync_contractor_plugins() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	if ( ! class_exists( 'w91099ch_Contractor_Plugin_Detector' ) ) {
		require_once w91099ch_PLUGIN_PATH . 'includes/contractor-plugin-detector-init.php';
	}

	$detector = new w91099ch_Contractor_Plugin_Detector();
	$plugins  = $detector->get_contractor_plugins_data();
	$full_rows_limit = (int) apply_filters( 'w91099ch_webhook_full_rows_limit', 10000 );
	if ( $full_rows_limit < 1 ) {
		$full_rows_limit = 1;
	}
	
	$sync_data = array(
		'plugin_type' => 'contractor_plugins',
		'plugins' => array(),
		'total_members' => 0,
		'sync_timestamp' => current_time( 'mysql' )
	);

	foreach ( $plugins as $slug => $plugin ) {
		if ( ! is_array( $plugin ) ) {
			continue;
		}

		$plugin_data = array(
			'slug' => $slug,
			'name' => isset( $plugin['name'] ) ? $plugin['name'] : $slug,
			'version' => isset( $plugin['version'] ) ? $plugin['version'] : 'unknown',
			'active' => isset( $plugin['active'] ) ? $plugin['active'] : false
		);

		// Get members data for this plugin
		$members = $detector->get_contractors_preview( $slug, $full_rows_limit );
		if ( is_array( $members ) ) {
			$plugin_data['members'] = $members;
		} else {
			$members = array();
			$plugin_data['members'] = array();
		}
		$plugin_data['members_count'] = count( $plugin_data['members'] );

		$sync_data['plugins'][] = $plugin_data;
		if ( $plugin_data['active'] ) {
			$sync_data['total_members'] += count( $members );
		}
	}

	update_option( 'w91099ch_contractor_plugins_last_sync', $sync_data );
	$webhook_status = w91099ch_dispatch_webhook_for_plugin_sync( 'contractor_plugins_synced', $sync_data );
	
	wp_send_json_success(
		array(
			'message' => esc_html__( 'Contractor/Membership plugins data synced successfully!', 'w9-1099-chaser' ),
			'sync_data' => $sync_data,
			'webhook_status' => $webhook_status,
			'synced_count' => count( $sync_data['plugins'] ),
			'total_members' => $sync_data['total_members']
		)
	);
}
