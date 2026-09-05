<?php
defined( 'ABSPATH' ) || exit;

class ShmppPlugin {

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
		add_action( 'rest_api_init', array( 'ShmppRestAPI', 'register_routes' ) );

		ShmppChannelSync::init();

		if ( is_admin() ) {
			ShmppAdmin::instance();
		}

		ShmppFrontend::instance();

		$db_version = get_option( 'shmpp_db_version' );
		if ( ! $db_version || ShmppDatabase::DB_VERSION !== $db_version ) {
			ShmppDatabase::create_tables();
			ShmppDatabase::seed_defaults();
		}
	}

}
