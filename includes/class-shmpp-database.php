<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

class ShmppDatabase {

	const DB_VERSION = '1.7.0';

	/**
	 * Allowed custom-table suffixes (never accept arbitrary caller input).
	 *
	 * @return string[]
	 */
	public static function allowed_table_suffixes() {
		return array(
			'room_types',
			'amenities',
			'room_type_amenities',
			'room_gallery',
			'booking_date_slots',
			'guests',
			'discounts',
			'offline_payment_types',
			'bookings',
			'guest_checkin_checkout',
			'room_bills',
			'restaurants',
			'restaurant_bills',
			'restaurant_guest_bills',
			'non_border_restaurant_bills',
			'laundry_bills',
			'damage_bills',
			'employee_roles',
			'employees',
			'employee_salaries',
		);
	}

	/**
	 * Resolve a plugin table name from an allowlisted suffix.
	 *
	 * @param string $name Table suffix without prefix (e.g. 'bookings').
	 * @return string|null Fully qualified table name, or null if not allowlisted.
	 */
	public static function table( $name ) {
		global $wpdb;
		if ( ! is_string( $name ) || ! in_array( $name, self::allowed_table_suffixes(), true ) ) {
			return null;
		}
		return $wpdb->prefix . 'shmpp_' . $name;
	}

	public static function create_tables() {
		self::migrate_legacy();

		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables = array();

		$tables[] = "CREATE TABLE " . self::table( 'room_types' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			description TEXT NULL,
			base_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			max_adults INT UNSIGNED NOT NULL DEFAULT 2,
			max_children INT UNSIGNED NOT NULL DEFAULT 0,
			total_rooms INT UNSIGNED NOT NULL DEFAULT 1,
			image_url TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'amenities' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			icon VARCHAR(100) NULL,
			description TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'room_type_amenities' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			room_type_id BIGINT UNSIGNED NOT NULL,
			amenity_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY room_amenity (room_type_id, amenity_id),
			KEY amenity_id (amenity_id)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'room_gallery' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			room_type_id BIGINT UNSIGNED NOT NULL,
			image_url TEXT NOT NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY room_type_id (room_type_id)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'booking_date_slots' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			room_type_id BIGINT UNSIGNED NOT NULL,
			slot_date DATE NOT NULL,
			available_rooms INT UNSIGNED NOT NULL DEFAULT 0,
			booked_rooms INT UNSIGNED NOT NULL DEFAULT 0,
			price_override DECIMAL(12,2) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY room_date (room_type_id, slot_date),
			KEY slot_date (slot_date),
			KEY status (status),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'guests' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			first_name VARCHAR(100) NOT NULL,
			last_name VARCHAR(100) NOT NULL,
			email VARCHAR(191) NULL,
			phone VARCHAR(50) NULL,
			address TEXT NULL,
			city VARCHAR(100) NULL,
			country VARCHAR(100) NULL,
			id_type VARCHAR(50) NULL,
			id_number VARCHAR(100) NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY email (email),
			KEY phone (phone),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'discounts' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(50) NOT NULL,
			name VARCHAR(191) NOT NULL,
			discount_type VARCHAR(20) NOT NULL DEFAULT 'percent',
			discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			min_nights INT UNSIGNED NOT NULL DEFAULT 1,
			max_uses INT UNSIGNED NULL,
			used_count INT UNSIGNED NOT NULL DEFAULT 0,
			starts_at DATETIME NULL,
			ends_at DATETIME NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY code (code),
			KEY status (status),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'offline_payment_types' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL,
			slug VARCHAR(100) NOT NULL,
			description TEXT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'bookings' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_code VARCHAR(50) NOT NULL,
			guest_id BIGINT UNSIGNED NOT NULL,
			room_type_id BIGINT UNSIGNED NOT NULL,
			check_in DATE NOT NULL,
			check_out DATE NOT NULL,
			adults INT UNSIGNED NOT NULL DEFAULT 1,
			children INT UNSIGNED NOT NULL DEFAULT 0,
			rooms_count INT UNSIGNED NOT NULL DEFAULT 1,
			discount_id BIGINT UNSIGNED NULL,
			discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			payment_status VARCHAR(30) NOT NULL DEFAULT 'pending',
			booking_status VARCHAR(30) NOT NULL DEFAULT 'confirmed',
			offline_payment_type_id BIGINT UNSIGNED NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY booking_code (booking_code),
			KEY guest_id (guest_id),
			KEY room_type_id (room_type_id),
			KEY check_in (check_in),
			KEY check_out (check_out),
			KEY booking_status (booking_status),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'guest_checkin_checkout' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			guest_id BIGINT UNSIGNED NOT NULL,
			checkin_at DATETIME NULL,
			checkout_at DATETIME NULL,
			room_number VARCHAR(50) NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'expected',
			checked_in_by BIGINT UNSIGNED NULL,
			checked_out_by BIGINT UNSIGNED NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY booking_id (booking_id),
			KEY guest_id (guest_id),
			KEY status (status),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'room_bills' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			guest_id BIGINT UNSIGNED NOT NULL,
			description VARCHAR(255) NOT NULL,
			amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			bill_date DATE NOT NULL,
			payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
			offline_payment_type_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY booking_id (booking_id),
			KEY guest_id (guest_id),
			KEY bill_date (bill_date),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'restaurants' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			location VARCHAR(191) NULL,
			phone VARCHAR(50) NULL,
			opening_hours VARCHAR(255) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'restaurant_bills' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			restaurant_id BIGINT UNSIGNED NOT NULL,
			bill_number VARCHAR(50) NOT NULL,
			bill_date DATETIME NOT NULL,
			subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
			offline_payment_type_id BIGINT UNSIGNED NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY bill_number (bill_number),
			KEY restaurant_id (restaurant_id),
			KEY bill_date (bill_date),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'restaurant_guest_bills' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			restaurant_bill_id BIGINT UNSIGNED NOT NULL,
			guest_id BIGINT UNSIGNED NOT NULL,
			booking_id BIGINT UNSIGNED NULL,
			amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY restaurant_bill_id (restaurant_bill_id),
			KEY guest_id (guest_id),
			KEY booking_id (booking_id)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'non_border_restaurant_bills' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			restaurant_id BIGINT UNSIGNED NOT NULL,
			bill_number VARCHAR(50) NOT NULL,
			guest_name VARCHAR(191) NOT NULL,
			guest_phone VARCHAR(50) NULL,
			bill_date DATETIME NOT NULL,
			subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			payment_status VARCHAR(30) NOT NULL DEFAULT 'paid',
			offline_payment_type_id BIGINT UNSIGNED NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY bill_number (bill_number),
			KEY restaurant_id (restaurant_id),
			KEY bill_date (bill_date),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'laundry_bills' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			guest_id BIGINT UNSIGNED NOT NULL,
			booking_id BIGINT UNSIGNED NULL,
			bill_number VARCHAR(50) NOT NULL,
			bill_date DATE NOT NULL,
			items_description TEXT NULL,
			amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
			offline_payment_type_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY bill_number (bill_number),
			KEY guest_id (guest_id),
			KEY booking_id (booking_id),
			KEY bill_date (bill_date),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'damage_bills' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			guest_id BIGINT UNSIGNED NOT NULL,
			booking_id BIGINT UNSIGNED NULL,
			bill_number VARCHAR(50) NOT NULL,
			bill_date DATE NOT NULL,
			damage_description TEXT NOT NULL,
			amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
			offline_payment_type_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY bill_number (bill_number),
			KEY guest_id (guest_id),
			KEY booking_id (booking_id),
			KEY bill_date (bill_date),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'employee_roles' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL,
			slug VARCHAR(100) NOT NULL,
			description TEXT NULL,
			permissions TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'employees' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_code VARCHAR(50) NOT NULL,
			first_name VARCHAR(100) NOT NULL,
			last_name VARCHAR(100) NOT NULL,
			email VARCHAR(191) NULL,
			phone VARCHAR(50) NULL,
			role_id BIGINT UNSIGNED NOT NULL,
			hire_date DATE NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			address TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY employee_code (employee_code),
			KEY role_id (role_id),
			KEY status (status),
			KEY deleted_at (deleted_at)
		) $charset;";

		$tables[] = "CREATE TABLE " . self::table( 'employee_salaries' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id BIGINT UNSIGNED NOT NULL,
			salary_month DATE NOT NULL,
			base_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			allowances DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			net_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			payment_status VARCHAR(30) NOT NULL DEFAULT 'pending',
			paid_at DATETIME NULL,
			offline_payment_type_id BIGINT UNSIGNED NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY employee_month (employee_id, salary_month),
			KEY payment_status (payment_status),
			KEY deleted_at (deleted_at)
		) $charset;";

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		self::ensure_trash_columns();

		update_option( 'shmpp_db_version', self::DB_VERSION );
	}

	/**
	 * Migrate legacy hb_/ifm_/ifmpp_ tables and options to shmpp_.
	 */
	public static function migrate_legacy() {
		global $wpdb;

		$option_map = array(
			'hb_settings'       => 'shmpp_settings',
			'ifm_settings'      => 'shmpp_settings',
			'ifmpp_settings'    => 'shmpp_settings',
			'hb_db_version'     => 'shmpp_db_version',
			'ifm_db_version'    => 'shmpp_db_version',
			'ifmpp_db_version'  => 'shmpp_db_version',
			'hb_export_token'   => 'shmpp_export_token',
			'ifm_export_token'  => 'shmpp_export_token',
			'ifmpp_export_token'=> 'shmpp_export_token',
			'hb_demo_seeded'    => 'shmpp_demo_seeded',
			'ifm_demo_seeded'   => 'shmpp_demo_seeded',
			'ifmpp_demo_seeded' => 'shmpp_demo_seeded',
		);

		foreach ( $option_map as $old => $new ) {
			if ( false !== get_option( $new, false ) ) {
				continue;
			}
			$old_val = get_option( $old, false );
			if ( false !== $old_val ) {
				update_option( $new, $old_val );
			}
		}

		$suffixes = array(
			'room_types',
			'amenities',
			'room_type_amenities',
			'room_gallery',
			'booking_date_slots',
			'guests',
			'discounts',
			'offline_payment_types',
			'bookings',
			'guest_checkin_checkout',
			'room_bills',
			'restaurants',
			'restaurant_bills',
			'restaurant_guest_bills',
			'non_border_restaurant_bills',
			'laundry_bills',
			'damage_bills',
			'employee_roles',
			'employees',
			'employee_salaries',
		);

		$legacy_prefixes = array( 'ifmpp_', 'ifm_', 'hb_' );

		foreach ( $suffixes as $suffix ) {
			$new = self::safe_identifier( $wpdb->prefix . 'shmpp_' . $suffix );
			if ( ! $new ) {
				continue;
			}
			$new_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new ) );

			foreach ( $legacy_prefixes as $legacy_prefix ) {
				$old = self::safe_identifier( $wpdb->prefix . $legacy_prefix . $suffix );
				if ( ! $old ) {
					continue;
				}
				$old_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old ) );
				if ( ! $old_exists ) {
					continue;
				}

				if ( $new_exists ) {
					$old_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE 1 = %d', $old, 1 ) );
					if ( $old_count > 0 ) {
						// Prefer legacy data over newly created empty/demo ifmpp_ tables.
						$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $new ) );
						$wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $old, $new ) );
						$new_exists = true;
					}
					continue;
				}

				$wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $old, $new ) );
				$new_exists = true;
			}
		}

		// Update shortcodes in content (legacy tag, then the pre-rename shmpp_search tag).
		$wpdb->query(
			$wpdb->prepare( 'UPDATE %i SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s', $wpdb->posts, '[hotel_booking_search',
				'[shmpp_search',
				'%[hotel_booking_search%'
			)
		);
		$wpdb->query(
			$wpdb->prepare( 'UPDATE %i SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s', $wpdb->posts, '[staynexus_hotel_manager_search',
				'[shmpp_search',
				'%[staynexus_hotel_manager_search%'
			)
		);
	}

	/**
	 * Validate a table identifier before it is passed to %i placeholders.
	 *
	 * Identifiers built from variables must be allow-listed against a strict pattern
	 * before use. Returns the identifier unchanged if safe, or null if it is not.
	 *
	 * @param string $identifier Fully-qualified table name to validate.
	 * @return string|null
	 */
	private static function safe_identifier( $identifier ) {
		if ( is_string( $identifier ) && preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) ) {
			return $identifier;
		}
		return null;
	}

	/**
	 * Ensure deleted_at exists on all trashable tables (existing installs).
	 */
	public static function ensure_trash_columns() {
		global $wpdb;
		$tables = array(
			'room_types',
			'amenities',
			'booking_date_slots',
			'guests',
			'discounts',
			'offline_payment_types',
			'bookings',
			'guest_checkin_checkout',
			'room_bills',
			'restaurants',
			'restaurant_bills',
			'non_border_restaurant_bills',
			'laundry_bills',
			'damage_bills',
			'employee_roles',
			'employees',
			'employee_salaries',
		);

		foreach ( $tables as $name ) {
			$table = self::table( $name );
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( ! $exists ) {
				continue;
			}
			$col = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, 'deleted_at' ) );
			if ( empty( $col ) ) {
					$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN deleted_at DATETIME NULL, ADD KEY deleted_at (deleted_at)', $table ) );
			}
		}
	}

	public static function seed_defaults() {
		global $wpdb;

		$payments = self::table( 'offline_payment_types' );
		$count    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE 1 = %d', $payments, 1 ) );
		if ( 0 === $count ) {
			$defaults = array(
				array( 'Cash', 'cash', 'Cash payment at front desk' ),
				array( 'Card', 'card', 'Credit/Debit card at POS' ),
				array( 'Bank Transfer', 'bank-transfer', 'Direct bank transfer' ),
				array( 'Cheque', 'cheque', 'Cheque payment' ),
			);
			foreach ( $defaults as $row ) {
				$wpdb->insert(
					$payments,
					array(
						'name'        => $row[0],
						'slug'        => $row[1],
						'description' => $row[2],
						'is_active'   => 1,
					),
					array( '%s', '%s', '%s', '%d' )
				);
			}
		}

		$roles = self::table( 'employee_roles' );
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE 1 = %d', $roles, 1 ) );
		if ( 0 === $count ) {
			$defaults = array(
				array( 'Manager', 'manager', 'Hotel manager' ),
				array( 'Receptionist', 'receptionist', 'Front desk staff' ),
				array( 'Housekeeping', 'housekeeping', 'Room cleaning staff' ),
				array( 'Chef', 'chef', 'Restaurant kitchen staff' ),
				array( 'Waiter', 'waiter', 'Restaurant service staff' ),
				array( 'Accountant', 'accountant', 'Finance and billing' ),
			);
			foreach ( $defaults as $row ) {
				$wpdb->insert(
					$roles,
					array(
						'name'        => $row[0],
						'slug'        => $row[1],
						'description' => $row[2],
					),
					array( '%s', '%s', '%s' )
				);
			}
		}

		$settings = get_option( 'shmpp_settings' );
		if ( ! $settings ) {
			update_option(
				'shmpp_settings',
				array(
					'hotel_name'      => 'Aurora Bay Resort & Spa',
					'currency'        => 'USD',
					'currency_symbol' => '$',
					'tax_rate'        => 12,
					'check_in_time'   => '15:00',
					'check_out_time'  => '11:00',
					'booking_page_id' => 0,
					'enable_frontend' => true,
					'phone'           => '+1 (415) 555-0148',
					'email'           => 'reservations@aurorabay.example',
					'address'         => '128 Harbor View Drive, Monterey Bay, CA 93940',
				)
			);
		}

		self::seed_demo_content();
	}

	/**
	 * Insert realistic sample data once (when no room types exist yet).
	 *
	 * @param bool $force If true, skip the empty-room guard (caller must ensure tables are clear).
	 */
	public static function seed_demo_content( $force = false ) {
		global $wpdb;

		$rooms_table = self::table( 'room_types' );
		if ( ! $force && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE 1 = %d', $rooms_table, 1 ) ) > 0 ) {
			return false;
		}

		// Align hotel branding with demo content when still on defaults / empty.
		$settings = get_option( 'shmpp_settings', array() );
		if ( empty( $settings['hotel_name'] ) || 'Grand Hotel' === $settings['hotel_name'] ) {
			update_option(
				'shmpp_settings',
				array_merge(
					is_array( $settings ) ? $settings : array(),
					array(
						'hotel_name'      => 'Aurora Bay Resort & Spa',
						'currency'        => 'USD',
						'currency_symbol' => '$',
						'tax_rate'        => 12,
						'check_in_time'   => '15:00',
						'check_out_time'  => '11:00',
						'phone'           => '+1 (415) 555-0148',
						'email'           => 'reservations@aurorabay.example',
						'address'         => '128 Harbor View Drive, Monterey Bay, CA 93940',
					)
				)
			);
		}

		$cash_table = self::table( 'offline_payment_types' );
		$cash_id    = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM %i WHERE slug = %s', $cash_table, 'cash' )
		);
		$card_id = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM %i WHERE slug = %s', $cash_table, 'card' )
		);

		// Amenities.
		$amenity_defs = array(
			array( 'Free Wi‑Fi', 'wifi', 'High-speed wireless internet' ),
			array( 'Air conditioning', 'ac', 'Individually controlled climate' ),
			array( 'King bed', 'bed', 'Premium king-size mattress' ),
			array( 'Ocean view', 'ocean', 'Floor-to-ceiling ocean outlook' ),
			array( 'Mini bar', 'minibar', 'Stocked complimentary mini bar' ),
			array( 'Bathtub', 'bath', 'Soaking tub with rainfall shower' ),
			array( 'Work desk', 'desk', 'Ergonomic workspace' ),
			array( 'Balcony', 'balcony', 'Private furnished balcony' ),
			array( 'Smart TV', 'tv', '55\" 4K smart television' ),
			array( 'Coffee machine', 'coffee', 'Nespresso machine & pods' ),
			array( 'Safe', 'safe', 'In-room electronic safe' ),
			array( 'Room service', 'service', '24-hour in-room dining' ),
		);
		$amenity_ids = array();
		foreach ( $amenity_defs as $a ) {
			$wpdb->insert(
				self::table( 'amenities' ),
				array(
					'name'        => $a[0],
					'icon'        => $a[1],
					'description' => $a[2],
				)
			);
			$amenity_ids[ $a[1] ] = (int) $wpdb->insert_id;
		}

		// Room types (images left empty — upload locally via Media Library).
		$room_defs = array(
			array(
				'name'         => 'Harbor Deluxe',
				'slug'         => 'harbor-deluxe',
				'description'  => 'Bright deluxe room with a soft coastal palette, king bed, and partial harbor view. Ideal for couples seeking a quiet stay near the waterfront.',
				'base_price'   => 189.00,
				'max_adults'   => 2,
				'max_children' => 1,
				'total_rooms'  => 18,
				'image_url'    => '',
				'gallery'      => array(),
				'amenities'    => array( 'wifi', 'ac', 'bed', 'tv', 'coffee', 'safe' ),
			),
			array(
				'name'         => 'Ocean Suite',
				'slug'         => 'ocean-suite',
				'description'  => 'Spacious suite with floor-to-ceiling windows, separate living area, soaking tub, and uninterrupted ocean views. Includes evening turndown service.',
				'base_price'   => 329.00,
				'max_adults'   => 3,
				'max_children' => 2,
				'total_rooms'  => 8,
				'image_url'    => '',
				'gallery'      => array(),
				'amenities'    => array( 'wifi', 'ac', 'bed', 'ocean', 'minibar', 'bath', 'balcony', 'tv', 'coffee', 'safe', 'service' ),
			),
			array(
				'name'         => 'Garden Family Room',
				'slug'         => 'garden-family',
				'description'  => 'Family-friendly room opening onto the courtyard garden. Two queen beds, blackout curtains, and a compact fridge for snacks and drinks.',
				'base_price'   => 249.00,
				'max_adults'   => 4,
				'max_children' => 3,
				'total_rooms'  => 12,
				'image_url'    => '',
				'gallery'      => array(),
				'amenities'    => array( 'wifi', 'ac', 'tv', 'coffee', 'desk', 'safe' ),
			),
			array(
				'name'         => 'Executive Studio',
				'slug'         => 'executive-studio',
				'description'  => 'Compact studio tailored for business travelers: wide work desk, ergonomic chair, dual monitors on request, and late checkout options.',
				'base_price'   => 219.00,
				'max_adults'   => 2,
				'max_children' => 0,
				'total_rooms'  => 10,
				'image_url'    => '',
				'gallery'      => array(),
				'amenities'    => array( 'wifi', 'ac', 'desk', 'tv', 'coffee', 'safe' ),
			),
			array(
				'name'         => 'Penthouse Residence',
				'slug'         => 'penthouse-residence',
				'description'  => 'Top-floor residence with wraparound terrace, dining area for six, butler pantry, and curated art. Perfect for celebrations and VIP stays.',
				'base_price'   => 689.00,
				'max_adults'   => 4,
				'max_children' => 2,
				'total_rooms'  => 2,
				'image_url'    => '',
				'gallery'      => array(),
				'amenities'    => array( 'wifi', 'ac', 'bed', 'ocean', 'minibar', 'bath', 'balcony', 'tv', 'coffee', 'safe', 'service', 'desk' ),
			),
		);

		$room_ids = array();
		foreach ( $room_defs as $room ) {
			$wpdb->insert(
				$rooms_table,
				array(
					'name'         => $room['name'],
					'slug'         => $room['slug'],
					'description'  => $room['description'],
					'base_price'   => $room['base_price'],
					'max_adults'   => $room['max_adults'],
					'max_children' => $room['max_children'],
					'total_rooms'  => $room['total_rooms'],
					'image_url'    => $room['image_url'],
					'status'       => 'active',
				)
			);
			$rid = (int) $wpdb->insert_id;
			$room_ids[ $room['slug'] ] = $rid;

			foreach ( $room['amenities'] as $key ) {
				if ( empty( $amenity_ids[ $key ] ) ) {
					continue;
				}
				$wpdb->insert(
					self::table( 'room_type_amenities' ),
					array(
						'room_type_id' => $rid,
						'amenity_id'   => $amenity_ids[ $key ],
					)
				);
			}

			$order = 0;
			foreach ( $room['gallery'] as $url ) {
				$wpdb->insert(
					self::table( 'room_gallery' ),
					array(
						'room_type_id' => $rid,
						'image_url'    => $url,
						'sort_order'   => $order++,
					)
				);
			}

			// 60 days of availability slots with light weekend premium.
			for ( $i = 0; $i < 60; $i++ ) {
				$date    = gmdate( 'Y-m-d', strtotime( "+{$i} days" ) );
				$dow     = (int) gmdate( 'N', strtotime( $date ) );
				$booked  = ( 5 === $dow || 6 === $dow ) ? wp_rand( 1, max( 1, (int) floor( $room['total_rooms'] * 0.45 ) ) ) : wp_rand( 0, max( 1, (int) floor( $room['total_rooms'] * 0.25 ) ) );
				$booked  = min( $booked, $room['total_rooms'] - 1 );
				$override = ( 5 === $dow || 6 === $dow ) ? round( $room['base_price'] * 1.15, 2 ) : null;
				$wpdb->insert(
					self::table( 'booking_date_slots' ),
					array(
						'room_type_id'    => $rid,
						'slot_date'       => $date,
						'available_rooms' => $room['total_rooms'],
						'booked_rooms'    => $booked,
						'price_override'  => $override,
						'status'          => 'open',
					)
				);
			}
		}

		// Discounts.
		$wpdb->insert(
			self::table( 'discounts' ),
			array(
				'code'           => 'WELCOME10',
				'name'           => 'Welcome 10% off',
				'discount_type'  => 'percent',
				'discount_value' => 10,
				'min_nights'     => 2,
				'max_uses'       => 200,
				'used_count'     => 12,
				'starts_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
				'ends_at'        => gmdate( 'Y-m-d H:i:s', strtotime( '+90 days' ) ),
				'status'         => 'active',
			)
		);
		$wpdb->insert(
			self::table( 'discounts' ),
			array(
				'code'           => 'STAY3GET50',
				'name'           => '$50 off stays of 3+ nights',
				'discount_type'  => 'fixed',
				'discount_value' => 50,
				'min_nights'     => 3,
				'max_uses'       => 80,
				'used_count'     => 5,
				'starts_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
				'ends_at'        => gmdate( 'Y-m-d H:i:s', strtotime( '+60 days' ) ),
				'status'         => 'active',
			)
		);
		$discounts_table = self::table( 'discounts' );
		$discount_id     = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM %i WHERE code = %s', $discounts_table, 'WELCOME10' )
		);

		// Restaurants.
		$rest_defs = array(
			array( 'The Tide Table', 'Lobby level, west wing', '+1 (415) 555-0190', '07:00 – 22:00' ),
			array( 'Cedar & Salt Grill', 'Pool terrace', '+1 (415) 555-0191', '12:00 – 23:00' ),
			array( 'Lantern Lounge', 'Rooftop bar', '+1 (415) 555-0192', '16:00 – 01:00' ),
		);
		$restaurant_ids = array();
		foreach ( $rest_defs as $r ) {
			$wpdb->insert(
				self::table( 'restaurants' ),
				array(
					'name'          => $r[0],
					'location'      => $r[1],
					'phone'         => $r[2],
					'opening_hours' => $r[3],
					'status'        => 'active',
				)
			);
			$restaurant_ids[] = (int) $wpdb->insert_id;
		}

		// Employees.
		$role_map = array();
		$roles_table = self::table( 'employee_roles' );
		$roles_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, slug FROM %i WHERE 1 = %d', $roles_table, 1 ), ARRAY_A );
		foreach ( $roles_rows as $rr ) {
			$role_map[ $rr['slug'] ] = (int) $rr['id'];
		}

		$employee_defs = array(
			array( 'EMP-1001', 'Maya', 'Chen', 'maya.chen@aurorabay.example', '+1 415-555-1001', 'manager', '2019-03-12', 7200, 400 ),
			array( 'EMP-1002', 'Luis', 'Ramirez', 'luis.ramirez@aurorabay.example', '+1 415-555-1002', 'receptionist', '2021-06-01', 3200, 150 ),
			array( 'EMP-1003', 'Aisha', 'Patel', 'aisha.patel@aurorabay.example', '+1 415-555-1003', 'receptionist', '2022-01-18', 3100, 120 ),
			array( 'EMP-1004', 'Tom', 'Nguyen', 'tom.nguyen@aurorabay.example', '+1 415-555-1004', 'housekeeping', '2020-09-09', 2800, 100 ),
			array( 'EMP-1005', 'Sofia', 'Berg', 'sofia.berg@aurorabay.example', '+1 415-555-1005', 'chef', '2018-11-22', 4500, 250 ),
			array( 'EMP-1006', 'James', 'Okoro', 'james.okoro@aurorabay.example', '+1 415-555-1006', 'waiter', '2023-04-03', 2600, 80 ),
			array( 'EMP-1007', 'Elena', 'Rossi', 'elena.rossi@aurorabay.example', '+1 415-555-1007', 'accountant', '2017-02-14', 4800, 200 ),
		);
		$employee_ids = array();
		$month_now    = gmdate( 'Y-m-01' );
		$month_prev   = gmdate( 'Y-m-01', strtotime( 'first day of last month' ) );
		foreach ( $employee_defs as $e ) {
			$wpdb->insert(
				self::table( 'employees' ),
				array(
					'employee_code' => $e[0],
					'first_name'    => $e[1],
					'last_name'     => $e[2],
					'email'         => $e[3],
					'phone'         => $e[4],
					'role_id'       => $role_map[ $e[5] ] ?? 0,
					'hire_date'     => $e[6],
					'status'        => 'active',
					'address'       => 'Monterey Bay, CA',
				)
			);
			$eid = (int) $wpdb->insert_id;
			$employee_ids[] = $eid;

			$bank_id = (int) $wpdb->get_var(
					$wpdb->prepare( 'SELECT id FROM %i WHERE slug = %s', $cash_table, 'bank-transfer' )
			);

			foreach ( array( $month_prev, $month_now ) as $mi => $month ) {
				$base = (float) $e[7];
				$all  = (float) $e[8];
				$ded  = 0 === $mi ? 50 : 0;
				$wpdb->insert(
					self::table( 'employee_salaries' ),
					array(
						'employee_id'             => $eid,
						'salary_month'            => $month,
						'base_salary'             => $base,
						'allowances'              => $all,
						'deductions'              => $ded,
						'net_salary'              => $base + $all - $ded,
						'payment_status'          => 0 === $mi ? 'paid' : 'pending',
						'paid_at'                 => 0 === $mi ? gmdate( 'Y-m-d H:i:s', strtotime( $month . ' +25 days' ) ) : null,
						'offline_payment_type_id' => $bank_id ?: null,
						'notes'                   => 0 === $mi ? 'Monthly payroll processed' : 'Current cycle',
					)
				);
			}
		}

		// Guests.
		$guest_defs = array(
			array( 'Emma', 'Whitaker', 'emma.whitaker@email.com', '+1 212-555-0142', '41 Mercer St', 'New York', 'USA', 'Passport', 'US9988771' ),
			array( 'Noah', 'Kim', 'noah.kim@email.com', '+1 310-555-0177', '902 Ocean Ave', 'Los Angeles', 'USA', 'Driver License', 'CA-D482910' ),
			array( 'Priya', 'Sharma', 'priya.sharma@email.com', '+44 20 7946 0958', '14 King’s Road', 'London', 'UK', 'Passport', 'GB4411223' ),
			array( 'Carlos', 'Mendez', 'carlos.mendez@email.com', '+52 55 5555 2211', 'Av. Reforma 222', 'Mexico City', 'Mexico', 'Passport', 'MX7788990' ),
			array( 'Hannah', 'Brooks', 'hannah.brooks@email.com', '+1 617-555-0133', '88 Beacon St', 'Boston', 'USA', 'Passport', 'US1122334' ),
			array( 'Kenji', 'Sato', 'kenji.sato@email.com', '+81 3-5555-0199', '2-1 Shibuya', 'Tokyo', 'Japan', 'Passport', 'JP5566778' ),
			array( 'Olivia', 'Moreau', 'olivia.moreau@email.com', '+33 1 42 68 53 00', '12 Rue Cler', 'Paris', 'France', 'Passport', 'FR3344556' ),
			array( 'Daniel', 'Okafor', 'daniel.okafor@email.com', '+234 803 555 0198', '15 Admiralty Way', 'Lagos', 'Nigeria', 'Passport', 'NG6677889' ),
		);
		$guest_ids = array();
		foreach ( $guest_defs as $g ) {
			$wpdb->insert(
				self::table( 'guests' ),
				array(
					'first_name' => $g[0],
					'last_name'  => $g[1],
					'email'      => $g[2],
					'phone'      => $g[3],
					'address'    => $g[4],
					'city'       => $g[5],
					'country'    => $g[6],
					'id_type'    => $g[7],
					'id_number'  => $g[8],
					'notes'      => '',
				)
			);
			$guest_ids[] = (int) $wpdb->insert_id;
		}

		$tax_rate = 12;
		$booking_defs = array(
			// Past checked out.
			array( 0, 'harbor-deluxe', -8, -5, 2, 0, 1, 'checked_out', 'paid', false, '104' ),
			array( 1, 'garden-family', -4, -1, 2, 2, 1, 'checked_out', 'paid', false, '212' ),
			// Currently in house.
			array( 2, 'ocean-suite', -1, 3, 2, 1, 1, 'checked_in', 'paid', true, '501' ),
			array( 3, 'executive-studio', 0, 2, 1, 0, 1, 'checked_in', 'pending', false, '308' ),
			// Upcoming.
			array( 4, 'harbor-deluxe', 2, 5, 2, 0, 1, 'confirmed', 'pending', true, null ),
			array( 5, 'penthouse-residence', 5, 9, 2, 0, 1, 'confirmed', 'paid', false, null ),
			array( 6, 'garden-family', 7, 10, 2, 2, 1, 'confirmed', 'pending', false, null ),
			array( 7, 'ocean-suite', 10, 14, 2, 0, 1, 'confirmed', 'pending', false, null ),
		);

		foreach ( $booking_defs as $b ) {
			$guest_id = $guest_ids[ $b[0] ];
			$room_id  = $room_ids[ $b[1] ];
			$room     = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $rooms_table, $room_id ), ARRAY_A );
			$check_in  = gmdate( 'Y-m-d', strtotime( $b[2] . ' days' ) );
			$check_out = gmdate( 'Y-m-d', strtotime( $b[3] . ' days' ) );
			$nights    = max( 1, (int) ( ( strtotime( $check_out ) - strtotime( $check_in ) ) / DAY_IN_SECONDS ) );
			$subtotal  = (float) $room['base_price'] * $nights * (int) $b[6];
			$disc_amt  = ! empty( $b[9] ) ? round( $subtotal * 0.10, 2 ) : 0;
			$taxable   = max( 0, $subtotal - $disc_amt );
			$tax       = round( $taxable * ( $tax_rate / 100 ), 2 );
			$total     = $taxable + $tax;
			$code      = 'SHM-' . strtoupper( substr( md5( $guest_id . $check_in . $room_id ), 0, 8 ) );

			$wpdb->insert(
				self::table( 'bookings' ),
				array(
					'booking_code'            => $code,
					'guest_id'                => $guest_id,
					'room_type_id'            => $room_id,
					'check_in'                => $check_in,
					'check_out'               => $check_out,
					'adults'                  => $b[4],
					'children'                => $b[5],
					'rooms_count'             => $b[6],
					'discount_id'             => ! empty( $b[9] ) ? $discount_id : null,
					'discount_amount'         => $disc_amt,
					'subtotal'                => $subtotal,
					'tax_amount'              => $tax,
					'total_amount'            => $total,
					'payment_status'          => $b[8],
					'booking_status'          => $b[7],
					'offline_payment_type_id' => 'paid' === $b[8] ? $card_id : null,
					'notes'                   => 'Seeded demo booking',
				)
			);
			$booking_id = (int) $wpdb->insert_id;

			$check_status = 'expected';
			$checkin_at   = null;
			$checkout_at  = null;
			if ( 'checked_in' === $b[7] ) {
				$check_status = 'checked_in';
				$checkin_at   = $check_in . ' 15:20:00';
			} elseif ( 'checked_out' === $b[7] ) {
				$check_status = 'checked_out';
				$checkin_at   = $check_in . ' 14:45:00';
				$checkout_at  = $check_out . ' 10:30:00';
			}

			$wpdb->insert(
				self::table( 'guest_checkin_checkout' ),
				array(
					'booking_id'     => $booking_id,
					'guest_id'       => $guest_id,
					'checkin_at'     => $checkin_at,
					'checkout_at'    => $checkout_at,
					'room_number'    => $b[10],
					'status'         => $check_status,
					'checked_in_by'  => $checkin_at ? 1 : null,
					'checked_out_by' => $checkout_at ? 1 : null,
				)
			);

			$wpdb->insert(
				self::table( 'room_bills' ),
				array(
					'booking_id'              => $booking_id,
					'guest_id'                => $guest_id,
					'description'             => sprintf( 'Room booking %s (%d nights)', $code, $nights ),
					'amount'                  => $taxable,
					'tax_amount'              => $tax,
					'total_amount'            => $total,
					'bill_date'               => $check_in,
					'payment_status'          => 'paid' === $b[8] ? 'paid' : 'unpaid',
					'offline_payment_type_id' => 'paid' === $b[8] ? $card_id : null,
				)
			);
		}

		// Restaurant / laundry / damage / walk-in bills for dashboard realism.
		$in_house_guest = $guest_ids[2];
		$bookings_table = self::table( 'bookings' );
		$in_house_book  = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM %i WHERE guest_id = %d ORDER BY id DESC LIMIT 1', $bookings_table, $in_house_guest
			)
		);

		$wpdb->insert(
			self::table( 'restaurant_bills' ),
			array(
				'restaurant_id'           => $restaurant_ids[0],
				'bill_number'             => 'RB-DEMO001',
				'bill_date'               => current_time( 'mysql' ),
				'subtotal'                => 86.50,
				'tax_amount'              => 10.38,
				'total_amount'            => 96.88,
				'payment_status'          => 'unpaid',
				'offline_payment_type_id' => null,
				'notes'                   => 'Dinner for two — charged to room 501',
			)
		);
		$rb_id = (int) $wpdb->insert_id;
		$wpdb->insert(
			self::table( 'restaurant_guest_bills' ),
			array(
				'restaurant_bill_id' => $rb_id,
				'guest_id'           => $in_house_guest,
				'booking_id'         => $in_house_book,
				'amount'             => 96.88,
			)
		);

		$wpdb->insert(
			self::table( 'restaurant_bills' ),
			array(
				'restaurant_id'           => $restaurant_ids[1],
				'bill_number'             => 'RB-DEMO002',
				'bill_date'               => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
				'subtotal'                => 142.00,
				'tax_amount'              => 17.04,
				'total_amount'            => 159.04,
				'payment_status'          => 'paid',
				'offline_payment_type_id' => $card_id,
				'notes'                   => 'Poolside lunch',
			)
		);

		$wpdb->insert(
			self::table( 'non_border_restaurant_bills' ),
			array(
				'restaurant_id'           => $restaurant_ids[2],
				'bill_number'             => 'NB-DEMO001',
				'guest_name'              => 'Walk-in: Avery Collins',
				'guest_phone'             => '+1 408-555-0166',
				'bill_date'               => current_time( 'mysql' ),
				'subtotal'                => 54.00,
				'tax_amount'              => 6.48,
				'total_amount'            => 60.48,
				'payment_status'          => 'paid',
				'offline_payment_type_id' => $cash_id,
				'notes'                   => 'Rooftop cocktails — non-resident',
			)
		);

		$wpdb->insert(
			self::table( 'laundry_bills' ),
			array(
				'guest_id'                => $in_house_guest,
				'booking_id'              => $in_house_book,
				'bill_number'             => 'LB-DEMO001',
				'bill_date'               => gmdate( 'Y-m-d' ),
				'items_description'       => '2 shirts, 1 dress, express service',
				'amount'                  => 38.00,
				'tax_amount'              => 4.56,
				'total_amount'            => 42.56,
				'payment_status'          => 'unpaid',
				'offline_payment_type_id' => null,
			)
		);

		$past_guest = $guest_ids[0];
		$past_book = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM %i WHERE guest_id = %d ORDER BY id ASC LIMIT 1', $bookings_table, $past_guest
			)
		);
		$wpdb->insert(
			self::table( 'damage_bills' ),
			array(
				'guest_id'                => $past_guest,
				'booking_id'              => $past_book,
				'bill_number'             => 'DB-DEMO001',
				'bill_date'               => gmdate( 'Y-m-d', strtotime( '-5 days' ) ),
				'damage_description'      => 'Broken bedside lamp shade — replacement cost',
				'amount'                  => 65.00,
				'tax_amount'              => 7.80,
				'total_amount'            => 72.80,
				'payment_status'          => 'paid',
				'offline_payment_type_id' => $card_id,
			)
		);

		update_option( 'shmpp_demo_seeded', 1 );
		return true;
	}
}
