<?php
defined( 'ABSPATH' ) || exit;

class InnflowManagerActivator {

	public static function activate() {
		InnflowManagerDatabase::create_tables();
		InnflowManagerDatabase::seed_defaults();
		flush_rewrite_rules();
	}
}
