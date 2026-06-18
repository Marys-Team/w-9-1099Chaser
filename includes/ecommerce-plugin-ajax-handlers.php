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
		$plugin = w91099ch_Plugin::get_instance();
		if ( ! isset( $plugin->core ) || ! method_exists( $plugin->core, 'get_ecommerce_plugin_sheets' ) ) {
			wp_send_json_error( esc_html__( 'Core not available', 'w9-1099-chaser' ) );
		}

		$result = $plugin->core->get_ecommerce_plugin_sheets();

		update_option( 'w91099ch_last_ecommerce_sync', current_time( 'mysql' ) );

		wp_send_json_success(
			array(
				'message'       => sprintf(
					'Successfully synced %d ecommerce plugin%s',
					$result['synced_count'],
					$result['synced_count'] !== 1 ? 's' : ''
				),
				'synced_count'  => $result['synced_count'],
				'sheets'        => $result['sheets'],
				'errors'        => $result['total_errors'],
				'sent'          => $result['total_sent'],
				'total_plugins' => $result['synced_count'],
				'timestamp'     => current_time( 'mysql' ),
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
