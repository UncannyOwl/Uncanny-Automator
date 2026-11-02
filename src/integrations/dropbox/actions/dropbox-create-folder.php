<?php
/**
 * Create a folder in Dropbox
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\Recipe\App_Action;

/**
 * Class Dropbox_Create_Folder
 *
 * @property Dropbox_App_Helpers $helpers
 * @property Dropbox_Api_Caller $api
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_Create_Folder extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'DROPBOX' );
		$this->set_action_code( 'DROPBOX_CREATE_FOLDER' );
		$this->set_action_meta( 'FOLDER_NAME' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Folder name 2: Parent folder */
				esc_html_x( 'Create {{a folder:%1$s}} in {{a parent folder:%2$s}}', 'Dropbox', 'uncanny-automator' ),
				$this->get_action_meta(),
				'PARENT_FOLDER:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Create {{a folder}} in {{a parent folder}}', 'Dropbox', 'uncanny-automator' ) );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_folder_option_config( 'PARENT_FOLDER' ),
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Folder name', 'Dropbox', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'description' => esc_html_x( 'The name of the folder to create', 'Dropbox', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'FOLDER_PATH' => array(
				'name' => esc_html_x( 'Folder path', 'Dropbox', 'uncanny-automator' ),
				'type' => 'text',
			),
			'FOLDER_ID'   => array(
				'name' => esc_html_x( 'Folder ID', 'Dropbox', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process the action. An existing folder counts as created: the platform
	 * returns its metadata.
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
		$parent_folder = $this->helpers->get_folder_from_parsed( $parsed, 'PARENT_FOLDER' );
		$folder_name   = $parsed[ $this->get_action_meta() ] ?? '';

		if ( empty( $folder_name ) ) {
			throw Action_Error_Exception::validation_failed(
				esc_html_x( 'Folder name is required.', 'Dropbox', 'uncanny-automator' )
			);
		}

		$result = $this->api->create_folder( $parent_folder . '/' . ltrim( $folder_name, '/' ), $action_data );

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
	 * Map the platform's create-folder response (`{id, name, path_display}`)
	 * to output tokens.
	 *
	 * @param array<string, mixed> $response
	 *
	 * @return array<string, mixed>
	 */
	protected function result_to_token_map( array $response ): array {
		return array(
			'FOLDER_PATH' => (string) ( $response['path_display'] ?? '' ),
			'FOLDER_ID'   => (string) ( $response['id'] ?? '' ),
		);
	}
}
