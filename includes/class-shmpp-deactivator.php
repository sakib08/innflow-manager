<?php
defined( 'ABSPATH' ) || exit;

class ShmppDeactivator {

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
