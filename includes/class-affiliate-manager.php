<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_Affiliate_Manager {
	private $cache_group = 'w91099ch';
	private $cache_ttl_short = 300;

	private function table_exists( $table ) {
		global $wpdb;
		$table = is_string( $table ) ? $table : '';
		if ( '' === $table ) {
			return false;
		}
		$key    = 'w91099ch_table_exists_' . md5( (string) $table );
		$cached = wp_cache_get( $key, $this->cache_group );
		if ( false !== $cached ) {
			return (bool) $cached;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		try {
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		} catch (Exception $e) {
			$this->log( 'Error checking if table exists ' . $table . ': ' . $e->getMessage() );
			return false;
		}
		$exists = is_string( $found ) && $found === $table;
		wp_cache_set( $key, $exists, $this->cache_group, $this->cache_ttl_short );
		return $exists;
	}

	private function is_valid_table_name( $table ) {
		global $wpdb;
		$table = is_string( $table ) ? $table : '';
		if ( '' === $table ) {
			return false;
		}
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			return false;
		}
		return ( strpos( $table, (string) $wpdb->prefix ) === 0 );
	}

	private function get_table_columns( $table ) {
		global $wpdb;
		$table = is_string( $table ) ? $table : '';
		if ( '' === $table ) {
			return array();
		}
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			return array();
		}
		$table_sql = esc_sql( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Used only for external plugin table introspection; $table is regex-validated and escaped via esc_sql().
		try {
			$cols = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM `' . $table_sql . '` WHERE 1 = %d', 1 ), 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} catch (Exception $e) {
			$this->log( 'Error getting columns for table ' . $table . ': ' . $e->getMessage() );
			return array();
		}
		$cols = is_array( $cols ) ? $cols : array();
		$out  = array();
		foreach ( $cols as $c ) {
			if ( is_string( $c ) && $c !== '' ) {
				$out[ $c ] = true;
			}
		}
		return $out;
	}

	private $detected_plugins = array();
	private $database;
	private $runtime_affiliates = array();

	public function __construct() {
		$this->database = w91099ch_Database::get_instance();
	}

	private function log( $message ) {
		if ( function_exists( 'w91099ch_log' ) ) {
			w91099ch_log( $message );
		}
	}

	/**
	 * Main detection method - gets data directly from all affiliate plugins
	 */
	public function detect_affiliate_plugins( $include_hidden = false ) {
		$this->detected_plugins   = array();
		$this->runtime_affiliates = array();

		$this->log( 'Starting plugin detection' );

		// Detect plugins by checking their existence in WordPress
		$this->detect_all_active_plugins();

		$this->merge_all_active_plugins();

		// Collect affiliate data directly from each plugin
		$this->collect_affiliates_from_all_plugins();

		$this->log( 'Detection completed. Found: ' . count( $this->detected_plugins ) . ' plugins' );

		return $this->format_plugins_for_display( $include_hidden );
	}

	private function merge_all_active_plugins() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
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

		$all_plugins = get_plugins();
		foreach ( (array) $all_plugins as $plugin_file => $plugin_data ) {
			$plugin_file = (string) $plugin_file;
			$is_active   = isset( $active_lookup[ $plugin_file ] ) || isset( $network_active_lookup[ $plugin_file ] );
			if ( ! $is_active ) {
				continue;
			}

			$plugin_name = (string) ( $plugin_data['Name'] ?? $plugin_file );
			$folder      = dirname( $plugin_file );
			$slug        = ( $folder && $folder !== '.' ) ? $folder : sanitize_title( $plugin_name );
			if ( ! $slug ) {
				continue;
			}

			if ( isset( $this->detected_plugins[ $slug ] ) ) {
				$this->detected_plugins[ $slug ]['version']     = $this->detected_plugins[ $slug ]['version'] ?? ( $plugin_data['Version'] ?? '' );
				$this->detected_plugins[ $slug ]['description'] = $this->detected_plugins[ $slug ]['description'] ?? ( $plugin_data['Description'] ?? '' );
				$this->detected_plugins[ $slug ]['plugin_file'] = $this->detected_plugins[ $slug ]['plugin_file'] ?? $plugin_file;
				$this->detected_plugins[ $slug ]['active']      = true;
				$this->detected_plugins[ $slug ]['installed']   = true;
				continue;
			}

			$this->detected_plugins[ $slug ] = array(
				'name'                      => $plugin_name,
				'version'                   => $plugin_data['Version'] ?? '',
				'description'               => $plugin_data['Description'] ?? '',
				'type'                      => 'other',
				'detected'                  => false,
				'plugin_file'               => $plugin_file,
				'score'                     => 0,
				'affiliate_count'           => 0,
				'plugin_type'               => 'other',
				'detection_method'          => 'active_plugins',
				'plugin_path'               => $slug,
				'real_data'                 => false,
				'installed'                 => true,
				'active'                    => true,
				'skip_affiliate_collection' => true,
			);
		}
	}

	private function get_hidden_plugin_slugs() {
		$hidden = get_option( 'w91099ch_hidden_plugins', array() );
		if ( ! is_array( $hidden ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'strval', $hidden ) ) );
	}

	private function get_manual_plugins() {
		$manual = get_option( 'w91099ch_manual_plugins', array() );
		if ( ! is_array( $manual ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $manual as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$slug = isset( $item['slug'] ) ? (string) $item['slug'] : '';
			$name = isset( $item['name'] ) ? (string) $item['name'] : '';
			$slug = sanitize_title( $slug );
			if ( ! $slug && $name ) {
				$slug = sanitize_title( $name );
			}
			if ( ! $slug || ! $name ) {
				continue;
			}
			$normalized[] = array(
				'slug' => $slug,
				'name' => $name,
			);
		}

		return $normalized;
	}

	/**
	 * Detect ALL active All plugins in WordPress
	 */
	private function detect_all_active_plugins() {
		$this->detected_plugins = array();

		// ============================================
		// YITH WOOCOMMERCE AFFILIATES
		// ============================================
		if ( $this->is_yith_affiliates_active() ) {
			$this->detected_plugins['yith-woocommerce-affiliates'] = array(
				'name'     => 'YITH WooCommerce Affiliates',
				'type'     => 'affiliate_management',
				'detected' => true,
			);
			$this->log( 'YITH WooCommerce Affiliates detected' );
		}

		// ============================================
		// SLICEWP
		// ============================================
		if ( $this->is_slicewp_active() ) {
			$this->detected_plugins['slicewp'] = array(
				'name'     => 'SliceWP',
				'type'     => 'affiliate_management',
				'detected' => true,
			);
			$this->log( 'SliceWP detected' );
		}

		// ============================================
		// AFFILIATEWP
		// ============================================
		if ( $this->is_affiliatewp_active() ) {
			$this->detected_plugins['affiliate-wp'] = array(
				'name'     => 'AffiliateWP',
				'type'     => 'affiliate_management',
				'detected' => true,
			);
			$this->log( 'AffiliateWP detected' );
		}

		// ============================================
		// OTHER AFFILIATE PLUGINS
		// ============================================
		$detected = $this->get_active_affiliate_plugins();

		foreach ( $detected as $plugin ) {
			$plugin_file = $plugin['file'] ?? '';
			$plugin_name = $plugin['name'] ?? '';
			if ( ! $plugin_file || ! $plugin_name ) {
				continue;
			}

			// Prefer folder slug, fall back to a sanitized plugin name.
			$folder = dirname( $plugin_file );
			$slug   = ( $folder && $folder !== '.' ) ? $folder : sanitize_title( $plugin_name );

			if ( isset( $this->detected_plugins[ $slug ] ) ) {
				// If we already detected it (e.g. via a dedicated checker), just enrich.
				$this->detected_plugins[ $slug ]['version']     = $plugin['version'] ?? ( $this->detected_plugins[ $slug ]['version'] ?? '' );
				$this->detected_plugins[ $slug ]['description'] = $plugin['description'] ?? ( $this->detected_plugins[ $slug ]['description'] ?? '' );
				continue;
			}

			$this->detected_plugins[ $slug ] = array(
				'name'        => $plugin_name,
				'version'     => $plugin['version'] ?? '',
				'description' => $plugin['description'] ?? '',
				'type'        => 'affiliate_management',
				'detected'    => true,
				'plugin_file' => $plugin_file,
				'score'       => $plugin['score'] ?? 0,
			);
		}

		return $this->detected_plugins;
	}

	/**
	 * Check if YITH WooCommerce Affiliates is active
	 */
	private function is_yith_affiliates_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if (
			is_plugin_active( 'yith-woocommerce-affiliates/yith-wcaf.php' ) ||
			is_plugin_active( 'yith-woocommerce-affiliates-premium/yith-wcaf.php' ) ||
			is_plugin_active( 'yith-woocommerce-affiliates-premium/init.php' )
		) {
			return true;
		}

		// Fallbacks: some installations may have custom folder names.
		if ( class_exists( 'YITH_WCAF' ) || defined( 'YITH_WCAF_VERSION' ) ) {
			return true;
		}

		if ( function_exists( 'get_plugins' ) ) {
			$plugins = get_plugins();
			foreach ( (array) $plugins as $file => $data ) {
				if ( stripos( (string) $file, 'yith-wcaf.php' ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	private function is_slicewp_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'slicewp/slicewp.php' ) || class_exists( 'SliceWP' );
	}

	private function is_affiliatewp_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'affiliate-wp/affiliate-wp.php' ) || function_exists( 'affiliate_wp' ) || class_exists( 'Affiliate_WP' );
	}

	/**
	 * Collect affiliates from ALL detected plugins
	 */
	private function collect_affiliates_from_all_plugins() {
		// DB-scan fallback for plugins we don't have a direct connector for.
		$fallback_records = $this->fetch_all_affiliates_with_payments();

		$earnings_by_email = array();
		foreach ( $fallback_records as $record ) {
			$email = strtolower( trim( (string) ( $record['email'] ?? '' ) ) );
			if ( ! $email ) {
				continue;
			}

			$earnings = 0;
			if ( isset( $record['total_earnings'] ) && is_numeric( $record['total_earnings'] ) ) {
				$earnings = (float) $record['total_earnings'];
			} elseif ( isset( $record['net_amount'] ) && is_numeric( $record['net_amount'] ) ) {
				$earnings = (float) $record['net_amount'];
			} elseif ( isset( $record['payout_amount'] ) && is_numeric( $record['payout_amount'] ) ) {
				$earnings = (float) $record['payout_amount'];
			} elseif ( isset( $record['amount'] ) && is_numeric( $record['amount'] ) ) {
				$earnings = (float) $record['amount'];
			} elseif ( isset( $record['total_amount'] ) && is_numeric( $record['total_amount'] ) ) {
				$earnings = (float) $record['total_amount'];
			}

			if ( ! isset( $earnings_by_email[ $email ] ) || $earnings_by_email[ $email ] < $earnings ) {
				$earnings_by_email[ $email ] = $earnings;
			}
		}

		foreach ( $this->detected_plugins as $plugin_slug => $plugin_info ) {
			if ( ! empty( $plugin_info['skip_affiliate_collection'] ) ) {
				$this->runtime_affiliates[ $plugin_slug ] = array();
				if ( ! isset( $this->detected_plugins[ $plugin_slug ]['affiliate_count'] ) ) {
					$this->detected_plugins[ $plugin_slug ]['affiliate_count'] = 0;
				}
				if ( ! isset( $this->detected_plugins[ $plugin_slug ]['real_data'] ) ) {
					$this->detected_plugins[ $plugin_slug ]['real_data'] = false;
				}
				continue;
			}
			switch ( $plugin_slug ) {
				case 'yith-woocommerce-affiliates':
					$affiliates = $this->get_affiliates_from_yith();
					break;
				case 'slicewp':
					$affiliates = $this->get_affiliates_from_slicewp();
					break;
				case 'affiliate-wp':
				case 'affiliatewp':
					$affiliates = $this->get_affiliates_from_affiliatewp();
					break;
				case 'affiliate-manager':
					$affiliates = $this->get_affiliates_from_affiliate_manager();
					break;
				case 'affiliates-manager':
					$affiliates = $this->get_affiliates_from_affiliate_manager();
					break;
				case 'wc-vendors':
					$affiliates = $this->get_affiliates_from_wc_vendors();
					break;
				case 'dokan-lite':
					$affiliates = $this->get_affiliates_from_dokan();
					break;
				default:
					// Fallback: try to map DB-scanned records to this plugin by name/slug match.
					$affiliates = array();

					$plugin_name   = strtolower( $plugin_info['name'] ?? '' );
					$plugin_slug_l = strtolower( $plugin_slug );

					foreach ( $fallback_records as $record ) {
						$source = strtolower( $record['plugin_source'] ?? '' );
						if ( ! $source ) {
							continue;
						}

						// Match by plugin name OR slug.
						if (
							( $plugin_name && strpos( $source, $plugin_name ) !== false ) ||
							( $plugin_slug_l && strpos( $source, $plugin_slug_l ) !== false )
						) {
							$full_name = trim( (string) ( $record['name'] ?? '' ) );
							$parts     = preg_split( '/\s+/', $full_name, 2 );
							$first     = $parts[0] ?? '';
							$last      = $parts[1] ?? '';

							$affiliate_id = $record['id'] ?? '';
							if ( $affiliate_id === '' || $affiliate_id === null ) {
								$affiliate_id = md5( wp_json_encode( $record ) );
							}

							$email = $record['email'] ?? '';
							if ( ! $email ) {
								$email = $this->generate_dummy_email( $affiliate_id );
							}

							$earnings = 0;
							if ( isset( $record['total_earnings'] ) && is_numeric( $record['total_earnings'] ) ) {
								$earnings = (float) $record['total_earnings'];
							} elseif ( isset( $record['net_amount'] ) && is_numeric( $record['net_amount'] ) ) {
								$earnings = (float) $record['net_amount'];
							} elseif ( isset( $record['payout_amount'] ) && is_numeric( $record['payout_amount'] ) ) {
								$earnings = (float) $record['payout_amount'];
							} elseif ( isset( $record['amount'] ) && is_numeric( $record['amount'] ) ) {
								$earnings = (float) $record['amount'];
							} elseif ( isset( $record['total_amount'] ) && is_numeric( $record['total_amount'] ) ) {
								$earnings = (float) $record['total_amount'];
							}

							$affiliates[] = array(
								'affiliate_id' => $plugin_slug . '_' . $affiliate_id,
								'first_name'   => $first,
								'last_name'    => $last,
								'email'        => $email,
								'company_name' => $record['business_name'] ?? '',
								'earnings'     => $earnings,
								'status'       => $record['status'] ?? 'active',
								'plugin_slug'  => $plugin_slug,
								'plugin_name'  => $plugin_info['name'] ?? $plugin_slug,
							);
						}
					}
					break;
			}

			foreach ( $affiliates as &$affiliate ) {
				if ( isset( $affiliate['earnings'] ) && is_numeric( $affiliate['earnings'] ) && (float) $affiliate['earnings'] > 0 ) {
					continue;
				}

				$email = strtolower( trim( (string) ( $affiliate['email'] ?? '' ) ) );
				if ( $email && isset( $earnings_by_email[ $email ] ) && (float) $earnings_by_email[ $email ] > 0 ) {
					$affiliate['earnings'] = (float) $earnings_by_email[ $email ];
				}
			}
			unset( $affiliate );

			// Update plugin info with affiliate count
			$this->detected_plugins[ $plugin_slug ]['affiliate_count']  = count( $affiliates );
			$this->detected_plugins[ $plugin_slug ]['plugin_type']      = 'affiliate_management';
			$this->detected_plugins[ $plugin_slug ]['detection_method'] = 'direct_database';
			$this->detected_plugins[ $plugin_slug ]['plugin_path']      = $plugin_slug;
			$this->detected_plugins[ $plugin_slug ]['real_data']        = ! empty( $affiliates );

			// Keep affiliate/vendor records in-memory only (no persistence).
			$this->runtime_affiliates[ $plugin_slug ] = $affiliates;
		}
	}

	/**
	 * Get affiliates directly from YITH WooCommerce Affiliates
	 */
	private function get_affiliates_from_yith() {
		global $wpdb;
		$affiliates = array();

		// YITH table naming varies by version (singular/plural).
		$candidate_tables = array(
			$wpdb->prefix . 'yith_wcaf_affiliate',
			$wpdb->prefix . 'yith_wcaf_affiliates',
		);

		$yith_table = '';
		foreach ( $candidate_tables as $candidate ) {
			if ( $this->table_exists( $candidate ) ) {
				$yith_table = $candidate;
				break;
			}
		}

		if ( ! $yith_table ) {
			return $affiliates;
		}

		$yith_table_sql = esc_sql( $yith_table );
		$cols           = $this->get_table_columns( $yith_table );
		$status_col     = isset( $cols['status'] ) ? 'status' : '';
		$user_col       = isset( $cols['user_id'] ) ? 'user_id' : '';

		$where = '';
		if ( $status_col ) {
			$where = "WHERE a.`status` = 'active'";
		}

		$join = '';
		if ( $user_col ) {
			$users_table = esc_sql( $wpdb->users );
			$join        = "LEFT JOIN `{$users_table}` u ON a.`user_id` = u.ID";
		}

		$cache_key = 'w91099ch_yith_affiliates_' . md5( $yith_table_sql . '|' . $status_col . '|' . $user_col );
		$cached    = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$query = 'SELECT a.*';
		if ( $join !== '' ) {
			$query .= ', u.user_email, u.display_name';
		}
		$query .= ' FROM `' . $yith_table_sql . '` a';
		if ( $join !== '' ) {
			$query .= ' ' . $join;
		}
		if ( $where !== '' ) {
			$query .= ' ' . $where;
		}
		$query .= ' AND 1 = %d';
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results( $wpdb->prepare( $query, 1 ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		foreach ( $results as $result ) {
			$display_name = isset( $result->display_name ) ? (string) $result->display_name : '';
			$name_parts   = explode( ' ', $display_name, 2 );
			$first_name   = $name_parts[0] ?? '';
			$last_name    = $name_parts[1] ?? '';

			$status_value = '';
			if ( $status_col && isset( $result->{$status_col} ) ) {
				$status_value = (string) $result->{$status_col};
			} elseif ( isset( $result->status ) ) {
				$status_value = (string) $result->status;
			}

			$affiliates[] = array(
				'affiliate_id' => 'yith_' . $result->ID,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'email'        => $result->user_email ?: $this->generate_dummy_email( $result->ID ),
				'company_name' => '',
				'status'       => $status_value,
				'plugin_slug'  => 'yith-woocommerce-affiliates',
				'plugin_name'  => 'YITH WooCommerce Affiliates',
			);
		}

		wp_cache_set( $cache_key, $affiliates, $this->cache_group, HOUR_IN_SECONDS );
		return $affiliates;
	}

	/**
	 * Get affiliates directly from AffiliateWP
	 */
	private function get_affiliates_from_affiliatewp() {
		global $wpdb;
		$affiliates = array();

		$aff_table     = $wpdb->prefix . 'affiliate_wp_affiliates';
		$ref_table     = $wpdb->prefix . 'affiliate_wp_referrals';
		$aff_table_sql = esc_sql( $aff_table );
		$ref_table_sql = esc_sql( $ref_table );

		if ( ! $this->table_exists( $aff_table ) ) {
			return $affiliates;
		}

		$cache_key = 'w91099ch_affiliatewp_affiliates';
		$cached    = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$users_table = esc_sql( $wpdb->users );
		$cols        = $this->get_table_columns( $aff_table );
		$status_col  = isset( $cols['status'] ) ? 'status' : '';

		$where = '';
		if ( $status_col ) {
			$where = "WHERE a.`status` = 'active'";
		}

		$query = 'SELECT a.`affiliate_id`, a.`user_id`';
		if ( $status_col ) {
			$query .= ', a.`status`';
		}
		$query .= ', u.user_email, u.display_name'
			. ' FROM `' . $aff_table_sql . '` a'
			. ' LEFT JOIN `' . $users_table . '` u ON a.user_id = u.ID';
		if ( $where !== '' ) {
			$query .= ' ' . $where;
		}
		$query .= ' AND 1 = %d';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results( $wpdb->prepare( $query, 1 ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$earnings_map = array();
		if ( $this->table_exists( $ref_table ) ) {
			$ref_cols        = $this->get_table_columns( $ref_table );
			$ref_status_col  = isset( $ref_cols['status'] ) ? 'status' : '';
			$ref_amount_col  = isset( $ref_cols['amount'] ) ? 'amount' : '';
			$ref_aff_col     = isset( $ref_cols['affiliate_id'] ) ? 'affiliate_id' : '';

			if ( $ref_amount_col && $ref_aff_col ) {
				$ref_where = '';
				if ( $ref_status_col ) {
					$ref_where = "WHERE r.`status` NOT IN ('rejected')";
				}

				$ref_query = 'SELECT r.`affiliate_id` AS affiliate_id, SUM(r.`amount`) AS earnings'
					. ' FROM `' . $ref_table_sql . '` r';
				if ( $ref_where !== '' ) {
					$ref_query .= ' ' . $ref_where;
				}
				$ref_query .= ' AND 1 = %d GROUP BY r.`affiliate_id`';
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$rows = $wpdb->get_results( $wpdb->prepare( $ref_query, 1 ), ARRAY_A );
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
				if ( is_array( $rows ) ) {
					foreach ( $rows as $r ) {
						$aid = isset( $r['affiliate_id'] ) ? (string) $r['affiliate_id'] : '';
						$val = isset( $r['earnings'] ) && is_numeric( $r['earnings'] ) ? (float) $r['earnings'] : 0.0;
						if ( $aid !== '' ) {
							$earnings_map[ $aid ] = $val;
						}
					}
				}
			}
		}

		foreach ( (array) $results as $result ) {
			$display_name = isset( $result->display_name ) ? (string) $result->display_name : '';
			$name_parts   = explode( ' ', $display_name, 2 );
			$first_name   = $name_parts[0] ?? '';
			$last_name    = $name_parts[1] ?? '';

			$status_value = 'active';
			if ( $status_col && isset( $result->{$status_col} ) && $result->{$status_col} !== '' ) {
				$status_value = (string) $result->{$status_col};
			}

			$affiliate_id = isset( $result->affiliate_id ) ? (string) $result->affiliate_id : '';
			$earnings     = ( $affiliate_id !== '' && isset( $earnings_map[ $affiliate_id ] ) ) ? (float) $earnings_map[ $affiliate_id ] : 0.0;

			$affiliates[] = array(
				'affiliate_id' => 'affiliatewp_' . $affiliate_id,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'email'        => ( isset( $result->user_email ) && $result->user_email ) ? (string) $result->user_email : $this->generate_dummy_email( $affiliate_id ),
				'company_name' => '',
				'earnings'     => $earnings,
				'status'       => $status_value,
				'plugin_slug'  => 'affiliate-wp',
				'plugin_name'  => 'AffiliateWP',
			);
		}

		wp_cache_set( $cache_key, $affiliates, $this->cache_group, HOUR_IN_SECONDS );
		return $affiliates;
	}

	/**
	 * Get affiliates directly from SliceWP
	 */
	private function get_affiliates_from_slicewp() {
		global $wpdb;
		$affiliates = array();

		$slicewp_table     = $wpdb->prefix . 'slicewp_affiliates';
		$slicewp_table_sql = esc_sql( $slicewp_table );

		if ( ! $this->table_exists( $slicewp_table ) ) {
			return $affiliates;
		}

		$cache_key = 'w91099ch_slicewp_affiliates';
		$cached    = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$users_table = esc_sql( $wpdb->users );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT a.id, a.user_id, a.status, u.user_email, u.display_name'
				. ' FROM `' . $slicewp_table_sql . '` a'
				. ' LEFT JOIN `' . $users_table . '` u ON a.user_id = u.ID'
				. ' WHERE a.status = %s',
				'active'
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		foreach ( $results as $result ) {
			$name_parts = explode( ' ', $result->display_name, 2 );
			$first_name = $name_parts[0] ?? '';
			$last_name  = $name_parts[1] ?? '';

			$affiliates[] = array(
				'affiliate_id' => 'slicewp_' . $result->id,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'email'        => $result->user_email ?: $this->generate_dummy_email( $result->id ),
				'company_name' => '',
				'status'       => $result->status,
				'plugin_slug'  => 'slicewp',
				'plugin_name'  => 'SliceWP',
			);
		}

		wp_cache_set( $cache_key, $affiliates, $this->cache_group, HOUR_IN_SECONDS );
		return $affiliates;
	}

	/**
	 * Get affiliates directly from Affiliate Manager (WP Affiliate Manager)
	 */
	private function get_affiliates_from_affiliate_manager() {
		global $wpdb;
		$affiliates = array();

		$am_table     = $wpdb->prefix . 'wpam_affiliates';
		$am_table_sql = esc_sql( $am_table );

		if ( ! $this->table_exists( $am_table ) ) {
			return $affiliates;
		}

		$cache_key = 'w91099ch_wpam_affiliates';
		$cached    = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$sql =
			'\tSELECT affiliateId, firstName, lastName, email, companyName, status'
			. ' FROM `' . $am_table_sql . '`'
			. " WHERE status IN ('active', 'approved', 'confirmed') AND 1 = %d";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT affiliateId, firstName, lastName, email, companyName, status' . ' FROM `' . $am_table_sql . '`' . " WHERE status IN ('active', 'approved', 'confirmed') AND 1 = %d", 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $results as $result ) {
			$affiliates[] = array(
				'affiliate_id' => 'wpam_' . $result->affiliateId,
				'first_name'   => $result->firstName ?: '',
				'last_name'    => $result->lastName ?: '',
				'email'        => $result->email ?: $this->generate_dummy_email( $result->affiliateId ),
				'company_name' => $result->companyName ?: '',
				'status'       => $result->status,
				'plugin_slug'  => 'affiliate-manager',
				'plugin_name'  => 'Affiliate Manager',
			);
		}

		wp_cache_set( $cache_key, $affiliates, $this->cache_group, HOUR_IN_SECONDS );
		return $affiliates;
	}

	/**
	 * Get affiliates directly from WC Vendors
	 */
	private function get_affiliates_from_wc_vendors() {
		$affiliates = array();

		if ( ! function_exists( 'get_users' ) ) {
			return $affiliates;
		}

		$vendors = get_users(
			array(
				'role'   => 'vendor',
				'number' => -1,
			)
		);

		foreach ( $vendors as $vendor ) {
			$store_name = get_user_meta( $vendor->ID, '_wcv_store_name', true );

			$affiliates[] = array(
				'affiliate_id' => 'wcv_' . $vendor->ID,
				'first_name'   => $vendor->first_name,
				'last_name'    => $vendor->last_name,
				'email'        => $vendor->user_email,
				'company_name' => $store_name ?: '',
				'status'       => 'active',
				'plugin_slug'  => 'wc-vendors',
				'plugin_name'  => 'WC Vendors',
			);
		}

		return $affiliates;
	}

	/**
	 * Get affiliates directly from Dokan
	 */
	private function get_affiliates_from_dokan() {
		$affiliates = array();

		if ( ! function_exists( 'dokan_get_sellers' ) ) {
			return $affiliates;
		}

		$vendors = dokan_get_sellers( array( 'number' => -1 ) );

		foreach ( $vendors as $vendor ) {
			$user = get_userdata( $vendor->ID );
			if ( ! $user ) {
				continue;
			}

			$store_name = get_user_meta( $vendor->ID, 'dokan_store_name', true );

			$affiliates[] = array(
				'affiliate_id' => 'dokan_' . $vendor->ID,
				'first_name'   => $user->first_name,
				'last_name'    => $user->last_name,
				'email'        => $user->user_email,
				'company_name' => $store_name ?: '',
				'status'       => 'active',
				'plugin_slug'  => 'dokan-lite',
				'plugin_name'  => 'Dokan',
			);
		}

		return $affiliates;
	}

	/**
	 * Save collected affiliates to our database
	 */
	private function save_collected_affiliates( $plugin_slug, $plugin_name, $affiliates ) {
		$saved_count = 0;

		foreach ( $affiliates as $affiliate ) {
			$earnings = 0.00;
			if ( isset( $affiliate['earnings'] ) && is_numeric( $affiliate['earnings'] ) ) {
				$earnings = (float) $affiliate['earnings'];
			} elseif ( isset( $affiliate['total_earnings'] ) && is_numeric( $affiliate['total_earnings'] ) ) {
				$earnings = (float) $affiliate['total_earnings'];
			} elseif ( isset( $affiliate['net_amount'] ) && is_numeric( $affiliate['net_amount'] ) ) {
				$earnings = (float) $affiliate['net_amount'];
			} elseif ( isset( $affiliate['payout_amount'] ) && is_numeric( $affiliate['payout_amount'] ) ) {
				$earnings = (float) $affiliate['payout_amount'];
			} elseif ( isset( $affiliate['amount'] ) && is_numeric( $affiliate['amount'] ) ) {
				$earnings = (float) $affiliate['amount'];
			}

			$affiliate_data = array(
				'affiliate_id'    => $affiliate['affiliate_id'],
				'plugin_slug'     => $plugin_slug,
				'plugin_name'     => $plugin_name,
				'first_name'      => $affiliate['first_name'],
				'last_name'       => $affiliate['last_name'],
				'email'           => $affiliate['email'],
				'company_name'    => $affiliate['company_name'],
				'client_type'     => ! empty( $affiliate['company_name'] ) ? 'corporation' : 'individual',
				'status'          => $affiliate['status'],
				'earnings'        => $earnings,
				'date_registered' => current_time( 'mysql' ),
			);

			if ( $this->database->save_affiliate( $affiliate_data ) ) {
				++$saved_count;
			}
		}

		return $saved_count;
	}

	/**
	 * Generate dummy email for affiliates without email
	 */
	private function generate_dummy_email( $affiliate_id ) {
		return 'affiliate_' . $affiliate_id . '@' . wp_parse_url( get_site_url(), PHP_URL_HOST );
	}

	/**
	 * Get TOTAL UNIQUE affiliates count from database.
	 *
	 * Hidden plugins are excluded so that global counts match what is shown in the UI.
	 */
	public function get_total_affiliates_count() {
		$hidden = $this->get_hidden_plugin_slugs();

		if ( empty( $this->detected_plugins ) ) {
			$this->detect_affiliate_plugins( true );
		}

		$total = 0;
		foreach ( (array) $this->runtime_affiliates as $slug => $items ) {
			if ( in_array( (string) $slug, $hidden, true ) ) {
				continue;
			}
			$total += is_array( $items ) ? count( $items ) : 0;
		}
		return $total;
	}

	public function get_payout_summary( $plugin_slug = '' ) {
		if ( empty( $this->detected_plugins ) ) {
			$this->detect_affiliate_plugins( true );
		}

		$hidden = $plugin_slug ? array() : $this->get_hidden_plugin_slugs();

		$total_payouts = 0.0;
		$with_payouts  = 0;

		foreach ( (array) $this->runtime_affiliates as $slug => $items ) {
			if ( $plugin_slug && (string) $slug !== (string) $plugin_slug ) {
				continue;
			}
			if ( ! $plugin_slug && in_array( (string) $slug, $hidden, true ) ) {
				continue;
			}

			foreach ( (array) $items as $a ) {
				$earnings = 0.0;
				if ( isset( $a['earnings'] ) && is_numeric( $a['earnings'] ) ) {
					$earnings = (float) $a['earnings'];
				}
				$total_payouts += $earnings;
				if ( $earnings > 0 ) {
					++$with_payouts;
				}
			}
		}

		$avg_payout = ( $with_payouts > 0 ) ? ( $total_payouts / $with_payouts ) : 0.0;

		return array(
			'total_payouts'           => $total_payouts,
			'affiliates_with_payouts' => $with_payouts,
			'avg_payout'              => $avg_payout,
		);
	}

	/**
	 * Get UNIQUE affiliates for display
	 */
	public function get_affiliates_for_display( $plugin_slug = '', $limit = 50, $offset = 0 ) {
		if ( empty( $this->detected_plugins ) ) {
			$this->detect_affiliate_plugins( true );
		}

		$hidden = $plugin_slug ? array() : $this->get_hidden_plugin_slugs();

		$all = array();
		foreach ( (array) $this->runtime_affiliates as $slug => $items ) {
			if ( $plugin_slug && (string) $slug !== (string) $plugin_slug ) {
				continue;
			}
			if ( ! $plugin_slug && in_array( (string) $slug, $hidden, true ) ) {
				continue;
			}
			foreach ( (array) $items as $a ) {
				$a['_plugin_slug'] = (string) $slug;
				$all[]             = $a;
			}
		}

		$total_count = count( $all );
		$slice       = array_slice( $all, max( 0, (int) $offset ), max( 0, (int) $limit ) );

		$formatted_affiliates = array();
		foreach ( $slice as $affiliate ) {
			$first = (string) ( $affiliate['first_name'] ?? '' );
			$last  = (string) ( $affiliate['last_name'] ?? '' );
			$name  = trim( $first . ' ' . $last );
			if ( $name === '' ) {
				$name = (string) ( $affiliate['name'] ?? '' );
			}
			$earnings = 0.0;
			if ( isset( $affiliate['earnings'] ) && is_numeric( $affiliate['earnings'] ) ) {
				$earnings = (float) $affiliate['earnings'];
			}

			$formatted_affiliates[] = array(
				'id'              => (string) ( $affiliate['affiliate_id'] ?? '' ),
				'name'            => $name,
				'email'           => (string) ( $affiliate['email'] ?? '' ),
				'company'         => (string) ( $affiliate['company_name'] ?? '' ),
				'status'          => (string) ( $affiliate['status'] ?? 'active' ),
				'plugin'          => (string) ( $affiliate['plugin_name'] ?? $affiliate['_plugin_slug'] ),
				'plugin_slug'     => (string) ( $affiliate['plugin_slug'] ?? $affiliate['_plugin_slug'] ),
				'amount'          => $earnings,
				'date_registered' => current_time( 'mysql' ),
			);
		}

		return array(
			'affiliates'  => $formatted_affiliates,
			'total_count' => $total_count,
		);
	}
	public function get_affiliates( $affiliate_system = 'yith' ) {
		global $wpdb;
		$affiliates = array();

		$cache_key = 'w91099ch_affiliates_' . $affiliate_system;
		$cached    = wp_cache_get( $cache_key, 'w91099ch' );
		if ( false !== $cached ) {
			return $cached;
		}

		switch ( $affiliate_system ) {
			case 'yith':
				$table_name = $wpdb->prefix . 'yith_wcaf_affiliate';
				if ( $this->table_exists( $table_name ) ) {
					$table_name_sql = esc_sql( $table_name );
					$users_table    = esc_sql( $wpdb->users );
					$sql            =
						'SELECT a.*, u.user_email, u.display_name'
						. ' FROM `' . $table_name_sql . '` a'
						. ' LEFT JOIN `' . $users_table . '` u ON a.user_id = u.ID'
						. ' WHERE a.status = %s';
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Cached by caller.
					$affiliates = $wpdb->get_results( $wpdb->prepare( 'SELECT a.*, u.user_email, u.display_name' . ' FROM `' . $table_name_sql . '` a' . ' LEFT JOIN `' . $users_table . '` u ON a.user_id = u.ID' . ' WHERE a.status = %s', 'active' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				}
				break;

			// Add other cases for different affiliate systems
		}

		if ( $affiliates ) {
			wp_cache_set( $cache_key, $affiliates, 'w91099ch', HOUR_IN_SECONDS );
		}

		return $affiliates;
	}
	/**
	 * Get ALL UNIQUE affiliates for sync
	 */
	public function get_all_affiliates_for_sync( $plugin_slug = '' ) {
		if ( empty( $this->detected_plugins ) ) {
			$this->detect_affiliate_plugins( true );
		}

		$hidden               = $plugin_slug ? array() : $this->get_hidden_plugin_slugs();
		$formatted_affiliates = array();

		foreach ( (array) $this->runtime_affiliates as $slug => $items ) {
			if ( $plugin_slug && (string) $slug !== (string) $plugin_slug ) {
				continue;
			}
			if ( ! $plugin_slug && in_array( (string) $slug, $hidden, true ) ) {
				continue;
			}

			foreach ( (array) $items as $affiliate ) {
				$earnings = 0.0;
				if ( isset( $affiliate['earnings'] ) && is_numeric( $affiliate['earnings'] ) ) {
					$earnings = (float) $affiliate['earnings'];
				}

				$formatted_affiliates[] = array(
					'id'              => (string) ( $affiliate['affiliate_id'] ?? '' ),
					'name'            => trim( ( (string) ( $affiliate['first_name'] ?? '' ) ) . ' ' . ( (string) ( $affiliate['last_name'] ?? '' ) ) ),
					'first_name'      => (string) ( $affiliate['first_name'] ?? '' ),
					'last_name'       => (string) ( $affiliate['last_name'] ?? '' ),
					'email'           => (string) ( $affiliate['email'] ?? '' ),
					'company'         => (string) ( $affiliate['company_name'] ?? '' ),
					'company_name'    => (string) ( $affiliate['company_name'] ?? '' ),
					'client_type'     => ! empty( $affiliate['company_name'] ) ? 'corporation' : 'individual',
					'status'          => (string) ( $affiliate['status'] ?? 'active' ),
					'plugin'          => (string) ( $affiliate['plugin_name'] ?? $slug ),
					'plugin_slug'     => (string) ( $affiliate['plugin_slug'] ?? $slug ),
					'amount'          => $earnings,
					'date_registered' => current_time( 'mysql' ),
					'meta_data'       => array(),
				);
			}
		}

		return $formatted_affiliates;
	}

	/**
	 * Format plugins for display
	 */
	private function format_plugins_for_display( $include_hidden = false ) {
		$formatted_plugins = array();
		$hidden            = $include_hidden ? array() : $this->get_hidden_plugin_slugs();

		// First, add detected plugins.
		foreach ( $this->detected_plugins as $slug => $plugin ) {
			if ( ! $include_hidden && in_array( (string) $slug, $hidden, true ) ) {
				continue;
			}
			$formatted_plugins[ $slug ] = array(
				'slug'             => $slug,
				'name'             => $plugin['name'],
				'version'          => $plugin['version'] ?? '',
				'vendor_count'     => $plugin['affiliate_count'] ?? 0,
				'affiliate_count'  => $plugin['affiliate_count'] ?? 0,
				'plugin_type'      => $plugin['plugin_type'] ?? 'affiliate_management',
				'detection_method' => $plugin['detection_method'] ?? 'direct_database',
				'installed'        => true,
				'active'           => true,
				'plugin_path'      => $plugin['plugin_path'] ?? $slug,
				'detected'         => $plugin['detected'] ?? false,
				'real_data'        => $plugin['real_data'] ?? false,
			);
		}

		// Then, merge manual plugins (so user can add even if detection fails).
		foreach ( $this->get_manual_plugins() as $manual ) {
			$slug = $manual['slug'];
			$name = $manual['name'];

			if ( ! $include_hidden && in_array( (string) $slug, $hidden, true ) ) {
				continue;
			}

			if ( isset( $formatted_plugins[ $slug ] ) ) {
				// Already detected; just mark source.
				$formatted_plugins[ $slug ]['manual'] = true;
				continue;
			}

			$formatted_plugins[ $slug ] = array(
				'slug'             => $slug,
				'name'             => $name,
				'version'          => '',
				'vendor_count'     => 0,
				'affiliate_count'  => 0,
				'plugin_type'      => 'affiliate_management',
				'detection_method' => 'manual',
				'installed'        => false,
				'active'           => false,
				'plugin_path'      => $slug,
				'detected'         => false,
				'real_data'        => false,
				'manual'           => true,
			);
		}

		return $formatted_plugins;
	}

	/**
	 * Format affiliates for API payload
	 */
	public function format_affiliates_for_api( $affiliates ) {
		$formatted_affiliates = array();

		foreach ( $affiliates as $affiliate ) {
			$formatted_affiliate = array(
				'data'    => array(
					'name'              => $affiliate['name'] ?: 'Unknown Affiliate',
					'email'             => $affiliate['email'],
					'status'            => $affiliate['status'] ?? 'active',
					'affiliate_id'      => $affiliate['id'],
					'plugin_source'     => $affiliate['plugin'] ?? 'unknown',
					'registration_date' => $affiliate['date_registered'] ?? current_time( 'mysql' ),
					'client_type'       => $affiliate['client_type'] ?? 'individual',
					'company'           => $affiliate['company'] ?? '',
				),
				'source'  => 'wordpress',
				'plug_id' => 11,
			);

			$formatted_affiliates[] = $formatted_affiliate;
		}

		return $formatted_affiliates;
	}

	/**
	 * Get ALL active affiliate plugins with enhanced detection (Better keyword matching)
	 */
	public function get_active_affiliate_plugins() {
		$active_plugins    = get_option( 'active_plugins', array() );
		$all_plugins       = get_plugins();
		$affiliate_plugins = array();

		// High priority keywords
		$primary_keywords = array(
			'affiliate',
			'referral',
			'refer-a-friend',
			'mlm',
			'commission',
			'partner program',
			'referral program',
			'affiliate program',
			'vendor',
			'vendor management',
			'multi-vendor',
			'marketplace',
			'w9',
			'w-9',
			'w9 form',
			'tax form',
		);

		// Secondary keywords
		$secondary_keywords = array(
			'downline',
			'upline',
			'tier commission',
			'network marketing',
			'influencer',
			'ambassador',
			'advocate program',
			'vendor store',
			'seller',
			'merchant',
			'payout',
			'payment tracking',
			'contractor',
			'freelancer',
			'fein',
			'tin',
			'taxpayer',
		);

		foreach ( $active_plugins as $plugin_file ) {
			if ( isset( $all_plugins[ $plugin_file ] ) ) {
				$plugin_data       = $all_plugins[ $plugin_file ];
				$plugin_name_lower = strtolower( $plugin_data['Name'] );
				$plugin_desc_lower = strtolower( $plugin_data['Description'] ?? '' );
				$combined_text     = $plugin_name_lower . ' ' . $plugin_desc_lower;

				// Calculate relevance score
				$score = 0;

				// Check primary keywords
				foreach ( $primary_keywords as $keyword ) {
					if ( strpos( $plugin_name_lower, $keyword ) !== false ) {
						$score += 10;
					}
					if ( strpos( $plugin_desc_lower, $keyword ) !== false ) {
						$score += 5;
					}
				}

				// Check secondary keywords
				foreach ( $secondary_keywords as $keyword ) {
					if ( strpos( $combined_text, $keyword ) !== false ) {
						$score += 3;
					}
				}

				if ( $score >= 5 ) {
					$affiliate_plugins[] = array(
						'name'        => $plugin_data['Name'],
						'version'     => $plugin_data['Version'] ?? '',
						'description' => $plugin_data['Description'] ?? '',
						'file'        => $plugin_file,
						'score'       => $score,
					);
				}
			}
		}

		usort(
			$affiliate_plugins,
			function ( $a, $b ) {
				return $b['score'] - $a['score'];
			}
		);

		return $affiliate_plugins;
	}

	/**
	 * Fetch all affiliate data with payment information (Enhanced scanning)
	 */
	public function fetch_all_affiliates_with_payments() {
		global $wpdb;
		$cache_key = 'w91099ch_affiliates_with_payments';
		$cached    = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// 1. BETTER DATABASE SCANNING - Find MORE tables:
		$patterns = array( '%aff%', '%affiliate%', '%referral%', '%partner%', '%vendor%', '%commission%', '%newaff%', '%payment%', '%order%', '%transaction%', '%payout%', '%earn%', '%sale%', '%purchase%', '%subscription%', '%invoice%', '%billing%', '%revenue%', '%fee%', '%balance%' );
		$tables   = array();
		$allData  = array();

		foreach ( $patterns as $pattern ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Introspection; result cached at end.
			$found = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
			if ( $found ) {
				$tables = array_merge( $tables, $found );
			}
		}
		$tables = array_unique( $tables );

		// Extract data from each table
		foreach ( $tables as $table ) {
			$table = is_string( $table ) ? $table : '';
			if ( '' === $table || ! $this->is_valid_table_name( $table ) ) {
				continue;
			}
			if ( ! $this->table_exists( $table ) ) {
				continue;
			}
			$table_sql = esc_sql( $table );

			$sql_count = 'SELECT COUNT(*) FROM `' . $table_sql . '` WHERE 1 = %d';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Introspection.
			try {
				$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . $table_sql . '` WHERE 1 = %d', 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			} catch (Exception $e) {
				$this->log( 'Error counting rows in table ' . $table . ': ' . $e->getMessage() );
				continue;
			}
			if ( $count == 0 ) {
				continue;
			}

			$cols_map = $this->get_table_columns( $table_sql );
			$columns  = is_array( $cols_map ) ? array_keys( $cols_map ) : array();
			if ( empty( $columns ) ) {
				continue;
			}

			$sql_rows = 'SELECT * FROM `' . $table_sql . '` WHERE 1 = %d LIMIT %d';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only introspection.
			try {
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `' . $table_sql . '` WHERE 1 = %d LIMIT %d', 1, 100 ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			} catch (Exception $e) {
				$this->log( 'Error querying table ' . $table . ': ' . $e->getMessage() );
				continue;
			}

			// If no rows, skip this table
			if ( empty( $rows ) ) {
				continue;
			}

			// 2. BETTER COLUMN DETECTION - Look for THESE column names:
			$relevantCols = array_filter(
				$columns,
				function ( $col ) {
					// Payment columns: 'amount', 'total_amount', 'payout_amount', 'net_amount', 'balance', 'commission', 'total', 'price', 'payment', 'earn', 'sale', 'purchase', 'revenue', 'fee'
					// Check BOTH exact matches AND partial matches
					return preg_match( '/id|name|email|user|status|pay|rate|amount|total_amount|payout_amount|net_amount|balance|commission|total|price|payment|earn|sale|purchase|revenue|fee|transaction|invoice|billing|address|city|state|zip|phone|ssn|fein|tin|business|affiliate|referral|vendor|partner/i', $col );
				}
			);

			// Skip if no relevant columns found
			if ( empty( $relevantCols ) ) {
				continue;
			}

			if ( ! empty( $rows ) ) {
				foreach ( $rows as $row ) {
					$normalized                  = $this->normalize_affiliate_data( $row );
					$normalized['plugin_source'] = $this->detect_plugin_from_table( $table );
					$normalized['type']          = $this->detect_entry_type( $table, $row );
					$allData[]                   = $normalized;
				}
			}
		}

		// Aggregate payment data by affiliate
		$allData = $this->aggregate_affiliate_payments( $allData );

		wp_cache_set( $cache_key, $allData, $this->cache_group, $this->cache_ttl_short );
		return $allData;
	}

	/**
	 * Normalize affiliate data structure (Enhanced payment detection)
	 */
	private function normalize_affiliate_data( $row ) {
		$normalized = array(
			// Basic Information
			'id'                          => '',
			'name'                        => '',
			'email'                       => '',
			'status'                      => '',
			'user_id'                     => '',
			'payment_info'                => '',
			'rate'                        => '',
			'payment_method'              => '',
			'payment_status'              => '',
			'transaction_id'              => '',
			'order_id'                    => '',
			'invoice_number'              => '',
			'currency'                    => get_option( 'woocommerce_currency', 'USD' ),

			// Payment Information - Enhanced detection for ALL payment columns
			'amount'                      => '0.00',
			'total_amount'                => '0.00',
			'subtotal'                    => '0.00',
			'tax'                         => '0.00',
			'shipping'                    => '0.00',
			'discount'                    => '0.00',
			'fees'                        => '0.00',
			'refunded_amount'             => '0.00',
			'net_amount'                  => '0.00',
			'balance'                     => '0.00',
			'commission'                  => '0.00',
			'payout_amount'               => '0.00',
			'due_date'                    => '',
			'paid_date'                   => '',
			'transaction_date'            => '',
			'payout_date'                 => '',
			'billing_cycle'               => '',
			'recurring'                   => '',

			// W-9 Information
			'business_name'               => '',
			'federal_tax_classification'  => 'Individual/Sole Proprietor',
			'address'                     => '',
			'city'                        => '',
			'state'                       => '',
			'zip_code'                    => '',
			'country'                     => '',
			'phone'                       => '',

			// Tax Identification
			'ssn'                         => '',
			'fein'                        => '',
			'tin'                         => '',
			'tin_type'                    => '', // 'SSN' or 'FEIN'
			'vat_number'                  => '',
			'tax_id'                      => '',

			// Tax Information
			'tax_year'                    => (int) gmdate( 'Y' ) - 1, // Default to previous tax year
			'total_earnings'              => '0.00',
			'federal_income_tax_withheld' => '0.00',
			'state_income'                => '0.00',
			'state_income_tax_withheld'   => '0.00',
			'state_id_number'             => '',

			// Additional Payment Metadata
			'payment_notes'               => '',
			'payment_gateway'             => '',
			'is_recurring'                => 'no',
			'next_payment_date'           => '',
			'last_payment_date'           => '',
		);

		foreach ( $row as $key => $value ) {
			$lower = strtolower( $key );

			// Skip null or empty values for most fields (but allow 0 for amounts)
			if ( $value === null || $value === '' ) {
				continue;
			}

			// Basic info detection
			if ( preg_match( '/^(id|affiliate_id|aff_id)$/i', $key ) && ! $normalized['id'] ) {
				$normalized['id'] = $value;
			} elseif ( preg_match( '/(name|display|username|first.*name|account.*name)$/i', $key ) && ! $normalized['name'] ) {
				$normalized['name'] = $value;
			} elseif ( preg_match( '/email/i', $key ) && ! $normalized['email'] ) {
				$normalized['email'] = $value;
			} elseif ( preg_match( '/status/i', $key ) && ! $normalized['status'] ) {
				$normalized['status'] = $value;
			} elseif ( preg_match( '/user.*id/i', $key ) && $key != 'id' && ! $normalized['user_id'] ) {
				$normalized['user_id'] = $value;
			} elseif ( preg_match( '/(payment|paypal|payout|account)/i', $key ) && ! $normalized['payment_info'] ) {
				$normalized['payment_info'] = $value;
			} elseif ( preg_match( '/(rate|commission|percentage)/i', $key ) && ! $normalized['rate'] ) {
				$normalized['rate'] = $value;
			}

			// Enhanced payment column detection - Look for ALL payment-related columns
			// Payment columns: 'amount', 'total_amount', 'payout_amount', 'net_amount', 'balance', 'commission', 'total', 'price', 'payment', 'earn', 'sale', 'purchase', 'revenue', 'fee'
			// Check BOTH exact matches AND partial matches
			elseif ( preg_match( '/(amount|total|price|payment|payout|balance|earn|sale|purchase|subscription|invoice|billing|revenue|fee|refund|discount|coupon|credit|debit|wallet|fund|tax|vat|gst|withdraw|deposit|transfer|settlement|due|paid|unpaid|outstanding|cleared|pending|processed|completed|failed|cancelled|refunded)/i', $key ) ) {

				// Capture ANY numeric value > 0
				if ( is_numeric( $value ) && (float) $value > 0 ) {
					if ( preg_match( '/(^amount$|^total$|^price$|^value$|^sum$)/i', $key ) ) {
						if ( (float) $normalized['amount'] == 0 ) {
							$normalized['amount'] = $value;
						}
					} elseif ( preg_match( '/(earn|payout|paid|revenue|income|gross)/i', $key ) ) {
						if ( (float) $normalized['payout_amount'] == 0 ) {
							$normalized['payout_amount'] = $value;
						}
					} elseif ( preg_match( '/(commission|comm_|aff_amount)/i', $key ) ) {
						if ( (float) $normalized['commission'] == 0 ) {
							$normalized['commission'] = $value;
						}
					} elseif ( (float) $normalized['amount'] == 0 ) {
						$normalized['amount'] = $value;
					}
				}
			}
		}

		return $normalized;
	}

	/**
	 * Detect plugin source from table name (Competoter style)
	 */
	private function detect_plugin_from_table( $tableName ) {
		global $wpdb;
		$table = strtolower( $tableName );

		// Plugin patterns
		$plugin_patterns = array(
			'newaff'         => 'New Affiliate Plugin',
			'affiliate_wp'   => 'AffiliateWP',
			'affiliatewp'    => 'AffiliateWP',
			'slicewp'        => 'SliceWP',
			'yith'           => 'YITH Affiliates',
			'itthinx'        => 'Affiliates Manager',
			'afwc'           => 'Affiliate For WooCommerce',
			'uap'            => 'Ultimate Affiliate Pro',
			'wpam'           => 'WP Affiliate Manager',
			'tapfiliate'     => 'Tapfiliate',
			'easy_affiliate' => 'Easy Affiliate',
			'wp_affiliate'   => 'WP Affiliate Platform',
			'referral'       => 'Referral System',
			'aff'            => 'Affiliate System',
		);

		foreach ( $plugin_patterns as $pattern => $name ) {
			if ( strpos( $table, $pattern ) !== false ) {
				return $name;
			}
		}

		return 'Other Affiliate Plugin';
	}

	/**
	 * Detect entry type from table name and data (Competoter style)
	 */
	private function detect_entry_type( $tableName, $rowData = array() ) {
		$table_lower = strtolower( $tableName );

		// Check table name for type indicators
		if ( strpos( $table_lower, 'vendor' ) !== false ) {
			return 'vendor';
		} elseif ( strpos( $table_lower, 'referral' ) !== false ) {
			return 'referral';
		} elseif ( strpos( $table_lower, 'partner' ) !== false ) {
			return 'partner';
		} elseif ( strpos( $table_lower, 'affiliate' ) !== false || strpos( $table_lower, 'aff' ) !== false ) {
			return 'affiliate';
		}

		return 'affiliate'; // Default to affiliate
	}

	/**
	 * Aggregate affiliate payment data for W9 reporting (Enhanced priority-based calculation)
	 */
	private function aggregate_affiliate_payments( $allData ) {
		$aggregated = array();

		foreach ( $allData as $record ) {
			$key = $record['plugin_source'] . '_' . $record['id'];

			if ( isset( $aggregated[ $key ] ) ) {
				// SUM ALL payment amounts
				$aggregated[ $key ]['amount']         += (float) $record['amount'];
				$aggregated[ $key ]['total_amount']   += (float) $record['total_amount'];
				$aggregated[ $key ]['payout_amount']  += (float) $record['payout_amount'];
				$aggregated[ $key ]['net_amount']     += (float) $record['net_amount'];
				$aggregated[ $key ]['balance']        += (float) $record['balance'];
				$aggregated[ $key ]['commission']     += (float) $record['commission'];
				$aggregated[ $key ]['total_earnings'] += (float) $record['total_earnings'];
			} else {
				$aggregated[ $key ] = $record;
			}
		}

		// Calculate final total_earnings
		foreach ( $aggregated as &$record ) {
			if ( (float) $record['total_earnings'] == 0 ) {
				// PRIORITY ORDER: net_amount > payout_amount > amount > total_amount > balance
				if ( (float) $record['net_amount'] > 0 ) {
					$record['total_earnings'] = $record['net_amount'];
				} elseif ( (float) $record['payout_amount'] > 0 ) {
					$record['total_earnings'] = $record['payout_amount'];
				} elseif ( (float) $record['amount'] > 0 ) {
					$record['total_earnings'] = $record['amount'];
				} elseif ( (float) $record['total_amount'] > 0 ) {
					$record['total_earnings'] = $record['total_amount'];
				} elseif ( (float) $record['balance'] > 0 ) {
					$record['total_earnings'] = $record['balance'];
				}
			}
		}

		return array_values( $aggregated );
	}

	/**
	 * Refresh detection - Main method for the "Refresh Detection" button
	 */
	public function refresh_detection() {
		// Detect and collect fresh data from ALL plugins (no persistence).
		$plugins          = $this->detect_affiliate_plugins();
		$total_affiliates = $this->get_total_affiliates_count();

		return array(
			'plugins'          => $plugins,
			'total_affiliates' => $total_affiliates,
		);
	}
}
