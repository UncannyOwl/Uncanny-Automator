<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe\Value_Objects;

use Uncanny_Automator\Api\Components\Trigger\Trigger;
use Uncanny_Automator\Api\Components\Shared\Enums\User_Type;

/**
 * Recipe Triggers Collection.
 *
 * Value object representing a collection of triggers within a recipe.
 * Enforces business rules:
 * - Anonymous recipes: maximum 1 trigger
 * - User recipes: multiple triggers allowed with logic
 *
 * @since 7.0.0
 */
class Recipe_Triggers {

	private array $triggers              = array();
	private ?Recipe_Trigger_Logic $logic = null;

	/**
	 * Constructor.
	 *
	 * @param array       $triggers Array of Trigger objects.
	 * @param string      $recipe_type Recipe type ('user' or 'anonymous').
	 * @param string|null $logic Trigger logic ('all' or 'any') - only for user recipes.
	 */
	public function __construct( array $triggers = array(), string $recipe_type = 'user', ?string $logic = null ) {
		$this->validate_triggers( $triggers );
		$this->enforce_business_rules( $triggers, $recipe_type, $logic );

		$this->triggers = $triggers;

		// Only user recipes can have trigger logic
		if ( 'user' === $recipe_type && count( $triggers ) > 1 ) {
			$this->logic = new Recipe_Trigger_Logic( $logic ?? 'all' );
		}
	}

	/**
	 * Get all triggers.
	 *
	 * @return array Array of Trigger objects.
	 */
	public function get_triggers(): array {
		return $this->triggers;
	}

	/**
	 * Get trigger logic.
	 *
	 * @return Recipe_Trigger_Logic|null
	 */
	public function get_logic(): ?Recipe_Trigger_Logic {
		return $this->logic;
	}

	/**
	 * Check if collection has triggers.
	 *
	 * @return bool
	 */
	public function has_triggers(): bool {
		return ! empty( $this->triggers );
	}

	/**
	 * Count triggers.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->triggers );
	}

	/**
	 * Get value (legacy compatibility).
	 *
	 * @return array
	 */
	public function get_value(): array {
		return $this->to_array();
	}

	/**
	 * To array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$triggers_data = array_map(
			function ( Trigger $trigger ) {
				return $trigger->to_array();
			},
			$this->triggers
		);

		$data = array(
			'triggers' => $triggers_data,
		);

		// Include logic only if it exists
		if ( $this->logic ) {
			$data['trigger_logic'] = $this->logic->get_value();
		}

		return $data;
	}

	/**
	 * Create from array.
	 *
	 * @param array  $data Array representation.
	 * @param string $recipe_type Recipe type.
	 * @return self
	 */
	public static function from_array( array $data, string $recipe_type ): self {
		$triggers = array();
		$logic    = $data['trigger_logic'] ?? null;

		// Convert trigger data to Trigger objects
		if ( ! empty( $data['triggers'] ) && is_array( $data['triggers'] ) ) {
			foreach ( $data['triggers'] as $trigger_data ) {
				if ( $trigger_data instanceof Trigger ) {
					$triggers[] = $trigger_data;
				}
				// Note: Trigger hydration from array would happen at service layer
			}
		}

		return new self( $triggers, $recipe_type, $logic );
	}

	/**
	 * Validate triggers array.
	 *
	 * @param array $triggers Array to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate_triggers( array $triggers ): void {
		foreach ( $triggers as $trigger ) {
			if ( ! $trigger instanceof Trigger ) {
				throw new \InvalidArgumentException( 'All items must be Trigger instances' );
			}
		}
	}

	/**
	 * Validate anonymous recipe trigger count.
	 *
	 * @param array  $triggers Array of triggers.
	 * @param string $recipe_type Recipe type.
	 * @throws \InvalidArgumentException If anonymous recipe has more than 1 trigger.
	 */
	public function validate_anonymous_trigger_count( array $triggers, string $recipe_type ): void {
		if ( User_Type::ANONYMOUS === $recipe_type && count( $triggers ) > 1 ) {
			throw new \InvalidArgumentException( 'Anonymous recipes can only have 1 trigger' );
		}
	}

	/**
	 * Validate anonymous recipe trigger logic.
	 *
	 * @param string      $recipe_type Recipe type.
	 * @param string|null $logic Trigger logic.
	 * @throws \InvalidArgumentException If anonymous recipe uses invalid logic.
	 */
	public function validate_anonymous_trigger_logic( string $recipe_type, ?string $logic ): void {
		if (
			User_Type::ANONYMOUS === $recipe_type
			&& null !== $logic
			&& Recipe_Trigger_Logic::LOGIC_ALL !== $logic
		) {
			throw new \InvalidArgumentException( 'Anonymous recipes can only use "all" trigger logic' );
		}
	}

	/**
	 * Validate trigger types match recipe type.
	 *
	 * @param array  $triggers Array of triggers.
	 * @param string $recipe_type Recipe type.
	 * @throws \InvalidArgumentException If trigger type doesn't match recipe type.
	 */
	public function validate_trigger_types_match_recipe( array $triggers, string $recipe_type ): void {
		foreach ( $triggers as $trigger ) {
			$trigger_type = $trigger->get_trigger_type()->get_value();
			if ( $trigger_type !== $recipe_type ) {
				throw new \InvalidArgumentException(
					"Trigger type '{$trigger_type}' does not match recipe type '{$recipe_type}'"
				);
			}
		}
	}

	/**
	 * Enforce business rules based on recipe type.
	 *
	 * @param array       $triggers Array of triggers.
	 * @param string      $recipe_type Recipe type.
	 * @param string|null $logic Trigger logic.
	 * @throws \InvalidArgumentException If rules violated.
	 */
	private function enforce_business_rules( array $triggers, string $recipe_type, ?string $logic ): void {
		$this->validate_anonymous_trigger_count( $triggers, $recipe_type );
		$this->validate_anonymous_trigger_logic( $recipe_type, $logic );
		$this->validate_trigger_types_match_recipe( $triggers, $recipe_type );
	}
}
