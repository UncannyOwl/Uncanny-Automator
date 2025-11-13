<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound

namespace Uncanny_Automator\Integrations\Threads;

use Exception;

/**
 * Class THREADS_CREATE_POST
 *
 * @package Uncanny_Automator
 * @property Threads_App_Helpers $helpers
 * @property Threads_Api_Caller $api
 */
class THREADS_CREATE_POST extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Action meta key prefix.
	 *
	 * @var string
	 */
	public $prefix = 'THREADS_SUBSCRIBER_ADD';

	/**
	 * Spins up new action inside "THREADS" integration.
	 *
	 * @return void
	 */
	public function setup_action() {

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
				'label'       => esc_html_x( 'Content', 'Threads', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => true,
			),
			array(
				'option_code' => 'IMAGE_URL',
				'label'       => esc_html_x( 'Image URL or Media library ID', 'Threads', 'uncanny-automator' ),
				'description' => esc_html_x( 'Enter the URL or the Media library ID of the image you wish to share. The image must be publicly accessible.', 'Threads', 'uncanny-automator' ),
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
	 * @throws Exception If credentials are invalid or an API request fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$message = $args['action_meta']['THREADS_SUBSCRIBER_ADD_META'] ?? '';
		$image   = $parsed['IMAGE_URL'] ?? '';

		$body = array(
			'action'    => 'create_thread',
			'user_id'   => $this->api->get_credential_user_id(),
			'image_url' => $this->get_media_url( $image ),
			'message'   => Automator()->parse->text( $message, $recipe_id, $user_id, $args ),
		);

		$this->api->api_request( $body, $action_data );

		return true;
	}

	/**
	 * Get the media URL from either a URL or a media library ID.
	 *
	 * If the input is a valid URL, it will return the URL. If the input is numeric,
	 * it will fetch the media URL using the attachment ID from the WordPress media library.
	 *
	 * @param string|int $input The input, either a URL or a numeric media library ID.
	 *
	 * @return string The media URL, or an empty string if no valid media is found.
	 */
	private function get_media_url( $input ) {

		// Check if the input is a valid URL
		if ( filter_var( $input, FILTER_VALIDATE_URL ) ) {
			return $input;
		}

		// Check if the input is numeric (assumed to be a media ID)
		if ( is_numeric( $input ) ) {
			// Get the URL of the media item using the media ID
			$media_url = wp_get_attachment_url( intval( $input ) );

			// Return the media URL if it exists
			if ( $media_url ) {
				return $media_url;
			}
		}

		// Return an empty string if the input is invalid or no URL was found
		return '';
	}
}
