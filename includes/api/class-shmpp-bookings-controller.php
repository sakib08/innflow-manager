<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

class ShmppBookingsController {

	const NS = 'staynexushm/v1';

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/bookings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_bookings' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_booking' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
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
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_booking' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_booking' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/bookings/(?P<id>\d+)/checkin',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'checkin' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/bookings/(?P<id>\d+)/checkout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'checkout' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/bookings/(?P<id>\d+)/mark-paid',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'mark_paid' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/discounts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_discounts' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_discount' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/discounts/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_discount' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/discounts/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_discount' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function list_bookings( $request ) {
		global $wpdb;
		$bookings = ShmppDatabase::table( 'bookings' );
		$guests   = ShmppDatabase::table( 'guests' );
		$rooms    = ShmppDatabase::table( 'room_types' );

		$status = sanitize_text_field( $request->get_param( 'status' ) );
		if ( $status ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT b.*, g.first_name, g.last_name, g.email, g.phone, r.name AS room_name
					FROM %i b
					LEFT JOIN %i g ON g.id = b.guest_id
					LEFT JOIN %i r ON r.id = b.room_type_id
					WHERE b.deleted_at IS NULL AND 1 = %d AND b.booking_status = %s
					ORDER BY b.created_at DESC LIMIT %d',
					$bookings,
					$guests,
					$rooms,
					1,
					$status,
					200
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT b.*, g.first_name, g.last_name, g.email, g.phone, r.name AS room_name
					FROM %i b
					LEFT JOIN %i g ON g.id = b.guest_id
					LEFT JOIN %i r ON r.id = b.room_type_id
					WHERE b.deleted_at IS NULL AND 1 = %d
					ORDER BY b.created_at DESC LIMIT %d',
					$bookings,
					$guests,
					$rooms,
					1,
					200
				),
				ARRAY_A
			);
		}

		return rest_ensure_response( $rows );
	}

	public function get_booking( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );
		if ( ! $id ) {
			$attrs = $request->get_attributes();
			$id    = isset( $attrs['id'] ) ? (int) $attrs['id'] : 0;
		}
		$bookings = ShmppDatabase::table( 'bookings' );
		$guests   = ShmppDatabase::table( 'guests' );
		$rooms    = ShmppDatabase::table( 'room_types' );
		$check    = ShmppDatabase::table( 'guest_checkin_checkout' );

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT b.*, g.first_name, g.last_name, g.email, g.phone, g.address, r.name AS room_name
				FROM %i b
				LEFT JOIN %i g ON g.id = b.guest_id
				LEFT JOIN %i r ON r.id = b.room_type_id
				WHERE b.id = %d AND b.deleted_at IS NULL', $bookings, $guests, $rooms, $id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Booking not found', array( 'status' => 404 ) );
		}

		$row['checkin_checkout'] = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE booking_id = %d AND deleted_at IS NULL', $check, $id ),
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

		$rooms_table = ShmppDatabase::table( 'room_types' );
		$room        = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND deleted_at IS NULL', $rooms_table, $room_type_id ), ARRAY_A );
		if ( ! $room ) {
			return new WP_Error( 'not_found', 'Room type not found', array( 'status' => 404 ) );
		}

		$payment_method = sanitize_text_field( $request->get_param( 'payment_method' ) ?: 'pay_at_hotel' );
		if ( ! in_array( $payment_method, array( 'pay_at_hotel', 'stripe', 'manual' ), true ) ) {
			$payment_method = 'pay_at_hotel';
		}
		$payment_status    = sanitize_text_field( $request->get_param( 'payment_status' ) ?: 'pending' );
		$payment_reference = sanitize_text_field( $request->get_param( 'payment_reference' ) );
		if ( 'paid' === $payment_status && '' === $payment_reference ) {
			return new WP_Error(
				'payment_reference_required',
				'A payment reference number is required when marking a booking as paid (cheque number, card transaction ID, transfer reference, etc.).',
				array( 'status' => 400 )
			);
		}

		// Same quote room search shows: per-night overrides, closed dates, and remaining rooms.
		$held = ShmppInventory::hold( $room_type_id, $check_in, $check_out, $rooms_count );
		if ( is_wp_error( $held ) ) {
			return $held;
		}

		$nights   = (int) $held['nights'];
		$subtotal = (float) $held['subtotal'];

		$settings   = get_option( 'shmpp_settings', array() );
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

		$wpdb->insert( ShmppDatabase::table( 'guests' ), $guest_data );
		$guest_id = (int) $wpdb->insert_id;
		if ( ! $guest_id ) {
			ShmppInventory::release( $room_type_id, $check_in, $check_out, $rooms_count );
			return new WP_Error( 'create_failed', 'Could not create booking.', array( 'status' => 500 ) );
		}

		$booking_code = 'SHM-' . strtoupper( wp_generate_password( 8, false, false ) );
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
			// This endpoint requires 'manage_options'; staff creating a booking may record
			// the payment status directly (e.g. paid in person at check-in).
			'payment_status'          => $payment_status,
			'payment_method'          => $payment_method,
			'payment_reference'       => $payment_reference ? $payment_reference : null,
			'booking_status'          => 'confirmed',
			'offline_payment_type_id' => $request->get_param( 'offline_payment_type_id' )
				? (int) $request->get_param( 'offline_payment_type_id' )
				: null,
			'source'                  => 'direct',
			'notes'                   => sanitize_textarea_field( $request->get_param( 'notes' ) ),
		);

		$wpdb->insert( ShmppDatabase::table( 'bookings' ), $booking_data );
		$booking_id = (int) $wpdb->insert_id;
		if ( ! $booking_id ) {
			ShmppInventory::release( $room_type_id, $check_in, $check_out, $rooms_count );
			return new WP_Error( 'create_failed', 'Could not create booking.', array( 'status' => 500 ) );
		}

		$wpdb->insert(
			ShmppDatabase::table( 'guest_checkin_checkout' ),
			array(
				'booking_id' => $booking_id,
				'guest_id'   => $guest_id,
				'status'     => 'expected',
			)
		);

		$wpdb->insert(
			ShmppDatabase::table( 'room_bills' ),
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

		do_action( 'shmpp_booking_inventory_changed', $room_type_id, $check_in, $check_out );

		if ( $discount_id ) {
			$disc_table = ShmppDatabase::table( 'discounts' );
			$wpdb->query(
				$wpdb->prepare( 'UPDATE %i SET used_count = used_count + 1 WHERE id = %d', $disc_table, $discount_id
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
		foreach ( array( 'booking_status', 'payment_status', 'payment_method', 'payment_reference', 'notes' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$data[ $field ] = sanitize_text_field( $request->get_param( $field ) );
			}
		}
		if ( null !== $request->get_param( 'offline_payment_type_id' ) ) {
			$ptid = $request->get_param( 'offline_payment_type_id' );
			$data['offline_payment_type_id'] = $ptid ? (int) $ptid : null;
		}
		if ( isset( $data['payment_status'] ) && 'paid' === $data['payment_status'] ) {
			$ref = isset( $data['payment_reference'] ) ? $data['payment_reference'] : $request->get_param( 'payment_reference' );
			$ref = is_string( $ref ) ? trim( $ref ) : '';
			if ( '' === $ref ) {
				return new WP_Error(
					'payment_reference_required',
					'A payment reference number is required when marking a booking as paid.',
					array( 'status' => 400 )
				);
			}
			$data['payment_reference'] = sanitize_text_field( $ref );
		}
		if ( $data ) {
			$wpdb->update( ShmppDatabase::table( 'bookings' ), $data, array( 'id' => $id ) );
			if ( isset( $data['payment_status'] ) && 'paid' === $data['payment_status'] ) {
				$this->mark_room_bills_paid(
					$id,
					isset( $data['offline_payment_type_id'] ) ? $data['offline_payment_type_id'] : null,
					isset( $data['payment_reference'] ) ? $data['payment_reference'] : null
				);
			}
		}
		return $this->get_booking( $request );
	}

	/**
	 * Record a manual / offline payment as paid.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function mark_paid( $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		if ( ! $id ) {
			return new WP_Error( 'invalid', 'Invalid booking', array( 'status' => 400 ) );
		}

		$reference = sanitize_text_field( $request->get_param( 'payment_reference' ) );
		if ( '' === $reference ) {
			return new WP_Error(
				'payment_reference_required',
				'Enter a payment reference number (cheque number, card transaction ID, bank transfer reference, etc.).',
				array( 'status' => 400 )
			);
		}

		$table = ShmppDatabase::table( 'bookings' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( 'SELECT id FROM %i WHERE id = %d AND deleted_at IS NULL', $table, $id ),
			ARRAY_A
		);
		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Booking not found', array( 'status' => 404 ) );
		}

		$offline_id = $request->get_param( 'offline_payment_type_id' )
			? (int) $request->get_param( 'offline_payment_type_id' )
			: null;

		$data = array(
			'payment_status'    => 'paid',
			'payment_reference' => $reference,
		);
		if ( $offline_id ) {
			$data['offline_payment_type_id'] = $offline_id;
		}

		$wpdb->update( $table, $data, array( 'id' => $id ) );
		$this->mark_room_bills_paid( $id, $offline_id, $reference );

		return $this->get_booking( $request );
	}

	/**
	 * @param int         $booking_id Booking ID.
	 * @param int|null    $offline_payment_type_id Optional offline type.
	 * @param string|null $payment_reference Payment reference number.
	 */
	private function mark_room_bills_paid( $booking_id, $offline_payment_type_id = null, $payment_reference = null ) {
		global $wpdb;
		$bill_data = array( 'payment_status' => 'paid' );
		if ( $offline_payment_type_id ) {
			$bill_data['offline_payment_type_id'] = (int) $offline_payment_type_id;
		}
		if ( $payment_reference ) {
			$bill_data['payment_reference'] = sanitize_text_field( $payment_reference );
		}
		$wpdb->update(
			ShmppDatabase::table( 'room_bills' ),
			$bill_data,
			array(
				'booking_id' => (int) $booking_id,
			)
		);
	}

	public function checkin( $request ) {
		global $wpdb;
		$id    = (int) $request['id'];
		$table = ShmppDatabase::table( 'guest_checkin_checkout' );
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
			ShmppDatabase::table( 'bookings' ),
			array( 'booking_status' => 'checked_in' ),
			array( 'id' => $id )
		);
		return $this->get_booking( $request );
	}

	public function checkout( $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		$wpdb->update(
			ShmppDatabase::table( 'guest_checkin_checkout' ),
			array(
				'checkout_at'    => current_time( 'mysql' ),
				'status'         => 'checked_out',
				'checked_out_by' => get_current_user_id(),
			),
			array( 'booking_id' => $id )
		);
		$wpdb->update(
			ShmppDatabase::table( 'bookings' ),
			array( 'booking_status' => 'checked_out' ),
			array( 'id' => $id )
		);
		return $this->get_booking( $request );
	}

	public function list_discounts() {
		global $wpdb;
		$table = ShmppDatabase::table( 'discounts' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE deleted_at IS NULL AND 1 = %d ORDER BY id DESC', $table, 1 ),
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
		$wpdb->insert( ShmppDatabase::table( 'discounts' ), $data );
		$id      = (int) $wpdb->insert_id;
		$d_table = ShmppDatabase::table( 'discounts' );
		$row     = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $d_table, $id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	public function delete_discount( $request ) {
		$result = ShmppTrash::trash( 'discounts', (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'trashed' => true, 'id' => (int) $request['id'] ) );
	}

	public function delete_booking( $request ) {
		$result = ShmppTrash::trash( 'bookings', (int) $request['id'] );
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
		$table = ShmppDatabase::table( 'discounts' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE code = %s AND status = \'active\' AND deleted_at IS NULL', $table, strtoupper( $code ) ),
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
}
