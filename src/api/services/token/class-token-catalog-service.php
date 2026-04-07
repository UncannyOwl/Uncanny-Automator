<?php
/**
 * Token Catalog Service.
 *
 * Assembles the full set of tokens available in a recipe context:
 * advanced (universal), common, date/time, trigger, action, and loop tokens.
 *
 * Extracted from Get_Tokens_Tool to keep the MCP tool layer thin.
 *
 * @package Uncanny_Automator\Api\Services\Token
 * @since   7.1.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Token;

use Uncanny_Automator\Api\Components\Token\Domain\Token_Data_Types;
use Uncanny_Automator\Api\Components\Token\Integration\Registry\WP_Integration_Token_Registry;
use WP_Error;

/**
 * Token_Catalog_Service
 *
 * @since 7.1.0
 */
class Token_Catalog_Service {

	/**
	 * Common token registry.
	 *
	 * @var Common_Token_Registry
	 */
	private $common_registry;

	/**
	 * Constructor.
	 *
	 * @since 7.1.0
	 *
	 * @param Common_Token_Registry|null $common_registry Optional registry instance.
	 */
	public function __construct( Common_Token_Registry $common_registry = null ) {
		$this->common_registry = $common_registry ? $common_registry : new Common_Token_Registry();
	}

	/**
	 * Get all tokens for a recipe, optionally scoped to a loop.
	 *
	 * @since 7.1.0
	 *
	 * @param int      $recipe_id Recipe post ID.
	 * @param int|null $loop_id   Optional loop ID to include loop iteration tokens.
	 *
	 * @return array|WP_Error Token categories array on success, WP_Error on failure.
	 */
	public function get_tokens_for_recipe( int $recipe_id, int $loop_id = null ) {

		$token_categories = array(
			'advanced'       => array(
				'description' => 'Universal Tokens for accessing meta fields, calculations, and integration-specific data.',
				'tokens'      => $this->get_advanced_tokens(),
			),
			'common'         => array(
				'description' => 'General tokens always available. Tokens with requiresUser=true can be used in user_selector.unique_field_value — this is NOT circular, it is the correct way to identify users in anonymous recipes.',
				'tokens'      => $this->common_registry->get_common_tokens(),
			),
			'date-and-time'  => array(
				'description' => 'Current date, time, and timestamp tokens',
				'tokens'      => $this->common_registry->get_date_time_tokens(),
			),
			'trigger-tokens' => array(
				'description' => 'Tokens from recipe triggers (only available after adding triggers)',
			),
			'action-tokens'  => array(
				'description' => 'Tokens from recipe actions (only available after adding actions)',
			),
		);

		try {
			$recipe_object = Automator()->get_recipe_object( $recipe_id, ARRAY_A );

			if ( ! empty( $recipe_object ) ) {
				$token_categories = $this->add_recipe_tokens( $token_categories, $recipe_object );

				if ( null !== $loop_id && $loop_id > 0 ) {
					$loop_tokens = $this->get_loop_tokens( $recipe_object, $loop_id );
					if ( is_wp_error( $loop_tokens ) ) {
						return $loop_tokens;
					}
					$token_categories['loop-tokens'] = $loop_tokens;
				}
			}
		} catch ( \Exception $e ) {
			return new WP_Error( 'token_catalog_error', $e->getMessage() );
		}

		return $token_categories;
	}

	/**
	 * Get advanced/universal tokens from the integration token registry.
	 *
	 * @since 7.1.0
	 *
	 * @return array Universal token definitions.
	 */
	private function get_advanced_tokens(): array {

		$tokens = array();

		try {
			$registry   = new WP_Integration_Token_Registry();
			$all_tokens = $registry->get_available_tokens();

			foreach ( $all_tokens as $token_id => $token ) {
				if ( strpos( $token_id, 'UT:' ) !== 0 ) {
					continue;
				}

				$has_template = ! empty( $token['idTemplate'] );
				$base_id      = $token['id'] ?? '';
				$usage        = $has_template ? '{{' . $base_id . ':' . $token['idTemplate'] . '}}' : '{{' . $base_id . '}}';

				$entry = array(
					'name'         => $token['name'] ?? '',
					'usage'        => $usage,
					'requiresUser' => $token['requiresUser'] ?? false,
				);

				if ( $has_template ) {
					$entry['idTemplate']   = $token['idTemplate'];
					$entry['nameTemplate'] = $token['nameTemplate'] ?? '';
				}

				$tokens[] = $entry;
			}
		} catch ( \Exception $e ) {
			return array();
		}

		return $tokens;
	}

	/**
	 * Add trigger and action tokens from the recipe object.
	 *
	 * @since 7.1.0
	 *
	 * @param array $categories    Token categories.
	 * @param array $recipe_object Recipe object data.
	 *
	 * @return array Updated categories.
	 */
	private function add_recipe_tokens( array $categories, array $recipe_object ): array {

		// Trigger tokens.
		$triggers       = $recipe_object['triggers']['items'] ?? array();
		$trigger_tokens = array( 'description' => 'Tokens from recipe triggers' );

		foreach ( $triggers as $trigger ) {
			$code   = $trigger['code'] ?? '';
			$tokens = $trigger['tokens'] ?? array();
			if ( empty( $code ) || empty( $tokens ) ) {
				continue;
			}
			$trigger_tokens[ $code ] = array();
			foreach ( $tokens as $token ) {
				$trigger_tokens[ $code ][] = array(
					'name'        => $token['name'] ?? '',
					'usage'       => '{{' . ( $token['id'] ?? '' ) . '}}',
					'type'        => $this->normalize_token_type( $token['data_type'] ?? $token['type'] ?? '' ),
					'description' => sprintf( 'Trigger token from trigger ID %d.', $trigger['id'] ?? 0 ),
				);
			}
		}
		$categories['trigger-tokens'] = $trigger_tokens;

		// Action tokens.
		$actions       = $recipe_object['actions']['items'] ?? array();
		$action_tokens = array( 'description' => 'Tokens from recipe actions' );

		foreach ( $actions as $action ) {
			if ( ( $action['type'] ?? '' ) === 'loop' ) {
				continue;
			}
			$code   = $action['code'] ?? '';
			$tokens = $action['tokens'] ?? array();
			if ( empty( $code ) || empty( $tokens ) ) {
				continue;
			}
			$action_tokens[ $code ] = array();
			foreach ( $tokens as $token ) {
				$action_tokens[ $code ][] = array(
					'name'        => $token['name'] ?? '',
					'type'        => $this->normalize_token_type( $token['data_type'] ?? $token['type'] ?? '' ),
					'usage'       => '{{' . ( $token['id'] ?? '' ) . '}}',
					'description' => sprintf( 'Action token from action ID %d.', $action['id'] ?? 0 ),
				);
			}
		}
		$categories['action-tokens'] = $action_tokens;

		return $categories;
	}

	/**
	 * Get loop iteration tokens for a specific loop.
	 *
	 * @since 7.1.0
	 *
	 * @param array $recipe_object Recipe object.
	 * @param int   $loop_id       Loop ID.
	 *
	 * @return array|WP_Error Loop tokens category on success, WP_Error if loop not found.
	 */
	private function get_loop_tokens( array $recipe_object, int $loop_id ) {

		$actions   = $recipe_object['actions']['items'] ?? array();
		$loop_data = null;

		foreach ( $actions as $item ) {
			if ( ( $item['type'] ?? '' ) === 'loop' && (int) ( $item['id'] ?? 0 ) === $loop_id ) {
				$loop_data = $item;
				break;
			}
		}

		if ( null === $loop_data ) {
			return new WP_Error(
				'loop_not_found',
				sprintf( 'Loop %d not found in recipe. Use get_recipe to verify loop IDs.', $loop_id )
			);
		}

		$loop_type   = $loop_data['iterable_expression']['type'] ?? 'unknown';
		$loop_tokens = $loop_data['tokens'] ?? array();

		$formatted = array();
		foreach ( $loop_tokens as $token ) {
			$formatted[] = array(
				'name'  => $token['name'] ?? '',
				'usage' => '{{' . ( $token['id'] ?? '' ) . '}}',
				'type'  => $this->normalize_token_type( $token['data_type'] ?? $token['type'] ?? '' ),
			);
		}

		return array(
			'description' => sprintf( 'Iteration tokens for %s loop (ID %d)', $loop_type, $loop_id ),
			'loop_id'     => $loop_id,
			'loop_type'   => $loop_type,
			'tokens'      => $formatted,
		);
	}

	/**
	 * Normalize a raw token type to the Token_Data_Types vocabulary.
	 *
	 * Handles legacy aliases (int -> integer, bool -> boolean) and defaults
	 * unknown types to 'text'.
	 *
	 * @since 7.1.0
	 *
	 * @param string $raw_type Raw type from token data.
	 *
	 * @return string Normalized type from Token_Data_Types.
	 */
	private function normalize_token_type( string $raw_type ): string {

		if ( '' === $raw_type ) {
			return Token_Data_Types::TEXT;
		}

		$aliases = array(
			'int'  => Token_Data_Types::INTEGER,
			'bool' => Token_Data_Types::BOOLEAN,
		);

		$normalized = isset( $aliases[ $raw_type ] ) ? $aliases[ $raw_type ] : $raw_type;

		return Token_Data_Types_Helper::is_valid( $normalized ) ? $normalized : Token_Data_Types::TEXT;
	}
}
