<?php

namespace Uncanny_Automator;

/**
 * Class Uoa_Tokens
 * @package Uncanny_Automator
 */
class Uoa_Tokens {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'UOA';

	/**
	 * Wp_Tokens constructor.
	 */
	public function __construct() {

	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $code
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $code ) {

		if ( self::$integration === $code ) {

			$status = true;
		}

		return $status;
	}
}
