<?php
/**
 * REST API Controller for My Powerly Cards
 *
 * @package w91099ch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_My_Powerly_Card_Rest_Controller {

	/**
	 * REST API namespace
	 */
	const REST_NAMESPACE = 'w91099ch/v1';

	const REST_NAMESPACE_LEGACY = 'w9-1099-chaser/v1';

	const OPTION_API_KEY = 'w91099ch_my_powerly_api_key';

	const FILTER_EXPECTED_TOKEN = 'w91099ch_my_powerly_rest_expected_token';

	/**
	 * @var w91099ch_Core
	 */
	private $core;

	public function __construct( $core ) {
		$this->core = $core;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'fix_rest_response' ), 10, 4 );
	}

	public function fix_rest_response( $served, $result, $request, $server ) {
		$route = $request->get_route();

		// Only apply to our endpoints
		if (
			strpos( $route, '/' . self::REST_NAMESPACE . '/card-' ) !== 0
			&& strpos( $route, '/' . self::REST_NAMESPACE_LEGACY . '/card-' ) !== 0
		) {
			return $served;
		}

		// Ensure proper JSON encoding
		if ( $result instanceof WP_REST_Response ) {
			$result->header( 'Content-Type', 'application/json; charset=utf-8' );
		}

		return $served;
	}

	public function register_routes() {
		$namespaces = array( self::REST_NAMESPACE, self::REST_NAMESPACE_LEGACY );

		foreach ( $namespaces as $namespace ) {
			register_rest_route(
				$namespace,
				'/card-1',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_card_1' ),
					'permission_callback' => array( $this, 'permission_check' ),
				)
			);

			register_rest_route(
				$namespace,
				'/card-2',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_card_2' ),
					'permission_callback' => array( $this, 'permission_check' ),
				)
			);

			register_rest_route(
				$namespace,
				'/card-3',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_card_3' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'plugin_slug' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'limit'       => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 50,
							'sanitize_callback' => 'absint',
						),
						'offset'      => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/card-4',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_card_4' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'limit'  => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 50,
							'sanitize_callback' => 'absint',
						),
						'offset' => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				)
			);
		}
	}

	public function permission_check( WP_REST_Request $request ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$provided = $this->get_provided_token( $request );
		if ( $provided === '' ) {
			return new WP_Error(
				'w91099ch_my_powerly_auth_missing',
				esc_html__( 'Missing API token', 'w9-1099-chaser' ),
				array(
					'status' => 401,
				)
			);
		}

		$expected = $this->get_expected_token();
		if ( $expected === '' ) {
			return new WP_Error(
				'w91099ch_my_powerly_auth_not_configured',
				esc_html__( 'API token is not configured on this site', 'w9-1099-chaser' ),
				array(
					'status' => 403,
				)
			);
		}

		if ( ! hash_equals( $expected, $provided ) ) {
			return new WP_Error(
				'w91099ch_my_powerly_auth_invalid',
				esc_html__( 'Invalid API token', 'w9-1099-chaser' ),
				array(
					'status' => 403,
				)
			);
		}

		return true;
	}

	private function get_expected_token() {
		$token = '';

		if ( $this->core && is_object( $this->core ) && method_exists( $this->core, 'get_credentials' ) ) {
			$credentials = $this->core->get_credentials();
			if ( is_array( $credentials ) && ! empty( $credentials['api_key'] ) ) {
				$token = (string) $credentials['api_key'];
			}
		}

		if ( $token === '' ) {
			$token = (string) get_option( self::OPTION_API_KEY, '' );
		}

		$token = trim( $token );

		/**
		 * Allows overriding the expected token, e.g. from environment variables.
		 */
		$token = (string) apply_filters( 'w91099ch_my_powerly_rest_expected_token', $token );

		// Backward compatibility.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$token = (string) apply_filters( 'my_powerly_rest_expected_token', $token );

		return trim( $token );
	}

	private function get_provided_token( WP_REST_Request $request ) {
		$headers = $request->get_headers();

		$auth_header = '';
		if ( isset( $headers['authorization'][0] ) ) {
			$auth_header = (string) $headers['authorization'][0];
		}

		if ( $auth_header !== '' && stripos( $auth_header, 'bearer ' ) === 0 ) {
			return trim( substr( $auth_header, 7 ) );
		}

		if ( isset( $headers['x_my_powerly_api_key'][0] ) ) {
			return trim( (string) $headers['x_my_powerly_api_key'][0] );
		}

		if ( isset( $headers['x-my-powerly-api-key'][0] ) ) {
			return trim( (string) $headers['x-my-powerly-api-key'][0] );
		}

		// Preferred (prefixed) header names.
		if ( isset( $headers['x_w91099ch_my_powerly_api_key'][0] ) ) {
			return trim( (string) $headers['x_w91099ch_my_powerly_api_key'][0] );
		}

		if ( isset( $headers['x-1099automation-ch-my-powerly-api-key'][0] ) ) {
			return trim( (string) $headers['x-1099automation-ch-my-powerly-api-key'][0] );
		}

		return '';
	}

	private function format_sync_time( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return array(
				'timestamp' => 0,
				'formatted' => 'Never',
			);
		}

		return array(
			'timestamp' => $timestamp,
			'formatted' => gmdate( 'Y-m-d H:i:s', $timestamp ),
		);
	}

	public function handle_card_1( WP_REST_Request $request ) {
		$is_admin = current_user_can( 'manage_options' );
		$profile_last_sync = (int) get_option( 'w91099ch_profile_last_sync', 0 );

		$admin_email = sanitize_email( (string) get_option( 'admin_email', '' ) );

		// Ensure admin_email is not empty
		if ( empty( $admin_email ) ) {
			$admin_email = sanitize_email( (string) get_bloginfo( 'admin_email' ) );
		}

		// Get workspace from credentials or option
		$workspace = '';
		if ( $this->core && method_exists( $this->core, 'get_credentials' ) ) {
			$creds = $this->core->get_credentials();
			if ( is_array( $creds ) && ! empty( $creds['workspace'] ) ) {
				$workspace = sanitize_text_field( (string) $creds['workspace'] );
			}
		}
		if ( empty( $workspace ) ) {
			$workspace = sanitize_text_field( (string) get_option( 'w91099ch_workspace', '' ) );
		}

		// Fallback to blog name if workspace is empty
		if ( empty( $workspace ) ) {
			$workspace = sanitize_text_field( (string) get_bloginfo( 'name' ) );
		}

		$response = rest_ensure_response(
			array(
				'card'        => 'card-1',
				'label'       => esc_html__( 'User Profile', 'w9-1099-chaser' ),
				'admin_email' => $is_admin ? ( $admin_email ?: 'no-email@example.com' ) : '',
				'workspace'   => $workspace ?: 'default-workspace',
				'last_sync'   => $this->format_sync_time( $profile_last_sync ),
			)
		);
		$response->set_status( 200 );
		$response->header( 'Content-Type', 'application/json; charset=utf-8' );
		return $response;
	}

	public function handle_card_2( WP_REST_Request $request ) {
		$plugin_last_sync = (int) get_option( 'w91099ch_plugin_last_sync', 0 );

		$affiliate_manager = class_exists( 'w91099ch_Affiliate_Manager' ) ? new w91099ch_Affiliate_Manager() : null;

		$detected_plugins = array();
		$total_affiliates = 0;

		if ( $affiliate_manager && method_exists( $affiliate_manager, 'detect_affiliate_plugins' ) ) {
			$detected_plugins = (array) $affiliate_manager->detect_affiliate_plugins();
		}
		if ( $affiliate_manager && method_exists( $affiliate_manager, 'get_total_affiliates_count' ) ) {
			$total_affiliates = (int) $affiliate_manager->get_total_affiliates_count();
		}

		$plugins_list = array();
		foreach ( $detected_plugins as $slug => $plugin ) {
			if ( ! is_array( $plugin ) ) {
				continue;
			}

			$plugins_list[] = array(
				'slug'            => (string) $slug,
				'name'            => (string) ( $plugin['name'] ?? '' ),
				'version'         => (string) ( $plugin['version'] ?? '' ),
				'affiliate_count' => (int) ( $plugin['affiliate_count'] ?? 0 ),
				'status'          => 'ACTIVE',
			);
		}

		$response = rest_ensure_response(
			array(
				'card'             => 'card-2',
				'label'            => esc_html__( 'Affiliate Plugins', 'w9-1099-chaser' ),
				'last_sync'        => $this->format_sync_time( $plugin_last_sync ),
				'plugins_count'    => count( $plugins_list ),
				'total_affiliates' => $total_affiliates,
				'plugins'          => $plugins_list,
				'stats'            => array(
					'plugins_count'    => count( $plugins_list ),
					'total_affiliates' => $total_affiliates,
				),
			)
		);
		$response->set_status( 200 );
		$response->header( 'Content-Type', 'application/json; charset=utf-8' );
		return $response;
	}

	public function handle_card_3( WP_REST_Request $request ) {
		$affiliates_last_sync = (int) get_option( 'w91099ch_affiliates_last_sync', 0 );

		$plugin_slug = sanitize_text_field( (string) $request->get_param( 'plugin_slug' ) );
		$plugin_slug = trim( $plugin_slug );

		$limit  = (int) $request->get_param( 'limit' );
		$offset = (int) $request->get_param( 'offset' );

		if ( $limit <= 0 ) {
			$limit = 50;
		}
		if ( $limit > 200 ) {
			$limit = 200;
		}
		if ( $offset < 0 ) {
			$offset = 0;
		}

		$affiliate_manager = class_exists( 'w91099ch_Affiliate_Manager' ) ? new w91099ch_Affiliate_Manager() : null;

		$total_affiliates = 0;
		if ( $affiliate_manager && method_exists( $affiliate_manager, 'get_total_affiliates_count' ) ) {
			$total_affiliates = (int) $affiliate_manager->get_total_affiliates_count();
		}

		$preview = array(
			'items'       => array(),
			'total_count' => 0,
		);
		$summary = array();

		if ( $affiliate_manager && method_exists( $affiliate_manager, 'get_affiliates_for_display' ) ) {
			$result = $affiliate_manager->get_affiliates_for_display( $plugin_slug, $limit, $offset );
			if ( is_array( $result ) ) {
				$preview['items']       = isset( $result['affiliates'] ) && is_array( $result['affiliates'] ) ? $result['affiliates'] : array();
				$preview['total_count'] = (int) ( $result['total_count'] ?? 0 );
			}
		}

		if ( $affiliate_manager && method_exists( $affiliate_manager, 'get_payout_summary' ) ) {
			$sum = $affiliate_manager->get_payout_summary( $plugin_slug );
			if ( is_array( $sum ) ) {
				$summary = $sum;
			}
		}

		$response = rest_ensure_response(
			array(
				'card'               => 'card-3',
				'label'              => esc_html__( 'Affiliates/Vendors Data', 'w9-1099-chaser' ),
				'last_sync'          => $this->format_sync_time( $affiliates_last_sync ),
				'total_affiliates'   => $total_affiliates,
				'filters'            => array(
					'plugin_slug' => $plugin_slug,
					'limit'       => $limit,
					'offset'      => $offset,
				),
				'payout_summary'     => $summary,
				'affiliates_preview' => $preview,
				'stats'              => array(
					'total_affiliates' => $total_affiliates,
					'plugin_slug'      => $plugin_slug,
				),
			)
		);
		$response->set_status( 200 );
		$response->header( 'Content-Type', 'application/json; charset=utf-8' );
		return $response;
	}

	public function handle_card_4( WP_REST_Request $request ) {
		$is_admin = current_user_can( 'manage_options' );
		$team_last_sync = (int) get_option( 'w91099ch_team_last_sync', 0 );

		$limit  = (int) $request->get_param( 'limit' );
		$offset = (int) $request->get_param( 'offset' );

		if ( $limit <= 0 ) {
			$limit = 50;
		}
		if ( $limit > 200 ) {
			$limit = 200;
		}
		if ( $offset < 0 ) {
			$offset = 0;
		}

		// Only include specific roles for display/sync
		$allowed_roles = array( 'shop_manager', 'contributor', 'author', 'editor', 'administrator' );

		$users_count = count_users();

		// Total users limited to allowed roles
		$total_users = 0;
		if ( isset( $users_count['avail_roles'] ) && is_array( $users_count['avail_roles'] ) ) {
			foreach ( $allowed_roles as $role_slug ) {
				if ( isset( $users_count['avail_roles'][ $role_slug ] ) ) {
					$total_users += (int) $users_count['avail_roles'][ $role_slug ];
				}
			}
		}

		$users = get_users(
			array(
				'number'   => $limit,
				'offset'   => $offset,
				'orderby'  => 'registered',
				'order'    => 'DESC',
				'role__in' => $allowed_roles,
			)
		);

		$formatted_users = array(
			'items'       => array(),
			'total_count' => $total_users,
		);
		foreach ( $users as $user ) {
			if ( ! is_object( $user ) ) {
				continue;
			}

			$item = array(
				'display_name' => (string) $user->display_name,
				'role'         => ! empty( $user->roles[0] ) ? (string) $user->roles[0] : 'subscriber',
			);
			if ( $is_admin ) {
				$item['username'] = (string) $user->user_login;
				$item['email']    = (string) $user->user_email;
			}
			$formatted_users['items'][] = $item;
		}

		$roles = array();
		if ( ! function_exists( 'wp_roles' ) ) {
			require_once ABSPATH . 'wp-includes/class-wp-roles.php';
		}
		if ( function_exists( 'wp_roles' ) && wp_roles() && isset( wp_roles()->roles ) ) {
			foreach ( wp_roles()->roles as $role_slug => $role_info ) {
				if ( ! in_array( $role_slug, $allowed_roles, true ) ) {
					continue;
				}
				$roles[] = array(
					'slug'  => $role_slug,
					'name'  => $role_info['name'],
					'count' => isset( $users_count['avail_roles'][ $role_slug ] ) ? (int) $users_count['avail_roles'][ $role_slug ] : 0,
				);
			}
		}

		$response = rest_ensure_response(
			array(
				'card'        => 'card-4',
				'label'       => esc_html__( 'Team / Users', 'w9-1099-chaser' ),
				'last_sync'   => $this->format_sync_time( $team_last_sync ),
				'total_users' => $total_users,
				'filters'     => array(
					'limit'  => $limit,
					'offset' => $offset,
				),
				'users'       => $formatted_users,
				'roles'       => $roles,
				'stats'       => array(
					'total_users' => $total_users,
					'roles'       => $roles,
				),
			)
		);
		$response->set_status( 200 );
		$response->header( 'Content-Type', 'application/json; charset=utf-8' );
		return $response;
	}
}

if ( ! class_exists( 'My_Powerly_Card_Rest_Controller' ) && class_exists( 'w91099ch_My_Powerly_Card_Rest_Controller' ) ) {
	class_alias( 'w91099ch_My_Powerly_Card_Rest_Controller', 'My_Powerly_Card_Rest_Controller' );
}
