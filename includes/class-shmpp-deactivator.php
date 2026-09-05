<?php
defined( 'ABSPATH' ) || exit;

class ShmppDeactivator {

	public static function deactivate() {
		ShmppChannelSync::clear_cron();
		flush_rewrite_rules();
	}
}
