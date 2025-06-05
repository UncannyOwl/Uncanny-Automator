<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Exception;

/**
 * Exception for configuration errors.
 *
 * Thrown when required settings are missing or invalid.
 * Common causes: missing API keys, invalid endpoints.
 *
 * @since 5.6
 */
class Configuration_Exception extends \RuntimeException {

	/**
	 * Initialize with error message.
	 *
	 * @param string $message Error description
	 */
	public function __construct( string $message = '' ) {
		parent::__construct( 'Configuration exception has occurred ' . esc_html( $message ) );
	}
}
