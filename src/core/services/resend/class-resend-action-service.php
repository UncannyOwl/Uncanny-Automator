<?php
namespace Uncanny_Automator;

use Uncanny_Automator\App\Infrastructure\Api_Log\Api_Log_Compressor;
use Uncanny_Automator\Webhooks\Response_Validator;

/**
 * Shared core behind the per-action "Resend" button and the bulk
 * "Resend App actions" tool. Replays the stored API payload; never
 * re-executes the action or re-resolves tokens.
 */
class Resend_Action_Service {

	/**
	 * Decode a uap_api_log column value into a PHP value, handling the
	 * current serialized blob AND the future gz1: format.
	 *
	 * @param mixed $raw
	 * @return mixed array for gz1:/JSON; object-or-array for legacy serialized; array() on corrupt input.
	 */
	public static function decode_api_log_value( $raw ) {

		if ( ! is_string( $raw ) ) {
			return $raw;
		}

		// Future gz1: format (PR #7232 / 7.4.0). Prefer the canonical decoder when present.
		if ( 0 === strpos( $raw, 'gz1:' ) ) {
			if ( class_exists( Api_Log_Compressor::class ) ) {
				return Api_Log_Compressor::decode( $raw );
			}
			$bytes = base64_decode( substr( $raw, 4 ), true );
			if ( false === $bytes ) {
				return array();
			}
			$json = @gzuncompress( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === $json ) {
				return array();
			}
			$decoded = json_decode( $json, true );
			return is_array( $decoded ) ? $decoded : array();
		}

		// General gz: helper (Automator_Compression), then unserialize.
		if ( 0 === strpos( $raw, 'gz:' ) && class_exists( Automator_Compression::class ) ) {
			return maybe_unserialize( Automator_Compression::maybe_decompress_string( $raw ) );
		}

		// Current format: PHP-serialized blob.
		return maybe_unserialize( $raw );
	}

	/**
	 * Replay the stored API request for one action_log id. Mirrors the
	 * per-action Resend button: re-fires the stored payload, records ONE
	 * uap_api_log_response row, does NOT mutate the action_log row.
	 *
	 * @param int $action_log_id
	 * @return array{ ok: bool, outcome: string, message: string, api_log_id: int }
	 */
	public function resend_action( $action_log_id ) {

		$action_log_id = absint( $action_log_id );
		$api_request   = Automator()->db->api->get_by_log_id( 'action', $action_log_id );

		if ( empty( $api_request ) || empty( $api_request->params ) ) {
			return array( 'ok' => false, 'outcome' => 'skipped', 'message' => 'No API log to resend', 'api_log_id' => 0 );
		}

		// Intentionally NOT gated on the action's current status — this mirrors the
		// per-action Resend button: it replays whatever request was logged. The bulk
		// tool pre-filters to failed rows, but a direct caller could re-fire an
		// already-completed action; repeated re-fires can produce duplicate vendor
		// effects, which is the caller's responsibility, not this seam's.
		$api_log_id = (int) $api_request->ID;

		// Webhook branch.
		if ( 'internal:webhook' === $api_request->endpoint ) {
			return $this->resend_webhook( $api_request, $api_log_id );
		}

		$params = self::decode_api_log_value( $api_request->params );

		// The 7.0+ app-client infra (Api_Client::send) logs the request as an immutable
		// Api_Request object, not the legacy array. api_call() needs the array shape, so
		// flatten it back via the value object's getters before replaying.
		if ( $params instanceof \Uncanny_Automator\App\Infrastructure\Api_Client\Api_Request ) {
			$params = $this->api_request_to_params( $params );
		}

		if ( is_array( $params ) ) {
			// Flag the replay as a resend. App_Integrations\Api_Caller hooks the
			// integration's {slug}_api_call filter on this flag and re-injects the
			// CURRENT credential, so an expired/rotated token captured in the stored
			// body is replaced with the live one before the request is re-fired.
			$params['resend'] = true;
		}

		try {
			$this->fire_api_call( $params );
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), 'resend_action', false );
			$this->record_retry( $action_log_id, $api_log_id, Automator_Status::get_class_name( Automator_Status::COMPLETED_WITH_ERRORS ), $e->getMessage() );
			return array( 'ok' => false, 'outcome' => 'failed', 'message' => $e->getMessage(), 'api_log_id' => $api_log_id );
		}

		$message = __( 'The request has been successfully resent', 'uncanny-automator' );
		$this->record_retry( $action_log_id, $api_log_id, Automator_Status::get_class_name( Automator_Status::COMPLETED ), $message );
		$this->mark_action_resolved( $action_log_id );
		do_action( 'automator_recipe_app_request_resent', $action_log_id, array( 'success' => true, 'message' => $message ) );

		return array( 'ok' => true, 'outcome' => 'resent', 'message' => $message, 'api_log_id' => $api_log_id );
	}

	/**
	 * Fire the cloud API call. Isolated in its own protected method so tests can
	 * subclass-and-override it to force failure without a network call (no
	 * production test hook). On 7.4.0 this is the seam PR #7232 re-points to
	 * Automator_Platform.
	 *
	 * @param mixed $params
	 * @return mixed
	 */
	protected function fire_api_call( $params ) {
		return Api_Server::api_call( $params );
	}

	/**
	 * Flatten a new-infra Api_Request value object back into the legacy params array shape
	 * Api_Server::api_call() expects. Only endpoint + body are strictly required by api_call();
	 * method and action (logging context) are carried through when present.
	 *
	 * @param \Uncanny_Automator\App\Infrastructure\Api_Client\Api_Request $request
	 * @return array
	 */
	private function api_request_to_params( $request ) {
		$params = array(
			'endpoint' => $request->endpoint(),
			'body'     => $request->body(),
			'method'   => $request->method(),
		);

		$action_data = $request->action_data();
		if ( null !== $action_data ) {
			$params['action'] = $action_data;
		}

		return $params;
	}

	/**
	 * Webhook replay branch.
	 *
	 * @param object $api_request
	 * @param int    $api_log_id
	 * @return array
	 */
	protected function resend_webhook( $api_request, $api_log_id ) {
		$params  = (array) self::decode_api_log_value( $api_request->params );
		$request = (array) self::decode_api_log_value( isset( $api_request->request ) ? $api_request->request : '' );

		try {
			if ( ! isset( $request['http_url'], $params['method'] ) ) {
				throw new \Exception( 'Invalid data. Cannot find "http_url" or "method".', 400 );
			}
			Response_Validator::validate_webhook_response(
				Automator_Send_Webhook::call_webhook( $request['http_url'], $params, $params['method'] )
			);
		} catch ( \Exception $e ) {
			$this->record_retry( (int) $api_request->item_log_id, $api_log_id, Automator_Status::get_class_name( Automator_Status::COMPLETED_WITH_ERRORS ), $e->getMessage() );
			return array( 'ok' => false, 'outcome' => 'failed', 'message' => $e->getMessage(), 'api_log_id' => $api_log_id );
		}

		$message = __( 'The request has been successfully resent', 'uncanny-automator' );
		$this->record_retry( (int) $api_request->item_log_id, $api_log_id, Automator_Status::get_class_name( Automator_Status::COMPLETED ), $message );
		$this->mark_action_resolved( (int) $api_request->item_log_id );
		do_action( 'automator_recipe_webhook_request_replayed', (int) $api_request->item_log_id, array( 'success' => true, 'message' => $message ) );

		return array( 'ok' => true, 'outcome' => 'resent', 'message' => $message, 'api_log_id' => $api_log_id );
	}

	/**
	 * Insert ONE uap_api_log_response row using the KNOWN api_log id
	 * (fixes the legacy MAX(id) heuristic).
	 *
	 * @return int|false
	 */
	protected function record_retry( $item_log_id, $api_log_id, $result, $message ) {
		global $wpdb;
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'uap_api_log_response',
			array( 'api_log_id' => (int) $api_log_id, 'item_log_id' => (int) $item_log_id, 'result' => (string) $result, 'message' => (string) $message ),
			array( '%d', '%d', '%s', '%s' )
		);
		return false !== $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * After a successful resend, flip the action_log to Completed and — only when no other
	 * action in the same recipe run is still in an error state — flip the recipe_log to
	 * "Completed with notice" (the run recovered via a manual resend). Raw status writes: this
	 * refreshes the DISPLAYED status only; it does not re-run the recipe or re-fire completion
	 * hooks/closures.
	 *
	 * @param int $action_log_id
	 * @return void
	 */
	protected function mark_action_resolved( $action_log_id ) {

		global $wpdb;

		$action_log_id = (int) $action_log_id;
		if ( $action_log_id <= 0 ) {
			return;
		}

		// The two status writes below are intentionally NOT wrapped in a transaction.
		// If execution dies between them the action shows Completed while the run still
		// shows errored — a benign, self-correcting window: the next resend (or any run
		// status recompute) re-derives the run status from all of its actions.

		// 1) The action itself is now Completed.
		$wpdb->update(
			$wpdb->prefix . 'uap_action_log',
			array( 'completed' => Automator_Status::COMPLETED ),
			array( 'ID' => $action_log_id ),
			array( '%d' ),
			array( '%d' )
		);

		// 2) Re-resolve the run's status from ALL its action statuses and persist it.
		$recipe_log_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT automator_recipe_log_id FROM {$wpdb->prefix}uap_action_log WHERE ID = %d",
				$action_log_id
			)
		);
		if ( $recipe_log_id <= 0 ) {
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'uap_recipe_log',
			array( 'completed' => $this->resolve_recipe_status( $recipe_log_id ) ),
			array( 'ID' => $recipe_log_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Recompute a run's status from all its action statuses. Delegates to the recipe runner's
	 * canonical Recipe_Status_Resolver — which derives the status from EVERY action (so a
	 * still-failed OR still-pending sibling correctly keeps the run errored/in-progress instead
	 * of letting us falsely mark it resolved) and honors the complete_with_notice flag. Falls
	 * back to a minimal local recompute only when that app-layer service is unavailable.
	 *
	 * @param int $recipe_log_id
	 * @return int An Automator_Status constant.
	 */
	private function resolve_recipe_status( $recipe_log_id ) {

		$recipe_log_id = (int) $recipe_log_id;
		$resolver      = '\Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Status_Resolver';

		if ( class_exists( $resolver ) ) {
			// complete_with_notice marks a fully-resolved run "Completed with notice" (recovered
			// via resend); a still-errored or still-pending sibling overrides it.
			return (int) ( new $resolver() )->resolve( $recipe_log_id, array( 'complete_with_notice' => true ) );
		}

		// Fallback (app layer absent): only mark notice when no sibling is in an error state.
		global $wpdb;
		$still_failing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}uap_action_log
				 WHERE automator_recipe_log_id = %d AND completed IN ( %d, %d, %d )",
				$recipe_log_id,
				Automator_Status::COMPLETED_WITH_ERRORS,
				Automator_Status::IN_PROGRESS_WITH_ERROR,
				Automator_Status::FAILED
			)
		);

		return $still_failing > 0 ? Automator_Status::COMPLETED_WITH_ERRORS : Automator_Status::COMPLETED_WITH_NOTICE;
	}
}
