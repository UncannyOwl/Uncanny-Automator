<?php

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Page_Builder;

/**
 * Stores the Automator-owned Page Builder availability setting.
 *
 * @since 7.5
 */
final class Page_Builder_Settings {

	public const OPTION_NAME = 'automator_uncanny_page_builder_settings';
	public const ENABLED_KEY = 'enabled';

	/**
	 * Return the default settings.
	 *
	 * @return array{enabled: bool}
	 */
	public static function defaults(): array {
		return array(
			self::ENABLED_KEY => true,
		);
	}

	/**
	 * Check if new Page Builder pages are available.
	 *
	 * Missing and invalid values use the enabled default.
	 *
	 * @param bool $force Get the value from the database.
	 *
	 * @return bool
	 */
	public function is_enabled( bool $force = false ): bool {
		$settings = automator_get_option( self::OPTION_NAME, self::defaults(), $force );

		if ( ! is_array( $settings ) || ! array_key_exists( self::ENABLED_KEY, $settings ) ) {
			return true;
		}

		$value = $settings[ self::ENABLED_KEY ];

		if ( null === $value || ( is_string( $value ) && '' === trim( $value ) ) ) {
			return true;
		}

		$enabled = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		return null === $enabled ? true : $enabled;
	}

	/**
	 * Update the Page Builder availability setting.
	 *
	 * @param bool $enabled Availability for new Page Builder pages.
	 *
	 * @return bool
	 */
	public function update_enabled( bool $enabled ): bool {
		$settings = automator_get_option( self::OPTION_NAME, self::defaults() );

		if ( ! is_array( $settings ) ) {
			$settings = self::defaults();
		}

		$settings[ self::ENABLED_KEY ] = $enabled;

		return automator_update_option( self::OPTION_NAME, $settings );
	}
}
