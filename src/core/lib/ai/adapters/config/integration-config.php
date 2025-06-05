<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Adapters\Config;

use Uncanny_Automator\Core\Lib\AI\Exception\Configuration_Exception;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Config_Interface;

/**
 * WordPress configuration adapter.
 *
 * Provides AI providers access to WordPress options and settings.
 * Uses automator_get_option() for data retrieval.
 *
 * @package Uncanny_Automator\Core\Lib\AI\Adapters\Config
 * @since 5.6
 */
final class Integration_Config implements Config_Interface {

	/**
	 * Get configuration value from WordPress options.
	 *
	 * @param string $key           Option key (e.g., automator_openai_api_key)
	 * @param string $default_value Default value if not found
	 *
	 * @return string Configuration value
	 */
	public function get( string $key, string $default_value = '' ): string {
		$value = automator_get_option( $key, $default_value );
		return is_string( $value ) ? $value : $default_value;
	}

	/**
	 * Validate required configuration keys exist.
	 *
	 * @param string[] $keys Required configuration keys
	 *
	 * @return void
	 *
	 * @throws Configuration_Exception If any key is missing
	 */
	public function validate_keys( array $keys ): void {
		foreach ( $keys as $k ) {
			if ( $this->get( $k, '' ) === '' ) {
				throw new Configuration_Exception( esc_html( "Missing required config key: {$k}" ) );
			}
		}
	}
}
