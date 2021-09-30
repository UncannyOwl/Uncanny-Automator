<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class INSTAGRAM_PUBLISH_PHOTO
 * @package Uncanny_Automator
 */
class INSTAGRAM_PUBLISH_PHOTO {

	use Recipe\Actions;

	public function __construct() {

		$this->ig_pages_wp_ajax_endpoint = 'ig_pages_wp_ajax_endpoint_post_link';

		$this->fb_endpoint_uri = AUTOMATOR_API_URL . 'v2/facebook';

		// Allow overwrite in wp-config.php.
		if ( DEFINED( 'UO_AUTOMATOR_DEV_FB_ENDPOINT_URL' ) ) {
			$this->fb_endpoint_uri = UO_AUTOMATOR_DEV_FB_ENDPOINT_URL;
		}

		add_action( "wp_ajax_{$this->ig_pages_wp_ajax_endpoint}", array( $this, $this->ig_pages_wp_ajax_endpoint ) );

		$this->setup_action();

	}

	public function ig_pages_wp_ajax_endpoint_post_link() {

		$pages = Automator()->helpers->recipe->facebook->options->get_user_pages_from_options_table();

		wp_send_json( $pages );

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$instagram = Automator()->helpers->recipe->instagram->options;

		$this->set_integration( 'INSTAGRAM' );
		$this->set_action_code( 'INSTAGRAM_PUBLISH_PHOTO' );
		$this->set_action_meta( 'INSTAGRAM_PUBLISH_PHOTO_ACCOUNT_ID' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		/* translators: Action - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Publish a photo to {{an Instagram Business account:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Publish a photo to {{an Instagram Business account}}', 'uncanny-automator' ) );

		$options_group = array(
			$this->get_action_meta() => array(
				// The facebook page dropdown.
				array(
					'option_code'           => $this->get_action_meta(),
					/* translators: Email field */
					'label'                 => esc_attr__( 'Select an Instagram account', 'uncanny-automator' ),
					'input_type'            => 'select',
					'supports_custom_value' => false,
					'required'              => true,
					'options'               => $instagram->get_ig_accounts(),
				),
				// The image url.
				array(
					'option_code' => 'INSTAGRAM_IMAGE_URL',
					'label'       => esc_html__( 'Enter the image URI. The image must be JPEG or PNG.', 'uncanny-automator' ),
					'input_type'  => 'url',
					'required'    => true,
					'placeholder' => esc_html__( 'https://pathtoimage/image.jpg', 'uncanny-automator' ),
					'description' => esc_html__( 'Extended JPEG formats such as MPO and JPS are not supported.', 'uncanny-automator' ),
				),
				// The hashtags.
				array(
					'option_code' => 'INSTAGRAM_HASHTAGS',
					'label'       => esc_html__( 'Description/Hashtags', 'uncanny-automator' ),
					'input_type'  => 'textarea',
					'required'    => false,
					'placeholder' => esc_html__( 'My image #description', 'uncanny-automator' ),
					'description' => esc_html__( 'Enter the description and/or hashtags that should be posted with the image.', 'uncanny-automator' ),
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

		$instagram = Automator()->helpers->recipe->instagram->options;

		$page_id   = sanitize_text_field( $parsed['INSTAGRAM_PUBLISH_PHOTO_ACCOUNT_ID'] );
		$image_uri = sanitize_text_field( $parsed['INSTAGRAM_IMAGE_URL'] );
		$hashtags  = sanitize_text_field( $parsed['INSTAGRAM_HASHTAGS'] );

		$page_props = $instagram->get_user_page_connected_ig( $page_id );

		// Bailout if no facebook account connected.
		if ( empty( $page_props ) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, esc_html__( 'Cound not find any settings for Facebook Authentication.', 'uncanny-automator' ) );
			return;
		}

		// Bailout if no instagram account connected.
		if ( empty( $page_props['ig_account'] ) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, esc_html__( 'Cannot find any Instagram account connected to the Facebook page.', 'uncanny-automator' ) );
			return;
		}

		$page_ig_account = end( $page_props['ig_account']->data );

		$business_account_id = $page_ig_account->instagram_business_account;

		$http_request_query = array(
			'body' => array(
				'action'                 => 'page-ig-media-publish',
				'access_token'           => $page_props['page_access_token'],
				'ig_business_account_id' => $business_account_id,
				'page_id'                => $page_id,
				'image_uri'              => $image_uri,
				'caption'                => $hashtags,
			),
		);

		// Add generous timeout limit for slow Instagram endpoint.
		$http_request_query['timeout'] = 60;

		$request = wp_remote_post(
			$this->fb_endpoint_uri,
			$http_request_query
		);

		if ( ! is_wp_error( $request ) ) {

			$response = json_decode( wp_remote_retrieve_body( $request ) );

			// Bailout if statusCode is not set.
			if ( ! isset( $response->statusCode ) ) {
				$action_data['complete_with_errors'] = true;
				Automator()->complete->action( $user_id, $action_data, $recipe_id, esc_html__( 'There was an error in the response code.', 'uncanny-automator' ) );
				return;
			}
			if ( 200 === $response->statusCode ) {
				// Otherwise, complete the action.
				Automator()->complete->action( $user_id, $action_data, $recipe_id );
			} else {
				$action_data['complete_with_errors'] = true;
				// Log error if there are any error messages.
				Automator()->complete->action( $user_id, $action_data, $recipe_id, $response->error->description );
			}
		} else {

			// Log if there are any http errors.
			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $request->get_error_message() );
		}

	}
}
