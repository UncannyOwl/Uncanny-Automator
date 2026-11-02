<?php
/**
 * Get a shareable link to a file in Dropbox
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\Recipe\App_Action;

/**
 * Class Dropbox_Get_Shareable_Link
 *
 * @property Dropbox_App_Helpers $helpers
 * @property Dropbox_Api_Caller $api
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_Get_Shareable_Link extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'DROPBOX' );
		$this->set_action_code( 'DROPBOX_GET_SHAREABLE_LINK' );
		$this->set_action_meta( 'FILE_PATH' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: File path */
				esc_html_x( 'Get a shareable link to {{a file:%1$s}}', 'Dropbox', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Get a shareable link to {{a file}}', 'Dropbox', 'uncanny-automator' ) );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_file_option_config( $this->get_action_meta() ),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'SHAREABLE_LINK' => array(
				'name' => esc_html_x( 'Shareable link', 'Dropbox', 'uncanny-automator' ),
				'type' => 'url',
			),
			'FILE_PATH'      => array(
				'name' => esc_html_x( 'File path', 'Dropbox', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process the action. A file that already has a link returns it.
	 *
	 * @param int   $user_id     User ID.
	 * @param array $action_data Action data.
	 * @param int   $recipe_id   Recipe ID.
	 * @param array $args        Arguments.
	 * @param array $parsed      Parsed values.
	 *
	 * @return bool
	 * @throws Action_Error_Exception If action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$file_path = $this->helpers->normalize_path( $parsed[ $this->get_action_meta() ] ?? '' );

		if ( empty( $file_path ) ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'File path is required.', 'Dropbox', 'uncanny-automator' ) );
		}

		$result = $this->api->create_shared_link( $file_path, $action_data );

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
	 * Map the platform's create-shared-link response (`{url, name,
	 * path_lower}` — no `path_display`) to output tokens.
	 *
	 * @param array<string, mixed> $response
	 *
	 * @return array<string, mixed>
	 */
	protected function result_to_token_map( array $response ): array {
		return array(
			'SHAREABLE_LINK' => (string) ( $response['url'] ?? '' ),
			'FILE_PATH'      => (string) ( $response['path_lower'] ?? '' ),
		);
	}
}
