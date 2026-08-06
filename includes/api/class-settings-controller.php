<?php
defined( 'ABSPATH' ) || exit;

// Custom tables: table names cannot use prepare placeholders; queries are built from trusted ShmppDatabase::table() keys.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

class ShmppSettingsController {

	const NS = 'staynexushm/v1';

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/seed-demo',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'seed_demo' ),
				'permission_callback' => array( 'ShmppRestAPI', 'permission_manage' ),
			)
		);
	}

	public function get_settings() {
		$settings = get_option( 'shmpp_settings', array() );
		return rest_ensure_response( $settings );
	}

	public function seed_demo() {
		global $wpdb;
		$rooms = ShmppDatabase::table( 'room_types' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rooms}" );
		if ( $count > 0 ) {
			return new WP_Error(
				'not_empty',
				'Demo data can only be loaded when there are no room types yet. Delete existing room types first, then try again.',
				array( 'status' => 400 )
			);
		}

		// seed_defaults() also loads demo content when room types are empty.
		ShmppDatabase::seed_defaults();
		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rooms}" );

		return rest_ensure_response(
			array(
				'seeded'  => $after > 0,
				'message' => $after > 0
					? 'Demo hotel data loaded successfully.'
					: 'Demo data was already present or could not be loaded.',
			)
		);
	}

	public function save_settings( $request ) {
		$current = get_option( 'shmpp_settings', array() );
		$incoming = $request->get_json_params();
		if ( ! is_array( $incoming ) ) {
			$incoming = $request->get_params();
		}

		$allowed = array(
			'hotel_name',
			'currency',
			'currency_symbol',
			'tax_rate',
			'check_in_time',
			'check_out_time',
			'booking_page_id',
			'enable_frontend',
			'address',
			'phone',
			'email',
		);

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $incoming ) ) {
				continue;
			}
			$value = $incoming[ $key ];
			if ( in_array( $key, array( 'tax_rate', 'booking_page_id' ), true ) ) {
				$current[ $key ] = (float) $value;
			} elseif ( 'enable_frontend' === $key ) {
				$current[ $key ] = (bool) $value;
			} else {
				$current[ $key ] = sanitize_text_field( $value );
			}
		}

		update_option( 'shmpp_settings', $current );
		return rest_ensure_response( $current );
	}
}
