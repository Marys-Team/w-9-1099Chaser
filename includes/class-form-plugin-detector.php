<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_Form_Plugin_Detector {
	private $cache_group = 'w91099ch_form_detector';

	private function cache_key( $prefix, $data ) {
		$blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
		$payload = function_exists( 'wp_json_encode' ) ? wp_json_encode( $data ) : json_encode( $data );
		return (string) $prefix . ':' . (string) $blog_id . ':' . md5( (string) $payload );
	}

	private function cache_get( $key, &$found = null ) {
		$found  = false;
		$cached = wp_cache_get( $key, $this->cache_group, false, $found );
		return $cached;
	}

	private function cache_set( $key, $value, $ttl ) {
		wp_cache_set( $key, $value, $this->cache_group, (int) $ttl );
		return $value;
	}

	private function db_get_var( $sql, $args = array(), $ttl = 300 ) {
		global $wpdb;

		$key   = $this->cache_key( 'var', array( 'sql' => (string) $sql, 'args' => $args ) );
		$found = false;
		$hit   = $this->cache_get( $key, $found );
		if ( $found ) {
			return $hit;
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

		return $this->cache_set( $key, $val, $ttl );
	}

	private function db_get_col( $sql, $col_offset = 0, $ttl = 300, $args = array() ) {
		global $wpdb;

		$key   = $this->cache_key( 'col', array( 'sql' => (string) $sql, 'offset' => (int) $col_offset ) );
		$found = false;
		$hit   = $this->cache_get( $key, $found );
		if ( $found ) {
			return is_array( $hit ) ? $hit : array();
		}

		if ( ! empty( $args ) ) {
			$args = array_values( (array) $args );
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_col( $wpdb->prepare( (string) $sql, ...$args ), (int) $col_offset );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_col( (string) $sql, (int) $col_offset );
		}
		$rows = is_array( $rows ) ? $rows : array();

		return $this->cache_set( $key, $rows, $ttl );
	}

	private function db_get_results( $sql, $args = array(), $ttl = 300 ) {
		global $wpdb;

		$key   = $this->cache_key( 'results', array( 'sql' => (string) $sql, 'args' => $args ) );
		$found = false;
		$hit   = $this->cache_get( $key, $found );
		if ( $found ) {
			return is_array( $hit ) ? $hit : array();
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

		return $this->cache_set( $key, $rows, $ttl );
	}

	private function table_exists( $table ) {
		global $wpdb;
		$table = is_string( $table ) ? $table : '';
		if ( $table === '' ) {
			return false;
		}

		$key   = $this->cache_key( 'table_exists', array( 'table' => $table ) );
		$found = false;
		$hit   = $this->cache_get( $key, $found );
		if ( $found ) {
			return (bool) $hit;
		}

		$exists = ( $this->db_get_var( 'SHOW TABLES LIKE %s', array( $table ), 300 ) === $table );
		return (bool) $this->cache_set( $key, $exists, 300 );
	}

	public function get_form_plugins_data() {
		$plugins = array();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$active      = (array) get_option( 'active_plugins', array() );

		$plugins = $this->detect_generic_form_plugins( $plugins, $all_plugins, $active );

		return $plugins;
	}

	private function detect_generic_form_plugins( $existing_plugins, $all_plugins, $active ) {
		if ( ! is_array( $existing_plugins ) ) {
			$existing_plugins = array();
		}

		$all_plugins = is_array( $all_plugins ) ? $all_plugins : array();
		$active      = is_array( $active ) ? $active : array();
		if ( empty( $all_plugins ) || empty( $active ) ) {
			return $existing_plugins;
		}

		$allowlist = array(
			'fluentform/fluentform.php'                 => array(
				'slug' => 'fluentform',
				'name' => 'Fluent Forms',
			),
			'wpforms-lite/wpforms.php'                  => array(
				'slug' => 'wpforms',
				'name' => 'WPForms Lite',
			),
			'contact-form-7/wp-contact-form-7.php'      => array(
				'slug' => 'contactform7',
				'name' => 'Contact Form 7',
			),
			'formidable/formidable.php'                 => array(
				'slug' => 'formidable',
				'name' => 'Formidable Forms',
			),
			'forminator/forminator.php'                 => array(
				'slug' => 'forminator',
				'name' => 'Forminator',
			),
			'ninja-forms/ninja-forms.php'               => array(
				'slug' => 'ninjaforms',
				'name' => 'Ninja Forms',
			),
			'everest-forms/everest-forms.php'           => array(
				'slug' => 'everestforms',
				'name' => 'Everest Forms',
			),
			'weforms/weforms.php'                       => array(
				'slug' => 'weforms',
				'name' => 'weForms',
			),
			'happyforms-upgrade/happyforms-upgrade.php' => array(
				'slug' => 'happyforms',
				'name' => 'Happyforms',
			),
		);

		$already = array();
		foreach ( $existing_plugins as $slug => $plugin ) {
			$already[ strtolower( (string) $slug ) ] = true;
			if ( isset( $plugin['name'] ) ) {
				$already[ strtolower( (string) $plugin['name'] ) ] = true;
			}
		}

		foreach ( $allowlist as $plugin_file => $info ) {
			if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
				continue;
			}
			if ( ! in_array( $plugin_file, $active, true ) ) {
				continue;
			}

			$slug = isset( $info['slug'] ) ? (string) $info['slug'] : '';
			$name = isset( $info['name'] ) ? (string) $info['name'] : '';
			if ( $slug === '' ) {
				continue;
			}
			if ( isset( $existing_plugins[ $slug ] ) || isset( $already[ strtolower( $name ) ] ) ) {
				continue;
			}

			$version = isset( $all_plugins[ $plugin_file ]['Version'] ) && $all_plugins[ $plugin_file ]['Version'] !== ''
				? (string) $all_plugins[ $plugin_file ]['Version']
				: 'Unknown';

			$existing_plugins[ $slug ] = array(
				'name'        => $name !== '' ? $name : $slug,
				'slug'        => $slug,
				'active'      => true,
				'forms_count' => $this->get_forms_count_for_slug( $slug ),
				'version'     => $version,
				'detected'    => true,
			);
		}

		return $existing_plugins;
	}

	private function get_forms_count_for_slug( $slug ) {
		$slug = is_string( $slug ) ? $slug : '';

		switch ( $slug ) {
			case 'fluentform':
				return $this->get_fluentform_count();
			case 'wpforms':
				return $this->get_wpforms_count();
			case 'formidable':
				return $this->get_formidableforms_count();
			case 'contactform7':
				return $this->get_contactform7_count();
			case 'forminator':
				return $this->get_forminator_count();
			case 'ninjaforms':
				return $this->get_ninjaforms_count();
			case 'everestforms':
				return $this->get_everestforms_count();
			default:
				return 0;
		}
	}

	private function get_fluentform_count() {
		global $wpdb;
		$table = $wpdb->prefix . 'fluentform_forms';
		if ( $this->table_exists( $table ) ) {
			$table_sql = esc_sql( $table );
			return (int) $this->db_get_var( 'SELECT COUNT(*) FROM ' . $table_sql );
		}
		return 0;
	}

	private function get_wpforms_count() {
		if ( function_exists( 'wpforms' ) ) {
			$forms = wpforms()->form->get( '', array( 'orderby' => 'ID' ) );
			return is_array( $forms ) ? count( $forms ) : 0;
		}
		return 0;
	}

	private function get_gravityforms_count() {
		if ( class_exists( 'GFAPI' ) ) {
			$forms = GFAPI::get_forms();
			return is_array( $forms ) ? count( $forms ) : 0;
		}
		return 0;
	}

	private function get_formidableforms_count() {
		global $wpdb;
		$forms_table = $wpdb->prefix . 'frm_forms';
		if ( $this->table_exists( $forms_table ) ) {
			$forms_table_sql = esc_sql( $forms_table );
			$cols            = $this->db_get_col( 'SHOW COLUMNS FROM ' . $forms_table_sql, 0 );
			$cols            = is_array( $cols ) ? $cols : array();
			$has_status      = in_array( 'status', $cols, true );
			$has_is_template = in_array( 'is_template', $cols, true );
			$has_parent      = in_array( 'parent_form_id', $cols, true );

			$where = array();
			if ( $has_status ) {
				$where[] = "status NOT IN ('trash','template')";
			}
			if ( $has_is_template ) {
				$where[] = '(is_template IS NULL OR is_template = 0)';
			}
			if ( $has_parent ) {
				$where[] = '(parent_form_id IS NULL OR parent_form_id = 0)';
			}

			$sql = 'SELECT COUNT(*) FROM ' . $forms_table_sql;
			if ( ! empty( $where ) ) {
				$sql .= ' WHERE ' . implode( ' AND ', $where );
			}
			return (int) $this->db_get_var( $sql );
		}
		return 0;
	}

	private function get_contactform7_count() {
		if ( ! post_type_exists( 'wpcf7_contact_form' ) ) {
			return 0;
		}
		$counts  = wp_count_posts( 'wpcf7_contact_form' );
		$publish = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$draft   = isset( $counts->draft ) ? (int) $counts->draft : 0;
		return $publish + $draft;
	}

	private function get_forminator_count() {
		if ( ! post_type_exists( 'forminator_forms' ) ) {
			return 0;
		}
		$counts  = wp_count_posts( 'forminator_forms' );
		$publish = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$draft   = isset( $counts->draft ) ? (int) $counts->draft : 0;
		return $publish + $draft;
	}

	private function get_ninjaforms_count() {
		if ( function_exists( 'Ninja_Forms' ) ) {
			$nf = Ninja_Forms();
			if ( is_object( $nf ) && method_exists( $nf, 'form' ) ) {
				$form_model = $nf->form();
				if ( is_object( $form_model ) && method_exists( $form_model, 'get_forms' ) ) {
					$forms = $form_model->get_forms();
					return is_array( $forms ) ? count( $forms ) : 0;
				}
			}
		}
		return 0;
	}

	private function get_everestforms_count() {
		if ( ! post_type_exists( 'everest_form' ) ) {
			return 0;
		}
		$counts  = wp_count_posts( 'everest_form' );
		$publish = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$draft   = isset( $counts->draft ) ? (int) $counts->draft : 0;
		return $publish + $draft;
	}

	public function get_plugin_forms( $plugin_slug ) {
		switch ( $plugin_slug ) {
			case 'fluentform':
				return $this->get_fluentform_forms();
			case 'wpforms':
				return $this->get_wpforms_forms();
			case 'formidable':
				return $this->get_formidableforms_forms();
			case 'contactform7':
				return $this->get_contactform7_forms();
			case 'forminator':
				return $this->get_forminator_forms();
			case 'ninjaforms':
				return $this->get_ninjaforms_forms();
			case 'everestforms':
				return $this->get_everestforms_forms();
			default:
				return array();
		}
	}

	public function get_entries_preview( $plugin_slug = '', $limit = 25 ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$entries = array();
		$plugins = $this->get_form_plugins_data();

		if ( $plugin_slug ) {
			if ( isset( $plugins[ $plugin_slug ] ) ) {
				$plugins = array( $plugin_slug => $plugins[ $plugin_slug ] );
			} else {
				$plugins = array();
			}
		}

		foreach ( $plugins as $slug => $plugin ) {
			if ( count( $entries ) >= $limit ) {
				break;
			}

			if ( ! isset( $plugin['active'] ) || ! $plugin['active'] ) {
				continue;
			}

			if ( ! in_array( $slug, array( 'fluentform', 'wpforms', 'formidable' ), true ) ) {
				continue;
			}

			$remaining = $limit - count( $entries );
			$chunk     = $this->get_entries_preview_for_plugin( $slug, $remaining );
			if ( ! empty( $chunk ) ) {
				$entries = array_merge( $entries, $chunk );
			}
		}

		usort(
			$entries,
			function ( $a, $b ) {
				$ad = isset( $a['date'] ) ? (string) $a['date'] : '';
				$bd = isset( $b['date'] ) ? (string) $b['date'] : '';
				if ( $ad === $bd ) {
					return 0;
				}
				return $ad < $bd ? 1 : -1;
			}
		);

		if ( count( $entries ) > $limit ) {
			$entries = array_slice( $entries, 0, $limit );
		}

		return $entries;
	}

	private function get_entries_preview_for_plugin( $plugin_slug, $limit ) {
		switch ( $plugin_slug ) {
			case 'fluentform':
				return $this->get_fluentform_entries_preview( $limit );
			case 'wpforms':
				return $this->get_wpforms_entries_preview( $limit );
			case 'formidable':
				return $this->get_formidableforms_entries_preview( $limit );
			default:
				return array();
		}
	}

	private function get_fluentform_forms() {
		global $wpdb;
		$table         = $wpdb->prefix . 'fluentform_forms';
		$entries_table = $wpdb->prefix . 'fluentform_submissions';
		if ( $this->table_exists( $table ) ) {
			$table_sql = esc_sql( $table );
			$forms     = $this->db_get_results( 'SELECT id, title, status, created_at FROM ' . $table_sql . ' ORDER BY id DESC' );
			return array_map(
				function ( $form ) use ( $wpdb, $entries_table ) {
					$entries_count = 0;
					if ( $this->table_exists( $entries_table ) ) {
						$entries_table_sql = esc_sql( $entries_table );
						$entries_count     = (int) $this->db_get_var( 'SELECT COUNT(*) FROM ' . $entries_table_sql . ' WHERE form_id = %d', array( (int) $form->id ) );
					}
					return array(
						'id'      => $form->id,
						'title'   => $form->title,
						'entries' => $entries_count,
						'status'  => isset( $form->status ) ? $form->status : 'published',
						'created' => isset( $form->created_at ) ? $form->created_at : '',
					);
				},
				$forms
			);
		}
		return array();
	}

	private function get_wpforms_forms() {
		global $wpdb;
		if ( function_exists( 'wpforms' ) ) {
			$forms = wpforms()->form->get( '', array( 'orderby' => 'ID' ) );
			if ( is_array( $forms ) ) {
				return array_map(
					function ( $form ) use ( $wpdb ) {
						$entries_count = 0;
						$entries_table = $wpdb->prefix . 'wpforms_entries';
						if ( $this->table_exists( $entries_table ) ) {
							$entries_table_sql = esc_sql( $entries_table );
							$entries_count     = (int) $this->db_get_var( 'SELECT COUNT(*) FROM ' . $entries_table_sql . ' WHERE form_id = %d', array( (int) $form->ID ) );
						}
						return array(
							'id'      => $form->ID,
							'title'   => $form->post_title,
							'entries' => $entries_count,
							'status'  => $form->post_status,
							'created' => $form->post_date,
						);
					},
					$forms
				);
			}
		}
		return array();
	}

	private function get_formidableforms_forms() {
		global $wpdb;

		$forms_table = $wpdb->prefix . 'frm_forms';
		$items_table = $wpdb->prefix . 'frm_items';

		if ( ! $this->table_exists( $forms_table ) ) {
			return array();
		}
		$forms_table_sql = esc_sql( $forms_table );
		$items_table_sql = esc_sql( $items_table );

		$cols            = $this->db_get_col( 'SHOW COLUMNS FROM ' . $forms_table_sql, 0 );
		$cols            = is_array( $cols ) ? $cols : array();
		$has_status      = in_array( 'status', $cols, true );
		$has_is_template = in_array( 'is_template', $cols, true );
		$has_parent      = in_array( 'parent_form_id', $cols, true );

		$where = array();
		if ( $has_status ) {
			$where[] = "status NOT IN ('trash','template')";
		}
		if ( $has_is_template ) {
			$where[] = '(is_template IS NULL OR is_template = 0)';
		}
		if ( $has_parent ) {
			$where[] = '(parent_form_id IS NULL OR parent_form_id = 0)';
		}

		$sql = 'SELECT id, name, status, created_at FROM ' . $forms_table_sql;
		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY id DESC';

		$forms = $this->db_get_results( $sql );
		if ( ! is_array( $forms ) ) {
			return array();
		}

		return array_map(
			function ( $form ) use ( $wpdb, $items_table, $items_table_sql ) {
				$entries_count = 0;
				if ( $this->table_exists( $items_table ) && isset( $form->id ) ) {
					$entries_count = (int) $this->db_get_var( 'SELECT COUNT(*) FROM ' . $items_table_sql . ' WHERE form_id = %d', array( (int) $form->id ) );
				}

				return array(
					'id'      => isset( $form->id ) ? (int) $form->id : 0,
					'title'   => isset( $form->name ) ? (string) $form->name : '',
					'entries' => $entries_count,
					'status'  => isset( $form->status ) ? (string) $form->status : '',
					'created' => isset( $form->created_at ) ? (string) $form->created_at : '',
				);
			},
			$forms
		);
	}

	private function get_contactform7_forms() {
		if ( ! post_type_exists( 'wpcf7_contact_form' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'   => 'wpcf7_contact_form',
				'post_status' => array( 'publish', 'draft' ),
				'numberposts' => -1,
				'orderby'     => 'ID',
				'order'       => 'DESC',
			)
		);

		if ( ! is_array( $posts ) ) {
			return array();
		}

		return array_map(
			function ( $p ) {
				return array(
					'id'      => isset( $p->ID ) ? (int) $p->ID : 0,
					'title'   => isset( $p->post_title ) ? (string) $p->post_title : '',
					'entries' => 0,
					'status'  => isset( $p->post_status ) ? (string) $p->post_status : '',
					'created' => isset( $p->post_date ) ? (string) $p->post_date : '',
				);
			},
			$posts
		);
	}

	private function get_forminator_forms() {
		if ( ! post_type_exists( 'forminator_forms' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'   => 'forminator_forms',
				'post_status' => array( 'publish', 'draft' ),
				'numberposts' => -1,
				'orderby'     => 'ID',
				'order'       => 'DESC',
			)
		);

		if ( ! is_array( $posts ) ) {
			return array();
		}

		return array_map(
			function ( $p ) {
				return array(
					'id'      => isset( $p->ID ) ? (int) $p->ID : 0,
					'title'   => isset( $p->post_title ) ? (string) $p->post_title : '',
					'entries' => 0,
					'status'  => isset( $p->post_status ) ? (string) $p->post_status : '',
					'created' => isset( $p->post_date ) ? (string) $p->post_date : '',
				);
			},
			$posts
		);
	}

	private function get_everestforms_forms() {
		if ( ! post_type_exists( 'everest_form' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'   => 'everest_form',
				'post_status' => array( 'publish', 'draft' ),
				'numberposts' => -1,
				'orderby'     => 'ID',
				'order'       => 'DESC',
			)
		);

		if ( ! is_array( $posts ) ) {
			return array();
		}

		return array_map(
			function ( $p ) {
				return array(
					'id'      => isset( $p->ID ) ? (int) $p->ID : 0,
					'title'   => isset( $p->post_title ) ? (string) $p->post_title : '',
					'entries' => 0,
					'status'  => isset( $p->post_status ) ? (string) $p->post_status : '',
					'created' => isset( $p->post_date ) ? (string) $p->post_date : '',
				);
			},
			$posts
		);
	}

	private function get_ninjaforms_forms() {
		if ( ! function_exists( 'Ninja_Forms' ) ) {
			return array();
		}

		$nf = Ninja_Forms();
		if ( ! is_object( $nf ) || ! method_exists( $nf, 'form' ) ) {
			return array();
		}

		$form_model = $nf->form();
		if ( ! is_object( $form_model ) || ! method_exists( $form_model, 'get_forms' ) ) {
			return array();
		}

		$forms = $form_model->get_forms();
		if ( ! is_array( $forms ) ) {
			return array();
		}

		return array_map(
			function ( $f ) {
				$id      = 0;
				$title   = '';
				$created = '';
				if ( is_object( $f ) ) {
					if ( isset( $f->get_id ) && is_callable( array( $f, 'get_id' ) ) ) {
						$id = (int) $f->get_id();
					} elseif ( isset( $f->id ) ) {
						$id = (int) $f->id;
					}
					if ( isset( $f->get_setting ) && is_callable( array( $f, 'get_setting' ) ) ) {
						$title = (string) $f->get_setting( 'title' );
					}
				} elseif ( is_array( $f ) ) {
					$id    = isset( $f['id'] ) ? (int) $f['id'] : 0;
					$title = isset( $f['title'] ) ? (string) $f['title'] : '';
				}

				return array(
					'id'      => $id,
					'title'   => $title,
					'entries' => 0,
					'status'  => 'active',
					'created' => $created,
				);
			},
			$forms
		);
	}

	private function get_formidableforms_entries( $form_id ) {
		global $wpdb;

		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return array();
		}

		$items_table  = $wpdb->prefix . 'frm_items';
		$forms_table  = $wpdb->prefix . 'frm_forms';
		$metas_table  = $wpdb->prefix . 'frm_item_metas';
		$fields_table = $wpdb->prefix . 'frm_fields';

		if ( ! $this->table_exists( $items_table ) ) {
			return array();
		}
		if ( ! $this->table_exists( $forms_table ) ) {
			return array();
		}

		$items_table_sql  = esc_sql( $items_table );
		$forms_table_sql  = esc_sql( $forms_table );
		$metas_table_sql  = esc_sql( $metas_table );
		$fields_table_sql = esc_sql( $fields_table );

		$items = $this->db_get_results(
			'SELECT i.id, i.form_id, i.created_at, i.updated_at, i.is_draft, f.name AS form_title FROM ' . $items_table_sql . ' i LEFT JOIN ' . $forms_table_sql . ' f ON f.id = i.form_id WHERE i.form_id = %d ORDER BY i.id DESC',
			array( $form_id ),
			60
		);

		if ( ! is_array( $items ) || empty( $items ) ) {
			return array();
		}

		$item_ids = array();
		foreach ( $items as $it ) {
			if ( isset( $it->id ) ) {
				$item_ids[] = (int) $it->id;
			}
		}
		$item_ids = array_values( array_filter( array_unique( $item_ids ) ) );
		if ( empty( $item_ids ) ) {
			return array();
		}

		$field_labels = array();
		if ( $this->table_exists( $fields_table ) ) {
			$rows = $this->db_get_results(
				'SELECT id, name, field_key FROM ' . $fields_table_sql . ' WHERE form_id = %d',
				array( $form_id ),
				60
			);
			if ( is_array( $rows ) ) {
				foreach ( $rows as $r ) {
					$fid   = isset( $r->id ) ? (int) $r->id : 0;
					$label = isset( $r->name ) ? (string) $r->name : '';
					$fkey  = isset( $r->field_key ) ? (string) $r->field_key : '';
					if ( $fid && $label ) {
						$field_labels[ $fid ] = $label;
					}
					if ( $fkey && $label ) {
						$field_labels[ $fkey ] = $label;
					}
				}
			}
		}

		$metas_by_item = array();
		if ( $this->table_exists( $metas_table ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );
			$query        = 'SELECT item_id, field_id, meta_value FROM ' . $metas_table_sql . " WHERE item_id IN ($placeholders)";
			$prepare_args = array_merge( array( $query ), $item_ids );
			$prepared     = call_user_func_array( array( $wpdb, 'prepare' ), $prepare_args );
			$rows         = $this->db_get_results( $prepared );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $r ) {
					$iid = isset( $r->item_id ) ? (int) $r->item_id : 0;
					$fid = isset( $r->field_id ) ? $r->field_id : '';
					$val = isset( $r->meta_value ) ? $r->meta_value : '';
					if ( ! $iid ) {
						continue;
					}
					if ( ! isset( $metas_by_item[ $iid ] ) ) {
						$metas_by_item[ $iid ] = array();
					}
					$key = is_scalar( $fid ) ? (string) $fid : '';
					if ( $key === '' ) {
						continue;
					}
					if ( is_scalar( $val ) ) {
						$metas_by_item[ $iid ][ $key ] = (string) $val;
					}
				}
			}
		}

		return array_map(
			function ( $it ) use ( $metas_by_item, $field_labels ) {
				$item_id = isset( $it->id ) ? (int) $it->id : 0;
				$raw     = isset( $metas_by_item[ $item_id ] ) && is_array( $metas_by_item[ $item_id ] ) ? $metas_by_item[ $item_id ] : array();

				$fields = array();
				foreach ( $raw as $fid => $val ) {
					$label            = isset( $field_labels[ $fid ] ) ? (string) $field_labels[ $fid ] : 'Field ' . (string) $fid;
					$fields[ $label ] = $val;
				}
				$fields = $this->normalize_fields_array( $fields );

				$name  = '';
				$email = '';
				foreach ( $fields as $k => $v ) {
					$kl = strtolower( (string) $k );
					$vs = (string) $v;
					if ( ! $email && ( strpos( $kl, 'email' ) !== false || strpos( strtolower( $vs ), '@' ) !== false ) ) {
						if ( is_email( $vs ) ) {
							$email = $vs;
						}
					}
					if ( ! $name && ( strpos( $kl, 'name' ) !== false || strpos( $kl, 'first' ) !== false ) ) {
						$name = $vs;
					}
				}

				return array(
					'id'      => $item_id,
					'form_id' => isset( $it->form_id ) ? (int) $it->form_id : 0,
					'name'    => $name,
					'email'   => $email,
					'date'    => isset( $it->created_at ) ? (string) $it->created_at : '',
					'status'  => isset( $it->is_draft ) && (int) $it->is_draft === 1 ? 'draft' : 'completed',
				);
			},
			$items
		);
	}

	private function get_gravityforms_forms() {
		if ( class_exists( 'GFAPI' ) ) {
			$forms = GFAPI::get_forms();
			if ( is_array( $forms ) ) {
				return array_map(
					function ( $form ) {
						$entries_count = class_exists( 'GFFormsModel' ) ? GFFormsModel::get_lead_count( $form['id'], '', null, null, null, null ) : 0;
						return array(
							'id'      => $form['id'],
							'title'   => $form['title'],
							'entries' => $entries_count,
							'status'  => isset( $form['is_active'] ) && $form['is_active'] ? 'active' : 'inactive',
							'created' => isset( $form['date_created'] ) ? $form['date_created'] : '',
						);
					},
					$forms
				);
			}
		}
		return array();
	}

	public function get_form_entries( $plugin_slug, $form_id ) {
		switch ( $plugin_slug ) {
			case 'fluentform':
				return $this->get_fluentform_entries( $form_id );
			case 'wpforms':
				return $this->get_wpforms_entries( $form_id );
			case 'formidable':
				return $this->get_formidableforms_entries( $form_id );
			default:
				return array();
		}
	}

	private function get_fluentform_entries( $form_id ) {
		global $wpdb;
		$entries_table    = $wpdb->prefix . 'fluentform_submissions';
		$entry_meta_table = $wpdb->prefix . 'fluentform_entry_details';

		if ( ! $this->table_exists( $entries_table ) ) {
			return array();
		}
		$entries_table_sql = esc_sql( $entries_table );

		$entries = $this->db_get_results(
			'SELECT * FROM ' . $entries_table_sql . ' WHERE form_id = %d ORDER BY id DESC',
			array( (int) $form_id ),
			60
		);

		return array_map(
			function ( $entry ) use ( $wpdb, $entry_meta_table ) {
				$response = $this->safe_maybe_unserialize( $entry->response );
				$name     = '';
				$email    = '';

				if ( is_array( $response ) ) {
					foreach ( $response as $key => $value ) {
						$key_lower = strtolower( $key );
						if ( strpos( $key_lower, 'name' ) !== false && empty( $name ) ) {
							$name = is_array( $value ) ? implode( ' ', $value ) : $value;
						}
						if ( strpos( $key_lower, 'email' ) !== false && empty( $email ) ) {
							$email = $value;
						}
					}
				}

				return array(
					'id'      => $entry->id,
					'form_id' => $entry->form_id,
					'name'    => $name,
					'email'   => $email,
					'date'    => $entry->created_at,
					'status'  => isset( $entry->status ) ? $entry->status : 'unread',
				);
			},
			$entries
		);
	}

	private function get_fluentform_entries_preview( $limit ) {
		global $wpdb;

		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$entries_table = $wpdb->prefix . 'fluentform_submissions';
		$forms_table   = $wpdb->prefix . 'fluentform_forms';
		$details_table = $wpdb->prefix . 'fluentform_entry_details';

		if ( ! $this->table_exists( $entries_table ) ) {
			return array();
		}
		if ( ! $this->table_exists( $forms_table ) ) {
			return array();
		}
		$entries_table_sql = esc_sql( $entries_table );
		$forms_table_sql   = esc_sql( $forms_table );
		$details_table_sql = esc_sql( $details_table );

		$rows = $this->db_get_results(
			'SELECT s.id, s.form_id, s.response, s.status, s.created_at, f.title AS form_title FROM ' . $entries_table_sql . ' s LEFT JOIN ' . $forms_table_sql . ' f ON f.id = s.form_id ORDER BY s.id DESC LIMIT %d',
			array( $limit ),
			60
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$details_by_submission = array();
		if ( $this->table_exists( $details_table ) ) {
			$ids = array();
			foreach ( $rows as $r ) {
				if ( isset( $r->id ) ) {
					$ids[] = (int) $r->id;
				}
			}

			$ids = array_values( array_filter( array_unique( $ids ) ) );
			if ( ! empty( $ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$query        = 'SELECT submission_id, field_name, field_value FROM ' . $details_table_sql . " WHERE submission_id IN ($placeholders)";
				$prepare_args = array_merge( array( $query ), $ids );
				$prepared     = call_user_func_array( array( $wpdb, 'prepare' ), $prepare_args );
				$detail_rows  = $this->db_get_results( $prepared, array(), 60 );

				if ( is_array( $detail_rows ) ) {
					foreach ( $detail_rows as $dr ) {
						$sid = isset( $dr->submission_id ) ? (int) $dr->submission_id : 0;
						$fn  = isset( $dr->field_name ) ? (string) $dr->field_name : '';
						$fv  = isset( $dr->field_value ) ? $dr->field_value : '';
						if ( ! $sid || $fn === '' ) {
							continue;
						}
						if ( ! isset( $details_by_submission[ $sid ] ) ) {
							$details_by_submission[ $sid ] = array();
						}
						if ( is_scalar( $fv ) ) {
							$details_by_submission[ $sid ][ $fn ] = (string) $fv;
						}
					}
				}
			}
		}

		return array_map(
			function ( $row ) use ( $details_by_submission ) {
				$response = $this->safe_maybe_unserialize( $row->response );
				$fields   = $this->normalize_fields_array( $response );

				$sid = isset( $row->id ) ? (int) $row->id : 0;
				if ( $sid && isset( $details_by_submission[ $sid ] ) && is_array( $details_by_submission[ $sid ] ) ) {
					$fields = array_merge( $fields, $this->normalize_fields_array( $details_by_submission[ $sid ] ) );
				}

				$name  = '';
				$email = '';
				foreach ( $fields as $k => $v ) {
					$kl = strtolower( (string) $k );
					$vs = (string) $v;

					if ( ! $email && ( strpos( $kl, 'email' ) !== false || strpos( strtolower( $vs ), '@' ) !== false ) ) {
						if ( is_email( $vs ) ) {
							$email = $vs;
						}
					}

					if ( ! $name ) {
						if ( strpos( $kl, 'name' ) !== false || strpos( $kl, 'first_name' ) !== false || strpos( $kl, 'last_name' ) !== false ) {
							$name = $vs;
						}
					}
				}

				if ( ! $name ) {
					$first = '';
					$last  = '';
					foreach ( $fields as $k => $v ) {
						$kl = strtolower( (string) $k );
						if ( ! $first && strpos( $kl, 'first' ) !== false ) {
							$first = (string) $v;
						}
						if ( ! $last && strpos( $kl, 'last' ) !== false ) {
							$last = (string) $v;
						}
					}
					$combo = trim( $first . ' ' . $last );
					if ( $combo !== '' ) {
						$name = $combo;
					}
				}

				return array(
					'plugin_slug' => 'fluentform',
					'plugin_name' => 'Fluent Forms',
					'form_id'     => (int) $row->form_id,
					'form_title'  => isset( $row->form_title ) ? (string) $row->form_title : '',
					'entry_id'    => (int) $row->id,
					'name'        => $name,
					'email'       => $email,
					'date'        => isset( $row->created_at ) ? (string) $row->created_at : '',
					'status'      => isset( $row->status ) ? (string) $row->status : '',
					'fields'      => $fields,
				);
			},
			$rows
		);
	}

	private function get_wpforms_entries( $form_id ) {
		global $wpdb;
		$entries_table      = $wpdb->prefix . 'wpforms_entries';
		$entry_fields_table = $wpdb->prefix . 'wpforms_entry_fields';

		if ( ! $this->table_exists( $entries_table ) ) {
			return array();
		}

		$entries_table_sql      = esc_sql( $entries_table );
		$entry_fields_table_sql = esc_sql( $entry_fields_table );

		$entries = $this->db_get_results(
			'SELECT * FROM ' . $entries_table_sql . ' WHERE form_id = %d ORDER BY entry_id DESC',
			array( (int) $form_id ),
			60
		);

		return array_map(
			function ( $entry ) use ( $wpdb, $entry_fields_table, $entry_fields_table_sql ) {
				$name  = '';
				$email = '';

				if ( $this->table_exists( $entry_fields_table ) ) {
					$fields = $this->db_get_results(
						'SELECT field_id, value FROM ' . $entry_fields_table_sql . ' WHERE entry_id = %d',
						array( (int) $entry->entry_id ),
						60
					);

					foreach ( $fields as $field ) {
						$value_lower = strtolower( $field->value );
						if ( empty( $name ) && ( strpos( $value_lower, '@' ) === false ) ) {
							$name = $field->value;
						}
						if ( empty( $email ) && strpos( $value_lower, '@' ) !== false ) {
							$email = $field->value;
						}
					}
				}

				return array(
					'id'      => $entry->entry_id,
					'form_id' => $entry->form_id,
					'name'    => $name,
					'email'   => $email,
					'date'    => $entry->date,
					'status'  => isset( $entry->status ) ? $entry->status : 'completed',
				);
			},
			$entries
		);
	}

	private function get_wpforms_entries_preview( $limit ) {
		global $wpdb;

		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$entries_table      = $wpdb->prefix . 'wpforms_entries';
		$entry_fields_table = $wpdb->prefix . 'wpforms_entry_fields';

		if ( ! $this->table_exists( $entries_table ) ) {
			return array();
		}

		$entries_table_sql      = esc_sql( $entries_table );
		$entry_fields_table_sql = esc_sql( $entry_fields_table );

		$rows = $this->db_get_results(
			'SELECT * FROM ' . $entries_table_sql . ' ORDER BY entry_id DESC LIMIT %d',
			array( $limit ),
			60
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			function ( $row ) use ( $wpdb, $entry_fields_table, $entry_fields_table_sql ) {
				$fields = array();
				$parsed = $this->parse_wpforms_entry_fields( $row );
				if ( ! empty( $parsed ) ) {
					$fields = $parsed;
				} elseif ( $this->table_exists( $entry_fields_table ) ) {
					$field_rows = $this->db_get_results(
						'SELECT field_id, value FROM ' . $entry_fields_table_sql . ' WHERE entry_id = %d',
						array( (int) $row->entry_id ),
						60
					);
					if ( is_array( $field_rows ) ) {
						foreach ( $field_rows as $fr ) {
							$key            = 'field_' . (string) $fr->field_id;
							$fields[ $key ] = is_scalar( $fr->value ) ? (string) $fr->value : '';
						}
					}
				}

				$fields = $this->normalize_fields_array( $fields );
				$name   = '';
				$email  = '';
				foreach ( $fields as $k => $v ) {
					$kl = strtolower( (string) $k );
					$vs = (string) $v;
					if ( ! $email && ( strpos( $kl, 'email' ) !== false || strpos( strtolower( $vs ), '@' ) !== false ) ) {
						if ( is_email( $vs ) ) {
							$email = $vs;
						}
					}
					if ( ! $name && ( strpos( $kl, 'name' ) !== false || strpos( $kl, 'first' ) !== false ) ) {
						$name = $vs;
					}
				}

				if ( ! $name ) {
					foreach ( $fields as $k => $v ) {
						$vs = (string) $v;
						if ( $vs !== '' && $vs !== $email && strpos( strtolower( $vs ), '@' ) === false ) {
							$name = $vs;
							break;
						}
					}
				}

				$form_title = '';
				$post       = get_post( (int) $row->form_id );
				if ( $post && isset( $post->post_title ) ) {
					$form_title = (string) $post->post_title;
				}

				return array(
					'plugin_slug' => 'wpforms',
					'plugin_name' => 'WPForms',
					'form_id'     => (int) $row->form_id,
					'form_title'  => $form_title,
					'entry_id'    => (int) $row->entry_id,
					'name'        => $name,
					'email'       => $email,
					'date'        => isset( $row->date ) ? (string) $row->date : '',
					'status'      => isset( $row->status ) ? (string) $row->status : '',
					'fields'      => $fields,
				);
			},
			$rows
		);
	}

	private function parse_wpforms_entry_fields( $row ) {
		$fields = array();
		if ( ! is_object( $row ) ) {
			return $fields;
		}

		if ( ! isset( $row->fields ) || ! is_string( $row->fields ) || $row->fields === '' ) {
			return $fields;
		}

		$data = json_decode( $row->fields, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			return $fields;
		}

		foreach ( $data as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$label = '';
			if ( isset( $field['name'] ) && is_string( $field['name'] ) ) {
				$label = $field['name'];
			} elseif ( isset( $field['label'] ) && is_string( $field['label'] ) ) {
				$label = $field['label'];
			}

			$value = '';
			if ( isset( $field['value'] ) ) {
				if ( is_scalar( $field['value'] ) ) {
					$value = (string) $field['value'];
				} elseif ( is_array( $field['value'] ) ) {
					$flat = array();
					foreach ( $field['value'] as $vv ) {
						if ( is_scalar( $vv ) ) {
							$flat[] = (string) $vv;
						}
					}
					if ( ! empty( $flat ) ) {
						$value = implode( ' ', $flat );
					}
				}
			}

			if ( $label && $value !== '' ) {
				$fields[ $label ] = $value;
			}
		}

		return $fields;
	}

	private function get_formidableforms_entries_preview( $limit ) {
		global $wpdb;

		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$items_table  = $wpdb->prefix . 'frm_items';
		$forms_table  = $wpdb->prefix . 'frm_forms';
		$metas_table  = $wpdb->prefix . 'frm_item_metas';
		$fields_table = $wpdb->prefix . 'frm_fields';

		if ( ! $this->table_exists( $items_table ) ) {
			return array();
		}
		if ( ! $this->table_exists( $forms_table ) ) {
			return array();
		}

		$items_table_sql = esc_sql( $items_table );
		$forms_table_sql = esc_sql( $forms_table );
		$items           = $this->db_get_results( 'SELECT i.id, i.form_id, i.created_at, i.updated_at, i.is_draft, f.name AS form_title FROM ' . $items_table_sql . ' i LEFT JOIN ' . $forms_table_sql . ' f ON f.id = i.form_id ORDER BY i.id DESC LIMIT %d', array( $limit ), 60 );

		if ( ! is_array( $items ) || empty( $items ) ) {
			return array();
		}

		$item_ids = array();
		$form_ids = array();
		foreach ( $items as $it ) {
			if ( isset( $it->id ) ) {
				$item_ids[] = (int) $it->id;
			}
			if ( isset( $it->form_id ) ) {
				$form_ids[] = (int) $it->form_id;
			}
		}

		$item_ids = array_values( array_filter( array_unique( $item_ids ) ) );
		$form_ids = array_values( array_filter( array_unique( $form_ids ) ) );

		$field_labels = array();
		$fields_table_sql = esc_sql( $fields_table );
		if ( $this->table_exists( $fields_table ) && ! empty( $form_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
			$query        = 'SELECT id, name, field_key FROM ' . $fields_table_sql . " WHERE form_id IN ($placeholders)";
			$prepare_args = array_merge( array( $query ), $form_ids );
			$prepared     = call_user_func_array( array( $wpdb, 'prepare' ), $prepare_args );
			$rows         = $this->db_get_results( $prepared, array(), 60 );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $r ) {
					$fid   = isset( $r->id ) ? (int) $r->id : 0;
					$label = isset( $r->name ) ? (string) $r->name : '';
					$fkey  = isset( $r->field_key ) ? (string) $r->field_key : '';
					if ( $fid && $label ) {
						$field_labels[ $fid ] = $label;
					}
					if ( $fkey && $label ) {
						$field_labels[ $fkey ] = $label;
					}
				}
			}
		}

		$metas_by_item = array();
		$metas_table_sql = esc_sql( $metas_table );
		if ( $this->table_exists( $metas_table ) && ! empty( $item_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );
			$query        = 'SELECT item_id, field_id, meta_value FROM ' . $metas_table_sql . " WHERE item_id IN ($placeholders)";
			$prepare_args = array_merge( array( $query ), $item_ids );
			$prepared     = call_user_func_array( array( $wpdb, 'prepare' ), $prepare_args );
			$rows         = $this->db_get_results( $prepared, array(), 60 );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $r ) {
					$iid = isset( $r->item_id ) ? (int) $r->item_id : 0;
					$fid = isset( $r->field_id ) ? $r->field_id : '';
					$val = isset( $r->meta_value ) ? $r->meta_value : '';
					if ( ! $iid ) {
						continue;
					}
					if ( ! isset( $metas_by_item[ $iid ] ) ) {
						$metas_by_item[ $iid ] = array();
					}
					$key = is_scalar( $fid ) ? (string) $fid : '';
					if ( $key === '' ) {
						continue;
					}
					if ( is_scalar( $val ) ) {
						$metas_by_item[ $iid ][ $key ] = (string) $val;
					}
				}
			}
		}

		return array_map(
			function ( $it ) use ( $metas_by_item, $field_labels ) {
				$item_id = isset( $it->id ) ? (int) $it->id : 0;
				$raw     = isset( $metas_by_item[ $item_id ] ) && is_array( $metas_by_item[ $item_id ] ) ? $metas_by_item[ $item_id ] : array();

				$fields = array();
				foreach ( $raw as $fid => $val ) {
					$label            = isset( $field_labels[ $fid ] ) ? (string) $field_labels[ $fid ] : 'Field ' . (string) $fid;
					$fields[ $label ] = $val;
				}
				$fields = $this->normalize_fields_array( $fields );

				$name  = '';
				$email = '';
				foreach ( $fields as $k => $v ) {
					$kl = strtolower( (string) $k );
					$vs = (string) $v;
					if ( ! $email && ( strpos( $kl, 'email' ) !== false || strpos( strtolower( $vs ), '@' ) !== false ) ) {
						if ( is_email( $vs ) ) {
							$email = $vs;
						}
					}
					if ( ! $name && ( strpos( $kl, 'name' ) !== false || strpos( $kl, 'first' ) !== false ) ) {
						$name = $vs;
					}
				}

				if ( ! $name ) {
					$first = '';
					$last  = '';
					foreach ( $fields as $k => $v ) {
						$kl = strtolower( (string) $k );
						if ( ! $first && strpos( $kl, 'first' ) !== false ) {
							$first = (string) $v;
						}
						if ( ! $last && strpos( $kl, 'last' ) !== false ) {
							$last = (string) $v;
						}
					}
					$combo = trim( $first . ' ' . $last );
					if ( $combo !== '' ) {
						$name = $combo;
					}
				}

				return array(
					'plugin_slug' => 'formidable',
					'plugin_name' => 'Formidable Forms',
					'form_id'     => isset( $it->form_id ) ? (int) $it->form_id : 0,
					'form_title'  => isset( $it->form_title ) ? (string) $it->form_title : '',
					'entry_id'    => $item_id,
					'name'        => $name,
					'email'       => $email,
					'date'        => isset( $it->created_at ) ? (string) $it->created_at : '',
					'status'      => isset( $it->is_draft ) && (int) $it->is_draft === 1 ? 'draft' : 'completed',
					'fields'      => $fields,
				);
			},
			$items
		);
	}

	private function get_gravityforms_entries( $form_id ) {
		if ( ! class_exists( 'GFAPI' ) ) {
			return array();
		}

		$search_criteria = array( 'status' => 'active' );
		$entries         = GFAPI::get_entries( $form_id, $search_criteria );

		if ( is_wp_error( $entries ) ) {
			return array();
		}

		$form = GFAPI::get_form( $form_id );

		return array_map(
			function ( $entry ) use ( $form ) {
				$name  = '';
				$email = '';

				if ( is_array( $form ) && isset( $form['fields'] ) ) {
					foreach ( $form['fields'] as $field ) {
						if ( isset( $field->type ) ) {
							if ( $field->type === 'name' && empty( $name ) ) {
								$name = rgar( $entry, $field->id );
							}
							if ( $field->type === 'email' && empty( $email ) ) {
								$email = rgar( $entry, $field->id );
							}
						}
					}
				}

				return array(
					'id'      => $entry['id'],
					'form_id' => $entry['form_id'],
					'name'    => $name,
					'email'   => $email,
					'date'    => $entry['date_created'],
					'status'  => $entry['status'],
				);
			},
			$entries
		);
	}

	private function get_gravityforms_entries_preview( $limit ) {
		if ( ! class_exists( 'GFAPI' ) ) {
			return array();
		}

		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$forms = GFAPI::get_forms();
		if ( ! is_array( $forms ) || empty( $forms ) ) {
			return array();
		}

		$out = array();
		foreach ( $forms as $form ) {
			if ( count( $out ) >= $limit ) {
				break;
			}

			$form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;
			if ( ! $form_id ) {
				continue;
			}

			$remaining       = $limit - count( $out );
			$search_criteria = array( 'status' => 'active' );
			$paging          = array(
				'offset'    => 0,
				'page_size' => $remaining,
			);
			$entries         = GFAPI::get_entries( $form_id, $search_criteria, null, $paging );
			if ( is_wp_error( $entries ) || ! is_array( $entries ) ) {
				continue;
			}

			foreach ( $entries as $entry ) {
				if ( count( $out ) >= $limit ) {
					break;
				}

				$fields = $this->normalize_gravityforms_entry_fields( $form, $entry );
				$name   = '';
				$email  = '';
				foreach ( $fields as $k => $v ) {
					$kl = strtolower( (string) $k );
					if ( ! $name && strpos( $kl, 'name' ) !== false ) {
						$name = $v;
					}
					if ( ! $email && strpos( $kl, 'email' ) !== false ) {
						$email = $v;
					}
				}

				$out[] = array(
					'plugin_slug' => 'gravityforms',
					'plugin_name' => 'Gravity Forms',
					'form_id'     => isset( $entry['form_id'] ) ? (int) $entry['form_id'] : $form_id,
					'form_title'  => isset( $form['title'] ) ? (string) $form['title'] : '',
					'entry_id'    => isset( $entry['id'] ) ? (int) $entry['id'] : 0,
					'name'        => $name,
					'email'       => $email,
					'date'        => isset( $entry['date_created'] ) ? (string) $entry['date_created'] : '',
					'status'      => isset( $entry['status'] ) ? (string) $entry['status'] : '',
					'fields'      => $fields,
				);
			}
		}

		return $out;
	}

	private function safe_maybe_unserialize( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$value = trim( $value );
		if ( '' === $value ) {
			return $value;
		}

		if ( ! is_serialized( $value ) ) {
			return $value;
		}

		// Disallow objects for safety when reading third-party plugin data.
		$unserialized = @unserialize( $value, array( 'allowed_classes' => false ) );
		if ( false === $unserialized && 'b:0;' !== $value ) {
			return $value;
		}

		return $unserialized;
	}

	private function normalize_fields_array( $raw_fields ) {
		$out = array();
		if ( is_string( $raw_fields ) ) {
			$maybe_json = json_decode( $raw_fields, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $maybe_json ) ) {
				$raw_fields = $maybe_json;
			}
		}
		if ( is_array( $raw_fields ) ) {
			foreach ( $raw_fields as $k => $v ) {
				if ( $v === null ) {
					continue;
				}
				if ( is_scalar( $v ) ) {
					$out[ (string) $k ] = (string) $v;
				} elseif ( is_array( $v ) ) {
					$flat = array();
					foreach ( $v as $vk => $vv ) {
						if ( is_scalar( $vv ) ) {
							$flat[] = (string) $vv;
						}
					}
					if ( ! empty( $flat ) ) {
						$out[ (string) $k ] = implode( ' ', $flat );
					}
				}
			}
		}

		$out = array_filter(
			$out,
			function ( $v ) {
				return $v !== '';
			}
		);

		return $out;
	}

	private function normalize_gravityforms_entry_fields( $form, $entry ) {
		$out = array();
		if ( ! is_array( $form ) || ! isset( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $out;
		}

		foreach ( $form['fields'] as $field ) {
			if ( ! isset( $field->id ) ) {
				continue;
			}

			$label = isset( $field->label ) ? (string) $field->label : 'Field ' . (string) $field->id;
			$value = '';

			if ( isset( $field->inputs ) && is_array( $field->inputs ) ) {
				$parts = array();
				foreach ( $field->inputs as $input ) {
					if ( ! isset( $input['id'] ) ) {
						continue;
					}
					$iv = rgar( $entry, $input['id'] );
					if ( is_scalar( $iv ) && (string) $iv !== '' ) {
						$parts[] = (string) $iv;
					}
				}
				if ( ! empty( $parts ) ) {
					$value = implode( ' ', $parts );
				}
			} else {
				$v = rgar( $entry, $field->id );
				if ( is_scalar( $v ) ) {
					$value = (string) $v;
				}
			}

			if ( $value !== '' ) {
				$out[ $label ] = $value;
			}
		}

		return $out;
	}
}
