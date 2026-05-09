<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_w91099ch_get_accounting_bookkeeping_plugins_preview', 'w91099ch_get_accounting_bookkeeping_plugins_preview' );
function w91099ch_get_accounting_bookkeeping_plugins_preview() {
	try {
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

		$detector = new w91099ch_Accounting_Bookkeeping_Plugin_Detector();
		$rows     = $detector->get_plugins_preview( $plugin_slug, $limit );

		wp_send_json_success(
			array(
				'rows'        => $rows,
				'total_count' => count( $rows ),
			)
		);
	} catch ( Exception $e ) {
		error_log( 'W9-1099-Chaser Accounting AJAX Error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Error loading accounting data: ' . $e->getMessage() ) );
	}
}
