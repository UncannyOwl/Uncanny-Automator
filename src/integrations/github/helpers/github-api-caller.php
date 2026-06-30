<?php

namespace Uncanny_Automator\Integrations\Github;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Github_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Github_App_Helpers $helpers
 * @property Github_Webhooks $webhooks
 */
class Github_Api_Caller extends Api_Caller {

	////////////////////////////////////////////////////////////
	// Abstract override methods
	////////////////////////////////////////////////////////////

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * @param array $credentials The raw credentials from options to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return array - The prepared credentials for user or Bot requests.
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		return wp_json_encode(
			array(
				'vault_signature' => $credentials['vault_signature'],
				'github_id'       => $credentials['github_id'],
			)
		);
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Get the repos for the user.
	 *
	 * @param bool $refresh Whether to force a refresh of the repos.
	 *
	 * @return array
	 */
	public function get_user_repos( $refresh = false ) {

		// Get the repos from the option.
		$repos = automator_get_option( $this->helpers->get_const( 'REPO_OPTION' ) );

		// If the repos are not set, or we're forcing a refresh, get the repos from the API.
		if ( empty( $repos ) || $refresh ) {
			try {
				$response = $this->api_request( 'get_user_repos' );
				$repos    = $response['data']['repos'] ?? array();
			} catch ( Exception $e ) {
				return array();
			}

			// Update the option.
			automator_update_option( $this->helpers->get_const( 'REPO_OPTION' ), $repos );
		}

		return $repos;
	}

	/**
	 * Get repository branches.
	 *
	 * @param string $repo_name
	 * @param string $owner
	 * @param bool   $refresh
	 *
	 * @return array
	 */
	public function get_repo_branches( $repo_name, $owner, $refresh = false ) {
		return $this->get_repo_ui_data( 'branches', $repo_name, $owner, $refresh );
	}

	/**
	 * Get repository labels.
	 *
	 * @param string $repo_name
	 * @param string $owner
	 * @param bool   $refresh
	 *
	 * @return array
	 */
	public function get_repo_labels( $repo_name, $owner, $refresh = false ) {
		return $this->get_repo_ui_data( 'labels', $repo_name, $owner, $refresh );
	}

	/**
	 * Get the issues for a repo.
	 *
	 * @param string $repo
	 * @param string $owner
	 * @param bool $refresh
	 *
	 * @return array
	 */
	public function get_repo_issues( $repo, $owner, $refresh = false ) {
		return $this->get_repo_ui_data( 'issues', $repo, $owner, $refresh );
	}

	/**
	 * Get the pull requests for a repo.
	 *
	 * @param string $repo
	 * @param string $owner
	 * @param bool $refresh
	 *
	 * @return array
	 */
	public function get_repo_pull_requests( $repo, $owner, $refresh = false ) {
		return $this->get_repo_ui_data( 'pull_requests', $repo, $owner, $refresh );
	}

	/**
	 * Get the tags for a repo.
	 *
	 * @param string $repo
	 * @param string $owner
	 * @param bool $refresh
	 *
	 * @return array
	 */
	public function get_repo_tags( $repo, $owner, $refresh = false ) {
		return $this->get_repo_ui_data( 'tags', $repo, $owner, $refresh );
	}

	/**
	 * Get cached repository UI data or fetch from API with caching.
	 *
	 * @param string $data_key The data key in the response
	 * @param string $repo The repository name
	 * @param string $owner The repository owner
	 * @param bool $refresh Whether to force refresh
	 *
	 * @return array
	 */
	private function get_repo_ui_data( $data_key, $repo, $owner, $refresh = false ) {
		// Set the cache key.
		$cache_key = "github_repo_{$data_key}_{$owner}_{$repo}";

		// If not refreshing, get the cached data.
		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Get fresh data from the API.
		$body = array(
			'action' => "get_repo_{$data_key}",
			'repo'   => $repo,
			'owner'  => $owner,
		);

		try {
			$response = $this->api_request( $body );
			$data     = $response['data'][ $data_key ] ?? array();
		} catch ( Exception $e ) {
			$data = array();
		}

		// Cache for 5 minutes.
		set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

		return $data;
	}

	/**
	 * Issue the remote `create_webhook` action against GitHub for a repository.
	 * Pure API I/O — config persistence is owned by `Github_Webhooks` via the manager trait.
	 *
	 * @param string $owner       Repository owner.
	 * @param string $repo_name   Repository name.
	 * @param string $target_url  The webhook target URL the manager will receive events on.
	 * @param string $secret      The webhook secret for HMAC signature verification.
	 * @param array  $events      The events to subscribe to.
	 *
	 * @return array {
	 *     hook_id: int|string|null,
	 * }
	 *
	 * @throws Exception When events are empty or the API rejects the request.
	 */
	public function do_create_webhook( $owner, $repo_name, $target_url, $secret, $events ) {

		if ( empty( $events ) ) {
			throw new Exception( esc_html_x( 'Please select at least one event to subscribe your webhook to.', 'GitHub', 'uncanny-automator' ) );
		}

		$args = array(
			'action'      => 'create_webhook',
			'owner'       => $owner,
			'repo'        => $repo_name,
			'webhook_url' => $target_url,
			'events'      => wp_json_encode( $events ),
			'secret'      => $secret,
		);

		$response = $this->api_request( $args );
		// Accept the platform's normalized 200 alongside GitHub's native 201 Created.
		if ( ! in_array( absint( $response['statusCode'] ?? 0 ), array( 200, 201 ), true ) ) {
			throw new Exception( esc_html( $response['data']['message'] ?? '' ) );
		}

		return array(
			'hook_id' => $response['data']['id'] ?? null,
		);
	}

	/**
	 * Issue the remote `update_webhook` action against GitHub — change the events the remote hook fires on.
	 * Pure API I/O — config persistence is owned by `Github_Webhooks` via the manager trait.
	 *
	 * @param string     $owner      Repository owner.
	 * @param string     $repo_name  Repository name.
	 * @param string     $target_url The webhook target URL.
	 * @param string     $secret     The webhook secret for HMAC signature verification.
	 * @param array      $events     The events to subscribe to.
	 * @param int|string $hook_id    The remote webhook ID.
	 *
	 * @return void
	 *
	 * @throws Exception When events are empty or the API rejects the request.
	 */
	public function do_update_webhook( $owner, $repo_name, $target_url, $secret, $events, $hook_id ) {

		if ( empty( $events ) ) {
			throw new Exception( esc_html_x( 'Please select at least one event to subscribe your webhook to.', 'GitHub', 'uncanny-automator' ) );
		}

		$args = array(
			'action'      => 'update_webhook',
			'webhook_id'  => $hook_id,
			'owner'       => $owner,
			'repo'        => $repo_name,
			'webhook_url' => $target_url,
			'events'      => wp_json_encode( $events ),
			'secret'      => $secret,
		);

		$response = $this->api_request( $args );
		if ( 200 !== $response['statusCode'] ) {
			throw new Exception( esc_html( $response['data']['message'] ) );
		}
	}

	/**
	 * Issue the remote `delete_webhook` action against GitHub for a repository.
	 * Pure API I/O — config persistence is owned by `Github_Webhooks` via the manager trait.
	 *
	 * @param string     $owner     Repository owner.
	 * @param string     $repo_name Repository name.
	 * @param int|string $hook_id   The remote webhook ID.
	 *
	 * @return void
	 *
	 * @throws Exception When the API rejects the request.
	 */
	public function do_delete_webhook( $owner, $repo_name, $hook_id ) {

		$args = array(
			'action'     => 'delete_webhook',
			'owner'      => $owner,
			'repo'       => $repo_name,
			'webhook_id' => $hook_id,
		);

		$response = $this->api_request( $args );
		if ( 204 !== (int) $response['statusCode'] && 200 !== (int) $response['statusCode'] ) {
			throw new Exception( esc_html( $response['data']['message'] ) );
		}
	}

	/*
	 * --------------------------------------------------------------------
	 * LSP-compat shims for pre-7.3.0 Uncanny_Automator_Pro
	 * --------------------------------------------------------------------
	 * The block below is dead-code-by-design. It exists so that PHP 8+ accepts
	 * the class declaration when pre-7.3.0 Pro's `Github_Pro_Api_Caller` (which
	 * extends this class) overrides `create_webhook`, `update_webhook`, and
	 * `delete_webhook` at its narrower arity. Without these signature stubs
	 * on the parent, PHP raises an LSP "must be compatible" fatal at class
	 * declaration time and the site refuses to boot, blocking the user from
	 * updating Pro from wp-admin.
	 *
	 * When old Pro is active, Pro's override runs and the bodies below are
	 * never reached. When Pro is updated to 7.3.0+, the override disappears
	 * and Free no longer calls these names (it uses `do_create_webhook` /
	 * `do_update_webhook` / `do_delete_webhook` above), so the bodies are
	 * still unreachable.
	 *
	 * Safe to delete this entire block once Pro ≥ 7.3.0 is the supported floor.
	 * --------------------------------------------------------------------
	 */

	/**
	 * LSP-compat shim. See block comment above. Do not invoke directly.
	 *
	 * @param string $repo_id Unused.
	 * @param array  $events  Unused.
	 *
	 * @return void
	 *
	 * @throws \BadMethodCallException Always.
	 */
	public function create_webhook( $repo_id, $events ) {
		throw new \BadMethodCallException( 'Github_Api_Caller::create_webhook is a pre-7.3.0 Pro LSP-compat stub; use do_create_webhook() instead.' );
	}

	/**
	 * LSP-compat shim. See block comment above. Do not invoke directly.
	 *
	 * @param string $repo_id Unused.
	 * @param array  $events  Unused.
	 *
	 * @return void
	 *
	 * @throws \BadMethodCallException Always.
	 */
	public function update_webhook( $repo_id, $events ) {
		throw new \BadMethodCallException( 'Github_Api_Caller::update_webhook is a pre-7.3.0 Pro LSP-compat stub; use do_update_webhook() instead.' );
	}

	/**
	 * LSP-compat shim. See block comment above. Do not invoke directly.
	 *
	 * @param string $repo_id Unused.
	 *
	 * @return void
	 *
	 * @throws \BadMethodCallException Always.
	 */
	public function delete_webhook( $repo_id ) {
		throw new \BadMethodCallException( 'Github_Api_Caller::delete_webhook is a pre-7.3.0 Pro LSP-compat stub; use do_delete_webhook() instead.' );
	}

	/**
	 * Surface GitHub's structured validation detail on 4xx errors.
	 *
	 * GitHub puts the actionable reason in data.errors[] (e.g. tag_name
	 * already_exists), while data.message is only the generic "Validation
	 * Failed". The parent throws on 4xx with that generic message; catch it and
	 * append every value from the errors[] array so the log explains the failure.
	 *
	 * @param array $response The API response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Exception If the parent flags an error.
	 */
	public function check_for_errors( $response, $args = array() ) {
		try {
			parent::check_for_errors( $response, $args );
		} catch ( Exception $e ) {
			$details = $this->format_response_errors( $response );
			if ( '' === $details ) {
				throw $e;
			}
			// $e->getMessage() is already escaped by the parent; escape only the detail.
			throw new Exception( $e->getMessage() . ': ' . esc_html( $details ) );
		}
	}

	/**
	 * Flatten GitHub's data.errors[] into a readable string.
	 *
	 * Each entry is typically an object like { resource, code, field } or
	 * { resource, code, message }, and some are plain strings. Read every scalar
	 * value so nothing is dropped.
	 *
	 * @param array $response The API response.
	 *
	 * @return string Empty string when there are no structured errors.
	 */
	private function format_response_errors( $response ) {

		$errors = $response['data']['errors'] ?? array();

		if ( ! is_array( $errors ) || empty( $errors ) ) {
			return '';
		}

		$parts = array();

		foreach ( $errors as $error ) {
			$values = is_array( $error ) ? array_filter( $error, 'is_scalar' ) : array( $error );
			$values = array_filter( array_map( 'strval', $values ), 'strlen' );

			if ( ! empty( $values ) ) {
				$parts[] = implode( ', ', $values );
			}
		}

		return implode( '; ', $parts );
	}

	/**
	 * Validate if response has expected status code and throw exception with GitHub error message if not.
	 *
	 * @param array $response The API response.
	 * @param int $expected_status The expected status code.
	 * @param string $action_message The action-specific error message.
	 *
	 * @return void
	 * @throws Exception If status code doesn't match expected.
	 */
	public function validate_action_response_status( $response, $expected_status, $action_message = '' ) {
		$status = $response['statusCode'] ?? 0;

		// The platform re-envelopes every successful vendor response to a
		// normalized 200, regardless of GitHub's native code (201 Created /
		// 204 No Content). The plugin trusts the PLATFORM contract, not the
		// vendor's — so 200 is success. We also keep accepting the legacy
		// upstream-forwarded $expected_status (201/204) for backward-compat
		// with the pre-cutover passthrough. This matters more once async
		// retries land and the platform absorbs vendor errors transparently.
		if ( 200 === absint( $status ) || absint( $status ) === absint( $expected_status ) ) {
			return;
		}

		$github_message = $response['data']['message'] ?? '';
		$error_details  = $this->format_response_errors( $response );
		$message        = $action_message . ' ';

		// Append GitHub's structured errors[] detail (e.g. tag_name already_exists)
		// to the generic message so the failure reason is visible.
		if ( '' !== $error_details ) {
			$github_message = '' !== $github_message
				? $github_message . ': ' . $error_details
				: $error_details;
		}

		if ( ! empty( $github_message ) ) {
			$message .= sprintf(
				// translators: %s is the error message from GitHub.
				esc_html_x( 'Error: %s', 'GitHub', 'uncanny-automator' ),
				esc_html( $github_message )
			);
		} else {
			$message .= sprintf(
				// translators: %d is the status code.
				esc_html_x( 'Failed with status code: %d', 'GitHub', 'uncanny-automator' ),
				absint( $status )
			);
		}

		throw new Exception( esc_html( $message ) );
	}
}
