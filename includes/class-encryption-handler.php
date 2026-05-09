<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_Encryption_Handler {

	private $debug_mode;
	private $secret_prefix = 'w91099ch_enc:';
	private $hex_prefix    = 'h2:';

	private function strict_base64_decode( $value, $max_len = 100000 ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$max_len = is_numeric( $max_len ) ? (int) $max_len : 0;
		if ( $max_len > 0 && strlen( $value ) > $max_len ) {
			return '';
		}

		if ( 1 !== preg_match( '/^[A-Za-z0-9+\/\r\n=]+$/', $value ) ) {
			return '';
		}

		$decoded = base64_decode( $value, true );
		return is_string( $decoded ) ? $decoded : '';
	}

	private function strict_hex_decode( $value, $max_len = 100000 ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$max_len = is_numeric( $max_len ) ? (int) $max_len : 0;
		if ( $max_len > 0 && strlen( $value ) > $max_len ) {
			return '';
		}

		if ( 0 !== ( strlen( $value ) % 2 ) ) {
			return '';
		}

		if ( 1 !== preg_match( '/^[A-Fa-f0-9]+$/', $value ) ) {
			return '';
		}

		$decoded = function_exists( 'hex2bin' ) ? hex2bin( $value ) : '';
		return is_string( $decoded ) ? $decoded : '';
	}

	public function __construct() {
		$this->debug_mode = ( defined( 'WP_DEBUG' ) && WP_DEBUG );
	}

	private function get_secret_key_bytes() {
		$material = '';
		if ( function_exists( 'wp_salt' ) ) {
			$material = (string) wp_salt( 'auth' );
		}
		if ( '' === $material && defined( 'AUTH_KEY' ) ) {
			$material = (string) AUTH_KEY;
		}
		if ( '' === $material ) {
			$material = (string) home_url( '/' );
		}
		return hash( 'sha256', $material, true );
	}

	public function encrypt_string( $plaintext ) {
		$plaintext = is_string( $plaintext ) ? $plaintext : '';
		if ( '' === $plaintext ) {
			return '';
		}

		if ( strlen( $plaintext ) >= strlen( $this->secret_prefix ) && substr( $plaintext, 0, strlen( $this->secret_prefix ) ) === $this->secret_prefix ) {
			return $plaintext;
		}

		$key = $this->get_secret_key_bytes();
		$iv  = function_exists( 'random_bytes' ) ? random_bytes( 16 ) : openssl_random_pseudo_bytes( 16 );
		if ( ! is_string( $iv ) || 16 !== strlen( $iv ) ) {
			return '';
		}

		$ciphertext = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( ! is_string( $ciphertext ) || '' === $ciphertext ) {
			return '';
		}

		$payload = wp_json_encode(
			array(
				'v'  => 2,
				'iv' => bin2hex( $iv ),
				'ct' => bin2hex( $ciphertext ),
			)
		);
		if ( ! is_string( $payload ) || '' === $payload ) {
			return '';
		}

		return $this->secret_prefix . $this->hex_prefix . bin2hex( $payload );
	}

	public function decrypt_string( $value ) {
		$value = is_string( $value ) ? $value : '';
		if ( '' === $value ) {
			return '';
		}

		if ( strlen( $value ) < strlen( $this->secret_prefix ) || substr( $value, 0, strlen( $this->secret_prefix ) ) !== $this->secret_prefix ) {
			return $value;
		}

		$encoded = substr( $value, strlen( $this->secret_prefix ) );
		if ( '' === $encoded ) {
			return '';
		}

		if ( strlen( $encoded ) >= strlen( $this->hex_prefix ) && substr( $encoded, 0, strlen( $this->hex_prefix ) ) === $this->hex_prefix ) {
			$hex = substr( $encoded, strlen( $this->hex_prefix ) );
			$json = $this->strict_hex_decode( $hex, 200000 );
		} else {
			$json = $this->strict_base64_decode( $encoded, 100000 );
		}
		if ( ! is_string( $json ) || '' === $json ) {
			return '';
		}

		$data = json_decode( $json, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			return '';
		}

		$ver = isset( $data['v'] ) ? (int) $data['v'] : 0;
		$iv_s = isset( $data['iv'] ) ? (string) $data['iv'] : '';
		$ct_s = isset( $data['ct'] ) ? (string) $data['ct'] : '';
		if ( '' === $iv_s || '' === $ct_s ) {
			return '';
		}

		if ( 2 === $ver ) {
			$iv         = $this->strict_hex_decode( $iv_s, 256 );
			$ciphertext = $this->strict_hex_decode( $ct_s, 400000 );
		} else {
			$iv         = $this->strict_base64_decode( $iv_s, 128 );
			$ciphertext = $this->strict_base64_decode( $ct_s, 200000 );
		}
		if ( ! is_string( $iv ) || 16 !== strlen( $iv ) || ! is_string( $ciphertext ) || '' === $ciphertext ) {
			return '';
		}

		$key       = $this->get_secret_key_bytes();
		$plaintext = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return is_string( $plaintext ) ? $plaintext : '';
	}

	public function decrypt_credentials_array( $credentials ) {
		if ( ! is_array( $credentials ) ) {
			return array();
		}

		$out = $credentials;
		$fields = array( 'client_id', 'client_secret', 'access_token', 'refresh_token', 'api_key' );
		foreach ( $fields as $field ) {
			if ( isset( $out[ $field ] ) && is_string( $out[ $field ] ) ) {
				$out[ $field ] = $this->decrypt_string( $out[ $field ] );
			}
		}
		return $out;
	}

	public function encrypt_credentials_array( $credentials ) {
		if ( ! is_array( $credentials ) ) {
			return array();
		}

		$out = $credentials;
		$fields = array( 'client_id', 'client_secret', 'access_token', 'refresh_token', 'api_key' );
		foreach ( $fields as $field ) {
			if ( isset( $out[ $field ] ) && is_string( $out[ $field ] ) && '' !== trim( $out[ $field ] ) ) {
				$out[ $field ] = $this->encrypt_string( $out[ $field ] );
			}
		}
		return $out;
	}

	public function get_encryption_key() {
		$cache_key = 'w91099ch_encryption_key';
		$key       = wp_cache_get( $cache_key, 'w91099ch' );
		if ( false !== $key ) {
			return $key;
		}

		$key = (string) get_option( 'w91099ch_encryption_key', '' );
		if ( '' !== $key ) {
			wp_cache_set( $cache_key, $key, 'w91099ch', DAY_IN_SECONDS );
		}

		return $key;
	}
	public function decrypt_credentials( $encrypted_data ) {
		try {
			$private_key = $this->get_temporary_private_key();
			if ( ! $private_key ) {
				error_log( '[W9-1099] Decryption failed: No temporary private key available' );
				return false;
			}

			// Fix common URL encoding issues
			$fixed_data = $encrypted_data;
			if ( is_array( $encrypted_data ) ) {
				$fixed_data['enc_key']    = isset( $encrypted_data['enc_key'] ) ? str_replace( ' ', '+', $encrypted_data['enc_key'] ) : '';
				$fixed_data['ciphertext'] = isset( $encrypted_data['ciphertext'] ) ? str_replace( ' ', '+', $encrypted_data['ciphertext'] ) : '';
				$fixed_data['iv']         = isset( $encrypted_data['iv'] ) ? str_replace( ' ', '+', $encrypted_data['iv'] ) : '';
			} else {
				error_log( '[W9-1099] Decryption failed: Invalid encrypted data format' );
				return false;
			}

			$encrypted_key = $this->strict_base64_decode( $fixed_data['enc_key'], 20000 );
			$ciphertext    = $this->strict_base64_decode( $fixed_data['ciphertext'], 200000 );
			$iv            = $this->strict_base64_decode( $fixed_data['iv'], 128 );

			if ( empty( $encrypted_key ) || empty( $ciphertext ) || empty( $iv ) ) {
				error_log( '[W9-1099] Decryption failed: Empty decoded components (key=' . strlen( $encrypted_key ) . ', ct=' . strlen( $ciphertext ) . ', iv=' . strlen( $iv ) . ')' );
				return false;
			}

			$rsa_key = openssl_pkey_get_private( $private_key );
			if ( ! $rsa_key ) {
				error_log( '[W9-1099] Decryption failed: Invalid RSA private key' );
				return false;
			}

			$aes_key = '';
			$success = openssl_private_decrypt( $encrypted_key, $aes_key, $rsa_key, OPENSSL_PKCS1_OAEP_PADDING );

			if ( ! $success || ! $aes_key ) {
				error_log( '[W9-1099] Decryption failed: RSA decryption failed' );
				return false;
			}

			$decrypted = openssl_decrypt( $ciphertext, 'AES-256-CBC', $aes_key, OPENSSL_RAW_DATA, $iv );

			if ( ! $decrypted ) {
				error_log( '[W9-1099] Decryption failed: AES decryption failed' );
				return false;
			}

			// Remove PKCS7 padding
			$padding = ord( $decrypted[ strlen( $decrypted ) - 1 ] );
			if ( $padding > 0 && $padding <= 16 ) {
				$decrypted = substr( $decrypted, 0, -$padding );
			}

			$credentials = json_decode( $decrypted, true );

			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $credentials ) || empty( $credentials ) ) {
				error_log( '[W9-1099] Decryption failed: Invalid JSON or empty credentials (JSON error: ' . json_last_error_msg() . ')' );
				return false;
			}

			return $credentials;

		} catch ( Exception $e ) {
			return false;
		}
	}

	public function generate_rsa_key_pair() {
		$config = array(
			'digest_alg'       => 'sha256',
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		);

		$res = openssl_pkey_new( $config );

		if ( ! $res ) {
			throw new Exception( 'Failed to generate RSA key pair' );
		}

		openssl_pkey_export( $res, $private_key );

		$public_key_info = openssl_pkey_get_details( $res );
		$public_key      = $public_key_info['key'];

		return array(
			'private_key' => $private_key,
			'public_key'  => $public_key,
		);
	}

	public function sign_payload( $payload, $private_key ) {
		$payload_json = json_encode( $payload );

		$key = openssl_pkey_get_private( $private_key );
		if ( ! $key ) {
			throw new Exception( 'Invalid private key for signing' );
		}

		$signature = '';
		$success   = openssl_sign( $payload_json, $signature, $key, OPENSSL_ALGO_SHA256 );

		if ( ! $success ) {
			throw new Exception( 'Failed to sign payload' );
		}

		return $signature;
	}

	public function store_temporary_private_key( $private_key ) {
		$user_id       = get_current_user_id();
		$transient_key = 'w91099ch_private_key_' . $user_id;

		set_transient( $transient_key, $private_key, 30 * MINUTE_IN_SECONDS );
	}

	public function get_temporary_private_key() {
		$user_id       = get_current_user_id();
		$transient_key = 'w91099ch_private_key_' . $user_id;
		$key           = get_transient( $transient_key );

		return $key;
	}

	public function clear_temporary_keys() {
		$user_ids = get_users(
			array(
				'fields' => 'ID',
				'number' => -1,
			)
		);
		$user_ids = is_array( $user_ids ) ? array_map( 'absint', $user_ids ) : array();
		foreach ( $user_ids as $user_id ) {
			if ( $user_id <= 0 ) {
				continue;
			}
			delete_transient( 'w91099ch_private_key_' . $user_id );
		}
	}

	private function log( $message ) {
		return;
	}
}
