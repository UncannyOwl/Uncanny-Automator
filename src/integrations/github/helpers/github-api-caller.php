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
		if ( absint( $status ) === absint( $expected_status ) ) {
			return;
		}

		$github_message = $response['data']['message'] ?? '';
		$message        = $action_message . ' ';

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
