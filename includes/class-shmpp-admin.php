<?php
defined( 'ABSPATH' ) || exit;

class ShmppAdmin {

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
			__( 'StayNexus Hotel Manager', 'staynexus-hotel-manager' ),
			__( 'StayNexus Hotel Manager', 'staynexus-hotel-manager' ),
			'manage_options',
			'staynexus-hotel-manager',
			array( $this, 'render_app' ),
			'dashicons-building',
			26
		);

		$subpages = array(
			'staynexus-hotel-manager'            => __( 'Dashboard', 'staynexus-hotel-manager' ),
			'staynexus-hotel-manager-rooms'      => __( 'Rooms', 'staynexus-hotel-manager' ),
			'staynexus-hotel-manager-bookings'   => __( 'Bookings', 'staynexus-hotel-manager' ),
			'staynexus-hotel-manager-guests'     => __( 'Guests', 'staynexus-hotel-manager' ),
			'staynexus-hotel-manager-billing'    => __( 'Billing', 'staynexus-hotel-manager' ),
			'staynexus-hotel-manager-staff'      => __( 'Staff', 'staynexus-hotel-manager' ),
			'staynexus-hotel-manager-restaurants' => __( 'Restaurants', 'staynexus-hotel-manager' ),
			'staynexus-hotel-manager-channels'   => __( 'Channels', 'staynexus-hotel-manager' ),
			'staynexus-hotel-manager-channel-help' => __( 'Channex help', 'staynexus-hotel-manager' ),
			'staynexus-hotel-manager-trash'       => __( 'Trash', 'staynexus-hotel-manager' ),
			'staynexus-hotel-manager-payments'   => __( 'Payment settings', 'staynexus-hotel-manager' ),
			'staynexus-hotel-manager-settings'   => __( 'Settings', 'staynexus-hotel-manager' ),
		);

		foreach ( $subpages as $slug => $title ) {
			add_submenu_page(
				'staynexus-hotel-manager',
				$title,
				$title,
				'manage_options',
				$slug,
				array( $this, 'render_app' )
			);
		}
	}

	public function render_app() {
		echo '<div class="wrap"><div id="shmpp-admin-root" class="shmpp-admin-app shmpp-root"></div></div>';
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'staynexus-hotel-manager' ) ) {
			return;
		}

		wp_enqueue_media();

		$asset_file = SHMPP_PLUGIN_DIR . 'assets/dist/admin.asset.php';
		$asset      = file_exists( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array(),
				'version'      => SHMPP_VERSION,
			);

		$js  = SHMPP_PLUGIN_URL . 'assets/dist/admin.js';
		$css = SHMPP_PLUGIN_URL . 'assets/dist/admin.css';

		if ( file_exists( SHMPP_PLUGIN_DIR . 'assets/dist/admin.js' ) ) {
			wp_enqueue_script(
				'shmpp-admin',
				$js,
				$asset['dependencies'],
				$asset['version'],
				true
			);
		}

		if ( file_exists( SHMPP_PLUGIN_DIR . 'assets/dist/admin.css' ) ) {
			wp_enqueue_style( 'shmpp-admin', $css, array(), $asset['version'] );
		}

		$settings = get_option( 'shmpp_settings', array() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page slug for localized script only; capability checked by menu callback.
		$page     = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'staynexus-hotel-manager';

		wp_localize_script(
			'shmpp-admin',
			'shmppAdmin',
			array(
				'apiUrl'   => esc_url_raw( rest_url( 'staynexushm/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'page'     => $page,
				'settings' => $settings,
				'pluginUrl'=> SHMPP_PLUGIN_URL,
				'adminUrl' => admin_url( 'admin.php' ),
			)
		);
	}
}
