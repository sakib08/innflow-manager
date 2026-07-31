<?php
defined( 'ABSPATH' ) || exit;

// Custom tables: table names cannot use prepare placeholders; queries are built from trusted InnflowManagerDatabase::table() keys.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

class InnflowManagerGuests_Controller {

	const NS = 'innflow-manager/v1';

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/guests',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_guests' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_guest' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/guests/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_guest' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_guest' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_guest' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
			)
		);
	}

	public function list_guests( $request ) {
		global $wpdb;
		$search = sanitize_text_field( $request->get_param( 'search' ) );
		$table  = InnflowManagerDatabase::table( 'guests' );
		$sql    = "SELECT * FROM {$table} WHERE " . InnflowManagerTrash::alive_sql();
		$params = array();
		if ( $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$sql     .= ' AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$params   = array( $like, $like, $like, $like );
		}
		$sql .= ' ORDER BY created_at DESC LIMIT 200';
		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A )
			: $wpdb->get_results( $sql, ARRAY_A );
		return rest_ensure_response( $rows );
	}

	public function get_guest( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );
		if ( ! $id ) {
			$attrs = $request->get_attributes();
			$id    = isset( $attrs['id'] ) ? (int) $attrs['id'] : 0;
		}
		$guest = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . InnflowManagerDatabase::table( 'guests' ) . ' WHERE id = %d AND ' . InnflowManagerTrash::alive_sql(), $id ),
			ARRAY_A
		);
		if ( ! $guest ) {
			return new WP_Error( 'not_found', 'Guest not found', array( 'status' => 404 ) );
		}

		$guest['bookings'] = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . InnflowManagerDatabase::table( 'bookings' ) . ' WHERE guest_id = %d AND ' . InnflowManagerTrash::alive_sql() . ' ORDER BY check_in DESC', $id ),
			ARRAY_A
		);
		$guest['checkins'] = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . InnflowManagerDatabase::table( 'guest_checkin_checkout' ) . ' WHERE guest_id = %d AND ' . InnflowManagerTrash::alive_sql(), $id ),
			ARRAY_A
		);

		return rest_ensure_response( $guest );
	}

	public function create_guest( $request ) {
		global $wpdb;
		$data = $this->sanitize( $request );
		$wpdb->insert( InnflowManagerDatabase::table( 'guests' ), $data );
		$req = new WP_REST_Request( 'GET' );
		$req->set_param( 'id', (int) $wpdb->insert_id );
		return $this->get_guest( $req );
	}

	public function update_guest( $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		$wpdb->update( InnflowManagerDatabase::table( 'guests' ), $this->sanitize( $request ), array( 'id' => $id ) );
		return $this->get_guest( $request );
	}

	public function delete_guest( $request ) {
		$result = InnflowManagerTrash::trash( 'guests', (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'trashed' => true, 'id' => (int) $request['id'] ) );
	}

	private function sanitize( $request ) {
		return array(
			'first_name' => sanitize_text_field( $request->get_param( 'first_name' ) ),
			'last_name'  => sanitize_text_field( $request->get_param( 'last_name' ) ),
			'email'      => sanitize_email( $request->get_param( 'email' ) ),
			'phone'      => sanitize_text_field( $request->get_param( 'phone' ) ),
			'address'    => sanitize_textarea_field( $request->get_param( 'address' ) ),
			'city'       => sanitize_text_field( $request->get_param( 'city' ) ),
			'country'    => sanitize_text_field( $request->get_param( 'country' ) ),
			'id_type'    => sanitize_text_field( $request->get_param( 'id_type' ) ),
			'id_number'  => sanitize_text_field( $request->get_param( 'id_number' ) ),
			'notes'      => sanitize_textarea_field( $request->get_param( 'notes' ) ),
		);
	}
}
