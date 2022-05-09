<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class INSTAGRAM_PUBLISH_PHOTO
 *
 * @package Uncanny_Automator
 */
class INSTAGRAM_PUBLISH_PHOTO {

	use Recipe\Actions;

	const PAGES_ENDPOINT = 'ig_pages_wp_ajax_endpoint_post_link';

	public function __construct() {

		add_action( 'wp_ajax_' . self::PAGES_ENDPOINT, array( $this, self::PAGES_ENDPOINT ) );

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

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/instagram/' ) );

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
					'label'                 => esc_attr__( 'Instagram account', 'uncanny-automator' ),
					'input_type'            => 'select',
					'supports_custom_value' => false,
					'required'              => true,
					'options'               => $instagram->get_ig_accounts(),
				),
				// The image url.
				array(
					'option_code' => 'INSTAGRAM_IMAGE_URL',
					'label'       => esc_html__( 'Image URL or Media library ID', 'uncanny-automator' ),
					'input_type'  => 'url',
					'required'    => true,
					'placeholder' => esc_html__( 'https://pathtoimage/image.jpg', 'uncanny-automator' ),
					'description' => esc_html__( 'The image just be in a JPG, JPEG or PNG format. The file name must not contain spaces and extended JPEG formats (such as MPO and JPS) are not supported.', 'uncanny-automator' ),
				),
				// The hashtags.
				array(
					'option_code' => 'INSTAGRAM_HASHTAGS',
					'label'       => esc_html__( 'Caption', 'uncanny-automator' ),
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
	 * Process the Instagram action.
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

		$instagram = Automator()->helpers->recipe->instagram->options;

		$page_id = sanitize_text_field( $parsed['INSTAGRAM_PUBLISH_PHOTO_ACCOUNT_ID'] );

		$image_uri = sanitize_text_field( $parsed['INSTAGRAM_IMAGE_URL'] );

		$hashtags = sanitize_text_field( $parsed['INSTAGRAM_HASHTAGS'] );

		$page_props = $instagram->get_user_page_connected_ig( $page_id );

		if ( is_numeric( trim( $image_uri ) ) ) {
			$image_uri = wp_get_attachment_url( intval( $image_uri ) );
		}

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

		$page_ig_account = (array) end( $page_props['ig_account']->data );

		// Get the business account id.
		$business_account_id = $page_ig_account['instagram_business_account'];

		// Pass as arguments to body.
		$body = array(
			'action'                 => 'page-ig-media-publish',
			'access_token'           => $page_props['page_access_token'],
			'ig_business_account_id' => $business_account_id,
			'image_uri'              => $image_uri,
			'caption'                => $hashtags,
		);

		// Try sending the data to our API.
		try {

			$instagram->api_request( $body, $action_data );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			// Log all errors.
			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}
}
