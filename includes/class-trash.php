<?php
defined( 'ABSPATH' ) || exit;

// Custom tables: table names cannot use prepare placeholders; queries are built from trusted ShmppDatabase::table() keys.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

/**
 * Soft-delete / trash helpers for all primary StayNexus Hotel Manager entities.
 */
class ShmppTrash {

	/**
	 * Map of trash type => meta.
	 *
	 * @return array<string,array{table:string,label:string,title_field:string,unique_fields?:string[]}>
	 */
	public static function entities() {
		return array(
			'rooms'              => array(
				'table'         => 'room_types',
				'label'         => 'Rooms',
				'title_field'   => 'name',
				'unique_fields' => array( 'slug' ),
			),
			'amenities'          => array(
				'table'       => 'amenities',
				'label'       => 'Amenities',
				'title_field' => 'name',
			),
			'guests'             => array(
				'table'       => 'guests',
				'label'       => 'Guests',
				'title_field' => 'email',
			),
			'discounts'          => array(
				'table'         => 'discounts',
				'label'         => 'Discounts',
				'title_field'   => 'code',
				'unique_fields' => array( 'code' ),
			),
			'payment_types'      => array(
				'table'         => 'offline_payment_types',
				'label'         => 'Payment types',
				'title_field'   => 'name',
				'unique_fields' => array( 'slug' ),
			),
			'bookings'           => array(
				'table'         => 'bookings',
				'label'         => 'Bookings',
				'title_field'   => 'booking_code',
				'unique_fields' => array( 'booking_code' ),
			),
			'checkins'           => array(
				'table'       => 'guest_checkin_checkout',
				'label'       => 'Check-ins',
				'title_field' => 'id',
			),
			'room_bills'         => array(
				'table'       => 'room_bills',
				'label'       => 'Room bills',
				'title_field' => 'description',
			),
			'restaurants'        => array(
				'table'       => 'restaurants',
				'label'       => 'Restaurants',
				'title_field' => 'name',
			),
			'restaurant_bills'   => array(
				'table'         => 'restaurant_bills',
				'label'         => 'Restaurant bills',
				'title_field'   => 'bill_number',
				'unique_fields' => array( 'bill_number' ),
			),
			'non_border_bills'   => array(
				'table'         => 'non_border_restaurant_bills',
				'label'         => 'Walk-in restaurant bills',
				'title_field'   => 'bill_number',
				'unique_fields' => array( 'bill_number' ),
			),
			'laundry_bills'      => array(
				'table'         => 'laundry_bills',
				'label'         => 'Laundry bills',
				'title_field'   => 'bill_number',
				'unique_fields' => array( 'bill_number' ),
			),
			'damage_bills'       => array(
				'table'         => 'damage_bills',
				'label'         => 'Damage bills',
				'title_field'   => 'bill_number',
				'unique_fields' => array( 'bill_number' ),
			),
			'roles'              => array(
				'table'         => 'employee_roles',
				'label'         => 'Staff roles',
				'title_field'   => 'name',
				'unique_fields' => array( 'slug' ),
			),
			'employees'          => array(
				'table'         => 'employees',
				'label'         => 'Employees',
				'title_field'   => 'employee_code',
				'unique_fields' => array( 'employee_code' ),
			),
			'salaries'           => array(
				'table'       => 'employee_salaries',
				'label'       => 'Salaries',
				'title_field' => 'id',
			),
			'slots'              => array(
				'table'       => 'booking_date_slots',
				'label'       => 'Date slots',
				'title_field' => 'slot_date',
			),
		);
	}

	/**
	 * SQL fragment: only non-trashed rows.
	 *
	 * @param string $alias Table alias or empty.
	 */
	public static function alive_sql( $alias = '' ) {
		$col = $alias ? "{$alias}.deleted_at" : 'deleted_at';
		return " {$col} IS NULL ";
	}

	/**
	 * AND fragment for WHERE clauses.
	 *
	 * @param string $alias Table alias or empty.
	 */
	public static function and_alive( $alias = '' ) {
		return ' AND ' . self::alive_sql( $alias );
	}

	public static function resolve( $type ) {
		$entities = self::entities();
		return isset( $entities[ $type ] ) ? $entities[ $type ] : null;
	}

	public static function table_for( $type ) {
		$meta = self::resolve( $type );
		return $meta ? ShmppDatabase::table( $meta['table'] ) : null;
	}

	/**
	 * Move a row to trash (soft delete).
	 *
	 * @return true|WP_Error
	 */
	public static function trash( $type, $id ) {
		global $wpdb;
		$meta = self::resolve( $type );
		if ( ! $meta ) {
			return new WP_Error( 'invalid_type', 'Unknown trash type', array( 'status' => 400 ) );
		}

		$table = ShmppDatabase::table( $meta['table'] );
		$id    = (int) $id;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Record not found', array( 'status' => 404 ) );
		}
		if ( ! empty( $row['deleted_at'] ) ) {
			return true;
		}

		$data = array( 'deleted_at' => current_time( 'mysql' ) );

		// Free unique keys so a replacement can be created while this sits in trash.
		if ( ! empty( $meta['unique_fields'] ) ) {
			foreach ( $meta['unique_fields'] as $field ) {
				if ( isset( $row[ $field ] ) && '' !== (string) $row[ $field ] ) {
					$original       = (string) $row[ $field ];
					$data[ $field ] = self::trashed_unique_value( $original, $id );
				}
			}
		}

		$wpdb->update( $table, $data, array( 'id' => $id ) );

		// Cascade soft-delete for tightly coupled children.
		if ( 'bookings' === $type ) {
			$check = ShmppDatabase::table( 'guest_checkin_checkout' );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$check} SET deleted_at = %s WHERE booking_id = %d AND deleted_at IS NULL",
					$data['deleted_at'],
					$id
				)
			);
		}

		if ( 'employees' === $type ) {
			$sal = ShmppDatabase::table( 'employee_salaries' );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$sal} SET deleted_at = %s WHERE employee_id = %d AND deleted_at IS NULL",
					$data['deleted_at'],
					$id
				)
			);
		}

		if ( 'rooms' === $type ) {
			$slots = ShmppDatabase::table( 'booking_date_slots' );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$slots} SET deleted_at = %s WHERE room_type_id = %d AND deleted_at IS NULL",
					$data['deleted_at'],
					$id
				)
			);
		}

		if ( 'restaurant_bills' === $type ) {
			// Guest splits stay; they are meaningless without the bill but have no deleted_at.
		}

		return true;
	}

	/**
	 * Restore a trashed row.
	 *
	 * @return true|WP_Error
	 */
	public static function restore( $type, $id ) {
		global $wpdb;
		$meta = self::resolve( $type );
		if ( ! $meta ) {
			return new WP_Error( 'invalid_type', 'Unknown trash type', array( 'status' => 400 ) );
		}

		$table = ShmppDatabase::table( $meta['table'] );
		$id    = (int) $id;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Record not found', array( 'status' => 404 ) );
		}
		if ( empty( $row['deleted_at'] ) ) {
			return true;
		}

		$data = array( 'deleted_at' => null );

		if ( ! empty( $meta['unique_fields'] ) ) {
			foreach ( $meta['unique_fields'] as $field ) {
				if ( isset( $row[ $field ] ) ) {
					$restored = self::restore_unique_value( (string) $row[ $field ], $id );
					// Avoid unique collisions with an active record.
					$clash = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT id FROM {$table} WHERE {$field} = %s AND id != %d AND deleted_at IS NULL LIMIT 1",
							$restored,
							$id
						)
					);
					if ( $clash ) {
						$restored = $restored . '-restored-' . $id;
					}
					$data[ $field ] = $restored;
				}
			}
		}

		// wpdb->update skips nulls unless format forces — use query for deleted_at NULL.
		$set_parts = array( 'deleted_at = NULL' );
		$params    = array();
		foreach ( $data as $key => $value ) {
			if ( 'deleted_at' === $key ) {
				continue;
			}
			$set_parts[] = "{$key} = %s";
			$params[]    = $value;
		}
		$params[] = $id;
		$sql      = 'UPDATE ' . $table . ' SET ' . implode( ', ', $set_parts ) . ' WHERE id = %d';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql placeholders match $params; table/columns are allowlisted.
		$wpdb->query( $wpdb->prepare( $sql, $params ) );

		if ( 'bookings' === $type ) {
			$check = ShmppDatabase::table( 'guest_checkin_checkout' );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$check} SET deleted_at = NULL WHERE booking_id = %d AND deleted_at IS NOT NULL",
					$id
				)
			);
		}

		if ( 'employees' === $type ) {
			$sal = ShmppDatabase::table( 'employee_salaries' );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$sal} SET deleted_at = NULL WHERE employee_id = %d AND deleted_at IS NOT NULL",
					$id
				)
			);
		}

		if ( 'rooms' === $type ) {
			$slots = ShmppDatabase::table( 'booking_date_slots' );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$slots} SET deleted_at = NULL WHERE room_type_id = %d AND deleted_at IS NOT NULL",
					$id
				)
			);
		}

		return true;
	}

	/**
	 * Permanently delete a trashed row (and safe children).
	 *
	 * @return true|WP_Error
	 */
	public static function force_delete( $type, $id ) {
		global $wpdb;
		$meta = self::resolve( $type );
		if ( ! $meta ) {
			return new WP_Error( 'invalid_type', 'Unknown trash type', array( 'status' => 400 ) );
		}

		$table = ShmppDatabase::table( $meta['table'] );
		$id    = (int) $id;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Record not found', array( 'status' => 404 ) );
		}
		if ( empty( $row['deleted_at'] ) ) {
			return new WP_Error( 'not_trashed', 'Move the item to trash before permanently deleting it.', array( 'status' => 400 ) );
		}

		if ( 'rooms' === $type ) {
			$wpdb->delete( ShmppDatabase::table( 'room_type_amenities' ), array( 'room_type_id' => $id ) );
			$wpdb->delete( ShmppDatabase::table( 'room_gallery' ), array( 'room_type_id' => $id ) );
			$wpdb->delete( ShmppDatabase::table( 'booking_date_slots' ), array( 'room_type_id' => $id ) );
		}

		if ( 'amenities' === $type ) {
			$wpdb->delete( ShmppDatabase::table( 'room_type_amenities' ), array( 'amenity_id' => $id ) );
		}

		if ( 'employees' === $type ) {
			$wpdb->delete( ShmppDatabase::table( 'employee_salaries' ), array( 'employee_id' => $id ) );
		}

		if ( 'bookings' === $type ) {
			$wpdb->delete( ShmppDatabase::table( 'guest_checkin_checkout' ), array( 'booking_id' => $id ) );
		}

		if ( 'restaurant_bills' === $type ) {
			$wpdb->delete( ShmppDatabase::table( 'restaurant_guest_bills' ), array( 'restaurant_bill_id' => $id ) );
		}

		$wpdb->delete( $table, array( 'id' => $id ) );
		return true;
	}

	/**
	 * Empty trash for one type or all types.
	 *
	 * @param string|null $type
	 * @return array{deleted:int}
	 */
	public static function empty_trash( $type = null ) {
		$deleted = 0;
		$types   = $type ? array( $type ) : array_keys( self::entities() );

		foreach ( $types as $t ) {
			$meta = self::resolve( $t );
			if ( ! $meta ) {
				continue;
			}
			global $wpdb;
			$table = ShmppDatabase::table( $meta['table'] );
			$ids   = $wpdb->get_col( "SELECT id FROM {$table} WHERE deleted_at IS NOT NULL" );
			foreach ( $ids as $id ) {
				$result = self::force_delete( $t, (int) $id );
				if ( ! is_wp_error( $result ) ) {
					$deleted++;
				}
			}
		}

		return array( 'deleted' => $deleted );
	}

	/**
	 * List trashed items, optionally filtered by type.
	 *
	 * @param string|null $type
	 * @return array
	 */
	public static function list_items( $type = null ) {
		global $wpdb;
		$out   = array();
		$types = $type ? array( $type ) : array_keys( self::entities() );

		foreach ( $types as $t ) {
			$meta = self::resolve( $t );
			if ( ! $meta ) {
				continue;
			}
			$table = ShmppDatabase::table( $meta['table'] );
			$rows  = $wpdb->get_results(
				"SELECT * FROM {$table} WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 200",
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$title = self::row_title( $meta, $row );
				$out[] = array(
					'type'       => $t,
					'label'      => $meta['label'],
					'id'         => (int) $row['id'],
					'title'      => $title,
					'deleted_at' => $row['deleted_at'],
					'row'        => $row,
				);
			}
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( (string) $b['deleted_at'], (string) $a['deleted_at'] );
			}
		);

		return $out;
	}

	public static function counts() {
		global $wpdb;
		$counts = array();
		$total  = 0;
		foreach ( self::entities() as $type => $meta ) {
			$table = ShmppDatabase::table( $meta['table'] );
			$n     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NOT NULL" );
			$counts[ $type ] = array(
				'label' => $meta['label'],
				'count' => $n,
			);
			$total += $n;
		}
		return array(
			'total'  => $total,
			'by_type'=> $counts,
		);
	}

	private static function row_title( $meta, $row ) {
		$field = $meta['title_field'];
		if ( 'guests' === ( $meta['table'] ?? '' ) || ( isset( $row['first_name'] ) && 'email' === $field ) ) {
			$name = trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) );
			if ( $name ) {
				return $name;
			}
		}
		if ( 'employees' === ( $meta['table'] ?? '' ) || ( isset( $row['first_name'] ) && 'employee_code' === $field ) ) {
			$name = trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) );
			$code = $row['employee_code'] ?? '';
			return $name ? ( $name . ( $code ? " ({$code})" : '' ) ) : (string) $code;
		}
		if ( isset( $row[ $field ] ) && '' !== (string) $row[ $field ] ) {
			return (string) $row[ $field ];
		}
		return '#' . (int) $row['id'];
	}

	private static function trashed_unique_value( $original, $id ) {
		// Keep under typical VARCHAR(50/191) limits.
		$suffix = '__trashed_' . (int) $id;
		$max    = 180;
		$base   = substr( $original, 0, max( 1, $max - strlen( $suffix ) ) );
		return $base . $suffix;
	}

	private static function restore_unique_value( $current, $id ) {
		$suffix = '__trashed_' . (int) $id;
		if ( substr( $current, -strlen( $suffix ) ) === $suffix ) {
			return substr( $current, 0, -strlen( $suffix ) );
		}
		return $current;
	}
}
