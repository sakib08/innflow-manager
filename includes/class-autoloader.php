<?php
defined( 'ABSPATH' ) || exit;

class ShmppAutoloader {

	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, 'Shmpp' ) ) {
			return;
		}

		$map = array(
			'ShmppPlugin'                 => 'class-plugin.php',
			'ShmppActivator'              => 'class-activator.php',
			'ShmppDeactivator'            => 'class-deactivator.php',
			'ShmppDatabase'               => 'class-database.php',
			'ShmppTrash'                  => 'class-trash.php',
			'ShmppXlsxWriter'            => 'class-xlsx-writer.php',
			'ShmppAdmin'                  => 'class-admin.php',
			'ShmppFrontend'               => 'class-frontend.php',
			'ShmppRestAPI'               => 'api/class-rest-api.php',
			'ShmppRoomsController'       => 'api/class-rooms-controller.php',
			'ShmppBookingsController'    => 'api/class-bookings-controller.php',
			'ShmppGuestsController'      => 'api/class-guests-controller.php',
			'ShmppBillingController'     => 'api/class-billing-controller.php',
			'ShmppDashboardController'   => 'api/class-dashboard-controller.php',
			'ShmppEmployeesController'   => 'api/class-employees-controller.php',
			'ShmppSettingsController'    => 'api/class-settings-controller.php',
			'ShmppRestaurantsController' => 'api/class-restaurants-controller.php',
			'ShmppAmenitiesController'   => 'api/class-amenities-controller.php',
			'ShmppTrashController'       => 'api/class-trash-controller.php',
		);

		if ( ! isset( $map[ $class ] ) ) {
			return;
		}

		$file = SHMPP_PLUGIN_DIR . 'includes/' . $map[ $class ];
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
