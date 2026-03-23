<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe;

use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Title;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_User_Type;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Notes;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Throttle;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Times_Per_User;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Total_Times;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Action_Conditions;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Triggers;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Trigger_Logic;
use Uncanny_Automator\Api\Components\Shared\Enums\User_Type;
/**
 * Root Aggregate: Recipe
 *
 * This class models an Automator Recipe as a domain aggregate.
 *
 * Responsibilities:
 * - Enforces cross-object business invariants at construction time.
 * - Delegates primitive validation and normalization to Value Objects.
 * - Guarantees that no Recipe instance can exist in memory with broken
 *   domain rules about type, throttling, or execution limits.
 *
 * Domain rules:
 * - Recipe type must be either 'user' or 'anonymous'.
 * - User recipes:
 *   • Define both `times_per_user` (per-user limit) and `total_times` (global limit).
 *   • Require a throttle scope if throttling is enabled.
 * - Anonymous recipes:
 *   • Define only `total_times` (global limit).
 *   • Must not define a throttle scope, even if throttling is enabled.
 *
 * Enforcement split:
 * - Aggregate root: guarantees type-specific invariants and relationships
 *   between value objects (e.g., type vs. limits, throttle scope rules).
 * - Value Objects: guarantee validity of individual values
 *   (IDs, titles, status, notes length, throttle shape, etc.).
 *
 * This ensures:
 * - Invalid configurations are rejected immediately.
 * - Every Recipe instance is guaranteed valid by definition.
 * - Downstream code (UI, persistence, sync, AI adapters) can safely assume
 *   Recipe integrity without re-checking.
 *
 * WARNING:
 * Do not move cross-object validation out of this class. Only the aggregate
 * can enforce invariants between value objects. Value Objects handle their
 * own internal constraints.
 */
class Recipe {

	/**
	 * Recipe title - required domain value.
	 *
	 * Encapsulates title validation (max 200 chars, sanitization handled at service layer).
	 * Cannot be null as every recipe must have a meaningful title.
	 *
	 * @var Recipe_Title
	 */
	private Recipe_Title $recipe_title;

	/**
	 * Recipe publication status - required domain value.
	 *
	 * Enforces valid states: 'draft' or 'publish' only.
	 * Trash status removed from domain - recipes are either being worked on or live.
	 *
	 * @var Recipe_Status
	 */
	private Recipe_Status $recipe_status;

	/**
	 * Recipe execution type - required domain value.
	 *
	 * Enforces valid types: 'user' (logged-in) or 'anonymous' (any visitor).
	 * This drives validation of times_per_user vs total_times fields.
	 *
	 * @var Recipe_User_Type
	 */
	private Recipe_User_Type $recipe_type;

	/**
	 * Recipe trigger logic - optional domain value.
	 *
	 * Enforces valid logic values: 'all' (AND) or 'any' (OR).
	 *
	 * @var Recipe_Trigger_Logic|null
	 */
	private ?Recipe_Trigger_Logic $recipe_trigger_logic = null;

	/**
	 * Recipe unique identifier - nullable for new recipe modeling.
	 *
	 * Can be null when creating new recipes that haven't been persisted yet.
	 * Once saved, this becomes the immutable primary key.
	 *
	 * @var Recipe_Id|null
	 */
	private ?Recipe_Id $recipe_id = null;

	/**
	 * Recipe description/notes - optional domain value.
	 *
	 * Free-form text with length limits (max 10,000 chars).
	 * Sanitization handled at service layer, validation at domain level.
	 *
	 * @var Recipe_Notes|null
	 */
	private ?Recipe_Notes $recipe_notes = null;

	/**
	 * Recipe execution throttling settings - optional domain value.
	 *
	 * Controls how frequently recipes can run. Validation rules:
	 * - User recipes: require scope when enabled
	 * - Anonymous recipes: cannot have scope
	 *
	 * @var Recipe_Throttle|null
	 */
	private ?Recipe_Throttle $recipe_throttle = null;

	/**
	 * Per-user execution limits - only for 'user' type recipes.
	 *
	 * Defines how many times each individual user can trigger this recipe.
	 * Mutually exclusive with total_times (anonymous recipes).
	 *
	 * @var Recipe_Times_Per_User|null
	 */
	private ?Recipe_Times_Per_User $recipe_times_per_user = null;

	/**
	 * Global execution limits - only for 'anonymous' type recipes.
	 *
	 * Defines total executions across all users/visitors.
	 * Mutually exclusive with times_per_user (user recipes).
	 *
	 * @var Recipe_Total_Times|null
	 */
	private ?Recipe_Total_Times $recipe_total_times = null;


	/**
	 * Recipe action conditions collection - optional domain aggregate.
	 *
	 * Contains condition groups that control when actions execute.
	 * Each condition group applies to specific actions and uses
	 * AND/OR logic to evaluate multiple conditions.
	 *
	 * @var Recipe_Action_Conditions|null
	 */
	private ?Recipe_Action_Conditions $recipe_action_conditions = null;

	/**
	 * Recipe trigger collection - optional domain aggregate.
	 *
	 * Contains all triggers that can activate this recipe.
	 * Triggers determine when and how the recipe executes.
	 *
	 * @var Recipe_Triggers|null
	 */
	private ?Recipe_Triggers $recipe_triggers = null;

	/**
	 * Recipe configuration - optional domain value.
	 *
	 * @var Recipe_Config|null
	 */
	private ?Recipe_Config $config = null;

	/**
	 * Construct a validated Recipe domain aggregate.
	 *
	 * Enforces cross-object business invariants at construction time while
	 * delegating primitive validation to Value Objects. Guarantees no Recipe
	 * instance can exist in memory with broken domain rules.
	 *
	 * The Recipe_Config acts as a dumb data container, holding raw input data.
	 * This constructor wraps that data into validated Value Objects and enforces
	 * relationships between them (type-dependent limits, throttle scope rules).
	 *
	 * Domain invariants enforced:
	 * - Recipe type: 'user' or 'anonymous' only
	 * - User recipes: both per-user and global limits, throttle scope required when enabled
	 * - Anonymous recipes: global limits only, no throttle scope allowed
	 *
	 * Value Objects handle individual constraints (ID format, title length, etc.).
	 * This aggregate handles relationships between Value Objects.
	 *
	 * @since 7.0.0
	 *
	 * @param Recipe_Config $config Raw configuration data container.
	 * @throws \InvalidArgumentException If cross-object domain rules are violated.
	 */
	public function __construct( Recipe_Config $config ) {

		// Early validation of recipe type to fail fast
		$this->validate_recipe_type( $config->get_user_type() );

		// Initialize core value objects
		$this->initialize_core_properties( $config );

		// Validate trigger logic
		$this->validate_trigger_logic( $config->get_trigger_logic() );

		// Initialize collections
		$this->initialize_collections( $config );

		// Initialize execution limits based on type
		$this->initialize_execution_limits( $config );

		// Validate throttle rules based on recipe type
		$this->validate_throttle_rules( $config->get_user_type() );

		$this->config = $config;
	}

	/**
	 * Get recipe configuration.
	 *
	 * @since 7.0.0
	 * @return Recipe_Config Recipe configuration.
	 */
	public function get_config(): Recipe_Config {
		return $this->config;
	}

	/**
	 * Validate recipe type is allowed.
	 *
	 * @since 7.0.0
	 * @param string $recipe_type Recipe type to validate.
	 * @throws \InvalidArgumentException If recipe type is invalid.
	 */
	private function validate_recipe_type( string $recipe_type ): void {
		if ( ! in_array( $recipe_type, array( 'user', 'anonymous' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid recipe type: ' . $recipe_type );
		}
	}

	/**
	 * Validate trigger logic.
	 *
	 * @since 7.0.0
	 * @param string $trigger_logic Trigger logic to validate.
	 * @throws \InvalidArgumentException If trigger logic is invalid.
	 */
	private function validate_trigger_logic( string $trigger_logic ): void {
		// Anonymous recipes cannot have "Any" trigger logic.
		if ( User_Type::ANONYMOUS === $this->recipe_type->get_value() && Recipe_Trigger_Logic::LOGIC_ANY === $trigger_logic ) {
			throw new \InvalidArgumentException( 'Anonymous recipes cannot have "Any" trigger logic' );
		}
	}

	/**
	 * Initialize core recipe properties.
	 *
	 * @since 7.0.0
	 * @param Recipe_Config $config Recipe configuration.
	 */
	private function initialize_core_properties( Recipe_Config $config ): void {

		$this->recipe_id            = new Recipe_Id( $config->get_id() );
		$this->recipe_title         = new Recipe_Title( $config->get_title() );
		$this->recipe_status        = new Recipe_Status( $config->get_status() );
		$this->recipe_type          = new Recipe_User_Type( $config->get_user_type() );
		$this->recipe_notes         = new Recipe_Notes( $config->get_notes() );
		$this->recipe_trigger_logic = new Recipe_Trigger_Logic( $config->get_trigger_logic() );
		// Let Recipe_Throttle handle validation and default values
		$this->recipe_throttle = Recipe_Throttle::from_array( $config->get_throttle() );
	}

	/**
	 * Initialize recipe collections.
	 *
	 * @since 7.0.0
	 * @param Recipe_Config $config Recipe configuration.
	 */
	private function initialize_collections( Recipe_Config $config ): void {

		// Initialize recipe triggers.
		$this->recipe_triggers = Recipe_Triggers::from_array( $config->get_triggers(), $config->get_user_type() );

		// Let domain objects handle validation and default values
		$this->recipe_action_conditions = Recipe_Action_Conditions::from_array( $config->get_action_conditions() );
	}

	/**
	 * Initialize execution limits based on recipe type.
	 *
	 * @since 7.0.0
	 * @param Recipe_Config $config Recipe configuration.
	 */
	private function initialize_execution_limits( Recipe_Config $config ): void {
		$recipe_type = $config->get_user_type();

		// Both types need total_times
		$this->recipe_total_times = new Recipe_Total_Times( $config->get_total_times() );

		// Only user type needs times_per_user
		if ( 'user' === $recipe_type ) {
			$this->recipe_times_per_user = new Recipe_Times_Per_User( $config->get_times_per_user() );
		}
	}

	/**
	 * Validate throttle rules based on recipe type.
	 *
	 * @since 7.0.0
	 * @param string $recipe_type Recipe type.
	 * @throws \InvalidArgumentException If throttle rules are violated.
	 */
	private function validate_throttle_rules( string $recipe_type ): void {

		$throttle_data = $this->recipe_throttle->to_array();

		// Skip validation if throttling is disabled
		if ( ! $throttle_data['enabled'] ) {
			return;
		}

		// User recipes require throttle scope when enabled
		if ( 'user' === $recipe_type && empty( $throttle_data['scope'] ) ) {
			throw new \InvalidArgumentException( 'User recipes require throttle scope when throttling is enabled' );
		}

		// Anonymous recipes cannot have throttle scope
		if ( 'anonymous' === $recipe_type && ! empty( $throttle_data['scope'] && 'user' === $throttle_data['scope'] ) ) {
			throw new \InvalidArgumentException( 'Anonymous recipes cannot have throttle scope of user' );
		}
	}

	/**
	 * Get recipe unique identifier.
	 *
	 * Returns the recipe's primary key. May be null for new recipes
	 * that haven't been persisted to storage yet.
	 *
	 * @since 7.0.0
	 * @return Recipe_Id|null Recipe identifier value object or null for new recipes.
	 */
	public function get_recipe_id(): ?Recipe_Id {
		return $this->recipe_id;
	}

	/**
	 * Get recipe title.
	 *
	 * Returns the validated recipe title. Always present as titles are required.
	 * Title validation (max 200 chars) is enforced by the value object.
	 *
	 * @since 7.0.0
	 * @return Recipe_Title Recipe title value object, never null.
	 */
	public function get_recipe_title(): Recipe_Title {
		return $this->recipe_title;
	}

	/**
	 * Get recipe publication status.
	 *
	 * Returns current publication state. Always 'draft' or 'publish' -
	 * trash status is not part of the domain model.
	 *
	 * @since 7.0.0
	 * @return Recipe_Status Recipe status value object, never null.
	 */
	public function get_recipe_status(): Recipe_Status {
		return $this->recipe_status;
	}

	/**
	 * Get recipe execution type.
	 *
	 * Returns whether recipe runs for logged-in users ('user') or any visitor ('anonymous').
	 * This determines which execution limit fields are valid.
	 *
	 * @since 7.0.0
	 * @return Recipe_User_Type Recipe type value object, never null.
	 */
	public function get_recipe_type(): Recipe_User_Type {
		return $this->recipe_type;
	}

	/**
	 * Get recipe trigger logic.
	 *
	 * @since 7.0.0
	 * @return Recipe_Trigger_Logic|null Recipe trigger logic value object or null if not set.
	 */
	public function get_recipe_trigger_logic(): ?Recipe_Trigger_Logic {
		return $this->recipe_trigger_logic;
	}

	/**
	 * Get recipe description/notes.
	 *
	 * Returns optional free-form description text. May be null if no notes provided.
	 * When present, enforces max 10,000 character limit.
	 *
	 * @since 7.0.0
	 * @return Recipe_Notes|null Recipe notes value object or null if not set.
	 */
	public function get_recipe_notes(): ?Recipe_Notes {
		return $this->recipe_notes;
	}

	/**
	 * Get recipe throttling configuration.
	 *
	 * Returns throttling settings that control execution frequency.
	 * Cross-object validation rules (scope requirements) are enforced
	 * by the domain aggregate based on recipe type.
	 *
	 * @since 7.0.0
	 * @return Recipe_Throttle|null Recipe throttle value object or null if not configured.
	 */
	public function get_recipe_throttle(): ?Recipe_Throttle {
		return $this->recipe_throttle;
	}

	/**
	 * Get per-user execution limits.
	 *
	 * Returns execution limits for individual users. Only valid for 'user' type recipes.
	 * Type-specific limit enforcement is a cross-object business invariant
	 * managed by the domain aggregate.
	 *
	 * @since 7.0.0
	 * @return Recipe_Times_Per_User|null Per-user limits or null for anonymous recipes.
	 */
	public function get_recipe_times_per_user(): ?Recipe_Times_Per_User {
		return $this->recipe_times_per_user;
	}

	/**
	 * Get global execution limits.
	 *
	 * Returns total execution limits across all users. Only valid for 'anonymous' type recipes.
	 * Type-specific limit enforcement is a cross-object business invariant
	 * managed by the domain aggregate.
	 *
	 * @since 7.0.0
	 * @return Recipe_Total_Times|null Global limits or null for user recipes.
	 */
	public function get_recipe_total_times(): ?Recipe_Total_Times {
		return $this->recipe_total_times;
	}

	/**
	 * Get recipe trigger collection.
	 *
	 * Returns all triggers that can activate this recipe. Triggers define
	 * the conditions under which the recipe executes.
	 *
	 * @since 7.0.0
	 * @return Recipe_Triggers|null Trigger collection or null if no triggers configured.
	 */
	public function get_recipe_triggers(): ?Recipe_Triggers {
		return $this->recipe_triggers;
	}

	/**
	 * Get action conditions collection.
	 *
	 * Returns condition groups that control when actions execute.
	 * Domain aggregate ensures collection integrity while delegating
	 * individual condition validation to the value object.
	 *
	 * @since 7.0.0
	 * @return Recipe_Action_Conditions|null Action conditions collection or null if not set.
	 */
	public function get_recipe_action_conditions(): ?Recipe_Action_Conditions {
		return $this->recipe_action_conditions;
	}

	/**
	 * Convert recipe to complete array representation.
	 *
	 * Returns all recipe data as associative array for persistence, API responses,
	 * or serialization. Includes all domain properties in their raw forms.
	 *
	 * Type-specific serialization behavior enforced by domain aggregate:
	 * - User recipes include 'times_per_user', exclude 'total_times'
	 * - Anonymous recipes include 'total_times', exclude 'times_per_user'
	 *
	 * @since 7.0.0
	 * @return array Complete recipe data with all domain properties.
	 */
	public function to_array(): array {
		$data = array(
			'recipe_id'     => $this->recipe_id->get_value(),
			'recipe_title'  => $this->recipe_title->get_value(),
			'recipe_status' => $this->recipe_status->get_value(),
			'recipe_type'   => $this->recipe_type->get_value(),
			'notes'         => $this->recipe_notes->get_value(),
			'trigger_logic' => $this->recipe_trigger_logic->get_value(),
			'throttle'      => $this->recipe_throttle->to_array(),
		);

		// Add type-specific execution limit fields
		if ( User_Type::USER === $this->recipe_type->get_value() && $this->recipe_times_per_user ) {
			$data['times_per_user'] = $this->recipe_times_per_user->get_value();
			$data['total_times']    = $this->recipe_total_times->get_value();
		}

		if ( User_Type::ANONYMOUS === $this->recipe_type->get_value() && $this->recipe_total_times ) {
			$data['total_times'] = $this->recipe_total_times->get_value();
		}

		return $data;
	}

	/**
	 * Convert recipe to basic array representation.
	 *
	 * Returns only essential information for listing and summary operations.
	 * Framework-agnostic - no WordPress dependencies like edit links.
	 *
	 * Use cases:
	 * - MCP tool responses (lightweight data transfer)
	 * - API listing endpoints (performance optimization)
	 * - Recipe browser/picker interfaces (minimal UI data)
	 *
	 * @since 7.0.0
	 * @return array Basic recipe data containing only id, title, and status.
	 */
	public function to_basic_array(): array {
		return array(
			'id'     => $this->recipe_id->get_value(),
			'title'  => $this->recipe_title->get_value(),
			'status' => $this->recipe_status->get_value(),
		);
	}
}
