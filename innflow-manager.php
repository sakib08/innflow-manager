<?php
/**
 * Plugin Name:       InnFlow Manager
 * Plugin URI:        https://pluginpros.co
 * Description:       Hotel operations, reservations, billing, and staff management.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Tested up to:      7.0
 * Author:            sakibbd08
 * Author URI:        https://profiles.wordpress.org/sakibbd08/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       innflow-manager
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'InnflowManagerVERSION' ) ) {
	return;
}

define( 'InnflowManagerVERSION', '1.0.0' );
define( 'InnflowManagerPLUGIN_FILE', __FILE__ );
define( 'InnflowManagerPLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'InnflowManagerPLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'InnflowManagerPLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once InnflowManagerPLUGIN_DIR . 'includes/class-autoloader.php';
InnflowManagerAutoloader::register();

register_activation_hook( __FILE__, array( 'InnflowManagerActivator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'InnflowManagerDeactivator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'InnflowManagerPlugin', 'instance' ) );
