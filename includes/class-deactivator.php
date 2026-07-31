<?php
defined( 'ABSPATH' ) || exit;

class InnflowManagerDeactivator {

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
