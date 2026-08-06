<?php
defined( 'ABSPATH' ) || exit;

class ShmppActivator {

	public static function activate() {
		ShmppDatabase::create_tables();
		ShmppDatabase::seed_defaults();
		flush_rewrite_rules();
	}
}
