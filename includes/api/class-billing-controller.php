<?php
defined( 'ABSPATH' ) || exit;

// Custom tables: table names cannot use prepare placeholders; queries are built from trusted ShmppDatabase::table() keys.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

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
					'permission_callback' => array( 'ShmppRestAPI', 'permission_public' ),
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
			'room'                   => $this->sum_table( 'room_bills', 'total_amount', 'bill_date', $range ),
			'restaurant'             => $this->sum_table( 'restaurant_bills', 'total_amount', 'bill_date', $range ),
			'non_border_restaurant'  => $this->sum_table( 'non_border_restaurant_bills', 'total_amount', 'bill_date', $range ),
			'laundry'                => $this->sum_table( 'laundry_bills', 'total_amount', 'bill_date', $range ),
			'damage'                 => $this->sum_table( 'damage_bills', 'total_amount', 'bill_date', $range ),
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
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . ShmppDatabase::table( 'room_bills' ) . ' WHERE ' . ShmppTrash::alive_sql() . ' ORDER BY bill_date DESC LIMIT 200',
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
		$data     = array(
			'booking_id'              => (int) $request->get_param( 'booking_id' ),
			'guest_id'                => (int) $request->get_param( 'guest_id' ),
			'description'             => sanitize_text_field( $request->get_param( 'description' ) ),
			'amount'                  => $amount,
			'tax_amount'              => $tax,
			'total_amount'            => $amount + $tax,
			'bill_date'               => sanitize_text_field( $request->get_param( 'bill_date' ) ?: gmdate( 'Y-m-d' ) ),
			'payment_status'          => sanitize_text_field( $request->get_param( 'payment_status' ) ?: 'unpaid' ),
			'offline_payment_type_id' => $request->get_param( 'offline_payment_type_id' ) ? (int) $request->get_param( 'offline_payment_type_id' ) : null,
		);
		$wpdb->insert( ShmppDatabase::table( 'room_bills' ), $data );
		$id = (int) $wpdb->insert_id;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ShmppDatabase::table( 'room_bills' ) . ' WHERE id = %d', $id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	public function list_restaurant_bills() {
		global $wpdb;
		$bills  = ShmppDatabase::table( 'restaurant_bills' );
		$rest   = ShmppDatabase::table( 'restaurants' );
		$rows   = $wpdb->get_results(
			"SELECT b.*, r.name AS restaurant_name FROM {$bills} b LEFT JOIN {$rest} r ON r.id = b.restaurant_id WHERE " . ShmppTrash::alive_sql( 'b' ) . ' ORDER BY b.bill_date DESC LIMIT 200',
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
		$data     = array(
			'restaurant_id'           => (int) $request->get_param( 'restaurant_id' ),
			'bill_number'             => 'RB-' . strtoupper( wp_generate_password( 8, false, false ) ),
			'bill_date'               => sanitize_text_field( $request->get_param( 'bill_date' ) ?: current_time( 'mysql' ) ),
			'subtotal'                => $subtotal,
			'tax_amount'              => $tax,
			'total_amount'            => $subtotal + $tax,
			'payment_status'          => sanitize_text_field( $request->get_param( 'payment_status' ) ?: 'unpaid' ),
			'offline_payment_type_id' => $request->get_param( 'offline_payment_type_id' ) ? (int) $request->get_param( 'offline_payment_type_id' ) : null,
			'notes'                   => sanitize_textarea_field( $request->get_param( 'notes' ) ),
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

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ShmppDatabase::table( 'restaurant_bills' ) . ' WHERE id = %d', $bill_id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	public function list_non_border() {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . ShmppDatabase::table( 'non_border_restaurant_bills' ) . ' WHERE ' . ShmppTrash::alive_sql() . ' ORDER BY bill_date DESC LIMIT 200',
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
		$data     = array(
			'restaurant_id'           => (int) $request->get_param( 'restaurant_id' ),
			'bill_number'             => 'NB-' . strtoupper( wp_generate_password( 8, false, false ) ),
			'guest_name'              => sanitize_text_field( $request->get_param( 'guest_name' ) ),
			'guest_phone'             => sanitize_text_field( $request->get_param( 'guest_phone' ) ),
			'bill_date'               => sanitize_text_field( $request->get_param( 'bill_date' ) ?: current_time( 'mysql' ) ),
			'subtotal'                => $subtotal,
			'tax_amount'              => $tax,
			'total_amount'            => $subtotal + $tax,
			'payment_status'          => sanitize_text_field( $request->get_param( 'payment_status' ) ?: 'paid' ),
			'offline_payment_type_id' => $request->get_param( 'offline_payment_type_id' ) ? (int) $request->get_param( 'offline_payment_type_id' ) : null,
			'notes'                   => sanitize_textarea_field( $request->get_param( 'notes' ) ),
		);
		$wpdb->insert( ShmppDatabase::table( 'non_border_restaurant_bills' ), $data );
		$id  = (int) $wpdb->insert_id;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ShmppDatabase::table( 'non_border_restaurant_bills' ) . ' WHERE id = %d', $id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	public function list_laundry() {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . ShmppDatabase::table( 'laundry_bills' ) . ' WHERE ' . ShmppTrash::alive_sql() . ' ORDER BY bill_date DESC LIMIT 200',
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
		$data     = array(
			'guest_id'                => (int) $request->get_param( 'guest_id' ),
			'booking_id'              => $request->get_param( 'booking_id' ) ? (int) $request->get_param( 'booking_id' ) : null,
			'bill_number'             => 'LB-' . strtoupper( wp_generate_password( 8, false, false ) ),
			'bill_date'               => sanitize_text_field( $request->get_param( 'bill_date' ) ?: gmdate( 'Y-m-d' ) ),
			'items_description'       => sanitize_textarea_field( $request->get_param( 'items_description' ) ),
			'amount'                  => $amount,
			'tax_amount'              => $tax,
			'total_amount'            => $amount + $tax,
			'payment_status'          => sanitize_text_field( $request->get_param( 'payment_status' ) ?: 'unpaid' ),
			'offline_payment_type_id' => $request->get_param( 'offline_payment_type_id' ) ? (int) $request->get_param( 'offline_payment_type_id' ) : null,
		);
		$wpdb->insert( ShmppDatabase::table( 'laundry_bills' ), $data );
		$id  = (int) $wpdb->insert_id;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ShmppDatabase::table( 'laundry_bills' ) . ' WHERE id = %d', $id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	public function list_damage() {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . ShmppDatabase::table( 'damage_bills' ) . ' WHERE ' . ShmppTrash::alive_sql() . ' ORDER BY bill_date DESC LIMIT 200',
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
		$data     = array(
			'guest_id'                => (int) $request->get_param( 'guest_id' ),
			'booking_id'              => $request->get_param( 'booking_id' ) ? (int) $request->get_param( 'booking_id' ) : null,
			'bill_number'             => 'DB-' . strtoupper( wp_generate_password( 8, false, false ) ),
			'bill_date'               => sanitize_text_field( $request->get_param( 'bill_date' ) ?: gmdate( 'Y-m-d' ) ),
			'damage_description'      => sanitize_textarea_field( $request->get_param( 'damage_description' ) ),
			'amount'                  => $amount,
			'tax_amount'              => $tax,
			'total_amount'            => $amount + $tax,
			'payment_status'          => sanitize_text_field( $request->get_param( 'payment_status' ) ?: 'unpaid' ),
			'offline_payment_type_id' => $request->get_param( 'offline_payment_type_id' ) ? (int) $request->get_param( 'offline_payment_type_id' ) : null,
		);
		$wpdb->insert( ShmppDatabase::table( 'damage_bills' ), $data );
		$id  = (int) $wpdb->insert_id;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ShmppDatabase::table( 'damage_bills' ) . ' WHERE id = %d', $id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	public function list_payment_types() {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . ShmppDatabase::table( 'offline_payment_types' ) . ' WHERE is_active = 1 AND ' . ShmppTrash::alive_sql() . ' ORDER BY name ASC',
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
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ShmppDatabase::table( 'offline_payment_types' ) . ' WHERE id = %d', $id ), ARRAY_A );
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

	private function sum_table( $table_key, $column, $date_col, $range ) {
		global $wpdb;
		$table = ShmppDatabase::table( $table_key );
		$sum   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM({$column}),0) FROM {$table} WHERE " . ShmppTrash::alive_sql() . " AND DATE({$date_col}) BETWEEN %s AND %s",
				$range['from'],
				$range['to']
			)
		);
		return round( (float) $sum, 2 );
	}
}
