<?php
defined( 'ABSPATH' ) || exit;

class InnflowManagerAutoloader {

	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, 'InnflowManager' ) ) {
			return;
		}

		$map = array(
			'InnflowManagerPlugin'                 => 'class-plugin.php',
			'InnflowManagerActivator'              => 'class-activator.php',
			'InnflowManagerDeactivator'            => 'class-deactivator.php',
			'InnflowManagerDatabase'               => 'class-database.php',
			'InnflowManagerTrash'                  => 'class-trash.php',
			'InnflowManagerXlsx_Writer'            => 'class-xlsx-writer.php',
			'InnflowManagerAdmin'                  => 'class-admin.php',
			'InnflowManagerFrontend'               => 'class-frontend.php',
			'InnflowManagerRest_API'               => 'api/class-rest-api.php',
			'InnflowManagerRooms_Controller'       => 'api/class-rooms-controller.php',
			'InnflowManagerBookings_Controller'    => 'api/class-bookings-controller.php',
			'InnflowManagerGuests_Controller'      => 'api/class-guests-controller.php',
			'InnflowManagerBilling_Controller'     => 'api/class-billing-controller.php',
			'InnflowManagerDashboard_Controller'   => 'api/class-dashboard-controller.php',
			'InnflowManagerEmployees_Controller'   => 'api/class-employees-controller.php',
			'InnflowManagerSettings_Controller'    => 'api/class-settings-controller.php',
			'InnflowManagerRestaurants_Controller' => 'api/class-restaurants-controller.php',
			'InnflowManagerAmenities_Controller'   => 'api/class-amenities-controller.php',
			'InnflowManagerTrash_Controller'       => 'api/class-trash-controller.php',
		);

		if ( ! isset( $map[ $class ] ) ) {
			return;
		}

		$file = InnflowManagerPLUGIN_DIR . 'includes/' . $map[ $class ];
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
