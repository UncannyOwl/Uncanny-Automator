<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Facebook;

/**
 * Class FACEBOOK_PAGE_CREATE_DRAFT
 *
 * @package Uncanny_Automator
 * @property Facebook_App_Helpers $helpers
 * @property Facebook_Api_Caller $api
 */
class FACEBOOK_PAGE_CREATE_DRAFT extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'FACEBOOK' );
		$this->set_action_code( 'FACEBOOK_PAGE_CREATE_DRAFT' );
		$this->set_action_meta( 'FACEBOOK_PAGE_CREATE_DRAFT_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/facebook/' ) );
		$this->set_requires_user( false );

		// Disables wpautop.
		$this->set_wpautop( false );

		// translators: %1$s is the Facebook page title.
		$this->set_sentence(
			sprintf(
				esc_attr_x( 'Create a draft post on {{a Facebook page:%1$s}}', 'Facebook', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Create a draft post on {{a Facebook page}}', 'Facebook', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'DRAFT_ID' => array(
					'name' => esc_html_x( 'Draft post ID', 'Facebook', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);

		$this->set_background_processing( true );
	}

	/**
	 * Define the action options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			// The facebook page dropdown.
			$this->helpers->get_linked_pages_select_config( $this->get_action_meta() ),
			// The message field.
			array(
				'option_code' => 'FACEBOOK_DRAFT_MESSAGE',
				'label'       => esc_html_x( 'Message', 'Facebook', 'uncanny-automator' ),
				'description' => esc_html_x( "Enter the message for your draft post. The draft will be saved to the Facebook Page's Publishing Tools where it can be reviewed and published later.", 'Facebook', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The args.
	 * @param array $parsed      The parsed variables.
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Required field - throws error if not set and valid.
		$page_id = $this->helpers->get_linked_page_id_from_parsed( $parsed, $this->get_action_meta() );
		$message = sanitize_textarea_field( $parsed['FACEBOOK_DRAFT_MESSAGE'] ?? '' );
		if ( empty( $message ) ) {
			throw new \Exception( esc_html_x( 'Message is required to create a draft post.', 'Facebook', 'uncanny-automator' ) );
		}

		$body = array(
			'action'  => 'draft-to-page',
			'message' => $message,
			'page_id' => $page_id,
		);

		$response = $this->api->api_request( $body, $action_data );
		$draft_id = $response['data']['id'] ?? '';

		if ( ! empty( $draft_id ) ) {
			$this->hydrate_tokens( array( 'DRAFT_ID' => $draft_id ) );
		}

		return true;
	}
}
