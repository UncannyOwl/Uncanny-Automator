<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Value_Objects;

use Uncanny_Automator\App\Recipe_Runner\Background\Background_Wait_Set;

/**
 * Execution context passed through the tree walker to processors.
 *
 * Prevents parameter sprawl — new processors don't force signature changes.
 * Private properties + getters for PHP 7.4 compat (no readonly).
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Value_Objects
 */
final class Execution_Context {

	/**
	 * Recipe post id.
	 *
	 * @var int
	 */
	private $recipe_id;

	/**
	 * User the run executes as (the entity's user in loop scope).
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * The run's recipe-log row id.
	 *
	 * @var int
	 */
	private $recipe_log_id;

	/**
	 * Trigger args; carries `loop` in loop scope.
	 *
	 * @var array<string, mixed>
	 */
	private $args;

	/**
	 * `action_id => action_log_id` for the rows created at run start.
	 *
	 * @var array<int, int>
	 */
	private $action_log_ids;

	/**
	 * Action ids the walker skips: terminal rows on resume, already-completed entity actions in a loop.
	 *
	 * @var array<int, true>
	 */
	private $completed_action_ids;

	/**
	 * The run's unfinished background work as this walk sees it.
	 *
	 * @var Background_Wait_Set
	 */
	private $wait_set;

	/**
	 * Whether this walk is a background continuation of a detached run. A continuation never detaches again.
	 *
	 * @var bool
	 */
	private $is_continuation;

	/**
	 * Build the context for one walk.
	 *
	 * @param int                      $recipe_id            Recipe post id.
	 * @param int                      $user_id              User the run executes as.
	 * @param int                      $recipe_log_id        Recipe-log row id.
	 * @param array<string, mixed>     $args                 Trigger args.
	 * @param array<int, int>          $action_log_ids       `action_id => action_log_id` map.
	 * @param array<int, true>         $completed_action_ids Action ids to skip.
	 * @param Background_Wait_Set|null $wait_set             Unfinished background work; empty on a fresh run.
	 * @param bool                     $is_continuation      Whether this walk resumes a detached run.
	 */
	public function __construct( int $recipe_id, int $user_id, int $recipe_log_id, array $args, array $action_log_ids = array(), array $completed_action_ids = array(), ?Background_Wait_Set $wait_set = null, bool $is_continuation = false ) {
		$this->recipe_id            = $recipe_id;
		$this->user_id              = $user_id;
		$this->recipe_log_id        = $recipe_log_id;
		$this->args                 = $args;
		$this->action_log_ids       = $action_log_ids;
		$this->completed_action_ids = $completed_action_ids;
		$this->wait_set             = $wait_set ?? new Background_Wait_Set();
		$this->is_continuation      = $is_continuation;
	}

	/**
	 * Whether this walk resumes a detached run and so must never detach again.
	 *
	 * @return bool
	 */
	public function is_continuation(): bool {
		return $this->is_continuation;
	}

	/**
	 * Recipe post id.
	 *
	 * @return int
	 */
	public function get_recipe_id(): int {
		return $this->recipe_id;
	}

	/**
	 * User the run executes as.
	 *
	 * @return int
	 */
	public function get_user_id(): int {
		return $this->user_id;
	}

	/**
	 * Recipe-log row id.
	 *
	 * @return int
	 */
	public function get_recipe_log_id(): int {
		return $this->recipe_log_id;
	}

	/**
	 * Trigger args.
	 *
	 * @return array<string, mixed>
	 */
	public function get_args(): array {
		return $this->args;
	}

	/**
	 * The run's `action_id => action_log_id` map.
	 *
	 * @return array<int, int>
	 */
	public function get_action_log_ids(): array {
		return $this->action_log_ids;
	}

	/**
	 * The row created at run start for an action, or null when it has none.
	 *
	 * @param int $action_id Action post id.
	 *
	 * @return int|null
	 */
	public function action_log_id_for( int $action_id ): ?int {
		return $this->action_log_ids[ $action_id ] ?? null;
	}

	/**
	 * Whether the walker must skip this action.
	 *
	 * @param int $action_id Action post id.
	 *
	 * @return bool
	 */
	public function is_completed( int $action_id ): bool {
		return isset( $this->completed_action_ids[ $action_id ] );
	}

	/**
	 * Whether this walk runs one loop entity: `args['loop']` is present.
	 *
	 * @return bool
	 */
	public function is_loop_scoped(): bool {
		return isset( $this->args['loop'] ) && is_array( $this->args['loop'] );
	}

	/**
	 * The run's unfinished background work.
	 *
	 * @return Background_Wait_Set
	 */
	public function wait_set(): Background_Wait_Set {
		return $this->wait_set;
	}

	/**
	 * Array representation for processor interfaces.
	 *
	 * Processors receive this instead of the object so they don't
	 * depend on the Execution_Context class directly.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'recipe_id'            => $this->recipe_id,
			'user_id'              => $this->user_id,
			'recipe_log_id'        => $this->recipe_log_id,
			'args'                 => $this->args,
			'action_log_ids'       => $this->action_log_ids,
			'completed_action_ids' => $this->completed_action_ids,
		);
	}
}
