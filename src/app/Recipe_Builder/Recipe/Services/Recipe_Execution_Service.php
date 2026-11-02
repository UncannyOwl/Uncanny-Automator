<?php
/**
 * Recipe Execution Service
 *
 * Validates and executes recipes with manual triggers.
 * Absorbs DB access and infrastructure concerns from the transport layer.
 *
 * @since 7.1.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\App\Plan\Services\Plan_Service;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Trigger_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Trigger_Store;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\Automator_Status;
use WP_Error;
use Uncanny_Automator\App\Events\Manual_Recipe_Run;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Bridge\Automator_Trigger_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Trigger_Definition_Bridge;

/**
 * Recipe_Execution_Service Class
 *
 * Handles recipe execution: validates the recipe exists, has a manual trigger,
 * and fires the execution action.
 */
class Recipe_Execution_Service {

	/**
	 * Singleton instance.
	 *
	 * @var Recipe_Execution_Service|null
	 */
	private static $instance = null;

	/**
	 * Trigger store.
	 *
	 * @var Recipe_Trigger_Store
	 */
	private $trigger_store;

	/** @var Manual_Recipe_Run */
	private $manual_run;

	/** @var Trigger_Definition_Bridge */
	private $trigger_definitions;

	/**
	 * Plan service — gates Run Now behind a valid Pro license.
	 *
	 * @var Plan_Service
	 */
	private Plan_Service $plan_service;

	/**
	 * Constructor.
	 *
	 * Allows dependency injection for testing. Production code can use instance()
	 * for backward compatibility with singleton pattern.
	 *
	 * @param Recipe_Trigger_Store|null $trigger_store Optional trigger store instance.
	 * @param Trigger_Definition_Bridge|null $trigger_definitions Optional trigger definitions.
	 * @param Plan_Service|null $plan_service Optional plan service instance.
	 */
	public function __construct( ?Recipe_Trigger_Store $trigger_store = null, ?Trigger_Definition_Bridge $trigger_definitions = null, ?Plan_Service $plan_service = null ) {
		global $wpdb;
		$this->trigger_store       = $trigger_store ?? new WP_Recipe_Trigger_Store( $wpdb );
		$this->manual_run          = new Manual_Recipe_Run();
		$this->trigger_definitions = $trigger_definitions ?? new Automator_Trigger_Definition_Bridge();
		$this->plan_service        = $plan_service ?? new Plan_Service();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Recipe_Execution_Service
	 */
	public static function instance(): Recipe_Execution_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Execute a recipe by its ID.
	 *
	 * Validates the recipe exists and is live, has a live manual trigger and a
	 * runner, and passes the same refusals as Run now, then fires the run.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @param bool $drain_queue Run the whole trigger queue, not only this recipe's trigger, before returning the receipt.
	 * @return array|WP_Error Result array with recipe_id, recipe_title, recipe_status on success.
	 */
	public function execute_recipe( int $recipe_id, bool $drain_queue = false ) {

		// Validate recipe exists.
		$recipe_post = get_post( $recipe_id );
		if ( ! $recipe_post || AUTOMATOR_POST_TYPE_RECIPE !== $recipe_post->post_type ) {
			return new WP_Error( 'recipe_not_found', 'Recipe not found or invalid type.' );
		}
		if ( 'publish' !== $recipe_post->post_status ) {
			return new WP_Error( 'recipe_not_published', 'Only published recipes can be executed.' );
		}

		// Check for manual trigger.
		$recipe_id_vo = new Recipe_Id( $recipe_id );

		if ( ! $this->trigger_store->recipe_has_live_manual_trigger( $recipe_id_vo ) ) {
			return new WP_Error(
				'no_manual_trigger',
				'Recipe lacks a published manual trigger. Add or enable trigger_code "RECIPE_MANUAL_TRIGGER_ANON".'
			);
		}

		// Saved hook metadata can outlive Pro. The Core listener alone does not
		// prove a registered provider can consume the queued manual trigger.
		$definition = $this->trigger_definitions->get_trigger_definition( 'RECIPE_MANUAL_TRIGGER_ANON' );
		if ( ! is_callable( $definition['validation_function'] ?? null ) || ! $this->manual_run->is_available() ) {
			return new WP_Error( 'manual_runner_unavailable', 'The manual recipe runner is unavailable. Confirm Uncanny Automator Pro and the manual trigger are active.' );
		}

		$refusal = $this->run_refusal( $recipe_id );
		if ( $refusal instanceof WP_Error ) {
			return $refusal;
		}

		$receipt = $this->manual_run->dispatch( $recipe_id, $drain_queue );
		if ( $receipt['recipe_log_id'] <= 0 && '' === $receipt['queue_id'] ) {
			// Firing a hook is not evidence that its provider accepted this run.
			// Do not claim no side effects: a provider may fail after doing work.
			return new WP_Error( 'recipe_execution_unconfirmed', 'The recipe request was dispatched, but the runner did not confirm a run. Check recipe logs and current run state before retrying.' );
		}

		$result = array(
			'recipe_id'     => $recipe_id,
			'recipe_title'  => get_the_title( $recipe_id ),
			'recipe_status' => $receipt['recipe_log_id'] > 0 ? 'initiated' : 'queued',
		);
		if ( $receipt['recipe_log_id'] > 0 ) {
			$result['recipe_log_id'] = $receipt['recipe_log_id'];
		}
		if ( '' !== $receipt['queue_id'] ) {
			$result['queue_id'] = $receipt['queue_id'];
		}
		if ( $receipt['dispatch_failed'] ) {
			$result['warning'] = 'The runner accepted this request, but a later dispatch callback failed. Check recipe logs and current run state before retrying.';
		}

		return $result;
	}

	/**
	 * Validate, publish, and run a run-now recipe.
	 *
	 * Runs the same publish validation as set-status on every press. The
	 * run-now trigger has nothing to configure, so adding it switches it on
	 * (`Trigger_CRUD_Service::add_to_recipe()`); a drafted one is reported
	 * as a missing live trigger and never switched on here — the recipe is the
	 * only thing this method publishes. A refused
	 * press returns the REJECTION_CODE WP_Error the REST layer maps to the
	 * 422 envelope; nothing is written or fired. A clean recipe is published
	 * (a direct status flip), the Pro run seam fires, and the new run's log
	 * is read back.
	 *
	 * @param int $recipe_id Recipe ID.
	 *
	 * @return array|WP_Error array{ recipe_log_id:int, run_number:int, recipe_run_completed:bool } on success.
	 */
	public function run_now( int $recipe_id ) {
		$recipe_post = get_post( $recipe_id );
		if ( ! $recipe_post || AUTOMATOR_POST_TYPE_RECIPE !== $recipe_post->post_type ) {
			return new WP_Error(
				'recipe_not_found',
				esc_html_x( 'Recipe not found or invalid type.', 'Run Now', 'uncanny-automator' ),
				array( 'status' => 404 )
			);
		}

		$refusal = $this->run_refusal( $recipe_id );
		if ( $refusal instanceof WP_Error ) {
			return $refusal;
		}

		$log_store = Database::get_execution_log_store();

		// Builder validation above checks live triggers; this guard also accepts import-style Run Now triggers.
		if ( ! $this->trigger_store->recipe_has_manual_trigger( new Recipe_Id( $recipe_id ) ) ) {
			return new WP_Error(
				'no_manual_trigger',
				esc_html_x( 'Recipe lacks a manual trigger.', 'Run Now', 'uncanny-automator' ),
				array( 'status' => 422 )
			);
		}

		// Publish only when the recipe isn't already live — a direct status
		// flip. Validation ran above; Recipe_Settings_Service::set_status()
		// would repeat the walk under a different message and write the
		// status on the already-live path.
		// The publish is its own operation, so its event fires before the run starts,
		// and every write the run makes (an action that switches a recipe off) is an
		// operation of its own instead of riding this one.
		if ( Recipe_Status::PUBLISH !== $recipe_post->post_status ) {
			$published = Recipe_Change_Events::operation(
				static function () use ( $recipe_id ) {
					try {
						Database::get_recipe_store()->update_status( $recipe_id, Recipe_Status::PUBLISH );
					} catch ( \Throwable $e ) {
						return new WP_Error(
							'status_update_failed',
							$e->getMessage(),
							array( 'status' => 500 )
						);
					}

					Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );

					return true;
				}
			);
			if ( is_wp_error( $published ) ) {
				return $published;
			}
		}

		// Publishing can activate a trigger that had no listener at request startup.
		Dispatcher::action( 'automator_refresh_trigger_hooks' );
		$before = $log_store->get_latest_recipe_log( $recipe_id );

		// Fire the Pro run seam (Rule 2 — via Events).
		Dispatcher::action( 'automator_pro_run_now_recipe', $recipe_id );

		// Firing the seam only ENQUEUES the trigger: Trigger_Engine::on_hook_fired()
		// hands it to Trigger_Queue, which drains on `shutdown` — long after this
		// response has been sent. Without an explicit drain the read-back below
		// finds no row on a recipe's first run (log id 0) and the PREVIOUS run's row
		// on every run after that, so the builder either never opens the log or
		// opens a stale one. Draining here is what makes the read-back mean what it
		// says. Safe to fire unconditionally: process_queue() returns immediately on
		// an empty queue, guards re-entry, and the later shutdown passes then find
		// nothing left to do. Hook name is Trigger_Queue::DRAIN_HOOK; kept as a
		// literal here so this service does not take a dependency on the legacy
		// queue class, matching the seam above.
		Dispatcher::action( 'automator_drain_trigger_queue' );

		// Read back the new run's log.
		$after = $log_store->get_latest_recipe_log( $recipe_id );
		if ( null === $after || ( null !== $before && (int) $after->ID <= (int) $before->ID ) ) {
			return new WP_Error(
				'recipe_execution_unconfirmed',
				esc_html_x( 'The runner did not confirm a new run. Check recipe logs and current run state before retrying.', 'Run Now', 'uncanny-automator' ),
				array( 'status' => 500 )
			);
		}
		$completed = (int) $after->completed;

		return array(
			'recipe_log_id'        => (int) $after->ID,
			'run_number'           => (int) $after->run_number,
			'recipe_run_completed' => Automator_Status::IN_PROGRESS !== $completed,
		);
	}

	/**
	 * Why a recipe may not run now, or null when it may: the same answer for
	 * the builder's Run now and for MCP's run_recipe.
	 *
	 * Run now is a Pro feature; a second run waits for the one in flight; and
	 * the walk sees the recipe exactly as set-status would: structure (a live
	 * trigger and a live action), run-as, and every live item's availability,
	 * required fields and placement. A drafted run-now trigger is a missing
	 * live trigger, not something to switch on. Runs on every press — a live
	 * recipe that no longer validates is refused.
	 *
	 * @param int $recipe_id Recipe ID.
	 *
	 * @return WP_Error|null
	 */
	private function run_refusal( int $recipe_id ): ?WP_Error {
		if ( ! $this->plan_service->is_pro() ) {
			return new WP_Error(
				'run_now_requires_pro',
				esc_html_x( 'Run Now requires a valid Uncanny Automator Pro license.', 'Run Now', 'uncanny-automator' ),
				array( 'status' => 403 )
			);
		}

		$latest = Database::get_execution_log_store()->get_latest_recipe_log( $recipe_id );
		if ( null !== $latest && Automator_Status::IN_PROGRESS === (int) $latest->completed ) {
			return new WP_Error(
				'automator_recipe_already_running',
				esc_html_x( 'Recipe is already running.', 'Run Now', 'uncanny-automator' ),
				array( 'status' => 409 )
			);
		}

		return Recipe_Violation_Service::rejection_error(
			Recipe_Violation_Service::instance()->evaluate_recipe_publish( $recipe_id ),
			esc_html_x( 'This recipe can’t be run yet.', 'Run Now', 'uncanny-automator' )
		);
	}
}
