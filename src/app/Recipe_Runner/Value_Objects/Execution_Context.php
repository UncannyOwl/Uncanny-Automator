<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Value_Objects;

/**
 * Execution context passed through the tree walker to processors.
 *
 * Prevents parameter sprawl — new processors don't force signature changes.
 * Private properties + getters for PHP 7.4 compat (no readonly).
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Value_Objects
 * @since   7.3
 */
final class Execution_Context {

	/**
	 * @var int
	 */
	private $recipe_id;

	/**
	 * @var int
	 */
	private $user_id;

	/**
	 * @var int
	 */
	private $recipe_log_id;

	/**
	 * @var array
	 */
	private $args;

	/**
	 * @param int   $recipe_id     The recipe post ID.
	 * @param int   $user_id       The user ID.
	 * @param int   $recipe_log_id The recipe log ID.
	 * @param array $args          Pipeline args (run_number, trigger data, tokens, etc.).
	 */
	public function __construct( int $recipe_id, int $user_id, int $recipe_log_id, array $args ) {
		$this->recipe_id     = $recipe_id;
		$this->user_id       = $user_id;
		$this->recipe_log_id = $recipe_log_id;
		$this->args          = $args;
	}

	/**
	 * @return int
	 */
	public function get_recipe_id(): int {
		return $this->recipe_id;
	}

	/**
	 * @return int
	 */
	public function get_user_id(): int {
		return $this->user_id;
	}

	/**
	 * @return int
	 */
	public function get_recipe_log_id(): int {
		return $this->recipe_log_id;
	}

	/**
	 * @return array
	 */
	public function get_args(): array {
		return $this->args;
	}

	/**
	 * Array representation for processor interfaces.
	 *
	 * Processors receive this instead of the object so they don't
	 * depend on the Execution_Context class directly.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'recipe_id'     => $this->recipe_id,
			'user_id'       => $this->user_id,
			'recipe_log_id' => $this->recipe_log_id,
			'args'          => $this->args,
		);
	}
}
