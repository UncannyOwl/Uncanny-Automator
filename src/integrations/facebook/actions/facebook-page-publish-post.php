<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class FACEBOOK_PAGE_PUBLISH_POST
 *
 * @package Uncanny_Automator
 */
class FACEBOOK_PAGE_PUBLISH_POST {

	use \Uncanny_Automator\Recipe\Actions;

	const AJAX_ENDPOINT = 'fb_pages_wp_ajax_endpoint_post_page';

	public function __construct() {

		add_action( 'wp_ajax_' . self::AJAX_ENDPOINT, array( $this, self::AJAX_ENDPOINT ) );

		$this->setup_action();

	}

	public function fb_pages_wp_ajax_endpoint_post_page() {

		$pages = Automator()->helpers->recipe->facebook->options->get_user_pages_from_options_table();

		wp_send_json( $pages );

	}

	/**
	 * Setup SENDEMAIL Automator Action.
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

		/* translators: Action - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Publish a post to {{a Facebook page:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Publish a post to {{a Facebook page}}', 'uncanny-automator' ) );

		$options = array(
			$this->get_action_meta() => array(
				// Email From Field.
				array(
					'option_code'           => $this->get_action_meta(),
					/* translators: Email field */
					'label'                 => esc_attr__( 'Facebook Page', 'uncanny-automator' ),
					'input_type'            => 'select',
					'is_ajax'               => true,
					'endpoint'              => self::AJAX_ENDPOINT,
					'supports_custom_value' => false,
					'required'              => true,
				),
				array(
					'option_code' => 'FACEBOOK_PAGE_MESSAGE',
					'input_type'  => 'textarea',
					'label'       => esc_attr__( 'Message', 'uncanny-automator' ),
					'description' => esc_attr__( 'Enter the message that you want to post on Facebook. Please take note that this action might fail when posting the same messages within short intervals.', 'uncanny-automator' ),
					'required'    => true,
				),
			),

		);

		$this->set_options_group( $options );

		$this->register_action();

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

		$facebook = Automator()->helpers->recipe->facebook->options;

		$page_id = sanitize_text_field( $parsed['FACEBOOK_PAGE_PUBLISH_POST_META'] );

		$message = sanitize_textarea_field( $parsed['FACEBOOK_PAGE_MESSAGE'] );

		$body = array(
			'action'  => 'post-to-page',
			'message' => $message,
			'page_id' => $page_id,
		);

		try {

			$facebook->api_request( $page_id, $body, $action_data );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			// Log error if there are any error messages.
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}
	}
}
