<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class FACEBOOK_PAGE_PUBLISH_PHOTO
 *
 * @package Uncanny_Automator
 */
class FACEBOOK_PAGE_PUBLISH_PHOTO {

	use Recipe\Actions;
	use Recipe\Action_Tokens;

	const AJAX_ENDPOINT = 'fb_pages_wp_ajax_endpoint_post_image';

	public function __construct() {

		add_action( 'wp_ajax_' . self::AJAX_ENDPOINT, array( $this, self::AJAX_ENDPOINT ) );

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
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/facebook/' ) );
		$this->set_requires_user( false );

		// Disables wpautop.
		$this->set_wpautop( false );

		/* translators: Action - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Publish a post with an image to {{a Facebook page:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Publish a post with an image to {{a Facebook page}}', 'uncanny-automator' ) );

		$options_group = array(
			$this->get_action_meta() => array(
				// The facebook pages field.
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
				// The photo url field.
				array(
					'option_code' => 'FACEBOOK_PAGE_PUBLISH_PHOTO_IMAGE_URL',
					/* translators: Email field */
					'label'       => esc_attr__( 'Image URL or Media library ID', 'uncanny-automator' ),
					'input_type'  => 'url',
					'required'    => true,
					'description' => esc_attr__( 'Enter the URL or the Media library ID of the image you wish to share. The image must be publicly accessible.', 'uncanny-automator' ),
				),
				// The message field.
				array(
					'option_code' => 'FACEBOOK_PAGE_PUBLISH_MESSAGE',
					/* translators: Email field */
					'label'       => esc_attr__( 'Message', 'uncanny-automator' ),
					'placeholder' => esc_attr__( 'The context of the image or description.', 'uncanny-automator' ),
					'input_type'  => 'textarea',
				),
			),
		);

		$this->set_options_group( $options_group );

		$this->set_action_tokens(
			array(
				'POST_LINK' => array(
					'name' => __( 'Link to Facebook post', 'uncanny-automator' ),
					'type' => 'url',
				),
			),
			$this->get_action_code()
		);

		$this->set_background_processing( true );

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
		$page_id  = isset( $parsed['FACEBOOK_PAGE_PUBLISH_PHOTO_META'] ) ? sanitize_text_field( $parsed['FACEBOOK_PAGE_PUBLISH_PHOTO_META'] ) : 0;
		$media    = isset( $parsed['FACEBOOK_PAGE_PUBLISH_PHOTO_IMAGE_URL'] ) ? sanitize_text_field( $parsed['FACEBOOK_PAGE_PUBLISH_PHOTO_IMAGE_URL'] ) : '';

		// Post content editor adds BR tag if shift+enter. Enter key adds paragraph. Support both.
		$message = isset( $parsed['FACEBOOK_PAGE_PUBLISH_MESSAGE'] ) ? sanitize_textarea_field( $parsed['FACEBOOK_PAGE_PUBLISH_MESSAGE'] ) : '';

		$body = array(
			'action'    => 'image-to-page',
			'image_url' => $this->resolve_image_url( $media ),
			'page_id'   => $page_id,
			'message'   => $message,
		);

		try {

			$response = $facebook->api_request( $page_id, $body, $action_data );

			$post_id = isset( $response['data']['id'] ) ? $response['data']['id'] : 0;

			if ( 0 !== $post_id ) {
				$this->hydrate_tokens( array( 'POST_LINK' => 'https://www.facebook.com/' . $post_id ) );
			}

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			// Log error if there are any error messages.
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

	/**
	 * Resolves the image URL.
	 *
	 * @param mixed $image The public image URL or the Media Library ID.
	 *
	 * @return string The URL of the image or false if its failing.
	 */
	private function resolve_image_url( $media = '' ) {
		return is_numeric( $media ) ? wp_get_attachment_url( $media ) : $media;
	}

}
