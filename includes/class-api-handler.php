<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_API_Handler {

	private $encryption;
	private $api_base_url = 'https://mypowerly.com/v1';

	public function __construct( $encryption_handler ) {
		$this->encryption = $encryption_handler;
	}

	public function prepare_connection_request( $discount_code = '' ) {
		// New simplified External Connect API - no RSA encryption needed
		$callback_nonce = wp_create_nonce( 'w91099ch_credentials_callback' );

		$return_url = add_query_arg(
			array( 'nonce' => $callback_nonce ),
			admin_url( 'admin.php?page=w91099ch' )
		);

		$payload = array(
			'admin_email' => get_option( 'admin_email' ),
			'site_url'    => get_site_url(),
			'site_name'   => get_bloginfo( 'name' ),
			'return_url'  => $return_url,
		);

		return array(
			'api_url'   => $this->api_base_url . '/api/external_connect/',
			'post_data' => $payload,
		);
	}

	public function process_encrypted_credentials( $encrypted_credentials ) {
		// New External Connect API uses authorization codes instead of encrypted credentials
		// This method is kept for backward compatibility but will handle authorization codes
		if ( ! is_array( $encrypted_credentials ) ) {
			error_log( '[W9-1099] Invalid credentials format: Expected array, got ' . gettype( $encrypted_credentials ) );
			return false;
		}

		// Check if this is an authorization code from the new API
		if ( isset( $encrypted_credentials['authorization_code'] ) ) {
			return $this->exchange_authorization_code( $encrypted_credentials['authorization_code'] );
		}

		// Legacy encrypted credentials handling (fallback)
		$required_fields = array( 'enc_key', 'ciphertext', 'iv' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $encrypted_credentials[ $field ] ) || ! is_string( $encrypted_credentials[ $field ] ) || '' === trim( $encrypted_credentials[ $field ] ) ) {
				error_log( '[W9-1099] Missing or empty required field: ' . $field );
				return false;
			}
		}

		return $this->encryption->decrypt_credentials( $encrypted_credentials );
	}

	// NEW: Exchange authorization code for API credentials using External Connect API
	public function exchange_authorization_code( $authorization_code ) {
		if ( ! is_string( $authorization_code ) || '' === trim( $authorization_code ) ) {
			error_log( '[W9-1099] Invalid authorization code' );
			return false;
		}

		$credentials_url = $this->api_base_url . '/api/external_connect/credentials/';

		$response = wp_remote_post(
			$credentials_url,
			array(
				'headers'   => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'w91099ch-WordPress/' . (string) get_bloginfo( 'version' ) . '; ' . (string) home_url( '/' ),
				),
				'body'      => wp_json_encode( array( 'authorization_code' => $authorization_code ) ),
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[W9-1099] Authorization code exchange failed: ' . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Keep the exact raw response for debugging in admin.
		update_option( 'w91099ch_last_external_connect_raw_response', $response_body );

		if ( 200 !== $response_code ) {
			error_log( '[W9-1099] Authorization code exchange failed with HTTP ' . $response_code );
			return false;
		}

		$data = json_decode( $response_body, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			error_log( '[W9-1099] Invalid response from credentials exchange' );
			return false;
		}

		// Check for successful response structure
		if ( ! isset( $data['status'] ) || 'success' !== $data['status'] || ! isset( $data['data']['credentials'] ) ) {
			error_log( '[W9-1099] Credentials exchange response missing required fields' );
			return false;
		}

		$credentials = $data['data']['credentials'];
		if ( is_array( $credentials ) ) {
			// Store exact credentials payload from backend before any normalization.
			update_option( 'w91099ch_last_external_connect_credentials_raw', $credentials );
		}
		$credentials = $this->normalize_webhook_credentials( $credentials, $data );

		// Validate required credential fields
		$required_fields = array( 'access_token', 'user_email', 'site_url' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $credentials[ $field ] ) || '' === trim( (string) $credentials[ $field ] ) ) {
				error_log( '[W9-1099] Missing required credential field: ' . $field );
				return false;
			}
		}

		return $credentials;
	}

	private function normalize_webhook_credentials( $credentials, $response_data = array() ) {
		if ( ! is_array( $credentials ) ) {
			return array();
		}

		$url_candidates = array(
			isset( $credentials['webhook_url'] ) ? $credentials['webhook_url'] : '',
			isset( $credentials['webhook_endpoint'] ) ? $credentials['webhook_endpoint'] : '',
			isset( $credentials['wordpress_webhook_url'] ) ? $credentials['wordpress_webhook_url'] : '',
			isset( $credentials['webhook']['url'] ) ? $credentials['webhook']['url'] : '',
			isset( $credentials['webhook']['webhook_url'] ) ? $credentials['webhook']['webhook_url'] : '',
			isset( $response_data['data']['webhook_url'] ) ? $response_data['data']['webhook_url'] : '',
			isset( $response_data['data']['webhook_endpoint'] ) ? $response_data['data']['webhook_endpoint'] : '',
			isset( $response_data['data']['webhook']['url'] ) ? $response_data['data']['webhook']['url'] : '',
		);

		foreach ( $url_candidates as $candidate ) {
			if ( ! is_string( $candidate ) || '' === trim( $candidate ) ) {
				continue;
			}
			$credentials['webhook_url'] = trim( $candidate );
			break;
		}

		$secret_candidates = array(
			isset( $credentials['webhook_secret'] ) ? $credentials['webhook_secret'] : '',
			isset( $credentials['webhook_signing_secret'] ) ? $credentials['webhook_signing_secret'] : '',
			isset( $credentials['webhook']['secret'] ) ? $credentials['webhook']['secret'] : '',
			isset( $credentials['webhook']['webhook_secret'] ) ? $credentials['webhook']['webhook_secret'] : '',
			isset( $response_data['data']['webhook_secret'] ) ? $response_data['data']['webhook_secret'] : '',
			isset( $response_data['data']['webhook_signing_secret'] ) ? $response_data['data']['webhook_signing_secret'] : '',
			isset( $response_data['data']['webhook']['secret'] ) ? $response_data['data']['webhook']['secret'] : '',
		);

		foreach ( $secret_candidates as $candidate ) {
			if ( ! is_string( $candidate ) || '' === trim( $candidate ) ) {
				continue;
			}
			$credentials['webhook_secret'] = trim( $candidate );
			break;
		}

		return $credentials;
	}

	// NEW: Disconnect from External Connect API
	public function disconnect_external_connection( $admin_email, $site_url ) {
		if ( ! is_string( $admin_email ) || '' === trim( $admin_email ) ) {
			return false;
		}

		if ( ! is_string( $site_url ) || '' === trim( $site_url ) ) {
			return false;
		}

		$disconnect_url = $this->api_base_url . '/api/external_connect/disconnect/';

		$response = wp_remote_post(
			$disconnect_url,
			array(
				'headers'   => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'w91099ch-WordPress/' . (string) get_bloginfo( 'version' ) . '; ' . (string) home_url( '/' ),
				),
				'body'      => wp_json_encode(
					array(
						'admin_email' => $admin_email,
						'site_url'    => $site_url,
					)
				),
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[W9-1099] Disconnect failed: ' . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		return 200 === $response_code;
	}

	public function get_api_base_url() {
		return $this->api_base_url;
	}

	// NEW: Make API requests to MyPowerly platform
	public function make_api_request( $endpoint, $method = 'GET', $data = null, $access_token = null ) {
		$url = $this->api_base_url . $endpoint;

		$sslverify = apply_filters( 'w91099ch_sslverify', true, $url, $endpoint, $method );
		$timeout   = (int) apply_filters( 'w91099ch_api_timeout', 15, $url, $endpoint, $method );
		if ( $timeout <= 0 ) {
			$timeout = 15;
		}

		$method = strtoupper( (string) $method );

		$args = array(
			'timeout'     => $timeout,
			'sslverify'   => (bool) $sslverify,
			'headers'     => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'user-agent'  => 'w91099ch-WordPress/' . (string) get_bloginfo( 'version' ) . '; ' . (string) home_url( '/' ),
			'redirection' => 2,
		);

		$token = is_string( $access_token ) ? trim( $access_token ) : '';
		if ( '' !== $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$args['method'] = $method;

		if ( $method === 'POST' || $method === 'PUT' ) {
			$args['headers']['Idempotency-Key'] = hash( 'sha256', (string) $endpoint . '|' . (string) $method . '|' . wp_json_encode( $data ) . '|' . (string) get_site_url() );
		}

		if ( ( $method === 'POST' || $method === 'PUT' ) && $data !== null ) {
			$args['body'] = wp_json_encode( $data );
		} elseif ( $method === 'GET' && is_array( $data ) && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			if ( function_exists( 'w91099ch_log' ) ) {
				w91099ch_log( 'API Error: ' . $response->get_error_message() );
			}
			throw new Exception( esc_html( $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			$msg  = 'Request failed (HTTP ' . (int) $code . ')';
			if ( is_string( $body ) && $body !== '' ) {
				$decoded = json_decode( $body, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
					if ( isset( $decoded['message'] ) && is_string( $decoded['message'] ) && $decoded['message'] !== '' ) {
						$msg = esc_html( (string) $decoded['message'] );
					} elseif ( isset( $decoded['detail'] ) && is_string( $decoded['detail'] ) && $decoded['detail'] !== '' ) {
						$msg = esc_html( (string) $decoded['detail'] );
					} elseif ( isset( $decoded['error'] ) && is_string( $decoded['error'] ) && $decoded['error'] !== '' ) {
						$msg = esc_html( (string) $decoded['error'] );
					}
				}
			}
			throw new Exception( esc_html( $msg ) );
		}

		return $response;
	}

	// NEW: Validate API response
	public function validate_response( $response ) {
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			/* translators: %d: HTTP status code returned by the API. */
			throw new Exception( sprintf( esc_html__( 'Request failed (HTTP %d). Your information was not saved. Please retry.', 'w9-1099-chaser' ), (int) $code ) );
		}

		if ( ! is_string( $body ) || $body === '' ) {
			return array();
		}

		$data = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			throw new Exception( esc_html__( 'Unexpected response from service. Your information was not saved. Please retry.', 'w9-1099-chaser' ) );
		}

		if ( ! empty( $data ) && isset( $data['success'] ) && $data['success'] === false && isset( $data['message'] ) && is_string( $data['message'] ) && $data['message'] !== '' ) {
			throw new Exception( esc_html( (string) $data['message'] ) );
		}

		return $data;
	}
}
