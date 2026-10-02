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
		$payment_method = sanitize_text_field( $request->get_param( 'payment_method' ) ?: 'pay_at_hotel' );
		if ( ! in_array( $payment_method, array( 'pay_at_hotel', 'stripe', 'manual' ), true ) ) {
			return new WP_Error( 'invalid_payment_method', 'Invalid payment method.', array( 'status' => 400 ) );
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

		$wpdb->update( $table, $data, array( 'id' => $booking_id ) );

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
