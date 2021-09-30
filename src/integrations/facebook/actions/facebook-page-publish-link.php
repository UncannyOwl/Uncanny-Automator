<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class FACEBOOK_PAGE_PUBLISH_LINK
 * @package Uncanny_Automator
 */
class FACEBOOK_PAGE_PUBLISH_LINK {

	use \Uncanny_Automator\Recipe\Actions;

	public function __construct() {

		$this->fb_pages_wp_ajax_endpoint = 'fb_pages_wp_ajax_endpoint_post_link';

		$this->fb_endpoint_uri = AUTOMATOR_API_URL . 'v2/facebook';

		// Allow overwrite in wp-config.php.
		if ( DEFINED( 'UO_AUTOMATOR_DEV_FB_ENDPOINT_URL' ) ) {
			$this->fb_endpoint_uri = UO_AUTOMATOR_DEV_FB_ENDPOINT_URL;
		}

		add_action( "wp_ajax_{$this->fb_pages_wp_ajax_endpoint}", array( $this, $this->fb_pages_wp_ajax_endpoint ) );

		$this->setup_action();

	}

	public function fb_pages_wp_ajax_endpoint_post_link() {

		$pages = Automator()->helpers->recipe->facebook->options->get_user_pages_from_options_table();

		wp_send_json( $pages );

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'FACEBOOK' );
		$this->set_action_code( 'FACEBOOK_PAGE_PUBLISH_LINK' );
		$this->set_action_meta( 'FACEBOOK_PAGE_PUBLISH_LINK_PAGE' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		/* translators: Action - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Share a link with a message to {{a Facebook page:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Share a link with a message to {{a Facebook page}}', 'uncanny-automator' ) );

		$options_group = array(
			$this->get_action_meta() => array(
				// The facebook page dropdown.
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
				// The link field.
				array(
					'option_code'           => 'FACEBOOK_PAGE_PUBLISH_LINK_URL',
					/* translators: Email field */
					'label'                 => esc_attr__( 'External link URL', 'uncanny-automator' ),
					'placeholder'           => esc_attr__( 'https://', 'uncanny-automator' ),
					'description'           => esc_attr__( "Enter the URL of the site you want to share to Facebook. Start with 'https://' to share an external link.", 'uncanny-automator' ),
					'input_type'            => 'url',
					'supports_custom_value' => false,
					'required'              => true,
				),
				// The message field.
				array(
					'option_code' => 'FACEBOOK_PAGE_PUBLISH_LINK_MESSAGE',
					/* translators: Email field */
					'label'       => esc_attr__( 'Message', 'uncanny-automator' ),
					'input_type'  => 'textarea',
					'required'    => true,
				),
			),
		);

		$this->set_options_group( $options_group );

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

		$page_id = isset( $parsed['FACEBOOK_PAGE_PUBLISH_LINK_PAGE'] ) ? sanitize_text_field( $parsed['FACEBOOK_PAGE_PUBLISH_LINK_PAGE'] ) : 0;

		$message = isset( $parsed['FACEBOOK_PAGE_PUBLISH_LINK_MESSAGE'] ) ? sanitize_text_field( $parsed['FACEBOOK_PAGE_PUBLISH_LINK_MESSAGE'] ) : '';

		$link = isset( $parsed['FACEBOOK_PAGE_PUBLISH_LINK_URL'] ) ? sanitize_text_field( $parsed['FACEBOOK_PAGE_PUBLISH_LINK_URL'] ) : '';

		$access_token = $facebook->get_user_page_access_token( $page_id );

		$http_request_query = array(
			'body' => array(
				'access_token' => $access_token,
				'action'       => 'link-to-page',
				'page_id'      => $page_id,
				'message'      => $message,
				'link'         => $link,
			),
		);

		if ( DEFINED( 'UO_AUTOMATOR_DEV_FB_ENDPOINT_URL' ) ) {
			$http_request_query['sslverify'] = false;
		}

		$request = wp_remote_post(
			$this->fb_endpoint_uri,
			$http_request_query
		);

		if ( ! is_wp_error( $request ) ) {

			$response = json_decode( wp_remote_retrieve_body( $request ) );

			if ( 200 !== $response->statusCode ) {

				$action_data['complete_with_errors'] = true;

				// Log error if there are any error messages.
				Automator()->complete->action( $user_id, $action_data, $recipe_id, $response->error->description );

			} else {

				Automator()->complete->action( $user_id, $action_data, $recipe_id );

			}
		} else {

			// Log if there are any http errors.
			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $request->get_error_message() );
		}

	}
}
