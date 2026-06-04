<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Stages;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Pipeline_Context;
use Uncanny_Automator\App\Recipe_Runner\Dtos\Pipeline_Result;
use Uncanny_Automator\App\Recipe_Runner\Stages\Stage;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Completion_Service;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Data_Provider;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Log_Manager;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Throttle_Service;
use Uncanny_Automator\App\Recipe_Runner\Services\Run_Number_Service;
use Uncanny_Automator\App\Recipe_Runner\Services\Trigger_Numtimes;
use Uncanny_Automator\App\Recipe_Runner\Services\Trigger_Validator;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Stage 1: Trigger Entry.
 *
 * Matches recipes against the fired trigger code, validates each trigger,
 * creates recipe/trigger logs, and checks numtimes conditions.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Stages
 * @since   7.2
 */
class Trigger_Entry_Stage implements Stage {

	/**
	 * @var Trigger_Validator
	 */
	private $validator;

	/**
	 * @var Recipe_Log_Manager
	 */
	private $log_manager;

	/**
	 * @var Trigger_Numtimes
	 */
	private $numtimes;

	/**
	 * @var Recipe_Completion_Service
	 */
	private $completion;

	/**
	 * @var Recipe_Throttle_Service
	 */
	private $throttle;

	/**
	 * @var Run_Number_Service
	 */
	private $run_number;

	/**
	 * @var Recipe_Data_Provider
	 */
	private $data_provider;

	/**
	 * @param Trigger_Validator             $validator      Trigger validation service.
	 * @param Recipe_Log_Manager            $log_manager    Recipe log creation service.
	 * @param Trigger_Numtimes              $numtimes       Numtimes tracking service.
	 * @param Recipe_Completion_Service|null $completion     Optional completion service.
	 * @param Recipe_Throttle_Service|null   $throttle       Optional throttle service.
	 * @param Run_Number_Service|null        $run_number     Optional run number service.
	 * @param Recipe_Data_Provider|null      $data_provider  Optional data provider service.
	 */
	public function __construct(
		Trigger_Validator $validator,
		Recipe_Log_Manager $log_manager,
		Trigger_Numtimes $numtimes,
		?Recipe_Completion_Service $completion = null,
		?Recipe_Throttle_Service $throttle = null,
		?Run_Number_Service $run_number = null,
		?Recipe_Data_Provider $data_provider = null
	) {
		$this->validator     = $validator;
		$this->log_manager   = $log_manager;
		$this->numtimes      = $numtimes;
		$this->completion    = $completion ?? new Recipe_Completion_Service();
		$this->throttle      = $throttle ?? new Recipe_Throttle_Service();
		$this->run_number    = $run_number ?? new Run_Number_Service();
		$this->data_provider = $data_provider ?? new Recipe_Data_Provider();
	}

	/**
	 * Execute the trigger entry stage.
	 *
	 * @param Pipeline_Context $context Immutable pipeline context.
	 * @param Pipeline_Result  $result  Accumulated results.
	 *
	 * @return Pipeline_Result
	 */
	public function execute( Pipeline_Context $context, Pipeline_Result $result ): Pipeline_Result {

		$trigger_code = $context->get_trigger_code();

		if ( empty( $trigger_code ) ) {
			return $result->halt( 'No trigger code provided.' );
		}

		// Webhook uses webhook_recipe_id for filtering; non-webhook uses matched_recipe_id.
		$filter_recipe_id = $context->is_webhook() ? $context->get_webhook_recipe_id() : $context->get_matched_recipe_id();
		$recipes          = $this->get_recipes( $trigger_code, $filter_recipe_id );

		if ( empty( $recipes ) ) {
			return $result->halt( 'No recipes matched trigger code.' );
		}

		$recipe_ids = array_keys( $recipes );
		update_meta_cache( 'post', $recipe_ids );

		$completed_map = $this->batch_check_completed( $recipe_ids, $context->get_user_id() );

		foreach ( $recipes as $recipe ) {
			$this->process_recipe( $recipe, $context, $result, $completed_map );
		}

		return $result;
	}

	/**
	 * Process a single recipe — check eligibility and validate its triggers.
	 *
	 * @param array            $recipe        Recipe data with ID, post_status, recipe_type, triggers.
	 * @param Pipeline_Context $context       Immutable pipeline context.
	 * @param Pipeline_Result  $result        Accumulated results (mutated in-place).
	 * @param array            $completed_map Map of recipe_id => true for completed recipes.
	 *
	 * @return void
	 */
	protected function process_recipe( array $recipe, Pipeline_Context $context, Pipeline_Result $result, array $completed_map ): void {

		if ( ! $this->is_recipe_eligible( $recipe, $context, $completed_map ) ) {
			return;
		}

		$recipe_id = absint( $recipe['ID'] );
		$args      = $context->get_args();

		$simulated_log = $this->log_manager->maybe_create_recipe_log_entry( $recipe_id, $context->get_user_id(), true, $args, true );

		if ( empty( $simulated_log ) || ! isset( $simulated_log['recipe_log_id'] ) ) {
			return;
		}

		$simulated_log_id = (int) $simulated_log['recipe_log_id'];

		foreach ( $recipe['triggers'] as $trigger ) {
			$this->process_trigger( $trigger, $recipe_id, $simulated_log, $simulated_log_id, $context, $result );
		}
	}

	/**
	 * Check if a recipe is eligible for trigger matching.
	 *
	 * @param array            $recipe        Recipe data.
	 * @param Pipeline_Context $context       Pipeline context.
	 * @param array            $completed_map Completed recipe map.
	 *
	 * @return bool
	 */
	protected function is_recipe_eligible( array $recipe, Pipeline_Context $context, array $completed_map ): bool {

		if ( 'publish' !== $recipe['post_status'] ) {
			return false;
		}

		if ( 'user' === (string) $recipe['recipe_type'] && ! $context->is_signed_in() ) {
			return false;
		}

		$recipe_id = absint( $recipe['ID'] );

		if ( ! empty( $completed_map[ $recipe_id ] ) ) {
			return false;
		}

		return ! $this->throttle->is_throttled( $recipe_id, absint( $context->get_user_id() ) );
	}

	/**
	 * Validate and process a single trigger within a recipe.
	 *
	 * @param array            $trigger          Trigger data.
	 * @param int              $recipe_id        The recipe ID.
	 * @param array            $simulated_log    Simulated log result.
	 * @param int              $simulated_log_id Simulated log ID.
	 * @param Pipeline_Context $context          Pipeline context.
	 * @param Pipeline_Result  $result           Accumulated results.
	 *
	 * @return void
	 */
	protected function process_trigger( array $trigger, int $recipe_id, array $simulated_log, int $simulated_log_id, Pipeline_Context $context, Pipeline_Result $result ): void {

		if ( ! $this->is_trigger_eligible( $trigger, $context ) ) {
			return;
		}

		$args           = $context->get_args();
		$user_id        = $context->get_user_id();
		$ignore_post_id = $context->should_ignore_post_id();
		$trigger_id     = absint( $trigger['ID'] );

		$validation = $this->validator->get_trigger_id( $args, $trigger, $recipe_id, $simulated_log_id, $ignore_post_id );

		if ( false === $validation['result'] ) {
			$result->add_trigger_entry( $validation );
			return;
		}

		$resolved = $this->resolve_recipe_log_id( $simulated_log, $recipe_id, $user_id, $args, $simulated_log_id, $ignore_post_id, $trigger );

		// If re-validation with the real log ID failed, bail out.
		if ( false === ( $resolved['result'] ?? true ) ) {
			$result->add_trigger_entry(
				array(
					'result' => false,
					'error' => 'Re-validation failed after log creation.',
				)
			);
			return;
		}

		$recipe_log_id  = $resolved['recipe_log_id'];
		$trigger_log_id = (int) ( $resolved['trigger_log_id'] ?? $validation['trigger_log_id'] );

		$numtimes_result = $this->numtimes->maybe_trigger_num_times_completed(
			array(
				'recipe_id'      => $recipe_id,
				'trigger_id'     => $trigger_id,
				'trigger'        => $trigger,
				'user_id'        => $user_id,
				'recipe_log_id'  => $recipe_log_id,
				'trigger_log_id' => $trigger_log_id,
				'is_signed_in'   => $context->is_signed_in(),
			)
		);

		$this->maybe_save_any_option_meta( $trigger, $context, $recipe_id, $trigger_id, $recipe_log_id, $trigger_log_id, $numtimes_result );

		$trigger_code = $context->get_trigger_code();
		$post_id      = $context->get_post_id();
		$trigger_meta = $context->get_trigger_meta();

		do_action_deprecated( 'uap_after_trigger_run', array( $trigger_code, $post_id, $user_id, $trigger_meta ), '3.0', 'automator_after_trigger_run' );
		Dispatcher::action( 'automator_after_trigger_run', $trigger_code, $post_id, $user_id, $trigger_meta );

		if ( true === $numtimes_result['result'] ) {
			$result->add_trigger_entry( $this->build_success_entry( $args, $trigger_log_id, $recipe_id, $trigger_id, $recipe_log_id, $context ) );
		}
	}

	/**
	 * Check if a trigger is eligible for validation.
	 *
	 * @param array            $trigger Trigger data.
	 * @param Pipeline_Context $context Pipeline context.
	 *
	 * @return bool
	 */
	protected function is_trigger_eligible( array $trigger, Pipeline_Context $context ): bool {

		$matched_trigger_id = $context->get_matched_trigger_id();

		if ( ! empty( $matched_trigger_id ) && is_numeric( $matched_trigger_id ) && (int) $trigger['ID'] !== (int) $matched_trigger_id ) {
			return false;
		}

		return 'publish' === $trigger['post_status'];
	}

	/**
	 * Resolve the real recipe log ID — create if simulated log was not existing.
	 *
	 * When the log is new (not existing), re-validates the trigger with the real log ID
	 * and returns the updated trigger_log_id from that re-validation.
	 *
	 * @param array    $simulated_log    Simulated log result.
	 * @param int      $recipe_id        The recipe ID.
	 * @param int      $user_id          The user ID.
	 * @param array    $args             Trigger args.
	 * @param int      $simulated_log_id Simulated log ID.
	 * @param bool     $ignore_post_id   Whether to ignore post_id.
	 * @param array    $trigger          Trigger data.
	 *
	 * @return array{recipe_log_id: int, trigger_log_id: int|null} Recipe log ID and optionally updated trigger log ID.
	 */
	protected function resolve_recipe_log_id( array $simulated_log, int $recipe_id, int $user_id, array $args, int $simulated_log_id, bool $ignore_post_id, array $trigger ): array {

		if ( $simulated_log['existing'] ) {
			return array(
				'recipe_log_id'  => $simulated_log_id,
				'trigger_log_id' => null, // Use the original validation's trigger_log_id.
			);
		}

		$real_log      = $this->log_manager->maybe_create_recipe_log_entry( $recipe_id, $user_id, true, $args );
		$recipe_log_id = (int) $real_log['recipe_log_id'];

		// Re-validate with the real log ID — the new trigger_log_id takes precedence.
		$revalidation = $this->validator->get_trigger_id( $args, $trigger, $recipe_id, $recipe_log_id, $ignore_post_id );

		return array(
			'recipe_log_id'  => $recipe_log_id,
			'trigger_log_id' => $revalidation['trigger_log_id'] ?? null,
			'result'         => $revalidation['result'] ?? true,
		);
	}

	/**
	 * Save "Any" (-1) option meta for token resolution if applicable.
	 *
	 * @param array            $trigger          Trigger data.
	 * @param Pipeline_Context $context          Pipeline context.
	 * @param int              $recipe_id        The recipe ID.
	 * @param int              $trigger_id       The trigger ID.
	 * @param int              $recipe_log_id    The recipe log ID.
	 * @param int              $trigger_log_id   The trigger log ID.
	 * @param array            $numtimes_result  Result from numtimes check.
	 *
	 * @return void
	 */
	protected function maybe_save_any_option_meta( array $trigger, Pipeline_Context $context, int $recipe_id, int $trigger_id, int $recipe_log_id, int $trigger_log_id, array $numtimes_result ): void {

		if ( true !== $numtimes_result['result'] ) {
			return;
		}

		$trigger_meta = $context->get_trigger_meta();
		$post_id      = $context->get_post_id();

		$is_any_option = isset( $trigger['meta'][ $trigger_meta ] ) && -1 === intval( $trigger['meta'][ $trigger_meta ] );

		if ( ! $is_any_option || 0 === absint( $post_id ) ) {
			return;
		}

		$this->numtimes->maybe_trigger_add_any_option_meta(
			array(
				'recipe_id'      => $recipe_id,
				'trigger_id'     => $trigger_id,
				'user_id'        => $context->get_user_id(),
				'recipe_log_id'  => $recipe_log_id,
				'trigger_log_id' => $trigger_log_id,
				'post_id'        => $post_id,
				'trigger'        => $trigger,
				'is_signed_in'   => $context->is_signed_in(),
				'meta'           => $trigger_meta,
				'run_number'     => $this->run_number->get_current( $recipe_id, $context->get_user_id() ),
			),
			$trigger_meta
		);
	}

	/**
	 * Build a successful trigger entry for the result accumulator.
	 *
	 * @param array            $args            Base trigger args.
	 * @param int              $trigger_log_id  The trigger log ID.
	 * @param int              $recipe_id       The recipe ID.
	 * @param int              $trigger_id      The trigger ID.
	 * @param int              $recipe_log_id   The recipe log ID.
	 * @param Pipeline_Context $context         Pipeline context.
	 *
	 * @return array
	 */
	protected function build_success_entry( array $args, int $trigger_log_id, int $recipe_id, int $trigger_id, int $recipe_log_id, Pipeline_Context $context ): array {

		$entry_args = $args;

		$entry_args['get_trigger_id'] = $trigger_log_id;
		$entry_args['trigger_log_id'] = $trigger_log_id;
		$entry_args['recipe_id']      = $recipe_id;
		$entry_args['trigger_id']     = $trigger_id;
		$entry_args['recipe_log_id']  = $recipe_log_id;
		$entry_args['post_id']        = $context->get_post_id();
		$entry_args['is_signed_in']   = $context->is_signed_in();
		$entry_args['run_number']     = $this->run_number->get_current( $recipe_id, $context->get_user_id() );

		return array(
			'result' => true,
			'args'   => $entry_args,
		);
	}

	/**
	 * Get recipes matching a trigger code.
	 *
	 * @param string   $trigger_code   The trigger code.
	 * @param int|null $webhook_recipe Specific recipe ID for webhook triggers.
	 *
	 * @return array
	 */
	protected function get_recipes( string $trigger_code, ?int $webhook_recipe = null ): array {
		return $this->data_provider->get_recipes_from_trigger_code( $trigger_code, $webhook_recipe );
	}

	/**
	 * Batch-check whether recipes are completed for a user.
	 *
	 * Delegates to Recipe_Completion_Service for the actual queries.
	 *
	 * @param int[] $recipe_ids Array of recipe IDs.
	 * @param int   $user_id   The user ID.
	 *
	 * @return array Map of recipe_id => true for completed recipes.
	 */
	protected function batch_check_completed( array $recipe_ids, int $user_id ): array {
		return $this->completion->batch_check_completed( $recipe_ids, $user_id );
	}
}
