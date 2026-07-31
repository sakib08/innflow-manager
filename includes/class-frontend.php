<?php
defined( 'ABSPATH' ) || exit;

class InnflowManagerFrontend {

	private static $instance = null;

	/** @var bool */
	private $shortcode_present = false;

	/** @var bool */
	private $assets_registered = false;

	/** @var bool */
	private $config_printed = false;

	/** @var string */
	private $shortcode_title = '';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'innflow_manager_search', array( $this, 'render_search' ) );
		// Legacy shortcode alias from Hotel Booking.
		add_shortcode( 'hotel_booking_search', array( $this, 'render_search' ) );
		add_filter( 'the_posts', array( $this, 'detect_shortcode' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 20 );
		add_action( 'wp_footer', array( $this, 'print_boot_config' ), 1 );
	}

	/**
	 * Detect shortcode early so assets can be enqueued in wp_enqueue_scripts.
	 */
	public function detect_shortcode( $posts, $query ) {
		if ( empty( $posts ) || is_admin() || ! $query->is_main_query() ) {
			return $posts;
		}

		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) || empty( $post->post_content ) ) {
				continue;
			}
			if (
				has_shortcode( $post->post_content, 'innflow_manager_search' )
				|| has_shortcode( $post->post_content, 'hotel_booking_search' )
			) {
				$this->shortcode_present = true;
				break;
			}
		}

		return $posts;
	}

	public function register_assets() {
		if ( $this->assets_registered ) {
			return;
		}

		$js_path = InnflowManagerPLUGIN_DIR . 'assets/dist/frontend.js';
		if ( ! file_exists( $js_path ) ) {
			return;
		}

		$asset_file = InnflowManagerPLUGIN_DIR . 'assets/dist/frontend.asset.php';
		$asset      = file_exists( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array(),
				'version'      => InnflowManagerVERSION,
			);

		wp_register_script(
			'ifmpp-frontend',
			InnflowManagerPLUGIN_URL . 'assets/dist/frontend.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_register_style(
			'ifmpp-frontend',
			InnflowManagerPLUGIN_URL . 'assets/dist/frontend.css',
			array(),
			$asset['version']
		);

		$this->assets_registered = true;
	}

	public function maybe_enqueue() {
		if ( $this->shortcode_present ) {
			$this->enqueue_with_config( $this->shortcode_title );
		}
	}

	private function get_boot_config( $title = '' ) {
		$settings = get_option( 'ifmpp_settings', array() );
		return array(
			'apiUrl'   => esc_url_raw( rest_url( 'innflow-manager/v1' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'title'    => $title ? $title : __( 'Find Your Stay', 'innflow-manager' ),
			'settings' => $settings,
		);
	}

	private function enqueue_with_config( $title = '' ) {
		$this->register_assets();

		if ( ! $this->assets_registered ) {
			return;
		}

		wp_enqueue_script( 'ifmpp-frontend' );
		wp_enqueue_style( 'ifmpp-frontend' );

		if ( $this->config_printed ) {
			return;
		}

		$config = $this->get_boot_config( $title );
		$inline = 'window.ifmppFrontend = ' . wp_json_encode( $config ) . ';';
		$ok     = wp_add_inline_script( 'ifmpp-frontend', $inline, 'before' );

		if ( $ok ) {
			$this->config_printed = true;
		}
	}

	/**
	 * Last-resort boot config if wp_add_inline_script could not attach
	 * (e.g. shortcode rendered before scripts were registered on block themes).
	 */
	public function print_boot_config() {
		if ( ! $this->shortcode_present ) {
			return;
		}

		if ( $this->config_printed ) {
			return;
		}

		$config = $this->get_boot_config( $this->shortcode_title );
		echo '<script id="ifmpp-frontend-boot">window.ifmppFrontend = ' . wp_json_encode( $config ) . ';</script>' . "\n";
		$this->config_printed = true;
	}

	public function render_search( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title' => __( 'Find Your Stay', 'innflow-manager' ),
			),
			$atts,
			'innflow_manager_search'
		);

		$this->shortcode_present = true;
		$this->shortcode_title   = $atts['title'];

		// May run before or after wp_enqueue_scripts depending on the theme.
		$this->enqueue_with_config( $atts['title'] );

		$config = $this->get_boot_config( $atts['title'] );

		return sprintf(
			'<div id="ifmpp-frontend-root" class="ifmpp-frontend-app ifmpp-root" data-api-url="%s" data-nonce="%s" data-title="%s" data-settings="%s"></div>',
			esc_url( $config['apiUrl'] ),
			esc_attr( $config['nonce'] ),
			esc_attr( $config['title'] ),
			esc_attr( wp_json_encode( $config['settings'] ) )
		);
	}
}
