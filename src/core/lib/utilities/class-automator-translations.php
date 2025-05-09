<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Translations
 *
 * @package Uncanny_Automator
 */
class Automator_Translations {

	/**
	 * @var
	 */
	public static $instance;
	/**
	 * Collection of error messages
	 *
	 * @var array
	 */
	private $ls = array();

	/**
	 *
	 */
	public function __construct() {
	}

	/**
	 *
	 */
	private function set_strings() {

		// if it is already initilized?
		if ( ! empty( $this->ls ) ) {
			return;
		}

		// Localized strings
		$this->ls = array();

		do_action_deprecated( 'uap_localized_string_after', array(), '3.0', 'automator_localized_string_after' );
		do_action( 'automator_localized_string_after' );
	}

	/**
	 * @return Automator_Translations
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get the strings associated with the string key
	 *
	 * @param null|string $string_key
	 *
	 * @return null|string
	 */
	public function get( $string_key = null ) {

		if ( isset( $error_messages[ $string_key ] ) ) {
			$localized_string = $this->ls[ $string_key ];
		} else {
			return null;
		}

		/**
		 * Filters the specific string
		 */
		$localized_string = apply_filters_deprecated(
			'uap_localized_string',
			array(
				$localized_string,
				$string_key,
			),
			'3.0',
			'automator_localized_string'
		);

		return apply_filters( 'automator_localized_string', $localized_string, $string_key );
	}

	/**
	 * Get get all translated strings
	 *
	 * @return array
	 */
	public function get_all() {
		$this->set_strings();
		$this->ls          = apply_filters_deprecated( 'uap_localized_strings', array( $this->ls ), '3.0', 'automator_localized_strings' );
		$localized_strings = apply_filters( 'automator_localized_strings', $this->ls );

		return $localized_strings;
	}
}
