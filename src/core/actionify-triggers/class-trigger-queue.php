<?php

namespace Uncanny_Automator\Actionify_Triggers;

/**
 * Fast Queue System for Uncanny Automator triggers.
 *
 * High-performance trigger processing with in-memory queueing, smart deduplication,
 * and failsafe redundancy for WordPress automation workflows.
 *
 * @package Uncanny_Automator\Actionify_Triggers
 * @since 6.7
 */
class Trigger_Queue {

	/**
	 * Maximum payload size for the fast queue.
	 *
	 * @var int
	 */
	const MAX_PAYLOAD_SIZE = 5242880; // 5 MiB

	/**
	 * Maximum number of queue drain iterations to prevent infinite loops.
	 *
	 * @var int
	 */
	const MAX_QUEUE_ITERATIONS = 10;

	/**
	 * Priority for the one-off listener.
	 *
	 * Needs to be unique to prevent collisions with other plugins.
	 *
	 * @var int
	 */
	const ONE_OFF_PRIORITY = 288662867;

	/**
	 * Priority for the wp_loaded hook.
	 *
	 * @var int
	 */
	const WP_LOADED_PRIORITY = 1;

	/**
	 * Early shutdown priority — runs BEFORE wp_ob_end_flush_all (priority 1)
	 * so HTTP response headers are still writable when closures fire
	 * (e.g. X-Automator-Redirect header).
	 *
	 * @var int
	 */
	const SHUTDOWN_PRIORITY_EARLY = 0;

	/**
	 * Late shutdown priority — catches triggers that fire during shutdown
	 * (e.g. app webhook handlers at priority 10).
	 *
	 * @var int
	 */
	const SHUTDOWN_PRIORITY_LATE = 900;

	/**
	 * In-memory queue for current request.
	 *
	 * @var array
	 */
	private $memory_queue = array();

	/**
	 * Processed triggers hash (deduplication).
	 *
	 * @var array
	 */
	private $processed_hashes = array();

	/**
	 * Whether shutdown hook is registered.
	 *
	 * @var bool
	 */
	private $hooks_registered = false;

	/**
	 * Whether the queue has been drained this request.
	 *
	 * @var bool
	 */
	private $queue_drained = false;

	/**
	 * Whether we're currently processing the queue.
	 *
	 * @var bool
	 */
	private $processing = false;

	/**
	 * Trigger codes that failed lookup this request — prevents repeated loopback requests.
	 *
	 * @var array
	 */
	private $failed_triggers = array();

	/**
	 * Argument packager instance.
	 *
	 * @var Trigger_Arguments
	 */
	private $packager;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->packager = new Trigger_Arguments();
		$this->init_redundancy();
	}

	/**
	 * Initialize redundancy system.
	 *
	 * @return void
	 */
	private function init_redundancy() {
		add_action( 'wp_ajax_automator_trigger_engine_process_trigger', array( $this, 'process_redundancy_request' ) );
		add_action( 'wp_ajax_nopriv_automator_trigger_engine_process_trigger', array( $this, 'process_redundancy_request' ) );
	}

	/**
	 * Queue a trigger for processing.
	 *
	 * @param string $trigger_code The trigger code.
	 * @param string $action_hook The WordPress hook.
	 * @param array  $args Hook arguments.
	 *
	 * @return bool True if queued, false if duplicate.
	 */
	public function enqueue( $trigger_code, $action_hook, $args ) {

		$trigger_hash = $this->generate_trigger_hash( $trigger_code, $args );

		// Skip if already processed in this request.
		if ( isset( $this->processed_hashes[ $trigger_hash ] ) ) {
			return false;
		}

		$hook_args = array(
			'trigger_code' => $trigger_code,
			'action_hook'  => $action_hook,
			'trigger_hash' => $trigger_hash,
		);

		do_action( 'automator_enqueue_trigger_before_enqueue_trigger', $hook_args );

		$this->processed_hashes[ $trigger_hash ] = true;

		$this->memory_queue[] = array(
			'hash'         => $trigger_hash,
			'trigger_code' => $trigger_code,
			'action_hook'  => $action_hook,
			'args'         => $args,
			'user_id'      => get_current_user_id(),
			'post_id'      => get_the_ID(),
			'timestamp'    => time(),
		);

		$this->ensure_shutdown_hooks();

		return true;
	}

	/**
	 * Process the in-memory queue. Idempotent.
	 *
	 * @return void
	 */
	public function process_queue() {
		// Prevent recursion.
		if ( $this->processing ) {
			return;
		}

		// If queue is empty, skip.
		if ( empty( $this->memory_queue ) ) {
			return;
		}

		// Reset drained flag if items are found after initial processing.
		if ( $this->queue_drained ) {
			$this->queue_drained = false;
		}

		$this->processing = true;

		$iteration = 0;

		// Process queue in batches to handle new items enqueued during processing.
		while ( ! empty( $this->memory_queue ) && $iteration < self::MAX_QUEUE_ITERATIONS ) {
			// Capture current batch to process.
			$batch              = $this->memory_queue;
			$this->memory_queue = array();

			++$iteration;

			foreach ( $batch as $item ) {
				$this->process_queue_item( $item );
			}
		}

		if ( ! empty( $this->memory_queue ) ) {
			$this->log( 'Queue processing hit max iterations (' . self::MAX_QUEUE_ITERATIONS . '). Remaining items discarded: ' . count( $this->memory_queue ) );
			$this->memory_queue = array();
		}

		// Mark as drained after processing.
		$this->queue_drained = true;
		$this->processing    = false;

		// Clear deduplication state between drain cycles.
		//
		// $processed_hashes prevents the same trigger firing twice within a
		// single queue drain — that protection is still active above. Clearing
		// here ensures that long-running processes (WP-CLI, Action Scheduler
		// batch workers) don't carry stale hashes into the next iteration,
		// which would silently drop legitimate trigger fires with matching args.
		$this->processed_hashes = array();
	}

	/**
	 * Process a single queued item.
	 *
	 * @param array $item Queue item to process.
	 *
	 * @return void
	 */
	private function process_queue_item( $item ) {

		$hook_args = array( 'item' => $item );
		do_action( 'automator_enqueue_trigger_before_process_queue_item', $hook_args );

		// If recipe parts haven't loaded yet (e.g., exit() was called before init:30),
		// delegate to the AJAX fallback where the full WordPress lifecycle runs.
		if ( ! did_action( 'automator_add_integration_recipe_parts' ) ) {
			$this->safe_process( $item );
			return;
		}

		$trigger_obj = Automator()->get_trigger( $item['trigger_code'] );

		// If trigger object doesn't exist, use redundancy fallback.
		if ( ! $trigger_obj ) {
			$this->safe_process( $item );
			return;
		}

		$this->execute_trigger( $trigger_obj, $item );
	}

	/**
	 * Execute one queued trigger with proper hook context.
	 *
	 * @param array $trigger_obj Trigger object from Automator.
	 * @param array $item Queue item.
	 *
	 * @return void
	 */
	private function execute_trigger( $trigger_obj, $item ) {

		$hook   = $item['action_hook'] ?? '';
		$method = $trigger_obj['validation_function'] ?? null;

		if ( empty( $hook ) || ! is_callable( $method ) ) {
			return;
		}

		$args = (array) ( $item['args'] ?? array() );

		$this->call_with_filter_context( $hook, $method, $args );
	}

	/**
	 * Redundancy processing via AJAX loopback.
	 *
	 * Includes a per-request circuit breaker keyed on the item hash (trigger code
	 * + args). This prevents sending duplicate loopback requests for the exact same
	 * event while still allowing different events for the same trigger code
	 * (e.g. WP_LOGIN for user A and user B in the same request).
	 *
	 * @param array $item Queue item to process safely.
	 *
	 * @return void
	 */
	private function safe_process( $item ) {

		$hash = $item['hash'] ?? '';

		// Circuit breaker: skip if this exact item was already sent this request.
		if ( isset( $this->failed_triggers[ $hash ] ) ) {
			return;
		}

		$this->failed_triggers[ $hash ] = true;

		// Send a request to wp-admin/admin-ajax.php.
		$url = admin_url( 'admin-ajax.php' );

		$packed = $this->packager->package( $item );

		if ( false === $packed ) {
			error_log( 'Automator trigger-queue: loopback skipped — package() failed for ' . ( $item['trigger_code'] ?? 'unknown' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return;
		}

		$payload = base64_encode( $packed ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		if ( strlen( $payload ) > self::MAX_PAYLOAD_SIZE ) {
			error_log( 'Automator trigger-queue: payload is ' . strlen( $payload ) . ' bytes (limit ' . self::MAX_PAYLOAD_SIZE . ') for ' . ( $item['trigger_code'] ?? 'unknown' ) . ' — sending anyway, may fail if server post_max_size is exceeded.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		$body = array(
			'action'    => 'automator_trigger_engine_process_trigger',
			'item'      => $payload,
			'signature' => hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) ),
		);

		$args = array(
			'timeout'  => 0.01,     // Fast request.
			'blocking' => false,    // Non-blocking.
			'body'     => $body,
			'cookies'  => $_COOKIE, // Preserve session context for anonymous/user triggers in loopback requests.
		);

		// Non blocking request.
		wp_remote_post( $url, $args );
	}

	/**
	 * Process redundancy request from AJAX.
	 *
	 * @return void
	 */
	public function process_redundancy_request() {

		$items     = isset( $_POST['item'] ) ? sanitize_text_field( wp_unslash( $_POST['item'] ) ) : ''; // phpcs:ignore
		$signature = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : ''; // phpcs:ignore

		// HMAC verification (user-independent, unlike nonces which are tied to the current user).
		if ( empty( $items ) || empty( $signature ) || ! hash_equals( hash_hmac( 'sha256', $items, wp_salt( 'nonce' ) ), $signature ) ) {
			wp_die();
		}

		// One-time-use guard — reject replayed payloads. The loopback request
		// traverses the public URL (load balancers, proxies, WAFs may log it).
		// Use first 16 chars of the signature as a compact replay key.
		$replay_key = 'automator_replay_' . substr( $signature, 0, 16 );
		if ( false !== get_transient( $replay_key ) ) {
			wp_die();
		}
		set_transient( $replay_key, 1, 60 );

		$package = $this->packager->unpack( base64_decode( $items ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( ! $package || ! isset( $package['args'] ) ) {
			wp_die();
		}

		// Reject stale payloads. The replay guard transient (above) is the primary
		// replay prevention. This timestamp check is defense-in-depth. 30 seconds
		// accommodates slow/loaded servers where PHP-FPM queuing + WordPress
		// bootstrap can exceed 5 seconds on shared hosting.
		$timestamp = $package['args']['timestamp'] ?? 0;
		if ( abs( time() - $timestamp ) > 30 ) {
			wp_die();
		}

		$trigger_code = $package['args']['trigger_code'] ?? '';
		$action_hook  = $package['args']['action_hook'] ?? '';
		$user_id      = $package['args']['user_id'] ?? 0;

		// Restore the user context from the payload since cookies may not reflect
		// the authenticated user (e.g. SSO login sets auth cookie but $_COOKIE is stale).
		if ( $user_id > 0 && get_userdata( $user_id ) ) {
			wp_set_current_user( $user_id );
		}

		$trigger_obj = Automator()->get_trigger( $trigger_code );

		if ( ! $trigger_obj ) {
			wp_die();
		}

		$this->call_with_filter_context( $action_hook, $trigger_obj['validation_function'], $package['args']['args'] );

		wp_die();
	}

	/**
	 * Call a function with filter context set for current_action().
	 *
	 * Sets the WordPress filter context so triggers can use current_action()
	 * to normalize parameters or run conditional checks.
	 *
	 * --- WHY THE FILTER STACK IS NOT POPPED ---
	 *
	 * The `finally { array_pop( $wp_current_filter ); }` block was deliberately
	 * removed. Here is the reason:
	 *
	 * Automator supports recipe chaining — recipe A can have an action that fires
	 * a WordPress hook (e.g. `some_action`), and recipe B can be configured to
	 * trigger on that same hook. When recipe A's action fires, recipe B's trigger
	 * validation runs inside the same call stack, while `some_action` is still the
	 * value on `$wp_current_filter`.
	 *
	 * If we popped the filter immediately after the callback returned, any trigger
	 * or condition in recipe B that calls `current_filter()` or `doing_filter()`
	 * would see an empty or incorrect filter context — breaking recipe chaining.
	 *
	 * By leaving the hook on the stack, the original filter remains "active" from
	 * WordPress's perspective for the full duration of any nested recipe processing.
	 * WordPress itself clears `$wp_current_filter` at request end, so there is no
	 * cross-request leak.
	 *
	 * --- TRADE-OFF TO BE AWARE OF ---
	 *
	 * After Automator's queue drains, `$wp_current_filter` will still contain the
	 * hook names that were pushed during processing. Any third-party code that runs
	 * after the queue and calls `doing_filter()` or `current_filter()` may see
	 * stale values. This is an accepted trade-off for recipe chaining support. Do
	 * not "fix" this by adding the pop back without first verifying that recipe
	 * chaining still works end-to-end.
	 *
	 * @param string   $hook     The WordPress hook name.
	 * @param callable $callback The function to call.
	 * @param array    $args     Arguments to pass to the callback.
	 *
	 * @return void
	 */
	private function call_with_filter_context( $hook, $callback, $args ) {
		if ( empty( $hook ) || ! is_callable( $callback ) ) {
			return;
		}

		global $wp_current_filter;
		$wp_current_filter[] = $hook; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		try {
			call_user_func_array( $callback, $args );
		} catch ( \Throwable $e ) {
			$this->log( $e->getMessage() );
		}
	}

	/**
	 * Generate consistent hash for deduplication.
	 *
	 * Replaces objects with lightweight "ClassName#spl_object_id" strings via
	 * fingerprint_value(), then serializes the cleaned structure. This avoids
	 * the 10-50ms cost of JSON-encoding full WP_Post/WC_Order objects while
	 * keeping the hash content-aware for all types — including mixed arrays
	 * like LearnDash's ['user' => WP_User, 'lesson' => WP_Post].
	 *
	 * @param string $trigger_code The trigger code.
	 * @param array  $args Hook arguments.
	 *
	 * @return string Hash for deduplication.
	 */
	private function generate_trigger_hash( $trigger_code, $args ) {

		// Replace objects with lightweight identity strings, then serialize.
		// This avoids serializing full WP_Post/WP_User objects (expensive) while
		// keeping the hash content-aware for all types including mixed arrays
		// (e.g. LearnDash's ['user' => WP_User, 'lesson' => WP_Post]).
		$safe_args = array_map( array( $this, 'fingerprint_value' ), $args );

		return md5( $trigger_code . '|' . get_current_user_id() . '|' . get_the_ID() . '|' . serialize( $safe_args ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * Convert a value to a serialize-safe fingerprint.
	 *
	 * TLDR: Makes any value safe to serialize() without the cost of serializing
	 * full WP_Post/WC_Order objects. Objects → "ClassName#id" string. Arrays →
	 * recurse. Everything else → pass through. The caller serializes the result
	 * to get a content-aware hash.
	 *
	 * Example: ['user' => WP_User#42, 'lesson' => WP_Post#100]
	 *        → ['user' => 'WP_User#42', 'lesson' => 'WP_Post#100']
	 *        → serialize() produces a distinct, cheap string.
	 *
	 * @param mixed $value Any hook argument value.
	 *
	 * @return mixed The serialize-safe representation.
	 */
	private function fingerprint_value( $value ) {
		if ( is_object( $value ) ) {
			return get_class( $value ) . '#' . spl_object_id( $value );
		}
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'fingerprint_value' ), $value );
		}

		return $value;
	}

	/**
	 * Ensure hooks are registered for queue processing.
	 *
	 * @return void
	 */
	private function ensure_shutdown_hooks() {

		if ( $this->hooks_registered ) {
			return;
		}

		// Try to process the queue after init.
		add_action( 'wp_loaded', array( $this, 'process_queue' ), self::WP_LOADED_PRIORITY );

		// Early pass: process triggers that fired before shutdown (form submissions, etc.).
		// Runs before wp_ob_end_flush_all (priority 1) so response headers are writable.
		add_action( 'shutdown', array( $this, 'process_queue' ), self::SHUTDOWN_PRIORITY_EARLY );

		// Late pass: process triggers that fire during shutdown (app webhook handlers, etc.).
		add_action( 'shutdown', array( $this, 'process_queue' ), self::SHUTDOWN_PRIORITY_LATE );

		$this->hooks_registered = true;
	}

	/**
	 * Log errors.
	 *
	 * @param string $message Error message.
	 *
	 * @return void
	 */
	private function log( $message ) {
		error_log( 'Automator trigger-queue: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
