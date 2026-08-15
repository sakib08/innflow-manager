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
			'ShmppPlugin'                 => 'class-shmpp-plugin.php',
			'ShmppActivator'              => 'class-shmpp-activator.php',
			'ShmppDeactivator'            => 'class-shmpp-deactivator.php',
			'ShmppDatabase'               => 'class-shmpp-database.php',
			'ShmppTrash'                  => 'class-shmpp-trash.php',
			'ShmppXlsxWriter'            => 'class-shmpp-xlsx-writer.php',
			'ShmppAdmin'                  => 'class-shmpp-admin.php',
			'ShmppFrontend'               => 'class-shmpp-frontend.php',
			'ShmppRestAPI'               => 'api/class-shmpp-rest-api.php',
			'ShmppRoomsController'       => 'api/class-shmpp-rooms-controller.php',
			'ShmppBookingsController'    => 'api/class-shmpp-bookings-controller.php',
			'ShmppGuestsController'      => 'api/class-shmpp-guests-controller.php',
			'ShmppBillingController'     => 'api/class-shmpp-billing-controller.php',
			'ShmppDashboardController'   => 'api/class-shmpp-dashboard-controller.php',
			'ShmppEmployeesController'   => 'api/class-shmpp-employees-controller.php',
			'ShmppSettingsController'    => 'api/class-shmpp-settings-controller.php',
			'ShmppRestaurantsController' => 'api/class-shmpp-restaurants-controller.php',
			'ShmppAmenitiesController'   => 'api/class-shmpp-amenities-controller.php',
			'ShmppTrashController'       => 'api/class-shmpp-trash-controller.php',
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
