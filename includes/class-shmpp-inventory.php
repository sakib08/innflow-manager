<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Inventory helpers for date-slot availability.
 */
class ShmppInventory {

	/**
	 * Free rooms for a room type on a date (total/available minus booked).
	 *
	 * @param int    $room_type_id Room type ID.
	 * @param string $date         Y-m-d.
	 * @return int
	 */
	public static function free_rooms( $room_type_id, $date ) {
		$state = self::night_state( $room_type_id, $date );
		return $state['free'];
	}

	/**
	 * Build availability map for mapped room types over a date range.
	 *
	 * @param int[]  $room_type_ids Room type IDs.
	 * @param string $date_from     Y-m-d.
	 * @param string $date_to       Y-m-d inclusive.
	 * @return array<int,array<string,int>> room_type_id => date => free count
	 */
	public static function availability_range( $room_type_ids, $date_from, $date_to ) {
		$result = array();
		foreach ( (array) $room_type_ids as $room_type_id ) {
			$room_type_id = (int) $room_type_id;
			$date         = $date_from;
			$result[ $room_type_id ] = array();
			while ( $date <= $date_to ) {
				$result[ $room_type_id ][ $date ] = self::free_rooms( $room_type_id, $date );
				$date = self::next_date( $date );
			}
		}
		return $result;
	}

	/**
	 * Price for a room type on a date.
	 *
	 * @param int    $room_type_id Room type ID.
	 * @param string $date         Y-m-d.
	 * @return float
	 */
	public static function price_for_date( $room_type_id, $date ) {
		$state = self::night_state( $room_type_id, $date );
		return $state['price'];
	}

	/**
	 * Quote a stay the same way a charge is calculated.
	 *
	 * Each night uses price_override when set, otherwise the room base price.
	 * A night is unavailable when its slot is closed or free rooms are below the request.
	 *
	 * @param int    $room_type_id Room type ID.
	 * @param string $check_in     Y-m-d.
	 * @param string $check_out    Y-m-d.
	 * @param int    $rooms_count  Rooms requested.
	 * @return array{available:bool,reason:?string,blocked_date:?string,nights:int,rooms_count:int,subtotal:float,available_rooms:int,price_per_night:float}|WP_Error
	 */
	public static function quote( $room_type_id, $check_in, $check_out, $rooms_count = 1 ) {
		return self::evaluate_stay( $room_type_id, $check_in, $check_out, $rooms_count, false );
	}

	/**
	 * Nights in [check_in, check_out).
	 *
	 * @param string $check_in  Y-m-d.
	 * @param string $check_out Y-m-d.
	 * @return int
	 */
	public static function nights( $check_in, $check_out ) {
		return self::night_count(
			sanitize_text_field( (string) $check_in ),
			sanitize_text_field( (string) $check_out )
		);
	}

	/**
	 * Reserve rooms only when every night in the quote is still open.
	 *
	 * Locks the date rows, re-reads the quote, then increments booked_rooms.
	 * The returned subtotal is the amount that should be charged.
	 *
	 * @param int    $room_type_id Room type ID.
	 * @param string $check_in     Y-m-d.
	 * @param string $check_out    Y-m-d.
	 * @param int    $rooms_count  Rooms to reserve.
	 * @return array{available:bool,reason:?string,blocked_date:?string,nights:int,rooms_count:int,subtotal:float,available_rooms:int,price_per_night:float}|WP_Error
	 */
	public static function hold( $room_type_id, $check_in, $check_out, $rooms_count = 1 ) {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );
		$quote = self::evaluate_stay( $room_type_id, $check_in, $check_out, $rooms_count, true );
		if ( is_wp_error( $quote ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $quote;
		}
		if ( empty( $quote['available'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::unavailable_error( $quote );
		}

		self::adjust_booked( $room_type_id, $check_in, $check_out, (int) $quote['rooms_count'] );
		$wpdb->query( 'COMMIT' );
		return $quote;
	}

	/**
	 * @param array{reason:?string,blocked_date:?string} $quote Failed quote.
	 * @return WP_Error
	 */
	public static function unavailable_error( $quote ) {
		$date   = ! empty( $quote['blocked_date'] ) ? (string) $quote['blocked_date'] : '';
		$reason = isset( $quote['reason'] ) ? (string) $quote['reason'] : 'full';
		if ( 'closed' === $reason ) {
			$message = $date
				? sprintf( 'This room is closed on %s.', $date )
				: 'This room is closed for one or more nights.';
			return new WP_Error( 'date_closed', $message, array( 'status' => 409 ) );
		}

		$message = $date
			? sprintf( 'Not enough rooms left on %s.', $date )
			: 'Not enough rooms left for these dates.';
		return new WP_Error( 'unavailable', $message, array( 'status' => 409 ) );
	}

	/**
	 * Increment booked_rooms for each night in [check_in, check_out).
	 *
	 * @param int    $room_type_id Room type ID.
	 * @param string $check_in     Y-m-d.
	 * @param string $check_out    Y-m-d.
	 * @param int    $rooms_count  Rooms to reserve.
	 */
	public static function reserve( $room_type_id, $check_in, $check_out, $rooms_count = 1 ) {
		self::adjust_booked( $room_type_id, $check_in, $check_out, absint( $rooms_count ) );
	}

	/**
	 * Decrement booked_rooms for each night in [check_in, check_out).
	 *
	 * @param int    $room_type_id Room type ID.
	 * @param string $check_in     Y-m-d.
	 * @param string $check_out    Y-m-d.
	 * @param int    $rooms_count  Rooms to release.
	 */
	public static function release( $room_type_id, $check_in, $check_out, $rooms_count = 1 ) {
		self::adjust_booked( $room_type_id, $check_in, $check_out, -1 * absint( $rooms_count ) );
	}

	/**
	 * @param int    $room_type_id Room type ID.
	 * @param string $check_in     Y-m-d.
	 * @param string $check_out    Y-m-d.
	 * @param int    $delta        Positive to reserve, negative to release.
	 */
	/**
	 * Walk each night and price it. Optionally lock slot rows.
	 *
	 * @param int    $room_type_id Room type ID.
	 * @param string $check_in     Y-m-d.
	 * @param string $check_out    Y-m-d.
	 * @param int    $rooms_count  Rooms requested.
	 * @param bool   $lock         SELECT ... FOR UPDATE when true.
	 * @return array{available:bool,reason:?string,blocked_date:?string,nights:int,rooms_count:int,subtotal:float,available_rooms:int,price_per_night:float}|WP_Error
	 */
	private static function evaluate_stay( $room_type_id, $check_in, $check_out, $rooms_count, $lock ) {
		$room_type_id = (int) $room_type_id;
		$rooms_count  = max( 1, (int) $rooms_count );
		$check_in     = sanitize_text_field( (string) $check_in );
		$check_out    = sanitize_text_field( (string) $check_out );
		$nights       = self::night_count( $check_in, $check_out );
		if ( $nights < 1 ) {
			return new WP_Error( 'invalid_dates', 'Valid check-in and check-out dates are required', array( 'status' => 400 ) );
		}

		$min_available = PHP_INT_MAX;
		$total_price   = 0.0;
		$blocked       = null;
		$blocked_date  = null;
		$date          = $check_in;

		for ( $i = 0; $i < $nights; $i++ ) {
			if ( $lock ) {
				self::ensure_slot_for_lock( $room_type_id, $date );
			}
			$state = self::night_state( $room_type_id, $date, $lock );
			if ( $state['closed'] || $state['free'] < $rooms_count ) {
				$blocked      = $state['closed'] ? 'closed' : 'full';
				$blocked_date = $date;
				break;
			}
			$min_available = min( $min_available, $state['free'] );
			$total_price  += $state['price'] * $rooms_count;
			$date          = self::next_date( $date );
		}

		$available = null === $blocked;
		return array(
			'available'       => $available,
			'reason'          => $blocked,
			'blocked_date'    => $blocked_date,
			'nights'          => $nights,
			'rooms_count'     => $rooms_count,
			'subtotal'        => $available ? round( $total_price, 2 ) : 0.0,
			'available_rooms' => ( $available && $min_available !== PHP_INT_MAX ) ? $min_available : 0,
			'price_per_night' => ( $available && $nights > 0 )
				? round( $total_price / $nights / $rooms_count, 2 )
				: 0.0,
		);
	}

	/**
	 * Create the date row when missing so a lock has a row to wait on.
	 *
	 * @param int    $room_type_id Room type ID.
	 * @param string $date         Y-m-d.
	 */
	private static function ensure_slot_for_lock( $room_type_id, $date ) {
		global $wpdb;
		$rooms = ShmppDatabase::table( 'room_types' );
		$slots = ShmppDatabase::table( 'booking_date_slots' );
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (room_type_id, slot_date, available_rooms, booked_rooms, status)
				SELECT %d, %s, total_rooms, 0, %s FROM %i WHERE id = %d AND deleted_at IS NULL
				ON DUPLICATE KEY UPDATE room_type_id = room_type_id',
				$slots,
				(int) $room_type_id,
				$date,
				'open',
				$rooms,
				(int) $room_type_id
			)
		);
	}

	/**
	 * Free count, nightly price, and closed flag for one date.
	 *
	 * @param int    $room_type_id Room type ID.
	 * @param string $date         Y-m-d.
	 * @param bool   $lock         Lock the slot row when true.
	 * @return array{free:int,price:float,closed:bool}
	 */
	private static function night_state( $room_type_id, $date, $lock = false ) {
		global $wpdb;
		$room_type_id = (int) $room_type_id;
		$date         = sanitize_text_field( (string) $date );
		$rooms        = ShmppDatabase::table( 'room_types' );
		$slots        = ShmppDatabase::table( 'booking_date_slots' );

		$room = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT total_rooms, base_price FROM %i WHERE id = %d AND deleted_at IS NULL',
				$rooms,
				$room_type_id
			),
			ARRAY_A
		);
		$total = $room ? (int) $room['total_rooms'] : 0;
		$base  = $room ? (float) $room['base_price'] : 0.0;

		if ( $lock ) {
			$slot = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT available_rooms, booked_rooms, status, price_override FROM %i WHERE room_type_id = %d AND slot_date = %s AND deleted_at IS NULL FOR UPDATE',
					$slots,
					$room_type_id,
					$date
				),
				ARRAY_A
			);
		} else {
			$slot = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT available_rooms, booked_rooms, status, price_override FROM %i WHERE room_type_id = %d AND slot_date = %s AND deleted_at IS NULL',
					$slots,
					$room_type_id,
					$date
				),
				ARRAY_A
			);
		}

		if ( ! $slot ) {
			return array(
				'free'   => max( 0, $total ),
				'price'  => $base,
				'closed' => false,
			);
		}

		$closed = ( 'closed' === $slot['status'] );
		$capacity = null !== $slot['available_rooms'] ? (int) $slot['available_rooms'] : $total;
		$free     = $closed ? 0 : max( 0, $capacity - (int) $slot['booked_rooms'] );
		$price    = ( null !== $slot['price_override'] && '' !== $slot['price_override'] )
			? (float) $slot['price_override']
			: $base;

		return array(
			'free'   => $free,
			'price'  => $price,
			'closed' => $closed,
		);
	}

	/**
	 * @param string $check_in  Y-m-d.
	 * @param string $check_out Y-m-d.
	 * @return int
	 */
	private static function night_count( $check_in, $check_out ) {
		$zone = new DateTimeZone( 'UTC' );
		$in   = date_create_immutable( $check_in . ' 00:00:00', $zone );
		$out  = date_create_immutable( $check_out . ' 00:00:00', $zone );
		if ( ! $in || ! $out || $out <= $in ) {
			return 0;
		}
		return (int) $in->diff( $out )->days;
	}

	/**
	 * @param string $date Y-m-d.
	 * @return string
	 */
	private static function next_date( $date ) {
		$dt = date_create_immutable( $date . ' 00:00:00', new DateTimeZone( 'UTC' ) );
		if ( ! $dt ) {
			return $date;
		}
		return $dt->modify( '+1 day' )->format( 'Y-m-d' );
	}

	private static function adjust_booked( $room_type_id, $check_in, $check_out, $delta ) {
		global $wpdb;
		$room_type_id = (int) $room_type_id;
		$check_in     = sanitize_text_field( $check_in );
		$check_out    = sanitize_text_field( $check_out );
		$delta        = (int) $delta;
		if ( ! $room_type_id || ! $check_in || ! $check_out || 0 === $delta ) {
			return;
		}

		$table  = ShmppDatabase::table( 'booking_date_slots' );
		$rooms  = ShmppDatabase::table( 'room_types' );
		$total  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT total_rooms FROM %i WHERE id = %d', $rooms, $room_type_id ) );
		$nights = self::night_count( $check_in, $check_out );
		$date   = $check_in;

		for ( $i = 0; $i < $nights; $i++ ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE room_type_id = %d AND slot_date = %s AND deleted_at IS NULL',
					$table,
					$room_type_id,
					$date
				),
				ARRAY_A
			);

			if ( $existing ) {
				$booked = max( 0, (int) $existing['booked_rooms'] + $delta );
				$wpdb->update(
					$table,
					array( 'booked_rooms' => $booked ),
					array( 'id' => (int) $existing['id'] )
				);
			} elseif ( $delta > 0 ) {
				$wpdb->insert(
					$table,
					array(
						'room_type_id'    => $room_type_id,
						'slot_date'       => $date,
						'available_rooms' => $total,
						'booked_rooms'    => $delta,
						'status'          => 'open',
					)
				);
			}

			$date = self::next_date( $date );
		}
	}
}
