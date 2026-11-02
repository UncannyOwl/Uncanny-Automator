<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Suggestions;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;

/**
 * Curated cold-start defaults per kind+scope. Seeds results when there is no
 * popularity signal and carries curated boosts (e.g. TOKEN_MEETS_CONDITION).
 * Each entry: array{ integration_code: string, code: string }.
 */
class Suggestion_Curated_Defaults {

	/**
	 * Return the curated default entries for a kind+scope (+ recipe type, + loop type).
	 *
	 * @param string      $kind        `'item'` or `'block_modifier'`.
	 * @param string      $scope       Item type or block-modifier type literal.
	 * @param string      $recipe_type `'user'` | `'anonymous'`.
	 * @param string|null $loop_type   Parent loop's iteration type when the scope is
	 *                                 loop_filter; null at any other scope, or when the
	 *                                 loop has not been configured yet.
	 * @return array<int, array<string, string>>
	 */
	public static function get_for( string $kind, string $scope, string $recipe_type, ?string $loop_type = null ): array {
		if ( 'item' === $kind ) {
			return self::item_defaults( $scope, $recipe_type );
		}
		if ( 'block_modifier' === $kind ) {
			return self::block_modifier_defaults( $scope, $loop_type );
		}
		return array();
	}

	/**
	 * Curated defaults for item scopes (triggers vary by recipe type).
	 *
	 * @param string $scope       Integration_Item_Types literal.
	 * @param string $recipe_type `'user'` | `'anonymous'`.
	 * @return array<int, array<string, string>>
	 */
	private static function item_defaults( string $scope, string $recipe_type ): array {
		switch ( $scope ) {
			case Integration_Item_Types::TRIGGER:
				return self::trigger_defaults( $recipe_type );
			case Integration_Item_Types::ACTION:
				return array(
					array(
						'integration_code' => 'EMAILS',
						'code'             => 'SENDEMAIL',
					),
					array(
						'integration_code' => 'WP',
						'code'             => 'CREATEPOST',
					),
					array(
						'integration_code' => 'WEBHOOKS',
						'code'             => 'WPSENDWEBHOOK',
					),
					array(
						'integration_code' => 'OPEN_AI',
						'code'             => 'OPEN_AI_TEXT_GENERATE',
					),
					array(
						'integration_code' => 'SLACK',
						'code'             => 'SLACKSENDMESSAGE',
					),
				);
			default:
				return array();
		}
	}

	/**
	 * Curated defaults for block-modifier scopes.
	 *
	 * Loop-filter defaults are per iteration type: a token filter is unusable on
	 * a Users loop, so a single blended list is always wrong for two of the three
	 * loop types. Entries name built-in integrations only — a third-party code
	 * would be removed by the availability filter on any site without that
	 * plugin, re-emptying the very list these defaults exist to fill.
	 *
	 * @param string      $scope     Block_Modifier_Types literal.
	 * @param string|null $loop_type Parent loop's iteration type, or null when unknown.
	 * @return array<int, array<string, string>>
	 */
	private static function block_modifier_defaults( string $scope, ?string $loop_type ): array {
		switch ( $scope ) {
			case Block_Modifier_Types::LOOP_FILTER:
				return self::loop_filter_defaults( $loop_type );
			case Block_Modifier_Types::FILTER_CONDITION:
				return array(
					array(
						'integration_code' => 'GEN',
						'code'             => 'TOKEN_MEETS_CONDITION',
					),
					array(
						'integration_code' => 'WP',
						'code'             => 'USER_EMAIL_CONTAINS_DOMAIN',
					),
					array(
						'integration_code' => 'WP',
						'code'             => 'USER_HAS_ROLE',
					),
				);
			default:
				return array();
		}
	}

	/**
	 * Curated loop-filter defaults, split by the loop's iteration type.
	 *
	 * An unconfigured loop (null) yields nothing: no iteration type means every
	 * registered filter's ancestor rule fails anyway, so offering a list the
	 * gate would empty is worse than offering none.
	 *
	 * @param string|null $loop_type `'users'` | `'posts'` | `'token'`, or null.
	 * @return array<int, array<string, string>>
	 */
	private static function loop_filter_defaults( ?string $loop_type ): array {
		if ( 'token' === $loop_type ) {
			return array(
				array(
					'integration_code' => 'GEN',
					'code'             => 'ITEM_NOT_EMPTY',
				),
				array(
					'integration_code' => 'GEN',
					'code'             => 'TOKEN_MEETS_CONDITION',
				),
			);
		}

		$by_type = array(
			'users' => array(
				'WP_USER_HAS_ROLE',
				'WP_USER_FIELD_MEETS_CONDITION',
				'WP_USER_META_MEETS_CONDITION',
				'WP_USER_EMAIL_ENDS_WITH',
				'WP_USER_REGISTRATION_DATE_MEETS_CONDITION',
			),
			'posts' => array(
				'WP_POST_HAS_STATUS',
				'WP_POST_EQUALS_POST_TYPE',
				'WP_POST_HAS_OR_HAS_NOT_TERM_TAXONOMY',
				'WP_POST_AUTHOR_IS',
				'WP_POST_META_MEETS_CONDITION',
			),
		);

		$codes = $by_type[ (string) $loop_type ] ?? array();

		return array_map(
			function ( $code ) {
				return array(
					'integration_code' => 'WP',
					'code'             => $code,
				);
			},
			$codes
		);
	}

	/**
	 * Curated trigger defaults, split by user vs anonymous recipe type.
	 *
	 * @param string $recipe_type `'user'` | `'anonymous'`.
	 * @return array<int, array<string, string>>
	 */
	private static function trigger_defaults( string $recipe_type ): array {
		if ( 'user' === $recipe_type ) {
			return array(
				array(
					'integration_code' => 'WP',
					'code'             => 'VIEWPAGE',
				),
				array(
					'integration_code' => 'WP',
					'code'             => 'USERROLEADDED',
				),
				array(
					'integration_code' => 'WP',
					'code'             => 'WP_USER_POST_PUBLISHED',
				),
			);
		}
		return array(
			array(
				'integration_code' => 'WP',
				'code'             => 'WP_POST_PUBLISHED',
			),
			array(
				'integration_code' => 'URL',
				'code'             => 'URL_HAS_PARAM',
			),
		);
	}
}
