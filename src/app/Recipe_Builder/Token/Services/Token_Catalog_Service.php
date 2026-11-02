<?php
/**
 * Token Catalog Service.
 *
 * Assembles the full set of tokens available in a recipe context:
 * advanced (universal), common, date/time, trigger, action, and loop tokens.
 * Recipe tokens come from the wire flow, at any depth.
 *
 * Extracted from Get_Tokens_Tool to keep the MCP tool layer thin.
 *
 * @package Uncanny_Automator\App\Recipe_Builder\Token\Services
 * @since   7.1.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Token\Services;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration_Exception;
use Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects\Loop_Field_Translator;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Flow_Walker;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Wire_Service;
use Uncanny_Automator\App\Recipe_Builder\Token\Integration\Registry\WP_Integration_Token_Registry;
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
	 * Cache-first wire read of the recipe tree.
	 *
	 * @var Recipe_Wire_Service
	 */
	private Recipe_Wire_Service $wire;

	/**
	 * Constructor.
	 *
	 * @since 7.1.0
	 *
	 * @param Common_Token_Registry|null $common_registry Optional registry instance.
	 * @param Recipe_Wire_Service|null   $wire            Optional wire read override (tests).
	 */
	public function __construct(
		?Common_Token_Registry $common_registry = null,
		?Recipe_Wire_Service $wire = null
	) {
		$this->common_registry = $common_registry ? $common_registry : new Common_Token_Registry();
		$this->wire            = $wire ?? Recipe_Wire_Service::instance();
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
	public function get_tokens_for_recipe( int $recipe_id, ?int $loop_id = null ) {

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
			$wire = $this->wire->get_wire_array( $recipe_id );

			if ( null !== $wire ) {
				$flow             = is_array( $wire[ Wire_Key::FLOW ] ?? null ) ? $wire[ Wire_Key::FLOW ] : array();
				$token_categories = $this->add_recipe_tokens( $token_categories, $flow );

				if ( null !== $loop_id && $loop_id > 0 ) {
					$loop_tokens = $this->get_loop_tokens( $flow, $loop_id );
					if ( is_wp_error( $loop_tokens ) ) {
						return $loop_tokens;
					}
					$token_categories['loop-tokens'] = $loop_tokens;
				}
			}
		} catch ( Legacy_Storage_Migration_Exception $e ) {
			return new WP_Error( 'recipe_unreadable', $e->user_message( $recipe_id ) );
		} catch ( \Exception $e ) {
			return new WP_Error( 'token_catalog_error', sprintf( 'Recipe %d could not be read (%s).', $recipe_id, $e->getMessage() ) );
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
	 * Add trigger and action tokens from the wire flow.
	 *
	 * Triggers come from flow.entry; actions from every item node
	 * at any depth. Loop blocks have a category of their own.
	 *
	 * @since 7.1.0
	 *
	 * @param array $categories Token categories.
	 * @param array $flow       The wire flow.
	 *
	 * @return array Updated categories.
	 */
	private function add_recipe_tokens( array $categories, array $flow ): array {
		$trigger_tokens = array( 'description' => 'Tokens from recipe triggers' );
		$action_tokens  = array( 'description' => 'Tokens from recipe actions' );

		foreach ( Flow_Walker::nodes( $flow ) as $entry ) {
			$node = $entry['node'];

			if ( Wire_Node_Type::ITEM !== ( $node[ Wire_Key::TYPE ] ?? '' ) ) {
				continue;
			}

			$code   = (string) ( $node[ Wire_Key::CODE ] ?? '' );
			$tokens = is_array( $node[ Wire_Key::TOKENS ] ?? null ) ? $node[ Wire_Key::TOKENS ] : array();
			if ( '' === $code || empty( $tokens ) ) {
				continue;
			}

			$item_type  = (string) ( $node[ Wire_Key::ITEM_TYPE ] ?? '' );
			$is_trigger = Integration_Item_Types::TRIGGER === $item_type;
			// A well-formed wire node always carries item_type; a bare label keeps a malformed one readable.
			$label = '' === $item_type ? 'item' : $item_type;
			$rows  = $this->format_tokens( $tokens, sprintf( '%s token from %s ID %d.', ucfirst( $label ), $label, (int) ( $node[ Wire_Key::ID ] ?? 0 ) ) );

			if ( $is_trigger ) {
				$trigger_tokens[ $code ] = array_merge( $trigger_tokens[ $code ] ?? array(), $rows );
			} else {
				$action_tokens[ $code ] = array_merge( $action_tokens[ $code ] ?? array(), $rows );
			}
		}

		$categories['trigger-tokens'] = $trigger_tokens;
		$categories['action-tokens']  = $action_tokens;

		return $categories;
	}

	/**
	 * Wire token rows as the catalog's { name, usage, type, description } rows.
	 *
	 * @param array  $tokens      Wire token rows.
	 * @param string $description Row description.
	 *
	 * @return array
	 */
	private function format_tokens( array $tokens, string $description ): array {
		$rows = array();

		foreach ( $tokens as $token ) {
			if ( ! is_array( $token ) ) {
				continue;
			}

			$rows[] = array(
				'name'        => $token['name'] ?? '',
				'usage'       => '{{' . ( $token[ Wire_Key::ID ] ?? '' ) . '}}',
				'type'        => Token_Data_Types_Helper::normalize( $token['data_type'] ?? $token['type'] ?? '' ),
				'description' => $description,
			);
		}

		return $rows;
	}

	/**
	 * Get loop iteration tokens for a specific loop, at any depth.
	 *
	 * @since 7.1.0
	 *
	 * @param array $flow    The wire flow.
	 * @param int   $loop_id Loop block ID.
	 *
	 * @return array|WP_Error Loop tokens category on success, WP_Error if the id is not a loop block.
	 */
	private function get_loop_tokens( array $flow, int $loop_id ) {
		$loop = Flow_Walker::find( $flow, Wire_Node_Type::BLOCK, $loop_id, Block_Type::LOOP );

		if ( null === $loop ) {
			return new WP_Error(
				'loop_not_found',
				sprintf( 'Loop %d not found in recipe. Use get_recipe to verify loop IDs.', $loop_id )
			);
		}

		// Field_Output_Serializer emits an object for an empty field map.
		$fields    = is_array( $loop[ Wire_Key::FIELDS ] ?? null ) ? $loop[ Wire_Key::FIELDS ] : array();
		$loop_type = Loop_Field_Translator::from_fields( $fields )['type'] ?? 'unknown';
		$tokens    = is_array( $loop[ Wire_Key::TOKENS ] ?? null ) ? $loop[ Wire_Key::TOKENS ] : array();

		$formatted = array();
		foreach ( $tokens as $token ) {
			if ( ! is_array( $token ) ) {
				continue;
			}
			$formatted[] = array(
				'name'  => $token['name'] ?? '',
				'usage' => '{{' . ( $token[ Wire_Key::ID ] ?? '' ) . '}}',
				'type'  => Token_Data_Types_Helper::normalize( $token['data_type'] ?? $token['type'] ?? '' ),
			);
		}

		return array(
			'description' => sprintf( 'Iteration tokens for %s loop (ID %d)', $loop_type, $loop_id ),
			'loop_id'     => $loop_id,
			'loop_type'   => $loop_type,
			'tokens'      => $formatted,
		);
	}
}
