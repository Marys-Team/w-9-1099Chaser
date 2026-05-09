<?php
/**
 * Backward compatibility aliases for Vendor Onboarding W9-1099 Chaser by Mypowerly plugin.
 * This file is loaded AFTER all class files so new class names exist.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Class aliases: map new prefixed classes to old names so legacy references work
if ( class_exists( 'w91099ch__Core' ) && ! class_exists( 'w91099ch_Core' ) ) {
	class_alias( 'w91099ch__Core', 'w91099ch_Core' );
}
if ( class_exists( 'w91099ch__API_Handler' ) && ! class_exists( 'w91099ch_API_Handler' ) ) {
	class_alias( 'w91099ch__API_Handler', 'w91099ch_API_Handler' );
}
if ( class_exists( 'w91099ch__Encryption_Handler' ) && ! class_exists( 'w91099ch_Encryption_Handler' ) ) {
	class_alias( 'w91099ch__Encryption_Handler', 'w91099ch_Encryption_Handler' );
}
if ( class_exists( 'w91099ch__Database' ) && ! class_exists( 'w91099ch_Database' ) ) {
	class_alias( 'w91099ch__Database', 'w91099ch_Database' );
}
if ( class_exists( 'w91099ch__Affiliate_Manager' ) && ! class_exists( 'w91099ch_Affiliate_Manager' ) ) {
	class_alias( 'w91099ch__Affiliate_Manager', 'w91099ch_Affiliate_Manager' );
}
if ( class_exists( 'w91099ch__Affiliate_Manager_Backup' ) && ! class_exists( 'w91099ch_Affiliate_Manager_Backup' ) ) {
	class_alias( 'w91099ch__Affiliate_Manager_Backup', 'w91099ch_Affiliate_Manager_Backup' );
}
if ( class_exists( 'w91099ch__Affiliate_Monitor' ) && ! class_exists( 'w91099ch_Affiliate_Monitor' ) ) {
	class_alias( 'w91099ch__Affiliate_Monitor', 'w91099ch_Affiliate_Monitor' );
}
if ( class_exists( 'w91099ch__W9_Form_Shortcode' ) && ! class_exists( 'w91099ch_W9_Form_Shortcode' ) ) {
	class_alias( 'w91099ch__W9_Form_Shortcode', 'w91099ch_W9_Form_Shortcode' );
}
if ( class_exists( 'w91099ch__Widget_Manager' ) && ! class_exists( 'w91099ch_Widget_Manager' ) ) {
	class_alias( 'w91099ch__Widget_Manager', 'w91099ch_Widget_Manager' );
}
if ( class_exists( 'w91099ch__Webhook_Dispatcher' ) && ! class_exists( 'w91099ch_Webhook_Dispatcher' ) ) {
	class_alias( 'w91099ch__Webhook_Dispatcher', 'w91099ch_Webhook_Dispatcher' );
}
if ( class_exists( 'w91099ch__Widget' ) && ! class_exists( 'w91099ch_Widget' ) ) {
	class_alias( 'w91099ch__Widget', 'w91099ch_Widget' );
}
if ( class_exists( 'w91099ch__Admin' ) && ! class_exists( 'w91099ch_Admin' ) ) {
	class_alias( 'w91099ch__Admin', 'w91099ch_Admin' );
}
if ( class_exists( 'w91099ch__Plugin' ) && ! class_exists( 'w91099ch_Plugin' ) ) {
	class_alias( 'w91099ch__Plugin', 'w91099ch_Plugin' );
}

// Provide standardized logger and legacy alias
function w91099ch__log( $message ) {
	$w91099ch_debug_enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG );
	if ( ! $w91099ch_debug_enabled ) {
		$w91099ch_debug_enabled = (bool) get_option( 'w91099ch_debug_logging', false );
	}

	if ( $w91099ch_debug_enabled ) {
		return;
	}
}

// Global accessor with new standardized name; legacy accessor remains available elsewhere
function w91099ch_() {
	if ( class_exists( 'w91099ch__Plugin' ) ) {
		return w91099ch__Plugin::get_instance();
	} elseif ( class_exists( 'w91099ch_Plugin' ) ) {
		return w91099ch_Plugin::get_instance();
	}
	return null;
}

