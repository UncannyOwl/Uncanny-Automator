<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Enums\Recipe_Setting_Id;

/**
 * Wire_Key Enum.
 *
 * Every key of the ModelRecipe wire shape the serializers emit and the MCP
 * readers consume, plus the ordered key lists the contract is checked against.
 * `Model_Recipe_Schema` builds its JSON Schema from the lists here, and
 * `Wire_Shape_Parity_Test` asserts the serializers emit exactly them.
 *
 * A key shared by more than one level of the wire (`tags` on the recipe and on
 * every node, `id` on nodes and on token rows) is one constant.
 */
final class Wire_Key {

	// Recipe: top-level ModelRecipe keys.

	/**
	 * Recipe: the recipe post id.
	 */
	const RECIPE_ID = 'recipe_id';

	/**
	 * Recipe: `publish` or `draft`.
	 */
	const RECIPE_STATUS = 'recipe_status';

	/**
	 * Recipe: whether the current user may edit the recipe.
	 */
	const CAN_EDIT = 'can_edit';

	/**
	 * Recipe: the recipe title.
	 */
	const TITLE = 'title';

	/**
	 * Recipe: the author's notes.
	 */
	const NOTES = 'notes';

	/**
	 * Recipe: the recipe's category terms.
	 */
	const CATEGORIES = 'categories';

	/**
	 * Recipe: the recipe's tag terms; on a node, its catalog scope tags.
	 */
	const TAGS = 'tags';

	/**
	 * Recipe: `user`, `anonymous`, or null when untyped.
	 */
	const RECIPE_TYPE = 'recipe_type';

	/**
	 * Recipe: the run-state flags and admin urls object.
	 */
	const MISCELLANEOUS = 'miscellaneous';

	/**
	 * Recipe: the settings envelopes keyed by recipe setting id.
	 */
	const SETTINGS = 'settings';

	/**
	 * Recipe: the entry triggers, run-as selector and ordered content.
	 */
	const FLOW = 'flow';

	// Miscellaneous: keys of the recipe's `miscellaneous` object.

	/**
	 * Miscellaneous: the recipe's last modification time as a unix timestamp.
	 */
	const LAST_MODIFIED_DATE_TIMESTAMP = 'last_modified_date_timestamp';

	/**
	 * Miscellaneous: whether the recipe has a manual trigger Run now can fire.
	 */
	const HAS_RUN_NOW = 'has_run_now';

	/**
	 * Miscellaneous: whether this site can use the run-as setting, shaped like a node's `availability`.
	 *
	 * @since 8.0.0
	 */
	const RUN_AS_AVAILABILITY = 'run_as_availability';

	/**
	 * Miscellaneous: the id of the recipe's latest log row, or null.
	 */
	const LATEST_RECIPE_LOG_ID = 'latest_recipe_log_id';

	/**
	 * Miscellaneous: the recipe's latest run number, or null.
	 */
	const LATEST_RUN_NUMBER = 'latest_run_number';

	/**
	 * Miscellaneous: admin url that duplicates the recipe.
	 */
	const URL_DUPLICATE_RECIPE = 'url_duplicate_recipe';

	/**
	 * Miscellaneous: admin url that trashes the recipe.
	 */
	const URL_TRASH_RECIPE = 'url_trash_recipe';

	/**
	 * Miscellaneous: admin url of the recipe's logs.
	 */
	const URL_LOGS = 'url_logs';

	/**
	 * Miscellaneous: admin url that downloads the recipe export.
	 */
	const URL_DOWNLOAD_RECIPE = 'url_download_recipe';

	/**
	 * Miscellaneous: admin url of the recipe list.
	 */
	const URL_ALL_RECIPES = 'url_all_recipes';

	// Flow: keys of `flow` and of `flow.entry`.

	/**
	 * Flow: the entry object holding the operator and the triggers.
	 */
	const ENTRY = 'entry';

	/**
	 * Flow entry: `all` or `any`, how the triggers combine.
	 */
	const OPERATOR = 'operator';

	/**
	 * Flow entry: the list of trigger item nodes.
	 */
	const TRIGGERS = 'triggers';

	/**
	 * Flow: the run-as user selector of an anonymous recipe, or null.
	 */
	const RUN_AS = 'run_as';

	/**
	 * Flow and block path: the execution-ordered list of item and block nodes.
	 */
	const CONTENT = 'content';

	// Node: keys of item, block and block modifier nodes.

	/**
	 * Node: the node discriminator, one of the `Wire_Node_Type` values.
	 */
	const TYPE = 'type';

	/**
	 * Item node: trigger or action.
	 */
	const ITEM_TYPE = 'item_type';

	/**
	 * Node: the node id; on a token row, the token id.
	 */
	const ID = 'id';

	/**
	 * Item and modifier node: the integration code.
	 */
	const INTEGRATION_CODE = 'integration_code';

	/**
	 * Item and modifier node: the item or modifier code.
	 */
	const CODE = 'code';

	/**
	 * Item node: whether the item is switched on.
	 */
	const IS_ITEM_ON = 'is_item_on';

	/**
	 * Item and modifier node: the author's own name for the node, or null.
	 */
	const CUSTOM_NAME = 'custom_name';

	/**
	 * Node: field values keyed by option code.
	 */
	const FIELDS = 'fields';

	/**
	 * Item and modifier node: the integration's display name as last resolved.
	 */
	const LAST_KNOWN_INTEGRATION_NAME = 'last_known_integration_name';

	/**
	 * Item and block node: the token rows the node publishes.
	 */
	const TOKENS = 'tokens';

	/**
	 * Node: whether the node runs on this site, and why not when it does not.
	 */
	const AVAILABILITY = 'availability';

	/**
	 * Block node with the deferred layout: its position among the flow's deferred blocks, from 0.
	 *
	 * @since 8.0.0
	 */
	const DEFERRED_ORDER = 'deferred_order';

	/**
	 * Loop block node: the ids of the loopable tokens the loop can iterate.
	 *
	 * @since 8.0.1
	 */
	const LOOPABLE_TOKEN_IDS = 'loopable_token_ids';

	/**
	 * Block node: filter, delay_schedule or loop.
	 */
	const BLOCK_TYPE = 'block_type';

	/**
	 * Block node: the block's filter conditions or loop filters.
	 */
	const MODIFIERS = 'modifiers';

	/**
	 * Block node: the block's branches in order.
	 */
	const PATHS = 'paths';

	/**
	 * Block path: the branch's path code, one of the `Block_Path_Code` values.
	 */
	const PATH_CODE = 'path_code';

	/**
	 * Block modifier node: filter_condition or loop_filter.
	 */
	const BLOCK_MODIFIER_TYPE = 'block_modifier_type';

	// Token row: keys of each entry of a node's `tokens` list.

	/**
	 * Token row: the token's display name.
	 */
	const NAME = 'name';

	/**
	 * Token row: the token's normalized data type.
	 */
	const DATA_TYPE = 'data_type';

	// Availability: keys of a node's `availability` object.

	/**
	 * Availability: one of the `Availability_Status` values.
	 */
	const STATUS = 'status';

	/**
	 * Availability: why the node cannot run on this site and what the run does with it; absent when usable.
	 */
	const MESSAGE = 'message';

	// Notices: the recipe's `notices` list, and the one notice key no other level
	// uses (a notice's `code`, `type` and `message` reuse the constants above).

	/**
	 * Recipe: the notices that apply to the recipe right now; absent when none apply.
	 */
	const NOTICES = 'notices';

	/**
	 * Notice: the heading shown above the message; absent when the notice has none.
	 */
	const HEADING = 'heading';

	// Key lists: the ordered contract each wire level is checked against.

	/**
	 * Top-level ModelRecipe keys, in wire order.
	 *
	 * @var string[]
	 */
	const RECIPE_KEYS = array(
		self::RECIPE_ID,
		self::RECIPE_STATUS,
		self::CAN_EDIT,
		self::TITLE,
		self::NOTES,
		self::CATEGORIES,
		self::TAGS,
		self::RECIPE_TYPE,
		self::MISCELLANEOUS,
		self::SETTINGS,
		self::FLOW,
	);

	/**
	 * Top-level ModelRecipe keys sent only when they apply, after the required ones.
	 *
	 * `Recipe_Wire_Service` adds them to a stored recipe's wire; the serializer never emits them.
	 *
	 * @var string[]
	 */
	const OPTIONAL_RECIPE_KEYS = array(
		self::NOTICES,
	);

	/**
	 * `miscellaneous` keys, in wire order.
	 *
	 * @var string[]
	 */
	const MISCELLANEOUS_KEYS = array(
		self::LAST_MODIFIED_DATE_TIMESTAMP,
		self::HAS_RUN_NOW,
		self::RUN_AS_AVAILABILITY,
		self::LATEST_RECIPE_LOG_ID,
		self::LATEST_RUN_NUMBER,
		self::URL_DUPLICATE_RECIPE,
		self::URL_TRASH_RECIPE,
		self::URL_LOGS,
		self::URL_DOWNLOAD_RECIPE,
		self::URL_ALL_RECIPES,
	);

	/**
	 * `settings` envelope ids, in wire order.
	 *
	 * @var string[]
	 */
	const SETTINGS_KEYS = array(
		Recipe_Setting_Id::RECIPE_THROTTLE,
		Recipe_Setting_Id::RECIPE_TOTAL_TIMES,
		Recipe_Setting_Id::RECIPE_TIMES_PER_USER,
		Recipe_Setting_Id::RECIPE_USER_TYPE,
		Recipe_Setting_Id::RECIPE_CREATED_ON,
		Recipe_Setting_Id::RECIPE_COMPLETED_RUNS,
	);

	/**
	 * `flow` keys, in wire order.
	 *
	 * @var string[]
	 */
	const FLOW_KEYS = array(
		self::ENTRY,
		self::RUN_AS,
		self::CONTENT,
	);

	/**
	 * Item node keys (trigger, action), in wire order, decorators included.
	 *
	 * @var string[]
	 */
	const ITEM_KEYS = array(
		self::TYPE,
		self::ITEM_TYPE,
		self::ID,
		self::INTEGRATION_CODE,
		self::CODE,
		self::IS_ITEM_ON,
		self::CUSTOM_NAME,
		self::FIELDS,
		self::LAST_KNOWN_INTEGRATION_NAME,
		self::TOKENS,
		self::AVAILABILITY,
		self::TAGS,
	);

	/**
	 * Block node keys, in wire order, decorators included.
	 *
	 * @var string[]
	 */
	const BLOCK_KEYS = array(
		self::TYPE,
		self::BLOCK_TYPE,
		self::ID,
		self::FIELDS,
		self::MODIFIERS,
		self::PATHS,
		self::TOKENS,
		self::AVAILABILITY,
		self::TAGS,
	);

	/**
	 * Block node keys present only on some blocks, after BLOCK_KEYS in wire order.
	 *
	 * `deferred_order` rides only on a block whose catalog layout is deferred;
	 * `loopable_token_ids` only on a loop block.
	 *
	 * @since 8.0.0
	 * @var string[]
	 */
	const OPTIONAL_BLOCK_KEYS = array(
		self::DEFERRED_ORDER,
		self::LOOPABLE_TOKEN_IDS,
	);

	/**
	 * Block modifier keys, in wire order, decorators included.
	 *
	 * @var string[]
	 */
	const MODIFIER_KEYS = array(
		self::TYPE,
		self::BLOCK_MODIFIER_TYPE,
		self::ID,
		self::INTEGRATION_CODE,
		self::CODE,
		self::CUSTOM_NAME,
		self::FIELDS,
		self::LAST_KNOWN_INTEGRATION_NAME,
		self::AVAILABILITY,
		self::TAGS,
	);

	/**
	 * Token row keys, in wire order.
	 *
	 * @var string[]
	 */
	const TOKEN_KEYS = array(
		self::ID,
		self::NAME,
		self::DATA_TYPE,
	);

	/**
	 * `availability` keys, in wire order.
	 *
	 * @var string[]
	 */
	const AVAILABILITY_KEYS = array(
		self::STATUS,
		self::MESSAGE,
	);

	/**
	 * Recipe notice keys, in wire order; `heading` is sent only when the notice has one.
	 *
	 * @var string[]
	 */
	const NOTICE_KEYS = array(
		self::CODE,
		self::TYPE,
		self::HEADING,
		self::MESSAGE,
	);

	/**
	 * Top-level keys `get_recipe` strips: admin-only edit rights.
	 *
	 * @var string[]
	 */
	const AGENT_STRIPPED_TOP_LEVEL = array(
		self::CAN_EDIT,
	);

	/**
	 * `miscellaneous` keys `get_recipe` strips: admin urls.
	 *
	 * @var string[]
	 */
	const AGENT_STRIPPED_MISCELLANEOUS = array(
		self::URL_DUPLICATE_RECIPE,
		self::URL_TRASH_RECIPE,
		self::URL_LOGS,
		self::URL_DOWNLOAD_RECIPE,
		self::URL_ALL_RECIPES,
	);

	/**
	 * Every wire key, in declaration order.
	 *
	 * Settings envelope ids are not wire keys of their own; they belong to
	 * `Recipe_Setting_Id`.
	 *
	 * @return list<string>
	 */
	public static function get_all(): array {
		return array(
			self::RECIPE_ID,
			self::RECIPE_STATUS,
			self::CAN_EDIT,
			self::TITLE,
			self::NOTES,
			self::CATEGORIES,
			self::TAGS,
			self::RECIPE_TYPE,
			self::MISCELLANEOUS,
			self::SETTINGS,
			self::FLOW,
			self::LAST_MODIFIED_DATE_TIMESTAMP,
			self::HAS_RUN_NOW,
			self::RUN_AS_AVAILABILITY,
			self::LATEST_RECIPE_LOG_ID,
			self::LATEST_RUN_NUMBER,
			self::URL_DUPLICATE_RECIPE,
			self::URL_TRASH_RECIPE,
			self::URL_LOGS,
			self::URL_DOWNLOAD_RECIPE,
			self::URL_ALL_RECIPES,
			self::ENTRY,
			self::OPERATOR,
			self::TRIGGERS,
			self::RUN_AS,
			self::CONTENT,
			self::TYPE,
			self::ITEM_TYPE,
			self::ID,
			self::INTEGRATION_CODE,
			self::CODE,
			self::IS_ITEM_ON,
			self::CUSTOM_NAME,
			self::FIELDS,
			self::LAST_KNOWN_INTEGRATION_NAME,
			self::TOKENS,
			self::AVAILABILITY,
			self::LOOPABLE_TOKEN_IDS,
			self::BLOCK_TYPE,
			self::MODIFIERS,
			self::PATHS,
			self::PATH_CODE,
			self::BLOCK_MODIFIER_TYPE,
			self::NAME,
			self::DATA_TYPE,
			self::STATUS,
			self::MESSAGE,
			self::NOTICES,
			self::HEADING,
		);
	}

	/**
	 * Whether `$key` is a known wire key.
	 *
	 * @param string $key Candidate key.
	 * @return bool True when the key is a wire key.
	 */
	public static function is_valid( string $key ): bool {
		return in_array( $key, self::get_all(), true );
	}
}
