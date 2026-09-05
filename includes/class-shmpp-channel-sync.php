<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Channel manager sync orchestration (Channex bridge).
 */
class ShmppChannelSync {

	const CRON_HOOK_BOOKINGS = 'shmpp_channel_poll_bookings';
	const CRON_HOOK_ARI      = 'shmpp_channel_full_ari';
	const ARI_DAYS           = 365;

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON_HOOK_BOOKINGS, array( __CLASS__, 'cron_poll_bookings' ) );
		add_action( self::CRON_HOOK_ARI, array( __CLASS__, 'cron_full_ari' ) );
		add_action( 'shmpp_booking_inventory_changed', array( __CLASS__, 'on_inventory_changed' ), 10, 3 );

		if ( ! wp_next_scheduled( self::CRON_HOOK_BOOKINGS ) ) {
			wp_schedule_event( time() + 120, 'shmpp_fifteen_minutes', self::CRON_HOOK_BOOKINGS );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_ARI ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK_ARI );
		}
	}

	/**
	 * @param array<string,array{interval:int,display:string}> $schedules Schedules.
	 * @return array<string,array{interval:int,display:string}>
	 */
	public static function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['shmpp_fifteen_minutes'] ) ) {
			$schedules['shmpp_fifteen_minutes'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes (StayNexus channels)', 'staynexus-hotel-manager' ),
			);
		}
		return $schedules;
	}

	public static function clear_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK_BOOKINGS );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK_BOOKINGS );
		}
		$timestamp = wp_next_scheduled( self::CRON_HOOK_ARI );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK_ARI );
		}
	}

	/**
	 * Active Channex connection row.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get_connection() {
		global $wpdb;
		$table = ShmppDatabase::table( 'channel_connections' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE provider = %s ORDER BY id ASC LIMIT 1',
				$table,
				'channex'
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * @param array<string,mixed> $data Connection fields.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function save_connection( array $data ) {
		global $wpdb;
		$table = ShmppDatabase::table( 'channel_connections' );
		$existing = self::get_connection();

		$api_key = isset( $data['api_key'] ) ? trim( (string) $data['api_key'] ) : '';
		if ( '' === $api_key && $existing && ! empty( $existing['api_key'] ) ) {
			$api_key = $existing['api_key'];
		}

		$row = array(
			'provider'    => 'channex',
			'api_key'     => $api_key,
			'property_id' => isset( $data['property_id'] ) ? sanitize_text_field( $data['property_id'] ) : '',
			'environment' => ( isset( $data['environment'] ) && 'live' === $data['environment'] ) ? 'live' : 'staging',
			'is_active'   => ! empty( $data['is_active'] ) ? 1 : 0,
		);

		if ( $existing ) {
			if ( empty( $existing['webhook_secret'] ) ) {
				$row['webhook_secret'] = wp_generate_password( 32, false, false );
			}
			$wpdb->update( $table, $row, array( 'id' => (int) $existing['id'] ) );
			$id = (int) $existing['id'];
		} else {
			$row['webhook_secret'] = wp_generate_password( 32, false, false );
			$wpdb->insert( $table, $row );
			$id = (int) $wpdb->insert_id;
		}

		return self::get_connection_by_id( $id );
	}

	/**
	 * @param int $id Connection ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_connection_by_id( $id ) {
		global $wpdb;
		$table = ShmppDatabase::table( 'channel_connections' );
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, (int) $id ),
			ARRAY_A
		);
	}

	/**
	 * Public-safe connection payload (mask API key).
	 *
	 * @param array<string,mixed>|null $row Connection.
	 * @return array<string,mixed>|null
	 */
	public static function public_connection( $row ) {
		if ( ! $row ) {
			return null;
		}
		$out = $row;
		$key = (string) ( $out['api_key'] ?? '' );
		$out['api_key_set'] = '' !== $key;
		$out['api_key_masked'] = '' !== $key
			? ( strlen( $key ) > 8 ? substr( $key, 0, 4 ) . '…' . substr( $key, -4 ) : '••••' )
			: '';
		unset( $out['api_key'] );
		$out['webhook_url'] = rest_url( 'staynexushm/v1/channels/webhook' );
		return $out;
	}

	/**
	 * @return ShmppChannexClient|WP_Error
	 */
	public static function client_from_connection( $connection = null ) {
		$connection = $connection ?: self::get_connection();
		if ( ! $connection || empty( $connection['api_key'] ) ) {
			return new WP_Error( 'no_connection', 'Channex is not configured', array( 'status' => 400 ) );
		}
		return new ShmppChannexClient( $connection['api_key'], $connection['environment'] ?? 'staging' );
	}

	/**
	 * @param int $connection_id Connection ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_maps( $connection_id ) {
		global $wpdb;
		$table = ShmppDatabase::table( 'channel_room_maps' );
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE connection_id = %d ORDER BY room_type_id ASC', $table, (int) $connection_id ),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Replace room maps for a connection.
	 *
	 * @param int                         $connection_id Connection ID.
	 * @param array<int,array<string,mixed>> $maps Maps.
	 * @return array<int,array<string,mixed>>
	 */
	public static function save_maps( $connection_id, array $maps ) {
		global $wpdb;
		$table = ShmppDatabase::table( 'channel_room_maps' );
		$connection_id = (int) $connection_id;
		$wpdb->delete( $table, array( 'connection_id' => $connection_id ) );

		foreach ( $maps as $map ) {
			$room_type_id = isset( $map['room_type_id'] ) ? (int) $map['room_type_id'] : 0;
			$ext_room     = isset( $map['external_room_type_id'] ) ? sanitize_text_field( $map['external_room_type_id'] ) : '';
			$ext_rate     = isset( $map['external_rate_plan_id'] ) ? sanitize_text_field( $map['external_rate_plan_id'] ) : '';
			if ( ! $room_type_id || '' === $ext_room ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'connection_id'          => $connection_id,
					'room_type_id'           => $room_type_id,
					'external_room_type_id'  => $ext_room,
					'external_rate_plan_id'  => $ext_rate ?: null,
				)
			);
		}

		return self::get_maps( $connection_id );
	}

	/**
	 * @param string     $direction in|out.
	 * @param string     $event_type Event.
	 * @param string     $status ok|error.
	 * @param string     $message Message.
	 * @param mixed|null $payload Payload.
	 * @param int        $connection_id Connection ID.
	 */
	public static function log( $direction, $event_type, $status, $message = '', $payload = null, $connection_id = 0 ) {
		global $wpdb;
		$table = ShmppDatabase::table( 'channel_sync_log' );
		$wpdb->insert(
			$table,
			array(
				'connection_id' => (int) $connection_id,
				'direction'     => sanitize_key( $direction ),
				'event_type'    => sanitize_key( $event_type ),
				'status'        => sanitize_key( $status ),
				'message'       => sanitize_textarea_field( (string) $message ),
				'payload'       => $payload ? wp_json_encode( $payload ) : null,
			)
		);

		// Keep last 200 log rows.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE 1 = %d', $table, 1 )
		);
		if ( $count > 200 ) {
			$excess = $count - 200;
			$wpdb->query(
				$wpdb->prepare( 'DELETE FROM %i ORDER BY id ASC LIMIT %d', $table, $excess )
			);
		}
	}

	/**
	 * Recent sync log rows.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent_logs( $limit = 30 ) {
		global $wpdb;
		$table = ShmppDatabase::table( 'channel_sync_log' );
		$limit = max( 1, min( 100, (int) $limit ) );
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', $table, $limit ),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Push availability + rates for mapped rooms.
	 *
	 * @param string|null $date_from Optional start.
	 * @param string|null $date_to   Optional end.
	 * @return true|WP_Error
	 */
	public static function push_ari( $date_from = null, $date_to = null ) {
		$connection = self::get_connection();
		if ( ! $connection || empty( $connection['is_active'] ) || empty( $connection['property_id'] ) ) {
			return new WP_Error( 'inactive', 'Channel connection is inactive or incomplete', array( 'status' => 400 ) );
		}

		$client = self::client_from_connection( $connection );
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$maps = self::get_maps( (int) $connection['id'] );
		if ( ! $maps ) {
			return new WP_Error( 'no_maps', 'Map at least one room type before syncing', array( 'status' => 400 ) );
		}

		$date_from = $date_from ?: gmdate( 'Y-m-d' );
		$date_to   = $date_to ?: gmdate( 'Y-m-d', strtotime( '+' . self::ARI_DAYS . ' days' ) );
		$property  = $connection['property_id'];

		$availability_values = array();
		$restriction_values  = array();

		foreach ( $maps as $map ) {
			$room_type_id = (int) $map['room_type_id'];
			$ext_room     = $map['external_room_type_id'];
			$ext_rate     = $map['external_rate_plan_id'];

			$date = $date_from;
			while ( $date <= $date_to ) {
				$free = ShmppInventory::free_rooms( $room_type_id, $date );
				$availability_values[] = array(
					'property_id'  => $property,
					'room_type_id' => $ext_room,
					'date'         => $date,
					'availability' => $free,
				);

				if ( $ext_rate ) {
					$price = ShmppInventory::price_for_date( $room_type_id, $date );
					if ( $price > 0 ) {
						$restriction_values[] = array(
							'property_id'  => $property,
							'rate_plan_id' => $ext_rate,
							'date'         => $date,
							'rate'         => number_format( $price, 2, '.', '' ),
							'stop_sell'    => 0 === $free,
						);
					}
				}

				$date = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
			}
		}

		// Batch in chunks to stay under payload limits.
		$chunks = array_chunk( $availability_values, 200 );
		foreach ( $chunks as $chunk ) {
			$result = $client->update_availability( $chunk );
			if ( is_wp_error( $result ) ) {
				self::set_last_error( $connection, $result->get_error_message() );
				self::log( 'out', 'ari_availability', 'error', $result->get_error_message(), null, (int) $connection['id'] );
				return $result;
			}
		}

		if ( $restriction_values ) {
			$chunks = array_chunk( $restriction_values, 200 );
			foreach ( $chunks as $chunk ) {
				$result = $client->update_restrictions( $chunk );
				if ( is_wp_error( $result ) ) {
					self::set_last_error( $connection, $result->get_error_message() );
					self::log( 'out', 'ari_rates', 'error', $result->get_error_message(), null, (int) $connection['id'] );
					return $result;
				}
			}
		}

		global $wpdb;
		$wpdb->update(
			ShmppDatabase::table( 'channel_connections' ),
			array(
				'last_ari_sync_at' => current_time( 'mysql' ),
				'last_error'       => null,
			),
			array( 'id' => (int) $connection['id'] )
		);

		self::log(
			'out',
			'ari_push',
			'ok',
			sprintf( 'Pushed availability for %d days across %d mapped rooms', self::ARI_DAYS, count( $maps ) ),
			array( 'from' => $date_from, 'to' => $date_to ),
			(int) $connection['id']
		);

		return true;
	}

	/**
	 * Push ARI for a narrower date window after a local booking change.
	 *
	 * @param int         $room_type_id Room type.
	 * @param string      $check_in     Check-in.
	 * @param string|null $check_out    Check-out.
	 */
	public static function on_inventory_changed( $room_type_id, $check_in, $check_out = null ) {
		$connection = self::get_connection();
		if ( ! $connection || empty( $connection['is_active'] ) ) {
			return;
		}

		$maps = self::get_maps( (int) $connection['id'] );
		$mapped = false;
		foreach ( $maps as $map ) {
			if ( (int) $map['room_type_id'] === (int) $room_type_id ) {
				$mapped = true;
				break;
			}
		}
		if ( ! $mapped ) {
			return;
		}

		$from = sanitize_text_field( $check_in );
		$to   = $check_out ? sanitize_text_field( $check_out ) : $from;
		// Include checkout night-1 already handled by inventory; push through checkout date for safety.
		self::push_ari( $from, $to );
	}

	public static function cron_poll_bookings() {
		self::pull_bookings();
	}

	public static function cron_full_ari() {
		$connection = self::get_connection();
		if ( $connection && ! empty( $connection['is_active'] ) ) {
			self::push_ari();
		}
	}

	/**
	 * Pull and process unacknowledged booking revisions.
	 *
	 * @return array{processed:int,errors:array}|WP_Error
	 */
	public static function pull_bookings() {
		$connection = self::get_connection();
		if ( ! $connection || empty( $connection['is_active'] ) || empty( $connection['property_id'] ) ) {
			return new WP_Error( 'inactive', 'Channel connection is inactive or incomplete', array( 'status' => 400 ) );
		}

		$client = self::client_from_connection( $connection );
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$feed = $client->booking_revisions_feed( $connection['property_id'] );
		if ( is_wp_error( $feed ) ) {
			self::set_last_error( $connection, $feed->get_error_message() );
			self::log( 'in', 'booking_feed', 'error', $feed->get_error_message(), null, (int) $connection['id'] );
			return $feed;
		}

		$items = array();
		if ( ! empty( $feed['data'] ) && is_array( $feed['data'] ) ) {
			$items = $feed['data'];
		}

		$processed = 0;
		$errors    = array();

		foreach ( $items as $item ) {
			$attrs = isset( $item['attributes'] ) && is_array( $item['attributes'] ) ? $item['attributes'] : $item;
			if ( empty( $attrs['id'] ) && ! empty( $item['id'] ) ) {
				$attrs['id'] = $item['id'];
			}
			// JSON:API style: id at top level, attributes nested.
			$revision_id = isset( $item['id'] ) ? (string) $item['id'] : (string) ( $attrs['id'] ?? '' );
			if ( $revision_id && empty( $attrs['unique_id'] ) && isset( $item['attributes'] ) ) {
				$attrs = array_merge( $item['attributes'], array( 'id' => $revision_id ) );
			} elseif ( $revision_id ) {
				$attrs['id'] = $revision_id;
			}

			$result = self::ingest_revision( $attrs, $connection, $client );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
				self::log( 'in', 'booking_ingest', 'error', $result->get_error_message(), $attrs, (int) $connection['id'] );
				continue;
			}
			$processed++;
		}

		global $wpdb;
		$wpdb->update(
			ShmppDatabase::table( 'channel_connections' ),
			array(
				'last_booking_sync_at' => current_time( 'mysql' ),
				'last_error'           => $errors ? implode( '; ', $errors ) : null,
			),
			array( 'id' => (int) $connection['id'] )
		);

		self::log(
			'in',
			'booking_pull',
			$errors ? 'error' : 'ok',
			sprintf( 'Processed %d booking revision(s)', $processed ),
			array( 'count' => $processed, 'errors' => $errors ),
			(int) $connection['id']
		);

		return array(
			'processed' => $processed,
			'errors'    => $errors,
		);
	}

	/**
	 * Ingest a single booking revision and acknowledge it.
	 *
	 * @param array<string,mixed>  $attrs      Revision attributes.
	 * @param array<string,mixed>  $connection Connection row.
	 * @param ShmppChannexClient   $client     Client.
	 * @return true|WP_Error
	 */
	public static function ingest_revision( array $attrs, array $connection, $client ) {
		$revision_id = isset( $attrs['id'] ) ? (string) $attrs['id'] : '';
		$unique_id   = isset( $attrs['unique_id'] ) ? (string) $attrs['unique_id'] : '';
		$status      = isset( $attrs['status'] ) ? strtolower( (string) $attrs['status'] ) : 'new';
		$ota_name    = isset( $attrs['ota_name'] ) ? (string) $attrs['ota_name'] : 'channel';

		if ( '' === $unique_id ) {
			return new WP_Error( 'invalid_revision', 'Booking revision missing unique_id' );
		}

		global $wpdb;
		$bookings = ShmppDatabase::table( 'bookings' );
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE external_id = %s ORDER BY id DESC LIMIT 1',
				$bookings,
				$unique_id
			),
			ARRAY_A
		);

		if ( 'cancelled' === $status ) {
			if ( $existing && empty( $existing['deleted_at'] ) && 'cancelled' !== $existing['booking_status'] ) {
				ShmppInventory::release(
					(int) $existing['room_type_id'],
					$existing['check_in'],
					$existing['check_out'],
					(int) $existing['rooms_count']
				);
				$wpdb->update(
					$bookings,
					array(
						'booking_status'       => 'cancelled',
						'external_revision_id' => $revision_id ?: $existing['external_revision_id'],
						'channel_meta'         => wp_json_encode( $attrs ),
					),
					array( 'id' => (int) $existing['id'] )
				);
				do_action( 'shmpp_booking_inventory_changed', (int) $existing['room_type_id'], $existing['check_in'], $existing['check_out'] );
			}
			if ( $revision_id ) {
				$client->ack_booking_revision( $revision_id );
			}
			return true;
		}

		$rooms_payload = isset( $attrs['rooms'] ) && is_array( $attrs['rooms'] ) ? $attrs['rooms'] : array();
		$first_room    = $rooms_payload ? $rooms_payload[0] : array();
		$ext_room_id   = isset( $first_room['room_type_id'] ) ? (string) $first_room['room_type_id'] : '';

		$maps         = self::get_maps( (int) $connection['id'] );
		$room_type_id = 0;
		foreach ( $maps as $map ) {
			if ( $ext_room_id && $map['external_room_type_id'] === $ext_room_id ) {
				$room_type_id = (int) $map['room_type_id'];
				break;
			}
		}
		if ( ! $room_type_id && count( $maps ) === 1 ) {
			$room_type_id = (int) $maps[0]['room_type_id'];
		}
		if ( ! $room_type_id ) {
			if ( $revision_id ) {
				// Still ack to avoid infinite feed noise; log for operator.
				$client->ack_booking_revision( $revision_id );
			}
			return new WP_Error( 'unmapped_room', 'No local room mapped for Channex room type ' . $ext_room_id );
		}

		$check_in  = isset( $attrs['arrival_date'] ) ? sanitize_text_field( $attrs['arrival_date'] ) : '';
		$check_out = isset( $attrs['departure_date'] ) ? sanitize_text_field( $attrs['departure_date'] ) : '';
		if ( ! empty( $first_room['checkin_date'] ) ) {
			$check_in = sanitize_text_field( $first_room['checkin_date'] );
		}
		if ( ! empty( $first_room['checkout_date'] ) ) {
			$check_out = sanitize_text_field( $first_room['checkout_date'] );
		}

		$occupancy = isset( $attrs['occupancy'] ) && is_array( $attrs['occupancy'] ) ? $attrs['occupancy'] : array();
		$adults    = isset( $occupancy['adults'] ) ? max( 1, (int) $occupancy['adults'] ) : 1;
		$children  = isset( $occupancy['children'] ) ? max( 0, (int) $occupancy['children'] ) : 0;
		$rooms_count = max( 1, count( $rooms_payload ) ?: 1 );

		$customer = isset( $attrs['customer'] ) && is_array( $attrs['customer'] ) ? $attrs['customer'] : array();
		$first    = sanitize_text_field( $customer['name'] ?? $customer['first_name'] ?? 'Guest' );
		$last     = sanitize_text_field( $customer['surname'] ?? $customer['last_name'] ?? $ota_name );
		if ( false !== strpos( $first, ' ' ) && ( empty( $customer['surname'] ) && empty( $customer['last_name'] ) ) ) {
			$parts = preg_split( '/\s+/', $first, 2 );
			$first = $parts[0];
			$last  = isset( $parts[1] ) ? $parts[1] : $last;
		}
		$email = sanitize_email( $customer['mail'] ?? $customer['email'] ?? '' );
		$phone = sanitize_text_field( $customer['phone'] ?? '' );

		$amount = isset( $attrs['amount'] ) ? (float) $attrs['amount'] : 0;
		$source = self::normalize_source( $ota_name );
		$notes  = trim(
			sprintf(
				'Channel: %s | OTA code: %s',
				$ota_name,
				isset( $attrs['ota_reservation_code'] ) ? $attrs['ota_reservation_code'] : $unique_id
			)
		);
		if ( ! empty( $attrs['notes'] ) ) {
			$notes .= "\n" . sanitize_textarea_field( $attrs['notes'] );
		}

		if ( $existing && empty( $existing['deleted_at'] ) ) {
			// Modified: release old inventory, reserve new.
			if ( 'cancelled' !== $existing['booking_status'] ) {
				ShmppInventory::release(
					(int) $existing['room_type_id'],
					$existing['check_in'],
					$existing['check_out'],
					(int) $existing['rooms_count']
				);
			}
			ShmppInventory::reserve( $room_type_id, $check_in, $check_out, $rooms_count );
			$wpdb->update(
				$bookings,
				array(
					'room_type_id'         => $room_type_id,
					'check_in'             => $check_in,
					'check_out'            => $check_out,
					'adults'               => $adults,
					'children'             => $children,
					'rooms_count'          => $rooms_count,
					'subtotal'             => $amount,
					'total_amount'         => $amount,
					'booking_status'       => 'confirmed',
					'source'               => $source,
					'external_revision_id' => $revision_id,
					'channel_meta'         => wp_json_encode( $attrs ),
					'notes'                => $notes,
				),
				array( 'id' => (int) $existing['id'] )
			);
			do_action( 'shmpp_booking_inventory_changed', $room_type_id, $check_in, $check_out );
			if ( $revision_id ) {
				$client->ack_booking_revision( $revision_id );
			}
			return true;
		}

		$wpdb->insert(
			ShmppDatabase::table( 'guests' ),
			array(
				'first_name' => $first,
				'last_name'  => $last,
				'email'      => $email,
				'phone'      => $phone,
			)
		);
		$guest_id = (int) $wpdb->insert_id;

		$booking_code = 'CHX-' . strtoupper( wp_generate_password( 8, false, false ) );
		$wpdb->insert(
			$bookings,
			array(
				'booking_code'         => $booking_code,
				'guest_id'             => $guest_id,
				'room_type_id'         => $room_type_id,
				'check_in'             => $check_in,
				'check_out'            => $check_out,
				'adults'               => $adults,
				'children'             => $children,
				'rooms_count'          => $rooms_count,
				'subtotal'             => $amount,
				'tax_amount'           => 0,
				'total_amount'         => $amount,
				'payment_status'       => ( isset( $attrs['payment_collect'] ) && 'ota' === $attrs['payment_collect'] ) ? 'paid' : 'pending',
				'payment_method'       => 'channel',
				'payment_reference'    => ( isset( $attrs['payment_collect'] ) && 'ota' === $attrs['payment_collect'] ) ? $unique_id : null,
				'booking_status'       => 'confirmed',
				'source'               => $source,
				'external_id'          => $unique_id,
				'external_revision_id' => $revision_id,
				'channel_meta'         => wp_json_encode( $attrs ),
				'notes'                => $notes,
			)
		);
		$booking_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			ShmppDatabase::table( 'guest_checkin_checkout' ),
			array(
				'booking_id' => $booking_id,
				'guest_id'   => $guest_id,
				'status'     => 'expected',
			)
		);

		$wpdb->insert(
			ShmppDatabase::table( 'room_bills' ),
			array(
				'booking_id'        => $booking_id,
				'guest_id'          => $guest_id,
				'description'       => sprintf( 'Channel booking %s (%s)', $booking_code, $ota_name ),
				'amount'            => $amount,
				'tax_amount'        => 0,
				'total_amount'      => $amount,
				'bill_date'         => gmdate( 'Y-m-d' ),
				'payment_status'    => ( isset( $attrs['payment_collect'] ) && 'ota' === $attrs['payment_collect'] ) ? 'paid' : 'unpaid',
				'payment_reference' => ( isset( $attrs['payment_collect'] ) && 'ota' === $attrs['payment_collect'] ) ? $unique_id : null,
			)
		);

		ShmppInventory::reserve( $room_type_id, $check_in, $check_out, $rooms_count );
		do_action( 'shmpp_booking_inventory_changed', $room_type_id, $check_in, $check_out );

		if ( $revision_id ) {
			$ack = $client->ack_booking_revision( $revision_id );
			if ( is_wp_error( $ack ) ) {
				self::log( 'in', 'booking_ack', 'error', $ack->get_error_message(), array( 'revision_id' => $revision_id ), (int) $connection['id'] );
			}
		}

		return true;
	}

	/**
	 * @param string $ota_name OTA name from Channex.
	 * @return string
	 */
	public static function normalize_source( $ota_name ) {
		$n = strtolower( preg_replace( '/[^a-z0-9]+/i', '', (string) $ota_name ) );
		if ( false !== strpos( $n, 'booking' ) ) {
			return 'booking.com';
		}
		if ( false !== strpos( $n, 'agoda' ) ) {
			return 'agoda';
		}
		if ( false !== strpos( $n, 'airbnb' ) ) {
			return 'airbnb';
		}
		if ( false !== strpos( $n, 'expedia' ) ) {
			return 'expedia';
		}
		return $ota_name ? sanitize_key( $ota_name ) : 'channel';
	}

	/**
	 * @param array<string,mixed> $connection Connection.
	 * @param string              $message    Error.
	 */
	private static function set_last_error( $connection, $message ) {
		global $wpdb;
		$wpdb->update(
			ShmppDatabase::table( 'channel_connections' ),
			array( 'last_error' => sanitize_textarea_field( $message ) ),
			array( 'id' => (int) $connection['id'] )
		);
	}
}
