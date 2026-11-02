<?php
/**
 * Add a member to a folder in Dropbox
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\Recipe\App_Action;

/**
 * Class Dropbox_Add_Folder_Member
 *
 * @property Dropbox_App_Helpers $helpers
 * @property Dropbox_Api_Caller $api
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_Add_Folder_Member extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'DROPBOX' );
		$this->set_action_code( 'DROPBOX_ADD_FOLDER_MEMBER' );
		$this->set_action_meta( 'FOLDER_PATH' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Folder 2: Member email */
				esc_html_x( 'Add {{a member:%2$s}} to {{a folder:%1$s}}', 'Dropbox', 'uncanny-automator' ),
				$this->get_action_meta(),
				'MEMBER_EMAIL:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Add {{a member}} to {{a folder}}', 'Dropbox', 'uncanny-automator' ) );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_folder_option_config( $this->get_action_meta(), false ),
			$this->helpers->get_member_email_option_config(),
			$this->helpers->get_access_level_option_config(),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'MEMBER_EMAIL'     => array(
				'name' => esc_html_x( 'Member email', 'Dropbox', 'uncanny-automator' ),
				'type' => 'email',
			),
			'ACCESS_LEVEL'     => array(
				'name' => esc_html_x( 'Access level', 'Dropbox', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SHARED_FOLDER_ID' => array(
				'name' => esc_html_x( 'Shared folder ID', 'Dropbox', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process the action. The platform shares the folder first when it isn't
	 * shared yet.
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
		$folder_path  = $this->helpers->get_shareable_folder_from_parsed( $parsed, $this->get_action_meta() );
		$member_email = $parsed['MEMBER_EMAIL'] ?? '';
		$access_level = $parsed['ACCESS_LEVEL'] ?? 'viewer';

		if ( empty( $member_email ) ) {
			throw Action_Error_Exception::validation_failed(
				esc_html_x( 'Member email is required.', 'Dropbox', 'uncanny-automator' )
			);
		}

		$result = $this->api->add_folder_member( $folder_path, $member_email, $access_level, $action_data );

		if ( $this->deferred( $result ) ) {
			return true;
		}

		return $this->process_job( is_array( $result ) ? $result : array(), $parsed, $action_data );
	}

	/**
	 * Fill the tokens from the platform's answer: the member tokens come from the parsed fields, the shared folder
	 * id from the unwrapped result of a synchronous write or from the completion envelope of a background job,
	 * whose result sits under `data`.
	 *
	 * @param array $response    The write's result, or the job's completion envelope.
	 * @param array $parsed      The parsed field values.
	 * @param array $action_data The action data.
	 *
	 * @return bool
	 */
	protected function process_job( array $response, array $parsed, array $action_data ): bool {
		$data = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : $response;

		$this->hydrate_tokens(
			array(
				'MEMBER_EMAIL'     => (string) ( $parsed['MEMBER_EMAIL'] ?? '' ),
				'ACCESS_LEVEL'     => (string) ( $parsed['ACCESS_LEVEL'] ?? 'viewer' ),
				'SHARED_FOLDER_ID' => (string) ( $data['shared_folder_id'] ?? '' ),
			)
		);

		return true;
	}
}
