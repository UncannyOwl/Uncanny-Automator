<?php
/**
 * Move a file or folder in Dropbox
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\Recipe\App_Action;

/**
 * Class Dropbox_Move_File
 *
 * @property Dropbox_App_Helpers $helpers
 * @property Dropbox_Api_Caller $api
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_Move_File extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'DROPBOX' );
		$this->set_action_code( 'DROPBOX_MOVE_FILE' );
		$this->set_action_meta( 'FROM_PATH' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: From path 2: To path */
				esc_html_x( 'Move {{a file or folder:%1$s}} to {{a new location:%2$s}}', 'Dropbox', 'uncanny-automator' ),
				$this->get_action_meta(),
				'TO_PATH:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Move {{a file or folder}} to {{a new location}}', 'Dropbox', 'uncanny-automator' ) );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'From path', 'Dropbox', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'description' => esc_html_x( 'The current path of the file or folder (e.g., /Documents/file.pdf)', 'Dropbox', 'uncanny-automator' ),
			),
			array(
				'option_code' => 'TO_PATH',
				'label'       => esc_html_x( 'To path', 'Dropbox', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'description' => esc_html_x( 'The new path for the file or folder (e.g., /Archive/file.pdf)', 'Dropbox', 'uncanny-automator' ),
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
			'NEW_PATH' => array(
				'name' => esc_html_x( 'New file path', 'Dropbox', 'uncanny-automator' ),
				'type' => 'text',
			),
			'FILE_ID'  => array(
				'name' => esc_html_x( 'File ID', 'Dropbox', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process the action.
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
		$from_path = $this->helpers->normalize_path( $parsed[ $this->get_action_meta() ] ?? '' );
		$to_path   = $this->helpers->normalize_path( $parsed['TO_PATH'] ?? '' );

		if ( empty( $from_path ) ) {
			throw Action_Error_Exception::validation_failed(
				esc_html_x( 'From path is required.', 'Dropbox', 'uncanny-automator' )
			);
		}

		if ( empty( $to_path ) ) {
			throw Action_Error_Exception::validation_failed(
				esc_html_x( 'To path is required.', 'Dropbox', 'uncanny-automator' )
			);
		}

		$result = $this->api->move_file( $from_path, $to_path, $action_data );

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
	 * Map the platform's move-file response (`{id, path_display}`) to output
	 * tokens.
	 *
	 * @param array<string, mixed> $response
	 *
	 * @return array<string, mixed>
	 */
	protected function result_to_token_map( array $response ): array {
		return array(
			'NEW_PATH' => (string) ( $response['path_display'] ?? '' ),
			'FILE_ID'  => (string) ( $response['id'] ?? '' ),
		);
	}
}
