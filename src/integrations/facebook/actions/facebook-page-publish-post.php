<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class FACEBOOK_PAGE_PUBLISH_POST
 * @package Uncanny_Automator
 */
class FACEBOOK_PAGE_PUBLISH_POST {

	use \Uncanny_Automator\Recipe\Actions;


	public $fb_endpoint_uri = '';

	public function __construct() {

		$this->fb_pages_wp_ajax_endpoint = 'fb_pages_wp_ajax_endpoint_post_page';

		$this->fb_endpoint_uri = AUTOMATOR_API_URL . 'v2/facebook';

		// Allow overwrite in wp-config.php.
		if ( DEFINED( 'UO_AUTOMATOR_DEV_FB_ENDPOINT_URL' ) ) {
			$this->fb_endpoint_uri = UO_AUTOMATOR_DEV_FB_ENDPOINT_URL;
		}

		add_action( "wp_ajax_{$this->fb_pages_wp_ajax_endpoint}", array( $this, $this->fb_pages_wp_ajax_endpoint ) );

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
					'label'                 => esc_attr__( 'Select a Facebook Page', 'uncanny-automator' ),
					'input_type'            => 'select',
					'is_ajax'               => true,
					'endpoint'              => $this->fb_pages_wp_ajax_endpoint,
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

		$access_token = $facebook->get_user_page_access_token( $page_id );

		$request = wp_remote_post(
			$facebook->get_endpoint_url(),
			array(
				'body' => array(
					'action'       => 'post-to-page',
					'access_token' => $access_token,
					'message'      => $message,
					'page_id'      => $page_id,
				),
			)
		);

		// Check to see if there are any errors regarding our request to the api.
		if ( ! is_wp_error( $request ) ) {

			$response = json_decode( wp_remote_retrieve_body( $request ) );

			if ( 200 !== $response->statusCode ) {
				$action_data['complete_with_errors'] = true;
				// Log error if there are any error messages.
				Automator()->complete->action( $user_id, $action_data, $recipe_id, $response->error->description );
			} else {
				// Otherwise, complete the action.
				Automator()->complete->action( $user_id, $action_data, $recipe_id );
			}
		} else {

			// Log if there are any http errors.

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $request->get_error_message() );

		}
	}
}
