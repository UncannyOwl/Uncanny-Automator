<?php
/**
 * Observe the runner acknowledgment for a manual recipe request.
 *
 * @package Uncanny_Automator
 */

declare( strict_types=1 );

namespace Uncanny_Automator\App\Events;

/**
 * Keeps temporary WordPress listeners inside the event boundary.
 */
final class Manual_Recipe_Run {

	/**
	 * Whether a listener is registered for manual recipe requests.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return false !== has_action( 'automator_pro_run_now_recipe' );
	}

	/**
	 * Dispatch once and capture acceptance or a synchronous manual-trigger log.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @param bool $drain_queue Run the whole trigger queue, not only this recipe's trigger, before returning the receipt.
	 * @return array{recipe_log_id:int,queue_id:string,dispatch_failed:bool} Request acknowledgment.
	 */
	public function dispatch( int $recipe_id, bool $drain_queue = false ): array {
		$recipe_log_id   = 0;
		$queue_id        = '';
		$dispatch_failed = false;
		$listener        = static function ( $process ) use ( $recipe_id, &$recipe_log_id ): void {
			$process_recipe_id = is_array( $process ) ? (int) ( $process['recipe_id'] ?? 0 ) : 0;
			$trigger_id        = is_array( $process ) ? (int) ( $process['trigger_id'] ?? 0 ) : 0;
			$trigger_recipe_id = (int) wp_get_post_parent_id( $trigger_id );
			if ( $recipe_id === $process_recipe_id
				&& $recipe_id === $trigger_recipe_id
				&& 'RECIPE_MANUAL_TRIGGER_ANON' === get_post_meta( $trigger_id, 'code', true ) ) {
				$recipe_log_id = max( $recipe_log_id, (int) ( $process['recipe_log_id'] ?? 0 ) );
			}
		};
		$queue_listener  = static function ( $item ) use ( $recipe_id, &$queue_id ): void {
			if ( ! is_array( $item ) || 'RECIPE_MANUAL_TRIGGER_ANON' !== ( $item['trigger_code'] ?? null )
				|| 'automator_pro_run_now_recipe' !== ( $item['action_hook'] ?? null )
				|| ! is_array( $item['args'] ?? null ) || ! is_scalar( $item['args'][0] ?? null )
				|| $recipe_id !== (int) $item['args'][0] || ! is_string( $item['hash'] ?? null ) ) {
				return;
			}
			$queue_id = $item['hash'];
		};

		// Core queues the trigger itself until shutdown, so no log yet does not
		// mean the request failed. Match the accepted item to this recipe; a
		// different queued event or trigger completion cannot prove this run.
		add_action( 'automator_trigger_completed', $listener );
		add_action( 'automator_enqueue_trigger_after_enqueue_trigger', $queue_listener );
		try {
			Dispatcher::action( 'automator_pro_run_now_recipe', $recipe_id );
			if ( $drain_queue ) {
				// This drains the whole queue, not only this recipe's item, on
				// purpose; do not narrow it. MCP run_recipe needs the run inside its
				// Account_Access_Guard. Left queued, the trigger would run at
				// shutdown, after the guard has ended, so the recipe the AI app
				// asked for would run unguarded. Triggers this run's actions queue
				// must drain too, or a chained recipe gets past the guard. Draining
				// queues nothing new: the queue runs in this request anyway, and this
				// runs it sooner, inside the guard. Like every drain, it ends duplicate
				// tracking, so an identical event fired later in this request runs
				// again (Trigger_Queue::process_queue()). Run now
				// (Recipe_Execution_Service::run_now()) drains the same way. The
				// earlier rule against draining unrelated queued work held only
				// while run_recipe needed just the acceptance receipt. Draining
				// inside this try also lets the receipt listeners see the run.
				Dispatcher::action( 'automator_drain_trigger_queue' );
			}
		} catch ( \Throwable $exception ) {
			if ( $recipe_log_id <= 0 && '' === $queue_id ) {
				throw $exception;
			}
			// A later listener can throw after acceptance. Preserve the receipt so
			// callers do not repeat accepted work, while exposing the uncertainty.
			$dispatch_failed = true;
		} finally {
			remove_action( 'automator_trigger_completed', $listener );
			remove_action( 'automator_enqueue_trigger_after_enqueue_trigger', $queue_listener );
		}

		return array(
			'recipe_log_id'   => $recipe_log_id,
			'queue_id'        => $queue_id,
			'dispatch_failed' => $dispatch_failed,
		);
	}
}
