<?php
/**
 * Alternative Triggers Info Value Object.
 *
 * Represents information about triggers that exist but are incompatible
 * with the current recipe type (e.g., anonymous triggers when searching
 * for a user recipe).
 *
 * @package Uncanny_Automator\Api\Components\Search\Trigger
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Search\Trigger;

/**
 * Value object representing alternative triggers info.
 */
class Alternative_Triggers_Info {

	/**
	 * Number of alternative triggers available.
	 *
	 * @var int
	 */
	private int $count;

	/**
	 * Recipe type required for these triggers ('user' or 'anonymous').
	 *
	 * @var string
	 */
	private string $recipe_type;

	/**
	 * Constructor.
	 *
	 * @param int    $count       Number of alternative triggers.
	 * @param string $recipe_type Recipe type required.
	 */
	public function __construct( int $count, string $recipe_type ) {
		$this->count       = $count;
		$this->recipe_type = $recipe_type;
	}

	/**
	 * Get the count of alternative triggers.
	 *
	 * @return int
	 */
	public function get_count(): int {
		return $this->count;
	}

	/**
	 * Get the recipe type required for these triggers.
	 *
	 * @return string
	 */
	public function get_recipe_type(): string {
		return $this->recipe_type;
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'count'       => $this->count,
			'recipe_type' => $this->recipe_type,
		);
	}

	/**
	 * Create from array data.
	 *
	 * @param array $data Array with 'count' and 'recipe_type' keys.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			(int) ( $data['count'] ?? 0 ),
			(string) ( $data['recipe_type'] ?? '' )
		);
	}
}
