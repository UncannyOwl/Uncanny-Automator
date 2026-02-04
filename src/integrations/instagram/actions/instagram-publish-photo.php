<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

// Load the retry trait.
require_once __DIR__ . '/../traits/trait-instagram-publish-retry.php';

/**
 * Class INSTAGRAM_PUBLISH_PHOTO
 *
 * @package Uncanny_Automator
 */
class INSTAGRAM_PUBLISH_PHOTO {

	use Recipe\Actions;
	use Instagram_Publish_Retry;

	const PAGES_ENDPOINT = 'ig_pages_wp_ajax_endpoint_post_link';

	/**
	 * Constructor.
	 *
	 * @return void.
	 */
	public function __construct() {

		add_action( 'wp_ajax_' . self::PAGES_ENDPOINT, array( $this, self::PAGES_ENDPOINT ) );

		// Register retry hooks from trait.
		$this->register_retry_hooks();

		$this->setup_action();
	}

	/**
	 * Instagram pages AJAX endpoint.
	 *
	 * @return void.
	 */
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
		$this->set_sentence( sprintf( esc_attr_x( 'Publish a photo to {{an Instagram Business account:%1$s}}', 'Instagram', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr_x( 'Publish a photo to {{an Instagram Business account}}', 'Instagram', 'uncanny-automator' ) );

		$options_group = array(
			$this->get_action_meta() => array(
				// The facebook page dropdown.
				array(
					'option_code'           => $this->get_action_meta(),
					/* translators: Email field */
					'label'                 => esc_attr_x( 'Instagram account', 'Instagram', 'uncanny-automator' ),
					'input_type'            => 'select',
					'supports_custom_value' => false,
					'required'              => true,
					'options'               => $instagram->get_ig_accounts(),
				),
				// The image url.
				array(
					'option_code' => 'INSTAGRAM_IMAGE_URL',
					'label'       => esc_attr_x( 'Image URL or Media library ID', 'Instagram', 'uncanny-automator' ),
					'input_type'  => 'url',
					'required'    => true,
					'placeholder' => esc_attr_x( 'https://pathtoimage/image.jpg', 'Instagram', 'uncanny-automator' ),
					'description' => esc_attr_x( 'The image must be in a JPG, JPEG or PNG format. The file name must not contain spaces and extended JPEG formats (such as MPO and JPS) are not supported.', 'Instagram', 'uncanny-automator' ),
				),
				// The hashtags.
				array(
					'option_code' => 'INSTAGRAM_HASHTAGS',
					'label'       => esc_attr_x( 'Caption', 'Instagram', 'uncanny-automator' ),
					'input_type'  => 'textarea',
					'required'    => false,
					'placeholder' => esc_attr_x( 'My image #description', 'Instagram', 'uncanny-automator' ),
					'description' => esc_attr_x( 'Enter the description and/or hashtags that should be posted with the image.', 'Instagram', 'uncanny-automator' ),
				),
			),
		);

		$this->set_wpautop( false );

		$this->set_options_group( $options_group );

		$this->set_background_processing( true );

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

		$hashtags = sanitize_textarea_field( $parsed['INSTAGRAM_HASHTAGS'] );

		$page_props = $instagram->get_user_page_connected_ig( $page_id );

		if ( is_numeric( trim( $image_uri ) ) ) {
			$image_uri = wp_get_attachment_url( intval( $image_uri ) );
		}

		// Bailout if no facebook account connected.
		if ( empty( $page_props ) ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, esc_attr_x( 'Cound not find any settings for Facebook Authentication.', 'Instagram', 'uncanny-automator' ) );

			return;

		}

		// Bailout if no instagram account connected.
		if ( empty( $page_props['ig_account'] ) ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, esc_attr_x( 'Cannot find any Instagram account connected to the Facebook page.', 'Instagram', 'uncanny-automator' ) );

			return;

		}

		$page_ig_account = (array) end( $page_props['ig_account']->data );

		// Get the business account id.
		$business_account_id = $page_ig_account['instagram_business_account'];

		if ( ! empty( $hashtags ) ) {
			// Replace new lines with double new lines.
			$hashtags = str_replace( "\r\n", "\n\n", $hashtags );
		}

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

			$response = $instagram->api_request( $body, $action_data );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			// Check for retryable error (Media ID not available - code 9007).
			if ( $this->is_media_unavailable_error( $e->getMessage() ) ) {
				// Try to extract container_id from the response for retry optimization.
				$container_id = $this->extract_container_id_from_last_response();
				if ( ! empty( $container_id ) ) {
					$body['container_id'] = $container_id;
				}

				$this->schedule_retry( $user_id, $action_data, $recipe_id, $body, 1 );
				Automator()->complete->action( $user_id, $action_data, $recipe_id );
				return;
			}

			// Log all other errors.
			$action_data['complete_with_errors'] = true;

			$error_message = $this->get_beautified_error_message( $e->getMessage() );

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

		}
	}

	/**
	 * Transform API response into more comprehensive message.
	 *
	 * @param string $error_mesage The original error message.
	 *
	 * @return string The error message.
	 */
	protected function get_beautified_error_message( $error_message = '' ) {

		if ( false !== strpos( $error_message, 'cannot be loaded due to missing permissions' ) ) {

			return esc_attr_x( 'Instagram account not found. Check that the requested account is connected to the associated Facebook page.', 'Instagram', 'uncanny-automator' );

		}

		return $error_message;
	}
}
