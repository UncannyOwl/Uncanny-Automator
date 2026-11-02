<?php
/**
 * Share a folder in Dropbox
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\Recipe\App_Action;

/**
 * Class Dropbox_Share_Folder
 *
 * @property Dropbox_App_Helpers $helpers
 * @property Dropbox_Api_Caller $api
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_Share_Folder extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'DROPBOX' );
		$this->set_action_code( 'DROPBOX_SHARE_FOLDER' );
		$this->set_action_meta( 'FOLDER_PATH' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Folder path */
				esc_html_x( 'Share {{a folder:%1$s}}', 'Dropbox', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Share {{a folder}}', 'Dropbox', 'uncanny-automator' ) );
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'SHARED_FOLDER_ID'  => array(
				'name' => esc_html_x( 'Shared folder ID', 'Dropbox', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SHARED_FOLDER_URL' => array(
				'name' => esc_html_x( 'Shared folder URL', 'Dropbox', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_folder_option_config( $this->get_action_meta(), false ),
			array(
				'option_code' => 'MEMBER_POLICY',
				'label'       => esc_html_x( 'Who can be a member', 'Dropbox', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => false,
				'options'     => array(
					array(
						'value' => 'anyone',
						'text'  => esc_html_x( 'Anyone', 'Dropbox', 'uncanny-automator' ),
					),
					array(
						'value' => 'team',
						'text'  => esc_html_x( 'Team only', 'Dropbox', 'uncanny-automator' ),
					),
					array(
						'value' => 'team_and_approved',
						'text'  => esc_html_x( 'Team and approved people', 'Dropbox', 'uncanny-automator' ),
					),
				),
				'description' => esc_html_x( 'Only applicable if you are on a team', 'Dropbox', 'uncanny-automator' ),
			),
			array(
				'option_code'   => 'ACL_UPDATE_POLICY',
				'label'         => esc_html_x( 'Who can change permissions', 'Dropbox', 'uncanny-automator' ),
				'input_type'    => 'select',
				'required'      => false,
				'default_value' => 'editors',
				'options'       => array(
					array(
						'value' => 'editors',
						'text'  => esc_html_x( 'Editors', 'Dropbox', 'uncanny-automator' ),
					),
					array(
						'value' => 'owner',
						'text'  => esc_html_x( 'Owner only', 'Dropbox', 'uncanny-automator' ),
					),
				),
			),
			array(
				'option_code' => 'SHARED_LINK_POLICY',
				'label'       => esc_html_x( 'Shared link policy', 'Dropbox', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => false,
				'options'     => array(
					array(
						'value' => 'anyone',
						'text'  => esc_html_x( 'Anyone with link', 'Dropbox', 'uncanny-automator' ),
					),
					array(
						'value' => 'members',
						'text'  => esc_html_x( 'Members only', 'Dropbox', 'uncanny-automator' ),
					),
				),
				'description' => esc_html_x( 'Must be on a team to set to Members only', 'Dropbox', 'uncanny-automator' ),
			),
			array(
				'option_code'   => 'VIEWER_INFO_POLICY',
				'label'         => esc_html_x( 'Viewer info policy', 'Dropbox', 'uncanny-automator' ),
				'input_type'    => 'select',
				'required'      => false,
				'default_value' => 'enabled',
				'options'       => array(
					array(
						'value' => 'enabled',
						'text'  => esc_html_x( 'Enabled', 'Dropbox', 'uncanny-automator' ),
					),
					array(
						'value' => 'disabled',
						'text'  => esc_html_x( 'Disabled', 'Dropbox', 'uncanny-automator' ),
					),
				),
				'description'   => esc_html_x( 'Who can enable/disable viewer info', 'Dropbox', 'uncanny-automator' ),
			),
			array(
				'option_code'   => 'ACCESS_INHERITANCE',
				'label'         => esc_html_x( 'Access inheritance', 'Dropbox', 'uncanny-automator' ),
				'input_type'    => 'select',
				'required'      => false,
				'default_value' => 'inherit',
				'options'       => array(
					array(
						'value' => 'inherit',
						'text'  => esc_html_x( 'Inherit from parent', 'Dropbox', 'uncanny-automator' ),
					),
					array(
						'value' => 'no_inherit',
						'text'  => esc_html_x( 'Do not inherit', 'Dropbox', 'uncanny-automator' ),
					),
				),
				'description'   => esc_html_x( 'Access inheritance settings', 'Dropbox', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Process the action. An already-shared folder returns its existing share.
	 *
	 * @param int   $user_id     User ID.
	 * @param array $action_data Action data.
	 * @param int   $recipe_id   Recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed values.
	 *
	 * @return bool
	 * @throws Action_Error_Exception If action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$folder_path        = $this->helpers->get_shareable_folder_from_parsed( $parsed, $this->get_action_meta() );
		$member_policy      = $parsed['MEMBER_POLICY'] ?? null;
		$acl_update_policy  = $parsed['ACL_UPDATE_POLICY'] ?? null;
		$shared_link_policy = $parsed['SHARED_LINK_POLICY'] ?? null;
		$viewer_info_policy = $parsed['VIEWER_INFO_POLICY'] ?? null;
		$access_inheritance = $parsed['ACCESS_INHERITANCE'] ?? null;

		$result = $this->api->share_folder(
			$folder_path,
			$member_policy,
			$acl_update_policy,
			$shared_link_policy,
			$viewer_info_policy,
			$access_inheritance,
			$action_data
		);

		if ( $this->deferred( $result ) ) {
			return true;
		}

		return $this->process_job( is_array( $result ) ? $result : array(), $parsed, $action_data );
	}

	/**
	 * Fill the tokens from the platform's answer: the unwrapped result of a synchronous write, or the completion
	 * envelope of a background job, whose result sits under `data`.
	 *
	 * @param array $response    The write's result, or the job's completion envelope.
	 * @param array $parsed      The parsed field values.
	 * @param array $action_data The action data.
	 *
	 * @return bool
	 */
	protected function process_job( array $response, array $parsed, array $action_data ): bool {
		$data = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : $response;

		$this->hydrate_tokens( $this->result_to_token_map( $data ) );

		return true;
	}

	/**
	 * Map the platform's share-folder response (`{shared_folder_id, name,
	 * preview_url}`) to output tokens.
	 *
	 * @param array<string, mixed> $response
	 *
	 * @return array<string, mixed>
	 */
	protected function result_to_token_map( array $response ): array {
		return array(
			'SHARED_FOLDER_ID'  => (string) ( $response['shared_folder_id'] ?? '' ),
			'SHARED_FOLDER_URL' => (string) ( $response['preview_url'] ?? '' ),
		);
	}
}
