<?php
defined( 'ABSPATH' ) || exit;

class ShmppRestAPI {

	public static function register_routes() {
		$controllers = array(
			new ShmppRoomsController(),
			new ShmppBookingsController(),
			new ShmppGuestsController(),
			new ShmppBillingController(),
			new ShmppDashboardController(),
			new ShmppEmployeesController(),
			new ShmppSettingsController(),
			new ShmppRestaurantsController(),
			new ShmppAmenitiesController(),
			new ShmppTrashController(),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}

	public static function permission_manage() {
		return current_user_can( 'manage_options' );
	}
}
