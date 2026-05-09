<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_Ecommerce_Plugin_Detector {

	public function get_ecommerce_plugins_data() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$active      = (array) get_option( 'active_plugins', array() );

		$predefined = array(
			array(
				'slug'         => 'woocommerce',
				'name'         => 'WooCommerce',
				'plugin_files' => array( 'woocommerce/woocommerce.php' ),
				'name_regex'   => '/\bwoocommerce\b/i',
			),
			array(
				'slug'         => 'dokan-lite',
				'name'         => 'Dokan',
				'plugin_files' => array( 'dokan-lite/dokan.php', 'dokan/dokan.php' ),
				'name_regex'   => '/\bdokan\b/i',
			),
			array(
				'slug'         => 'wc-multivendor-marketplace',
				'name'         => 'WCFM',
				'plugin_files' => array( 'wc-multivendor-marketplace/wc-multivendor-marketplace.php', 'wc-frontend-manager/wc-frontend-marketplace.php' ),
				'name_regex'   => '/\bwcfm\b|\bfrontend\s+manager\b/i',
			),
			array(
				'slug'         => 'stripe',
				'name'         => 'Stripe',
				'plugin_files' => array( 'woocommerce-gateway-stripe/woocommerce-stripe-gateway.php', 'stripe/stripe.php' ),
				'name_regex'   => '/\bstripe\b/i',
			),
			array(
				'slug'         => 'paypal',
				'name'         => 'PayPal',
				'plugin_files' => array( 'woocommerce-paypal-payments/woocommerce-paypal-payments.php', 'paypal-for-woocommerce/paypal-for-woocommerce.php' ),
				'name_regex'   => '/\bpaypal\b/i',
			),
		);

		$plugins = array();

		foreach ( $predefined as $def ) {
			$matched             = false;
			$matched_plugin_file = '';

			foreach ( $def['plugin_files'] as $plugin_file ) {
				if ( isset( $all_plugins[ $plugin_file ] ) ) {
					$matched             = true;
					$matched_plugin_file = $plugin_file;
					break;
				}
			}

			if ( ! $matched ) {
				foreach ( $all_plugins as $plugin_file => $plugin_data ) {
					$name = isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : '';
					if ( $name !== '' && preg_match( $def['name_regex'], $name ) ) {
						$matched             = true;
						$matched_plugin_file = (string) $plugin_file;
						break;
					}
				}
			}

			if ( ! $matched || $matched_plugin_file === '' ) {
				continue;
			}

			$plugin_data = isset( $all_plugins[ $matched_plugin_file ] ) ? $all_plugins[ $matched_plugin_file ] : array();

			$plugins[ $def['slug'] ] = array(
				'name'     => $def['name'],
				'slug'     => $def['slug'],
				'active'   => in_array( $matched_plugin_file, $active, true ),
				'version'  => isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '',
				'detected' => true,
			);
		}

		return $plugins;
	}
}
