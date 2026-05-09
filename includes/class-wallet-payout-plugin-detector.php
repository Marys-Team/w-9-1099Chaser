<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_Wallet_Payout_Plugin_Detector {

	public function get_wallet_payout_plugins_data() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$active      = (array) get_option( 'active_plugins', array() );

		$predefined = array(
			array(
				'slug'         => 'woo-wallet',
				'name'         => 'TeraWallet (WooWallet)',
				'plugin_files' => array(
					'woo-wallet/woo-wallet.php',
				),
				'name_regex'   => '/\bterawallet\b|\bwoowallet\b|\bwoo\s*wallet\b/i',
			),
			array(
				'slug'         => 'mycred',
				'name'         => 'myCred',
				'plugin_files' => array(
					'mycred/mycred.php',
				),
				'name_regex'   => '/\bmycred\b/i',
			),
			array(
				'slug'         => 'bp-wallet',
				'name'         => 'BP Wallet',
				'plugin_files' => array(
					'bp-wallet/bp-wallet.php',
				),
				'name_regex'   => '/\bbp\s*wallet\b/i',
			),
			array(
				'slug'         => 'woocommerce-wallet',
				'name'         => 'WooCommerce Wallet',
				'plugin_files' => array(
					'woocommerce-wallet/woocommerce-wallet.php',
				),
				'name_regex'   => '/\bwoocommerce\s+wallet\b/i',
			),
			array(
				'slug'         => 'store-credit-for-woocommerce',
				'name'         => 'Store Credit System for WooCommerce',
				'plugin_files' => array(),
				'name_regex'   => '/\bstore\s+credit\b.*\bwoocommerce\b|\bstore\s+credit\s+system\b/i',
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
				'source'   => 'predefined',
			);
		}

		$plugins = $this->detect_generic_plugins( $plugins, $all_plugins, $active );

		uasort(
			$plugins,
			function ( $a, $b ) {
				$an = isset( $a['name'] ) ? (string) $a['name'] : '';
				$bn = isset( $b['name'] ) ? (string) $b['name'] : '';
				return strcasecmp( $an, $bn );
			}
		);

		return $plugins;
	}

	public function get_plugins_preview( $plugin_slug, $limit ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 50;
		}

		$plugin_slug = is_string( $plugin_slug ) ? $plugin_slug : '';
		$plugins     = $this->get_wallet_payout_plugins_data();

		$rows = array();

		if ( $plugin_slug !== '' && isset( $plugins[ $plugin_slug ] ) && is_array( $plugins[ $plugin_slug ] ) ) {
			$rows[] = $this->plugin_to_row( $plugins[ $plugin_slug ] );
		} else {
			foreach ( $plugins as $p ) {
				if ( is_array( $p ) ) {
					$rows[] = $this->plugin_to_row( $p );
				}
			}
		}

		if ( count( $rows ) > $limit ) {
			$rows = array_slice( $rows, 0, $limit );
		}

		return $rows;
	}

	private function plugin_to_row( $plugin ) {
		$name    = isset( $plugin['name'] ) ? (string) $plugin['name'] : '';
		$version = isset( $plugin['version'] ) ? (string) $plugin['version'] : '';
		$active  = isset( $plugin['active'] ) ? (bool) $plugin['active'] : false;
		$source  = isset( $plugin['source'] ) ? (string) $plugin['source'] : '';

		return array(
			'plugin'  => $name !== '' ? $name : 'N/A',
			'version' => $version !== '' ? $version : 'N/A',
			'status'  => $active ? 'Active' : 'Inactive',
			'source'  => $source !== '' ? $source : 'N/A',
		);
	}

	private function detect_generic_plugins( $existing, $all_plugins, $active ) {
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$keywords = array(
			'wallet',
			'payout',
			'payouts',
			'store credit',
			'credit',
			'balance',
			'mycred',
			'woowallet',
			'woo wallet',
			'terawallet',
			'points',
			'rewards',
		);

		$used_slugs = array();
		foreach ( $existing as $k => $v ) {
			$used_slugs[ (string) $k ] = true;
		}

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$name = isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : '';
			$desc = isset( $plugin_data['Description'] ) ? (string) $plugin_data['Description'] : '';

			$hay      = strtolower( $name . ' ' . wp_strip_all_tags( $desc ) );
			$is_match = false;
			foreach ( $keywords as $kw ) {
				if ( strpos( $hay, $kw ) !== false ) {
					$is_match = true;
					break;
				}
			}

			if ( ! $is_match ) {
				continue;
			}

			$slug = $this->canonicalize_plugin_slug( $this->slug_from_plugin_file( $plugin_file ) );
			if ( $slug === '' || isset( $used_slugs[ $slug ] ) ) {
				continue;
			}

			if ( $this->is_blocked_generic_plugin( $slug, $name, $desc ) ) {
				continue;
			}

			$existing[ $slug ] = array(
				'name'     => $name !== '' ? $name : $slug,
				'slug'     => $slug,
				'active'   => in_array( $plugin_file, $active, true ),
				'version'  => isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '',
				'detected' => true,
				'source'   => 'generic',
			);

			$used_slugs[ $slug ] = true;
		}

		return $existing;
	}

	private function is_blocked_generic_plugin( $slug, $name, $desc ) {
		$slug = is_string( $slug ) ? strtolower( $slug ) : '';
		$name = is_string( $name ) ? strtolower( $name ) : '';
		$desc = is_string( $desc ) ? strtolower( $desc ) : '';
		$hay  = $slug . ' ' . $name . ' ' . $desc;

		$blocked_slugs = array(
			'woocommerce',
			'jetpack',
			'elementor',
			'wordfence',
			'litespeed-cache',
			'wp-rocket',
			'w3-total-cache',
			'rank-math',
			'yoast',
			'akismet',
			'updraftplus',
			'plugin-compatibility-checker',
		);

		if ( $slug !== '' && in_array( $slug, $blocked_slugs, true ) ) {
			return true;
		}

		$blocked_terms = array(
			'cache',
			'caching',
			'seo',
			'security',
			'backup',
			'antispam',
			'newsletter',
			'smtp',
			'analytics',
			'compatibility',
			'checker',
		);

		foreach ( $blocked_terms as $t ) {
			if ( strpos( $hay, $t ) !== false ) {
				return true;
			}
		}

		return false;
	}

	private function slug_from_plugin_file( $plugin_file ) {
		$plugin_file = is_string( $plugin_file ) ? $plugin_file : '';
		if ( $plugin_file === '' ) {
			return '';
		}

		$parts = explode( '/', $plugin_file );
		if ( ! is_array( $parts ) || empty( $parts ) ) {
			return '';
		}

		return strtolower( (string) $parts[0] );
	}

	private function canonicalize_plugin_slug( $slug ) {
		$slug = is_string( $slug ) ? strtolower( $slug ) : '';
		if ( $slug === '' ) {
			return '';
		}

		$aliases = array(
			'woo-wallet' => 'woo-wallet',
			'mycred'     => 'mycred',
			'bp-wallet'  => 'bp-wallet',
		);

		return isset( $aliases[ $slug ] ) ? $aliases[ $slug ] : $slug;
	}

	public function get_wallet_entries_preview( $plugin_slug, $limit ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$plugin_slug = is_string( $plugin_slug ) ? $plugin_slug : '';
		$plugins = $this->get_wallet_payout_plugins_data();

		$rows = array();

		// If specific plugin requested and it exists
		if ( $plugin_slug !== '' && isset( $plugins[ $plugin_slug ] ) ) {
			$plugin_rows = $this->get_real_wallet_data( $plugin_slug, $limit );
			$rows = array_merge( $rows, $plugin_rows );
		} 
		// If "all" or empty, get data from all plugins
		else {
			$plugin_count = count( $plugins );
			$per_plugin   = $plugin_count > 0 ? max( 1, (int) floor( $limit / $plugin_count ) ) : $limit;
			foreach ( $plugins as $slug => $plugin ) {
				$plugin_rows = $this->get_real_wallet_data( $slug, $per_plugin );
				$rows = array_merge( $rows, $plugin_rows );
			}
		}

		if ( count( $rows ) > $limit ) {
			$rows = array_slice( $rows, 0, $limit );
		}

		return $rows;
	}

	private function get_real_wallet_data( $plugin_slug, $limit ) {
		global $wpdb;
		$real_data = array();
		$limit     = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}
		
		// Add debug logging
		error_log("W9-1099-Chaser: Getting wallet data for plugin: " . $plugin_slug . " with limit: " . $limit);
		
		// Suppress errors to prevent HTML output in AJAX
		$wpdb->hide_errors();
		$wpdb->suppress_errors( true );

		switch ( $plugin_slug ) {
			case 'woo-wallet':
				// WooWallet uses database tables, not usermeta
				$balance_table = $wpdb->prefix . 'woo_wallet';
				$transaction_table = $wpdb->prefix . 'woo_wallet_transactions';
				$users_table = $wpdb->users;
				
				// First try balance table
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $balance_table ) ) ) {
					error_log("W9-1099-Chaser: Found WooWallet balance table: " . $balance_table);
					
					$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$balance_table}", 0 );
					$cols = is_array( $cols ) ? $cols : array();
					error_log("W9-1099-Chaser: Balance table columns: " . print_r($cols, true));

					$user_id_col = in_array( 'user_id', $cols, true ) ? 'user_id' : ( in_array( 'userid', $cols, true ) ? 'userid' : '' );
					$amount_col  = in_array( 'balance', $cols, true ) ? 'balance' : ( in_array( 'amount', $cols, true ) ? 'amount' : '' );

					if ( $user_id_col !== '' && $amount_col !== '' ) {
						$balances = $wpdb->get_results( $wpdb->prepare("
							SELECT 
								u.display_name as user_name,
								u.user_email,
								w.{$amount_col} as amount
							FROM {$balance_table} w
							JOIN {$users_table} u ON w.{$user_id_col} = u.ID
							WHERE w.{$amount_col} != 0
							ORDER BY w.{$amount_col} DESC
							LIMIT %d
						", $limit ) );

						if ( $balances ) {
							error_log("W9-1099-Chaser: Found " . count($balances) . " balance records");
							foreach ( $balances as $balance ) {
								$real_data[] = array(
									'user_name' => $balance->user_name,
									'user_email' => $balance->user_email,
									'amount' => $balance->amount,
									'transaction_type' => 'Balance',
									'status' => 'active',
									'created_date' => current_time( 'mysql' ),
								);
							}
						} else {
							error_log("W9-1099-Chaser: No balance records found");
						}
					} else {
						error_log("W9-1099-Chaser: Required columns not found in balance table. user_id_col: " . $user_id_col . ", amount_col: " . $amount_col);
					}
				} else {
					error_log("W9-1099-Chaser: Balance table not found: " . $balance_table);
				}
				
				// If no balance data, try transactions table
				if ( empty( $real_data ) ) {
					if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $transaction_table ) ) ) {
						error_log("W9-1099-Chaser: Trying transaction table: " . $transaction_table);
						
						$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$transaction_table}", 0 );
						$cols = is_array( $cols ) ? $cols : array();
						error_log("W9-1099-Chaser: Transaction table columns: " . print_r($cols, true));

						$user_id_col = in_array( 'user_id', $cols, true ) ? 'user_id' : ( in_array( 'userid', $cols, true ) ? 'userid' : '' );
						$amount_col  = in_array( 'amount', $cols, true ) ? 'amount' : ( in_array( 'balance', $cols, true ) ? 'balance' : '' );
						$type_col    = in_array( 'type', $cols, true ) ? 'type' : ( in_array( 'transaction_type', $cols, true ) ? 'transaction_type' : '' );
						$date_col    = in_array( 'created_at', $cols, true ) ? 'created_at' : ( in_array( 'date', $cols, true ) ? 'date' : ( in_array( 'time', $cols, true ) ? 'time' : '' ) );

						if ( $user_id_col !== '' && $amount_col !== '' && $date_col !== '' ) {
							$select_status = ( in_array( 'status', $cols, true ) ) ? ", w.status" : ", 'completed' as status";
							$select_type = $type_col !== '' ? "w.{$type_col}" : "'Balance'";
							
							$transactions = $wpdb->get_results( $wpdb->prepare("
								SELECT 
									u.display_name as user_name,
									u.user_email,
									w.{$amount_col} as amount,
									{$select_type} as transaction_type,
									w.{$date_col} as created_date
									{$select_status}
								FROM {$transaction_table} w
								JOIN {$users_table} u ON w.{$user_id_col} = u.ID
								ORDER BY w.{$date_col} DESC
								LIMIT %d
							", $limit ) );

							if ( $transactions ) {
								error_log("W9-1099-Chaser: Found " . count($transactions) . " transaction records");
								foreach ( $transactions as $transaction ) {
									$real_data[] = array(
										'user_name' => $transaction->user_name,
										'user_email' => $transaction->user_email,
										'amount' => $transaction->amount,
										'transaction_type' => $transaction->transaction_type,
										'status' => isset( $transaction->status ) ? $transaction->status : 'completed',
										'created_date' => $transaction->created_date,
									);
								}
							} else {
								error_log("W9-1099-Chaser: No transaction records found");
							}
						} else {
							error_log("W9-1099-Chaser: Required columns not found in transaction table. user_id_col: " . $user_id_col . ", amount_col: " . $amount_col . ", date_col: " . $date_col);
						}
					} else {
						error_log("W9-1099-Chaser: Transaction table not found: " . $transaction_table);
					}
				}
				break;

			case 'mycred':
				// myCred uses usermeta
				error_log("W9-1099-Chaser: Getting myCred data from usermeta");
				$users = get_users( array( 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
				$count = 0;
				
				foreach ( $users as $user ) {
					if ( $count >= $limit ) break;
					
					$points = get_user_meta( $user->ID, 'mycred_default', true );
					if ( $points && $points != 0 ) {
						$real_data[] = array(
							'user_name' => $user->display_name,
							'user_email' => $user->user_email,
							'amount' => $points,
							'transaction_type' => 'Points Balance',
							'status' => 'active',
							'created_date' => current_time( 'mysql' ),
						);
						$count++;
					}
				}
				error_log("W9-1099-Chaser: Found " . count($real_data) . " myCred users with points");
				break;

			default:
				// Generic wallet plugin - try database tables first, then usermeta
				error_log("W9-1099-Chaser: Trying generic wallet plugin: " . $plugin_slug);
				
				// Try database tables
				$table_patterns = array(
					$wpdb->prefix . $plugin_slug . '_wallet',
					$wpdb->prefix . $plugin_slug . '_balances',
					$wpdb->prefix . $plugin_slug . '_transactions',
				);

				$found_data = false;
				foreach ( $table_patterns as $table ) {
					if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
						error_log("W9-1099-Chaser: Found table: " . $table);
						
						$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
						$cols = is_array( $cols ) ? $cols : array();

						$user_id_col = in_array( 'user_id', $cols, true ) ? 'user_id' : ( in_array( 'userid', $cols, true ) ? 'userid' : '' );
						$amount_col  = in_array( 'balance', $cols, true ) ? 'balance' : ( in_array( 'amount', $cols, true ) ? 'amount' : '' );

						if ( $user_id_col !== '' && $amount_col !== '' ) {
							$results = $wpdb->get_results( $wpdb->prepare("
								SELECT 
									u.display_name as user_name,
									u.user_email,
									w.{$amount_col} as amount
								FROM {$table} w
								JOIN {$users_table} u ON w.{$user_id_col} = u.ID
								WHERE w.{$amount_col} != 0
								ORDER BY w.{$amount_col} DESC
								LIMIT %d
							", $limit ) );

							if ( $results ) {
								foreach ( $results as $result ) {
									$real_data[] = array(
										'user_name' => $result->user_name,
										'user_email' => $result->user_email,
										'amount' => $result->amount,
										'transaction_type' => 'Balance',
										'status' => 'active',
										'created_date' => current_time( 'mysql' ),
									);
								}
								$found_data = true;
								break;
							}
						}
					}
				}

				break;
		}

		// Restore error reporting
		$wpdb->show_errors();
		$wpdb->suppress_errors( false );

		error_log("W9-1099-Chaser: Returning " . count($real_data) . " records for plugin: " . $plugin_slug);
		return $real_data;
	}
}
