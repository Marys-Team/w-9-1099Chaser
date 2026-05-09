<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_Freelancer_Contractor_Plugin_Detector {

	public function get_freelancer_contractor_plugins_data() {
		// Clear any existing cache to ensure fresh results
		wp_cache_delete('w91099ch_freelancer_contractor_plugins', 'w91099ch');
		
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$active      = (array) get_option( 'active_plugins', array() );

		$predefined = array(
			array(
				'slug'         => 'projectopia',
				'name'         => 'Projectopia',
				'plugin_files' => array(
					'projectopia/projectopia.php',
					'projectopia-core/projectopia-core.php',
					'projectopia/projectopia-core.php',
					'cqpim/cqpim.php',
				),
				'name_regex'   => '/\bprojectopia\b|\bprojectopia core\b|\bcqpim\b/i',
			),
			array(
				'slug'         => 'hivepress',
				'name'         => 'HivePress',
				'plugin_files' => array(
					'hivepress/hivepress.php',
				),
				'name_regex'   => '/\bhivepress\b/i',
			),
			array(
				'slug'         => 'wpclient',
				'name'         => 'WP-Client',
				'plugin_files' => array(
					'wp-client/wp-client.php',
					'wp-client/wp-client-lite.php',
					'wp-client-client-portal/wp-client-client-portal.php',
				),
				'name_regex'   => '/\bwp[- ]?client\b/i',
			),
			array(
				'slug'         => 'mavenir',
				'name'         => 'Mavenir',
				'plugin_files' => array(),
				'name_regex'   => '/\bmavenir\b/i',
			),
			array(
				'slug'         => 'zephyrprojectmanager',
				'name'         => 'Zephyr Project Manager',
				'plugin_files' => array(
					'zephyr-project-manager/zephyr-project-manager.php',
				),
				'name_regex'   => '/\bzephyr\b.*\bproject\b/i',
			),
			array(
				'slug'         => 'simplejobboard',
				'name'         => 'Simple Job Board',
				'plugin_files' => array(
					'simple-job-board/simple-job-board.php',
				),
				'name_regex'   => '/\bsimple\s+job\s+board\b/i',
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

	public function get_contractors_preview( $plugin_slug, $limit ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$plugin_slug = is_string( $plugin_slug ) ? $plugin_slug : '';

		if ( $plugin_slug === '' ) {
			return $this->get_all_preview( $limit );
		}

		return $this->get_preview_for_plugin( $plugin_slug, $limit );
	}

	private function get_all_preview( $limit ) {
		$plugins = $this->get_freelancer_contractor_plugins_data();
		if ( ! is_array( $plugins ) || empty( $plugins ) ) {
			return array();
		}

		$rows = array();
		foreach ( $plugins as $slug => $plugin ) {
			$slug = is_string( $slug ) ? $slug : '';
			if ( $slug === '' ) {
				continue;
			}

			$sub = $this->get_preview_for_plugin( $slug, $limit );
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

	private function get_preview_for_plugin( $plugin_slug, $limit ) {
		$plugin_slug = is_string( $plugin_slug ) ? $plugin_slug : '';
		$plugins     = $this->get_freelancer_contractor_plugins_data();
		$source_name = ( is_array( $plugins ) && isset( $plugins[ $plugin_slug ]['name'] ) ) ? (string) $plugins[ $plugin_slug ]['name'] : $this->pretty_plugin_name_from_slug( $plugin_slug );

		// Debug: Log what plugin slug we're looking for
		error_log('W91099ch Freelancer Debug: Looking for plugin slug: ' . $plugin_slug);
		error_log('W91099ch Freelancer Debug: Available plugins: ' . print_r(array_keys($plugins), true));
		error_log('W91099ch Freelancer Debug: Source name: ' . $source_name);

		return $this->get_wp_users_by_related_roles( $limit, $source_name, $plugin_slug );
	}

	private function get_wp_users_by_related_roles( $limit, $source_name, $plugin_slug ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 25;
		}

		$plugin_slug = is_string( $plugin_slug ) ? $plugin_slug : '';
		$source_name = is_string( $source_name ) ? $source_name : '';

		$roles_obj = function_exists( 'wp_roles' ) ? wp_roles() : null;
		$all_roles = ( $roles_obj && isset( $roles_obj->roles ) && is_array( $roles_obj->roles ) ) ? $roles_obj->roles : array();

		$known_role_map = array(
			'projectopia'          => array( 'cqpim_client', 'cqpim_team_member', 'cqpim_admin' ),
			'hivepress'            => array( 'hp_vendor', 'hp_user', 'vendor', 'customer' ),
			'wpclient'             => array( 'wpc_client', 'wpc_client_staff', 'wpc_manager', 'client' ),
			'zephyrprojectmanager' => array( 'zephyr_user', 'zephyr_admin', 'project_manager' ),
			'simplejobboard'       => array( 'employer', 'job_manager', 'candidate' ),
		);

		$kw         = array( 'contractor', 'freelancer', 'vendor', 'provider', 'service', 'client', 'employer', 'staff', 'agency', 'worker', 'supplier' );
		$slug_token = strtolower( preg_replace( '/[^a-z0-9]+/i', '', $plugin_slug ) );

		$role__in = array();

		if ( $plugin_slug !== '' && isset( $known_role_map[ $plugin_slug ] ) && is_array( $known_role_map[ $plugin_slug ] ) ) {
			foreach ( $known_role_map[ $plugin_slug ] as $rk ) {
				if ( is_string( $rk ) && $rk !== '' ) {
					$role__in[] = $rk;
				}
			}
		}

		foreach ( $all_roles as $role_key => $role_info ) {
			$role_key  = is_string( $role_key ) ? $role_key : '';
			$role_name = ( is_array( $role_info ) && isset( $role_info['name'] ) ) ? (string) $role_info['name'] : '';

			if ( $role_key === '' ) {
				continue;
			}

			$hay      = strtolower( $role_key . ' ' . $role_name );
			$is_match = false;

			foreach ( $kw as $k ) {
				if ( strpos( $hay, $k ) !== false ) {
					$is_match = true;
					break;
				}
			}

			if ( ! $is_match && $slug_token !== '' ) {
				if ( strpos( preg_replace( '/[^a-z0-9]+/i', '', $hay ), $slug_token ) !== false ) {
					$is_match = true;
				}
			}

			if ( $is_match ) {
				$role__in[] = $role_key;
			}
		}

		$role__in = array_values( array_unique( array_filter( $role__in ) ) );
		
		error_log('W91099ch Freelancer Debug: Final role__in array: ' . print_r($role__in, true));
		
		if ( empty( $role__in ) ) {
			return array();
		} else {
			error_log('W91099ch Freelancer Debug: Getting users with specific roles');
			$users = get_users(
				array(
					'number'   => $limit,
					'orderby'  => 'registered',
					'order'    => 'DESC',
					'role__in' => $role__in,
					'fields'   => array( 'ID', 'display_name', 'user_email', 'user_registered', 'user_status' ),
				)
			);
		}

		if ( ! is_array( $users ) || empty( $users ) ) {
			return array();
		}

		$rows = array();
		foreach ( $users as $u ) {
			$uid = isset( $u->ID ) ? (int) $u->ID : 0;
			if ( $uid <= 0 ) {
				continue;
			}

			$userdata = get_userdata( $uid );
			$role     = '';
			if ( $userdata && isset( $userdata->roles ) && is_array( $userdata->roles ) && ! empty( $userdata->roles ) ) {
				$role = (string) reset( $userdata->roles );
			}

			$role_type = $role;
			if ( $role !== '' && isset( $all_roles[ $role ]['name'] ) ) {
				$role_type = (string) $all_roles[ $role ]['name'];
			}

			$status = ( isset( $u->user_status ) && (int) $u->user_status !== 0 ) ? 'Inactive' : 'Active';

			$rows[] = array(
				'name'          => isset( $u->display_name ) ? (string) $u->display_name : '',
				'email'         => isset( $u->user_email ) ? (string) $u->user_email : '',
				'role_type'     => $role_type !== '' ? $role_type : 'N/A',
				'status'        => $status,
				'source_plugin' => $source_name !== '' ? $source_name : ( $plugin_slug !== '' ? $plugin_slug : '' ),
				'created'       => isset( $u->user_registered ) ? (string) $u->user_registered : '',
			);
		}

		return $this->dedupe_rows( $rows );
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

	private function detect_generic_plugins( $existing, $all_plugins, $active ) {
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$keywords = array(
			'freelancer',
			'freelancers',
			'contractor',
			'contractors',
			'service provider',
			'service providers',
			'vendor',
			'vendors',
			'client portal',
			'project manager',
			'project management',
			'job board',
			'hiring',
			'hire',
			'staffing',
			'marketplace',
		);

		$used_slugs = array();
		foreach ( $existing as $k => $v ) {
			$used_slugs[ (string) $k ] = true;
		}

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$name = isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : '';
			$desc = isset( $plugin_data['Description'] ) ? (string) $plugin_data['Description'] : '';

			// Immediate skip for compatibility checker plugins
			$name_lower = strtolower( $name );
			if ( strpos( $name_lower, 'compatibility' ) !== false || strpos( $name_lower, 'checker' ) !== false ) {
				continue;
			}

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
			'jetpack',
			'woocommerce',
			'elementor',
			'wordfence',
			'litespeed-cache',
			'wp-rocket',
			'w3-total-cache',
			'rank-math',
			'yoast',
			'akismet',
			'updraftplus',
			'contact-form-7',
			'wpforms',
			'fluentform',
			'formidable',
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

		// Additional aggressive blocking for plugin compatibility checker
		if ( strpos( $hay, 'plugin compatibility checker' ) !== false ) {
			return true;
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
			'wp-client'              => 'wpclient',
			'simple-job-board'       => 'simplejobboard',
			'zephyr-project-manager' => 'zephyrprojectmanager',
			'projectopia-core'       => 'projectopia',
			'cqpim'                  => 'projectopia',
		);

		return isset( $aliases[ $slug ] ) ? $aliases[ $slug ] : $slug;
	}

	private function pretty_plugin_name_from_slug( $slug ) {
		$slug = is_string( $slug ) ? $slug : '';
		if ( $slug === '' ) {
			return '';
		}

		$slug = str_replace( array( '-', '_' ), ' ', $slug );
		$slug = preg_replace( '/\s+/', ' ', $slug );
		$slug = trim( (string) $slug );
		return ucwords( $slug );
	}
}
