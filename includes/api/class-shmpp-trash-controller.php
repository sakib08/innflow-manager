<?php
defined( 'ABSPATH' ) || exit;

// Custom tables: table names cannot use prepare placeholders; queries are built from trusted ShmppDatabase::table() keys.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

class ShmppTrashController {

	public function register_routes() {
		register_rest_route(
			'staynexushm/v1',
			'/trash',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_trash' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'empty_trash' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			'staynexushm/v1',
			'/trash/counts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'counts' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		register_rest_route(
			'staynexushm/v1',
			'/trash/(?P<type>[a-z_]+)/(?P<id>\d+)/restore',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'restore' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		register_rest_route(
			'staynexushm/v1',
			'/trash/(?P<type>[a-z_]+)/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'force_delete' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		// Generic soft-delete: DELETE /items/{type}/{id}
		register_rest_route(
			'staynexushm/v1',
			'/items/(?P<type>[a-z_]+)/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'soft_delete' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);
	}

	public function list_trash( $request ) {
		$type = sanitize_key( (string) $request->get_param( 'type' ) );
		if ( $type && ! ShmppTrash::resolve( $type ) ) {
			return new WP_Error( 'invalid_type', 'Unknown trash type', array( 'status' => 400 ) );
		}
		return rest_ensure_response(
			array(
				'counts' => ShmppTrash::counts(),
				'items'  => ShmppTrash::list_items( $type ? $type : null ),
				'types'  => ShmppTrash::entities(),
			)
		);
	}

	public function counts() {
		return rest_ensure_response( ShmppTrash::counts() );
	}

	public function soft_delete( $request ) {
		$result = ShmppTrash::trash( sanitize_key( $request['type'] ), (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'trashed' => true, 'id' => (int) $request['id'], 'type' => $request['type'] ) );
	}

	public function restore( $request ) {
		$result = ShmppTrash::restore( sanitize_key( $request['type'] ), (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'restored' => true, 'id' => (int) $request['id'], 'type' => $request['type'] ) );
	}

	public function force_delete( $request ) {
		$result = ShmppTrash::force_delete( sanitize_key( $request['type'] ), (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'deleted' => true, 'id' => (int) $request['id'], 'type' => $request['type'] ) );
	}

	public function empty_trash( $request ) {
		$type = sanitize_key( (string) $request->get_param( 'type' ) );
		if ( $type && ! ShmppTrash::resolve( $type ) ) {
			return new WP_Error( 'invalid_type', 'Unknown trash type', array( 'status' => 400 ) );
		}
		return rest_ensure_response( ShmppTrash::empty_trash( $type ? $type : null ) );
	}
}
