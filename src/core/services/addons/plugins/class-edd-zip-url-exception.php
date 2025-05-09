<?php

namespace Uncanny_Automator\Services\Addons\Plugins;

use Exception;

/**
 * EDD_Zip_URL_Exception
 *
 * @package Uncanny_Automator\Services\Addons\Plugins
 */
class EDD_Zip_URL_Exception extends Exception {

	/**
	 * Constructor for the EDD Zip URL Exception
	 *
	 * @param string $message The exception message
	 * @param int $code The exception code
	 * @param \Throwable|null $previous Previous exception
	 */
	public function __construct( $message = '', $code = 0, Exception $previous = null ) {
		parent::__construct( $message, $code, $previous ); // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
	}
}
