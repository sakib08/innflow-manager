<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thin Stripe REST client (Checkout Sessions) via wp_remote_*.
 */
class ShmppStripe {

	const API_BASE = 'https://api.stripe.com/v1';

	/**
	 * @return array{enabled:bool,publishable_key:string,secret_key:string,webhook_secret:string}
	 */
	public static function get_config() {
		$settings = get_option( 'shmpp_settings', array() );
		$secret   = isset( $settings['stripe_secret_key'] ) ? (string) $settings['stripe_secret_key'] : '';
		$pub      = isset( $settings['stripe_publishable_key'] ) ? (string) $settings['stripe_publishable_key'] : '';
		$enabled  = ! empty( $settings['stripe_enabled'] ) && $secret && $pub;

		return array(
			'enabled'         => (bool) $enabled,
			'publishable_key' => $pub,
			'secret_key'      => $secret,
			'webhook_secret'  => isset( $settings['stripe_webhook_secret'] ) ? (string) $settings['stripe_webhook_secret'] : '',
		);
	}

	/**
	 * Safe subset for frontend boot config (never includes secrets).
	 *
	 * @return array{enabled:bool,publishableKey:string}
	 */
	public static function get_public_config() {
		$config = self::get_config();
		return array(
			'enabled'        => $config['enabled'],
			'publishableKey' => $config['enabled'] ? $config['publishable_key'] : '',
		);
	}

	/**
	 * Create a Stripe Checkout Session for a booking.
	 *
	 * @param array  $booking Booking row (needs id, booking_code, total_amount, email optional).
	 * @param string $success_url Absolute URL (may include {CHECKOUT_SESSION_ID}).
	 * @param string $cancel_url Absolute URL.
	 * @return array|WP_Error Session object as array.
	 */
	public static function create_checkout_session( $booking, $success_url, $cancel_url ) {
		$config = self::get_config();
		if ( ! $config['enabled'] ) {
			return new WP_Error( 'stripe_disabled', 'Stripe payments are not enabled.', array( 'status' => 400 ) );
		}

		$settings  = get_option( 'shmpp_settings', array() );
		$currency  = strtolower( ! empty( $settings['currency'] ) ? $settings['currency'] : 'usd' );
		$hotel     = ! empty( $settings['hotel_name'] ) ? $settings['hotel_name'] : 'Hotel booking';
		$amount    = (float) $booking['total_amount'];
		$cents     = (int) round( $amount * 100 );

		if ( $cents < 50 ) {
			return new WP_Error( 'amount_too_small', 'Booking total is too small to charge via Stripe.', array( 'status' => 400 ) );
		}

		$line_name = sprintf(
			/* translators: 1: hotel name, 2: booking code */
			__( '%1$s — Booking %2$s', 'staynexus-hotel-manager' ),
			$hotel,
			$booking['booking_code']
		);

		$body = array(
			'mode'                                   => 'payment',
			'success_url'                            => $success_url,
			'cancel_url'                             => $cancel_url,
			'client_reference_id'                    => (string) $booking['id'],
			'metadata[booking_id]'                   => (string) $booking['id'],
			'metadata[booking_code]'                 => (string) $booking['booking_code'],
			'payment_intent_data[metadata][booking_id]' => (string) $booking['id'],
			'line_items[0][quantity]'                => 1,
			'line_items[0][price_data][currency]'    => $currency,
			'line_items[0][price_data][unit_amount]' => $cents,
			'line_items[0][price_data][product_data][name]' => $line_name,
		);

		if ( ! empty( $booking['email'] ) && is_email( $booking['email'] ) ) {
			$body['customer_email'] = $booking['email'];
		}

		return self::request( 'POST', '/checkout/sessions', $body );
	}

	/**
	 * @param string $session_id cs_...
	 * @return array|WP_Error
	 */
	public static function retrieve_session( $session_id ) {
		$session_id = sanitize_text_field( $session_id );
		if ( ! $session_id ) {
			return new WP_Error( 'invalid', 'Missing session id', array( 'status' => 400 ) );
		}
		return self::request( 'GET', '/checkout/sessions/' . rawurlencode( $session_id ) );
	}

	/**
	 * Verify Stripe webhook signature and return event payload.
	 *
	 * @param string $payload Raw request body.
	 * @param string $sig_header Stripe-Signature header.
	 * @return array|WP_Error Decoded event.
	 */
	public static function construct_event( $payload, $sig_header ) {
		$config = self::get_config();
		$secret = $config['webhook_secret'];
		if ( ! $secret ) {
			return new WP_Error( 'webhook_not_configured', 'Stripe webhook secret is not configured.', array( 'status' => 400 ) );
		}

		if ( ! $sig_header || ! $payload ) {
			return new WP_Error( 'invalid_signature', 'Missing Stripe signature.', array( 'status' => 400 ) );
		}

		$parts = array();
		foreach ( explode( ',', $sig_header ) as $piece ) {
			$kv = explode( '=', trim( $piece ), 2 );
			if ( count( $kv ) === 2 ) {
				$parts[ $kv[0] ][] = $kv[1];
			}
		}

		if ( empty( $parts['t'][0] ) || empty( $parts['v1'] ) ) {
			return new WP_Error( 'invalid_signature', 'Malformed Stripe signature.', array( 'status' => 400 ) );
		}

		$timestamp = (int) $parts['t'][0];
		if ( abs( time() - $timestamp ) > 300 ) {
			return new WP_Error( 'invalid_signature', 'Stripe signature timestamp too old.', array( 'status' => 400 ) );
		}

		$signed_payload = $timestamp . '.' . $payload;
		$expected       = hash_hmac( 'sha256', $signed_payload, $secret );
		$valid          = false;
		foreach ( $parts['v1'] as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				$valid = true;
				break;
			}
		}

		if ( ! $valid ) {
			return new WP_Error( 'invalid_signature', 'Stripe signature verification failed.', array( 'status' => 400 ) );
		}

		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) ) {
			return new WP_Error( 'invalid_payload', 'Invalid Stripe event payload.', array( 'status' => 400 ) );
		}

		return $event;
	}

	/**
	 * @param string               $method GET|POST
	 * @param string               $path   e.g. /checkout/sessions
	 * @param array<string,mixed>  $body   Form fields for POST
	 * @return array|WP_Error
	 */
	private static function request( $method, $path, $body = array() ) {
		$config = self::get_config();
		if ( empty( $config['secret_key'] ) ) {
			return new WP_Error( 'stripe_not_configured', 'Stripe secret key is missing.', array( 'status' => 400 ) );
		}

		$url  = self::API_BASE . $path;
		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $config['secret_key'],
				'Stripe-Version' => '2023-10-16',
			),
		);

		if ( 'POST' === strtoupper( $method ) ) {
			$args['method'] = 'POST';
			$args['body']   = $body;
			$response       = wp_remote_post( $url, $args );
		} else {
			$args['method'] = 'GET';
			$response       = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = 'Stripe API error';
			if ( is_array( $data ) && ! empty( $data['error']['message'] ) ) {
				$message = $data['error']['message'];
			}
			return new WP_Error( 'stripe_api_error', $message, array( 'status' => $code ?: 502 ) );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'stripe_api_error', 'Unexpected Stripe response.', array( 'status' => 502 ) );
		}

		return $data;
	}
}
