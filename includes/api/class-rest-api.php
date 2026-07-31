<?php
defined( 'ABSPATH' ) || exit;

class InnflowManagerRest_API {

	public static function register_routes() {
		$controllers = array(
			new InnflowManagerRooms_Controller(),
			new InnflowManagerBookings_Controller(),
			new InnflowManagerGuests_Controller(),
			new InnflowManagerBilling_Controller(),
			new InnflowManagerDashboard_Controller(),
			new InnflowManagerEmployees_Controller(),
			new InnflowManagerSettings_Controller(),
			new InnflowManagerRestaurants_Controller(),
			new InnflowManagerAmenities_Controller(),
			new InnflowManagerTrash_Controller(),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}

	public static function permission_manage() {
		return current_user_can( 'manage_options' );
	}

	public static function permission_public() {
		return true;
	}
}
