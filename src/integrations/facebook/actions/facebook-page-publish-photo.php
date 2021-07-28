<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class FACEBOOK_PAGE_PUBLISH_PHOTO
 * @package Uncanny_Automator
 */
class FACEBOOK_PAGE_PUBLISH_PHOTO {

	use \Uncanny_Automator\Recipe\Actions;

	public function __construct() {

		$this->fb_pages_wp_ajax_endpoint = 'fb_pages_wp_ajax_endpoint_post_image';

		$this->fb_endpoint_uri = AUTOMATOR_API_URL . 'v2/facebook';

		// Allow overwrite in wp-config.php.
		if ( DEFINED( 'UO_AUTOMATOR_DEV_FB_ENDPOINT_URL' ) ) {
			$this->fb_endpoint_uri = UO_AUTOMATOR_DEV_FB_ENDPOINT_URL;
		}

		add_action( "wp_ajax_{$this->fb_pages_wp_ajax_endpoint}", array( $this, $this->fb_pages_wp_ajax_endpoint ) );

		$this->setup_action();

	}

	public function fb_pages_wp_ajax_endpoint_post_image() {

		$pages = Automator()->helpers->recipe->facebook->options->get_user_pages_from_options_table();

		wp_send_json( $pages );

	}

	/**
	 * Setup the action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'FACEBOOK' );
		$this->set_action_code( 'FACEBOOK_PAGE_PUBLISH_PHOTO' );
		$this->set_action_meta( 'FACEBOOK_PAGE_PUBLISH_PHOTO_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		/* translators: Action - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Share a photo to {{a Facebook page:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Share a photo to {{a Facebook page}}', 'uncanny-automator' ) );

		$options_group = array(
			$this->get_action_meta() => array(
				// The facebook pages field.
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
				// The photo url field.
				array(
					'option_code' => 'FACEBOOK_PAGE_PUBLISH_PHOTO_IMAGE_URL',
					/* translators: Email field */
					'label'       => esc_attr__( 'Image URL', 'uncanny-automator' ),
					'placeholder' => esc_attr__( 'https://examplewebsite.com/path/to/image.jpg', 'uncanny-automator' ),
					'input_type'  => 'url',
					'required'    => true,
					'description' => esc_attr__( 'Enter the URL of the image you wish to share. The URL must be publicly accessible.', 'uncanny-automator' ),
				),
			),
		);

		$this->set_options_group( $options_group );

		$this->register_action();

	}


	/**
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	protected function process_action( int $user_id, array $action_data, int $recipe_id, array $args, $parsed ) {

		$facebook = Automator()->helpers->recipe->facebook->options;

		$page_id = isset( $parsed['FACEBOOK_PAGE_PUBLISH_PHOTO_META'] ) ? sanitize_text_field( $parsed['FACEBOOK_PAGE_PUBLISH_PHOTO_META'] ) : 0;

		$image_url = isset( $parsed['FACEBOOK_PAGE_PUBLISH_PHOTO_IMAGE_URL'] ) ? sanitize_text_field( $parsed['FACEBOOK_PAGE_PUBLISH_PHOTO_IMAGE_URL'] ) : '';

		$access_token = $facebook->get_user_page_access_token( $page_id );

		$http_request_query = array(
			'body' => array(
				'action'       => 'image-to-page',
				'access_token' => $access_token,
				'image_url'    => $image_url,
				'page_id'      => $page_id,
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
