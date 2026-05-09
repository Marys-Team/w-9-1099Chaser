<?php
/**
 * Uninstall handler for MyPowerly Connector
 *
 * @package    w91099ch
 * @subpackage Uninstall
 * @since      1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only allow uninstall from WordPress admin
if ( ! current_user_can( 'delete_plugins' ) ) {
	return;
}

global $wpdb;

$w91099ch_option_keys = array(
	'w91099ch_admin_consent',
	'w91099ch_connected',
	'w91099ch_credentials',
	'w91099ch_site_url',
	'w91099ch_connected_at',
	'w91099ch_user_email',
	'w91099ch_last_checked',
	'w91099ch_credentials_valid',
	'w91099ch_client_id',
	'w91099ch_client_secret',
	'w91099ch_access_token',
	'w91099ch_refresh_token',
	'w91099ch_excluded_forms',
	'w91099ch_excluded_affiliate_ids',
	'w91099ch_active_plugins_snapshot',
	'w91099ch_plugins_last_sync',
	'w91099ch_affiliates_last_sync',
	'w91099ch_affiliates_count',
	'w91099ch_team_last_sync',
	'w91099ch_debug_logging',
	'w91099ch_hidden_plugins',
	'w91099ch_manual_plugins',
	'w91099ch_update_last_checked',
	'w91099ch_profile_last_sync',
	'w91099ch_plugin_last_sync',
	'w91099ch_db_version',
);

$w91099ch_cleanup_for_blog = static function () use ( $wpdb, $w91099ch_option_keys ) {

	// Reviewer note: Uninstall removes plugin options/transients (including stored connection credentials/tokens)
	// and drops plugin-created tables used for local caching/reporting.

	// Delete plugin options.
	foreach ( $w91099ch_option_keys as $w91099ch_key ) {
		delete_option( $w91099ch_key );
	}

	// Clear any scheduled events.
	if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
		wp_clear_scheduled_hook( 'w91099ch_daily_cleanup' );
		wp_clear_scheduled_hook( 'w91099ch_sync_affiliates_cron' );
	}

	// Clear plugin transients.
	delete_transient( 'w91099ch_connection_error' );
	delete_transient( 'w91099ch_connection_success' );
	delete_transient( 'w91099ch_activated' );

	// Clear per-user transients that use dynamic keys.
	$user_ids = get_users(
		array(
			'fields' => 'ID',
			'number' => -1,
		)
	);
	$user_ids = is_array( $user_ids ) ? array_map( 'absint', $user_ids ) : array();
	foreach ( $user_ids as $user_id ) {
		if ( $user_id <= 0 ) {
			continue;
		}
		delete_transient( 'w91099ch_pending_credentials_' . $user_id );
		delete_transient( 'w91099ch_private_key_' . $user_id );
	}

	// Remove custom tables created by the plugin.
	// We only drop known plugin-owned table names, built from the current blog prefix.
	// Table identifiers cannot be parameterized in $wpdb->prepare(), so we validate using a strict whitelist.
	$tables = array(
		$wpdb->prefix . 'w91099ch_affiliates',
		$wpdb->prefix . 'w91099ch_data',
		$wpdb->prefix . 'w91099ch_affiliate_activity',
	);

	foreach ( $tables as $table ) {
		// Table identifiers can't be passed via prepare(). We validate strictly and only drop plugin-owned tables.
		if ( ! is_string( $table ) || ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			continue;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Dropping plugin-owned table during uninstall; identifiers validated.
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $table . '`' );
	}
};

if ( is_multisite() ) {
	$w91099ch_sites = get_sites( array( 'number' => 0 ) );
	foreach ( $w91099ch_sites as $w91099ch_site ) {
		switch_to_blog( (int) $w91099ch_site->blog_id );
		$w91099ch_cleanup_for_blog();
		restore_current_blog();
	}

	// Network-level options (if any).
	foreach ( $w91099ch_option_keys as $w91099ch_key ) {
		delete_site_option( $w91099ch_key );
	}
} else {
	$w91099ch_cleanup_for_blog();
}

// Log the uninstallation
if ( function_exists( 'w91099ch_log' ) ) {
	w91099ch_log( 'Plugin uninstalled and all data cleaned up' );
}
