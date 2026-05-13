<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_w91099ch_get_ecommerce_plugins', 'w91099ch_get_ecommerce_plugins' );
function w91099ch_get_ecommerce_plugins() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	try {
		if ( ! class_exists( 'w91099ch_Ecommerce_Plugin_Detector' ) ) {
			require_once w91099ch_PLUGIN_PATH . 'includes/class-ecommerce-plugin-detector.php';
		}

		$detector = new w91099ch_Ecommerce_Plugin_Detector();
		$plugins  = $detector->get_ecommerce_plugins_data();

		wp_send_json_success(
			array(
				'plugins'      => $plugins,
				'total_count'  => count( $plugins ),
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

add_action( 'wp_ajax_w91099ch_get_ecommerce_plugins_preview', 'w91099ch_get_ecommerce_plugins_preview' );
function w91099ch_get_ecommerce_plugins_preview() {
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

	if ( ! class_exists( 'w91099ch_Ecommerce_Plugin_Detector' ) ) {
		require_once w91099ch_PLUGIN_PATH . 'includes/class-ecommerce-plugin-detector.php';
	}

	$detector = new w91099ch_Ecommerce_Plugin_Detector();
	$plugins  = $detector->get_ecommerce_plugins_data();

	$rows = array();
	foreach ( $plugins as $slug => $plugin ) {
		if ( '' !== $plugin_slug && (string) $slug !== (string) $plugin_slug ) {
			continue;
		}
		$rows[] = array(
			'slug'    => (string) $slug,
			'name'    => isset( $plugin['name'] ) ? (string) $plugin['name'] : (string) $slug,
			'version' => isset( $plugin['version'] ) ? (string) $plugin['version'] : '',
			'active'  => ! empty( $plugin['active'] ),
		);
	}

	wp_send_json_success(
		array(
			'rows'        => $rows,
			'total_count' => count( $rows ),
		)
	);
}

// Ecommerce Sync Handler
add_action( 'wp_ajax_w91099ch_sync_ecommerce_plugins', 'w91099ch_sync_ecommerce_plugins' );
function w91099ch_sync_ecommerce_plugins() {
	check_ajax_referer( 'w91099ch_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized', 'w9-1099-chaser' ) );
	}

	try {
		$plugin_slug_raw = filter_input( INPUT_POST, 'plugin_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$plugin_slug     = is_string( $plugin_slug_raw ) ? sanitize_text_field( wp_unslash( $plugin_slug_raw ) ) : '';

		error_log( "W9-1099-Chaser: Starting ecommerce sync for plugin: " . $plugin_slug );

		if ( ! class_exists( 'w91099ch_Ecommerce_Plugin_Detector' ) ) {
			require_once w91099ch_PLUGIN_PATH . 'includes/class-ecommerce-plugin-detector.php';
		}

		$detector = new w91099ch_Ecommerce_Plugin_Detector();
		
		// Get ecommerce data
		$all_plugins = $detector->get_ecommerce_plugins_data();
		if ( ! is_array( $all_plugins ) ) {
			$all_plugins = array();
		}

		$plugins_for_sync = array();
		foreach ( $all_plugins as $slug => $plugin ) {
			if ( '' !== $plugin_slug && (string) $slug !== (string) $plugin_slug ) {
				continue;
			}
			$plugins_for_sync[] = array(
				'slug'    => (string) $slug,
				'name'    => isset( $plugin['name'] ) ? (string) $plugin['name'] : (string) $slug,
				'version' => isset( $plugin['version'] ) ? (string) $plugin['version'] : '',
				'active'  => ! empty( $plugin['active'] ),
			);
		}

		error_log( "W9-1099-Chaser: Found " . count( $plugins_for_sync ) . " ecommerce plugins" );

		$synced_count = 0;
		$webhook_status = array(
			'attempted' => 0,
			'sent' => 0,
			'errors' => array()
		);

		if ( ! empty( $plugins_for_sync ) ) {
			$sync_data = array(
				'plugin_type'    => 'ecommerce_plugins',
				'plugins'        => $plugins_for_sync,
				'total_plugins'  => count( $plugins_for_sync ),
				'active_plugins' => count(
					array_filter(
						$plugins_for_sync,
						static function( $plugin_item ) {
							return is_array( $plugin_item ) && ! empty( $plugin_item['active'] );
						}
					)
				),
				'sync_timestamp' => current_time( 'mysql' ),
			);

			if ( function_exists( 'w91099ch_dispatch_webhook_for_plugin_sync' ) ) {
				$webhook_status = w91099ch_dispatch_webhook_for_plugin_sync( 'ecommerce_plugins_synced', $sync_data );
			} elseif ( class_exists( 'w91099ch_Webhook_Dispatcher' ) ) {
				$webhook_status = w91099ch_Webhook_Dispatcher::dispatch_raw_payload(
					array(
						'event_type'     => 'ecommerce_plugins_synced',
						'timestamp'      => gmdate( 'c' ),
						'site_url'       => get_site_url(),
						'site_name'      => get_bloginfo( 'name' ),
						'admin_email'    => get_option( 'admin_email' ),
						'sheet_tab'      => 'ecommerce',
						'card_key'       => 'ecommerce_data',
						'sync_scope'     => 'summary',
						'context_action' => 'w91099ch_sync_ecommerce_plugins',
						'summary_total_plugins' => count( $plugins_for_sync ),
					),
					'ecommerce_plugins_synced'
				);
			}

			$synced_count = count( $plugins_for_sync );
		}

		// Update last sync time
		update_option( 'w91099ch_last_ecommerce_sync', current_time( 'mysql' ) );

		wp_send_json_success(
			array(
				'message' => sprintf( 
					'Successfully synced %d ecommerce plugin%s', 
					$synced_count,
					$synced_count !== 1 ? 's' : ''
				),
				'synced_count' => $synced_count,
				'total_plugins' => count( $plugins_for_sync ),
				'webhook_status' => $webhook_status,
				'plugin_slug' => $plugin_slug,
				'timestamp' => current_time( 'mysql' )
			)
		);

	} catch ( Throwable $e ) {
		error_log( "W9-1099-Chaser: Ecommerce sync error: " . $e->getMessage() );
		wp_send_json_error(
			array(
				'message' => 'Sync failed: ' . $e->getMessage()
			)
		);
	}
}
