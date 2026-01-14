<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe;

use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Trigger_Logic;

/**
 * Recipe Configuration - Dumb Data Container.
 *
 * Pure data transfer object that shuttles raw configuration data to the Recipe aggregate.
 * This is intentionally DUMB - no validation, no business logic, no intelligence.
 *
 * Purpose:
 * - Collect raw recipe data from various sources (UI forms, API calls, database)
 * - Provide fluent builder interface for constructing recipe parameters
 * - Pass validated data to Recipe aggregate constructor for domain validation
 *
 * Anti-patterns avoided:
 * - No validation logic (Recipe aggregate handles this)
 * - No business rules (Recipe aggregate enforces these)
 * - No formatting/transformation (value objects handle this)
 *
 * The Recipe aggregate is the smart one. This is just a messenger.
 *
 * @since 7.0.0
 */
class Recipe_Config {

	/**
	 * Raw recipe title - no validation.
	 *
	 * Passed directly to Recipe_Title value object for validation.
	 * Can be null, empty, or invalid - Recipe aggregate will handle it.
	 *
	 * @var mixed
	 */
	private $title;

	/**
	 * Raw recipe status - no validation.
	 *
	 * Passed directly to Recipe_Status value object for enum validation.
	 * Can be invalid status - Recipe aggregate will reject it.
	 *
	 * @var mixed
	 */
	private $status;

	/**
	 * Raw recipe ID - no validation.
	 *
	 * Passed directly to Recipe_Id value object for validation.
	 * Can be null for new recipes or invalid format.
	 *
	 * @var mixed
	 */
	private $id;

	/**
	 * Raw recipe type - no validation.
	 *
	 * Passed directly to Recipe_User_Type value object for enum validation.
	 * Can be invalid type - Recipe aggregate will enforce 'user'/'anonymous' only.
	 *
	 * @var mixed
	 */
	private $type;

	/**
	 * Raw recipe notes - no validation.
	 *
	 * Default empty string. Passed to Recipe_Notes value object for length validation.
	 * Can exceed limits - domain will enforce 10,000 char max.
	 *
	 * @var string
	 */
	private $notes = '';

	/**
	 * Raw throttle configuration - no validation.
	 *
	 * Default disabled throttle. Passed to Recipe_Throttle value object.
	 * Can have invalid structure - Recipe aggregate validates scope rules by type.
	 *
	 * @var array
	 */
	private $throttle = array(
		'enabled'  => false,
		'duration' => 1,
		'unit'     => 'hours',
	);

	/**
	 * Raw per-user execution limit - no validation.
	 *
	 * Default 1 execution per user. Only used for 'user' type recipes.
	 * Can be invalid number - Recipe_Times_Per_User value object validates.
	 *
	 * @var int
	 */
	private $times_per_user = null;

	/**
	 * Raw total execution limit - no validation.
	 *
	 * Default 1 total execution. Only used for 'anonymous' type recipes.
	 * Can be invalid number - Recipe_Total_Times value object validates.
	 *
	 * @var int
	 */
	private $total_times = null;

	/**
	 * Raw triggers array - no validation.
	 *
	 * Default empty array. Passed to Recipe_Triggers value object.
	 * Can have malformed trigger data - Recipe aggregate validates structure.
	 *
	 * @var array
	 */
	private $triggers = array();

	/**
	 * Raw action conditions array - no validation.
	 *
	 * Default empty array. Passed to Recipe_Action_Conditions value object.
	 * Can have malformed condition data - Recipe aggregate validates structure.
	 *
	 * @var array
	 */
	private $action_conditions = array();

	/**
	 * Raw trigger logic - no validation.
	 *
	 * Default null. Passed to Recipe_Trigger_Logic value object.
	 * Can be invalid logic - Recipe aggregate validates logic.
	 *
	 * @var string
	 */
	private $trigger_logic = null;

	/**
	 * Redirect URL for closure - no validation.
	 *
	 * Optional URL where users are redirected when recipe completes.
	 * When set, automatically creates/updates a REDIRECT closure.
	 * Validation handled by Closure_Service.
	 *
	 * @var string|null
	 */
	private $redirect_url = null;

	/**
	 * Set recipe title - fluent interface.
	 *
	 * Accepts any value without validation. Recipe aggregate will validate.
	 * Part of builder pattern for constructing configuration objects.
	 *
	 * @since 7.0.0
	 * @param mixed $title Raw title data - can be invalid.
	 * @return self For method chaining.
	 */
	public function title( $title ): self {
		$this->title = $title;
		return $this;
	}

	/**
	 * Set recipe trigger logic - fluent interface.
	 *
	 * Accepts any value without validation. Recipe_Trigger_Logic will validate logic.
	 * Part of builder pattern for constructing configuration objects.
	 *
	 * @since 7.0.0
	 * @param mixed $trigger_logic Raw trigger logic data - can be invalid.
	 * @return self For method chaining.
	 */
	public function trigger_logic( $trigger_logic ): self {
		$this->trigger_logic = $trigger_logic;
		return $this;
	}

	/**
	 * Set recipe status - fluent interface.
	 *
	 * Accepts any value without validation. Recipe_Status will enforce enum.
	 * Part of builder pattern for constructing configuration objects.
	 *
	 * @since 7.0.0
	 * @param mixed $status Raw status data - can be invalid.
	 * @return self For method chaining.
	 */
	public function status( $status ): self {
		$this->status = $status;
		return $this;
	}

	/**
	 * Set recipe ID - fluent interface.
	 *
	 * Accepts any value without validation. Recipe_Id will validate format.
	 * Part of builder pattern for constructing configuration objects.
	 *
	 * @since 7.0.0
	 * @param mixed $id Raw ID data - can be null or invalid.
	 * @return self For method chaining.
	 */
	public function id( $id ): self {
		$this->id = $id;
		return $this;
	}

	/**
	 * Set recipe type - fluent interface.
	 *
	 * Accepts any value without validation. Recipe_User_Type will enforce 'user'/'anonymous'.
	 * Part of builder pattern for constructing configuration objects.
	 *
	 * @since 7.0.0
	 * @param mixed $type Raw type data - can be invalid.
	 * @return self For method chaining.
	 */
	public function user_type( $type ): self {
		$this->type = $type;
		return $this;
	}

	/**
	 * Set recipe notes - fluent interface.
	 *
	 * Accepts any value without validation. Recipe_Notes will enforce length limits.
	 * Part of builder pattern for constructing configuration objects.
	 *
	 * @since 7.0.0
	 * @param mixed $notes Raw notes data - can exceed limits.
	 * @return self For method chaining.
	 */
	public function notes( $notes ): self {
		$this->notes = $notes;
		return $this;
	}

	/**
	 * Set throttle configuration - fluent interface.
	 *
	 * Accepts any value without validation. Recipe aggregate validates scope rules.
	 * Part of builder pattern for constructing configuration objects.
	 *
	 * @since 7.0.0
	 * @param mixed $throttle Raw throttle data - can have invalid structure.
	 * @return self For method chaining.
	 */
	public function throttle( $throttle ): self {
		$this->throttle = $throttle;
		return $this;
	}

	/**
	 * Set per-user execution limits - fluent interface.
	 *
	 * Accepts any value without validation. Recipe_Times_Per_User validates numbers.
	 * Only meaningful for 'user' type recipes.
	 *
	 * @since 7.0.0
	 * @param mixed $times_per_user Raw limit data - can be invalid number.
	 * @return self For method chaining.
	 */
	public function times_per_user( $times_per_user ): self {
		$this->times_per_user = $times_per_user;
		return $this;
	}

	/**
	 * Set total execution limits - fluent interface.
	 *
	 * Accepts any value without validation. Recipe_Total_Times validates numbers.
	 * Only meaningful for 'anonymous' type recipes.
	 *
	 * @since 7.0.0
	 * @param mixed $total_times Raw limit data - can be invalid number.
	 * @return self For method chaining.
	 */
	public function total_times( $total_times ): self {
		$this->total_times = $total_times;
		return $this;
	}

	/**
	 * Set triggers configuration - fluent interface.
	 *
	 * Accepts any array without validation. Recipe_Triggers validates structure.
	 * Part of builder pattern for constructing configuration objects.
	 *
	 * @since 7.0.0
	 * @param array $triggers Raw triggers data - can have malformed structure.
	 * @return self For method chaining.
	 */
	public function triggers( array $triggers ): self {
		$this->triggers = $triggers;
		return $this;
	}

	/**
	 * Set action conditions configuration - fluent interface.
	 *
	 * Accepts any array without validation. Recipe_Action_Conditions validates structure.
	 * Part of builder pattern for constructing configuration objects.
	 *
	 * @since 7.0.0
	 * @param array $action_conditions Raw action conditions data - can have malformed structure.
	 * @return self For method chaining.
	 */
	public function action_conditions( array $action_conditions ): self {
		$this->action_conditions = $action_conditions;
		return $this;
	}

	/**
	 * Set redirect URL for closure - fluent interface.
	 *
	 * Accepts any value without validation. Closure_Service validates URL format.
	 * Optional field for specifying completion redirect target.
	 * Part of builder pattern for constructing configuration objects.
	 *
	 * @since 7.0.0
	 * @param mixed $redirect_url Raw URL data - can be null or invalid format.
	 * @return self For method chaining.
	 */
	public function redirect_url( $redirect_url ): self {
		$this->redirect_url = $redirect_url;
		return $this;
	}

	/**
	 * Get raw title data.
	 *
	 * Returns unvalidated title data exactly as stored.
	 * Used by Recipe aggregate for creating Recipe_Title value object.
	 *
	 * @since 7.0.0
	 * @return mixed Raw title data - may be null or invalid.
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Get raw status data.
	 *
	 * Returns unvalidated status data exactly as stored.
	 * Used by Recipe aggregate for creating Recipe_Status value object.
	 *
	 * @since 7.0.0
	 * @return mixed Raw status data - may be invalid enum value.
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Get raw ID data.
	 *
	 * Returns unvalidated ID data exactly as stored.
	 * Used by Recipe aggregate for creating Recipe_Id value object.
	 *
	 * @since 7.0.0
	 * @return mixed Raw ID data - may be null or invalid format.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get raw type data.
	 *
	 * Returns unvalidated type data exactly as stored.
	 * Used by Recipe aggregate for creating Recipe_User_Type value object.
	 *
	 * @since 7.0.0
	 * @return mixed Raw type data - may be invalid enum value.
	 */
	public function get_user_type() {
		return $this->type;
	}

	/**
	 * Get raw notes data.
	 *
	 * Returns unvalidated notes data exactly as stored.
	 * Used by Recipe aggregate for creating Recipe_Notes value object.
	 *
	 * @since 7.0.0
	 * @return string Raw notes data - may exceed length limits.
	 */
	public function get_notes(): string {
		return $this->notes;
	}

	/**
	 * Get raw throttle data.
	 *
	 * Returns unvalidated throttle data exactly as stored.
	 * Used by Recipe aggregate for creating Recipe_Throttle value object.
	 *
	 * @since 7.0.0
	 * @return array Raw throttle data - may have invalid structure.
	 */
	public function get_throttle(): array {
		return $this->throttle;
	}

	/**
	 * Get raw per-user limits data.
	 *
	 * Returns unvalidated times per user data exactly as stored.
	 * Used by Recipe aggregate for creating Recipe_Times_Per_User value object.
	 *
	 * @since 7.0.0
	 * @return int Raw limit data - may be invalid number.
	 */
	public function get_times_per_user(): ?int {
		if ( empty( $this->times_per_user ) ) {
			return null;
		}

		return $this->times_per_user;
	}

	/**
	 * Get raw total limits data.
	 *
	 * Returns unvalidated total times data exactly as stored.
	 * Used by Recipe aggregate for creating Recipe_Total_Times value object.
	 *
	 * @since 7.0.0
	 * @return null|int Raw limit data - may be invalid number.
	 */
	public function get_total_times(): ?int {
		if ( empty( $this->total_times ) ) {
			return null;
		}

		return $this->total_times;
	}

	/**
	 * Get raw triggers data.
	 *
	 * Returns unvalidated triggers data exactly as stored.
	 * Used by Recipe aggregate for creating Recipe_Triggers value object.
	 *
	 * @since 7.0.0
	 * @return array Raw triggers data - may have malformed structure.
	 */
	public function get_triggers(): array {
		return $this->triggers;
	}

	/**
	 * Get raw action conditions data.
	 *
	 * Returns unvalidated action conditions data exactly as stored.
	 * Used by Recipe aggregate for creating Recipe_Action_Conditions value object.
	 *
	 * @since 7.0.0
	 * @return array Raw action conditions data - may have malformed structure.
	 */
	public function get_action_conditions(): array {
		return $this->action_conditions;
	}

	/**
	 * Get raw trigger logic data.
	 *
	 * Returns unvalidated trigger logic data exactly as stored and normalizes
	 * missing/empty values to the domain default ("all").
	 *
	 * @since 7.0.0
	 * @return string Raw trigger logic data with domain default applied.
	 */
	public function get_trigger_logic(): string {

		if ( ! is_string( $this->trigger_logic ) ) {
			return Recipe_Trigger_Logic::LOGIC_ALL;
		}

		$logic = trim( $this->trigger_logic );

		if ( '' === $logic ) {
			return Recipe_Trigger_Logic::LOGIC_ALL;
		}

		return $logic;
	}

	/**
	 * Get raw redirect URL data.
	 *
	 * Returns unvalidated redirect URL data exactly as stored.
	 * Used by Recipe_CRUD_Service for managing closure redirects.
	 *
	 * @since 7.0.0
	 * @return string|null Raw redirect URL data - may be null or invalid format.
	 */
	public function get_redirect_url(): ?string {
		return ! empty( $this->redirect_url ) ? (string) $this->redirect_url : null;
	}

	/**
	 * Convert to array representation.
	 *
	 * Returns all raw configuration data as associative array.
	 * Used for debugging, logging, or passing to Recipe aggregate constructor.
	 *
	 * No validation is performed - data returned exactly as stored.
	 * Recipe aggregate is responsible for validating this data.
	 *
	 * @since 7.0.0
	 * @return array All raw configuration data without validation.
	 */
	public function to_array(): array {
		return array(
			'id'             => $this->id,
			'title'          => $this->title,
			'status'         => $this->status,
			'type'           => $this->type,
			'notes'          => $this->notes,
			'throttle'       => $this->throttle,
			'times_per_user' => $this->times_per_user,
			'total_times'    => $this->total_times,
			'trigger_logic'  => $this->trigger_logic,
		);
	}
}
