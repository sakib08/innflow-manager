<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * REST endpoints for channel manager (Channex) bridge.
 */
class ShmppChannelsController {

	const NS = 'staynexushm/v1';

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/channels',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_connection' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/channels/maps',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_maps' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_maps' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/channels/channex/properties',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_properties' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/channels/channex/room-types',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_room_types' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/channels/channex/rate-plans',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_rate_plans' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/channels/sync/ari',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_ari' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/channels/sync/bookings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_bookings' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/channels/logs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/channels/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function get_status() {
		global $wpdb;
		$connection = ShmppChannelSync::public_connection( ShmppChannelSync::get_connection() );
		$rooms      = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, name, total_rooms, base_price FROM %i WHERE deleted_at IS NULL AND status = %s ORDER BY name ASC',
				ShmppDatabase::table( 'room_types' ),
				'active'
			),
			ARRAY_A
		);

		$maps = array();
		if ( $connection ) {
			$maps = ShmppChannelSync::get_maps( (int) $connection['id'] );
		}

		return rest_ensure_response(
			array(
				'connection' => $connection,
				'rooms'      => $rooms ?: array(),
				'maps'       => $maps,
				'logs'       => ShmppChannelSync::recent_logs( 20 ),
			)
		);
	}

	public function save_connection( $request ) {
		$row = ShmppChannelSync::save_connection(
			array(
				'api_key'     => $request->get_param( 'api_key' ),
				'property_id' => $request->get_param( 'property_id' ),
				'environment' => $request->get_param( 'environment' ),
				'is_active'   => $request->get_param( 'is_active' ),
			)
		);

		if ( is_wp_error( $row ) ) {
			return $row;
		}

		return rest_ensure_response(
			array(
				'connection' => ShmppChannelSync::public_connection( $row ),
				'message'    => 'Connection saved',
			)
		);
	}

	public function get_maps() {
		$connection = ShmppChannelSync::get_connection();
		if ( ! $connection ) {
			return rest_ensure_response( array() );
		}
		return rest_ensure_response( ShmppChannelSync::get_maps( (int) $connection['id'] ) );
	}

	public function save_maps( $request ) {
		$connection = ShmppChannelSync::get_connection();
		if ( ! $connection ) {
			return new WP_Error( 'no_connection', 'Save Channex connection first', array( 'status' => 400 ) );
		}
		$maps = $request->get_param( 'maps' );
		if ( ! is_array( $maps ) ) {
			return new WP_Error( 'invalid', 'maps must be an array', array( 'status' => 400 ) );
		}
		$saved = ShmppChannelSync::save_maps( (int) $connection['id'], $maps );
		return rest_ensure_response( array( 'maps' => $saved ) );
	}

	public function list_properties() {
		$client = ShmppChannelSync::client_from_connection();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		$result = $client->list_properties();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $this->normalize_collection( $result ) );
	}

	public function list_room_types( $request ) {
		$connection = ShmppChannelSync::get_connection();
		$property_id = sanitize_text_field( $request->get_param( 'property_id' ) ?: ( $connection['property_id'] ?? '' ) );
		if ( ! $property_id ) {
			return new WP_Error( 'missing_property', 'property_id is required', array( 'status' => 400 ) );
		}
		$client = ShmppChannelSync::client_from_connection();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		$result = $client->list_room_types( $property_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $this->normalize_collection( $result ) );
	}

	public function list_rate_plans( $request ) {
		$connection = ShmppChannelSync::get_connection();
		$property_id = sanitize_text_field( $request->get_param( 'property_id' ) ?: ( $connection['property_id'] ?? '' ) );
		if ( ! $property_id ) {
			return new WP_Error( 'missing_property', 'property_id is required', array( 'status' => 400 ) );
		}
		$client = ShmppChannelSync::client_from_connection();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		$result = $client->list_rate_plans( $property_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $this->normalize_collection( $result ) );
	}

	public function sync_ari() {
		$result = ShmppChannelSync::push_ari();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array(
				'ok'      => true,
				'message' => 'Availability and rates pushed to Channex',
			)
		);
	}

	public function sync_bookings() {
		$result = ShmppChannelSync::pull_bookings();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array(
				'ok'        => true,
				'processed' => $result['processed'],
				'errors'    => $result['errors'],
				'message'   => sprintf( 'Processed %d booking revision(s)', $result['processed'] ),
			)
		);
	}

	public function get_logs( $request ) {
		$limit = (int) ( $request->get_param( 'limit' ) ?: 30 );
		return rest_ensure_response( ShmppChannelSync::recent_logs( $limit ) );
	}

	/**
	 * Channex booking webhook — always return 200 after attempting pull.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function webhook( $request ) {
		$connection = ShmppChannelSync::get_connection();
		if ( ! $connection || empty( $connection['is_active'] ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'skipped' => 'inactive' ), 200 );
		}

		$secret = (string) ( $connection['webhook_secret'] ?? '' );
		$given  = (string) $request->get_param( 'secret' );
		if ( ! $given ) {
			$given = (string) $request->get_header( 'x-shmpp-webhook-secret' );
		}
		if ( $secret && ! hash_equals( $secret, $given ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'unauthorized' ), 401 );
		}

		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		// Prefer pulling the revision by ID when provided; otherwise poll the feed.
		$revision_id = '';
		if ( ! empty( $payload['payload']['booking_revision_id'] ) ) {
			$revision_id = (string) $payload['payload']['booking_revision_id'];
		} elseif ( ! empty( $payload['booking_revision_id'] ) ) {
			$revision_id = (string) $payload['booking_revision_id'];
		} elseif ( ! empty( $payload['data']['id'] ) ) {
			$revision_id = (string) $payload['data']['id'];
		}

		$client = ShmppChannelSync::client_from_connection( $connection );
		if ( is_wp_error( $client ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'skipped' => $client->get_error_message() ), 200 );
		}

		if ( $revision_id ) {
			$rev = $client->get_booking_revision( $revision_id );
			if ( ! is_wp_error( $rev ) ) {
				$item  = isset( $rev['data'] ) ? $rev['data'] : $rev;
				$attrs = isset( $item['attributes'] ) ? $item['attributes'] : $item;
				if ( isset( $item['id'] ) ) {
					$attrs['id'] = $item['id'];
				}
				ShmppChannelSync::ingest_revision( $attrs, $connection, $client );
			} else {
				ShmppChannelSync::pull_bookings();
			}
		} else {
			ShmppChannelSync::pull_bookings();
		}

		ShmppChannelSync::log( 'in', 'webhook', 'ok', 'Webhook received', $payload, (int) $connection['id'] );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Flatten JSON:API style collections to {id, name, ...attributes}.
	 *
	 * @param array<string,mixed> $result API result.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_collection( $result ) {
		$rows = array();
		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$attrs = isset( $item['attributes'] ) && is_array( $item['attributes'] ) ? $item['attributes'] : $item;
			$id    = isset( $item['id'] ) ? $item['id'] : ( $attrs['id'] ?? '' );
			$row   = array_merge( $attrs, array( 'id' => $id ) );
			if ( empty( $row['name'] ) && ! empty( $row['title'] ) ) {
				$row['name'] = $row['title'];
			}
			$rows[] = $row;
		}
		return $rows;
	}
}
