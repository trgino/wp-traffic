<?php
/**
 * Conversion handling
 *
 * Handles all conversion operations.
 *
 * @package System
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace Traffic\System;

/**
 * Define the conversion functionality.
 *
 * Handles all conversion operations.
 *
 * @package System
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Conversion {

	/**
	 * Initializes the class and set its properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Get a shortened number.
	 *
	 * @param   float   $number    The number to shorten.
	 * @param   integer $precision Optional. The decimal numbers.
	 * @return  string  The shortened number.
	 * @since   1.0.0
	 */
	public static function number_shorten( $number, $precision = 2 ) {
		$divisors = [
			pow( 1000, 0 ) => '',
			pow( 1000, 1 ) => esc_html_x( 'K', 'Abbreviation - Stands for "thousand".', 'traffic' ),
			pow( 1000, 2 ) => esc_html_x( 'M', 'Abbreviation - Stands for "million".', 'traffic' ),
			pow( 1000, 3 ) => esc_html_x( 'B', 'Abbreviation - Stands for "billion".', 'traffic' ),
			pow( 1000, 4 ) => esc_html_x( 'T', 'Abbreviation - Stands for "trillion".', 'traffic' ),
			pow( 1000, 5 ) => esc_html_x( 'Qa', 'Abbreviation - Stands for "quadrillion".', 'traffic' ),
			pow( 1000, 6 ) => esc_html_x( 'Qi', 'Abbreviation - Stands for "quintillion".', 'traffic' ),
		];
		foreach ( $divisors as $divisor => $shorthand ) {
			if ( abs( $number ) < ( $divisor * 1000 ) ) {
				break;
			}
		}
		return 0 + number_format( $number / $divisor, $precision ) . $shorthand;
	}

	/**
	 * Get a shortened number.
	 *
	 * @param   float   $number    The number to shorten.
	 * @param   integer $precision Optional. The decimal numbers.
	 * @return  string  The shortened number.
	 * @since   1.0.0
	 */
	public static function data_shorten( $number, $precision = 2 ) {
		$divisors = [
			pow( 1024, 0 ) => '',
			pow( 1024, 1 ) => esc_html_x( 'k', 'Abbreviation - Stands for "kilo".', 'traffic' ),
			pow( 1024, 2 ) => esc_html_x( 'M', 'Abbreviation - Stands for "mega".', 'traffic' ),
			pow( 1024, 3 ) => esc_html_x( 'G', 'Abbreviation - Stands for "giga".', 'traffic' ),
			pow( 1024, 4 ) => esc_html_x( 'T', 'Abbreviation - Stands for "tera".', 'traffic' ),
			pow( 1024, 5 ) => esc_html_x( 'Qa', 'Abbreviation - Stands for "quadrillion".', 'traffic' ),
			pow( 1024, 6 ) => esc_html_x( 'Qi', 'Abbreviation - Stands for "quintillion".', 'traffic' ),
		];
		foreach ( $divisors as $divisor => $shorthand ) {
			if ( abs( $number ) < ( $divisor * 1024 ) ) {
				break;
			}
		}
		return 0 + number_format( $number / $divisor, $precision ) . $shorthand;
	}

}