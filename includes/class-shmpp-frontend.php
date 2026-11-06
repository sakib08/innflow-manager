<?php
defined( 'ABSPATH' ) || exit;

class ShmppFrontend {

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
		add_shortcode( 'shmpp_search', array( $this, 'render_search' ) );
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
			if ( has_shortcode( $post->post_content, 'shmpp_search' ) ) {
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

		$js_path = SHMPP_PLUGIN_DIR . 'assets/dist/frontend.js';
		if ( ! file_exists( $js_path ) ) {
			return;
		}

		$asset_file = SHMPP_PLUGIN_DIR . 'assets/dist/frontend.asset.php';
		$asset      = file_exists( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array(),
				'version'      => SHMPP_VERSION,
			);

		wp_register_script(
			'shmpp-frontend',
			SHMPP_PLUGIN_URL . 'assets/dist/frontend.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_register_style(
			'shmpp-frontend',
			SHMPP_PLUGIN_URL . 'assets/dist/frontend.css',
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
		$settings = get_option( 'shmpp_settings', array() );
		// Never expose Stripe secrets to the public frontend.
		$public_settings = $settings;
		unset( $public_settings['stripe_secret_key'], $public_settings['stripe_webhook_secret'] );

		$manual_title = ! empty( $settings['manual_payment_title'] )
			? $settings['manual_payment_title']
			: __( 'Bank transfer / Manual payment', 'staynexus-hotel-manager' );

		$default_language = isset( $settings['frontend_language'] ) ? sanitize_key( $settings['frontend_language'] ) : 'en';
		$allowed_langs    = array( 'en', 'es', 'fr', 'de', 'bn', 'ar' );
		if ( ! in_array( $default_language, $allowed_langs, true ) ) {
			$default_language = 'en';
		}

		return array(
			'apiUrl'          => esc_url_raw( rest_url( 'staynexushm/v1' ) ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'title'           => $title ? $title : __( 'Find Your Stay', 'staynexus-hotel-manager' ),
			'defaultLanguage' => $default_language,
			'settings'        => $public_settings,
			'stripe'          => ShmppStripe::get_public_config(),
			'manualPayment'   => array(
				'enabled'      => ! empty( $settings['manual_payment_enabled'] ),
				'title'        => $manual_title,
				'instructions' => isset( $settings['manual_payment_instructions'] ) ? (string) $settings['manual_payment_instructions'] : '',
			),
		);
	}

	private function enqueue_with_config( $title = '' ) {
		$this->register_assets();

		if ( ! $this->assets_registered ) {
			return;
		}

		wp_enqueue_script( 'shmpp-frontend' );
		wp_enqueue_style( 'shmpp-frontend' );
		$this->enqueue_frontend_theme_css();

		if ( $this->config_printed ) {
			return;
		}

		$config = $this->get_boot_config( $title );
		$inline = 'window.shmppFrontend = ' . wp_json_encode( $config ) . ';';
		$ok     = wp_add_inline_script( 'shmpp-frontend', $inline, 'before' );

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
		echo '<script id="shmpp-frontend-boot">window.shmppFrontend = ' . wp_json_encode( $config ) . ';</script>' . "\n";
		$this->config_printed = true;
	}

	public function render_search( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title' => __( 'Find Your Stay', 'staynexus-hotel-manager' ),
			),
			$atts,
			'shmpp_search'
		);

		$this->shortcode_present = true;
		$this->shortcode_title   = $atts['title'];

		// May run before or after wp_enqueue_scripts depending on the theme.
		$this->enqueue_with_config( $atts['title'] );

		$config = $this->get_boot_config( $atts['title'] );
		$theme_style = $this->frontend_theme_style_attr( $config['settings'] );

		return sprintf(
			'<div id="shmpp-frontend-root" class="shmpp-frontend-app shmpp-root"%s data-api-url="%s" data-nonce="%s" data-title="%s" data-default-language="%s" data-settings="%s" data-stripe="%s" data-manual-payment="%s"></div>',
			$theme_style ? ' style="' . esc_attr( $theme_style ) . '"' : '',
			esc_url( $config['apiUrl'] ),
			esc_attr( $config['nonce'] ),
			esc_attr( $config['title'] ),
			esc_attr( $config['defaultLanguage'] ),
			esc_attr( wp_json_encode( $config['settings'] ) ),
			esc_attr( wp_json_encode( $config['stripe'] ) ),
			esc_attr( wp_json_encode( $config['manualPayment'] ) )
		);
	}

	/**
	 * Inline CSS variables for a custom frontend primary color.
	 *
	 * @param array $settings Public settings.
	 * @return string Style attribute value (no style="" wrapper), or empty.
	 */
	private function frontend_theme_style_attr( $settings ) {
		$hex = isset( $settings['frontend_primary_color'] ) ? ShmppColors::sanitize_hex( $settings['frontend_primary_color'] ) : '';
		if ( ! $hex || strtolower( $hex ) === strtolower( ShmppColors::DEFAULT_PRIMARY ) ) {
			return '';
		}
		return ShmppColors::brand_css_variables( $hex );
	}

	/**
	 * Also print theme CSS via inline style sheet for specificity / FOUC reduction.
	 */
	private function enqueue_frontend_theme_css() {
		$settings = get_option( 'shmpp_settings', array() );
		$hex      = isset( $settings['frontend_primary_color'] ) ? ShmppColors::sanitize_hex( $settings['frontend_primary_color'] ) : '';
		if ( ! $hex || strtolower( $hex ) === strtolower( ShmppColors::DEFAULT_PRIMARY ) ) {
			return;
		}
		$vars = ShmppColors::brand_css_variables( $hex );
		$css  = '#shmpp-frontend-root{' . $vars . '}';
		wp_add_inline_style( 'shmpp-frontend', $css );
	}
}
