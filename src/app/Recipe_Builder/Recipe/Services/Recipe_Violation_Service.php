<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Query_Service;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks\Loop_Block;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\WP_Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Placement_Rule_Service;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Fields_Bag;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Validation_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Recipe_Tree_Builder;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Violation;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Violation_Target;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Availability_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Entity_Availability_Resolver;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Run_As_Availability_Resolver;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Value_Objects\User_Selector_Source;

/**
 * The set-status validation rule engine — the single "evaluate recipe,
 * return violations with targets" service behind the 422 rejection
 * contract (and, once confirmed, the payload annotation).
 *
 * Reads the canonical recipe tree only (Recipe_Tree_Builder); built
 * fresh, never from the schema cache, because a correctness gate must
 * see live state (delete paths rebuild the cache pre-deletion).
 *
 * Vocabulary is open: codes are lowercase snake_case class constants;
 * the FE never branches on them, so new rules ship backend-only.
 */
final class Recipe_Violation_Service {

	/**
	 * WP_Error code the set_status services use to carry a validation
	 * rejection to the REST layer (which maps it to the 422 envelope).
	 *
	 * @var string
	 */
	public const REJECTION_CODE = 'automator_validation_rejection';

	/**
	 * Violation code: no live trigger on the recipe.
	 *
	 * @var string
	 */
	public const RECIPE_MISSING_LIVE_TRIGGER = 'recipe_missing_live_trigger';

	/**
	 * Violation code: no live action on the recipe.
	 *
	 * @var string
	 */
	public const RECIPE_MISSING_LIVE_ACTION = 'recipe_missing_live_action';

	/**
	 * Violation code: a draft trigger without an on / off switch is waiting
	 * for its configuration. Targeted at that trigger; the recipe itself
	 * carries a missing live trigger.
	 *
	 * @var string
	 */
	public const RECIPE_TRIGGER_NEEDS_SETUP = 'recipe_trigger_needs_setup';

	/**
	 * Violation code: the item has empty required fields.
	 *
	 * @var string
	 */
	public const ITEM_REQUIRED_FIELDS_EMPTY = 'item_required_fields_empty';

	/**
	 * Violation code: the item's integration is not installed / active / licensed.
	 *
	 * @var string
	 */
	public const ITEM_INTEGRATION_UNAVAILABLE = 'item_integration_unavailable';

	/**
	 * Violation code: the recipe runs as an existing user, but the value used
	 * to find that user was left blank.
	 *
	 * @var string
	 */
	public const RECIPE_RUN_AS_INCOMPLETE = 'recipe_run_as_incomplete';

	/**
	 * Violation code: a live action sits outside the block it must be used inside.
	 *
	 * @var string
	 */
	public const ITEM_PLACEMENT_INVALID = 'item_placement_invalid';

	/**
	 * Violation code: a block sits inside a block that does not allow it.
	 *
	 * @var string
	 */
	public const BLOCK_PLACEMENT_INVALID = 'block_placement_invalid';

	/**
	 * Violation code: a block has empty required fields.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	public const BLOCK_REQUIRED_FIELDS_EMPTY = 'block_required_fields_empty';

	/**
	 * Violation code: a block modifier has empty required fields.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	public const BLOCK_MODIFIER_REQUIRED_FIELDS_EMPTY = 'block_modifier_required_fields_empty';

	/**
	 * Violation code: a Loop block has no filter.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	public const BLOCK_MODIFIER_MISSING = 'block_modifier_missing';

	/**
	 * Violation code: a block modifier's integration is unavailable on this
	 * site, or its code is not registered.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	public const BLOCK_MODIFIER_UNAVAILABLE = 'block_modifier_unavailable';

	/**
	 * Singleton instance.
	 *
	 * @var Recipe_Violation_Service|null
	 */
	private static ?Recipe_Violation_Service $instance = null;

	/**
	 * Canonical-tree source.
	 *
	 * @var Recipe_Tree_Builder
	 */
	private Recipe_Tree_Builder $tree_builder;

	/**
	 * Required-fields engine (the scoped-field-validation service).
	 *
	 * @var Field_Validation_Service
	 */
	private Field_Validation_Service $field_validation;

	/**
	 * Availability source — the SAME resolver the wire availability
	 * decorator uses, so `item_integration_unavailable` and the wire
	 * `availability.status` can never disagree.
	 *
	 * @var Recipe_Entity_Availability_Resolver
	 */
	private Recipe_Entity_Availability_Resolver $availability;

	/**
	 * Catalog reader — display-name fallback for violation messages.
	 *
	 * @var Integration_Query_Service
	 */
	private Integration_Query_Service $catalog;

	/**
	 * The placement rules: the blocks above an action, and whether they allow it there.
	 *
	 * @var Placement_Rule_Service
	 */
	private Placement_Rule_Service $placement;

	/**
	 * Switch-less declaration reader.
	 *
	 * @var Trigger_Registry_Service
	 */
	private Trigger_Registry_Service $trigger_registry;

	/**
	 * Configurable-fields reader.
	 *
	 * @var Field_Registry_Service
	 */
	private Field_Registry_Service $field_registry;

	/**
	 * Block definitions — the source of a block's fields and name.
	 *
	 * @var Block_Registry
	 */
	private Block_Registry $block_registry;

	/**
	 * Run-as availability — the SAME resolver the wire's
	 * `miscellaneous.run_as_availability` comes from, so the run-as rules and
	 * the builder can never disagree.
	 *
	 * @var Run_As_Availability_Resolver
	 */
	private Run_As_Availability_Resolver $run_as_availability;

	/**
	 * Lazy-default constructor injection (Rule 9c).
	 *
	 * @param Recipe_Tree_Builder|null                  $tree_builder     Tree source (test seam).
	 * @param Field_Validation_Service|null             $field_validation Required-fields engine.
	 * @param Recipe_Entity_Availability_Resolver|null  $availability     Availability source.
	 * @param Integration_Query_Service|null            $catalog          Catalog reader.
	 * @param Placement_Rule_Service|null               $placement        Placement rules (test seam).
	 * @param Trigger_Registry_Service|null             $trigger_registry Switch-less declaration reader.
	 * @param Field_Registry_Service|null               $field_registry   Configurable-fields reader.
	 * @param Block_Registry|null                       $block_registry   Block definitions (test seam).
	 * @param Run_As_Availability_Resolver|null         $run_as_availability Run-as availability (test seam).
	 */
	private function __construct(
		?Recipe_Tree_Builder $tree_builder = null,
		?Field_Validation_Service $field_validation = null,
		?Recipe_Entity_Availability_Resolver $availability = null,
		?Integration_Query_Service $catalog = null,
		?Placement_Rule_Service $placement = null,
		?Trigger_Registry_Service $trigger_registry = null,
		?Field_Registry_Service $field_registry = null,
		?Block_Registry $block_registry = null,
		?Run_As_Availability_Resolver $run_as_availability = null
	) {
		$this->tree_builder     = $tree_builder ?? Recipe_Tree_Builder::instance();
		$this->field_validation = $field_validation ?? Field_Validation_Service::instance();
		$this->availability     = $availability ?? new Recipe_Entity_Availability_Resolver();
		$this->catalog          = $catalog ?? Integration_Query_Service::get_instance();
		$this->placement        = $placement ?? Placement_Rule_Service::instance();
		$this->trigger_registry = $trigger_registry ?? Trigger_Registry_Service::instance();
		$this->field_registry   = $field_registry ?? Field_Registry_Service::instance();
		$this->block_registry   = $block_registry ?? new WP_Block_Registry();

		$this->run_as_availability = $run_as_availability ?? new Run_As_Availability_Resolver();
	}

	/**
	 * Singleton accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Test seam — fresh instance with injected collaborators.
	 *
	 * @param Recipe_Tree_Builder|null                  $tree_builder     Tree source.
	 * @param Field_Validation_Service|null             $field_validation Required-fields engine.
	 * @param Recipe_Entity_Availability_Resolver|null  $availability     Availability source.
	 * @param Integration_Query_Service|null            $catalog          Catalog reader.
	 * @param Placement_Rule_Service|null               $placement        Placement rules (test seam).
	 * @param Trigger_Registry_Service|null             $trigger_registry Switch-less declaration reader.
	 * @param Field_Registry_Service|null               $field_registry   Configurable-fields reader.
	 * @param Block_Registry|null                       $block_registry   Block definitions (test seam).
	 * @param Run_As_Availability_Resolver|null         $run_as_availability Run-as availability (test seam).
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?Recipe_Tree_Builder $tree_builder = null,
		?Field_Validation_Service $field_validation = null,
		?Recipe_Entity_Availability_Resolver $availability = null,
		?Integration_Query_Service $catalog = null,
		?Placement_Rule_Service $placement = null,
		?Trigger_Registry_Service $trigger_registry = null,
		?Field_Registry_Service $field_registry = null,
		?Block_Registry $block_registry = null,
		?Run_As_Availability_Resolver $run_as_availability = null
	): self {
		return new self( $tree_builder, $field_validation, $availability, $catalog, $placement, $trigger_registry, $field_registry, $block_registry, $run_as_availability );
	}

	/**
	 * Whether a violation list blocks its transition — the single emptiness
	 * seam for evaluate results and their wire projections, so call sites
	 * never hand-roll `array() !== $violations` literal comparisons.
	 *
	 * @param array $violations Recipe_Violation[] or their wire shapes.
	 *
	 * @return bool
	 */
	public static function has_violations( array $violations ): bool {
		return array() !== $violations;
	}

	/**
	 * Build the rejection WP_Error the REST layer maps to the 422 envelope,
	 * or null when the list is clean — the single construction seam for the
	 * gate services (code, status, and wire mapping can never drift apart).
	 *
	 * @param Recipe_Violation[] $violations Evaluate-method result.
	 * @param string             $message    Localized top-level summary.
	 *
	 * @return \WP_Error|null Null when there is nothing to reject.
	 */
	public static function rejection_error( array $violations, string $message ): ?\WP_Error {
		if ( ! self::has_violations( $violations ) ) {
			return null;
		}

		return new \WP_Error(
			self::REJECTION_CODE,
			$message,
			array(
				'status'     => 422,
				'violations' => array_map(
					static function ( Recipe_Violation $violation ) {
						return $violation->to_wire();
					},
					$violations
				),
			)
		);
	}

	/**
	 * Item→on rules for one trigger/action: integration availability, then
	 * required fields (skipped when the integration is unavailable — its
	 * field definitions aren't resolvable, and a second violation for the
	 * same root cause is noise). Every violation targets the item.
	 *
	 * @param int         $item_id          Item post ID (item targets are always int).
	 * @param string      $item_type        'trigger' | 'action'.
	 * @param string      $code             Item code.
	 * @param string      $integration_code Integration code.
	 * @param string|null $custom_name      User-set display name, if any.
	 * @param array       $fields           Persisted field config.
	 * @return Recipe_Violation[] Empty when the item may switch on.
	 */
	public function evaluate_item_on( int $item_id, string $item_type, string $code, string $integration_code, ?string $custom_name, array $fields ): array {
		$target = new Recipe_Violation_Target( Wire_Node_Type::ITEM, $item_id );
		$name   = $this->display_name( $custom_name, $integration_code, $code, $item_type );

		$resolved = $this->availability->resolve(
			array(
				Wire_Key::TYPE             => Wire_Node_Type::ITEM,
				Wire_Key::INTEGRATION_CODE => $integration_code,
				Wire_Key::CODE             => $code,
				Wire_Key::ITEM_TYPE        => $item_type,
			)
		);

		if ( Availability_Status::USABLE !== ( $resolved[ Wire_Key::AVAILABILITY ][ Wire_Key::STATUS ] ?? '' ) ) {
			return array(
				new Recipe_Violation(
					self::ITEM_INTEGRATION_UNAVAILABLE,
					$this->unavailable_message( (string) ( $resolved[ Wire_Key::AVAILABILITY ][ Wire_Key::MESSAGE ] ?? '' ), $item_type, $name ),
					$target
				),
			);
		}

		$check = $this->field_validation->validate_for_publish( $code, $fields, $item_type );
		if ( is_wp_error( $check ) ) {
			return array(
				new Recipe_Violation(
					self::ITEM_REQUIRED_FIELDS_EMPTY,
					$this->required_fields_message( $item_type, $name ),
					$target
				),
			);
		}

		return array();
	}

	/**
	 * Placement rules for one action being switched on: its declared rules
	 * against the blocks above it. The recipe-level publish gate runs the same
	 * check over the whole tree; this is the item-toggle counterpart, so an
	 * action cannot go live one at a time where the recipe could not.
	 *
	 * @param int         $action_id        Action post ID (the violation target).
	 * @param int         $parent_id        The action's parent post ID (recipe or block).
	 * @param string      $code             Action code.
	 * @param string      $integration_code Integration code.
	 * @param string|null $custom_name      User-set display name, if any.
	 * @param string      $path_code        The parent's path the action sits on; '' for the parent's default path.
	 * @return Recipe_Violation[] Empty when the placement is allowed.
	 */
	public function evaluate_action_placement( int $action_id, int $parent_id, string $code, string $integration_code, ?string $custom_name, string $path_code = '' ): array {
		return $this->placement_violation( $action_id, $code, $integration_code, $custom_name, $this->placement->chain_for( $parent_id, $path_code ) );
	}

	/**
	 * Publish rules for one block: its own required fields, then each
	 * modifier — availability first (an unavailable or unregistered modifier
	 * is one violation and skips its field check, as items do), then its
	 * required fields. Paths are not walked; the caller walks them.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string,mixed> $block_node Block tree entry.
	 * @return Recipe_Violation[] Empty when the block may go live.
	 */
	public function evaluate_block( array $block_node ): array {
		$violations = array_merge( $this->block_field_violations( $block_node ), $this->loop_filter_violations( $block_node ) );

		foreach ( (array) ( $block_node['modifiers'] ?? array() ) as $modifier ) {
			if ( ! is_array( $modifier ) ) {
				continue;
			}

			$definitions = $this->usable_modifier_definitions( $modifier );
			if ( null === $definitions ) {
				$violations[] = $this->modifier_violation( self::BLOCK_MODIFIER_UNAVAILABLE, $modifier );
				continue;
			}

			$violations = array_merge( $violations, $this->modifier_field_violations( $modifier, $definitions ) );
		}

		return $violations;
	}

	/**
	 * Run-time rules for one block: its own required fields, then each
	 * modifier's. A modifier whose integration is unavailable, or whose code
	 * is not registered, is skipped here rather than refused: the block's
	 * processor meets it (Pro's filter processor counts such a condition as
	 * not met, as its wire availability says). Paths are not walked; the
	 * caller walks them.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string,mixed> $block_node Block tree entry.
	 * @return Recipe_Violation[] Empty when the block may run.
	 */
	public function evaluate_block_configuration( array $block_node ): array {
		$violations = array_merge( $this->block_field_violations( $block_node ), $this->loop_filter_violations( $block_node ) );

		foreach ( (array) ( $block_node['modifiers'] ?? array() ) as $modifier ) {
			if ( ! is_array( $modifier ) ) {
				continue;
			}

			$definitions = $this->usable_modifier_definitions( $modifier );
			if ( null !== $definitions ) {
				$violations = array_merge( $violations, $this->modifier_field_violations( $modifier, $definitions ) );
			}
		}

		return $violations;
	}

	/**
	 * Structural rules only: at least one live trigger AND one live
	 * action. This is the runnability invariant — Recipe_Runnability_Service
	 * delegates here so the two sides of the rule can never disagree.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return Recipe_Violation[] Empty when structurally publishable.
	 */
	public function evaluate_recipe_structural( int $recipe_id ): array {
		$tree = $this->tree_builder->build( $recipe_id );
		if ( null === $tree ) {
			return array( $this->missing_live_trigger(), $this->missing_live_action() );
		}

		return $this->structural_violations( $tree );
	}

	/**
	 * Recipe→live rules: structural (live trigger + live action exist) plus
	 * item checks on every LIVE trigger/action node — draft/off nodes are
	 * ignored per the contract — and placement and configuration checks
	 * (evaluate_block()) on every block, which has no draft state. Violations arrive in tree order: structural
	 * first, then triggers, then content (block / loop paths walked).
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return Recipe_Violation[] Empty when the recipe may go live.
	 */
	public function evaluate_recipe_publish( int $recipe_id ): array {
		$tree = $this->tree_builder->build( $recipe_id );
		if ( null === $tree ) {
			return array( $this->missing_live_trigger(), $this->missing_live_action() );
		}

		$violations = $this->structural_violations( $tree );
		$violations = array_merge( $violations, $this->run_as_violations( $tree ) );

		foreach ( (array) ( $tree['triggers'] ?? array() ) as $entry ) {
			if ( is_array( $entry ) ) {
				$violations = array_merge( $violations, $this->evaluate_live_entry( $entry ) );
			}
		}

		return array_merge( $violations, $this->evaluate_live_content( (array) ( $tree['content'] ?? array() ) ) );
	}

	/**
	 * Walk content entries: live actions get item checks and placement
	 * checks against the blocks above them; block entries get placement and
	 * configuration checks, then extend the chain and recurse into their
	 * `paths[].items`.
	 *
	 * @param array<int,mixed>                                                                 $entries Tree content entries.
	 * @param array<int,array{block_type:string,fields:array<string,mixed>,path_code:string}> $chain   Blocks above these entries, outermost first, each with the path the chain passes through.
	 * @return Recipe_Violation[]
	 */
	private function evaluate_live_content( array $entries, array $chain = array() ): array {
		$violations = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			if ( 'action' === ( $entry['kind'] ?? '' ) ) {
				$violations = array_merge( $violations, $this->evaluate_live_entry( $entry ), $this->placement_violations( $entry, $chain ) );
			}

			if ( 'block' === ( $entry['kind'] ?? '' ) ) {
				$violations = array_merge( $violations, $this->block_placement_violation( $entry, $chain ), $this->evaluate_block( $entry ) );
			}

			foreach ( (array) ( $entry['paths'] ?? array() ) as $path ) {
				$deeper     = $chain;
				$deeper[]   = Placement_Rule_Service::entry(
					(string) ( $entry['block_type'] ?? '' ),
					is_array( $entry['fields'] ?? null ) ? $entry['fields'] : array(),
					(string) ( $path['path_code'] ?? '' )
				);
				$violations = array_merge( $violations, $this->evaluate_live_content( (array) ( $path['items'] ?? array() ), $deeper ) );
			}
		}

		return $violations;
	}

	/**
	 * Item checks for one live tree entry, against the fields it stores.
	 *
	 * @param array<string,mixed> $entry Trigger/action tree entry.
	 * @return Recipe_Violation[]
	 */
	private function evaluate_live_entry( array $entry ): array {
		if ( true !== ( $entry['is_item_on'] ?? false ) ) {
			return array();
		}

		$code = (string) ( $entry['code'] ?? '' );
		if ( '' === $code ) {
			return array();
		}

		return $this->evaluate_item_on(
			(int) ( $entry['id'] ?? 0 ),
			(string) ( $entry['kind'] ?? '' ),
			$code,
			(string) ( $entry['integration_code'] ?? '' ),
			isset( $entry['custom_name'] ) ? (string) $entry['custom_name'] : null,
			is_array( $entry['fields'] ?? null ) ? $entry['fields'] : array()
		);
	}

	/**
	 * The placement violation for one block, if a block above it does not
	 * allow it there. A block has no draft state, so every block is evaluated.
	 *
	 * @param array<string,mixed>                                                              $entry Block tree entry.
	 * @param array<int,array{block_type:string,fields:array<string,mixed>,path_code:string}> $chain Blocks above the block, outermost first.
	 * @return Recipe_Violation[]
	 */
	private function block_placement_violation( array $entry, array $chain ): array {
		$block_type = (string) ( $entry['block_type'] ?? '' );
		$violation  = $this->placement->violation( $chain, $this->placement->block_candidate( $block_type ) );
		if ( null === $violation ) {
			return array();
		}

		return array(
			new Recipe_Violation(
				self::BLOCK_PLACEMENT_INVALID,
				$this->placement->message( $violation, $block_type ),
				new Recipe_Violation_Target( Wire_Node_Type::BLOCK, (int) ( $entry['id'] ?? 0 ) )
			),
		);
	}

	/**
	 * The required-fields violation for a block's own fields, if any.
	 *
	 * @param array<string,mixed> $block_node Block tree entry.
	 * @return Recipe_Violation[]
	 */
	private function block_field_violations( array $block_node ): array {
		$definition = $this->block_registry->get_block_definition( (string) ( $block_node['block_type'] ?? '' ) );
		if ( null === $definition ) {
			return array();
		}

		$definitions = $definition->get_fields();
		$check       = $this->field_validation->validate_required_present(
			$definitions,
			$this->effective_config( $definitions, is_array( $block_node['fields'] ?? null ) ? $block_node['fields'] : array() )
		);
		if ( ! is_wp_error( $check ) ) {
			return array();
		}

		return array(
			new Recipe_Violation(
				self::BLOCK_REQUIRED_FIELDS_EMPTY,
				sprintf(
					// translators: %s is the block's name, such as Loop.
					esc_html_x( 'The %s block has empty required fields.', 'Recipe violation message', 'uncanny-automator' ),
					$definition->get_name()
				),
				new Recipe_Violation_Target( Wire_Node_Type::BLOCK, (int) ( $block_node['id'] ?? 0 ) )
			),
		);
	}

	/**
	 * The violation for a Loop block with no filter. Pro takes a loop's items
	 * from its filters only, so a loop without one runs over nothing. A loop
	 * with no type gets none: its empty required type already refuses it, and
	 * its default filter only arrives once a type is saved.
	 *
	 * @param array<string,mixed> $block_node Block tree entry.
	 * @return Recipe_Violation[]
	 */
	private function loop_filter_violations( array $block_node ): array {
		if ( Block_Type::LOOP !== (string) ( $block_node['block_type'] ?? '' ) || ! empty( $block_node['modifiers'] ) ) {
			return array();
		}

		$fields = is_array( $block_node['fields'] ?? null ) ? $block_node['fields'] : array();
		if ( '' === ( new Fields_Bag( $fields ) )->scalar( Loop_Block::FIELD_LOOP_TYPE ) ) {
			return array();
		}

		$definition = $this->block_registry->get_block_definition( Block_Type::LOOP );
		if ( null === $definition ) {
			return array();
		}

		return array(
			new Recipe_Violation(
				self::BLOCK_MODIFIER_MISSING,
				sprintf(
					// translators: %s is the block's name, such as Loop.
					esc_html_x( 'The %s block needs at least one filter.', 'Recipe violation message', 'uncanny-automator' ),
					$definition->get_name()
				),
				new Recipe_Violation_Target( Wire_Node_Type::BLOCK, (int) ( $block_node['id'] ?? 0 ) )
			),
		);
	}

	/**
	 * The values a block or modifier runs with, keyed by option code: the
	 * saved value when the field was saved at all (a saved '' stays empty),
	 * else the definition's `default_value` — what the builder dialog shows
	 * and what the processors read for a field nobody saved. Read through
	 * Fields_Bag, which unwraps the envelope, bare-scalar and flat shapes.
	 *
	 * @param array $definitions Field definitions.
	 * @param array $fields      Stored fields.
	 * @return array<string,string>
	 */
	private function effective_config( array $definitions, array $fields ): array {
		$bag    = new Fields_Bag( $fields );
		$config = array();

		foreach ( $definitions as $definition ) {
			$definition = (array) $definition;
			$code       = (string) ( $definition['option_code'] ?? '' );
			if ( '' === $code ) {
				continue;
			}

			if ( null !== $bag->get( $code ) ) {
				$config[ $code ] = $bag->scalar( $code );
				continue;
			}

			if ( isset( $definition['default_value'] ) && is_scalar( $definition['default_value'] ) ) {
				$config[ $code ] = (string) $definition['default_value'];
			}
		}

		return $config;
	}

	/**
	 * A modifier's field definitions when it can run on this site: usable per
	 * the wire availability resolver and registered. Null otherwise.
	 *
	 * @param array<string,mixed> $modifier Modifier tree entry.
	 * @return array|null
	 */
	private function usable_modifier_definitions( array $modifier ): ?array {
		$integration_code    = (string) ( $modifier['integration_code'] ?? '' );
		$code                = (string) ( $modifier['code'] ?? '' );
		$block_modifier_type = (string) ( $modifier['block_modifier_type'] ?? '' );

		$resolved = $this->availability->resolve(
			array(
				Wire_Key::TYPE                => Wire_Node_Type::BLOCK_MODIFIER,
				Wire_Key::INTEGRATION_CODE    => $integration_code,
				Wire_Key::CODE                => $code,
				Wire_Key::BLOCK_MODIFIER_TYPE => $block_modifier_type,
			)
		);
		if ( Availability_Status::USABLE !== ( $resolved[ Wire_Key::AVAILABILITY ][ Wire_Key::STATUS ] ?? '' ) ) {
			return null;
		}

		$definitions = $this->field_registry->get_block_modifier_fields( $integration_code, $block_modifier_type, $code );

		return is_wp_error( $definitions ) ? null : $definitions;
	}

	/**
	 * The required-fields violation for one usable modifier, if any.
	 *
	 * @param array<string,mixed> $modifier    Modifier tree entry.
	 * @param array               $definitions Its field definitions.
	 * @return Recipe_Violation[]
	 */
	private function modifier_field_violations( array $modifier, array $definitions ): array {
		$check = $this->field_validation->validate_required_present(
			$definitions,
			$this->effective_config( $definitions, is_array( $modifier['fields'] ?? null ) ? $modifier['fields'] : array() )
		);
		if ( ! is_wp_error( $check ) ) {
			return array();
		}

		return array( $this->modifier_violation( self::BLOCK_MODIFIER_REQUIRED_FIELDS_EMPTY, $modifier ) );
	}

	/**
	 * One violation targeting a modifier, by the tree's id verbatim.
	 *
	 * @param string              $code     Violation code.
	 * @param array<string,mixed> $modifier Modifier tree entry.
	 * @return Recipe_Violation
	 */
	private function modifier_violation( string $code, array $modifier ): Recipe_Violation {
		return new Recipe_Violation(
			$code,
			$this->modifier_message( $code, $modifier ),
			new Recipe_Violation_Target( Wire_Node_Type::BLOCK_MODIFIER, $modifier['id'] ?? 0 )
		);
	}

	/**
	 * The modifier message per code and kind (full templates so translators
	 * see complete sentences). Plain text: the run log shows it as-is, so
	 * the name sits in typographic quotes, never Markdown emphasis.
	 *
	 * @param string              $code     Violation code.
	 * @param array<string,mixed> $modifier Modifier tree entry.
	 * @return string
	 */
	private function modifier_message( string $code, array $modifier ): string {
		$name    = $this->modifier_display_name( $modifier );
		$is_loop = Block_Modifier_Types::LOOP_FILTER === (string) ( $modifier['block_modifier_type'] ?? '' );

		if ( self::BLOCK_MODIFIER_UNAVAILABLE === $code ) {
			return $is_loop
				// translators: %s is the loop filter's name.
				? sprintf( esc_html_x( 'The loop filter “%s” requires an integration that is unavailable on this site.', 'Recipe violation message', 'uncanny-automator' ), $name )
				// translators: %s is the condition's name.
				: sprintf( esc_html_x( 'The condition “%s” requires an integration that is unavailable on this site.', 'Recipe violation message', 'uncanny-automator' ), $name );
		}

		return $is_loop
			// translators: %s is the loop filter's name.
			? sprintf( esc_html_x( 'The loop filter “%s” has empty required fields.', 'Recipe violation message', 'uncanny-automator' ), $name )
			// translators: %s is the condition's name.
			: sprintf( esc_html_x( 'The condition “%s” has empty required fields.', 'Recipe violation message', 'uncanny-automator' ), $name );
	}

	/**
	 * Display name for a modifier: its custom name when set; else the
	 * catalog sentence as plain text; else the raw code.
	 *
	 * @param array<string,mixed> $modifier Modifier tree entry.
	 * @return string
	 */
	private function modifier_display_name( array $modifier ): string {
		$custom_name = trim( (string) ( $modifier['custom_name'] ?? '' ) );
		if ( '' !== $custom_name ) {
			return $custom_name;
		}

		$code  = (string) ( $modifier['code'] ?? '' );
		$row   = $this->catalog->get_block_modifier( (string) ( $modifier['integration_code'] ?? '' ), $code, (string) ( $modifier['block_modifier_type'] ?? '' ) );
		$plain = null === $row ? '' : $this->plain_sentence( $row->to_array()['sentence'] ?? array() );

		return '' === $plain ? $code : $plain;
	}

	/**
	 * The placement violation for one live action, if it sits outside every
	 * block its declared rules allow. Draft actions are skipped, like every
	 * other publish rule.
	 *
	 * @param array<string,mixed>                                              $entry Action tree entry.
	 * @param array<int,array{block_type:string,fields:array<string,mixed>}> $chain Blocks above the action, outermost first.
	 * @return Recipe_Violation[]
	 */
	private function placement_violations( array $entry, array $chain ): array {
		if ( true !== ( $entry['is_item_on'] ?? false ) ) {
			return array();
		}

		return $this->placement_violation(
			(int) ( $entry['id'] ?? 0 ),
			(string) ( $entry['code'] ?? '' ),
			(string) ( $entry['integration_code'] ?? '' ),
			isset( $entry['custom_name'] ) ? (string) $entry['custom_name'] : null,
			$chain
		);
	}

	/**
	 * The placement violation for one action at a given chain, if a block
	 * above it or its own declared rules reject it there.
	 *
	 * @param int                                                                              $action_id        Action post ID (the violation target).
	 * @param string                                                                           $code             Action code.
	 * @param string                                                                           $integration_code Integration code.
	 * @param string|null                                                                      $custom_name      User-set display name, if any.
	 * @param array<int,array{block_type:string,fields:array<string,mixed>,path_code:string}> $chain            Blocks above the action, outermost first.
	 * @return Recipe_Violation[]
	 */
	private function placement_violation( int $action_id, string $code, string $integration_code, ?string $custom_name, array $chain ): array {
		$violation = $this->placement->violation( $chain, $this->placement->action_candidate( $code, $integration_code ) );
		if ( null === $violation ) {
			return array();
		}

		return array(
			new Recipe_Violation(
				self::ITEM_PLACEMENT_INVALID,
				$this->placement->message( $violation, $this->display_name( $custom_name, $integration_code, $code, 'action' ) ),
				new Recipe_Violation_Target( Wire_Node_Type::ITEM, $action_id )
			),
		);
	}

	/**
	 * Structural rules against an already-built tree.
	 *
	 * @param array<string,mixed> $tree Recipe tree (Recipe_Tree_Builder shape).
	 * @return Recipe_Violation[]
	 */
	private function structural_violations( array $tree ): array {
		$violations = array();

		if ( ! $this->tree_has_live_trigger( $tree ) ) {
			$trigger_id = $this->trigger_needing_setup( $tree );
			if ( null === $trigger_id ) {
				$violations[] = $this->missing_live_trigger();
			} else {
				$violations[] = $this->missing_live_trigger_until_setup();
				$violations[] = $this->trigger_needs_setup( $trigger_id );
			}
		}
		if ( ! $this->tree_has_live_action( $tree ) ) {
			$violations[] = $this->missing_live_action();
		}

		return $violations;
	}

	/**
	 * Run-as rules, read off `flow.run_as` as the tree already projects it.
	 *
	 * One rule today: a recipe that runs as an *existing* user needs a value
	 * to find that user by. `User_Selector` deliberately accepts an empty
	 * string at save time — the builder seeds the field before the author
	 * fills it — so the non-empty requirement has to be a publish-time gate
	 * or it is no requirement at all.
	 *
	 * A recipe with no run-as configured is not a violation: the projection
	 * is null and this returns nothing. Neither is a run-as setting this site
	 * cannot use (no user resolver is registered): the run ignores it, so
	 * nothing it holds can block going live.
	 *
	 * @param array<string,mixed> $tree Recipe tree (Recipe_Tree_Builder shape).
	 *
	 * @return Recipe_Violation[]
	 */
	private function run_as_violations( array $tree ): array {
		$fields = $tree['run_as']['fields'] ?? null;
		if ( ! is_array( $fields ) ) {
			return array();
		}

		if ( Availability_Status::USABLE !== ( $this->run_as_availability->resolve()[ Wire_Key::STATUS ] ?? '' ) ) {
			return array();
		}

		if ( User_Selector_Source::EXISTING_USER !== ( $fields['SOURCE']['value'] ?? '' ) ) {
			return array();
		}

		if ( '' !== trim( (string) ( $fields['UNIQUE_FIELD_VALUE']['value'] ?? '' ) ) ) {
			return array();
		}

		return array( $this->run_as_incomplete() );
	}

	/**
	 * The run-as violation raised when the existing-user lane has no value to
	 * match on.
	 *
	 * @return Recipe_Violation
	 */
	private function run_as_incomplete(): Recipe_Violation {
		return new Recipe_Violation(
			self::RECIPE_RUN_AS_INCOMPLETE,
			esc_html_x( 'The recipe runs as an existing user, but no value was set to find that user by.', 'Recipe violation message', 'uncanny-automator' )
		);
	}

	/**
	 * The missing-live-trigger structural violation.
	 *
	 * @return Recipe_Violation
	 */
	private function missing_live_trigger(): Recipe_Violation {
		return new Recipe_Violation(
			self::RECIPE_MISSING_LIVE_TRIGGER,
			esc_html_x( 'Add at least one live trigger.', 'Recipe violation message', 'uncanny-automator' )
		);
	}

	/**
	 * The missing-live-action structural violation.
	 *
	 * @return Recipe_Violation
	 */
	private function missing_live_action(): Recipe_Violation {
		return new Recipe_Violation(
			self::RECIPE_MISSING_LIVE_ACTION,
			esc_html_x( 'Add at least one live action.', 'Recipe violation message', 'uncanny-automator' )
		);
	}

	/**
	 * The missing-live-trigger structural violation when the recipe's trigger
	 * has no on / off switch and is waiting for its setup: switching something
	 * on is not the way to a live trigger, so the recipe's message says so.
	 *
	 * @return Recipe_Violation
	 */
	private function missing_live_trigger_until_setup(): Recipe_Violation {
		return new Recipe_Violation(
			self::RECIPE_MISSING_LIVE_TRIGGER,
			esc_html_x( 'This recipe has no live trigger yet. Its trigger goes live once it’s set up.', 'Recipe violation message', 'uncanny-automator' )
		);
	}

	/**
	 * The needs-setup violation, on the trigger that is waiting for its setup.
	 *
	 * @param int $trigger_id The trigger's id.
	 *
	 * @return Recipe_Violation
	 */
	private function trigger_needs_setup( int $trigger_id ): Recipe_Violation {
		return new Recipe_Violation(
			self::RECIPE_TRIGGER_NEEDS_SETUP,
			esc_html_x( 'Finish setting up this trigger.', 'Recipe violation message', 'uncanny-automator' ),
			new Recipe_Violation_Target( Wire_Node_Type::ITEM, $trigger_id )
		);
	}

	/**
	 * The first trigger that has no on / off switch and declares fields. It
	 * goes live on its first valid save, so the user's next step is to
	 * configure it. Only called when no trigger is live, so every trigger
	 * seen here is a draft. Null when none fits.
	 *
	 * @param array<string,mixed> $tree Recipe tree (Recipe_Tree_Builder shape).
	 *
	 * @return int|null The trigger's id.
	 */
	private function trigger_needing_setup( array $tree ): ?int {
		foreach ( (array) ( $tree['triggers'] ?? array() ) as $trigger ) {
			$code = is_array( $trigger ) ? (string) ( $trigger['code'] ?? '' ) : '';
			if ( '' === $code || $this->trigger_registry->has_status_toggle( $code ) ) {
				continue;
			}

			if ( ! $this->field_registry->declares_author_fields( (string) ( $trigger['integration_code'] ?? '' ), Integration_Item_Types::TRIGGER, $code ) ) {
				continue;
			}

			return (int) ( $trigger['id'] ?? 0 );
		}

		return null;
	}

	/**
	 * Whether the recipe tree has at least one published trigger.
	 *
	 * @param array<string,mixed> $tree Recipe tree (Recipe_Tree_Builder shape).
	 *
	 * @return bool
	 */
	private function tree_has_live_trigger( array $tree ): bool {
		foreach ( $tree['triggers'] ?? array() as $trigger ) {
			if ( true === ( $trigger['is_item_on'] ?? false ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the recipe tree has at least one published action, including
	 * actions nested inside block / loop paths.
	 *
	 * @param array<string,mixed> $tree Recipe tree (Recipe_Tree_Builder shape).
	 *
	 * @return bool
	 */
	private function tree_has_live_action( array $tree ): bool {
		return $this->entries_have_live_action( $tree['content'] ?? array() );
	}

	/**
	 * Recursively scan content entries for a published action. `action`
	 * entries count directly; block / loop entries are walked into their
	 * `paths[].items`. Block modifiers never count.
	 *
	 * @param array<int,array<string,mixed>> $entries Tree content entries.
	 *
	 * @return bool
	 */
	private function entries_have_live_action( array $entries ): bool {
		foreach ( $entries as $entry ) {

			if ( 'action' === ( $entry['kind'] ?? '' ) && true === ( $entry['is_item_on'] ?? false ) ) {
				return true;
			}

			foreach ( $entry['paths'] ?? array() as $path ) {
				if ( $this->entries_have_live_action( $path['items'] ?? array() ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * The required-fields message, per item type (full templates so
	 * translators see complete sentences).
	 *
	 * @param string $item_type 'trigger' | 'action'.
	 * @param string $name      Display name (already plain text).
	 *
	 * @return string
	 */
	private function required_fields_message( string $item_type, string $name ): string {
		if ( Integration_Item_Types::TRIGGER === $item_type ) {
			return sprintf(
				// translators: %s is the trigger's display name.
				esc_html_x( 'The trigger **%s** has empty required fields.', 'Recipe violation message', 'uncanny-automator' ),
				$name
			);
		}

		return sprintf(
			// translators: %s is the action's display name.
			esc_html_x( 'The action **%s** has empty required fields.', 'Recipe violation message', 'uncanny-automator' ),
			$name
		);
	}

	/**
	 * The integration-unavailable message. Prefers the availability
	 * resolver's own composed markdown (it names the blocking dependency);
	 * falls back to a generic item-naming template.
	 *
	 * @param string $resolver_message The resolver's `availability.message`, possibly empty.
	 * @param string $item_type        'trigger' | 'action'.
	 * @param string $name             Display name.
	 *
	 * @return string
	 */
	private function unavailable_message( string $resolver_message, string $item_type, string $name ): string {
		if ( '' !== $resolver_message ) {
			return $resolver_message;
		}

		if ( Integration_Item_Types::TRIGGER === $item_type ) {
			return sprintf(
				// translators: %s is the trigger's display name.
				esc_html_x( 'The trigger **%s** requires an integration that is unavailable on this site.', 'Recipe violation message', 'uncanny-automator' ),
				$name
			);
		}

		return sprintf(
			// translators: %s is the action's display name.
			esc_html_x( 'The action **%s** requires an integration that is unavailable on this site.', 'Recipe violation message', 'uncanny-automator' ),
			$name
		);
	}

	/**
	 * Display name for violation messages: the user's custom name when
	 * set; else the catalog sentence — `short` (the human-readable name
	 * the builder rows display) preferred over `dynamic` (the templated
	 * form), both stripped of their `{{option}}` / `{{option:CODE}}`
	 * wrappers; else the raw item code as the last resort.
	 *
	 * @param string|null $custom_name      User-set display name, if any.
	 * @param string      $integration_code Integration code.
	 * @param string      $code             Item code.
	 * @param string      $item_type        'trigger' | 'action'.
	 *
	 * @return string
	 */
	public function display_name( ?string $custom_name, string $integration_code, string $code, string $item_type ): string {
		if ( null !== $custom_name && '' !== trim( $custom_name ) ) {
			return trim( $custom_name );
		}

		$item  = $this->catalog->get_item( $integration_code, $code, $item_type );
		$plain = null === $item ? '' : $this->plain_sentence( $item->to_array()['sentence'] ?? array() );

		return '' === $plain ? $code : $plain;
	}

	/**
	 * A catalog sentence as plain text: `short` (the human-readable name the
	 * builder rows display) preferred over `dynamic` (the templated form),
	 * with `{{option}}` / `{{option:CODE}}` wrappers stripped. The catalog
	 * sentence is a `{ short, dynamic }` pair (see Integration_Item_Discoverer).
	 * '' when neither variant yields text.
	 *
	 * @param mixed $sentence The catalog sentence pair.
	 * @return string
	 */
	private function plain_sentence( $sentence ): string {
		foreach ( array( 'short', 'dynamic' ) as $variant ) {
			$candidate = is_array( $sentence ) ? ( $sentence[ $variant ] ?? '' ) : '';
			if ( ! is_string( $candidate ) || '' === $candidate ) {
				continue;
			}
			$plain = trim( (string) preg_replace( '/\{\{([^:}]*)(?::[^}]*)?\}\}/', '$1', $candidate ) );
			if ( '' !== $plain ) {
				return $plain;
			}
		}

		return '';
	}
}
