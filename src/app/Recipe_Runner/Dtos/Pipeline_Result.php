<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Dtos;

/**
 * Forward-only result accumulator for the recipe execution pipeline.
 *
 * Stages append their results here as the pipeline progresses.
 * No undo/compensate — results accumulate forward-only.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Dtos
 * @since   7.2
 */
final class Pipeline_Result {

	/**
	 * @var bool Whether the pipeline should stop executing further stages.
	 */
	private $halted = false;

	/**
	 * @var array Trigger entry results from the entry stage.
	 */
	private $trigger_entries = array();

	/**
	 * @var array Action execution results from the action stage.
	 */
	private $action_results = array();

	/**
	 * @var int|null Final recipe completion status.
	 */
	private $recipe_status = null;

	/**
	 * @var string Reason for halting (if halted).
	 */
	private $halt_reason = '';

	/**
	 * @var bool Whether Stage 2 determined triggers are satisfied and execution should proceed.
	 */
	private $execution_ready = false;

	/**
	 * @var int Recipe post ID (set by Stage 2 for stages 3-5).
	 */
	private $recipe_id = 0;

	/**
	 * @var int User ID (set by Stage 2, may differ from Pipeline_Context if user resolver changed it).
	 */
	private $user_id = 0;

	/**
	 * @var int Recipe log ID (set by Stage 2 for stages 3-5).
	 */
	private $recipe_log_id = 0;

	/**
	 * @var array Enriched args (set by Stage 2 with trigger data, token context, etc.).
	 */
	private $execution_args = array();

	/**
	 * @var bool Whether async work (loops, delays) was dispatched during Stage 3.
	 * When true, Stage 4 skips finalize_recipe() — the async completion handler
	 * will finalize when all work is done.
	 */
	private $has_async_work = false;

	/**
	 * Halt the pipeline. No further stages will execute.
	 *
	 * @param string $reason Why the pipeline was halted.
	 *
	 * @return self
	 */
	public function halt( string $reason = '' ): self {
		$this->halted      = true;
		$this->halt_reason = $reason;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function should_halt(): bool {
		return $this->halted;
	}

	/**
	 * @return string
	 */
	public function get_halt_reason(): string {
		return $this->halt_reason;
	}

	/**
	 * Record a trigger entry result.
	 *
	 * @param array $entry [ 'result' => bool, 'args' => array ].
	 *
	 * @return self
	 */
	public function add_trigger_entry( array $entry ): self {
		$this->trigger_entries[] = $entry;
		return $this;
	}

	/**
	 * @return array
	 */
	public function get_trigger_entries(): array {
		return $this->trigger_entries;
	}

	/**
	 * Record an action execution result.
	 *
	 * @param array $result Action result data.
	 *
	 * @return self
	 */
	public function add_action_result( array $result ): self {
		$this->action_results[] = $result;
		return $this;
	}

	/**
	 * @return array
	 */
	public function get_action_results(): array {
		return $this->action_results;
	}

	/**
	 * Set the final recipe status.
	 *
	 * @param int $status One of the Automator_Status constants.
	 *
	 * @return self
	 */
	public function set_recipe_status( int $status ): self {
		$this->recipe_status = $status;
		return $this;
	}

	/**
	 * @return int|null
	 */
	public function get_recipe_status(): ?int {
		return $this->recipe_status;
	}

	/**
	 * Signal that triggers are satisfied and execution should proceed to stages 3-5.
	 *
	 * Called by Stage 2 (Trigger_Complete_Stage) after triggers_completed() returns true.
	 * Stages 3-5 check is_execution_ready() before doing work.
	 *
	 * @param int   $recipe_id     The recipe post ID.
	 * @param int   $user_id       The resolved user ID.
	 * @param int   $recipe_log_id The recipe log ID.
	 * @param array $args          Enriched args with trigger data.
	 *
	 * @return self
	 */
	public function set_execution_ready( int $recipe_id, int $user_id, int $recipe_log_id, array $args ): self {
		$this->execution_ready = true;
		$this->recipe_id       = $recipe_id;
		$this->user_id         = $user_id;
		$this->recipe_log_id   = $recipe_log_id;

		// Pipeline_Result is the single source of truth for execution metadata.
		// Overwrite stale values in $args so all downstream consumers (token parser,
		// logger, field resolver) read consistent data. $args may carry trigger-time
		// values that diverged from the DB during log creation and user resolution.
		$args['recipe_id']     = $recipe_id;
		$args['user_id']       = $user_id;
		$args['recipe_log_id'] = $recipe_log_id;

		$this->execution_args = $args;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function is_execution_ready(): bool {
		return $this->execution_ready;
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
	public function get_execution_args(): array {
		return $this->execution_args;
	}

	/**
	 * Signal that async work (loops, delays) was dispatched.
	 * Stage 4 skips finalize_recipe() when this is set.
	 *
	 * @return self
	 */
	public function mark_async_work_dispatched(): self {
		$this->has_async_work = true;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function has_async_work(): bool {
		return $this->has_async_work;
	}
}
