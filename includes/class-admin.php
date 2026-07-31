<?php
defined( 'ABSPATH' ) || exit;

class InnflowManagerAdmin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'InnFlow Manager', 'innflow-manager' ),
			__( 'InnFlow Manager', 'innflow-manager' ),
			'manage_options',
			'innflow-manager',
			array( $this, 'render_app' ),
			'dashicons-building',
			26
		);

		$subpages = array(
			'innflow-manager'            => __( 'Dashboard', 'innflow-manager' ),
			'innflow-manager-rooms'      => __( 'Rooms', 'innflow-manager' ),
			'innflow-manager-bookings'   => __( 'Bookings', 'innflow-manager' ),
			'innflow-manager-guests'     => __( 'Guests', 'innflow-manager' ),
			'innflow-manager-billing'    => __( 'Billing', 'innflow-manager' ),
			'innflow-manager-staff'      => __( 'Staff', 'innflow-manager' ),
			'innflow-manager-restaurants' => __( 'Restaurants', 'innflow-manager' ),
			'innflow-manager-trash'       => __( 'Trash', 'innflow-manager' ),
			'innflow-manager-settings'   => __( 'Settings', 'innflow-manager' ),
		);

		foreach ( $subpages as $slug => $title ) {
			add_submenu_page(
				'innflow-manager',
				$title,
				$title,
				'manage_options',
				$slug,
				array( $this, 'render_app' )
			);
		}
	}

	public function render_app() {
		echo '<div class="wrap"><div id="ifmpp-admin-root" class="ifmpp-admin-app"></div></div>';
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'innflow-manager' ) ) {
			return;
		}

		wp_enqueue_media();

		$asset_file = InnflowManagerPLUGIN_DIR . 'assets/dist/admin.asset.php';
		$asset      = file_exists( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array(),
				'version'      => InnflowManagerVERSION,
			);

		$js  = InnflowManagerPLUGIN_URL . 'assets/dist/admin.js';
		$css = InnflowManagerPLUGIN_URL . 'assets/dist/admin.css';

		if ( file_exists( InnflowManagerPLUGIN_DIR . 'assets/dist/admin.js' ) ) {
			wp_enqueue_script(
				'ifmpp-admin',
				$js,
				$asset['dependencies'],
				$asset['version'],
				true
			);
		}

		if ( file_exists( InnflowManagerPLUGIN_DIR . 'assets/dist/admin.css' ) ) {
			wp_enqueue_style( 'ifmpp-admin', $css, array(), $asset['version'] );
		}

		$settings = get_option( 'ifmpp_settings', array() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page slug for localized script only; capability checked by menu callback.
		$page     = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'innflow-manager';

		wp_localize_script(
			'ifmpp-admin',
			'ifmppAdmin',
			array(
				'apiUrl'   => esc_url_raw( rest_url( 'innflow-manager/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'page'     => $page,
				'settings' => $settings,
				'pluginUrl'=> InnflowManagerPLUGIN_URL,
				'adminUrl' => admin_url( 'admin.php' ),
			)
		);
	}
}
