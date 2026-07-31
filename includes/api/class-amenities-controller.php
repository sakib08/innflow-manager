<?php
defined( 'ABSPATH' ) || exit;

// Custom tables: table names cannot use prepare placeholders; queries are built from trusted InnflowManagerDatabase::table() keys.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

class InnflowManagerAmenities_Controller {

	const NS = 'innflow-manager/v1';

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/amenities',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_amenities' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_public' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_amenity' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/amenities/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_amenity' ),
				'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
			)
		);
	}

	public function list_amenities() {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . InnflowManagerDatabase::table( 'amenities' ) . ' WHERE ' . InnflowManagerTrash::alive_sql() . ' ORDER BY name ASC',
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	public function create_amenity( $request ) {
		global $wpdb;
		$wpdb->insert(
			InnflowManagerDatabase::table( 'amenities' ),
			array(
				'name'        => sanitize_text_field( $request->get_param( 'name' ) ),
				'icon'        => sanitize_text_field( $request->get_param( 'icon' ) ),
				'description' => sanitize_textarea_field( $request->get_param( 'description' ) ),
			)
		);
		return $this->list_amenities();
	}

	public function delete_amenity( $request ) {
		$result = InnflowManagerTrash::trash( 'amenities', (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'trashed' => true, 'id' => (int) $request['id'] ) );
	}
}
