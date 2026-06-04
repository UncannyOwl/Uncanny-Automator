<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Trigger numtimes tracking and meta storage.
 *
 * Tracks how many times a trigger has fired for a user and stores
 * sentence/trigger_object meta for logging. Each method has a single job.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.2
 */
class Trigger_Numtimes {

	/**
	 * @var Recipe_Log_Manager
	 */
	private $log_manager;

	/**
	 * @var Run_Number_Service
	 */
	private $run_number;

	/**
	 * @var Execution_Log_Store
	 */
	private $log_store;

	/**
	 * @var Recipe_Data_Provider
	 */
	private $data_provider;

	/**
	 * @param Recipe_Log_Manager        $log_manager   Log manager for recipe log status changes.
	 * @param Run_Number_Service|null   $run_number    Optional run number service.
	 * @param Execution_Log_Store|null            $log_store     Optional log store instance.
	 * @param Recipe_Data_Provider|null $data_provider Optional data provider instance.
	 */
	public function __construct( Recipe_Log_Manager $log_manager, ?Run_Number_Service $run_number = null, ?Execution_Log_Store $log_store = null, ?Recipe_Data_Provider $data_provider = null ) {
		$this->log_manager   = $log_manager;
		$this->run_number    = $run_number ?? new Run_Number_Service();
		$this->log_store     = $log_store ?? Database::get_execution_log_store();
		$this->data_provider = $data_provider ?? new Recipe_Data_Provider();
	}

	/**
	 * Check if the trigger's number-of-times condition is met.
	 *
	 * Orchestrates three steps:
	 *   1. Validate required fields.
	 *   2. Record the trigger run (increment counter + store meta).
	 *   3. Compare user's run count against the required threshold.
	 *
	 * @param array $times_args Keyed array with recipe_id, trigger_id, trigger, user_id, recipe_log_id, trigger_log_id.
	 *
	 * @return array{result: bool, error: string, run_number?: int}
	 */
	public function maybe_trigger_num_times_completed( array $times_args ): array {

		Dispatcher::action( 'automator_before_maybe_trigger_num_times_completed', $times_args );

		$validation_error = $this->validate_required_fields( $times_args );

		if ( null !== $validation_error ) {
			return $validation_error;
		}

		$trigger_id     = (int) $times_args['trigger_id'];
		$trigger        = $times_args['trigger'];
		$user_id        = (int) $times_args['user_id'];
		$recipe_id      = isset( $times_args['recipe_id'] ) ? (int) $times_args['recipe_id'] : null;
		$recipe_log_id  = isset( $times_args['recipe_log_id'] ) ? (int) $times_args['recipe_log_id'] : null;
		$trigger_log_id = isset( $times_args['trigger_log_id'] ) ? (int) $times_args['trigger_log_id'] : null;

		$num_times_required = $this->get_required_num_times( $trigger );
		$run_data           = $this->record_trigger_run( $user_id, $trigger_id, $trigger_log_id, $trigger );

		// Transition recipe log from -1 (pending) to 0 (in progress).
		$this->log_manager->maybe_change_recipe_log_to_zero( (int) $recipe_id, (int) $user_id, (int) $recipe_log_id, true );

		if ( $run_data['user_count'] < $num_times_required ) {
			$this->fire_insufficient_action( $trigger_id, $recipe_id, $trigger_log_id, $recipe_log_id, $run_data['run_number'], $user_id );

			return array(
				'result' => false,
				'error'  => 'Number of times condition is not completed.',
			);
		}

		return array(
			'result'     => true,
			'error'      => 'Number of times condition met.',
			'run_number' => $run_data['run_number'],
		);
	}

	/**
	 * Store the "Any" (-1) option meta value for token resolution.
	 *
	 * @param array       $option_meta    Keyed array with recipe_id, trigger_id, user_id, etc.
	 * @param string|null $save_for_option The meta key to save for.
	 *
	 * @return array{result: bool, error: string}
	 */
	public function maybe_trigger_add_any_option_meta( array $option_meta, ?string $save_for_option = null ): array {

		if ( null === $save_for_option || '' === $save_for_option ) {
			return array(
				'result' => false,
				'error'  => esc_html__( 'Option meta not defined.', 'uncanny-automator' ),
			);
		}

		$trigger_id     = isset( $option_meta['trigger_id'] ) ? absint( $option_meta['trigger_id'] ) : null;
		$user_id        = isset( $option_meta['user_id'] ) ? absint( $option_meta['user_id'] ) : null;
		$trigger        = $option_meta['trigger'] ?? null;
		$trigger_log_id = isset( $option_meta['trigger_log_id'] ) ? absint( $option_meta['trigger_log_id'] ) : null;
		$post_id        = $option_meta['post_id'] ?? null;

		if ( null === $trigger_id || null === $trigger || null === $user_id ) {
			return array(
				'result' => false,
				'error'  => 'One of the required field is missing.',
			);
		}

		$run_number = $this->run_number->get_current( (int) $option_meta['recipe_id'], (int) $user_id );

		return $this->upsert_option_meta( $trigger_id, $trigger_log_id, $run_number, $user_id, $save_for_option, $post_id );
	}

	/**
	 * Validate that trigger_id, trigger, and user_id are present.
	 *
	 * @param array $args The input args.
	 *
	 * @return array|null Error array if validation fails, null if valid.
	 */
	protected function validate_required_fields( array $args ): ?array {

		$trigger_id = $args['trigger_id'] ?? null;
		$trigger    = $args['trigger'] ?? null;
		$user_id    = $args['user_id'] ?? null;

		if ( null === $trigger_id || null === $trigger || null === $user_id ) {
			return array(
				'result' => false,
				'error'  => esc_html__( 'One of the required field is missing.', 'uncanny-automator' ),
			);
		}

		return null;
	}

	/**
	 * Get the required number of times from trigger meta, defaulting to 1.
	 *
	 * @param array $trigger The trigger data with meta.
	 *
	 * @return int
	 */
	protected function get_required_num_times( array $trigger ): int {
		return isset( $trigger['meta']['NUMTIMES'] ) ? absint( $trigger['meta']['NUMTIMES'] ) : 1;
	}

	/**
	 * Record one trigger run: increment counter, store sentence and trigger object.
	 *
	 * @param int   $user_id        The user ID.
	 * @param int   $trigger_id     The trigger post ID.
	 * @param int   $trigger_log_id The trigger log ID.
	 * @param array $trigger        The trigger data.
	 *
	 * @return array{user_count: int, run_number: int}
	 */
	protected function record_trigger_run( int $user_id, int $trigger_id, ?int $trigger_log_id, array $trigger ): array {

		$trigger_log_id = (int) $trigger_log_id;

		$run_number     = $this->data_provider->get_trigger_run_number( $trigger_id, $trigger_log_id, $user_id );
		$user_num_times = $this->data_provider->get_trigger_meta( $user_id, $trigger['ID'], 'NUMTIMES', $trigger_log_id );

		if ( ! empty( $user_num_times ) ) {
			$user_num_times++;
			$run_number++;
		} else {
			$user_num_times = 1;
		}

		$this->insert_trigger_meta( $user_id, $trigger_id, $trigger_log_id, $run_number, 'NUMTIMES', 1 );
		$this->store_sentence_meta( $user_id, $trigger_id, $trigger_log_id, $run_number );
		$this->store_trigger_object_meta( $user_id, $trigger_id, $trigger_log_id, $run_number, $trigger );

		return array(
			'user_count' => $user_num_times,
			'run_number' => $run_number,
		);
	}

	/**
	 * Store the human-readable trigger sentence if available.
	 *
	 * @param int $user_id        The user ID.
	 * @param int $trigger_id     The trigger post ID.
	 * @param int $trigger_log_id The trigger log ID.
	 * @param int $run_number     The current run number.
	 *
	 * @return void
	 */
	protected function store_sentence_meta( int $user_id, int $trigger_id, int $trigger_log_id, int $run_number ): void {

		$trigger_data = $this->data_provider->get_trigger_sentence( $trigger_id, 'trigger_detail' );

		Dispatcher::action(
			'automator_complete_trigger_detail',
			$trigger_data,
			array(
				'user_id'        => $user_id,
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $trigger_log_id,
				'run_number'     => $run_number,
			)
		);

		$sentence = $this->data_provider->get_trigger_sentence( $trigger_id, 'sentence_human_readable' );

		if ( ! empty( $sentence ) ) {
			$this->insert_trigger_meta( $user_id, $trigger_id, $trigger_log_id, $run_number, 'sentence_human_readable', $sentence );
		}
	}

	/**
	 * Store the serialized trigger object for debugging/replay.
	 *
	 * @param int   $user_id        The user ID.
	 * @param int   $trigger_id     The trigger post ID.
	 * @param int   $trigger_log_id The trigger log ID.
	 * @param int   $run_number     The current run number.
	 * @param array $trigger        The full trigger data.
	 *
	 * @return void
	 */
	protected function store_trigger_object_meta( int $user_id, int $trigger_id, int $trigger_log_id, int $run_number, array $trigger ): void {
		$this->insert_trigger_meta( $user_id, $trigger_id, $trigger_log_id, $run_number, 'trigger_object', maybe_serialize( $trigger ) );
	}

	/**
	 * Insert or update option meta for "Any" (-1) selections.
	 *
	 * @param int    $trigger_id     The trigger ID.
	 * @param int    $trigger_log_id The trigger log ID.
	 * @param int    $run_number     The run number.
	 * @param int    $user_id        The user ID.
	 * @param string $meta_key       The meta key to store.
	 * @param mixed  $meta_value     The value to store (typically a post ID).
	 *
	 * @return array{result: bool|int, error: string}
	 */
	protected function upsert_option_meta( int $trigger_id, ?int $trigger_log_id, int $run_number, int $user_id, string $meta_key, $meta_value ): array {

		$trigger_log_id = (int) $trigger_log_id;

		$existing_meta_id = $this->data_provider->maybe_get_meta_id_from_trigger_log( $run_number, $trigger_id, $trigger_log_id, $meta_key, $user_id );

		// With ?int return type on find_trigger_log_meta_id(), $existing_meta_id
		// is always null (not found) or int (found). Two branches, no fallback.
		if ( null === $existing_meta_id ) {
			return array(
				'result' => $this->log_store->add_trigger_meta(
					$trigger_id,
					$trigger_log_id,
					$run_number,
					array(
						'user_id'        => $user_id,
						'trigger_id'     => $trigger_id,
						'meta_key'       => $meta_key,
						'meta_value'     => $meta_value,
						'run_number'     => $run_number,
						'trigger_log_id' => $trigger_log_id,
					)
				),
				'error'  => esc_html__( 'Meta entry added.', 'uncanny-automator' ),
			);
		}

		return array(
			'result' => $this->data_provider->update_trigger_meta( $user_id, $trigger_id, $meta_key, $meta_value, $trigger_log_id ),
			'error'  => esc_html__( 'Meta entry updated.', 'uncanny-automator' ),
		);
	}

	/**
	 * Insert a single trigger meta row.
	 *
	 * @param int    $user_id        The user ID.
	 * @param int    $trigger_id     The trigger post ID.
	 * @param int    $trigger_log_id The trigger log ID.
	 * @param int    $run_number     The run number.
	 * @param string $meta_key       The meta key.
	 * @param mixed  $meta_value     The meta value.
	 *
	 * @return mixed
	 */
	protected function insert_trigger_meta( int $user_id, int $trigger_id, int $trigger_log_id, int $run_number, string $meta_key, $meta_value ) {
		return $this->log_store->add_trigger_meta(
			$trigger_id,
			$trigger_log_id,
			$run_number,
			array(
				'user_id'        => $user_id,
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $trigger_log_id,
				'run_number'     => $run_number,
				'meta_key'       => $meta_key,
				'meta_value'     => $meta_value,
			)
		);
	}

	/**
	 * Fire the insufficient-numtimes action for extensions to hook into.
	 *
	 * @param int      $trigger_id     The trigger ID.
	 * @param int|null $recipe_id      The recipe ID.
	 * @param int|null $trigger_log_id The trigger log ID.
	 * @param int|null $recipe_log_id  The recipe log ID.
	 * @param int      $run_number     The run number.
	 * @param int      $user_id        The user ID.
	 *
	 * @return void
	 */
	protected function fire_insufficient_action( int $trigger_id, ?int $recipe_id, ?int $trigger_log_id, ?int $recipe_log_id, int $run_number, int $user_id ): void {
		Dispatcher::action(
			'automator_recipe_process_user_trigger_num_times_insufficient',
			array(
				'trigger_id'     => $trigger_id,
				'recipe_id'      => $recipe_id,
				'trigger_log_id' => $trigger_log_id,
				'recipe_log_id'  => $recipe_log_id,
				'run_number'     => $run_number,
				'user_id'        => $user_id,
			)
		);
	}
}
