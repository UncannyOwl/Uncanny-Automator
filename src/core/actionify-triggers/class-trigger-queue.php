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
	const MAX_PAYLOAD_SIZE = 2097152; // 2 MiB

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
	 * Priority for the shutdown hook.
	 *
	 * @var int
	 */
	const SHUTDOWN_PRIORITY = 900;

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

		// Process queue in batches to handle new items enqueued during processing.
		while ( ! empty( $this->memory_queue ) ) {
			// Capture current batch to process.
			$batch              = $this->memory_queue;
			$this->memory_queue = array();

			foreach ( $batch as $item ) {
				$this->process_queue_item( $item );
			}
		}

		// Mark as drained after processing.
		$this->queue_drained = true;
		$this->processing    = false;
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
	 * Redundancy processing via AJAX.
	 *
	 * @param array $item Queue item to process safely.
	 *
	 * @return void
	 */
	private function safe_process( $item ) {

		// Send a request to wp-admin/admin-ajax.php.
		$url = admin_url( 'admin-ajax.php' );

		$body = array(
			'action' => 'automator_trigger_engine_process_trigger',
			'item'   => base64_encode( $this->packager->package( $item ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'nonce'  => wp_create_nonce( 'automator_trigger_engine_process_trigger' ),
		);

		$args = array(
			'timeout'  => 0.01,     // Fast request.
			'blocking' => false,    // Non-blocking.
			'body'     => $body,
			'cookies'  => $_COOKIE, // Required to preserve user context for anonymous/user triggers in loopback requests.
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

		$nonce = automator_filter_input( 'nonce', INPUT_POST );

		// Nonce check for security.
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'automator_trigger_engine_process_trigger' ) ) {
			wp_die( 'Invalid nonce' );
		}

		$items = isset( $_POST['item'] ) ? sanitize_text_field( wp_unslash( $_POST['item'] ) ) : ''; // phpcs:ignore

		$package = $this->packager->unpack( base64_decode( $items ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( ! $package || ! isset( $package['args'] ) ) {
			wp_die();
		}

		$trigger_code = $package['args']['trigger_code'] ?? '';
		$action_hook  = $package['args']['action_hook'] ?? '';
		$trigger_obj  = Automator()->get_trigger( $trigger_code );

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
	 * to normalize parameters or run conditional checks. WordPress clears
	 * the filter stack at request end, so no cleanup is needed.
	 *
	 * @param string   $hook The WordPress hook name.
	 * @param callable $callback The function to call.
	 * @param array    $args Arguments to pass to the callback.
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
	 * @param string $trigger_code The trigger code.
	 * @param array  $args Hook arguments.
	 *
	 * @return string Hash for deduplication.
	 */
	private function generate_trigger_hash( $trigger_code, $args ) {

		$hash_data = array(
			'trigger_code' => $trigger_code,
			'user_id'      => get_current_user_id(),
			'post_id'      => get_the_ID(),
			'trigger_args' => $args,
		);

		return md5( wp_json_encode( $hash_data ) );
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

		// Remaining items in queue will be processed on shutdown.
		add_action( 'shutdown', array( $this, 'process_queue' ), self::SHUTDOWN_PRIORITY );

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
		if ( function_exists( 'automator_log' ) ) {
			automator_log( $message, 'trigger-queue', true, 'trigger-queue' );
		}
	}
}
