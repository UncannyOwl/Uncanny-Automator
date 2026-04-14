<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Facebook;

/**
 * Class FACEBOOK_PAGE_PUBLISH_POST
 *
 * @package Uncanny_Automator
 * @property Facebook_App_Helpers $helpers
 * @property Facebook_Api_Caller $api
 */
class FACEBOOK_PAGE_PUBLISH_POST extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'FACEBOOK' );
		$this->set_action_code( 'FACEBOOK_PAGE_PUBLISH_POST' );
		$this->set_action_meta( 'FACEBOOK_PAGE_PUBLISH_POST_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/facebook/' ) );
		$this->set_requires_user( false );

		// Disables wpautop.
		$this->set_wpautop( false );

		// translators: %1$s is the Facebook page title.
		$this->set_sentence(
			sprintf(
				esc_attr_x( 'Publish a post to {{a Facebook page:%1$s}}', 'Facebook', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Publish a post to {{a Facebook page}}', 'Facebook', 'uncanny-automator' ) );

		$this->set_action_tokens(
			$this->helpers->get_post_link_token_config(),
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
				'option_code' => 'FACEBOOK_PAGE_MESSAGE',
				'label'       => esc_html_x( 'Message', 'Facebook', 'uncanny-automator' ),
				'description' => esc_html_x( 'Enter the message that you want to post on Facebook. Please take note that this action might fail when posting the same messages within short intervals.', 'Facebook', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		// Required field - throws error if not set and valid.
		$page_id = $this->helpers->get_linked_page_id_from_parsed( $parsed, $this->get_action_meta() );

		// Post content editor adds BR tag if shift+enter. Enter key adds paragraph. Support both.
		$message = isset( $parsed['FACEBOOK_PAGE_MESSAGE'] ) ? sanitize_textarea_field( $parsed['FACEBOOK_PAGE_MESSAGE'] ) : '';

		$body = array(
			'action'  => 'post-to-page',
			'message' => $message,
			'page_id' => $page_id,
		);

		$response = $this->api->api_request( $body, $action_data );
		$post_id  = isset( $response['data']['id'] ) ? $response['data']['id'] : 0;

		if ( 0 !== $post_id ) {
			$this->hydrate_tokens( $this->helpers->hydrate_post_link_token( $post_id ) );
		}

		return true;
	}
}
