<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

class ShmppAmenitiesController {

	const NS = 'staynexushm/v1';

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/amenities',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_amenities' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_amenity' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/amenities/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_amenity' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);
	}

	public function list_amenities() {
		global $wpdb;
		$table = ShmppDatabase::table( 'amenities' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE deleted_at IS NULL AND 1 = %d ORDER BY name ASC', $table, 1 ),
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	public function create_amenity( $request ) {
		global $wpdb;
		$wpdb->insert(
			ShmppDatabase::table( 'amenities' ),
			array(
				'name'        => sanitize_text_field( $request->get_param( 'name' ) ),
				'icon'        => sanitize_text_field( $request->get_param( 'icon' ) ),
				'description' => sanitize_textarea_field( $request->get_param( 'description' ) ),
			)
		);
		return $this->list_amenities();
	}

	public function delete_amenity( $request ) {
		$result = ShmppTrash::trash( 'amenities', (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'trashed' => true, 'id' => (int) $request['id'] ) );
	}
}
