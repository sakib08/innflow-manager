<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

/**
 * Public site checkout + Stripe payment endpoints.
 */
class ShmppPaymentsController {

	const NS = 'staynexushm/v1';

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/checkout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'checkout' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/checkout/quote',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'quote' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/payments/stripe/confirm',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'confirm_stripe' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/payments/stripe/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Public guest checkout: create booking, optionally start Stripe Checkout or manual payment.
	 *
	 * Body: guest + stay fields, payment_method = pay_at_hotel|stripe|manual,
	 * optional success_url / cancel_url for Stripe.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function checkout( $request ) {
		// Hidden form field that real guests never fill in.
		if ( '' !== trim( (string) $request->get_param( 'hp_website' ) ) ) {
			return new WP_Error( 'rejected', 'Could not complete the booking.', array( 'status' => 400 ) );
		}

		$payment_method = sanitize_text_field( $request->get_param( 'payment_method' ) ?: 'pay_at_hotel' );
		if ( ! in_array( $payment_method, array( 'pay_at_hotel', 'stripe', 'manual' ), true ) ) {
			return new WP_Error( 'invalid_payment_method', 'Invalid payment method.', array( 'status' => 400 ) );
		}

		$stay = $this->validate_public_stay( $request );
		if ( is_wp_error( $stay ) ) {
			return $stay;
		}

		$email = sanitize_email( (string) $request->get_param( 'email' ) );
		if ( '' !== trim( (string) $request->get_param( 'email' ) ) && ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'Please enter a valid email address.', array( 'status' => 400 ) );
		}

		if ( $this->checkout_limit_reached() ) {
			return new WP_Error(
				'too_many_bookings',
				'Too many bookings were made from your network recently. Please try again later or contact the hotel.',
				array( 'status' => 429 )
			);
		}

		$settings = get_option( 'shmpp_settings', array() );

		if ( 'stripe' === $payment_method ) {
			$config = ShmppStripe::get_config();
			if ( ! $config['enabled'] ) {
				return new WP_Error( 'stripe_disabled', 'Stripe is not configured.', array( 'status' => 400 ) );
			}
			$email = sanitize_email( $request->get_param( 'email' ) );
			if ( ! $email || ! is_email( $email ) ) {
				return new WP_Error( 'email_required', 'A valid email is required to pay with Stripe.', array( 'status' => 400 ) );
			}
		}

		if ( 'manual' === $payment_method && empty( $settings['manual_payment_enabled'] ) ) {
			return new WP_Error( 'manual_disabled', 'Manual payment is not enabled.', array( 'status' => 400 ) );
		}

		$booking_req = new WP_REST_Request( 'POST' );
		foreach ( array(
			'first_name',
			'last_name',
			'email',
			'phone',
			'address',
			'city',
			'country',
			'id_type',
			'id_number',
			'room_type_id',
			'check_in',
			'check_out',
			'adults',
			'children',
			'rooms_count',
			'discount_code',
			'notes',
		) as $field ) {
			$val = $request->get_param( $field );
			if ( null !== $val ) {
				$booking_req->set_param( $field, $val );
			}
		}

		$booking_req->set_param( 'payment_method', $payment_method );
		// Guests cannot self-mark paid; Stripe/manual start as awaiting_payment.
		$booking_req->set_param(
			'payment_status',
			in_array( $payment_method, array( 'stripe', 'manual' ), true ) ? 'awaiting_payment' : 'pending'
		);

		$bookings = new ShmppBookingsController();
		$created  = $bookings->create_booking( $booking_req );
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$booking = $created->get_data();
		if ( empty( $booking['id'] ) ) {
			return new WP_Error( 'create_failed', 'Could not create booking.', array( 'status' => 500 ) );
		}

		$this->record_checkout();

		// Stripe bookings are announced once payment succeeds, so abandoned checkouts send nothing.
		if ( 'stripe' !== $payment_method ) {
			ShmppEmails::send_booking_received( (int) $booking['id'] );
		}

		if ( 'pay_at_hotel' === $payment_method ) {
			return rest_ensure_response(
				array(
					'payment_method' => 'pay_at_hotel',
					'booking'        => $booking,
				)
			);
		}

		if ( 'manual' === $payment_method ) {
			$title = ! empty( $settings['manual_payment_title'] )
				? $settings['manual_payment_title']
				: __( 'Bank transfer / Manual payment', 'staynexus-hotel-manager' );
			$instructions = isset( $settings['manual_payment_instructions'] )
				? (string) $settings['manual_payment_instructions']
				: '';

			return rest_ensure_response(
				array(
					'payment_method' => 'manual',
					'booking'        => $booking,
					'manual'         => array(
						'title'        => $title,
						'instructions' => $instructions,
					),
				)
			);
		}

		$success_url = $this->sanitize_checkout_return_url( $request->get_param( 'success_url' ), true );
		$cancel_url  = $this->sanitize_checkout_return_url( $request->get_param( 'cancel_url' ), false );
		if ( ! $success_url || ! $cancel_url ) {
			$page = home_url( '/' );
			$success_url = $this->with_checkout_session_token(
				add_query_arg( array( 'shmpp_stripe' => 'success' ), $page )
			);
			$cancel_url = add_query_arg(
				array(
					'shmpp_stripe' => 'cancel',
					'booking_id'   => $booking['id'],
				),
				$page
			);
		} else {
			$success_url = $this->with_checkout_session_token( $success_url );
		}

		$session = ShmppStripe::create_checkout_session( $booking, $success_url, $cancel_url );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		global $wpdb;
		$wpdb->update(
			ShmppDatabase::table( 'bookings' ),
			array(
				'stripe_session_id'        => sanitize_text_field( $session['id'] ),
				'stripe_payment_intent_id' => ! empty( $session['payment_intent'] )
					? sanitize_text_field( $session['payment_intent'] )
					: null,
			),
			array( 'id' => (int) $booking['id'] )
		);

		$booking['stripe_session_id'] = $session['id'];
		$booking['payment_status']    = 'awaiting_payment';

		return rest_ensure_response(
			array(
				'payment_method' => 'stripe',
				'checkout_url'   => isset( $session['url'] ) ? $session['url'] : '',
				'session_id'     => $session['id'],
				'booking'        => $booking,
			)
		);
	}

	/**
	 * Confirm a Stripe Checkout Session after browser return.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function confirm_stripe( $request ) {
		$session_id = sanitize_text_field( $request->get_param( 'session_id' ) );
		if ( ! $session_id ) {
			return new WP_Error( 'invalid', 'session_id is required', array( 'status' => 400 ) );
		}

		$session = ShmppStripe::retrieve_session( $session_id );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$booking_id = 0;
		if ( ! empty( $session['metadata']['booking_id'] ) ) {
			$booking_id = (int) $session['metadata']['booking_id'];
		} elseif ( ! empty( $session['client_reference_id'] ) ) {
			$booking_id = (int) $session['client_reference_id'];
		}

		if ( ! $booking_id ) {
			return new WP_Error( 'not_found', 'No booking linked to this payment session.', array( 'status' => 404 ) );
		}

		$paid = ( ! empty( $session['payment_status'] ) && 'paid' === $session['payment_status'] )
			|| ( ! empty( $session['status'] ) && 'complete' === $session['status'] );

		if ( $paid ) {
			$pi = ! empty( $session['payment_intent'] ) ? $session['payment_intent'] : '';
			$this->mark_booking_paid( $booking_id, $session_id, is_string( $pi ) ? $pi : '' );
		}

		$bookings = new ShmppBookingsController();
		$req      = new WP_REST_Request( 'GET' );
		$req->set_param( 'id', $booking_id );
		$booking_res = $bookings->get_booking( $req );
		if ( is_wp_error( $booking_res ) ) {
			return $booking_res;
		}

		return rest_ensure_response(
			array(
				'paid'    => (bool) $paid,
				'booking' => $booking_res->get_data(),
			)
		);
	}

	/**
	 * Stripe webhook receiver.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function webhook( $request ) {
		$payload    = $request->get_body();
		$sig_header = $request->get_header( 'stripe_signature' );
		if ( ! $sig_header ) {
			$sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) : '';
		}

		$event = ShmppStripe::construct_event( $payload, $sig_header );
		if ( is_wp_error( $event ) ) {
			return $event;
		}

		$type = isset( $event['type'] ) ? $event['type'] : '';
		if ( in_array( $type, array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ), true ) ) {
			$session = isset( $event['data']['object'] ) ? $event['data']['object'] : array();
			$booking_id = 0;
			if ( ! empty( $session['metadata']['booking_id'] ) ) {
				$booking_id = (int) $session['metadata']['booking_id'];
			} elseif ( ! empty( $session['client_reference_id'] ) ) {
				$booking_id = (int) $session['client_reference_id'];
			}
			if ( $booking_id ) {
				$pi = ! empty( $session['payment_intent'] ) ? $session['payment_intent'] : '';
				$this->mark_booking_paid(
					$booking_id,
					isset( $session['id'] ) ? $session['id'] : '',
					is_string( $pi ) ? $pi : ''
				);
			}
		}

		return rest_ensure_response( array( 'received' => true ) );
	}

	/**
	 * Mark booking + linked room bill as paid.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $session_id Stripe session id.
	 * @param string $payment_intent Payment intent id.
	 */
	private function mark_booking_paid( $booking_id, $session_id = '', $payment_intent = '' ) {
		global $wpdb;
		$booking_id = (int) $booking_id;
		if ( ! $booking_id ) {
			return;
		}

		$table = ShmppDatabase::table( 'bookings' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( 'SELECT id, payment_status FROM %i WHERE id = %d AND deleted_at IS NULL', $table, $booking_id ),
			ARRAY_A
		);
		if ( ! $row ) {
			return;
		}

		$data = array( 'payment_status' => 'paid' );
		if ( $session_id ) {
			$data['stripe_session_id'] = sanitize_text_field( $session_id );
		}
		if ( $payment_intent ) {
			$data['stripe_payment_intent_id'] = sanitize_text_field( $payment_intent );
		}
		// Prefer PaymentIntent id as the human-auditable payment reference.
		$reference = $payment_intent ? sanitize_text_field( $payment_intent ) : sanitize_text_field( $session_id );
		if ( $reference ) {
			$data['payment_reference'] = $reference;
		}

		// Stripe can confirm the same session from the browser return and the webhook at once;
		// only the caller that flips the status to paid sends emails.
		$newly_paid = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET payment_status = %s WHERE id = %d AND payment_status <> %s',
				$table,
				'paid',
				$booking_id,
				'paid'
			)
		);
		$wpdb->update( $table, $data, array( 'id' => $booking_id ) );
		if ( $newly_paid ) {
			ShmppEmails::send_payment_received( $booking_id );
		}

		$bill_data = array( 'payment_status' => 'paid' );
		if ( $reference ) {
			$bill_data['payment_reference'] = $reference;
		}
		$wpdb->update(
			ShmppDatabase::table( 'room_bills' ),
			$bill_data,
			array(
				'booking_id'     => $booking_id,
				'payment_status' => 'unpaid',
			)
		);
	}

	/**
	 * Price breakdown the guest will be charged, before booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function quote( $request ) {
		$stay = $this->validate_public_stay( $request );
		if ( is_wp_error( $stay ) ) {
			return $stay;
		}

		$quote = ShmppInventory::quote( (int) $stay['room']['id'], $stay['check_in'], $stay['check_out'], $stay['rooms_count'] );
		if ( is_wp_error( $quote ) ) {
			return $quote;
		}
		if ( empty( $quote['available'] ) ) {
			return ShmppInventory::unavailable_error( $quote );
		}

		$code     = sanitize_text_field( (string) $request->get_param( 'discount_code' ) );
		$bookings = new ShmppBookingsController();
		$totals   = $bookings->calculate_totals( (float) $quote['subtotal'], (int) $quote['nights'], $code );

		return rest_ensure_response(
			array(
				'nights'          => (int) $quote['nights'],
				'rooms_count'     => (int) $quote['rooms_count'],
				'available_rooms' => (int) $quote['available_rooms'],
				'subtotal'        => (float) $quote['subtotal'],
				'discount_code'   => $totals['discount_code'],
				'discount_valid'  => '' === $code ? null : '' !== $totals['discount_code'],
				'discount_amount' => $totals['discount_amount'],
				'tax_rate'        => $totals['tax_rate'],
				'tax_amount'      => $totals['tax_amount'],
				'total'           => $totals['total'],
			)
		);
	}

	/**
	 * Checks shared by public quote and checkout: dates, room status, and guest capacity.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{room:array<string,mixed>,check_in:string,check_out:string,rooms_count:int}|WP_Error
	 */
	private function validate_public_stay( $request ) {
		global $wpdb;

		$check_in  = sanitize_text_field( (string) $request->get_param( 'check_in' ) );
		$check_out = sanitize_text_field( (string) $request->get_param( 'check_out' ) );
		if ( ! $this->is_valid_date( $check_in ) || ! $this->is_valid_date( $check_out ) || $check_out <= $check_in ) {
			return new WP_Error( 'invalid_dates', 'Valid check-in and check-out dates are required.', array( 'status' => 400 ) );
		}
		if ( $check_in < wp_date( 'Y-m-d' ) ) {
			return new WP_Error( 'invalid_dates', 'Check-in cannot be in the past.', array( 'status' => 400 ) );
		}
		$max_nights = (int) apply_filters( 'shmpp_max_stay_nights', 90 );
		if ( ShmppInventory::nights( $check_in, $check_out ) > $max_nights ) {
			return new WP_Error(
				'stay_too_long',
				sprintf( 'Online bookings are limited to %d nights. Please contact the hotel for longer stays.', $max_nights ),
				array( 'status' => 400 )
			);
		}

		$room_type_id = (int) $request->get_param( 'room_type_id' );
		$room         = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, max_adults, max_children, status FROM %i WHERE id = %d AND deleted_at IS NULL',
				ShmppDatabase::table( 'room_types' ),
				$room_type_id
			),
			ARRAY_A
		);
		if ( ! $room || 'active' !== $room['status'] ) {
			return new WP_Error( 'not_found', 'This room type is not available for booking.', array( 'status' => 404 ) );
		}

		$rooms_count = max( 1, (int) $request->get_param( 'rooms_count' ) );
		$adults      = max( 1, (int) $request->get_param( 'adults' ) );
		$children    = max( 0, (int) $request->get_param( 'children' ) );
		if ( $adults > (int) $room['max_adults'] * $rooms_count || $children > (int) $room['max_children'] * $rooms_count ) {
			return new WP_Error(
				'over_capacity',
				'Too many guests for the selected number of rooms. Add rooms or choose a larger room type.',
				array( 'status' => 400 )
			);
		}

		return array(
			'room'        => $room,
			'check_in'    => $check_in,
			'check_out'   => $check_out,
			'rooms_count' => $rooms_count,
		);
	}

	private function is_valid_date( $date ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * Per-IP cap on bookings created through the public checkout.
	 */
	private function checkout_limit_reached() {
		$limit = (int) apply_filters( 'shmpp_checkout_rate_limit', 3 );
		if ( $limit < 1 ) {
			return false;
		}
		return (int) get_transient( $this->checkout_limit_key() ) >= $limit;
	}

	private function record_checkout() {
		$key    = $this->checkout_limit_key();
		$window = (int) apply_filters( 'shmpp_checkout_rate_window', HOUR_IN_SECONDS );
		set_transient( $key, (int) get_transient( $key ) + 1, $window );
	}

	private function checkout_limit_key() {
		// REMOTE_ADDR only: forwarded headers are client-controlled unless a trusted proxy rewrites them.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = (string) apply_filters( 'shmpp_checkout_client_ip', $ip );
		return 'shmpp_checkout_' . md5( $ip );
	}

	/**
	 * Sanitize a return URL without destroying Stripe's session token.
	 *
	 * @param mixed $url URL from request.
	 * @param bool  $allow_session_token Whether {CHECKOUT_SESSION_ID} is allowed.
	 * @return string
	 */
	private function sanitize_checkout_return_url( $url, $allow_session_token = false ) {
		$url = is_string( $url ) ? trim( $url ) : '';
		if ( ! $url ) {
			return '';
		}

		$token = '{CHECKOUT_SESSION_ID}';
		$had   = $allow_session_token && ( false !== strpos( $url, $token ) || false !== strpos( $url, rawurlencode( $token ) ) );
		$clean = str_replace( array( $token, rawurlencode( $token ) ), 'SESSION_PLACEHOLDER', $url );
		$clean = esc_url_raw( $clean );
		if ( ! $clean ) {
			return '';
		}
		if ( $had ) {
			$clean = str_replace( 'SESSION_PLACEHOLDER', $token, $clean );
		} else {
			$clean = str_replace( 'SESSION_PLACEHOLDER', '', $clean );
		}
		return $clean;
	}

	/**
	 * Ensure success URL contains the literal Stripe session template.
	 *
	 * @param string $url Base success URL.
	 * @return string
	 */
	private function with_checkout_session_token( $url ) {
		$token = '{CHECKOUT_SESSION_ID}';
		$url   = str_replace( rawurlencode( $token ), $token, $url );
		if ( false === strpos( $url, $token ) ) {
			$sep = ( false === strpos( $url, '?' ) ) ? '?' : '&';
			$url = $url . $sep . 'session_id=' . $token;
		}
		return $url;
	}
}
