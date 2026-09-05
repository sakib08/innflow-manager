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
		global $wpdb;
		$room_type_id = (int) $room_type_id;
		$date         = sanitize_text_field( $date );
		$rooms        = ShmppDatabase::table( 'room_types' );
		$slots        = ShmppDatabase::table( 'booking_date_slots' );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT total_rooms FROM %i WHERE id = %d AND deleted_at IS NULL', $rooms, $room_type_id )
		);

		$slot = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT available_rooms, booked_rooms, status FROM %i WHERE room_type_id = %d AND slot_date = %s AND deleted_at IS NULL',
				$slots,
				$room_type_id,
				$date
			),
			ARRAY_A
		);

		if ( $slot ) {
			if ( 'closed' === $slot['status'] ) {
				return 0;
			}
			$capacity = null !== $slot['available_rooms'] ? (int) $slot['available_rooms'] : $total;
			return max( 0, $capacity - (int) $slot['booked_rooms'] );
		}

		return max( 0, $total );
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
				$date = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
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
		global $wpdb;
		$rooms = ShmppDatabase::table( 'room_types' );
		$slots = ShmppDatabase::table( 'booking_date_slots' );

		$base = (float) $wpdb->get_var(
			$wpdb->prepare( 'SELECT base_price FROM %i WHERE id = %d', $rooms, (int) $room_type_id )
		);

		$override = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT price_override FROM %i WHERE room_type_id = %d AND slot_date = %s AND deleted_at IS NULL',
				$slots,
				(int) $room_type_id,
				sanitize_text_field( $date )
			)
		);

		if ( null !== $override && '' !== $override ) {
			return (float) $override;
		}
		return $base;
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
	private static function adjust_booked( $room_type_id, $check_in, $check_out, $delta ) {
		global $wpdb;
		$room_type_id = (int) $room_type_id;
		$check_in     = sanitize_text_field( $check_in );
		$check_out    = sanitize_text_field( $check_out );
		$delta        = (int) $delta;
		if ( ! $room_type_id || ! $check_in || ! $check_out || 0 === $delta ) {
			return;
		}

		$table = ShmppDatabase::table( 'booking_date_slots' );
		$rooms = ShmppDatabase::table( 'room_types' );
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT total_rooms FROM %i WHERE id = %d', $rooms, $room_type_id ) );
		$nights = (int) ( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS );
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

			$date = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
		}
	}
}
