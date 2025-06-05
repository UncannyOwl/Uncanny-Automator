<?php
namespace Uncanny_Automator\Core\Lib\AI\Exception;

/**
 * Exception for response parsing errors.
 *
 * Thrown when AI response format is unexpected or malformed.
 * Common causes: invalid JSON, missing fields, API changes.
 *
 * @since 5.6
 */
class Response_Exception extends \Exception {

	/**
	 * Initialize with error message.
	 *
	 * @param string $message Error description
	 */
	public function __construct( string $message = '' ) {
		parent::__construct( 'Response exception has occurred: ' . esc_html( $message ) );
	}
}
