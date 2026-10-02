<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

class ShmppBillingController {

	const NS = 'staynexushm/v1';

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/billing/overview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'overview' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/billing/room',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_room_bills' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_room_bill' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/billing/restaurant',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_restaurant_bills' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_restaurant_bill' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/billing/non-border-restaurant',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_non_border' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_non_border' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/billing/laundry',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_laundry' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_laundry' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/billing/damage',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_damage' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_damage' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);

		$bill_delete_map = array(
			'/billing/room/(?P<id>\d+)'                    => 'room_bills',
			'/billing/restaurant/(?P<id>\d+)'              => 'restaurant_bills',
			'/billing/non-border-restaurant/(?P<id>\d+)'   => 'non_border_bills',
			'/billing/laundry/(?P<id>\d+)'                 => 'laundry_bills',
			'/billing/damage/(?P<id>\d+)'                  => 'damage_bills',
			'/payment-types/(?P<id>\d+)'                   => 'payment_types',
		);

		foreach ( $bill_delete_map as $route => $type ) {
			register_rest_route(
				self::NS,
				$route,
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => function ( $request ) use ( $type ) {
						$result = ShmppTrash::trash( $type, (int) $request['id'] );
						if ( is_wp_error( $result ) ) {
							return $result;
						}
						return rest_ensure_response( array( 'trashed' => true, 'id' => (int) $request['id'], 'type' => $type ) );
					},
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				)
			);
		}

		register_rest_route(
			self::NS,
			'/payment-types',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_payment_types' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_payment_type' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);
	}

	public function overview( $request ) {
		global $wpdb;
		$period = sanitize_text_field( $request->get_param( 'period' ) ?: 'month' );
		$range  = $this->date_range( $period );

		$sums = array(
			'room'                  => $this->sum_table( 'room_bills', $range ),
			'restaurant'            => $this->sum_table( 'restaurant_bills', $range ),
			'non_border_restaurant' => $this->sum_table( 'non_border_restaurant_bills', $range ),
			'laundry'               => $this->sum_table( 'laundry_bills', $range ),
			'damage'                => $this->sum_table( 'damage_bills', $range ),
		);

		$sums['total'] = array_sum( $sums );

		return rest_ensure_response(
			array(
				'period' => $period,
				'from'   => $range['from'],
				'to'     => $range['to'],
				'sums'   => $sums,
			)
		);
	}

	public function list_room_bills() {
		global $wpdb;
		$table = ShmppDatabase::table( 'room_bills' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE deleted_at IS NULL ORDER BY bill_date DESC LIMIT %d', $table, 200 ),
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	public function create_room_bill( $request ) {
		global $wpdb;
		$settings = get_option( 'shmpp_settings', array() );
		$tax_rate = isset( $settings['tax_rate'] ) ? (float) $settings['tax_rate'] : 0;
		$amount   = (float) $request->get_param( 'amount' );
		$tax      = round( $amount * ( $tax_rate / 100 ), 2 );
		$payment  = $this->payment_fields_from_request( $request, 'unpaid' );
		if ( is_wp_error( $payment ) ) {
			return $payment;
		}
		$data     = array_merge(
			array(
				'booking_id'   => (int) $request->get_param( 'booking_id' ),
				'guest_id'     => (int) $request->get_param( 'guest_id' ),
				'description'  => sanitize_text_field( $request->get_param( 'description' ) ),
				'amount'       => $amount,
				'tax_amount'   => $tax,
				'total_amount' => $amount + $tax,
				'bill_date'    => sanitize_text_field( $request->get_param( 'bill_date' ) ?: gmdate( 'Y-m-d' ) ),
			),
			$payment
		);
		$wpdb->insert( ShmppDatabase::table( 'room_bills' ), $data );
		$id = (int) $wpdb->insert_id;
		$table = ShmppDatabase::table( 'room_bills' );
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	public function list_restaurant_bills() {
		global $wpdb;
		$bills  = ShmppDatabase::table( 'restaurant_bills' );
		$rest   = ShmppDatabase::table( 'restaurants' );
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT b.*, r.name AS restaurant_name FROM %i b LEFT JOIN %i r ON r.id = b.restaurant_id WHERE b.deleted_at IS NULL ORDER BY b.bill_date DESC LIMIT %d', $bills, $rest, 200
			),
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	public function create_restaurant_bill( $request ) {
		global $wpdb;
		$settings = get_option( 'shmpp_settings', array() );
		$tax_rate = isset( $settings['tax_rate'] ) ? (float) $settings['tax_rate'] : 0;
		$subtotal = (float) $request->get_param( 'subtotal' );
		$tax      = round( $subtotal * ( $tax_rate / 100 ), 2 );
		$payment  = $this->payment_fields_from_request( $request, 'unpaid' );
		if ( is_wp_error( $payment ) ) {
			return $payment;
		}
		$data     = array_merge(
			array(
				'restaurant_id' => (int) $request->get_param( 'restaurant_id' ),
				'bill_number'   => 'RB-' . strtoupper( wp_generate_password( 8, false, false ) ),
				'bill_date'     => sanitize_text_field( $request->get_param( 'bill_date' ) ?: current_time( 'mysql' ) ),
				'subtotal'      => $subtotal,
				'tax_amount'    => $tax,
				'total_amount'  => $subtotal + $tax,
				'notes'         => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			),
			$payment
		);
		$wpdb->insert( ShmppDatabase::table( 'restaurant_bills' ), $data );
		$bill_id = (int) $wpdb->insert_id;

		$guest_id   = (int) $request->get_param( 'guest_id' );
		$booking_id = $request->get_param( 'booking_id' ) ? (int) $request->get_param( 'booking_id' ) : null;
		if ( $guest_id ) {
			$wpdb->insert(
				ShmppDatabase::table( 'restaurant_guest_bills' ),
				array(
					'restaurant_bill_id' => $bill_id,
					'guest_id'           => $guest_id,
					'booking_id'         => $booking_id,
					'amount'             => $subtotal + $tax,
				)
			);
		}

		$rb_table = ShmppDatabase::table( 'restaurant_bills' );
		$row      = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $rb_table, $bill_id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	public function list_non_border() {
		global $wpdb;
		$table = ShmppDatabase::table( 'non_border_restaurant_bills' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE deleted_at IS NULL ORDER BY bill_date DESC LIMIT %d', $table, 200 ),
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	public function create_non_border( $request ) {
		global $wpdb;
		$settings = get_option( 'shmpp_settings', array() );
		$tax_rate = isset( $settings['tax_rate'] ) ? (float) $settings['tax_rate'] : 0;
		$subtotal = (float) $request->get_param( 'subtotal' );
		$tax      = round( $subtotal * ( $tax_rate / 100 ), 2 );
		$payment  = $this->payment_fields_from_request( $request, 'paid' );
		if ( is_wp_error( $payment ) ) {
			return $payment;
		}
		$data     = array_merge(
			array(
				'restaurant_id' => (int) $request->get_param( 'restaurant_id' ),
				'bill_number'   => 'NB-' . strtoupper( wp_generate_password( 8, false, false ) ),
				'guest_name'    => sanitize_text_field( $request->get_param( 'guest_name' ) ),
				'guest_phone'   => sanitize_text_field( $request->get_param( 'guest_phone' ) ),
				'bill_date'     => sanitize_text_field( $request->get_param( 'bill_date' ) ?: current_time( 'mysql' ) ),
				'subtotal'      => $subtotal,
				'tax_amount'    => $tax,
				'total_amount'  => $subtotal + $tax,
				'notes'         => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			),
			$payment
		);
		$wpdb->insert( ShmppDatabase::table( 'non_border_restaurant_bills' ), $data );
		$id  = (int) $wpdb->insert_id;
		$nb_table = ShmppDatabase::table( 'non_border_restaurant_bills' );
		$row      = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $nb_table, $id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	public function list_laundry() {
		global $wpdb;
		$table = ShmppDatabase::table( 'laundry_bills' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE deleted_at IS NULL ORDER BY bill_date DESC LIMIT %d', $table, 200 ),
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	public function create_laundry( $request ) {
		global $wpdb;
		$settings = get_option( 'shmpp_settings', array() );
		$tax_rate = isset( $settings['tax_rate'] ) ? (float) $settings['tax_rate'] : 0;
		$amount   = (float) $request->get_param( 'amount' );
		$tax      = round( $amount * ( $tax_rate / 100 ), 2 );
		$payment  = $this->payment_fields_from_request( $request, 'unpaid' );
		if ( is_wp_error( $payment ) ) {
			return $payment;
		}
		$data     = array_merge(
			array(
				'guest_id'          => (int) $request->get_param( 'guest_id' ),
				'booking_id'        => $request->get_param( 'booking_id' ) ? (int) $request->get_param( 'booking_id' ) : null,
				'bill_number'       => 'LB-' . strtoupper( wp_generate_password( 8, false, false ) ),
				'bill_date'         => sanitize_text_field( $request->get_param( 'bill_date' ) ?: gmdate( 'Y-m-d' ) ),
				'items_description' => sanitize_textarea_field( $request->get_param( 'items_description' ) ),
				'amount'            => $amount,
				'tax_amount'        => $tax,
				'total_amount'      => $amount + $tax,
			),
			$payment
		);
		$wpdb->insert( ShmppDatabase::table( 'laundry_bills' ), $data );
		$id  = (int) $wpdb->insert_id;
		$lb_table = ShmppDatabase::table( 'laundry_bills' );
		$row      = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $lb_table, $id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	public function list_damage() {
		global $wpdb;
		$table = ShmppDatabase::table( 'damage_bills' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE deleted_at IS NULL ORDER BY bill_date DESC LIMIT %d', $table, 200 ),
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	public function create_damage( $request ) {
		global $wpdb;
		$settings = get_option( 'shmpp_settings', array() );
		$tax_rate = isset( $settings['tax_rate'] ) ? (float) $settings['tax_rate'] : 0;
		$amount   = (float) $request->get_param( 'amount' );
		$tax      = round( $amount * ( $tax_rate / 100 ), 2 );
		$payment  = $this->payment_fields_from_request( $request, 'unpaid' );
		if ( is_wp_error( $payment ) ) {
			return $payment;
		}
		$data     = array_merge(
			array(
				'guest_id'           => (int) $request->get_param( 'guest_id' ),
				'booking_id'         => $request->get_param( 'booking_id' ) ? (int) $request->get_param( 'booking_id' ) : null,
				'bill_number'        => 'DB-' . strtoupper( wp_generate_password( 8, false, false ) ),
				'bill_date'          => sanitize_text_field( $request->get_param( 'bill_date' ) ?: gmdate( 'Y-m-d' ) ),
				'damage_description' => sanitize_textarea_field( $request->get_param( 'damage_description' ) ),
				'amount'             => $amount,
				'tax_amount'         => $tax,
				'total_amount'       => $amount + $tax,
			),
			$payment
		);
		$wpdb->insert( ShmppDatabase::table( 'damage_bills' ), $data );
		$id  = (int) $wpdb->insert_id;
		$db_table = ShmppDatabase::table( 'damage_bills' );
		$row      = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $db_table, $id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	/**
	 * Shared payment_status / reference / offline type fields.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $default_status Default payment status.
	 * @return array|WP_Error
	 */
	private function payment_fields_from_request( $request, $default_status = 'unpaid' ) {
		$status    = sanitize_text_field( $request->get_param( 'payment_status' ) ?: $default_status );
		$reference = sanitize_text_field( $request->get_param( 'payment_reference' ) );
		if ( in_array( $status, array( 'paid' ), true ) && '' === $reference ) {
			return new WP_Error(
				'payment_reference_required',
				'A payment reference number is required for paid bills (cheque number, card transaction ID, transfer reference, etc.).',
				array( 'status' => 400 )
			);
		}
		return array(
			'payment_status'          => $status,
			'offline_payment_type_id' => $request->get_param( 'offline_payment_type_id' ) ? (int) $request->get_param( 'offline_payment_type_id' ) : null,
			'payment_reference'       => $reference ? $reference : null,
		);
	}

	public function list_payment_types() {
		global $wpdb;
		$table = ShmppDatabase::table( 'offline_payment_types' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE is_active = 1 AND deleted_at IS NULL AND 1 = %d ORDER BY name ASC', $table, 1 ),
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	public function create_payment_type( $request ) {
		global $wpdb;
		$name = sanitize_text_field( $request->get_param( 'name' ) );
		$slug = sanitize_title( $name );
		$wpdb->insert(
			ShmppDatabase::table( 'offline_payment_types' ),
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => sanitize_textarea_field( $request->get_param( 'description' ) ),
				'is_active'   => 1,
			)
		);
		$id  = (int) $wpdb->insert_id;
		$pt_table = ShmppDatabase::table( 'offline_payment_types' );
		$row      = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $pt_table, $id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	private function date_range( $period ) {
		$today = current_time( 'Y-m-d' );
		switch ( $period ) {
			case 'day':
				return array( 'from' => $today, 'to' => $today );
			case 'week':
				return array(
					'from' => gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( $today ) ) ),
					'to'   => gmdate( 'Y-m-d', strtotime( 'sunday this week', strtotime( $today ) ) ),
				);
			case 'month':
			default:
				return array(
					'from' => gmdate( 'Y-m-01', strtotime( $today ) ),
					'to'   => gmdate( 'Y-m-t', strtotime( $today ) ),
				);
		}
	}

	private function sum_table( $table_key, $range ) {
		global $wpdb;
		$table = ShmppDatabase::table( $table_key );
		if ( ! $table ) {
			return 0.0;
		}
		$sum = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COALESCE(SUM(total_amount),0) FROM %i WHERE deleted_at IS NULL AND DATE(bill_date) BETWEEN %s AND %s', $table, $range['from'],
				$range['to']
			)
		);
		return round( (float) $sum, 2 );
	}
}
