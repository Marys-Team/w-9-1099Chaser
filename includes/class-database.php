<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_Database {

	private static $instance = null;
	private $table_name;
	private $cache_group  = 'w91099ch';
	private $cache_expire = 300;

	private function cache_key( $prefix, $data ) {
		$blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
		$payload = function_exists( 'wp_json_encode' ) ? wp_json_encode( $data ) : json_encode( $data );
		return (string) $prefix . ':' . (string) $blog_id . ':' . md5( (string) $payload );
	}

	private function db_get_var( $sql, $args = array(), $ttl = null ) {
		global $wpdb;
		if ( null === $ttl ) {
			$ttl = $this->cache_expire;
		}

		$key    = $this->cache_key( 'var', array( 'sql' => (string) $sql, 'args' => $args ) );
		$cached = wp_cache_get( $key, $this->cache_group );
		if ( false !== $cached ) {
			return $cached;
		}

		if ( ! empty( $args ) ) {
			$args = array_values( (array) $args );
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$val = $wpdb->get_var( $wpdb->prepare( (string) $sql, ...$args ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$val = $wpdb->get_var( (string) $sql );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		}
		wp_cache_set( $key, $val, $this->cache_group, (int) $ttl );
		return $val;
	}

	private function db_get_row( $sql, $args = array(), $ttl = null ) {
		global $wpdb;
		if ( null === $ttl ) {
			$ttl = $this->cache_expire;
		}

		$key    = $this->cache_key( 'row', array( 'sql' => (string) $sql, 'args' => $args ) );
		$cached = wp_cache_get( $key, $this->cache_group );
		if ( false !== $cached ) {
			return $cached;
		}

		if ( ! empty( $args ) ) {
			$args = array_values( (array) $args );
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( (string) $sql, ...$args ), ARRAY_A );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$row = $wpdb->get_row( (string) $sql, ARRAY_A );
		}
		wp_cache_set( $key, $row, $this->cache_group, (int) $ttl );
		return $row;
	}

	private function db_get_results( $sql, $args = array(), $ttl = null ) {
		global $wpdb;
		if ( null === $ttl ) {
			$ttl = $this->cache_expire;
		}

		$key    = $this->cache_key( 'results', array( 'sql' => (string) $sql, 'args' => $args ) );
		$cached = wp_cache_get( $key, $this->cache_group );
		if ( false !== $cached ) {
			return $cached;
		}

		if ( ! empty( $args ) ) {
			$args = array_values( (array) $args );
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare( (string) $sql, ...$args ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$rows = $wpdb->get_results( (string) $sql );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		}
		$rows = is_array( $rows ) ? $rows : array();
		wp_cache_set( $key, $rows, $this->cache_group, (int) $ttl );
		return $rows;
	}

	private function flush_cache_group() {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( $this->cache_group );
			return;
		}
		wp_cache_delete( 'w91099ch_db_flush', $this->cache_group );
	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'w91099ch_affiliates';
	}

	public function create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            affiliate_id varchar(100) NOT NULL,
            plugin_slug varchar(100) NOT NULL,
            plugin_name varchar(200) NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(200) NOT NULL,
            company_name varchar(200) DEFAULT '',
            client_type varchar(50) DEFAULT 'individual',
            status varchar(50) DEFAULT 'active',
            earnings decimal(10,2) DEFAULT 0.00,
            referrals int(11) DEFAULT 0,
            date_registered datetime DEFAULT '0000-00-00 00:00:00',
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            meta_data longtext,
            PRIMARY KEY (id),
            UNIQUE KEY affiliate_plugin_unique (affiliate_id, plugin_slug),
            KEY plugin_slug (plugin_slug),
            KEY email (email),
            KEY status (status)
        ) $charset_collate;";

		// Suppress any output from dbDelta
		ob_start();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		ob_end_clean();

		// Silent operation - no logging during activation
		return;
	}

	// Example of a fixed query method with caching
	public function get_data( $id ) {
		$cache_key = 'w91099ch_data_' . $id;

		// Try to get from cache first
		$cached = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->db_get_row( "SELECT * FROM {$this->table_name} WHERE id = %d", array( (int) $id ) );

		// Cache the result
		if ( $result ) {
			wp_cache_set( $cache_key, $result, $this->cache_group, $this->cache_expire );
		}

		return $result;
	}

	// Example of an insert/update method
	public function save_data( $data ) {
		global $wpdb;

		// Data validation and sanitization
		$data = array_map( 'sanitize_text_field', $data );

		// Check if we're updating or inserting
		if ( ! empty( $data['id'] ) ) {
			$where  = array( 'id' => intval( $data['id'] ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Plugin-owned table write.
			$result = $wpdb->update(
				$this->table_name,
				$data,
				$where,
				array_fill( 0, count( $data ), '%s' ),
				array( '%d' )
			);

			// Clear cache on update
			wp_cache_delete( 'w91099ch_data_' . $data['id'], $this->cache_group );
			$this->flush_cache_group();
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Plugin-owned table write.
			$result = $wpdb->insert(
				$this->table_name,
				$data,
				array_fill( 0, count( $data ), '%s' )
			);
			$this->flush_cache_group();
		}
		return $result;
	}

	public static function create_tables() {
		// Start output buffering to prevent any dbDelta output
		ob_start();
		
		try {
			$instance = self::get_instance();
			$instance->create_table();
			add_option( 'w91099ch_db_version', '1.0.0' );
		} catch ( Throwable $e ) {
			// Silent operation
		}
		
		// Clean any output from dbDelta
		$output = ob_get_clean();
		if ( $output ) {
			// Log for debugging but don't display
			error_log( 'W9-1099-Chaser DB creation output: ' . $output );
		}
	}

	public function save_affiliate( $affiliate_data ) {
		global $wpdb;

		$defaults = array(
			'affiliate_id'    => '',
			'plugin_slug'     => '',
			'plugin_name'     => '',
			'first_name'      => '',
			'last_name'       => '',
			'email'           => '',
			'company_name'    => '',
			'client_type'     => 'individual',
			'status'          => 'active',
			'earnings'        => 0.00,
			'referrals'       => 0,
			'date_registered' => current_time( 'mysql' ),
			'meta_data'       => '',
		);

		$data = wp_parse_args( $affiliate_data, $defaults );

		if ( is_array( $data['meta_data'] ) ) {
			$data['meta_data'] = json_encode( $data['meta_data'] );
		}

		$existing = $this->db_get_var(
			"SELECT id FROM {$this->table_name} WHERE affiliate_id = %s AND plugin_slug = %s",
			array( (string) $data['affiliate_id'], (string) $data['plugin_slug'] ),
			60
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table write.
			$result = $wpdb->update(
				$this->table_name,
				$data,
				array( 'id' => $existing )
			);

			if ( $result !== false ) {
				if ( function_exists( 'w91099ch_log' ) ) {
					w91099ch_log( "Updated affiliate {$data['affiliate_id']} for plugin {$data['plugin_slug']}" );
				}
				return $existing;
			}
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Plugin-owned table write.
			$result = $wpdb->insert(
				$this->table_name,
				$data
			);

			if ( $result ) {
				if ( function_exists( 'w91099ch_log' ) ) {
					w91099ch_log( "Inserted new affiliate {$data['affiliate_id']} for plugin {$data['plugin_slug']}" );
				}
				return $wpdb->insert_id;
			}
		}

		$this->flush_cache_group();

		return false;
	}

	public function get_affiliates_by_plugin( $plugin_slug, $limit = 100, $offset = 0 ) {
		return $this->db_get_results(
			"SELECT * FROM {$this->table_name} WHERE plugin_slug = %s ORDER BY last_updated DESC LIMIT %d OFFSET %d",
			array( (string) $plugin_slug, (int) $limit, (int) $offset )
		);
	}

	public function get_all_affiliates( $limit = 100, $offset = 0 ) {
		return $this->db_get_results(
			"SELECT * FROM {$this->table_name} ORDER BY last_updated DESC LIMIT %d OFFSET %d",
			array( (int) $limit, (int) $offset )
		);
	}

	public function get_all_affiliates_excluding( $exclude_slugs = array(), $limit = 100, $offset = 0 ) {
		global $wpdb;

		$exclude_slugs = array_values( array_filter( array_map( 'strval', (array) $exclude_slugs ) ) );

		if ( empty( $exclude_slugs ) ) {
			return $this->get_all_affiliates( $limit, $offset );
		}

		$placeholders = implode( ',', array_fill( 0, count( $exclude_slugs ), '%s' ) );

		$sql = "SELECT * FROM {$this->table_name} WHERE plugin_slug NOT IN ($placeholders) ORDER BY last_updated DESC LIMIT %d OFFSET %d";
		return $this->db_get_results( $sql, array_merge( $exclude_slugs, array( (int) $limit, (int) $offset ) ) );
	}

	public function get_total_affiliates_count( $exclude_slugs = array() ) {
		$exclude_slugs = array_values( array_filter( array_map( 'strval', (array) $exclude_slugs ) ) );

		if ( empty( $exclude_slugs ) ) {
			return (int) $this->db_get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
		}

		$placeholders = implode( ',', array_fill( 0, count( $exclude_slugs ), '%s' ) );
		$sql          = "SELECT COUNT(*) FROM {$this->table_name} WHERE plugin_slug NOT IN ($placeholders)";
		return (int) $this->db_get_var( $sql, $exclude_slugs );
	}

	public function get_payout_summary( $plugin_slug = '', $exclude_slugs = array() ) {
		global $wpdb;

		$exclude_slugs = array_values( array_filter( array_map( 'strval', (array) $exclude_slugs ) ) );

		if ( $plugin_slug ) {
			$cache_key = 'w91099ch_payout_summary_' . $plugin_slug;

			// Try to get from cache first
			$cached = wp_cache_get( $cache_key, $this->cache_group );
			if ( false !== $cached ) {
				return $cached;
			}

			$row = $this->db_get_row(
				"SELECT
					COALESCE(SUM(earnings), 0) AS total_payouts,
					COALESCE(SUM(CASE WHEN earnings > 0 THEN 1 ELSE 0 END), 0) AS affiliates_with_payouts
				 FROM {$this->table_name}
				 WHERE plugin_slug = %s",
				array( (string) $plugin_slug ),
				60
			);

			// Cache the result
			wp_cache_set( $cache_key, $row, $this->cache_group, $this->cache_expire );
		} elseif ( ! empty( $exclude_slugs ) ) {
				$cache_key = 'w91099ch_payout_summary_excluding_' . implode( '_', $exclude_slugs );

				// Try to get from cache first
				$cached = wp_cache_get( $cache_key, $this->cache_group );
				if ( false !== $cached ) {
					return $cached;
				}

				$placeholders = implode( ',', array_fill( 0, count( $exclude_slugs ), '%s' ) );
				$sql          = "SELECT
					COALESCE(SUM(earnings), 0) AS total_payouts,
					COALESCE(SUM(CASE WHEN earnings > 0 THEN 1 ELSE 0 END), 0) AS affiliates_with_payouts
				 FROM {$this->table_name}
				 WHERE plugin_slug NOT IN ($placeholders)";
				$row          = $this->db_get_row( $sql, $exclude_slugs, 60 );

				// Cache the result
				wp_cache_set( $cache_key, $row, $this->cache_group, $this->cache_expire );
		} else {
			$cache_key = 'w91099ch_payout_summary';

			// Try to get from cache first
			$cached = wp_cache_get( $cache_key, $this->cache_group );
			if ( false !== $cached ) {
				return $cached;
			}

			$row = $this->db_get_row(
				"SELECT
					COALESCE(SUM(earnings), 0) AS total_payouts,
					COALESCE(SUM(CASE WHEN earnings > 0 THEN 1 ELSE 0 END), 0) AS affiliates_with_payouts
				 FROM {$this->table_name}",
				array(),
				60
			);

			// Cache the result
			wp_cache_set( $cache_key, $row, $this->cache_group, $this->cache_expire );
		}

		$total_payouts           = 0.0;
		$affiliates_with_payouts = 0;

		if ( is_array( $row ) ) {
			if ( isset( $row['total_payouts'] ) && is_numeric( $row['total_payouts'] ) ) {
				$total_payouts = (float) $row['total_payouts'];
			}
			if ( isset( $row['affiliates_with_payouts'] ) && is_numeric( $row['affiliates_with_payouts'] ) ) {
				$affiliates_with_payouts = (int) $row['affiliates_with_payouts'];
			}
		}

		$avg_payout = 0.0;
		if ( $affiliates_with_payouts > 0 ) {
			$avg_payout = $total_payouts / $affiliates_with_payouts;
		}

		return array(
			'total_payouts'           => $total_payouts,
			'affiliates_with_payouts' => $affiliates_with_payouts,
			'avg_payout'              => $avg_payout,
		);
	}

	public function get_affiliates_count_by_plugin( $plugin_slug = '' ) {
		global $wpdb;

		if ( $plugin_slug ) {
			$cache_key = 'w91099ch_affiliates_count_by_plugin_' . $plugin_slug;

			// Try to get from cache first
			$cached = wp_cache_get( $cache_key, $this->cache_group );
			if ( false !== $cached ) {
				return $cached;
			}

			$count = (int) $this->db_get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE plugin_slug = %s", array( (string) $plugin_slug ), 60 );

			// Cache the result
			wp_cache_set( $cache_key, $count, $this->cache_group, $this->cache_expire );

			return $count;
		}

		$cache_key = 'w91099ch_affiliates_count_by_plugin';

		// Try to get from cache first
		$cached = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached ) {
			return $cached;
		}

		$results = $this->db_get_results( "SELECT plugin_slug, COUNT(*) as count FROM {$this->table_name} GROUP BY plugin_slug", array(), 60 );

		$counts = array();
		foreach ( $results as $result ) {
			$counts[ $result->plugin_slug ] = (int) $result->count;
		}

		// Cache the result
		wp_cache_set( $cache_key, $counts, $this->cache_group, $this->cache_expire );

		return $counts;
	}

	public function get_plugins_with_counts() {
		global $wpdb;

		$cache_key = 'w91099ch_plugins_with_counts';

		// Try to get from cache first
		$cached = wp_cache_get( $cache_key, $this->cache_group );
		if ( false !== $cached ) {
			return $cached;
		}

		$results = $this->db_get_results(
			"SELECT plugin_slug, plugin_name, COUNT(*) as affiliate_count 
			 FROM {$this->table_name} 
			 GROUP BY plugin_slug, plugin_name 
			 ORDER BY affiliate_count DESC",
			array(),
			60
		);

		$plugins = array();
		foreach ( $results as $result ) {
			$plugins[ $result->plugin_slug ] = array(
				'name'            => $result->plugin_name,
				'affiliate_count' => (int) $result->affiliate_count,
			);
		}

		return $plugins;
	}

	public function clear_all_affiliates() {
		global $wpdb;
		$table = esc_sql( $this->table_name );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $table . '` WHERE 1 = %d', 1 ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$this->flush_cache_group();
		if ( function_exists( 'w91099ch_log' ) ) {
			w91099ch_log( "Cleared {$deleted} affiliates from database" );
		}
		return $deleted;
	}

	public function delete_affiliates_by_plugin( $plugin_slug ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table write.
		$deleted = $wpdb->delete(
			$this->table_name,
			array( 'plugin_slug' => $plugin_slug )
		);

		if ( function_exists( 'w91099ch_log' ) ) {
			w91099ch_log( "Deleted {$deleted} affiliates for plugin {$plugin_slug}" );
		}
		$this->flush_cache_group();
		return $deleted;
	}
}
