<?php
/**
 * Plugin Name:       StayNexus Hotel Manager
 * Plugin URI:        https://pluginpros.co
 * Description:       Hotel operations, reservations, billing, and staff management.
 * Version:           1.0.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Tested up to:      7.0
 * Author:            sakibbd08
 * Author URI:        https://profiles.wordpress.org/sakibbd08/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       staynexus-hotel-manager
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'SHMPP_VERSION' ) ) {
	return;
}

define( 'SHMPP_VERSION', '1.0.3' );
define( 'SHMPP_PLUGIN_FILE', __FILE__ );
define( 'SHMPP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SHMPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SHMPP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once SHMPP_PLUGIN_DIR . 'includes/class-shmpp-autoloader.php';
ShmppAutoloader::register();

register_activation_hook( __FILE__, array( 'ShmppActivator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ShmppDeactivator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'ShmppPlugin', 'instance' ) );
