<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

class ShmppDashboardController {

	const NS = 'staynexushm/v1';

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dashboard' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);
	}

	public function get_dashboard( $request ) {
		$period = sanitize_text_field( $request->get_param( 'period' ) ?: 'week' );
		$range  = $this->date_range( $period );

		return rest_ensure_response(
			array(
				'period'           => $period,
				'from'             => $range['from'],
				'to'               => $range['to'],
				'guest_overview'   => $this->guest_overview( $range ),
				'billing_overview' => $this->billing_overview( $range ),
				'recent_bookings'  => $this->recent_bookings(),
				'occupancy'        => $this->occupancy( $range ),
				'daily_series'     => $this->daily_series( $range ),
			)
		);
	}

	private function guest_overview( $range ) {
		global $wpdb;
		$bookings = ShmppDatabase::table( 'bookings' );
		$check    = ShmppDatabase::table( 'guest_checkin_checkout' );

		$arrivals = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE deleted_at IS NULL AND check_in BETWEEN %s AND %s AND booking_status != \'cancelled\'', $bookings, $range['from'],
				$range['to']
			)
		);

		$departures = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE deleted_at IS NULL AND check_out BETWEEN %s AND %s AND booking_status != \'cancelled\'', $bookings, $range['from'],
				$range['to']
			)
		);

		$in_house = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE deleted_at IS NULL AND status = %s', $check, 'checked_in'
			)
		);

		$expected = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE deleted_at IS NULL AND status = \'expected\' AND booking_id IN (
					SELECT id FROM %i WHERE deleted_at IS NULL AND check_in BETWEEN %s AND %s
				)', $check, $bookings, $range['from'],
				$range['to']
			)
		);

		$total_guests = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COALESCE(SUM(adults + children),0) FROM %i
				WHERE deleted_at IS NULL AND check_in <= %s AND check_out > %s AND booking_status IN (\'confirmed\',\'checked_in\')', $bookings, $range['to'],
				$range['from']
			)
		);

		$new_bookings = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE deleted_at IS NULL AND DATE(created_at) BETWEEN %s AND %s', $bookings, $range['from'],
				$range['to']
			)
		);

		return array(
			'arrivals'      => $arrivals,
			'departures'    => $departures,
			'in_house'      => $in_house,
			'expected'      => $expected,
			'total_guests'  => $total_guests,
			'new_bookings'  => $new_bookings,
		);
	}

	private function billing_overview( $range ) {
		return array(
			'rooms'                  => $this->sum_bills( 'room_bills', $range ),
			'restaurants'            => $this->sum_bills( 'restaurant_bills', $range ),
			'non_border_restaurants' => $this->sum_bills( 'non_border_restaurant_bills', $range ),
			'laundry'                => $this->sum_bills( 'laundry_bills', $range ),
			'damage'                 => $this->sum_bills( 'damage_bills', $range ),
			'total'                  => 0,
		);
	}

	private function sum_bills( $table_key, $range ) {
		global $wpdb;
		$table = ShmppDatabase::table( $table_key );
		if ( ! $table ) {
			return 0.0;
		}
		$sum = (float) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COALESCE(SUM(total_amount),0) FROM %i WHERE deleted_at IS NULL AND DATE(bill_date) BETWEEN %s AND %s', $table, $range['from'],
				$range['to']
			)
		);
		return round( $sum, 2 );
	}

	private function recent_bookings() {
		global $wpdb;
		$bookings = ShmppDatabase::table( 'bookings' );
		$guests   = ShmppDatabase::table( 'guests' );
		$rooms    = ShmppDatabase::table( 'room_types' );
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT b.id, b.booking_code, b.check_in, b.check_out, b.total_amount, b.booking_status, b.payment_status,
					g.first_name, g.last_name, r.name AS room_name
				FROM %i b
				LEFT JOIN %i g ON g.id = b.guest_id
				LEFT JOIN %i r ON r.id = b.room_type_id
				WHERE b.deleted_at IS NULL
				ORDER BY b.created_at DESC LIMIT %d', $bookings, $guests, $rooms, 10
			),
			ARRAY_A
		);
	}

	private function occupancy( $range ) {
		global $wpdb;
		$rooms_table = ShmppDatabase::table( 'room_types' );
		$slots       = ShmppDatabase::table( 'booking_date_slots' );
		$total_rooms = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COALESCE(SUM(total_rooms),0) FROM %i WHERE status = %s AND deleted_at IS NULL', $rooms_table, 'active'
			)
		);

		$days = max( 1, (int) ( ( strtotime( $range['to'] ) - strtotime( $range['from'] ) ) / DAY_IN_SECONDS ) + 1 );
		$capacity = $total_rooms * $days;

		$booked = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COALESCE(SUM(booked_rooms),0) FROM %i WHERE deleted_at IS NULL AND slot_date BETWEEN %s AND %s', $slots, $range['from'],
				$range['to']
			)
		);

		$rate = $capacity > 0 ? round( ( $booked / $capacity ) * 100, 1 ) : 0;

		return array(
			'total_rooms'  => $total_rooms,
			'capacity'     => $capacity,
			'booked_nights'=> $booked,
			'rate'         => $rate,
		);
	}

	private function daily_series( $range ) {
		global $wpdb;
		$bookings = ShmppDatabase::table( 'bookings' );
		$room_bills = ShmppDatabase::table( 'room_bills' );
		$rest_bills = ShmppDatabase::table( 'restaurant_bills' );

		$series = array();
		$date   = $range['from'];
		while ( $date <= $range['to'] ) {
			$guests = (int) $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE deleted_at IS NULL AND check_in <= %s AND check_out > %s AND booking_status IN (\'confirmed\',\'checked_in\')', $bookings, $date,
					$date
				)
			);
			$room_rev = (float) $wpdb->get_var(
				$wpdb->prepare( 'SELECT COALESCE(SUM(total_amount),0) FROM %i WHERE bill_date = %s', $room_bills, $date )
			);
			$rest_rev = (float) $wpdb->get_var(
				$wpdb->prepare( 'SELECT COALESCE(SUM(total_amount),0) FROM %i WHERE DATE(bill_date) = %s', $rest_bills, $date )
			);
			$series[] = array(
				'date'       => $date,
				'guests'     => $guests,
				'room_billing' => round( $room_rev, 2 ),
				'restaurant_billing' => round( $rest_rev, 2 ),
			);
			$date = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
		}
		return $series;
	}

	private function date_range( $period ) {
		$today = current_time( 'Y-m-d' );
		switch ( $period ) {
			case 'day':
				return array( 'from' => $today, 'to' => $today );
			case 'month':
				return array(
					'from' => gmdate( 'Y-m-01', strtotime( $today ) ),
					'to'   => gmdate( 'Y-m-t', strtotime( $today ) ),
				);
			case 'week':
			default:
				return array(
					'from' => gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( $today ) ) ),
					'to'   => gmdate( 'Y-m-d', strtotime( 'sunday this week', strtotime( $today ) ) ),
				);
		}
	}
}
