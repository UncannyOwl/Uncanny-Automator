<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Exception;

/**
 * Exception for AI service errors.
 *
 * Thrown when HTTP requests fail or API returns errors.
 * Includes network issues, authentication failures, rate limits.
 *
 * @since 5.6
 */
class AI_Service_Exception extends \RuntimeException {

	/**
	 * Initialize with error message.
	 *
	 * @param string $message Error description
	 */
	public function __construct( string $message = '' ) {
		parent::__construct( 'AI service exception has occurred: ' . esc_html( $message ) );
	}
}
