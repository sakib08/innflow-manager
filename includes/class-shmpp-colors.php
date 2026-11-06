<?php
defined( 'ABSPATH' ) || exit;

/**
 * Frontend brand palette helpers from a single primary hex color.
 */
class ShmppColors {

	const DEFAULT_PRIMARY = '#27584c';

	/**
	 * Sanitize a #RRGGBB color string.
	 *
	 * @param mixed $value Raw value.
	 * @return string Normalized #rrggbb or empty string if invalid.
	 */
	public static function sanitize_hex( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( preg_match( '/^#([A-Fa-f0-9]{3})$/', $value, $m ) ) {
			$h = $m[1];
			$value = '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		}
		if ( ! preg_match( '/^#([A-Fa-f0-9]{6})$/', $value ) ) {
			return '';
		}
		return '#' . strtolower( substr( $value, 1 ) );
	}

	/**
	 * Build a 50–950 brand palette. The chosen color maps to brand-700 (buttons / accents).
	 *
	 * @param string $primary Hex primary color.
	 * @return array<int|string,string> Shade => hex.
	 */
	public static function brand_palette( $primary ) {
		$primary = self::sanitize_hex( $primary );
		if ( ! $primary ) {
			$primary = self::DEFAULT_PRIMARY;
		}

		return array(
			50  => self::mix( $primary, '#ffffff', 0.92 ),
			100 => self::mix( $primary, '#ffffff', 0.84 ),
			200 => self::mix( $primary, '#ffffff', 0.70 ),
			300 => self::mix( $primary, '#ffffff', 0.52 ),
			400 => self::mix( $primary, '#ffffff', 0.32 ),
			500 => self::mix( $primary, '#ffffff', 0.14 ),
			600 => self::mix( $primary, '#000000', 0.12 ),
			700 => $primary,
			800 => self::mix( $primary, '#000000', 0.22 ),
			900 => self::mix( $primary, '#000000', 0.34 ),
			950 => self::mix( $primary, '#000000', 0.55 ),
		);
	}

	/**
	 * CSS custom-property declarations for a palette.
	 *
	 * @param string $primary Hex primary.
	 * @return string
	 */
	public static function brand_css_variables( $primary ) {
		$palette = self::brand_palette( $primary );
		$parts   = array();
		foreach ( $palette as $shade => $hex ) {
			$parts[] = '--shmpp-brand-' . $shade . ':' . $hex;
		}
		return implode( ';', $parts ) . ';';
	}

	/**
	 * Mix two hex colors. $amount is weight of $with (0–1).
	 *
	 * @param string $hex  Base.
	 * @param string $with Mix target.
	 * @param float  $amount 0–1.
	 * @return string
	 */
	private static function mix( $hex, $with, $amount ) {
		$a = self::hex_to_rgb( $hex );
		$b = self::hex_to_rgb( $with );
		$amount = max( 0, min( 1, (float) $amount ) );
		$r = (int) round( $a[0] * ( 1 - $amount ) + $b[0] * $amount );
		$g = (int) round( $a[1] * ( 1 - $amount ) + $b[1] * $amount );
		$bl = (int) round( $a[2] * ( 1 - $amount ) + $b[2] * $amount );
		return sprintf( '#%02x%02x%02x', $r, $g, $bl );
	}

	/**
	 * @param string $hex Hex color.
	 * @return array{0:int,1:int,2:int}
	 */
	private static function hex_to_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}
}
