<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Severity;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Availability_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Recipe_Builder\Shared\Enums\Post_Status;
use Uncanny_Automator\App\Recipe_Builder\Shared\Enums\User_Type;

/**
 * The ModelRecipe wire contract as JSON Schema, from the key lists on `Wire_Key`.
 *
 * `Wire_Shape_Parity_Test` asserts the serializers emit exactly these keys;
 * `Get_Recipe_Tool` publishes `recipe_for_agent()` as its outputSchema. A key
 * added to a serializer without being added here fails the parity test, and a
 * key added here without the serializer emitting it fails the schema test, so
 * the contract has one home. The drift this still cannot see is against the
 * frontend's Zod shapes; see the README.
 *
 * `notices` is the one key outside the serializer: `Recipe_Wire_Service` adds
 * it to a stored recipe's wire when a notice applies, so the recipe schemas
 * publish it as optional (`Wire_Key::OPTIONAL_RECIPE_KEYS`), never required.
 */
final class Model_Recipe_Schema {

	/**
	 * Top-level keys the agent sees.
	 *
	 * @return string[]
	 */
	public static function agent_recipe_keys(): array {
		return array_values( array_diff( Wire_Key::RECIPE_KEYS, Wire_Key::AGENT_STRIPPED_TOP_LEVEL ) );
	}

	/**
	 * `miscellaneous` keys the agent sees.
	 *
	 * @return string[]
	 */
	public static function agent_miscellaneous_keys(): array {
		return array_values( array_diff( Wire_Key::MISCELLANEOUS_KEYS, Wire_Key::AGENT_STRIPPED_MISCELLANEOUS ) );
	}

	/**
	 * The full ModelRecipe schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function recipe(): array {
		return self::recipe_with( Wire_Key::RECIPE_KEYS, Wire_Key::MISCELLANEOUS_KEYS );
	}

	/**
	 * The ModelRecipe schema `get_recipe` publishes: no `can_edit`, no admin urls.
	 *
	 * @return array<string, mixed>
	 */
	public static function recipe_for_agent(): array {
		return self::recipe_with( self::agent_recipe_keys(), self::agent_miscellaneous_keys() );
	}

	/**
	 * `flow`: the entry triggers with their operator, the run-as selector, and the ordered content.
	 *
	 * @return array<string, mixed>
	 */
	public static function flow(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				Wire_Key::ENTRY   => array(
					'type'       => 'object',
					'properties' => array(
						Wire_Key::OPERATOR => array(
							'type'        => 'string',
							'enum'        => array( 'all', 'any' ),
							'description' => 'Whether all triggers or any one trigger starts a run.',
						),
						Wire_Key::TRIGGERS => array(
							'type'  => 'array',
							'items' => self::item(),
						),
					),
					'required'   => array( Wire_Key::OPERATOR, Wire_Key::TRIGGERS ),
				),
				Wire_Key::RUN_AS  => array(
					'type'        => array( 'object', 'null' ),
					'description' => 'The run-as user selector of an anonymous recipe, or null.',
				),
				Wire_Key::CONTENT => array(
					'type'        => 'array',
					'description' => 'Execution-ordered items and blocks.',
					'items'       => self::node(),
				),
			),
			'required'   => Wire_Key::FLOW_KEYS,
		);
	}

	/**
	 * An item node: a trigger or action.
	 *
	 * @return array<string, mixed>
	 */
	public static function item(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				Wire_Key::TYPE                        => array(
					'type' => 'string',
					'enum' => array( Wire_Node_Type::ITEM ),
				),
				Wire_Key::ITEM_TYPE                   => array(
					'type' => 'string',
					'enum' => array(
						Integration_Item_Types::TRIGGER,
						Integration_Item_Types::ACTION,
					),
				),
				Wire_Key::ID                          => array( 'type' => 'integer' ),
				Wire_Key::INTEGRATION_CODE            => array( 'type' => 'string' ),
				Wire_Key::CODE                        => array( 'type' => 'string' ),
				Wire_Key::IS_ITEM_ON                  => array( 'type' => 'boolean' ),
				Wire_Key::CUSTOM_NAME                 => array( 'type' => array( 'string', 'null' ) ),
				Wire_Key::FIELDS                      => array(
					'type'        => 'object',
					'description' => 'Field values keyed by option code: { value, readable, custom }.',
				),
				Wire_Key::LAST_KNOWN_INTEGRATION_NAME => array( 'type' => 'string' ),
				Wire_Key::TOKENS                      => array(
					'type'  => 'array',
					'items' => self::token(),
				),
				Wire_Key::AVAILABILITY                => self::availability(),
				Wire_Key::TAGS                        => array(
					'type'  => 'array',
					'items' => self::tag(),
				),
			),
			'required'   => Wire_Key::ITEM_KEYS,
		);
	}

	/**
	 * A block node: a filter, delay_schedule or loop container.
	 *
	 * @return array<string, mixed>
	 */
	public static function block(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				Wire_Key::TYPE               => array(
					'type' => 'string',
					'enum' => array( Wire_Node_Type::BLOCK ),
				),
				Wire_Key::BLOCK_TYPE         => array(
					'type' => 'string',
					'enum' => array(
						Block_Type::FILTER,
						Block_Type::DELAY_SCHEDULE,
						Block_Type::LOOP,
					),
				),
				Wire_Key::ID                 => array( 'type' => array( 'integer', 'string' ) ),
				Wire_Key::FIELDS             => array( 'type' => 'object' ),
				Wire_Key::MODIFIERS          => array(
					'type'        => 'array',
					'description' => 'Conditions on a filter block; loop filters on a loop block.',
					'items'       => self::modifier(),
				),
				Wire_Key::PATHS              => array(
					'type'        => 'array',
					'description' => 'The block\'s branches in order. path_code is what save_action and save_block accept as path_code.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							Wire_Key::PATH_CODE => array( 'type' => 'string' ),
							Wire_Key::CONTENT   => array(
								'type'        => 'array',
								'description' => 'Items and blocks on this branch; the same node shape as flow.content[].',
								'items'       => array( 'type' => 'object' ),
							),
						),
						'required'   => array( Wire_Key::PATH_CODE, Wire_Key::CONTENT ),
					),
				),
				Wire_Key::TOKENS             => array(
					'type'        => 'array',
					'description' => 'Loop iteration tokens; always empty on filter and delay blocks.',
					'items'       => self::token(),
				),
				Wire_Key::AVAILABILITY       => self::availability(),
				Wire_Key::TAGS               => array(
					'type'  => 'array',
					'items' => self::tag(),
				),
				Wire_Key::DEFERRED_ORDER     => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => 'Present on a block drawn apart from the flow (catalog layout deferred): its position among the recipe\'s deferred blocks, lowest first.',
				),
				Wire_Key::LOOPABLE_TOKEN_IDS => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Present on a loop block: the ids of the loopable tokens a token loop here can iterate. They are the recipe\'s triggers\' loopable tokens, those of the actions that run before the loop at its level or in a block it is in, and the active integrations\' universal ones. save_loop accepts only these.',
				),
			),
			'required'   => Wire_Key::BLOCK_KEYS,
		);
	}

	/**
	 * A block modifier: a filter condition or a loop filter.
	 *
	 * @return array<string, mixed>
	 */
	public static function modifier(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				Wire_Key::TYPE                        => array(
					'type' => 'string',
					'enum' => array( Wire_Node_Type::BLOCK_MODIFIER ),
				),
				Wire_Key::BLOCK_MODIFIER_TYPE         => array(
					'type' => 'string',
					'enum' => array(
						Block_Modifier_Types::FILTER_CONDITION,
						Block_Modifier_Types::LOOP_FILTER,
					),
				),
				Wire_Key::ID                          => array( 'type' => array( 'integer', 'string' ) ),
				Wire_Key::INTEGRATION_CODE            => array( 'type' => 'string' ),
				Wire_Key::CODE                        => array( 'type' => 'string' ),
				Wire_Key::CUSTOM_NAME                 => array( 'type' => array( 'string', 'null' ) ),
				Wire_Key::FIELDS                      => array( 'type' => 'object' ),
				Wire_Key::LAST_KNOWN_INTEGRATION_NAME => array( 'type' => 'string' ),
				Wire_Key::AVAILABILITY                => self::availability(),
				Wire_Key::TAGS                        => array(
					'type'  => 'array',
					'items' => self::tag(),
				),
			),
			'required'   => Wire_Key::MODIFIER_KEYS,
		);
	}

	/**
	 * The recipe schema over a given top-level and miscellaneous key set, then the optional keys (never required).
	 *
	 * @param string[] $recipe_keys        Top-level keys to publish.
	 * @param string[] $miscellaneous_keys `miscellaneous` keys to publish.
	 * @return array<string, mixed>
	 */
	private static function recipe_with( array $recipe_keys, array $miscellaneous_keys ): array {
		$all = array(
			Wire_Key::RECIPE_ID     => array( 'type' => 'integer' ),
			Wire_Key::RECIPE_STATUS => array(
				'type' => 'string',
				'enum' => array(
					Post_Status::PUBLISH,
					Post_Status::DRAFT,
				),
			),
			Wire_Key::CAN_EDIT      => array( 'type' => 'boolean' ),
			Wire_Key::TITLE         => array( 'type' => 'string' ),
			Wire_Key::NOTES         => array( 'type' => 'string' ),
			Wire_Key::CATEGORIES    => array( 'type' => 'array' ),
			Wire_Key::TAGS          => array( 'type' => 'array' ),
			Wire_Key::RECIPE_TYPE   => array(
				'type' => array( 'string', 'null' ),
				'enum' => array(
					User_Type::USER,
					User_Type::ANONYMOUS,
					null,
				),
			),
			Wire_Key::MISCELLANEOUS => self::miscellaneous( $miscellaneous_keys ),
			Wire_Key::SETTINGS      => array(
				'type'        => 'object',
				'description' => 'Recipe settings envelopes keyed by setting id.',
				'properties'  => array_fill_keys( Wire_Key::SETTINGS_KEYS, array( 'type' => 'object' ) ),
				'required'    => Wire_Key::SETTINGS_KEYS,
			),
			Wire_Key::FLOW          => self::flow(),
			Wire_Key::NOTICES       => self::notices(),
		);

		$properties = array();
		foreach ( array_merge( $recipe_keys, Wire_Key::OPTIONAL_RECIPE_KEYS ) as $key ) {
			$properties[ $key ] = $all[ $key ];
		}

		return array(
			'type'       => 'object',
			'properties' => $properties,
			'required'   => $recipe_keys,
		);
	}

	/**
	 * `notices`: what the builder shows about the recipe above the flow, for
	 * example that it was imported and its values need checking. Absent when none apply.
	 *
	 * @return array<string, mixed>
	 */
	private static function notices(): array {
		return array(
			'type'        => 'array',
			'description' => 'Notices about the recipe as it is now, in the order shown. Absent when none apply.',
			'minItems'    => 1,
			'items'       => array(
				'type'                 => 'object',
				'properties'           => array(
					Wire_Key::CODE    => array(
						'type'      => 'string',
						'minLength' => 1,
					),
					Wire_Key::TYPE    => array(
						'type' => 'string',
						'enum' => array( Notice_Severity::ERROR, Notice_Severity::WARNING, Notice_Severity::SUCCESS, Notice_Severity::INFO ),
					),
					Wire_Key::HEADING => array(
						'type'      => 'string',
						'minLength' => 1,
					),
					Wire_Key::MESSAGE => array(
						'type'        => 'string',
						'minLength'   => 1,
						'description' => 'Limited Markdown: **bold**, *italic*, [links](https://...). No HTML.',
					),
				),
				'required'             => array( Wire_Key::CODE, Wire_Key::TYPE, Wire_Key::MESSAGE ),
				'additionalProperties' => false,
			),
		);
	}

	/**
	 * The `miscellaneous` schema over a given key set.
	 *
	 * @param string[] $keys Keys to publish.
	 * @return array<string, mixed>
	 */
	private static function miscellaneous( array $keys ): array {
		$all = array(
			Wire_Key::LAST_MODIFIED_DATE_TIMESTAMP => array( 'type' => 'integer' ),
			Wire_Key::HAS_RUN_NOW                  => array(
				'type'        => 'boolean',
				'description' => 'Whether the recipe has a manual trigger that Run now can fire.',
			),
			Wire_Key::RUN_AS_AVAILABILITY          => array_merge(
				self::availability(),
				array( 'description' => 'Whether this site can use the run-as user selector: usable when a user resolver is registered (Uncanny Automator Pro). When unavailable, a saved selector is ignored when the recipe runs and save_recipe refuses user_selector.' )
			),
			Wire_Key::LATEST_RECIPE_LOG_ID         => array( 'type' => array( 'integer', 'null' ) ),
			Wire_Key::LATEST_RUN_NUMBER            => array( 'type' => array( 'integer', 'null' ) ),
			Wire_Key::URL_DUPLICATE_RECIPE         => array( 'type' => 'string' ),
			Wire_Key::URL_TRASH_RECIPE             => array( 'type' => 'string' ),
			Wire_Key::URL_LOGS                     => array( 'type' => 'string' ),
			Wire_Key::URL_DOWNLOAD_RECIPE          => array( 'type' => 'string' ),
			Wire_Key::URL_ALL_RECIPES              => array( 'type' => 'string' ),
		);

		$properties = array();
		foreach ( $keys as $key ) {
			$properties[ $key ] = $all[ $key ];
		}

		return array(
			'type'       => 'object',
			'properties' => $properties,
			'required'   => $keys,
		);
	}

	/**
	 * A flow node: an item or a block.
	 *
	 * @return array<string, mixed>
	 */
	private static function node(): array {
		return array(
			'type'        => 'object',
			'description' => 'An item (type=item) or a block (type=block).',
			'oneOf'       => array( self::item(), self::block() ),
		);
	}

	/**
	 * One published token.
	 *
	 * @return array<string, mixed>
	 */
	private static function token(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				Wire_Key::ID        => array(
					'type'        => 'string',
					'description' => 'Use as {{id}} in any later field.',
				),
				Wire_Key::NAME      => array( 'type' => 'string' ),
				Wire_Key::DATA_TYPE => array( 'type' => 'string' ),
			),
			'required'   => array(
				Wire_Key::ID,
				Wire_Key::NAME,
			),
		);
	}

	/**
	 * Availability and node-test tags share these fields.
	 *
	 * @return array<string, mixed>
	 */
	private static function tag(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'type'        => array( 'type' => 'string' ),
				'scenario_id' => array( 'type' => 'string' ),
				'label'       => array( 'type' => 'string' ),
				'icon'        => array( 'type' => 'string' ),
				'color'       => array( 'type' => 'string' ),
				'helper'      => array( 'type' => 'string' ),
			),
			'required'   => array( 'type', 'scenario_id', 'label' ),
		);
	}

	/**
	 * Node availability on this site: usable, or unavailable with the reason.
	 *
	 * @return array<string, mixed>
	 */
	private static function availability(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				Wire_Key::STATUS  => array(
					'type' => 'string',
					'enum' => Availability_Status::get_all(),
				),
				Wire_Key::MESSAGE => array(
					'type'        => 'string',
					'description' => 'Why the step cannot run on this site and what the recipe run does with it; absent when usable.',
				),
			),
			'required'   => array(
				Wire_Key::STATUS,
			),
		);
	}
}
