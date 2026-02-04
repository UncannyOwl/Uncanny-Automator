<?php
/**
 * Instagram Publish Retry Trait
 *
 * Provides automatic retry functionality for Instagram publish actions
 * when the "Media ID is not available" (error 9007) occurs.
 *
 * @package Uncanny_Automator
 * @since 6.14
 */

namespace Uncanny_Automator;

/**
 * Trait Instagram_Publish_Retry
 *
 * @package Uncanny_Automator
 */
trait Instagram_Publish_Retry {

	/**
	 * Maximum number of retry attempts.
	 *
	 * @var int
	 */
	protected $max_retry_attempts = 10;

	/**
	 * Delay between retries in seconds.
	 *
	 * @var int
	 */
	protected $retry_delay = 60;

	/**
	 * WordPress cron hook name.
	 *
	 * @var string
	 */
	protected $retry_hook = 'automator_instagram_publish_retry';

	/**
	 * Action code for filtering.
	 *
	 * @var string
	 */
	protected $retry_action_code = 'INSTAGRAM_PUBLISH_PHOTO';

	/**
	 * Register all retry-related hooks.
	 *
	 * Call this method in the action class constructor or integration setup.
	 *
	 * @return void
	 */
	public function register_retry_hooks() {
		add_filter( 'automator_get_action_completed_status', array( $this, 'retry_set_completed_status' ), 10, 7 );
		add_filter( 'automator_get_action_error_message', array( $this, 'retry_set_error_message' ), 10, 7 );
		add_action( 'automator_action_created', array( $this, 'retry_persist_meta_data' ), 10, 1 );
		add_action( $this->retry_hook, array( $this, 'handle_retry' ), 10, 1 );
	}

	/**
	 * Check if error is the "Media ID not available" error (code 9007).
	 *
	 * @param string $message The error message.
	 *
	 * @return bool True if media unavailable error.
	 */
	public function is_media_unavailable_error( $message ) {
		return false !== stripos( $message, 'Media ID is not available' );
	}

	/**
	 * Extract container_id from the last API response if available.
	 *
	 * The API proxy injects container_id into the response data object
	 * when a publish error occurs, enabling retry optimization.
	 *
	 * @return string|null The container_id if found, null otherwise.
	 */
	public function extract_container_id_from_last_response() {
		$last_response = Api_Server::$last_response;

		if ( empty( $last_response ) || is_wp_error( $last_response ) ) {
			return null;
		}

		$response_body = json_decode( wp_remote_retrieve_body( $last_response ), true );

		// The API proxy adds container_id to the response object, which gets wrapped in 'data' by respondWithData().
		if ( ! empty( $response_body['data']['container_id'] ) ) {
			return $response_body['data']['container_id'];
		}

		return null;
	}

	/**
	 * Schedule a retry for the Instagram publish action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data (passed by reference).
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $body        The API request body to retry.
	 * @param int   $attempt     The current attempt number.
	 *
	 * @return void
	 */
	public function schedule_retry( $user_id, &$action_data, $recipe_id, $body, $attempt ) {
		$retry_data = array(
			'user_id'       => $user_id,
			'action_id'     => $action_data['ID'],
			'recipe_id'     => $recipe_id,
			'recipe_log_id' => $action_data['recipe_log_id'],
			'body'          => $body,
			'attempt'       => $attempt,
		);

		// Set await flag to trigger COMPLETED_AWAITING status via filter.
		$action_data['args']['await'] = array( 'instagram_retry' => $retry_data );

		wp_schedule_single_event( time() + $this->retry_delay, $this->retry_hook, array( $retry_data ) );
	}

	/**
	 * Handle the scheduled retry event.
	 *
	 * @param array $retry_data The retry data.
	 *
	 * @return void
	 */
	public function handle_retry( $retry_data ) {
		try {
			// Make API request (implementation differs per framework).
			$this->execute_retry_api_request( $retry_data['body'] );

			// Success - mark completed.
			Automator()->db->action->mark_complete(
				$retry_data['action_id'],
				$retry_data['recipe_log_id'],
				Automator_Status::COMPLETED
			);
			Automator()->db->recipe->mark_complete( $retry_data['recipe_log_id'], Automator_Status::COMPLETED );

		} catch ( \Exception $e ) {
			$this->handle_retry_failure( $retry_data, $e );
		}
	}

	/**
	 * Handle a retry failure - either schedule another retry or mark as failed.
	 *
	 * @param array      $retry_data The retry data.
	 * @param \Exception $e          The exception.
	 *
	 * @return void
	 */
	protected function handle_retry_failure( $retry_data, $e ) {
		// Check if we can retry (retryable error and attempts remaining).
		if ( ! $this->is_media_unavailable_error( $e->getMessage() ) || $retry_data['attempt'] >= $this->max_retry_attempts ) {
			// Final failure - either max attempts reached or non-retryable error.
			$error_msg = $retry_data['attempt'] >= $this->max_retry_attempts
				? sprintf(
					// translators: 1: Max attempts, 2: Error message
					esc_html_x( 'Failed after %1$d retry attempts. Last error: %2$s', 'Instagram', 'uncanny-automator' ),
					$this->max_retry_attempts,
					$e->getMessage()
				)
				: $e->getMessage();

			Automator()->db->action->mark_complete(
				$retry_data['action_id'],
				$retry_data['recipe_log_id'],
				Automator_Status::COMPLETED_WITH_ERRORS,
				$error_msg
			);
			Automator()->db->recipe->mark_complete(
				$retry_data['recipe_log_id'],
				Automator_Status::COMPLETED_WITH_ERRORS
			);

			return;
		}

		// Try to extract container_id from the response for retry optimization.
		$container_id = $this->extract_container_id_from_last_response();
		if ( ! empty( $container_id ) && empty( $retry_data['body']['container_id'] ) ) {
			$retry_data['body']['container_id'] = $container_id;
		}

		// Schedule another retry.
		++$retry_data['attempt'];
		wp_schedule_single_event( time() + $this->retry_delay, $this->retry_hook, array( $retry_data ) );

		// Update action log with retry status.
		Automator()->db->action->mark_complete(
			$retry_data['action_id'],
			$retry_data['recipe_log_id'],
			Automator_Status::COMPLETED_AWAITING,
			sprintf(
				// translators: 1: Current attempt number, 2: Max attempts
				esc_html_x( 'Publishing in progress. Retry attempt %1$d of %2$d.', 'Instagram', 'uncanny-automator' ),
				$retry_data['attempt'],
				$this->max_retry_attempts
			)
		);
	}

	/**
	 * Execute the API request for retry.
	 *
	 * Override this method in the using class to provide framework-specific API call.
	 *
	 * @param array $body The API request body.
	 *
	 * @return array The API response.
	 * @throws \Exception On API error.
	 */
	protected function execute_retry_api_request( $body ) {
		$instagram = Automator()->helpers->recipe->instagram->options;
		return $instagram->api_request( $body, null );
	}

	/**
	 * Filter: Set completed status to COMPLETED_AWAITING during retry.
	 *
	 * @param int    $completed      The completion status.
	 * @param int    $user_id        The user ID.
	 * @param array  $action_data    The action data.
	 * @param int    $recipe_id      The recipe ID.
	 * @param string $error_message  The error message.
	 * @param int    $recipe_log_id  The recipe log ID.
	 * @param array  $args           Additional arguments.
	 *
	 * @return int The completion status.
	 */
	public function retry_set_completed_status( $completed, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args ) {
		if ( ( $action_data['meta']['code'] ?? '' ) !== $this->retry_action_code ) {
			return $completed;
		}
		if ( isset( $args['await']['instagram_retry'] ) ) {
			return Automator_Status::COMPLETED_AWAITING;
		}
		return $completed;
	}

	/**
	 * Filter: Set error message during retry.
	 *
	 * @param string $message        The message.
	 * @param int    $user_id        The user ID.
	 * @param array  $action_data    The action data.
	 * @param int    $recipe_id      The recipe ID.
	 * @param string $error_message  The error message.
	 * @param int    $recipe_log_id  The recipe log ID.
	 * @param array  $args           Additional arguments.
	 *
	 * @return string The message.
	 */
	public function retry_set_error_message( $message, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args ) {
		if ( ( $action_data['meta']['code'] ?? '' ) !== $this->retry_action_code ) {
			return $message;
		}
		if ( isset( $args['await']['instagram_retry'] ) ) {
			$attempt = $args['await']['instagram_retry']['attempt'];
			return sprintf(
				// translators: 1: Current attempt number, 2: Max attempts
				esc_html_x( 'Publishing in progress. Retry attempt %1$d of %2$d scheduled.', 'Instagram', 'uncanny-automator' ),
				$attempt,
				$this->max_retry_attempts
			);
		}
		return $message;
	}

	/**
	 * Action: Persist retry data to action log meta.
	 *
	 * @param array $action_arguments The action arguments.
	 *
	 * @return void
	 */
	public function retry_persist_meta_data( $action_arguments ) {
		if ( empty( $action_arguments['args']['await']['instagram_retry'] ) ) {
			return;
		}
		Automator()->db->action->add_meta(
			$action_arguments['user_id'],
			$action_arguments['action_log_id'],
			$action_arguments['action_id'],
			'instagram_retry_data',
			wp_json_encode( $action_arguments['args']['await']['instagram_retry'] )
		);
	}
}
