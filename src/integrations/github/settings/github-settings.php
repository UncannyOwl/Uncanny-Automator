<?php
/**
 * Creates the settings page
 */

namespace Uncanny_Automator\Integrations\Github;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;

/**
 * Github_Settings
 *
 * @package Uncanny_Automator
 *
 * @property Github_App_Helpers $helpers
 */
class Github_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array Formatted account information for UI display
	 */
	protected function get_formatted_account_info() {
		// Get the account info.
		$account = $this->helpers->get_account_info();
		$name    = $account['name'] ?? null;
		$login   = $account['login'];

		// Format the account info.
		$info = array(
			'avatar_type'  => 'image',
			'avatar_value' => $account['avatar'],
			'main_info'    => sprintf(
				// translators: %s is the name / login of the user
				esc_html_x( 'Connected as: %s', 'GitHub', 'uncanny-automator' ),
				$name ? "{$name} ({$login})" : $login
			),
		);

		// Add the email if it's present.
		if ( ! empty( $account['email'] ) ) {
			$info['additional'] = sprintf(
				// translators: %s is the email of the user from the connected account
				esc_html_x( 'Email: %s', 'GitHub', 'uncanny-automator' ),
				esc_html( $account['email'] )
			);
		}

		return $info;
	}

	////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////

	/**
	 * Before disconnecting.
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {

		// Clean up any transient cache data for all repos.
		$this->cleanup_repo_transients();

		// Register repo option for automatic cleanup.
		$this->register_option( $this->helpers->get_const( 'REPO_OPTION' ) );

		return $response;
	}

	/**
	 * Clean up transient cache data for all cached repository data.
	 *
	 * @return void
	 */
	private function cleanup_repo_transients() {
		// Get the repos from the option
		$repos = automator_get_option( $this->helpers->get_const( 'REPO_OPTION' ) );

		if ( empty( $repos ) || ! is_array( $repos ) ) {
			return;
		}

		// Define all the data types we cache
		$data_types = array( 'branches', 'labels', 'issues', 'pull_requests', 'tags' );

		// Loop through each repo and delete transients for all data types
		foreach ( $repos as $repo ) {
			$owner = $repo['owner'] ?? '';
			$name  = $repo['name'] ?? '';

			if ( empty( $owner ) || empty( $name ) ) {
				continue;
			}

			// Delete transients for each data type
			foreach ( $data_types as $data_type ) {
				$cache_key = "github_repo_{$data_type}_{$owner}_{$name}";
				delete_transient( $cache_key );
			}
		}
	}
}
