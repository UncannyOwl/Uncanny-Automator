<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound

namespace Uncanny_Automator\Integrations\Threads;

use Exception;

/**
 * Class THREADS_CREATE_POST
 *
 * @package Uncanny_Automator
 */
class THREADS_CREATE_POST extends \Uncanny_Automator\Recipe\Action {

	public $prefix = 'THREADS_SUBSCRIBER_ADD';

	/**
	 * Spins up new action inside "THREADS" integration.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'THREADS' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/threads/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr_x( 'Create {{a thread post:%1$s}}', 'Threads', 'uncanny-automator' ),
				'NON_EXISTING:' . $this->get_action_meta(),
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Create {{a thread post}}', 'Threads', 'uncanny-automator' ) );
		$this->set_background_processing( true );
		$this->set_should_apply_extra_formatting( false );

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
				'label'       => _x( 'Content', 'Threads', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => true,
			),
			array(
				'option_code' => 'IMAGE_URL',
				'label'       => _x( 'Image URL or Media library ID', 'Threads', 'uncanny-automator' ),
				'description' => _x( 'Enter the URL or the Media library ID of the image you wish to share. The image must be publicly accessible.', 'Threads', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
		);

	}

	/**
	 * @param int   $user_id     The ID of the user performing the action.
	 * @param array $action_data The data related to the action being performed.
	 * @param int   $recipe_id   The ID of the recipe associated with the action.
	 * @param array $args        Additional arguments passed to the action.
	 * @param array $parsed      Parsed data needed for action execution.
	 *
	 * @return bool Returns true on successful action processing.
	 *
	 * @throws \Exception If credentials are invalid or an API request fails.
	 *
	 * @package YourPackageName
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$message = $args['action_meta']['THREADS_SUBSCRIBER_ADD_META'] ?? '';
		$image   = $parsed['IMAGE_URL'] ?? '';

		try {

			$credentials  = $this->helpers->get_credentials();
			$access_token = $credentials['access_token'] ?? '';
			$user_id      = $credentials['user_id'] ?? '';

			if ( empty( $access_token ) || empty( $user_id ) ) {
				throw new \Exception( 'Failed to authenticate: Missing or invalid credentials. Please reconnect your account through the settings page to continue.' );
			}

			$body = array(
				'action'      => 'create_thread',
				'user_id'     => $user_id,
				'credentials' => wp_json_encode( $credentials ),
				'image_url'   => Threads_Helpers::get_media_url( $image ),
				'message'     => Automator()->parse->text( $message, $recipe_id, $user_id, $args ),
			);

			$response = $this->helpers->api_request( $body, $action_data );

			return true;

		} catch ( \Exception $e ) {

			throw new \Exception( 'Error processing action: ' . $e->getMessage() );

		}

	}


}
