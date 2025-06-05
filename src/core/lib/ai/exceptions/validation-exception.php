<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Exception;

/**
 * Exception for input validation errors.
 *
 * Thrown when request parameters are invalid or missing.
 * Common causes: empty prompts, invalid models, bad parameters.
 *
 * @since 5.6
 */
class Validation_Exception extends \Exception {

	/**
	 * Initialize with error message.
	 *
	 * @param string $message Error description
	 */
	public function __construct( string $message = '' ) {
		parent::__construct( 'Validation exception has occurred: ' . esc_html( $message ) );
	}
}
