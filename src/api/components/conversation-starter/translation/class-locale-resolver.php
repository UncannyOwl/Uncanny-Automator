<?php
/**
 * Resolves WordPress locales to the language code used by translation overlay files.
 *
 * @package Uncanny_Automator\Api\Components\Conversation_Starter\Translation
 * @since 7.3
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Conversation_Starter\Translation;

/**
 * Collapses WordPress' xx_YY[_variant] locale strings to the 2- or 3-letter language code used to select a bundled translation overlay.
 */
class Locale_Resolver {

	/**
	 * Resolve the current user's WordPress locale to a language code.
	 *
	 * Any region or variant suffix is stripped, so `es_AR` and `pt_BR_formal` both collapse to their primary language. Returns an empty string when the locale is missing or malformed; callers treat that as "no overlay, use English source values".
	 *
	 * @return string Two- or three-letter language code, or '' when no overlay should be loaded.
	 */
	public function get_language_code(): string {

		$wp_locale = get_user_locale();

		$language = strtok( $wp_locale, '_' );

		if ( false === $language ) {
			return '';
		}

		$language = strtolower( $language );

		if ( 1 !== preg_match( '/^[a-z]{2,3}$/', $language ) ) {
			return '';
		}

		return $language;
	}
}
