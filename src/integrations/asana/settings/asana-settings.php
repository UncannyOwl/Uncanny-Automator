<?php
/**
 * Creates the settings page
 */

namespace Uncanny_Automator\Integrations\Asana;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;

use Exception;

/**
 * Asana_Settings
 *
 * @property Asana_App_Helpers $helpers
 * @property Asana_Api_Caller $api
 */
class Asana_Settings extends App_Integration_Settings {

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
		$name    = $account['name'] ?? '';
		$email   = $account['email'] ?? '';
		$avatar  = $account['avatar'] ?? '';

		// Use name if available, fallback to email, then default.
		$display_name = empty( $name ) ? $email : $name;
		$display_name = empty( $display_name )
			? esc_html_x( 'Asana User', 'Asana', 'uncanny-automator' )
			: $display_name;

		// Extract the avatar image from the response.
		if ( ! empty( $avatar ) && is_array( $avatar ) ) {
			$avatar = $avatar['image_60x60'] ?? $avatar['image_128x128'] ?? '';
		}

		// Format the account info.
		$info = array(
			'avatar_type'    => empty( $avatar ) ? 'text' : 'image',
			'avatar_value'   => empty( $avatar ) ? substr( $display_name, 0, 1 ) : $avatar,
			'main_info'      => sprintf(
				// translators: %s is the name / email of the user
				esc_html_x( 'Connected as: %s', 'Asana', 'uncanny-automator' ),
				$display_name
			),
			'main_info_icon' => true,
		);

		// Add the email if it's present and different from name.
		if ( ! empty( $email ) && $name !== $email ) {
			$info['additional'] = sprintf(
				// translators: %s is the email of the user
				esc_html_x( 'Email: %s', 'Asana', 'uncanny-automator' ),
				$email
			);
		}

		return $info;
	}

	////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////

	/**
	 * Register all options for automatic cleanup on disconnection.
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {

		$workspace_key = $this->helpers->get_const( 'WORKSPACE_OPTION' );

		// Get all workspaces to clean up their cached data.
		$workspaces = automator_get_option( $workspace_key, array() );
		automator_delete_option( $workspace_key );

		if ( empty( $workspaces ) || ! is_array( $workspaces ) ) {
			return $response;
		}

		foreach ( $workspaces as $workspace ) {
			$workspace_id = $workspace['value'] ?? '';

			if ( empty( $workspace_id ) ) {
				continue;
			}

			// Get projects for this workspace before deleting the cache.
			$projects_key = 'ASANA_PROJECTS_' . $workspace_id;
			$projects     = automator_get_option( $projects_key, array() );

			// Clean up projects cache for this workspace.
			automator_delete_option( $projects_key );

			// Clean up tags cache for this workspace.
			$tags_key = 'ASANA_TAGS_' . $workspace_id;
			automator_delete_option( $tags_key );

			// Clean up users cache for this workspace.
			$users_key = 'ASANA_USERS_' . $workspace_id;
			automator_delete_option( $users_key );

			// Clean up task caches for all projects in this workspace.
			if ( empty( $projects ) || ! is_array( $projects ) ) {
				continue;
			}

			foreach ( $projects as $project ) {
				$project_id = $project['value'] ?? '';

				if ( empty( $project_id ) ) {
					continue;
				}

				// Clean up tasks cache for this project.
				$tasks_key = 'ASANA_TASKS_' . $project_id;
				automator_delete_option( $tasks_key );
			}
		}

		return $response;
	}
}
