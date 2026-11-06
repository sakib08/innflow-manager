<?php
defined( 'ABSPATH' ) || exit;

/**
 * Booking notification emails for guests and hotel staff.
 */
class ShmppEmails {

	/**
	 * Guest + hotel emails for a new website booking (pay at hotel or manual payment).
	 *
	 * @param int $booking_id Booking ID.
	 */
	public static function send_booking_received( $booking_id ) {
		$booking = self::load_booking( $booking_id );
		if ( ! $booking ) {
			return;
		}

		$settings = get_option( 'shmpp_settings', array() );
		$hotel    = self::hotel_name( $settings );

		if ( 'manual' === $booking['payment_method'] ) {
			$guest_subject = sprintf(
				/* translators: 1: hotel name, 2: booking code */
				__( '[%1$s] Booking %2$s reserved — payment instructions', 'staynexus-hotel-manager' ),
				$hotel,
				$booking['booking_code']
			);
			$intro = __( 'Thank you for your booking. Your room is reserved and awaiting payment.', 'staynexus-hotel-manager' );
			$extra = self::manual_payment_block( $booking, $settings );
		} else {
			$guest_subject = sprintf(
				/* translators: 1: hotel name, 2: booking code */
				__( '[%1$s] Booking %2$s confirmed', 'staynexus-hotel-manager' ),
				$hotel,
				$booking['booking_code']
			);
			$intro = __( 'Thank you for your booking. Your reservation is confirmed and payment is due at the hotel.', 'staynexus-hotel-manager' );
			$extra = '';
		}

		self::send_guest( $booking, $settings, $guest_subject, $intro, $extra );
		self::send_hotel( $booking, $settings );
	}

	/**
	 * Guest + hotel emails after an online payment succeeds.
	 *
	 * @param int $booking_id Booking ID.
	 */
	public static function send_payment_received( $booking_id ) {
		$booking = self::load_booking( $booking_id );
		if ( ! $booking ) {
			return;
		}

		$settings = get_option( 'shmpp_settings', array() );
		$subject  = sprintf(
			/* translators: 1: hotel name, 2: booking code */
			__( '[%1$s] Payment received — booking %2$s confirmed', 'staynexus-hotel-manager' ),
			self::hotel_name( $settings ),
			$booking['booking_code']
		);
		$intro = __( 'We received your payment. Your reservation is confirmed.', 'staynexus-hotel-manager' );

		self::send_guest( $booking, $settings, $subject, $intro, '' );
		self::send_hotel( $booking, $settings );
	}

	/**
	 * @param int $booking_id Booking ID.
	 * @return array<string,mixed>|null
	 */
	private static function load_booking( $booking_id ) {
		if ( ! apply_filters( 'shmpp_send_booking_emails', true, (int) $booking_id ) ) {
			return null;
		}

		$controller = new ShmppBookingsController();
		$request    = new WP_REST_Request( 'GET' );
		$request->set_param( 'id', (int) $booking_id );
		$response = $controller->get_booking( $request );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$booking = $response->get_data();
		return is_array( $booking ) ? $booking : null;
	}

	private static function send_guest( $booking, $settings, $subject, $intro, $extra ) {
		$to = isset( $booking['email'] ) ? sanitize_email( $booking['email'] ) : '';
		if ( ! $to || ! is_email( $to ) ) {
			return;
		}

		$lines   = array();
		$lines[] = sprintf(
			/* translators: %s: guest first name */
			__( 'Hello %s,', 'staynexus-hotel-manager' ),
			$booking['first_name']
		);
		$lines[] = '';
		$lines[] = $intro;
		$lines[] = '';
		$lines   = array_merge( $lines, self::summary_lines( $booking, $settings ) );

		if ( ! empty( $settings['check_in_time'] ) || ! empty( $settings['check_out_time'] ) ) {
			$lines[] = '';
			$lines[] = sprintf(
				/* translators: 1: check-in time, 2: check-out time */
				__( 'Check-in from %1$s · Check-out by %2$s', 'staynexus-hotel-manager' ),
				! empty( $settings['check_in_time'] ) ? $settings['check_in_time'] : '—',
				! empty( $settings['check_out_time'] ) ? $settings['check_out_time'] : '—'
			);
		}

		if ( $extra ) {
			$lines[] = '';
			$lines[] = $extra;
		}

		$lines[] = '';
		$lines   = array_merge( $lines, self::hotel_contact_lines( $settings ) );

		$headers     = array( 'Content-Type: text/plain; charset=UTF-8' );
		$hotel_email = self::hotel_email( $settings );
		if ( $hotel_email ) {
			$headers[] = sprintf( 'Reply-To: %s <%s>', self::header_name( self::hotel_name( $settings ) ), $hotel_email );
		}

		wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
	}

	private static function send_hotel( $booking, $settings ) {
		$to = self::hotel_email( $settings );
		if ( ! $to ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: booking code, 2: guest name */
			__( 'New booking %1$s — %2$s', 'staynexus-hotel-manager' ),
			$booking['booking_code'],
			trim( $booking['first_name'] . ' ' . $booking['last_name'] )
		);

		$lines   = array( __( 'A new booking was made on the website.', 'staynexus-hotel-manager' ), '' );
		$lines   = array_merge( $lines, self::summary_lines( $booking, $settings ) );
		$lines[] = '';
		$lines[] = sprintf( __( 'Email: %s', 'staynexus-hotel-manager' ), $booking['email'] ? $booking['email'] : '—' );
		$lines[] = sprintf( __( 'Phone: %s', 'staynexus-hotel-manager' ), $booking['phone'] ? $booking['phone'] : '—' );
		$lines[] = sprintf( __( 'Payment status: %s', 'staynexus-hotel-manager' ), $booking['payment_status'] );
		if ( ! empty( $booking['payment_reference'] ) ) {
			$lines[] = sprintf( __( 'Payment reference: %s', 'staynexus-hotel-manager' ), $booking['payment_reference'] );
		}
		$lines[] = '';
		$lines[] = admin_url( 'admin.php?page=staynexus-hotel-manager-bookings' );

		$headers     = array( 'Content-Type: text/plain; charset=UTF-8' );
		$guest_email = isset( $booking['email'] ) ? sanitize_email( $booking['email'] ) : '';
		if ( $guest_email && is_email( $guest_email ) ) {
			$headers[] = sprintf(
				'Reply-To: %s <%s>',
				self::header_name( trim( $booking['first_name'] . ' ' . $booking['last_name'] ) ),
				$guest_email
			);
		}

		wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
	}

	/**
	 * @return string[]
	 */
	private static function summary_lines( $booking, $settings ) {
		$lines   = array();
		$lines[] = sprintf( __( 'Booking code: %s', 'staynexus-hotel-manager' ), $booking['booking_code'] );
		$lines[] = sprintf( __( 'Room: %s', 'staynexus-hotel-manager' ), $booking['room_name'] );
		$lines[] = sprintf(
			/* translators: 1: check-in date, 2: check-out date */
			__( 'Dates: %1$s → %2$s', 'staynexus-hotel-manager' ),
			$booking['check_in'],
			$booking['check_out']
		);
		$lines[] = sprintf(
			/* translators: 1: rooms, 2: adults, 3: children */
			__( 'Rooms: %1$d · Adults: %2$d · Children: %3$d', 'staynexus-hotel-manager' ),
			(int) $booking['rooms_count'],
			(int) $booking['adults'],
			(int) $booking['children']
		);
		if ( (float) $booking['discount_amount'] > 0 ) {
			$lines[] = sprintf( __( 'Discount: -%s', 'staynexus-hotel-manager' ), self::money( $booking['discount_amount'], $settings ) );
		}
		if ( (float) $booking['tax_amount'] > 0 ) {
			$lines[] = sprintf( __( 'Tax: %s', 'staynexus-hotel-manager' ), self::money( $booking['tax_amount'], $settings ) );
		}
		$lines[] = sprintf( __( 'Total: %s', 'staynexus-hotel-manager' ), self::money( $booking['total_amount'], $settings ) );
		$lines[] = sprintf( __( 'Payment method: %s', 'staynexus-hotel-manager' ), self::payment_method_label( $booking['payment_method'], $settings ) );
		return $lines;
	}

	private static function manual_payment_block( $booking, $settings ) {
		$title = ! empty( $settings['manual_payment_title'] )
			? $settings['manual_payment_title']
			: __( 'Bank transfer / Manual payment', 'staynexus-hotel-manager' );

		$parts   = array( $title );
		$parts[] = sprintf(
			/* translators: %s: booking code */
			__( 'Please use booking code %s as your payment reference.', 'staynexus-hotel-manager' ),
			$booking['booking_code']
		);
		if ( ! empty( $settings['manual_payment_instructions'] ) ) {
			$parts[] = '';
			$parts[] = (string) $settings['manual_payment_instructions'];
		}
		return implode( "\n", $parts );
	}

	/**
	 * @return string[]
	 */
	private static function hotel_contact_lines( $settings ) {
		$lines = array( self::hotel_name( $settings ) );
		foreach ( array( 'address', 'phone', 'email' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				$lines[] = $settings[ $key ];
			}
		}
		return $lines;
	}

	private static function payment_method_label( $method, $settings ) {
		switch ( $method ) {
			case 'stripe':
				return __( 'Card (Stripe)', 'staynexus-hotel-manager' );
			case 'manual':
				return ! empty( $settings['manual_payment_title'] )
					? $settings['manual_payment_title']
					: __( 'Bank transfer / Manual payment', 'staynexus-hotel-manager' );
			default:
				return __( 'Pay at hotel', 'staynexus-hotel-manager' );
		}
	}

	private static function hotel_name( $settings ) {
		return ! empty( $settings['hotel_name'] ) ? $settings['hotel_name'] : get_bloginfo( 'name' );
	}

	/**
	 * Hotel email from Settings, or the WordPress admin email.
	 */
	private static function hotel_email( $settings ) {
		$email = ! empty( $settings['email'] ) ? sanitize_email( $settings['email'] ) : '';
		if ( $email && is_email( $email ) ) {
			return $email;
		}
		$admin = get_option( 'admin_email' );
		return is_email( $admin ) ? $admin : '';
	}

	private static function header_name( $name ) {
		return str_replace( array( "\r", "\n", '<', '>', '"' ), '', (string) $name );
	}

	private static function money( $amount, $settings ) {
		$symbol = ! empty( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$';
		return $symbol . number_format( (float) $amount, 2 );
	}
}
