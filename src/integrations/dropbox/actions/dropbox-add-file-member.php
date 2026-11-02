<?php
/**
 * Add a member to a file in Dropbox
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\Recipe\App_Action;

/**
 * Class Dropbox_Add_File_Member
 *
 * @property Dropbox_App_Helpers $helpers
 * @property Dropbox_Api_Caller $api
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_Add_File_Member extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'DROPBOX' );
		$this->set_action_code( 'DROPBOX_ADD_FILE_MEMBER' );
		$this->set_action_meta( 'FILE_PATH' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: File 2: Member email */
				esc_html_x( 'Add {{a member:%2$s}} to {{a file:%1$s}}', 'Dropbox', 'uncanny-automator' ),
				$this->get_action_meta(),
				'MEMBER_EMAIL:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Add {{a member}} to {{a file}}', 'Dropbox', 'uncanny-automator' ) );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_file_option_config( $this->get_action_meta() ),
			$this->helpers->get_member_email_option_config(),
			$this->helpers->get_access_level_option_config(),
		);
	}

	/**
	 * Define tokens. Dropbox's response carries no file identifier, so both
	 * tokens come from the action's own fields.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'MEMBER_EMAIL' => array(
				'name' => esc_html_x( 'Member email', 'Dropbox', 'uncanny-automator' ),
				'type' => 'email',
			),
			'ACCESS_LEVEL' => array(
				'name' => esc_html_x( 'Access level', 'Dropbox', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process the action. The platform answers 422 when Dropbox refuses the
	 * member, so a return means the member was added.
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
		$file_path    = $this->helpers->normalize_path( $parsed[ $this->get_action_meta() ] ?? '' );
		$member_email = $parsed['MEMBER_EMAIL'] ?? '';
		$access_level = $parsed['ACCESS_LEVEL'] ?? 'viewer';

		if ( empty( $file_path ) ) {
			throw Action_Error_Exception::validation_failed(
				esc_html_x( 'File path is required.', 'Dropbox', 'uncanny-automator' )
			);
		}

		if ( empty( $member_email ) ) {
			throw Action_Error_Exception::validation_failed(
				esc_html_x( 'Member email is required.', 'Dropbox', 'uncanny-automator' )
			);
		}

		$result = $this->api->add_file_member( $file_path, $member_email, $access_level, $action_data );

		if ( $this->deferred( $result ) ) {
			return true;
		}

		return $this->process_job( is_array( $result ) ? $result : array(), $parsed, $action_data );
	}

	/**
	 * Fill the member tokens from the parsed fields; the platform's answer, synchronous or a background job's
	 * completion envelope, carries nothing the tokens need.
	 *
	 * @param array $response    The write's result, or the job's completion envelope.
	 * @param array $parsed      The parsed field values.
	 * @param array $action_data The action data.
	 *
	 * @return bool
	 */
	protected function process_job( array $response, array $parsed, array $action_data ): bool {
		$this->hydrate_tokens(
			array(
				'MEMBER_EMAIL' => (string) ( $parsed['MEMBER_EMAIL'] ?? '' ),
				'ACCESS_LEVEL' => (string) ( $parsed['ACCESS_LEVEL'] ?? 'viewer' ),
			)
		);

		return true;
	}
}
