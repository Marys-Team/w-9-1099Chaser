<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_Webhook_Dispatcher {

	private static function mypowerly_is_connected() {
		if ( function_exists( 'w91099ch' ) ) {
			$plugin = w91099ch();
			if ( is_object( $plugin ) && isset( $plugin->core ) && is_object( $plugin->core ) && method_exists( $plugin->core, 'is_connected' ) ) {
				return (bool) $plugin->core->is_connected();
			}
		}
		return (bool) get_option( 'w91099ch_connected', false );
	}

	private static function get_payment_limit_settings() {
		if ( ! self::mypowerly_is_connected() ) {
			return array(
				'enabled' => false,
				'amount'  => 0.0,
				'period'  => (string) get_option( 'w91099ch_payment_limit_period', 'month' ),
				'action'  => (string) get_option( 'w91099ch_payment_limit_action', 'block' ),
			);
		}
		return array(
			'enabled' => (bool) get_option( 'w91099ch_payment_limit_enabled', false ),
			'amount'  => (float) get_option( 'w91099ch_payment_limit_amount', 0 ),
			'period'  => (string) get_option( 'w91099ch_payment_limit_period', 'month' ),
			'action'  => (string) get_option( 'w91099ch_payment_limit_action', 'block' ),
		);
	}

	private static function detect_payment_amount_from_payload( $payload ) {
		if ( ! is_array( $payload ) ) {
			return 0.0;
		}

		$explicit_keys = array(
			'affiliate_amount',
			'amount',
			'payout_amount',
			'payment_amount',
			'earnings',
			'total_payouts',
		);
		foreach ( $explicit_keys as $key ) {
			if ( isset( $payload[ $key ] ) && is_numeric( $payload[ $key ] ) ) {
				$val = (float) $payload[ $key ];
				return $val > 0 ? $val : 0.0;
			}
		}

		$best = 0.0;
		foreach ( $payload as $k => $v ) {
			if ( ! is_string( $k ) ) {
				continue;
			}
			$k_norm = strtolower( (string) $k );
			if ( false === strpos( $k_norm, 'amount' ) && false === strpos( $k_norm, 'payout' ) && false === strpos( $k_norm, 'payment' ) && false === strpos( $k_norm, 'earning' ) && false === strpos( $k_norm, 'commission' ) ) {
				continue;
			}
			if ( is_numeric( $v ) ) {
				$num = (float) $v;
				if ( $num > $best ) {
					$best = $num;
				}
				continue;
			}
			if ( is_string( $v ) ) {
				$san = preg_replace( '/[^0-9.]/', '', $v );
				if ( is_numeric( $san ) ) {
					$num = (float) $san;
					if ( $num > $best ) {
						$best = $num;
					}
				}
			}
		}

		return $best > 0 ? $best : 0.0;
	}

	private static function payment_limit_period_key( $period ) {
		$period = is_string( $period ) ? strtolower( trim( $period ) ) : 'month';
		if ( ! in_array( $period, array( 'day', 'week', 'month' ), true ) ) {
			$period = 'month';
		}
		$now = function_exists( 'current_time' ) ? (int) current_time( 'timestamp', true ) : time();
		if ( 'day' === $period ) {
			return 'day:' . gmdate( 'Y-m-d', $now );
		}
		if ( 'week' === $period ) {
			return 'week:' . gmdate( 'o-W', $now );
		}
		return 'month:' . gmdate( 'Y-m', $now );
	}

	private static function should_apply_payment_limit( $payload, $event_type ) {
		$event_type = is_string( $event_type ) ? strtolower( trim( $event_type ) ) : '';
		if ( '' !== $event_type ) {
			if ( false !== strpos( $event_type, 'payout' ) || false !== strpos( $event_type, 'payment' ) || false !== strpos( $event_type, 'commission' ) || false !== strpos( $event_type, 'earn' ) ) {
				return true;
			}
		}
		if ( is_array( $payload ) && isset( $payload['sheet_tab'] ) && is_string( $payload['sheet_tab'] ) ) {
			$tab = strtolower( (string) $payload['sheet_tab'] );
			if ( false !== strpos( $tab, 'payout' ) || false !== strpos( $tab, 'wallet' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function sanitize_webhook_url( $url ) {
		$url = is_string( $url ) ? trim( $url ) : '';
		if ( '' === $url ) {
			return '';
		}
		return esc_url_raw( $url );
	}

	public static function sanitize_webhook_secret( $secret ) {
		$secret = is_string( $secret ) ? (string) $secret : '';
		$secret = trim( $secret );
		if ( '' === $secret ) {
			return '';
		}
		return sanitize_text_field( $secret );
	}

	public static function get_webhook_status() {
		$webhook_url = '';
		if ( function_exists( 'w91099ch' ) ) {
			$plugin = w91099ch();
			if ( is_object( $plugin ) && isset( $plugin->core ) && is_object( $plugin->core ) && method_exists( $plugin->core, 'get_credentials' ) ) {
				$creds = $plugin->core->get_credentials();
				if ( is_array( $creds ) && isset( $creds['webhook_url'] ) ) {
					$webhook_url = (string) $creds['webhook_url'];
				}
			}
		}

		if ( '' === trim( $webhook_url ) ) {
			$webhook_url = (string) get_option( 'w91099ch_master_webhook_url', '' );
		}
		if ( '' === trim( $webhook_url ) ) {
			$webhook_url = (string) get_option( 'w91099ch_webhook_url', '' );
		}

		return array(
			'configured'  => '' !== trim( (string) $webhook_url ),
			'webhook_url' => esc_url_raw( (string) $webhook_url ),
		);
	}

	public static function dispatch_raw_payload( $payload, $event_type = '' ) {
		$attempted = 1;
		$sent      = 0;
		$errors    = array();

		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$event_type = is_string( $event_type ) ? trim( $event_type ) : '';
		if ( '' !== $event_type ) {
			$payload['event_type'] = $event_type;
		}

		$limit_settings = self::get_payment_limit_settings();
		$apply_limit    = $limit_settings['enabled'] && $limit_settings['amount'] > 0;
		$payment_amount = 0.0;
		if ( $apply_limit && self::should_apply_payment_limit( $payload, $event_type ) ) {
			$payment_amount = self::detect_payment_amount_from_payload( $payload );
		}

		$webhook_url    = '';
		$webhook_secret = '';

		if ( function_exists( 'w91099ch' ) ) {
			$plugin = w91099ch();
			if ( is_object( $plugin ) && isset( $plugin->core ) && is_object( $plugin->core ) && method_exists( $plugin->core, 'get_credentials' ) ) {
				$creds = $plugin->core->get_credentials();
				if ( is_array( $creds ) ) {
					$webhook_url    = isset( $creds['webhook_url'] ) ? (string) $creds['webhook_url'] : '';
					$webhook_secret = isset( $creds['webhook_secret'] ) ? (string) $creds['webhook_secret'] : '';
				}
			}
		}

		// Fallback for installs where webhook settings are stored in options rather than credentials.
		if ( '' === trim( $webhook_url ) ) {
			$webhook_url = (string) get_option( 'w91099ch_master_webhook_url', '' );
		}
		if ( '' === trim( $webhook_secret ) ) {
			$webhook_secret = (string) get_option( 'w91099ch_master_webhook_secret', '' );
		}
		if ( '' === trim( $webhook_url ) ) {
			$webhook_url = (string) get_option( 'w91099ch_webhook_url', '' );
		}
		if ( '' === trim( $webhook_secret ) ) {
			$webhook_secret = (string) get_option( 'w91099ch_webhook_secret', '' );
		}

		$webhook_url    = apply_filters( 'w91099ch_webhook_url', trim( $webhook_url ), $payload, $event_type );
		$webhook_secret = apply_filters( 'w91099ch_webhook_secret', trim( $webhook_secret ), $payload, $event_type );

		if ( $apply_limit && $payment_amount > 0 ) {
			$period_key = self::payment_limit_period_key( $limit_settings['period'] );
			$stored     = get_option( 'w91099ch_payment_limit_totals', array() );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			$current_total = isset( $stored[ $period_key ] ) && is_numeric( $stored[ $period_key ] ) ? (float) $stored[ $period_key ] : 0.0;
			$next_total    = $current_total + $payment_amount;
			$limit_amount  = (float) $limit_settings['amount'];

			if ( $limit_amount > 0 && $next_total > $limit_amount && (string) $limit_settings['action'] === 'block' ) {
				$errors[] = array(
					'error'   => 'payment_limit_exceeded',
					'message' => 'Payment limit exceeded',
				);
				return array(
					'attempted' => $attempted,
					'sent'      => $sent,
					'errors'    => $errors,
				);
			}

			if ( $limit_amount > 0 && $next_total > $limit_amount && (string) $limit_settings['action'] === 'warn' ) {
				if ( function_exists( 'w91099ch_log' ) ) {
					w91099ch_log( 'Payment limit warning: projected total ' . (string) $next_total . ' exceeds limit ' . (string) $limit_amount . ' for ' . $period_key );
				}
			}
		}

		if ( '' === $webhook_url ) {
			$errors[] = array(
				'error'   => 'webhook_url_missing',
				'message' => 'Webhook URL is not configured',
			);
			return array(
				'attempted' => $attempted,
				'sent'      => $sent,
				'errors'    => $errors,
			);
		}

		$body = wp_json_encode( $payload );
		if ( ! is_string( $body ) || '' === $body ) {
			$errors[] = array(
				'error'   => 'json_encode_failed',
				'message' => 'Failed to encode webhook payload',
			);
			return array(
				'attempted' => $attempted,
				'sent'      => $sent,
				'errors'    => $errors,
			);
		}

		$powerly_signing_secret = (string) get_option( 'w91099ch_powerly_signing_secret', '' );

		$signature = '';
		if ( '' !== $powerly_signing_secret ) {
			$signature = hash_hmac( 'sha256', $body, $powerly_signing_secret );
		} elseif ( '' !== $webhook_secret ) {
			$signature = hash_hmac( 'sha256', $body, $webhook_secret );
		}

		$headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'User-Agent'   => 'w9-1099-chaser-WordPress/' . ( defined( 'w91099ch_VERSION' ) ? (string) w91099ch_VERSION : '1.0' ),
		);
		if ( '' !== $event_type ) {
			$headers['X-W91099CH-Event'] = $event_type;
		}
		if ( '' !== $signature ) {
			$headers['X-Powerly-Signature'] = $signature;
		}

		$timeout   = (int) apply_filters( 'w91099ch_webhook_timeout', 15, $webhook_url, $payload, $event_type );
		$sslverify = (bool) apply_filters( 'w91099ch_sslverify', true, $webhook_url, '', 'POST' );

		$response = wp_remote_post(
			$webhook_url,
			array(
				'headers'     => $headers,
				'body'        => $body,
				'timeout'     => $timeout > 0 ? $timeout : 15,
				'sslverify'   => $sslverify,
				'redirection' => 2,
			)
		);

		if ( is_wp_error( $response ) ) {
			$errors[] = array(
				'error'   => 'wp_error',
				'message' => $response->get_error_message(),
			);
			return array(
				'attempted' => $attempted,
				'sent'      => $sent,
				'errors'    => $errors,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			if ( $apply_limit && $payment_amount > 0 ) {
				$period_key = self::payment_limit_period_key( $limit_settings['period'] );
				$stored     = get_option( 'w91099ch_payment_limit_totals', array() );
				if ( ! is_array( $stored ) ) {
					$stored = array();
				}
				$current_total = isset( $stored[ $period_key ] ) && is_numeric( $stored[ $period_key ] ) ? (float) $stored[ $period_key ] : 0.0;
				$stored[ $period_key ] = $current_total + $payment_amount;
				update_option( 'w91099ch_payment_limit_totals', $stored );
			}
			$sent = 1;
			return array(
				'attempted' => $attempted,
				'sent'      => $sent,
				'errors'    => array(),
			);
		}

		$resp_body = (string) wp_remote_retrieve_body( $response );
		$errors[]  = array(
			'error'   => 'http_' . $code,
			'message' => $resp_body !== '' ? $resp_body : ( 'HTTP ' . $code ),
		);

		return array(
			'attempted' => $attempted,
			'sent'      => $sent,
			'errors'    => $errors,
		);
	}
}
