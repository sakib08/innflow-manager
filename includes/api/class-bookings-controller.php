<?php
defined( 'ABSPATH' ) || exit;

// Custom tables: table names cannot use prepare placeholders; queries are built from trusted InnflowManagerDatabase::table() keys.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

class InnflowManagerBookings_Controller {

	const NS = 'innflow-manager/v1';

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/bookings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_bookings' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_booking' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_public' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/bookings/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_booking' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_booking' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_booking' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/bookings/(?P<id>\d+)/checkin',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'checkin' ),
				'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/bookings/(?P<id>\d+)/checkout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'checkout' ),
				'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/discounts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_discounts' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_discount' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/discounts/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_discount' ),
				'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/discounts/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_discount' ),
				'permission_callback' => array( 'InnflowManagerRest_API', 'permission_public' ),
			)
		);
	}

	public function list_bookings( $request ) {
		global $wpdb;
		$bookings = InnflowManagerDatabase::table( 'bookings' );
		$guests   = InnflowManagerDatabase::table( 'guests' );
		$rooms    = InnflowManagerDatabase::table( 'room_types' );

		$status = sanitize_text_field( $request->get_param( 'status' ) );
		$sql    = "SELECT b.*, g.first_name, g.last_name, g.email, g.phone, r.name AS room_name
			FROM {$bookings} b
			LEFT JOIN {$guests} g ON g.id = b.guest_id
			LEFT JOIN {$rooms} r ON r.id = b.room_type_id
			WHERE " . InnflowManagerTrash::alive_sql( 'b' );
		$params = array();
		if ( $status ) {
			$sql     .= ' AND b.booking_status = %s';
			$params[] = $status;
		}
		$sql .= ' ORDER BY b.created_at DESC LIMIT 200';

		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A )
			: $wpdb->get_results( $sql, ARRAY_A );

		return rest_ensure_response( $rows );
	}

	public function get_booking( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );
		if ( ! $id ) {
			$attrs = $request->get_attributes();
			$id    = isset( $attrs['id'] ) ? (int) $attrs['id'] : 0;
		}
		$bookings = InnflowManagerDatabase::table( 'bookings' );
		$guests   = InnflowManagerDatabase::table( 'guests' );
		$rooms    = InnflowManagerDatabase::table( 'room_types' );
		$check    = InnflowManagerDatabase::table( 'guest_checkin_checkout' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*, g.first_name, g.last_name, g.email, g.phone, g.address, r.name AS room_name
				FROM {$bookings} b
				LEFT JOIN {$guests} g ON g.id = b.guest_id
				LEFT JOIN {$rooms} r ON r.id = b.room_type_id
				WHERE b.id = %d AND " . InnflowManagerTrash::alive_sql( 'b' ),
				$id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Booking not found', array( 'status' => 404 ) );
		}

		$row['checkin_checkout'] = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$check} WHERE booking_id = %d AND " . InnflowManagerTrash::alive_sql(), $id ),
			ARRAY_A
		);

		return rest_ensure_response( $row );
	}

	public function create_booking( $request ) {
		global $wpdb;

		$check_in  = sanitize_text_field( $request->get_param( 'check_in' ) );
		$check_out = sanitize_text_field( $request->get_param( 'check_out' ) );
		$room_type_id = (int) $request->get_param( 'room_type_id' );
		$adults    = max( 1, (int) $request->get_param( 'adults' ) );
		$children  = max( 0, (int) $request->get_param( 'children' ) );
		$rooms_count = max( 1, (int) $request->get_param( 'rooms_count' ) );

		$guest_data = array(
			'first_name' => sanitize_text_field( $request->get_param( 'first_name' ) ),
			'last_name'  => sanitize_text_field( $request->get_param( 'last_name' ) ),
			'email'      => sanitize_email( $request->get_param( 'email' ) ),
			'phone'      => sanitize_text_field( $request->get_param( 'phone' ) ),
			'address'    => sanitize_textarea_field( $request->get_param( 'address' ) ),
			'city'       => sanitize_text_field( $request->get_param( 'city' ) ),
			'country'    => sanitize_text_field( $request->get_param( 'country' ) ),
			'id_type'    => sanitize_text_field( $request->get_param( 'id_type' ) ),
			'id_number'  => sanitize_text_field( $request->get_param( 'id_number' ) ),
		);

		if ( empty( $guest_data['first_name'] ) || empty( $guest_data['last_name'] ) || ! $room_type_id ) {
			return new WP_Error( 'invalid', 'Guest name and room type are required', array( 'status' => 400 ) );
		}

		$rooms_table = InnflowManagerDatabase::table( 'room_types' );
		$room        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$rooms_table} WHERE id = %d", $room_type_id ), ARRAY_A );
		if ( ! $room ) {
			return new WP_Error( 'not_found', 'Room type not found', array( 'status' => 404 ) );
		}

		$nights   = (int) ( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS );
		$subtotal = (float) $room['base_price'] * $nights * $rooms_count;

		$settings   = get_option( 'ifmpp_settings', array() );
		$tax_rate   = isset( $settings['tax_rate'] ) ? (float) $settings['tax_rate'] : 0;
		$discount_amount = 0;
		$discount_id     = null;

		$code = sanitize_text_field( $request->get_param( 'discount_code' ) );
		if ( $code ) {
			$discount = $this->find_valid_discount( $code, $nights );
			if ( $discount ) {
				$discount_id = (int) $discount['id'];
				if ( 'percent' === $discount['discount_type'] ) {
					$discount_amount = $subtotal * ( (float) $discount['discount_value'] / 100 );
				} else {
					$discount_amount = (float) $discount['discount_value'];
				}
			}
		}

		$taxable = max( 0, $subtotal - $discount_amount );
		$tax     = round( $taxable * ( $tax_rate / 100 ), 2 );
		$total   = round( $taxable + $tax, 2 );

		$wpdb->insert( InnflowManagerDatabase::table( 'guests' ), $guest_data );
		$guest_id = (int) $wpdb->insert_id;

		$booking_code = 'IFM-' . strtoupper( wp_generate_password( 8, false, false ) );
		$booking_data = array(
			'booking_code'            => $booking_code,
			'guest_id'                => $guest_id,
			'room_type_id'            => $room_type_id,
			'check_in'                => $check_in,
			'check_out'               => $check_out,
			'adults'                  => $adults,
			'children'                => $children,
			'rooms_count'             => $rooms_count,
			'discount_id'             => $discount_id,
			'discount_amount'         => $discount_amount,
			'subtotal'                => $subtotal,
			'tax_amount'              => $tax,
			'total_amount'            => $total,
			'payment_status'          => sanitize_text_field( $request->get_param( 'payment_status' ) ?: 'pending' ),
			'booking_status'          => 'confirmed',
			'offline_payment_type_id' => $request->get_param( 'offline_payment_type_id' )
				? (int) $request->get_param( 'offline_payment_type_id' )
				: null,
			'notes'                   => sanitize_textarea_field( $request->get_param( 'notes' ) ),
		);

		$wpdb->insert( InnflowManagerDatabase::table( 'bookings' ), $booking_data );
		$booking_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			InnflowManagerDatabase::table( 'guest_checkin_checkout' ),
			array(
				'booking_id' => $booking_id,
				'guest_id'   => $guest_id,
				'status'     => 'expected',
			)
		);

		$wpdb->insert(
			InnflowManagerDatabase::table( 'room_bills' ),
			array(
				'booking_id'   => $booking_id,
				'guest_id'     => $guest_id,
				'description'  => sprintf( 'Room booking %s (%d nights)', $booking_code, $nights ),
				'amount'       => $taxable,
				'tax_amount'   => $tax,
				'total_amount' => $total,
				'bill_date'    => gmdate( 'Y-m-d' ),
				'payment_status' => $booking_data['payment_status'] === 'paid' ? 'paid' : 'unpaid',
			)
		);

		$this->reserve_slots( $room_type_id, $check_in, $check_out, $rooms_count );

		if ( $discount_id ) {
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . InnflowManagerDatabase::table( 'discounts' ) . ' SET used_count = used_count + 1 WHERE id = %d',
					$discount_id
				)
			);
		}

		$req = new WP_REST_Request( 'GET' );
		$req->set_param( 'id', $booking_id );
		return $this->get_booking( $req );
	}

	public function update_booking( $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		$data = array();
		foreach ( array( 'booking_status', 'payment_status', 'notes' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$data[ $field ] = sanitize_text_field( $request->get_param( $field ) );
			}
		}
		if ( $data ) {
			$wpdb->update( InnflowManagerDatabase::table( 'bookings' ), $data, array( 'id' => $id ) );
		}
		return $this->get_booking( $request );
	}

	public function checkin( $request ) {
		global $wpdb;
		$id    = (int) $request['id'];
		$table = InnflowManagerDatabase::table( 'guest_checkin_checkout' );
		$wpdb->update(
			$table,
			array(
				'checkin_at'  => current_time( 'mysql' ),
				'room_number' => sanitize_text_field( $request->get_param( 'room_number' ) ),
				'status'      => 'checked_in',
				'checked_in_by' => get_current_user_id(),
			),
			array( 'booking_id' => $id )
		);
		$wpdb->update(
			InnflowManagerDatabase::table( 'bookings' ),
			array( 'booking_status' => 'checked_in' ),
			array( 'id' => $id )
		);
		return $this->get_booking( $request );
	}

	public function checkout( $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		$wpdb->update(
			InnflowManagerDatabase::table( 'guest_checkin_checkout' ),
			array(
				'checkout_at'    => current_time( 'mysql' ),
				'status'         => 'checked_out',
				'checked_out_by' => get_current_user_id(),
			),
			array( 'booking_id' => $id )
		);
		$wpdb->update(
			InnflowManagerDatabase::table( 'bookings' ),
			array( 'booking_status' => 'checked_out' ),
			array( 'id' => $id )
		);
		return $this->get_booking( $request );
	}

	public function list_discounts() {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . InnflowManagerDatabase::table( 'discounts' ) . ' WHERE ' . InnflowManagerTrash::alive_sql() . ' ORDER BY id DESC',
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	public function create_discount( $request ) {
		global $wpdb;
		$data = array(
			'code'           => strtoupper( sanitize_text_field( $request->get_param( 'code' ) ) ),
			'name'           => sanitize_text_field( $request->get_param( 'name' ) ),
			'discount_type'  => sanitize_text_field( $request->get_param( 'discount_type' ) ?: 'percent' ),
			'discount_value' => (float) $request->get_param( 'discount_value' ),
			'min_nights'     => max( 1, (int) $request->get_param( 'min_nights' ) ),
			'max_uses'       => $request->get_param( 'max_uses' ) ? (int) $request->get_param( 'max_uses' ) : null,
			'starts_at'      => sanitize_text_field( $request->get_param( 'starts_at' ) ),
			'ends_at'        => sanitize_text_field( $request->get_param( 'ends_at' ) ),
			'status'         => 'active',
		);
		$wpdb->insert( InnflowManagerDatabase::table( 'discounts' ), $data );
		$id  = (int) $wpdb->insert_id;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . InnflowManagerDatabase::table( 'discounts' ) . ' WHERE id = %d', $id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	public function delete_discount( $request ) {
		$result = InnflowManagerTrash::trash( 'discounts', (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'trashed' => true, 'id' => (int) $request['id'] ) );
	}

	public function delete_booking( $request ) {
		$result = InnflowManagerTrash::trash( 'bookings', (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'trashed' => true, 'id' => (int) $request['id'] ) );
	}

	public function validate_discount( $request ) {
		$code   = sanitize_text_field( $request->get_param( 'code' ) );
		$nights = max( 1, (int) $request->get_param( 'nights' ) );
		$row    = $this->find_valid_discount( $code, $nights );
		if ( ! $row ) {
			return new WP_Error( 'invalid_code', 'Discount code is invalid or expired', array( 'status' => 400 ) );
		}
		return rest_ensure_response( $row );
	}

	private function find_valid_discount( $code, $nights ) {
		global $wpdb;
		$table = InnflowManagerDatabase::table( 'discounts' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s AND status = 'active' AND " . InnflowManagerTrash::alive_sql(), strtoupper( $code ) ),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		if ( (int) $row['min_nights'] > $nights ) {
			return null;
		}
		if ( null !== $row['max_uses'] && (int) $row['used_count'] >= (int) $row['max_uses'] ) {
			return null;
		}
		$now = current_time( 'mysql' );
		if ( $row['starts_at'] && $now < $row['starts_at'] ) {
			return null;
		}
		if ( $row['ends_at'] && $now > $row['ends_at'] ) {
			return null;
		}
		return $row;
	}

	private function reserve_slots( $room_type_id, $check_in, $check_out, $rooms_count ) {
		global $wpdb;
		$table  = InnflowManagerDatabase::table( 'booking_date_slots' );
		$rooms  = InnflowManagerDatabase::table( 'room_types' );
		$total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT total_rooms FROM {$rooms} WHERE id = %d", $room_type_id ) );
		$date   = $check_in;
		$nights = (int) ( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS );

		for ( $i = 0; $i < $nights; $i++ ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE room_type_id = %d AND slot_date = %s AND " . InnflowManagerTrash::alive_sql(),
					$room_type_id,
					$date
				),
				ARRAY_A
			);

			if ( $existing ) {
				$wpdb->update(
					$table,
					array( 'booked_rooms' => (int) $existing['booked_rooms'] + $rooms_count ),
					array( 'id' => (int) $existing['id'] )
				);
			} else {
				$wpdb->insert(
					$table,
					array(
						'room_type_id'    => $room_type_id,
						'slot_date'       => $date,
						'available_rooms' => $total,
						'booked_rooms'    => $rooms_count,
						'status'          => 'open',
					)
				);
			}
			$date = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
		}
	}
}
