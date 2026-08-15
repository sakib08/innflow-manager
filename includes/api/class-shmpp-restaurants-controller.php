<?php
defined( 'ABSPATH' ) || exit;

// Custom tables: table names cannot use prepare placeholders; queries are built from trusted ShmppDatabase::table() keys.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

class ShmppRestaurantsController {

	const NS = 'staynexushm/v1';

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/restaurants',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_restaurants' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_restaurant' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/restaurants/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_restaurant' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_restaurant' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);
	}

	public function list_restaurants() {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . ShmppDatabase::table( 'restaurants' ) . ' WHERE ' . ShmppTrash::alive_sql() . ' ORDER BY name ASC',
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	public function create_restaurant( $request ) {
		global $wpdb;
		$data = array(
			'name'          => sanitize_text_field( $request->get_param( 'name' ) ),
			'location'      => sanitize_text_field( $request->get_param( 'location' ) ),
			'phone'         => sanitize_text_field( $request->get_param( 'phone' ) ),
			'opening_hours' => sanitize_text_field( $request->get_param( 'opening_hours' ) ),
			'status'        => sanitize_text_field( $request->get_param( 'status' ) ?: 'active' ),
		);
		$wpdb->insert( ShmppDatabase::table( 'restaurants' ), $data );
		return $this->list_restaurants();
	}

	public function update_restaurant( $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		$data = array(
			'name'          => sanitize_text_field( $request->get_param( 'name' ) ),
			'location'      => sanitize_text_field( $request->get_param( 'location' ) ),
			'phone'         => sanitize_text_field( $request->get_param( 'phone' ) ),
			'opening_hours' => sanitize_text_field( $request->get_param( 'opening_hours' ) ),
			'status'        => sanitize_text_field( $request->get_param( 'status' ) ?: 'active' ),
		);
		$wpdb->update( ShmppDatabase::table( 'restaurants' ), $data, array( 'id' => $id ) );
		return $this->list_restaurants();
	}

	public function delete_restaurant( $request ) {
		$result = ShmppTrash::trash( 'restaurants', (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'trashed' => true, 'id' => (int) $request['id'] ) );
	}
}
