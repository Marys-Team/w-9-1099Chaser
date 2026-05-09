<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_Contractor_Plugin_Detector {
	private $cache_group = 'w91099ch_contractor_detector';

	private function cache_key( $prefix, $data ) {
		$blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
		$payload = function_exists( 'wp_json_encode' ) ? wp_json_encode( $data ) : json_encode( $data );
		return (string) $prefix . ':' . (string) $blog_id . ':' . md5( (string) $payload );
	}

	public function get_contractor_plugins_data() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$active      = (array) get_option( 'active_plugins', array() );

		$predefined = array(
			'memberpress/memberpress.php'             => array(
				'slug' => 'memberpress',
				'name' => 'MemberPress',
			),
			'paid-memberships-pro/paid-memberships-pro.php' => array(
				'slug' => 'pmpro',
				'name' => 'Paid Memberships Pro',
			),
			'ultimate-member/ultimate-member.php'     => array(
				'slug' => 'ultimatemember',
				'name' => 'Ultimate Member',
			),
			'user-registration/user-registration.php' => array(
				'slug' => 'userregistration',
				'name' => 'User Registration (WPEverest)',
			),
			'restrict-content-pro/restrict-content-pro.php' => array(
				'slug' => 'restrictcontentpro',
				'name' => 'Restrict Content Pro',
			),
			'woocommerce-memberships/woocommerce-memberships.php' => array(
				'slug' => 'woocommercememberships',
				'name' => 'WooCommerce Memberships',
			),
			'woocommerce-subscriptions/woocommerce-subscriptions.php' => array(
				'slug' => 'woocommercesubscriptions',
				'name' => 'WooCommerce Subscriptions',
			),
			's2member/s2member.php'                   => array(
				'slug' => 's2member',
				'name' => 's2Member',
			),
		);

		$plugins = array();

		foreach ( $predefined as $plugin_file => $info ) {
			if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
				continue;
			}

			$plugins[ $info['slug'] ] = array(
				'name'     => $info['name'],
				'slug'     => $info['slug'],
				'active'   => in_array( $plugin_file, $active, true ),
				'version'  => isset( $all_plugins[ $plugin_file ]['Version'] ) ? (string) $all_plugins[ $plugin_file ]['Version'] : '',
				'detected' => true,
				'source'   => 'predefined',
			);
		}

		$plugins = $this->detect_generic_contractor_plugins( $plugins, $all_plugins, $active );

		if ( isset( $plugins['erp'] ) ) {
			unset( $plugins['erp'] );
		}
		if ( isset( $plugins['wp-erp'] ) ) {
			unset( $plugins['wp-erp'] );
		}
		if ( isset( $plugins['wperp'] ) ) {
			unset( $plugins['wperp'] );
		}

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

	public function get_contractors_preview( $plugin_slug, $limit ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$plugin_slug = is_string( $plugin_slug ) ? $plugin_slug : '';

		if ( $plugin_slug === '' ) {
			return $this->get_all_members_preview( $limit );
		}

		return $this->get_members_preview_for_plugin( $plugin_slug, $limit );
	}

	private function get_members_preview_for_plugin( $plugin_slug, $limit ) {
		$plugin_slug = is_string( $plugin_slug ) ? $plugin_slug : '';

		switch ( $plugin_slug ) {
			case 'wperp':
				return $this->get_wperp_contractors_preview( $limit );
			case 'memberpress':
				return $this->get_wp_users_preview( $limit, 'MemberPress', $this->get_meta_query_for_plugin( 'memberpress' ) );
			case 'pmpro':
				return $this->get_wp_users_preview( $limit, 'Paid Memberships Pro', $this->get_meta_query_for_plugin( 'pmpro' ) );
			case 'ultimatemember':
				return $this->get_wp_users_preview( $limit, 'Ultimate Member', $this->get_meta_query_for_plugin( 'ultimatemember' ) );
			case 'userregistration':
				return $this->get_wp_users_preview( $limit, 'User Registration (WPEverest)', $this->get_meta_query_for_plugin( 'userregistration' ) );
			case 'simplemembership':
			case 'simple-membership':
				return $this->get_simple_membership_preview( $limit );
			case 'paid-member-subscriptions':
			case 'paidmembersubscriptions':
			case 'pms':
				return $this->get_paid_member_subscriptions_preview( $limit );
			default:
				return $this->get_wp_users_preview( $limit, $this->pretty_plugin_name_from_slug( $plugin_slug ), $this->get_meta_query_for_plugin( $plugin_slug ) );
		}
	}

	private function get_all_members_preview( $limit ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$plugins = $this->get_contractor_plugins_data();
		if ( ! is_array( $plugins ) || empty( $plugins ) ) {
			return array();
		}

		$rows = array();
		foreach ( array_keys( $plugins ) as $slug ) {
			$slug = is_string( $slug ) ? $slug : '';
			if ( $slug === '' ) {
				continue;
			}
			$sub = $this->get_members_preview_for_plugin( $slug, $limit );
			if ( is_array( $sub ) && ! empty( $sub ) ) {
				$rows = array_merge( $rows, $sub );
			}
		}

		$rows = $this->dedupe_rows( $rows );

		usort(
			$rows,
			function ( $a, $b ) {
				$ad = ( is_array( $a ) && isset( $a['created'] ) ) ? (string) $a['created'] : '';
				$bd = ( is_array( $b ) && isset( $b['created'] ) ) ? (string) $b['created'] : '';
				$at = $ad !== '' ? strtotime( $ad ) : 0;
				$bt = $bd !== '' ? strtotime( $bd ) : 0;
				if ( ! $at ) {
					$at = 0;
				}
				if ( ! $bt ) {
					$bt = 0;
				}
				return $bt <=> $at;
			}
		);

		if ( count( $rows ) > $limit ) {
			$rows = array_slice( $rows, 0, $limit );
		}

		return $rows;
	}

	private function dedupe_rows( $rows ) {
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$out  = array();
		$seen = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$email = isset( $r['email'] ) ? strtolower( trim( (string) $r['email'] ) ) : '';
			$key   = $email !== '' ? $email : strtolower( trim( (string) ( isset( $r['name'] ) ? $r['name'] : '' ) ) ) . '|' . trim( (string) ( isset( $r['created'] ) ? $r['created'] : '' ) );
			if ( $key === '' || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $r;
		}

		return $out;
	}

	private function detect_generic_contractor_plugins( $existing, $all_plugins, $active ) {
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$keywords = array(
			'contractor',
			'contractors',
			'freelancer',
			'freelancers',
			'service provider',
			'service providers',
			'membership',
			'member',
			'subscription',
			'subscriptions',
			'recurring',
			'profile',
			'hr',
			'employee',
			'employees',
			'vendor',
			'vendors',
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

			$slug = $this->slug_from_plugin_file( $plugin_file );
			$slug = $this->canonicalize_plugin_slug( $slug );

			if ( $this->is_blocked_generic_plugin( $slug, $name, $desc ) ) {
				continue;
			}
			if ( $slug === '' || isset( $used_slugs[ $slug ] ) ) {
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

	private function canonicalize_plugin_slug( $slug ) {
		$slug = is_string( $slug ) ? $slug : '';
		if ( $slug === '' ) {
			return '';
		}

		$aliases = array(
			'ultimate-member'           => 'ultimatemember',
			'paid-memberships-pro'      => 'pmpro',
			'wp-erp'                    => 'wperp',
			'user-registration'         => 'userregistration',
			'restrict-content-pro'      => 'restrictcontentpro',
			'woocommerce-memberships'   => 'woocommercememberships',
			'woocommerce-subscriptions' => 'woocommercesubscriptions',
			'simple-membership'         => 'simplemembership',
			'paid-member-subscriptions' => 'paidmembersubscriptions',
		);

		return isset( $aliases[ $slug ] ) ? (string) $aliases[ $slug ] : $slug;
	}

	private function is_blocked_generic_plugin( $slug, $name, $desc ) {
		$slug = is_string( $slug ) ? $slug : '';
		$name = is_string( $name ) ? $name : '';
		$desc = is_string( $desc ) ? $desc : '';

		$blocked_slugs = array(
			'erp',
			'wp-erp',
			'wperp',
			'slicewp',
			'prettylinks',
			'pretty-links',
			'yith-woocommerce-affiliates',
			'yith-woocommerce-affiliate',
			'woocommerce-affiliates',
			'affiliates',
			'multivendorx',
			'dc-woocommerce-multi-vendor',
			'my-powerly-master-affiliates-pro',
			'mypowerly-master-affiliates-pro',
			'plugin-compatibility-checker',
		);

		if ( $slug !== '' && in_array( $slug, $blocked_slugs, true ) ) {
			return true;
		}

		if ( preg_match( '/\berp\b/i', $name . ' ' . wp_strip_all_tags( $desc ) ) ) {
			return true;
		}

		$hay             = strtolower( $name . ' ' . wp_strip_all_tags( $desc ) );
		$blocked_phrases = array(
			'wp erp',
			'slicewp',
			'pretty links',
			'prettylinks',
			'yith woocommerce affiliates',
			'woocommerce affiliates',
			'affiliates',
			'multivendorx',
			'multi vendor',
			'master affiliates pro',
			'plugin compatibility checker',
		);

		foreach ( $blocked_phrases as $phrase ) {
			if ( strpos( $hay, $phrase ) !== false ) {
				return true;
			}
		}

		return false;
	}

	private function slug_from_plugin_file( $plugin_file ) {
		if ( ! is_string( $plugin_file ) || $plugin_file === '' ) {
			return '';
		}
		$parts = explode( '/', $plugin_file );
		if ( empty( $parts ) ) {
			return '';
		}
		return sanitize_key( (string) $parts[0] );
	}

	private function pretty_plugin_name_from_slug( $slug ) {
		$slug = is_string( $slug ) ? $slug : '';
		if ( $slug === '' ) {
			return '';
		}
		$slug = str_replace( array( '-', '_' ), ' ', $slug );
		$slug = ucwords( $slug );
		return $slug;
	}

	private function get_meta_query_for_plugin( $plugin_slug ) {
		$plugin_slug = is_string( $plugin_slug ) ? $plugin_slug : '';

		if ( $plugin_slug === 'memberpress' ) {
			return array(
				'relation' => 'OR',
				array(
					'key'     => 'mepr_user_levels',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'mepr_active_product_memberships',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'mepr_expired_product_memberships',
					'compare' => 'EXISTS',
				),
			);
		}

		if ( $plugin_slug === 'pmpro' ) {
			return array(
				'relation' => 'OR',
				array(
					'key'     => 'pmpro_membership_level',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'pmpro_membership_levels',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'pmpro_last_order',
					'compare' => 'EXISTS',
				),
			);
		}

		if ( $plugin_slug === 'ultimatemember' ) {
			return array(
				'relation' => 'OR',
				array(
					'key'     => 'um_account_status',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'account_status',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'um_member_role',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'um_registered',
					'compare' => 'EXISTS',
				),
			);
		}

		if ( $plugin_slug === 'userregistration' ) {
			return array(
				'relation' => 'OR',
				array(
					'key'     => 'ur_form_id',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'ur_user_form_id',
					'compare' => 'EXISTS',
				),
			);
		}

		if ( $plugin_slug === 'restrictcontentpro' ) {
			return array(
				'relation' => 'OR',
				array(
					'key'     => 'rcp_membership_level',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'rcp_status',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'rcp_expiration_date',
					'compare' => 'EXISTS',
				),
			);
		}

		if ( $plugin_slug === 'woocommercememberships' ) {
			return array(
				'relation' => 'OR',
				array(
					'key'     => 'wc_memberships',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'wc_memberships_for_user',
					'compare' => 'EXISTS',
				),
			);
		}

		if ( $plugin_slug === 'woocommercesubscriptions' ) {
			return array(
				'relation' => 'OR',
				array(
					'key'     => 'wcs_active_subscriptions',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_subscription_status',
					'compare' => 'EXISTS',
				),
			);
		}

		// Unknown plugin: avoid generic meta-key matching to prevent unrelated data.
		return array();
	}

	private function get_wp_users_preview_by_meta_key( $limit, $source_plugin, $meta_key ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}
		$meta_key = is_string( $meta_key ) ? $meta_key : '';
		if ( '' === $meta_key ) {
			return array();
		}
		$meta_key = sanitize_text_field( $meta_key );

		global $wpdb;
		$users_table    = esc_sql( $wpdb->users );
		$usermeta_table = esc_sql( $wpdb->usermeta );

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$cache_key = $this->cache_key( 'wp_users_by_meta', array( 'meta_key' => $meta_key, 'limit' => $limit ) );
		$cached    = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached && is_array( $cached ) ) {
			$user_ids = $cached;
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$user_ids = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT u.ID'
					. ' FROM `' . $users_table . '` u' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					. ' INNER JOIN `' . $usermeta_table . '` um' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					. ' ON um.user_id = u.ID AND um.meta_key = %s'
					. ' ORDER BY u.user_registered DESC'
					. ' LIMIT %d',
					$meta_key,
					$limit
				)
			);
			wp_cache_set( $cache_key, $user_ids, $this->cache_group, 300 );
		}

		$user_ids = is_array( $user_ids ) ? array_map( 'absint', $user_ids ) : array();
		$user_ids = array_values( array_filter( $user_ids ) );
		if ( empty( $user_ids ) ) {
			return array();
		}

		$rows = array();
		foreach ( $user_ids as $user_id ) {
			$wp_user = get_userdata( $user_id );
			if ( ! $wp_user ) {
				continue;
			}
			$role    = '';
			if ( is_array( $wp_user->roles ) && ! empty( $wp_user->roles ) ) {
				$role = (string) reset( $wp_user->roles );
			}

			$status  = 'Active';
			$created = isset( $wp_user->user_registered ) ? (string) $wp_user->user_registered : '';

			$rows[] = array(
				'name'          => isset( $wp_user->display_name ) ? (string) $wp_user->display_name : '',
				'email'         => isset( $wp_user->user_email ) ? (string) $wp_user->user_email : '',
				'role_type'     => $role,
				'status'        => $status,
				'source_plugin' => (string) $source_plugin,
				'created'       => $created,
			);
		}

		return $this->dedupe_rows( $rows );
	}

	private function get_wp_users_preview( $limit, $source_plugin, $meta_query ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}
		if ( ! is_array( $meta_query ) || empty( $meta_query ) ) {
			return array();
		}

		$relation = '';
		if ( isset( $meta_query['relation'] ) && is_string( $meta_query['relation'] ) ) {
			$relation = strtoupper( trim( $meta_query['relation'] ) );
		}

		if ( 'OR' === $relation ) {
			$merged = array();
			foreach ( $meta_query as $clause ) {
				if ( ! is_array( $clause ) ) {
					continue;
				}
				$meta_key = isset( $clause['key'] ) ? (string) $clause['key'] : '';
				if ( '' === $meta_key ) {
					continue;
				}
				$merged = array_merge( $merged, $this->get_wp_users_preview_by_meta_key( $limit, $source_plugin, $meta_key ) );
			}
			$merged = $this->dedupe_rows( $merged );
			return array_slice( $merged, 0, $limit );
		}

		$first = null;
		foreach ( $meta_query as $clause ) {
			if ( is_array( $clause ) && isset( $clause['key'] ) ) {
				$first = $clause;
				break;
			}
		}
		if ( ! is_array( $first ) ) {
			return array();
		}
		$meta_key = isset( $first['key'] ) ? (string) $first['key'] : '';
		if ( '' === $meta_key ) {
			return array();
		}

		return $this->get_wp_users_preview_by_meta_key( $limit, $source_plugin, $meta_key );
	}

	private function get_table_columns( $table ) {
		global $wpdb;
		$table = is_string( $table ) ? $table : '';
		if ( $table === '' ) {
			return array();
		}
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			return array();
		}

		$cache_key = $this->cache_key( 'table_cols', array( 'table' => $table ) );
		$cached    = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$table_sql = esc_sql( $table );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$cols = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM `' . $table_sql . '` WHERE 1 = %d', 1 ), 0 );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $cols ) ) {
			return array();
		}
		$out = array();
		foreach ( $cols as $c ) {
			if ( is_string( $c ) && $c !== '' ) {
				$out[ $c ] = true;
			}
		}
		wp_cache_set( $cache_key, $out, $this->cache_group, 600 );
		return $out;
	}

	private function table_exists( $table ) {
		global $wpdb;
		$table = is_string( $table ) ? $table : '';
		if ( $table === '' ) {
			return false;
		}
		$cache_key = $this->cache_key( 'table_exists', array( 'table' => $table ) );
		$cached    = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached ) {
			return (bool) $cached;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$exists = is_string( $found ) && $found === $table;
		wp_cache_set( $cache_key, $exists, $this->cache_group, 600 );
		return $exists;
	}

	private function get_simple_membership_preview( $limit ) {
		global $wpdb;
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$table = $wpdb->prefix . 'swpm_members_tbl';
		if ( ! $this->table_exists( $table ) ) {
			return array();
		}

		$table_sql = esc_sql( $table );
		$cols      = $this->get_table_columns( $table );
		if ( empty( $cols ) ) {
			return array();
		}

		if ( ! isset( $cols['email'] ) ) {
			return array();
		}

		$select = array( '`email` AS email' );
		if ( isset( $cols['first_name'] ) ) {
			$select[] = '`first_name` AS first_name';
		}
		if ( isset( $cols['last_name'] ) ) {
			$select[] = '`last_name` AS last_name';
		}
		if ( isset( $cols['user_name'] ) ) {
			$select[] = '`user_name` AS user_name';
		}
		if ( isset( $cols['joined_date'] ) ) {
			$select[] = '`joined_date` AS created';
			$order_by = '`joined_date`';
		} elseif ( isset( $cols['subscription_starts'] ) ) {
			$select[] = '`subscription_starts` AS created';
			$order_by = '`subscription_starts`';
		} else {
			$order_by = '`email`';
		}
		if ( isset( $cols['account_state'] ) ) {
			$select[] = '`account_state` AS status';
		} elseif ( isset( $cols['member_state'] ) ) {
			$select[] = '`member_state` AS status';
		}
		if ( isset( $cols['membership_level'] ) ) {
			$select[] = '`membership_level` AS membership_level';
		}

		$sql = 'SELECT ' . implode( ', ', $select ) . ' FROM `' . $table_sql . '` ORDER BY ' . $order_by . ' DESC LIMIT %d';
		$cache_key = $this->cache_key( 'swpm_preview', array( 'limit' => $limit, 'table' => $table_sql, 'sql' => $sql ) );
		$cached    = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached && is_array( $cached ) ) {
			$results = $cached;
		} else {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$results = $wpdb->get_results( $wpdb->prepare( 'SELECT ' . implode( ', ', $select ) . ' FROM `' . $table_sql . '` ORDER BY ' . $order_by . ' DESC LIMIT %d', $limit ), ARRAY_A );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			wp_cache_set( $cache_key, $results, $this->cache_group, 300 );
		}
		if ( ! is_array( $results ) || empty( $results ) ) {
			return array();
		}

		$rows = array();
		foreach ( $results as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$email = isset( $r['email'] ) ? (string) $r['email'] : '';
			$name  = '';
			$fn    = isset( $r['first_name'] ) ? trim( (string) $r['first_name'] ) : '';
			$ln    = isset( $r['last_name'] ) ? trim( (string) $r['last_name'] ) : '';
			if ( $fn !== '' || $ln !== '' ) {
				$name = trim( $fn . ' ' . $ln );
			}
			if ( $name === '' && isset( $r['user_name'] ) ) {
				$name = (string) $r['user_name'];
			}
			$created   = isset( $r['created'] ) ? (string) $r['created'] : '';
			$status    = isset( $r['status'] ) ? (string) $r['status'] : 'Active';
			$role_type = isset( $r['membership_level'] ) ? (string) $r['membership_level'] : '';

			$rows[] = array(
				'name'          => $name,
				'email'         => $email,
				'role_type'     => $role_type,
				'status'        => $status,
				'source_plugin' => 'Simple Membership',
				'created'       => $created,
			);
		}

		return $this->dedupe_rows( $rows );
	}

	private function get_paid_member_subscriptions_preview( $limit ) {
		global $wpdb;
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$table = $wpdb->prefix . 'pms_member_subscriptions';
		if ( ! $this->table_exists( $table ) ) {
			return array();
		}

		$table_sql = esc_sql( $table );
		$cols      = $this->get_table_columns( $table );
		if ( empty( $cols ) || ! isset( $cols['user_id'] ) ) {
			return array();
		}

		$select = array( 'ms.user_id AS user_id' );
		if ( isset( $cols['status'] ) ) {
			$select[] = 'ms.status AS status';
		}
		if ( isset( $cols['start_date'] ) ) {
			$select[] = 'ms.start_date AS created';
			$order_by = 'ms.start_date';
		} elseif ( isset( $cols['date_created'] ) ) {
			$select[] = 'ms.date_created AS created';
			$order_by = 'ms.date_created';
		} elseif ( isset( $cols['created_at'] ) ) {
			$select[] = 'ms.created_at AS created';
			$order_by = 'ms.created_at';
		} else {
			$order_by = 'ms.user_id';
		}

		$sql = 'SELECT ' . implode( ', ', $select ) . ' FROM `' . $table_sql . '` ms ORDER BY ' . $order_by . ' DESC LIMIT %d';
		$cache_key = $this->cache_key( 'pms_preview', array( 'limit' => $limit, 'table' => $table_sql, 'sql' => $sql ) );
		$cached    = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached && is_array( $cached ) ) {
			$results = $cached;
		} else {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$results = $wpdb->get_results( $wpdb->prepare( 'SELECT ' . implode( ', ', $select ) . ' FROM `' . $table_sql . '` ms ORDER BY ' . $order_by . ' DESC LIMIT %d', $limit * 2 ), ARRAY_A );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			wp_cache_set( $cache_key, $results, $this->cache_group, 300 );
		}
		if ( ! is_array( $results ) || empty( $results ) ) {
			return array();
		}

		$rows = array();
		foreach ( $results as $r ) {
			if ( ! is_array( $r ) || ! isset( $r['user_id'] ) ) {
				continue;
			}
			$user_id = (int) $r['user_id'];
			if ( $user_id <= 0 ) {
				continue;
			}
			$u = get_userdata( $user_id );
			if ( ! $u ) {
				continue;
			}

			$status  = isset( $r['status'] ) ? (string) $r['status'] : 'Active';
			$created = isset( $r['created'] ) ? (string) $r['created'] : ( isset( $u->user_registered ) ? (string) $u->user_registered : '' );

			$role = '';
			if ( is_array( $u->roles ) && ! empty( $u->roles ) ) {
				$role = (string) reset( $u->roles );
			}

			$rows[] = array(
				'name'          => isset( $u->display_name ) ? (string) $u->display_name : '',
				'email'         => isset( $u->user_email ) ? (string) $u->user_email : '',
				'role_type'     => $role,
				'status'        => $status,
				'source_plugin' => 'Paid Member Subscriptions',
				'created'       => $created,
			);
		}

		$rows = $this->dedupe_rows( $rows );
		if ( count( $rows ) > $limit ) {
			$rows = array_slice( $rows, 0, $limit );
		}

		return $rows;
	}

	private function get_wperp_contractors_preview( $limit ) {
		if ( function_exists( 'erp_hr_get_employees' ) ) {
			$employees = erp_hr_get_employees(
				array(
					'number'  => $limit,
					'orderby' => 'hiring_date',
					'order'   => 'DESC',
				)
			);

			if ( is_array( $employees ) && ! empty( $employees ) ) {
				$rows = array();
				foreach ( $employees as $emp ) {
					$name = '';
					if ( is_object( $emp ) ) {
						if ( isset( $emp->display_name ) && is_string( $emp->display_name ) ) {
							$name = $emp->display_name;
						} elseif ( isset( $emp->first_name ) || isset( $emp->last_name ) ) {
							$name = trim( (string) ( $emp->first_name ?? '' ) . ' ' . (string) ( $emp->last_name ?? '' ) );
						}
					}

					$email = '';
					if ( is_object( $emp ) && isset( $emp->user_email ) && is_string( $emp->user_email ) ) {
						$email = $emp->user_email;
					}

					$created = '';
					if ( is_object( $emp ) && isset( $emp->created_at ) && is_string( $emp->created_at ) ) {
						$created = $emp->created_at;
					}

					$status = 'Active';
					if ( is_object( $emp ) && isset( $emp->status ) && $emp->status !== '' ) {
						$status = (string) $emp->status;
					}

					$rows[] = array(
						'name'          => $name,
						'email'         => $email,
						'role_type'     => 'Employee',
						'status'        => $status,
						'source_plugin' => 'WP ERP',
						'created'       => $created,
					);
				}

				return $rows;
			}
		}

		return $this->get_wp_users_preview( $limit, 'WP ERP', array() );
	}
}
