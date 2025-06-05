<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Core\Interfaces;

/**
 * Configuration interface for AI providers.
 *
 * Provides access to configuration values like API keys and settings.
 * WordPress implementation uses automator_get_option().
 *
 * @package Uncanny_Automator\Core\Lib\AI\Core\Interfaces
 * @since 5.6
 */
interface Config_Interface {

	/**
	 * Get configuration value by key.
	 *
	 * @param string $key           Configuration key
	 * @param string $default_value Default value if not set
	 *
	 * @return string Configuration value
	 */
	public function get( string $key, string $default_value = '' ): string;
}
