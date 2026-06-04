<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Stages;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Pipeline_Context;
use Uncanny_Automator\App\Recipe_Runner\Dtos\Pipeline_Result;
use Uncanny_Automator\App\Recipe_Runner\Stages\Stage;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Handler;
use Uncanny_Automator\App\Recipe_Runner\Services\Integration_Registry;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Data_Provider;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Stage 5: Closures.
 *
 * Runs closures (redirects, completion emails) after recipe status
 * is finalized by Stage 4. Closures can read the final recipe status
 * from Pipeline_Result.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Stages
 * @since   7.3
 */
class Closure_Stage implements Stage {

	/**
	 * @var Execution_Log_Store
	 */
	private $log_store;

	/**
	 * @var Integration_Registry
	 */
	private $integrations;

	/**
	 * @var Recipe_Data_Provider
	 */
	private $data_provider;

	/**
	 * @var Error_Handler
	 */
	private $error_handler;

	/**
	 * @param Execution_Log_Store|null            $log_store     Optional log store instance.
	 * @param Integration_Registry|null $integrations  Optional integration registry.
	 * @param Recipe_Data_Provider|null $data_provider Optional data provider instance.
	 * @param Error_Handler|null        $error_handler Optional error handler instance.
	 */
	public function __construct( ?Execution_Log_Store $log_store = null, ?Integration_Registry $integrations = null, ?Recipe_Data_Provider $data_provider = null, ?Error_Handler $error_handler = null ) {
		$this->log_store     = $log_store ?? Database::get_execution_log_store();
		$this->integrations  = $integrations ?? new Integration_Registry();
		$this->data_provider = $data_provider ?? new Recipe_Data_Provider();
		$this->error_handler = $error_handler ?? new Error_Handler();
	}

	/**
	 * Execute closures for the recipe.
	 *
	 * Only runs when execution_ready is set by Stage 2 (triggers satisfied).
	 * Reads recipe data from Pipeline_Result, not Pipeline_Context.
	 *
	 * @param Pipeline_Context $context Immutable pipeline context.
	 * @param Pipeline_Result  $result  Accumulated results from prior stages.
	 *
	 * @return Pipeline_Result
	 */
	public function execute( Pipeline_Context $context, Pipeline_Result $result ): Pipeline_Result {

		// IMPORTANT: Do NOT gate on has_async_work(). Closures are primarily
		// redirects — they MUST fire immediately for the user's browser to
		// navigate. Blocking until async loops/delays complete would leave
		// the user stranded on the current page with no feedback.
		if ( ! $result->is_execution_ready() ) {
			return $result;
		}

		$recipe_id     = $result->get_recipe_id();
		$user_id       = $result->get_user_id();
		$recipe_log_id = $result->get_recipe_log_id();
		$args          = $result->get_execution_args();

		$closure_data = $this->data_provider->get_recipe_data( AUTOMATOR_POST_TYPE_CLOSURE, $recipe_id );

		if ( ! empty( $closure_data ) ) {
			foreach ( $closure_data as $closure ) {
				$this->execute_closure( $closure, $recipe_id, $user_id, $recipe_log_id, $args );
			}
		}

		Dispatcher::action( 'automator_closures_completed', $recipe_id, $user_id, $args );

		return $result;
	}

	/**
	 * Run closures for a recipe — public entry point for legacy callers.
	 *
	 * Used by Recipe_Complete_Stage::closures() (deprecated facade path)
	 * to delegate here instead of duplicating the closure execution logic.
	 *
	 * @param array      $closure_data_list Array of closure data arrays (or null to fetch).
	 * @param int|null   $recipe_id         The recipe ID.
	 * @param int|null   $user_id           The user ID.
	 * @param int|null   $recipe_log_id     The recipe log ID.
	 * @param array      $args              Trigger args.
	 *
	 * @return void
	 */
	public function run_closures( ?array $closure_data_list, $recipe_id, $user_id, $recipe_log_id, array &$args ): void {

		if ( null === $closure_data_list ) {
			$closure_data_list = $this->data_provider->get_recipe_data( AUTOMATOR_POST_TYPE_CLOSURE, $recipe_id );
		}

		foreach ( $closure_data_list as $closure_data ) {
			$this->execute_closure( $closure_data, $recipe_id, $user_id, $recipe_log_id, $args );
		}

		Dispatcher::action( 'automator_closures_completed', $recipe_id, $user_id, $args );
	}

	/**
	 * Execute a single closure.
	 *
	 * @param array    $closure_data   Closure data.
	 * @param int|null $recipe_id      The recipe ID.
	 * @param int|null $user_id        The user ID.
	 * @param int|null $recipe_log_id  The recipe log ID.
	 * @param array    $args           Trigger args.
	 *
	 * @return void
	 */
	protected function execute_closure( array $closure_data, $recipe_id, $user_id, $recipe_log_id, array &$args ): void {

		$closure_code   = $closure_data['meta']['code'] ?? '';
		$closure_status = $closure_data['post_status'] ?? '';

		if ( empty( $closure_code ) || 'publish' !== (string) $closure_status ) {
			return;
		}

		$closure_integration = $this->integrations->get_closure_integration( $closure_code );

		if ( ! $this->integrations->is_plugin_active( $closure_integration ) ) {
			$this->error_handler->add_error(
				'closure_execution',
				'ERROR: Plugin for closure ' . $closure_code . ' (' . $closure_integration . ') is not active.',
				$this
			);
			return;
		}

		// Insert as NOT_COMPLETED first — update to COMPLETED after successful execution.
		// Prevents false-positive log entries if the closure callback throws.
		$log_id = $this->log_store->add_closure_entry(
			array(
				'user_id'                 => $user_id,
				'automator_closure_id'    => $closure_data['ID'],
				'automator_recipe_id'     => $recipe_id,
				'automator_recipe_log_id' => $recipe_log_id,
				'completed'               => Automator_Status::NOT_COMPLETED,
			)
		);

		$closure_execution_function = $this->integrations->get_closure_execution_function( $closure_code );

		if ( null !== $log_id ) {
			$args['closure_log_id'] = $log_id;
			$this->log_store->add_closure_entry_meta(
				array(
					'user_id'                  => $user_id,
					'automator_closure_id'     => $closure_data['ID'],
					'automator_closure_log_id' => $log_id,
				),
				'closure_data',
				wp_json_encode( $closure_data )
			);
		}

		if ( is_callable( $closure_execution_function ) ) {
			try {
				call_user_func_array( $closure_execution_function, array( $user_id, $closure_data, $recipe_id, $args ) );
			} catch ( \Throwable $e ) {
				$this->error_handler->add_error(
					'closure_execution',
					'Closure ' . $closure_code . ' threw: ' . $e->getMessage(),
					$this
				);
				return;
			}
		}

		// Mark COMPLETED only after successful execution.
		if ( null !== $log_id ) {
			$this->log_store->mark_closure_complete( $log_id, Automator_Status::COMPLETED );
		}
	}
}
