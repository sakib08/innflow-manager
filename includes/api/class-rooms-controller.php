<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.WP.AlternativeFunctions.file_system_operations_fread,WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV stream to php://output and reading uploaded tmp files.

// Custom tables: table names cannot use prepare placeholders; queries are built from trusted InnflowManagerDatabase::table() keys.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

class InnflowManagerRooms_Controller {

	const NS = 'innflow-manager/v1';

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/rooms',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_rooms' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_public' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_room' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
			)
		);

		// Register static paths before /rooms/{id} so they are never shadowed.
		register_rest_route(
			self::NS,
			'/rooms/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_rooms' ),
				'permission_callback' => array( 'InnflowManagerRest_API', 'permission_public' ),
			)
		);

		register_rest_route(
			self::NS,
			'/rooms/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_rooms' ),
				'permission_callback' => array( $this, 'can_export' ),
			)
		);

		register_rest_route(
			self::NS,
			'/rooms/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import_rooms' ),
				'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/rooms/export-token',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_export_token' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'regenerate_export_token' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/rooms/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_room' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_public' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_room' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_room' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/slots',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_slots' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upsert_slot' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
			)
		);
	}

	public function list_rooms( $request ) {
		global $wpdb;
		$table = InnflowManagerDatabase::table( 'room_types' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE " . InnflowManagerTrash::alive_sql() . ' ORDER BY id DESC', ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['amenities'] = $this->get_amenities( (int) $row['id'] );
			$row['gallery']   = $this->get_gallery( (int) $row['id'] );
		}
		return rest_ensure_response( $rows );
	}

	public function get_room( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );
		if ( ! $id ) {
			$attrs = $request->get_attributes();
			$id    = isset( $attrs['id'] ) ? (int) $attrs['id'] : 0;
		}
		$table = InnflowManagerDatabase::table( 'room_types' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND " . InnflowManagerTrash::alive_sql(), $id ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Room type not found', array( 'status' => 404 ) );
		}
		$row['amenities'] = $this->get_amenities( $id );
		$row['gallery']   = $this->get_gallery( $id );
		return rest_ensure_response( $row );
	}

	public function create_room( $request ) {
		global $wpdb;
		$data = $this->sanitize_room( $request );
		$data['slug'] = $this->unique_slug( sanitize_title( $data['name'] ) );
		$wpdb->insert( InnflowManagerDatabase::table( 'room_types' ), $data );
		$id = (int) $wpdb->insert_id;
		$this->sync_amenities( $id, $request->get_param( 'amenity_ids' ) );
		$this->sync_gallery( $id, $request->get_param( 'gallery_urls' ) );
		$this->ensure_slots( $id, (int) $data['total_rooms'], (float) $data['base_price'] );
		$req = new WP_REST_Request( 'GET' );
		$req->set_param( 'id', $id );
		return $this->get_room( $req );
	}

	public function update_room( $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		$data = $this->sanitize_room( $request );
		$wpdb->update( InnflowManagerDatabase::table( 'room_types' ), $data, array( 'id' => $id ) );
		$this->sync_amenities( $id, $request->get_param( 'amenity_ids' ) );
		if ( null !== $request->get_param( 'gallery_urls' ) ) {
			$this->sync_gallery( $id, $request->get_param( 'gallery_urls' ) );
		}
		return $this->get_room( $request );
	}

	public function delete_room( $request ) {
		$result = InnflowManagerTrash::trash( 'rooms', (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'trashed' => true, 'id' => (int) $request['id'] ) );
	}

	public function can_export( $request ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$token  = sanitize_text_field( $request->get_param( 'token' ) );
		$stored = get_option( 'ifmpp_export_token' );
		return $token && $stored && hash_equals( (string) $stored, $token );
	}

	public function get_export_token() {
		$token = get_option( 'ifmpp_export_token' );
		if ( ! $token ) {
			$token = wp_generate_password( 32, false, false );
			update_option( 'ifmpp_export_token', $token );
		}
		return rest_ensure_response(
			array(
				'token'      => $token,
				'export_url' => add_query_arg(
					array(
						'format' => 'csv',
						'token'  => $token,
					),
					rest_url( self::NS . '/rooms/export' )
				),
			)
		);
	}

	public function regenerate_export_token() {
		$token = wp_generate_password( 32, false, false );
		update_option( 'ifmpp_export_token', $token );
		return $this->get_export_token();
	}

	public function export_rooms( $request ) {
		global $wpdb;
		$format = sanitize_text_field( $request->get_param( 'format' ) ?: 'csv' );
		$table  = InnflowManagerDatabase::table( 'room_types' );
		$rooms  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A );

		$columns = array( 'id', 'name', 'slug', 'description', 'base_price', 'max_adults', 'max_children', 'total_rooms', 'image_url', 'gallery_urls', 'amenities', 'status' );

		$data_rows = array();
		foreach ( $rooms as $room ) {
			$amenity_names = wp_list_pluck( $this->get_amenities( (int) $room['id'] ), 'name' );
			$gallery_urls  = wp_list_pluck( $this->get_gallery( (int) $room['id'] ), 'image_url' );
			$data_rows[]   = array(
				$room['id'],
				$room['name'],
				$room['slug'],
				$room['description'],
				$room['base_price'],
				$room['max_adults'],
				$room['max_children'],
				$room['total_rooms'],
				$room['image_url'],
				implode( '|', $gallery_urls ),
				implode( '|', $amenity_names ),
				$room['status'],
			);
		}

		if ( 'xlsx' === $format && InnflowManagerXlsx_Writer::is_available() ) {
			$writer = new InnflowManagerXlsx_Writer();
			$writer->add_row( $columns );
			foreach ( $data_rows as $row ) {
				$writer->add_row( $row );
			}

			nocache_headers();
			header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
			header( 'Content-Disposition: attachment; filename="room-types-' . gmdate( 'Y-m-d' ) . '.xlsx"' );
			echo $writer->output(); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="room-types-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, $columns );
		foreach ( $data_rows as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out );
		exit;
	}

	public function import_rooms( $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file']['tmp_name'] ) ) {
			return new WP_Error( 'no_file', 'No file was uploaded', array( 'status' => 400 ) );
		}

		$handle = fopen( $files['file']['tmp_name'], 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'read_error', 'Unable to read the uploaded file', array( 'status' => 400 ) );
		}

		$bom = fread( $handle, 3 );
		if ( "\xEF\xBB\xBF" !== $bom ) {
			rewind( $handle );
		}

		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle );
			return new WP_Error( 'invalid_csv', 'The CSV file appears to be empty', array( 'status' => 400 ) );
		}
		$header = array_map(
			function ( $h ) {
				return strtolower( trim( (string) $h ) );
			},
			$header
		);

		global $wpdb;
		$rooms_table = InnflowManagerDatabase::table( 'room_types' );

		$created = 0;
		$updated = 0;
		$errors  = array();
		$row_num = 1;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row_num++;
			if ( count( array_filter( $row, function ( $v ) {
				return '' !== trim( (string) $v );
			} ) ) === 0 ) {
				continue;
			}

			$assoc = array();
			foreach ( $header as $i => $key ) {
				$assoc[ $key ] = isset( $row[ $i ] ) ? $row[ $i ] : '';
			}

			$name = sanitize_text_field( $assoc['name'] ?? '' );
			if ( ! $name ) {
				$errors[] = "Row {$row_num}: missing name, skipped";
				continue;
			}

			$data = array(
				'name'         => $name,
				'description'  => sanitize_textarea_field( $assoc['description'] ?? '' ),
				'base_price'   => (float) ( $assoc['base_price'] ?? 0 ),
				'max_adults'   => max( 1, (int) ( $assoc['max_adults'] ?? 1 ) ),
				'max_children' => max( 0, (int) ( $assoc['max_children'] ?? 0 ) ),
				'total_rooms'  => max( 1, (int) ( $assoc['total_rooms'] ?? 1 ) ),
				'image_url'    => esc_url_raw( $assoc['image_url'] ?? '' ),
				'status'       => sanitize_text_field( $assoc['status'] ?? 'active' ),
			);

			$id       = isset( $assoc['id'] ) ? (int) $assoc['id'] : 0;
			$existing = $id ? $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rooms_table} WHERE id = %d", $id ) ) : null;

			if ( ! $existing && ! empty( $assoc['slug'] ) ) {
				$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rooms_table} WHERE slug = %s", sanitize_title( $assoc['slug'] ) ) );
			}

			if ( $existing ) {
				$id = (int) $existing;
				$wpdb->update( $rooms_table, $data, array( 'id' => $id ) );
				$updated++;
			} else {
				$data['slug'] = $this->unique_slug( sanitize_title( $name ) );
				$wpdb->insert( $rooms_table, $data );
				$id = (int) $wpdb->insert_id;
				$this->ensure_slots( $id, (int) $data['total_rooms'], (float) $data['base_price'] );
				$created++;
			}

			if ( isset( $assoc['amenities'] ) && '' !== trim( $assoc['amenities'] ) ) {
				$names       = array_filter( array_map( 'trim', explode( '|', $assoc['amenities'] ) ) );
				$amenity_ids = array_map( array( $this, 'find_or_create_amenity' ), $names );
				$this->sync_amenities( $id, $amenity_ids );
			}

			if ( isset( $assoc['gallery_urls'] ) && '' !== trim( $assoc['gallery_urls'] ) ) {
				$urls = array_filter( array_map( 'trim', explode( '|', $assoc['gallery_urls'] ) ) );
				$this->sync_gallery( $id, $urls );
			}
		}

		fclose( $handle );

		return rest_ensure_response(
			array(
				'created' => $created,
				'updated' => $updated,
				'errors'  => $errors,
			)
		);
	}

	private function find_or_create_amenity( $name ) {
		global $wpdb;
		$table = InnflowManagerDatabase::table( 'amenities' );
		$name  = sanitize_text_field( $name );
		$id    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s", $name ) );
		if ( $id ) {
			return (int) $id;
		}
		$wpdb->insert( $table, array( 'name' => $name ) );
		return (int) $wpdb->insert_id;
	}

	public function search_rooms( $request ) {
		global $wpdb;
		$check_in  = sanitize_text_field( $request->get_param( 'check_in' ) );
		$check_out = sanitize_text_field( $request->get_param( 'check_out' ) );
		$adults    = max( 1, (int) $request->get_param( 'adults' ) );
		$children  = max( 0, (int) $request->get_param( 'children' ) );
		$rooms     = max( 1, (int) $request->get_param( 'rooms' ) );

		if ( ! $check_in || ! $check_out || strtotime( $check_out ) <= strtotime( $check_in ) ) {
			return new WP_Error( 'invalid_dates', 'Valid check-in and check-out dates are required', array( 'status' => 400 ) );
		}

		$room_table = InnflowManagerDatabase::table( 'room_types' );
		$slot_table = InnflowManagerDatabase::table( 'booking_date_slots' );
		$nights     = (int) ( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS );

		$types = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$room_table} WHERE status = 'active' AND " . InnflowManagerTrash::alive_sql() . ' AND max_adults >= %d AND max_children >= %d',
				$adults,
				$children
			),
			ARRAY_A
		);

		$results = array();
		foreach ( $types as $type ) {
			$min_available = PHP_INT_MAX;
			$total_price   = 0;
			$date          = $check_in;
			$ok            = true;

			for ( $i = 0; $i < $nights; $i++ ) {
				$slot = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$slot_table} WHERE room_type_id = %d AND slot_date = %s AND " . InnflowManagerTrash::alive_sql(),
						$type['id'],
						$date
					),
					ARRAY_A
				);

				$available = $slot
					? max( 0, (int) $slot['available_rooms'] - (int) $slot['booked_rooms'] )
					: (int) $type['total_rooms'];

				$price = $slot && null !== $slot['price_override']
					? (float) $slot['price_override']
					: (float) $type['base_price'];

				if ( $available < $rooms ) {
					$ok = false;
					break;
				}

				$min_available = min( $min_available, $available );
				$total_price  += $price * $rooms;
				$date          = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
			}

			if ( $ok ) {
				$type['amenities']       = $this->get_amenities( (int) $type['id'] );
				$type['gallery']         = $this->get_gallery( (int) $type['id'] );
				$type['nights']          = $nights;
				$type['available_rooms'] = $min_available === PHP_INT_MAX ? (int) $type['total_rooms'] : $min_available;
				$type['total_price']     = round( $total_price, 2 );
				$type['price_per_night'] = $nights > 0 ? round( $total_price / $nights / $rooms, 2 ) : (float) $type['base_price'];
				$results[]               = $type;
			}
		}

		return rest_ensure_response(
			array(
				'check_in'  => $check_in,
				'check_out' => $check_out,
				'nights'    => $nights,
				'results'   => $results,
			)
		);
	}

	public function list_slots( $request ) {
		global $wpdb;
		$room_type_id = (int) $request->get_param( 'room_type_id' );
		$from         = sanitize_text_field( $request->get_param( 'from' ) );
		$to           = sanitize_text_field( $request->get_param( 'to' ) );
		$table        = InnflowManagerDatabase::table( 'booking_date_slots' );

		$sql    = "SELECT * FROM {$table} WHERE " . InnflowManagerTrash::alive_sql();
		$params = array();
		if ( $room_type_id ) {
			$sql     .= ' AND room_type_id = %d';
			$params[] = $room_type_id;
		}
		if ( $from ) {
			$sql     .= ' AND slot_date >= %s';
			$params[] = $from;
		}
		if ( $to ) {
			$sql     .= ' AND slot_date <= %s';
			$params[] = $to;
		}
		$sql .= ' ORDER BY slot_date ASC';

		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A )
			: $wpdb->get_results( $sql, ARRAY_A );

		return rest_ensure_response( $rows );
	}

	public function upsert_slot( $request ) {
		global $wpdb;
		$table = InnflowManagerDatabase::table( 'booking_date_slots' );
		$data  = array(
			'room_type_id'    => (int) $request->get_param( 'room_type_id' ),
			'slot_date'       => sanitize_text_field( $request->get_param( 'slot_date' ) ),
			'available_rooms' => (int) $request->get_param( 'available_rooms' ),
			'booked_rooms'    => (int) $request->get_param( 'booked_rooms' ),
			'price_override'  => $request->get_param( 'price_override' ) !== null && $request->get_param( 'price_override' ) !== ''
				? (float) $request->get_param( 'price_override' )
				: null,
			'status'          => sanitize_text_field( $request->get_param( 'status' ) ?: 'open' ),
		);

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE room_type_id = %d AND slot_date = %s",
				$data['room_type_id'],
				$data['slot_date']
			)
		);

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ) );
			$id = (int) $existing;
		} else {
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return rest_ensure_response( $row );
	}

	private function sanitize_room( $request ) {
		return array(
			'name'         => sanitize_text_field( $request->get_param( 'name' ) ),
			'description'  => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'base_price'   => (float) $request->get_param( 'base_price' ),
			'max_adults'   => max( 1, (int) $request->get_param( 'max_adults' ) ),
			'max_children' => max( 0, (int) $request->get_param( 'max_children' ) ),
			'total_rooms'  => max( 1, (int) $request->get_param( 'total_rooms' ) ),
			'image_url'    => esc_url_raw( $request->get_param( 'image_url' ) ),
			'status'       => sanitize_text_field( $request->get_param( 'status' ) ?: 'active' ),
		);
	}

	private function unique_slug( $slug ) {
		global $wpdb;
		$table   = InnflowManagerDatabase::table( 'room_types' );
		$base    = $slug ?: 'room';
		$candidate = $base;
		$i = 1;
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s AND " . InnflowManagerTrash::alive_sql(), $candidate ) ) ) {
			$candidate = $base . '-' . $i;
			$i++;
		}
		return $candidate;
	}

	private function get_amenities( $room_type_id ) {
		global $wpdb;
		$join = InnflowManagerDatabase::table( 'room_type_amenities' );
		$am   = InnflowManagerDatabase::table( 'amenities' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.* FROM {$am} a INNER JOIN {$join} j ON j.amenity_id = a.id WHERE j.room_type_id = %d AND " . InnflowManagerTrash::alive_sql( 'a' ),
				$room_type_id
			),
			ARRAY_A
		);
	}

	private function sync_amenities( $room_type_id, $amenity_ids ) {
		global $wpdb;
		$table = InnflowManagerDatabase::table( 'room_type_amenities' );
		$wpdb->delete( $table, array( 'room_type_id' => $room_type_id ) );
		if ( ! is_array( $amenity_ids ) ) {
			return;
		}
		foreach ( $amenity_ids as $aid ) {
			$wpdb->insert(
				$table,
				array(
					'room_type_id' => $room_type_id,
					'amenity_id'   => (int) $aid,
				)
			);
		}
	}

	private function get_gallery( $room_type_id ) {
		global $wpdb;
		$table = InnflowManagerDatabase::table( 'room_gallery' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, image_url, sort_order FROM {$table} WHERE room_type_id = %d ORDER BY sort_order ASC, id ASC",
				$room_type_id
			),
			ARRAY_A
		);
	}

	private function sync_gallery( $room_type_id, $image_urls ) {
		global $wpdb;
		$table = InnflowManagerDatabase::table( 'room_gallery' );
		$wpdb->delete( $table, array( 'room_type_id' => $room_type_id ) );
		if ( ! is_array( $image_urls ) ) {
			return;
		}
		$order = 0;
		foreach ( $image_urls as $url ) {
			$url = esc_url_raw( $url );
			if ( ! $url ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'room_type_id' => $room_type_id,
					'image_url'    => $url,
					'sort_order'   => $order,
				)
			);
			$order++;
		}
	}

	private function ensure_slots( $room_type_id, $total_rooms, $base_price ) {
		global $wpdb;
		$table = InnflowManagerDatabase::table( 'booking_date_slots' );
		for ( $i = 0; $i < 90; $i++ ) {
			$date = gmdate( 'Y-m-d', strtotime( "+{$i} days" ) );
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE room_type_id = %d AND slot_date = %s",
					$room_type_id,
					$date
				)
			);
			if ( ! $exists ) {
				$wpdb->insert(
					$table,
					array(
						'room_type_id'    => $room_type_id,
						'slot_date'       => $date,
						'available_rooms' => $total_rooms,
						'booked_rooms'    => 0,
						'price_override'  => null,
						'status'          => 'open',
					)
				);
			}
		}
	}
}
