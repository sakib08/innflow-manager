<?php
defined( 'ABSPATH' ) || exit;

class InnflowManagerPlugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
	}

	private function init_hooks() {
		add_action( 'rest_api_init', array( 'InnflowManagerRest_API', 'register_routes' ) );

		if ( is_admin() ) {
			InnflowManagerAdmin::instance();
		}

		InnflowManagerFrontend::instance();

		$db_version = get_option( 'ifmpp_db_version' );
		if ( ! $db_version || InnflowManagerDatabase::DB_VERSION !== $db_version ) {
			InnflowManagerDatabase::create_tables();
			InnflowManagerDatabase::seed_defaults();
		}
	}

}
