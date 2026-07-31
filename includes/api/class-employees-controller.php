<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.WP.AlternativeFunctions.file_system_operations_fread,WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV stream to php://output and reading uploaded tmp files.

// Custom tables: table names cannot use prepare placeholders; queries are built from trusted InnflowManagerDatabase::table() keys.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

class InnflowManagerEmployees_Controller {

	const NS = 'innflow-manager/v1';

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/employees',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_employees' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_employee' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/employees/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_employees' ),
				'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/employees/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_employee' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_employee' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/employee-roles',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_roles' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_role' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/salaries',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_salaries' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_salary' ),
					'permission_callback' => array( 'InnflowManagerRest_API', 'permission_manage' ),
				),
			)
		);
	}

	public function list_employees() {
		global $wpdb;
		$emp  = InnflowManagerDatabase::table( 'employees' );
		$role = InnflowManagerDatabase::table( 'employee_roles' );
		$rows = $wpdb->get_results(
			"SELECT e.*, r.name AS role_name FROM {$emp} e LEFT JOIN {$role} r ON r.id = e.role_id WHERE " . InnflowManagerTrash::alive_sql( 'e' ) . ' ORDER BY e.id DESC',
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	public function export_employees( $request ) {
		global $wpdb;
		$format = sanitize_text_field( $request->get_param( 'format' ) ?: 'csv' );
		$emp    = InnflowManagerDatabase::table( 'employees' );
		$role   = InnflowManagerDatabase::table( 'employee_roles' );
		$employees = $wpdb->get_results(
			"SELECT e.*, r.name AS role_name FROM {$emp} e LEFT JOIN {$role} r ON r.id = e.role_id WHERE " . InnflowManagerTrash::alive_sql( 'e' ) . ' ORDER BY e.id ASC',
			ARRAY_A
		);

		$columns = array(
			'id',
			'employee_code',
			'first_name',
			'last_name',
			'email',
			'phone',
			'role',
			'hire_date',
			'status',
			'address',
		);

		$data_rows = array();
		foreach ( $employees as $row ) {
			$data_rows[] = array(
				$row['id'],
				$row['employee_code'],
				$row['first_name'],
				$row['last_name'],
				$row['email'],
				$row['phone'],
				$row['role_name'],
				$row['hire_date'],
				$row['status'],
				$row['address'],
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
			header( 'Content-Disposition: attachment; filename="employees-' . gmdate( 'Y-m-d' ) . '.xlsx"' );
			echo $writer->output(); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="employees-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, $columns );
		foreach ( $data_rows as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out );
		exit;
	}

	public function create_employee( $request ) {
		global $wpdb;
		$data = array(
			'employee_code' => sanitize_text_field( $request->get_param( 'employee_code' ) ?: ( 'EMP-' . wp_generate_password( 6, false, false ) ) ),
			'first_name'    => sanitize_text_field( $request->get_param( 'first_name' ) ),
			'last_name'     => sanitize_text_field( $request->get_param( 'last_name' ) ),
			'email'         => sanitize_email( $request->get_param( 'email' ) ),
			'phone'         => sanitize_text_field( $request->get_param( 'phone' ) ),
			'role_id'       => (int) $request->get_param( 'role_id' ),
			'hire_date'     => sanitize_text_field( $request->get_param( 'hire_date' ) ),
			'status'        => sanitize_text_field( $request->get_param( 'status' ) ?: 'active' ),
			'address'       => sanitize_textarea_field( $request->get_param( 'address' ) ),
		);
		$wpdb->insert( InnflowManagerDatabase::table( 'employees' ), $data );
		return $this->list_employees();
	}

	public function update_employee( $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		$data = array(
			'first_name' => sanitize_text_field( $request->get_param( 'first_name' ) ),
			'last_name'  => sanitize_text_field( $request->get_param( 'last_name' ) ),
			'email'      => sanitize_email( $request->get_param( 'email' ) ),
			'phone'      => sanitize_text_field( $request->get_param( 'phone' ) ),
			'role_id'    => (int) $request->get_param( 'role_id' ),
			'hire_date'  => sanitize_text_field( $request->get_param( 'hire_date' ) ),
			'status'     => sanitize_text_field( $request->get_param( 'status' ) ?: 'active' ),
			'address'    => sanitize_textarea_field( $request->get_param( 'address' ) ),
		);
		$wpdb->update( InnflowManagerDatabase::table( 'employees' ), $data, array( 'id' => $id ) );
		return $this->list_employees();
	}

	public function delete_employee( $request ) {
		$result = InnflowManagerTrash::trash( 'employees', (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'trashed' => true, 'id' => (int) $request['id'] ) );
	}

	public function list_roles() {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . InnflowManagerDatabase::table( 'employee_roles' ) . ' WHERE ' . InnflowManagerTrash::alive_sql() . ' ORDER BY name ASC',
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	public function create_role( $request ) {
		global $wpdb;
		$name = sanitize_text_field( $request->get_param( 'name' ) );
		$wpdb->insert(
			InnflowManagerDatabase::table( 'employee_roles' ),
			array(
				'name'        => $name,
				'slug'        => sanitize_title( $name ),
				'description' => sanitize_textarea_field( $request->get_param( 'description' ) ),
			)
		);
		return $this->list_roles();
	}

	public function list_salaries() {
		global $wpdb;
		$sal = InnflowManagerDatabase::table( 'employee_salaries' );
		$emp = InnflowManagerDatabase::table( 'employees' );
		$rows = $wpdb->get_results(
			"SELECT s.*, e.first_name, e.last_name, e.employee_code
			FROM {$sal} s LEFT JOIN {$emp} e ON e.id = s.employee_id
			WHERE " . InnflowManagerTrash::alive_sql( 's' ) . '
			ORDER BY s.employee_id ASC, s.salary_month DESC',
			ARRAY_A
		);

		$seen = array();
		foreach ( $rows as &$row ) {
			$eid = (int) $row['employee_id'];
			// First row encountered per employee is the most recent month (is_latest = active record).
			$row['is_latest'] = ! isset( $seen[ $eid ] );
			$seen[ $eid ]     = true;
		}
		unset( $row );

		usort(
			$rows,
			function ( $a, $b ) {
				return strcmp( $b['salary_month'], $a['salary_month'] );
			}
		);

		return rest_ensure_response( array_slice( $rows, 0, 200 ) );
	}

	public function create_salary( $request ) {
		global $wpdb;
		$base         = (float) $request->get_param( 'base_salary' );
		$all          = (float) $request->get_param( 'allowances' );
		$ded          = (float) $request->get_param( 'deductions' );
		$employee_id  = (int) $request->get_param( 'employee_id' );
		$salary_month = sanitize_text_field( $request->get_param( 'salary_month' ) );

		$data = array(
			'employee_id'             => $employee_id,
			'salary_month'            => $salary_month,
			'base_salary'             => $base,
			'allowances'              => $all,
			'deductions'              => $ded,
			'net_salary'              => $base + $all - $ded,
			'payment_status'          => sanitize_text_field( $request->get_param( 'payment_status' ) ?: 'pending' ),
			'paid_at'                 => $request->get_param( 'paid_at' ) ? sanitize_text_field( $request->get_param( 'paid_at' ) ) : null,
			'offline_payment_type_id' => $request->get_param( 'offline_payment_type_id' ) ? (int) $request->get_param( 'offline_payment_type_id' ) : null,
			'notes'                   => sanitize_textarea_field( $request->get_param( 'notes' ) ),
		);

		$table    = InnflowManagerDatabase::table( 'employee_salaries' );
		$existing = $employee_id && $salary_month
			? $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE employee_id = %d AND salary_month = %s",
					$employee_id,
					$salary_month
				)
			)
			: null;

		if ( $existing ) {
			// Merge into the existing record for this employee + month instead of creating a duplicate row.
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ) );
		} else {
			$wpdb->insert( $table, $data );
		}

		return $this->list_salaries();
	}
}
