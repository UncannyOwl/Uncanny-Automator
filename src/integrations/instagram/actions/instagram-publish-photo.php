<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Instagram;

use Exception;

/**
 * Class INSTAGRAM_PUBLISH_PHOTO
 *
 * @package Uncanny_Automator
 * @property Instagram_App_Helpers $helpers
 * @property Instagram_Api_Caller $api
 */
class INSTAGRAM_PUBLISH_PHOTO extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {
		$this->set_integration( 'INSTAGRAM' );
		$this->set_action_code( 'INSTAGRAM_PUBLISH_PHOTO' );
		$this->set_action_meta( 'INSTAGRAM_PUBLISH_PHOTO_ACCOUNT_ID' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/instagram/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the name of the Instagram Business account.
				esc_attr_x( 'Publish a photo to {{an Instagram Business account:%1$s}}', 'Instagram', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Publish a photo to {{an Instagram Business account}}', 'Instagram', 'uncanny-automator' ) );
		$this->set_wpautop( false );
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
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_attr_x( 'Instagram account', 'Instagram', 'uncanny-automator' ),
				'input_type'            => 'select',
				'supports_custom_value' => false,
				'required'              => true,
				'options'               => $this->helpers->get_instagram_accounts_options(),
			),
			// The image url.
			array(
				'option_code' => 'INSTAGRAM_IMAGE_URL',
				'label'       => esc_html_x( 'Image URL or Media library ID', 'Instagram', 'uncanny-automator' ),
				'input_type'  => 'url',
				'required'    => true,
				'placeholder' => esc_html_x( 'https://pathtoimage/image.jpg', 'Instagram', 'uncanny-automator' ),
				'description' => esc_html_x( 'The image must be in a JPG, JPEG or PNG format. The file name must not contain spaces and extended JPEG formats (such as MPO and JPS) are not supported.', 'Instagram', 'uncanny-automator' ),
			),
			// The hashtags.
			array(
				'option_code' => 'INSTAGRAM_HASHTAGS',
				'label'       => esc_html_x( 'Caption', 'Instagram', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
				'placeholder' => esc_html_x( 'My image #description', 'Instagram', 'uncanny-automator' ),
				'description' => esc_html_x( 'Enter the description and/or hashtags that should be posted with the image.', 'Instagram', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Process the Instagram action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed values.
	 *
	 * @return bool True on success.
	 * @throws \Exception When the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$page_id   = sanitize_text_field( $parsed['INSTAGRAM_PUBLISH_PHOTO_ACCOUNT_ID'] );
		$image_uri = sanitize_text_field( $parsed['INSTAGRAM_IMAGE_URL'] );
		$caption   = sanitize_textarea_field( $parsed['INSTAGRAM_HASHTAGS'] );
		$image_uri = $this->resolve_image_url( $image_uri );

		if ( ! empty( $caption ) ) {
			// Replace new lines with double new lines.
			$caption = str_replace( "\r\n", "\n\n", $caption );
		}

		try {
			$this->api->publish_photo( $page_id, $image_uri, $caption, '', $action_data );
		} catch ( Exception $e ) {
			// Check for retryable error (Media ID not available - code 9007).
			if ( $this->helpers->is_media_unavailable_error( $e->getMessage() ) ) {
				// Build retry body with params needed by publish_photo().
				$retry_body = array(
					'page_id'   => $page_id,
					'image_uri' => $image_uri,
					'caption'   => $caption,
				);

				// Try to extract container_id from the response for retry optimization.
				$container_id = $this->helpers->extract_container_id_from_last_response();
				if ( ! empty( $container_id ) ) {
					$retry_body['container_id'] = $container_id;
				}

				// Use $this->action_data so changes persist to do_action() completion call.
				$this->helpers->schedule_retry( $user_id, $this->action_data, $recipe_id, $retry_body, 1 );

				// Return true - the await flag will trigger COMPLETED_AWAITING via filter.
				return true;
			}

			$message = $this->get_beautified_error_message( $e->getMessage() );
			throw new Exception( esc_html( $message ) );
		}

		return true;
	}

	/**
	 * Resolves the image URL from either a direct URL or media library ID.
	 *
	 * @param string $image_uri The image URI or media library ID.
	 * @return string The resolved image URL.
	 * @throws \Exception When the image URL cannot be resolved.
	 */
	private function resolve_image_url( $image_uri ) {
		// Check if it's a proper URL with protocol and domain
		if ( preg_match( '/^https?:\/\/[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}/', $image_uri ) ) {
			return esc_url_raw( $image_uri );
		}

		// If not a proper URL, remove protocol that might have been added by the UI.
		$image_uri = str_replace( array( 'http://', 'https://' ), '', $image_uri );

		// Now check if it's a numeric ID
		if ( is_numeric( $image_uri ) ) {
			$attachment_url = wp_get_attachment_url( intval( $image_uri ) );
			if ( ! empty( $attachment_url ) ) {
				return $attachment_url;
			}
			throw new Exception(
				sprintf(
					// translators: %s is the media library ID
					esc_html_x( 'Media library image with ID %s not found.', 'Instagram', 'uncanny-automator' ),
					absint( $image_uri )
				)
			);
		}

		throw new Exception( esc_html_x( 'Invalid image URL or media library ID provided.', 'Instagram', 'uncanny-automator' ) );
	}

	/**
	 * Transform API response into more comprehensive message.
	 *
	 * @param string $error_mesage The original error message.
	 *
	 * @return string The error message.
	 */
	protected function get_beautified_error_message( $error_message = '' ) {
		// TODO REVIEW : We could adjust this at the Facebook_Api_Caller and provide link to KB article.
		if ( false !== strpos( $error_message, 'cannot be loaded due to missing permissions' ) ) {
			return esc_html_x( 'Instagram account not found. Check that the requested account is connected to the associated Facebook page.', 'Instagram', 'uncanny-automator' );
		}
		return $error_message;
	}
}
