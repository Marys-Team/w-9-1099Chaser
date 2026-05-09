<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore PEAR.NamingConventions.ValidClassName.StartWithCapital
class w91099ch_Widget_Manager {

	/**
	 * Per-request render state to prevent duplicate widget output.
	 *
	 * @var array<string, mixed>
	 */
	private static $render_state = array(
		'rendered' => false,
		'source'   => '',
	);

	const OPTION_WIDGET_CODE    = 'w91099ch_widget_code';
	const OPTION_DISPLAY_MODE   = 'w91099ch_widget_display_mode';
	const OPTION_SELECTED_PAGES = 'w91099ch_widget_selected_pages';
	const OPTION_POSITION       = 'w91099ch_widget_position';

	const NONCE_SAVE_ACTION = 'w91099ch_widget_save';
	const NONCE_SAVE_NAME   = 'w91099ch_widget_nonce';

	const AJAX_ACTION_GENERATE_CODE = 'w91099ch_generate_widget_code';
	const AJAX_NONCE_ACTION         = 'w91099ch_generate_widget_code_nonce';
	const AJAX_NONCE_NAME           = 'security';

	const SHORTCODE = 'w91099ch_widget';

	const ADMIN_PARENT_SLUG      = 'w91099ch';
	const ADMIN_PAGE_SLUG        = 'w91099ch-widget';
	const ADMIN_PAGE_SLUG_LEGACY = 'w9-1099-chaser-widget';

	public function init() {
		add_action( 'wp', array( __CLASS__, 'reset_widget_render_state' ), 1 );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'auto_embed' ) );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_GENERATE_CODE, array( $this, 'ajax_generate_widget_code' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );

		add_option( self::OPTION_POSITION, 'bottom-right' );
		add_option( self::OPTION_DISPLAY_MODE, 'shortcode' );
		add_option( self::OPTION_SELECTED_PAGES, array() );
	}

	/**
	 * Reset widget render state for current request.
	 */
	public static function reset_widget_render_state() {
		self::$render_state = array(
			'rendered' => false,
			'source'   => '',
		);
	}

	/**
	 * Check whether widget already rendered by any method.
	 *
	 * @return bool
	 */
	public static function is_widget_rendered() {
		return ! empty( self::$render_state['rendered'] );
	}

	/**
	 * Mark widget as rendered by source.
	 *
	 * @param string $source Render source.
	 */
	public static function mark_widget_rendered( $source ) {
		self::$render_state = array(
			'rendered' => true,
			'source'   => sanitize_key( (string) $source ),
		);
	}

	/**
	 * Get render source.
	 *
	 * @return string
	 */
	public static function get_widget_render_source() {
		return isset( self::$render_state['source'] ) ? (string) self::$render_state['source'] : '';
	}

	public function add_admin_menu() {
		add_submenu_page(
			self::ADMIN_PARENT_SLUG,
			'Widget',
			'Widget',
			'manage_options',
			self::ADMIN_PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			self::ADMIN_PARENT_SLUG,
			'Widget',
			'Widget (Legacy)',
			'manage_options',
			self::ADMIN_PAGE_SLUG_LEGACY,
			array( $this, 'render_admin_page' )
		);
	}

	public function admin_scripts( $hook ) {
		if ( strpos( (string) $hook, self::ADMIN_PAGE_SLUG ) === false && strpos( (string) $hook, self::ADMIN_PAGE_SLUG_LEGACY ) === false ) {
			return;
		}
		wp_enqueue_style( 'w9-1099-chaser-tailwind', w91099ch_PLUGIN_URL . 'assets/css/vendor/tailwind-2.2.19.min.css', array(), '2.2.19' );
		wp_enqueue_style( 'w9-1099-chaser-fontawesome', w91099ch_PLUGIN_URL . 'assets/vendor/fontawesome/css/all.min.css', array(), '6.4.0' );
		wp_enqueue_style( 'w9-1099-chaser-inter', w91099ch_PLUGIN_URL . 'assets/css/vendor/inter.css', array(), '1.0.0' );
		wp_enqueue_script( 'jquery' );

		$widget_inline_css_path = w91099ch_PLUGIN_PATH . 'assets/css/w9-1099-chaser-widget-page-inline.css';
		$widget_inline_js_path  = w91099ch_PLUGIN_PATH . 'assets/js/w9-1099-chaser-widget-page-inline.js';
		$widget_inline_css_ver  = file_exists( $widget_inline_css_path ) ? filemtime( $widget_inline_css_path ) : '1.0.0';
		$widget_inline_js_ver   = file_exists( $widget_inline_js_path ) ? filemtime( $widget_inline_js_path ) : '1.0.0';

		wp_enqueue_style(
			'w9-1099-chaser-widget-page-inline',
			w91099ch_PLUGIN_URL . 'assets/css/w9-1099-chaser-widget-page-inline.css',
			array( 'w9-1099-chaser-tailwind', 'w9-1099-chaser-fontawesome', 'w9-1099-chaser-inter' ),
			$widget_inline_css_ver
		);

		wp_enqueue_script(
			'w9-1099-chaser-widget-page-inline',
			w91099ch_PLUGIN_URL . 'assets/js/w9-1099-chaser-widget-page-inline.js',
			array( 'jquery' ),
			$widget_inline_js_ver,
			true
		);

		wp_localize_script(
			'w9-1099-chaser-widget-page-inline',
			'w91099chChaserWidgetPage',
			array(
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
				'action'     => self::AJAX_ACTION_GENERATE_CODE,
				'nonceName'  => self::AJAX_NONCE_NAME,
				'nonceValue' => wp_create_nonce( self::AJAX_NONCE_ACTION ),
			)
		);
	}

	private function get_display_mode() {
		return get_option( self::OPTION_DISPLAY_MODE, 'auto' );
	}

	private function get_widget_code() {
		return (string) get_option( self::OPTION_WIDGET_CODE, '' );
	}

	private function get_selected_pages() {
		$pages = get_option( self::OPTION_SELECTED_PAGES, array() );
		return is_array( $pages ) ? array_map( 'intval', $pages ) : array();
	}

	private function get_position() {
		$pos = (string) get_option( self::OPTION_POSITION, 'bottom-right' );
		return in_array( $pos, array( 'bottom-right', 'bottom-left' ), true ) ? $pos : 'bottom-right';
	}

	private function has_user_consented() {
		return (bool) get_option( 'w91099ch_admin_consent', false );
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'w9-1099-chaser' ) );
		}

		$message       = '';
		$message_class = '';

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		$nonce_param_raw = filter_input( INPUT_POST, self::NONCE_SAVE_NAME, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'POST' === $request_method && is_string( $nonce_param_raw ) && '' !== $nonce_param_raw ) {
			$nonce_value = sanitize_text_field( wp_unslash( $nonce_param_raw ) );
			if ( ! wp_verify_nonce( $nonce_value, self::NONCE_SAVE_ACTION ) ) {
				$message       = esc_html__( 'Security check failed.', 'w9-1099-chaser' );
				$message_class = 'error';
			} else {
				$widget_code_raw = filter_input( INPUT_POST, 'w91099ch_widget_code', FILTER_UNSAFE_RAW );
				$widget_code_raw = is_string( $widget_code_raw ) ? wp_unslash( $widget_code_raw ) : '';
				$widget_code_raw = is_string( $widget_code_raw ) ? $widget_code_raw : '';
				$widget_code     = $this->sanitize_widget_embed_code( $widget_code_raw );
				$display_mode_raw = filter_input( INPUT_POST, 'w91099ch_widget_display_mode', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$display_mode     = is_string( $display_mode_raw ) ? sanitize_text_field( wp_unslash( $display_mode_raw ) ) : 'auto';
				$selected_pages_raw = isset( $_POST['w91099ch_widget_selected_pages'] ) ? wp_unslash( $_POST['w91099ch_widget_selected_pages'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitized immediately below via absint.
				$selected_pages     = is_array( $selected_pages_raw ) ? array_map( 'absint', $selected_pages_raw )
					: array();
				$position_raw = filter_input( INPUT_POST, 'w91099ch_widget_position', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$position     = is_string( $position_raw ) ? sanitize_text_field( wp_unslash( $position_raw ) ) : 'bottom-right';

				if ( ! in_array( $display_mode, array( 'auto', 'selected', 'shortcode' ), true ) ) {
					$display_mode = 'auto';
				}

				if ( ! in_array( $position, array( 'bottom-right', 'bottom-left' ), true ) ) {
					$position = 'bottom-right';
				}

				update_option( self::OPTION_WIDGET_CODE, $widget_code );
				update_option( self::OPTION_DISPLAY_MODE, $display_mode );
				update_option( self::OPTION_SELECTED_PAGES, $selected_pages );
				update_option( self::OPTION_POSITION, $position );

				$message       = esc_html__( 'Settings saved.', 'w9-1099-chaser' );
				$message_class = 'success';
			}
		}

		$display_mode   = $this->get_display_mode();
		$code           = $this->get_widget_code();
		$selected_pages = $this->get_selected_pages();
		$position       = $this->get_position();
		$pages          = get_pages();

		$is_connected = (bool) get_option( 'w91099ch_connected', false );

		$ajax_nonce = wp_create_nonce( self::AJAX_NONCE_ACTION );
		$page_title = esc_html__( 'Widget Settings', 'w9-1099-chaser' );

		include w91099ch_PLUGIN_PATH . 'admin/views/widget-page.php';
	}

	public function ajax_generate_widget_code() {
		check_ajax_referer( self::AJAX_NONCE_ACTION, self::AJAX_NONCE_NAME );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'w9-1099-chaser' ) ) );
		}

		if ( ! $this->has_user_consented() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Consent required. Please accept the data handling notice before fetching widget data.', 'w9-1099-chaser' ) ) );
		}

		try {
			$credentials  = get_option( 'w91099ch_credentials', array() );
			if ( function_exists( 'w91099ch' ) ) {
				$plugin = w91099ch();
				if ( is_object( $plugin ) && isset( $plugin->encryption ) && is_object( $plugin->encryption ) && method_exists( $plugin->encryption, 'decrypt_credentials_array' ) ) {
					$credentials = $plugin->encryption->decrypt_credentials_array( $credentials );
				}
			}

			$access_token = '';
			if ( is_array( $credentials ) && isset( $credentials['access_token'] ) && is_string( $credentials['access_token'] ) ) {
				$access_token = $credentials['access_token'];
			}
			if ( '' === (string) $access_token ) {
				$access_token = (string) get_option( 'w91099ch_access_token', '' );
				if ( function_exists( 'w91099ch' ) ) {
					$plugin = w91099ch();
					if ( is_object( $plugin ) && isset( $plugin->encryption ) && is_object( $plugin->encryption ) && method_exists( $plugin->encryption, 'decrypt_string' ) ) {
						$access_token = (string) $plugin->encryption->decrypt_string( $access_token );
					}
				}
			}

			$expires_at = ( is_array( $credentials ) && isset( $credentials['expires_at'] ) && is_string( $credentials['expires_at'] ) )
				? $credentials['expires_at']
				: '';
			if ( '' !== $expires_at ) {
				$ts = strtotime( $expires_at );
				if ( $ts && $ts <= ( time() + 300 ) && function_exists( 'w91099ch' ) ) {
					$plugin = w91099ch();
					if ( is_object( $plugin ) && isset( $plugin->core ) && is_object( $plugin->core ) && method_exists( $plugin->core, 'refresh_access_token' ) ) {
						try {
							$plugin->core->refresh_access_token();
						} catch ( Throwable $e ) {
							unset( $e );
						}
					}

					$credentials = get_option( 'w91099ch_credentials', array() );
					if ( function_exists( 'w91099ch' ) ) {
						$plugin = w91099ch();
						if ( is_object( $plugin ) && isset( $plugin->encryption ) && is_object( $plugin->encryption ) && method_exists( $plugin->encryption, 'decrypt_credentials_array' ) ) {
							$credentials = $plugin->encryption->decrypt_credentials_array( $credentials );
						}
					}
					if ( is_array( $credentials ) && isset( $credentials['access_token'] ) && is_string( $credentials['access_token'] ) ) {
						$access_token = $credentials['access_token'];
					}
				}
			}

			$access_token = trim( (string) $access_token );
			if ( '' === $access_token ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Not connected. Please connect to MyPowerly before generating widget code.', 'w9-1099-chaser' ) ) );
			}

			if ( ! class_exists( 'w91099ch_API_Handler' ) ) {
				$api_handler_path = defined( 'w91099ch_PLUGIN_PATH' )
					? ( w91099ch_PLUGIN_PATH . 'includes/class-api-handler.php' )
					: '';
				if ( $api_handler_path && file_exists( $api_handler_path ) ) {
					require_once $api_handler_path;
				}
			}

			if ( ! class_exists( 'w91099ch_API_Handler' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Service is not available. Please reload and try again.', 'w9-1099-chaser' ) ) );
			}

			$api = null;
			if ( function_exists( 'w91099ch' ) ) {
				$plugin = w91099ch();
				if ( is_object( $plugin ) && isset( $plugin->api ) && is_object( $plugin->api ) ) {
					$api = $plugin->api;
				}
				if ( null === $api && is_object( $plugin ) && isset( $plugin->encryption ) && is_object( $plugin->encryption ) ) {
					$api = new w91099ch_API_Handler( $plugin->encryption );
				}
			}
			if ( null === $api ) {
				$api = new w91099ch_API_Handler( new w91099ch_Encryption_Handler() );
			}

			$base_url = rtrim( (string) $api->get_api_base_url(), '/' );
			if ( '' === $base_url ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Service configuration is invalid. Please reload and try again.', 'w9-1099-chaser' ) ) );
			}

			// IMPORTANT: use the configured API base URL (it includes the API version path, e.g. /v1).
			// Stripping down to scheme+host can break routing and return an HTML site page instead of JSON.
			$url = $base_url . '/api/widgets/mp_widgets-sites/embed-script/';

			$sslverify = apply_filters( 'w91099ch_sslverify', true, $url, '/api/widgets/mp_widgets-sites/embed-script/', 'GET' );
			$timeout   = (int) apply_filters( 'w91099ch_api_timeout', 15, $url, '/api/widgets/mp_widgets-sites/embed-script/', 'GET' );
			if ( 0 >= $timeout ) {
				$timeout = 15;
			}

			$args = array(
				'timeout'     => $timeout,
				'sslverify'   => (bool) $sslverify,
				'headers'     => array(
					'Accept'           => 'application/json',
					'Content-Type'     => 'application/json',
					'Authorization'    => 'Bearer ' . $access_token,
					'User-Agent'       => 'WordPress-Plugin/1.0',
					'X-Requested-With' => 'XMLHttpRequest',
				),
				'user-agent'  => 'w9-1099-chaser-WordPress/' . (string) get_bloginfo( 'version' ) . '; ' . (string) home_url( '/' ),
				'redirection' => 0,
				'method'      => 'GET',
			);

			$response = wp_remote_request( $url, $args );
			if ( is_wp_error( $response ) ) {
				$err_msg = sanitize_text_field( wp_strip_all_tags( $response->get_error_message() ) );
				/* translators: %s: Error message returned by the remote request. */
				wp_send_json_error( array( 'message' => sprintf( esc_html__( 'Connection failed: %s', 'w9-1099-chaser' ), $err_msg ) ) );
			}

			$body         = (string) wp_remote_retrieve_body( $response );
			$http         = (int) wp_remote_retrieve_response_code( $response );
			$headers      = wp_remote_retrieve_headers( $response );
			$content_type = isset( $headers['content-type'] ) ? $headers['content-type'] : '';

			if ( $http < 200 || $http >= 300 ) {
				$error = array(
					/* translators: %d: HTTP status code returned by the API. */
					'message' => sprintf( esc_html__( 'API returned HTTP %d', 'w9-1099-chaser' ), (int) $http ),
				);
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$body_preview = sanitize_text_field( wp_strip_all_tags( (string) substr( $body, 0, 200 ) ) );
					$error['debug'] = array(
						'url'          => esc_url_raw( $url ),
						'status'       => $http,
						'content_type' => sanitize_text_field( (string) $content_type ),
						'body_preview' => $body_preview,
					);
				}
				wp_send_json_error( $error );
			}

			if ( '' === trim( $body ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Empty response from API.', 'w9-1099-chaser' ) ) );
			}

			// Check if response is HTML (indicates wrong endpoint or redirect)
			if ( stripos( $body, '<!DOCTYPE html>' ) === 0 || stripos( $body, '<html' ) !== false ) {
				$error = array(
					'message' => esc_html__( 'API returned HTML page instead of JSON. Check endpoint URL and authentication.', 'w9-1099-chaser' ),
				);
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$error['debug'] = array(
						'url'          => esc_url_raw( $url ),
						'content_type' => sanitize_text_field( (string) $content_type ),
						'is_html'      => true,
					);
				}
				wp_send_json_error( $error );
			}

			// Parse JSON response
			$decoded = json_decode( $body, true );
			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
				$error = array(
					'message' => esc_html__( 'Invalid JSON response from API.', 'w9-1099-chaser' ),
				);
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$body_preview = sanitize_text_field( wp_strip_all_tags( (string) substr( $body, 0, 200 ) ) );
					$error['debug'] = array(
						'json_error'   => sanitize_text_field( (string) json_last_error_msg() ),
						'body_preview' => $body_preview,
					);
				}
				wp_send_json_error( $error );
			}

			// Extract embed_code
			$embed_code = isset( $decoded['embed_code'] ) ? (string) $decoded['embed_code'] : '';
			if ( '' === trim( $embed_code ) ) {
				$error = array(
					'message' => esc_html__( 'No embed_code found in API response.', 'w9-1099-chaser' ),
				);
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$error['debug'] = array(
						'available_keys' => array_keys( $decoded ),
					);
				}
				wp_send_json_error( $error );
			}

			$embed_code = $this->sanitize_widget_embed_code( $embed_code );

			wp_send_json_success(
				array(
					'code'     => $embed_code,
					'response' => array(
						'endpoint'     => '/api/widgets/mp_widgets-sites/embed-script/',
						'workspace_id' => isset( $decoded['workspace_id'] ) ? $decoded['workspace_id'] : '',
						'site_url'     => isset( $decoded['site_url'] ) ? $decoded['site_url'] : '',
					),
				)
			);
		} catch ( Throwable $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Request failed. Please retry.', 'w9-1099-chaser' ) ) );
		}
	}

	private function should_display() {
		$display_mode = $this->get_display_mode();

		if ( 'auto' === $display_mode ) {
			return true;
		}

		if ( 'selected' === $display_mode ) {
			$selected_pages = $this->get_selected_pages();
			$current_id     = get_the_ID();
			if ( $current_id && in_array( (int) $current_id, $selected_pages, true ) ) {
				return true;
			}
		}

		return false;
	}

	private function strip_php_tags( $code ) {
		return preg_replace( '/<\?(php)?(.*?)\?>/is', '', (string) $code );
	}

	private function is_allowed_widget_src( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		if ( 'https' !== $scheme && 'http' !== $scheme ) {
			return false;
		}

		$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		if ( '' === $host ) {
			return false;
		}

		$allowed_hosts = array(
			'mypowerly.com',
			'www.mypowerly.com',
			'1099automation.com',
			'www.1099automation.com',
			'esign.signmary.com',
		);

		if ( in_array( $host, $allowed_hosts, true ) ) {
			return true;
		}

		foreach ( $allowed_hosts as $allowed ) {
			$allowed = strtolower( (string) $allowed );
			if ( $allowed ) {
				$suffix = '.' . $allowed;
				if ( strlen( $host ) >= strlen( $suffix ) && substr( $host, -strlen( $suffix ) ) === $suffix ) {
					return true;
				}
			}
		}

		return false;
	}

	private function sanitize_widget_embed_code( $code ) {
		$code = $this->strip_php_tags( (string) $code );
		$code = wp_kses( $code, $this->get_w91099ch_allowed_widget_html() );

		if ( ! class_exists( 'DOMDocument' ) ) {
			return $code;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<!DOCTYPE html><html><body>' . $code . '</body></html>' );
		libxml_clear_errors();

		$tags = array( 'script', 'iframe' );
		foreach ( $tags as $tag ) {
			$nodes  = $dom->getElementsByTagName( $tag );
			$remove = array();

			foreach ( $nodes as $node ) {
				if ( ! $node->hasAttribute( 'src' ) ) {
					if ( 'script' === $tag ) {
						continue;
					}
					$remove[] = $node;
					continue;
				}

				$src = (string) $node->getAttribute( 'src' );
				if ( ! $this->is_allowed_widget_src( $src ) ) {
					$remove[] = $node;
					continue;
				}
			}

			foreach ( $remove as $node ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument uses camelCase properties.
				if ( $node && $node->parentNode ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument uses camelCase properties.
					$node->parentNode->removeChild( $node );
				}
			}
		}

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return $code;
		}

		$out = '';
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument uses camelCase properties.
		foreach ( $body->childNodes as $child ) {
			$out .= $dom->saveHTML( $child );
		}

		return (string) $out;
	}

	public function get_w91099ch_allowed_widget_html() {
		$allowed = wp_kses_allowed_html( 'post' );

		$allowed_data_tags = array( 'div', 'span', 'button', 'a', 'img', 'input' );
		foreach ( $allowed_data_tags as $tag ) {
			if ( ! isset( $allowed[ $tag ] ) || ! is_array( $allowed[ $tag ] ) ) {
				$allowed[ $tag ] = array();
			}
			$allowed[ $tag ]['data-*'] = true;
			$allowed[ $tag ]['aria-*'] = true;
			$allowed[ $tag ]['role']   = true;
		}

		$allowed['svg'] = array(
			'xmlns'       => true,
			'width'       => true,
			'height'      => true,
			'viewbox'     => true,
			'fill'        => true,
			'class'       => true,
			'id'          => true,
			'style'       => true,
			'role'        => true,
			'aria-hidden' => true,
			'data-*'      => true,
			'aria-*'      => true,
		);

		$allowed['g'] = array(
			'transform' => true,
			'class'     => true,
			'id'        => true,
			'style'     => true,
			'data-*'    => true,
			'aria-*'    => true,
		);

		$allowed['path'] = array(
			'd'            => true,
			'fill'         => true,
			'stroke'       => true,
			'stroke-width' => true,
			'transform'    => true,
			'class'        => true,
			'id'           => true,
			'style'        => true,
			'data-*'       => true,
			'aria-*'       => true,
		);

		$allowed['text'] = array(
			'x'           => true,
			'y'           => true,
			'dx'          => true,
			'dy'          => true,
			'fill'        => true,
			'font-size'   => true,
			'font-family' => true,
			'font-weight' => true,
			'class'       => true,
			'id'          => true,
			'style'       => true,
			'data-*'      => true,
			'aria-*'      => true,
		);

		$allowed['textpath'] = array(
			'href'       => true,
			'xlink:href' => true,
			'offset'     => true,
			'startoffset'=> true,
			'method'     => true,
			'spacing'    => true,
			'class'      => true,
			'id'         => true,
			'style'      => true,
			'data-*'     => true,
			'aria-*'     => true,
		);

		$allowed['defs'] = array();
		$allowed['title'] = array();
		$allowed['desc'] = array();

		$allowed['iframe'] = array(
			'src'             => true,
			'width'           => true,
			'height'          => true,
			'frameborder'     => true,
			'allow'           => true,
			'allowfullscreen' => true,
			'loading'         => true,
			'referrerpolicy'  => true,
			'title'           => true,
			'name'            => true,
			'id'              => true,
			'class'           => true,
			'style'           => true,
		);

		$allowed['script'] = array(
			'src'            => true,
			'type'           => true,
			'async'          => true,
			'defer'          => true,
			'crossorigin'    => true,
			'referrerpolicy' => true,
			'integrity'      => true,
			'id'             => true,
		);

		$allowed['style'] = array(
			'type'  => true,
			'media' => true,
		);

		return $allowed;
	}

	public function render_position_script() {
		return "(function(){\n" .
			"document.addEventListener('DOMContentLoaded', function() {\n" .
			"  var position = window.w91099chChaserWidgetPosition || 'bottom-right';\n" .
			"  var container = document.querySelector('.w9-1099-chaser-widget-container');\n" .
			"  if (!container) return;\n" .
			"  var chatButton = container.querySelector('.chat-button');\n" .
			"  var floatingGroup = container.querySelector('.floating-group');\n" .
			"  var curvedText = container.querySelector('.curved-text');\n" .
			"  if (!chatButton || !floatingGroup || !curvedText) return;\n" .
			"  if (position === 'bottom-left') {\n" .
			"    chatButton.style.left = '20px';\n" .
			"    chatButton.style.right = 'auto';\n" .
			"    floatingGroup.style.left = '20px';\n" .
			"    floatingGroup.style.right = 'auto';\n" .
			"    curvedText.style.left = '5px';\n" .
			"    curvedText.style.right = 'auto';\n" .
			"  } else {\n" .
			"    chatButton.style.right = '20px';\n" .
			"    chatButton.style.left = 'auto';\n" .
			"    floatingGroup.style.right = '20px';\n" .
			"    floatingGroup.style.left = 'auto';\n" .
			"    curvedText.style.right = '5px';\n" .
			"    curvedText.style.left = 'auto';\n" .
			"  }\n" .
			"});\n" .
			'})();';
	}

	public function render_widget_runtime_script() {
		return "(function(){\n" .
			"var w91099chConsole = { log: function() {}, error: function() {}, warn: function() {} };\n" .
			"function w91099chToggleFloatingGroup(){\n" .
			"  var el = document.getElementById('w9-1099-chaser-floatingGroup');\n" .
			"  if (!el) {\n" .
			"    w91099chConsole.log('Floating group element not found');\n" .
			"    return;\n" .
			"  }\n" .
			"  var d = el.style.display;\n" .
			"  el.style.display = (!d || d === 'none') ? 'block' : 'none';\n" .
			"  w91099chConsole.log('Toggled floating group display to:', el.style.display);\n" .
			"}\n" .
			"document.addEventListener('click', function(e){\n" .
			"  var t = e.target;\n" .
			"  if (!t) return;\n" .
			"  var toggleElement = t.closest('[data-w9-1099-chaser-widget-toggle=\"1\"]');\n" .
			"  if (toggleElement) {\n" .
			"    w91099chConsole.log('Toggle element clicked:', toggleElement);\n" .
			"    e.preventDefault();\n" .
			"    e.stopPropagation();\n" .
			"    w91099chToggleFloatingGroup();\n" .
			"  }\n" .
			"});\n" .
			'})();';
	}

	public function render_frontend_styles() {
		return '.w9-1099-chaser-widget-container .chat-button{position:fixed;bottom:20px;z-index:1000;display:flex;flex-direction:column;align-items:center;cursor:pointer}' .
			'.w9-1099-chaser-widget-container .chat-button img{width:70px;height:70px}' .
			'.w9-1099-chaser-widget-container .curved-text{position:absolute;bottom:65px;width:90px}' .
			'.w9-1099-chaser-widget-container .curved-text svg{width:90px;height:35px}' .
			'.w9-1099-chaser-widget-container .curved-text text{font-size:14px;font-weight:bold;fill:black}' .
			'.w9-1099-chaser-widget-container .floating-group{position:fixed;bottom:90px;width:350px;height:500px;background:white;box-shadow:0 4px 8px rgba(0,0,0,0.2);border-radius:10px;overflow:hidden;display:none;z-index:999}' .
			'.w9-1099-chaser-widget-container .floating-group iframe{width:100%;height:100%;border:none}' .
			'.w9-1099-chaser-widget-container .close-button{position:absolute;top:10px;right:10px;background:#f44336;color:white;border:none;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;line-height:1}';
	}

	private function should_enqueue_frontend_assets() {
		if ( is_admin() ) {
			return false;
		}

		$code = $this->get_widget_code();
		if ( ! $code ) {
			return false;
		}

		if ( $this->should_display() ) {
			return true;
		}

		if ( is_singular() ) {
			$post = get_post();
			if ( $post && isset( $post->post_content ) && is_string( $post->post_content ) && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
				return true;
			}
		}

		if ( function_exists( 'is_active_widget' ) && is_active_widget( false, false, 'w91099ch_widget', true ) ) {
			return true;
		}

		return false;
	}

	public function enqueue_frontend_assets() {
		if ( ! $this->should_enqueue_frontend_assets() ) {
			return;
		}

		wp_register_style( 'w9-1099-chaser-widget-inline', false, array(), '1.0.0' );
		wp_enqueue_style( 'w9-1099-chaser-widget-inline' );
		wp_add_inline_style( 'w9-1099-chaser-widget-inline', $this->render_frontend_styles() );

		wp_register_script( 'w9-1099-chaser-widget-inline', false, array(), '1.0.0', true );
		wp_enqueue_script( 'w9-1099-chaser-widget-inline' );

		wp_add_inline_script(
			'w9-1099-chaser-widget-inline',
			'window.w91099chChaserWidgetPosition = ' . wp_json_encode( $this->get_position() ) . ';',
			'before'
		);

		wp_add_inline_script( 'w9-1099-chaser-widget-inline', $this->render_widget_runtime_script(), 'after' );
		wp_add_inline_script( 'w9-1099-chaser-widget-inline', $this->render_position_script(), 'after' );
	}

	/**
	 * Render sanitized widget embed with deduplication across all display methods.
	 *
	 * @param string $source                 Render source identifier.
	 * @param bool   $show_missing_notice    Whether to show missing code warning.
	 * @param bool   $show_duplicate_comment Whether to return duplicate comment.
	 * @return string
	 */
	public function render_for_display( $source = 'shortcode', $show_missing_notice = true, $show_duplicate_comment = true ) {
		if ( self::is_widget_rendered() ) {
			if ( ! $show_duplicate_comment ) {
				return '';
			}

			$rendered_by = self::get_widget_render_source();
			if ( '' === $rendered_by ) {
				$rendered_by = 'another-method';
			}

			return '<!-- MyPowerly Widget: Already displayed via ' . esc_html( $rendered_by ) . ' -->';
		}

		$code = $this->get_widget_code();
		if ( ! $code ) {
			if ( ! $show_missing_notice ) {
				return '';
			}

			return '<div style="padding: 15px; border: 1px solid #f00; background: #ffebe8; color: #333; border-radius: 4px; margin: 10px 0;">⚠️ No widget code found. Please go to Widget settings and paste your widget code.</div>';
		}

		$code = $this->sanitize_widget_embed_code( $code );

		$this->enqueue_frontend_assets();
		self::mark_widget_rendered( $source );

		// Output sanitized by wp_kses() in sanitize_widget_embed_code.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized via wp_kses() before output.
		return '<div class="w9-1099-chaser-widget-container">' . $code . '</div>';
	}

	public function auto_embed() {
		if ( is_admin() ) {
			return;
		}

		try {

			if ( ! $this->should_display() ) {
				return;
			}

			$widget_markup = $this->render_for_display( 'auto-embed', false, false );
			if ( '' === $widget_markup ) {
				return;
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized via render_for_display().
			echo $widget_markup;
		} catch ( Throwable $e ) {
			return;
		}
	}

	public function shortcode( $atts ) {
		return $this->render_for_display( 'shortcode', true, true );
	}

	public function register_widget() {
		if ( class_exists( 'w91099ch_Widget' ) ) {
			register_widget( 'w91099ch_Widget' );
		}
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
if ( class_exists( 'WP_Widget' ) ) {
	// phpcs:ignore PEAR.NamingConventions.ValidClassName.StartWithCapital
	class w91099ch_Widget extends WP_Widget {

		public function __construct() {
			parent::__construct(
				'w91099ch_widget',
				'Vendor Onboarding W9-1099 Chaser by Mypowerly Widget',
				array( 'description' => 'Display the configured Vendor Onboarding W9-1099 Chaser by Mypowerly widget' )
			);
		}

		public function widget( $args, $instance ) {
			$display_mode = get_option( w91099ch_Widget_Manager::OPTION_DISPLAY_MODE, 'auto' );
			if ( 'auto' === $display_mode ) {
				return;
			}

			$mgr          = new w91099ch_Widget_Manager();
			$widget_markup = $mgr->render_for_display( 'classic-widget', true, true );
			if ( '' === $widget_markup ) {
				return;
			}

			echo wp_kses_post( $args['before_widget'] );
			if ( ! empty( $instance['title'] ) ) {
				echo wp_kses_post( $args['before_title'] ) . esc_html( apply_filters( 'widget_title', $instance['title'] ) ) . wp_kses_post( $args['after_title'] );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized via render_for_display().
			echo $widget_markup;
			echo wp_kses_post( $args['after_widget'] );
		}

		public function form( $instance ) {
			$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
			?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'w9-1099-chaser' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
			<?php
		}

		public function update( $new_instance, $old_instance ) {
			$instance          = array();
			$instance['title'] = sanitize_text_field( $new_instance['title'] );

			return $instance;
		}
	}

}

// phpcs:enable Generic.Files.OneObjectStructurePerFile.MultipleFound

// phpcs:enable WordPress.Files.FileName.InvalidClassFileName

