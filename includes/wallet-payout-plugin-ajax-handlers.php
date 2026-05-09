<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_w91099ch_get_wallet_payout_plugins_preview', 'w91099ch_get_wallet_payout_plugins_preview' );
function w91099ch_get_wallet_payout_plugins_preview() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	$plugin_slug_raw = filter_input( INPUT_POST, 'plugin_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	$plugin_slug     = is_string( $plugin_slug_raw ) ? sanitize_text_field( wp_unslash( $plugin_slug_raw ) ) : '';
	$limit_raw       = filter_input( INPUT_POST, 'limit', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	$limit           = is_string( $limit_raw ) ? absint( wp_unslash( $limit_raw ) ) : 50;
	if ( $limit <= 0 ) {
		$limit = 50;
	}

	$detector = new w91099ch_Wallet_Payout_Plugin_Detector();
	$rows     = $detector->get_plugins_preview( $plugin_slug, $limit );

	wp_send_json_success(
		array(
			'rows'        => $rows,
			'total_count' => count( $rows ),
		)
	);
}

add_action( 'wp_ajax_w91099ch_get_wallet_payout_entries_preview', 'w91099ch_get_wallet_payout_entries_preview' );
function w91099ch_get_wallet_payout_entries_preview() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	try {
		$plugin_slug_raw = filter_input( INPUT_POST, 'plugin_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$plugin_slug     = is_string( $plugin_slug_raw ) ? sanitize_text_field( wp_unslash( $plugin_slug_raw ) ) : '';
		$limit_raw       = filter_input( INPUT_POST, 'limit', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$limit           = is_string( $limit_raw ) ? absint( wp_unslash( $limit_raw ) ) : 25;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$detector = new w91099ch_Wallet_Payout_Plugin_Detector();
		$rows     = $detector->get_wallet_entries_preview( $plugin_slug, $limit );

		wp_send_json_success(
			array(
				'rows'        => $rows,
				'total_count' => count( $rows ),
			)
		);
	} catch ( Throwable $e ) {
		wp_send_json_error(
			array(
				'message' => $e->getMessage(),
			)
		);
	}
}

// Wallet/Payout Sync Handler
add_action( 'wp_ajax_w91099ch_sync_wallet_payout_data', 'w91099ch_sync_wallet_payout_data' );
function w91099ch_sync_wallet_payout_data() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	try {
		$plugin_slug_raw = filter_input( INPUT_POST, 'plugin_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$plugin_slug     = is_string( $plugin_slug_raw ) ? sanitize_text_field( wp_unslash( $plugin_slug_raw ) ) : '';

		error_log( "W9-1099-Chaser: Starting wallet/payout sync for plugin: " . $plugin_slug );

		$detector = new w91099ch_Wallet_Payout_Plugin_Detector();
		
		// Get wallet/payout data
		if ( $plugin_slug !== '' ) {
			// Sync specific plugin
			$wallet_data = $detector->get_wallet_entries_preview( $plugin_slug, 1000 ); // Get up to 1000 records
		} else {
			// Sync all wallet plugins
			$plugins = $detector->get_wallet_payout_plugins_data();
			$wallet_data = array();
			
			foreach ( $plugins as $slug => $plugin ) {
				$plugin_data = $detector->get_wallet_entries_preview( $slug, 500 ); // 500 per plugin
				$wallet_data = array_merge( $wallet_data, $plugin_data );
			}
		}

		error_log( "W9-1099-Chaser: Found " . count( $wallet_data ) . " wallet/payout records" );

		$synced_count = 0;
		$webhook_status = array(
			'attempted' => 0,
			'sent' => 0,
			'errors' => array()
		);

		if ( ! empty( $wallet_data ) ) {
			// Build row-wise sync payload compatible with wallet payout card tabs.
			$all_plugins = $detector->get_wallet_payout_plugins_data();
			if ( ! is_array( $all_plugins ) ) {
				$all_plugins = array();
			}

			$plugins_for_sync = array();
			foreach ( $all_plugins as $slug => $plugin ) {
				if ( '' !== $plugin_slug && (string) $slug !== (string) $plugin_slug ) {
					continue;
				}
				$entries = $detector->get_wallet_entries_preview( (string) $slug, 1000 );
				$plugins_for_sync[] = array(
					'slug'           => (string) $slug,
					'name'           => isset( $plugin['name'] ) ? (string) $plugin['name'] : (string) $slug,
					'version'        => isset( $plugin['version'] ) ? (string) $plugin['version'] : '',
					'active'         => ! empty( $plugin['active'] ),
					'wallet_entries' => is_array( $entries ) ? $entries : array(),
					'records_count'  => is_array( $entries ) ? count( $entries ) : 0,
				);
			}

			$sync_data = array(
				'plugin_type'    => 'wallet_payout_plugins',
				'plugins'        => $plugins_for_sync,
				'total_wallets'  => count(
					array_filter(
						$plugins_for_sync,
						static function( $plugin_item ) {
							return is_array( $plugin_item ) && ! empty( $plugin_item['active'] );
						}
					)
				),
				'total_records'  => count( $wallet_data ),
				'sync_timestamp' => current_time( 'mysql' ),
			);

			if ( function_exists( 'w91099ch_dispatch_webhook_for_plugin_sync' ) ) {
				$webhook_status = w91099ch_dispatch_webhook_for_plugin_sync( 'wallet_payout_plugins_synced', $sync_data );
			} elseif ( class_exists( 'w91099ch_Webhook_Dispatcher' ) ) {
				$webhook_status = w91099ch_Webhook_Dispatcher::dispatch_raw_payload(
					array(
						'event_type'     => 'wallet_payout_plugins_synced',
						'timestamp'      => gmdate( 'c' ),
						'site_url'       => get_site_url(),
						'site_name'      => get_bloginfo( 'name' ),
						'admin_email'    => get_option( 'admin_email' ),
						'sheet_tab'      => 'wallet_payout',
						'card_key'       => 'payout_data',
						'sync_scope'     => 'summary',
						'context_action' => 'w91099ch_sync_wallet_payout_data',
						'summary_total_records' => count( $wallet_data ),
					),
					'wallet_payout_plugins_synced'
				);
			}

			$synced_count = count( $wallet_data );
		}

		// Update last sync time
		update_option( 'w91099ch_last_wallet_payout_sync', current_time( 'mysql' ) );

		wp_send_json_success(
			array(
				'message' => sprintf( 
					'Successfully synced %d wallet/payout records', 
					$synced_count 
				),
				'synced_count' => $synced_count,
				'webhook_status' => $webhook_status,
				'plugin_slug' => $plugin_slug,
				'timestamp' => current_time( 'mysql' )
			)
		);

	} catch ( Throwable $e ) {
		error_log( "W9-1099-Chaser: Wallet/payout sync error: " . $e->getMessage() );
		wp_send_json_error(
			array(
				'message' => 'Sync failed: ' . $e->getMessage()
			)
		);
	}
}
