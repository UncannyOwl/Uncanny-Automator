<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Instagram;

use Exception;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job_Result;

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
			// The image url. Uses 'text' (not 'url') because the field accepts
			// either a URL or a numeric media library ID; a 'url' input makes the
			// UI prepend "http://" to a bare ID (e.g. 5452 => http://5452).
			// The value is resolved/sanitized on the backend in resolve_image_url().
			array(
				'option_code' => 'INSTAGRAM_IMAGE_URL',
				'label'       => esc_html_x( 'Image URL or Media library ID', 'Instagram', 'uncanny-automator' ),
				'input_type'  => 'text',
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
	 * Define the action's output tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'INSTAGRAM_POST_URL' => array(
				'name' => esc_html_x( 'Post URL', 'Instagram', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}

	/**
	 * Process the Instagram action. The platform may accept the publish as a background job, publish it at once,
	 * or answer that Instagram is still processing the media; the last finishes as a site job that retries the
	 * same container.
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

		list( $page_id, $image_uri, $caption ) = $this->publish_args( $parsed );

		try {
			$result = $this->api->publish_photo( $page_id, $image_uri, $caption, '', $action_data );
		} catch ( \Throwable $e ) {
			// Catch \Throwable (not just Exception) so a PHP \Error in the API /
			// bridge path fails the action cleanly instead of escaping as an
			// uncaught fatal that white-screens the site.
			$error = $this->action_error( $e );
			throw $error;
		}

		if ( $this->deferred( $result ) ) {
			return true;
		}

		if ( Instagram_Api_Caller::is_media_processing( $result ) ) {
			if ( $this->defer_to_site( array( 'container_id' => (string) ( $result['data']['container_id'] ?? '' ) ), MINUTE_IN_SECONDS, 10 ) ) {
				return true;
			}
			throw Action_Error_Exception::execution_failed( esc_html_x( 'Instagram is still processing this photo.', 'Instagram', 'uncanny-automator' ) );
		}

		return $this->process_job( is_array( $result ) ? $result : array(), $parsed, $action_data );
	}

	/**
	 * Fill the Post URL from the platform's answer: the synchronous write's envelope or a background job's
	 * completion, whose result sits under `data` in both.
	 *
	 * @since 8.0.0
	 *
	 * @param array $response    The write's envelope, or the job's completion envelope.
	 * @param array $parsed      The parsed field values.
	 * @param array $action_data The action data.
	 *
	 * @return bool
	 */
	protected function process_job( array $response, array $parsed, array $action_data ): bool {
		$this->hydrate_tokens(
			array(
				'INSTAGRAM_POST_URL' => (string) ( $response['data']['permalink'] ?? '' ),
			)
		);

		return true;
	}

	/**
	 * One synchronous re-publish of the container Instagram was still processing. A transport failure or a
	 * transient answer (no status, 408, 429, 5xx) waits for the next attempt; the attempt cap bounds it.
	 *
	 * @since 8.0.0
	 *
	 * @param array $parsed     The parsed field values recorded when the action ran.
	 * @param array $remembered `container_id`.
	 *
	 * @return Background_Job_Result|null Null while Instagram is still processing the media.
	 */
	protected function retry_job( array $parsed, array $remembered ): ?Background_Job_Result {
		list( $page_id, $image_uri, $caption ) = $this->publish_args( $parsed );

		try {
			$result = $this->api->publish_photo( $page_id, $image_uri, $caption, (string) ( $remembered['container_id'] ?? '' ) );
		} catch ( \Throwable $e ) {
			$code = (int) $e->getCode();
			if ( in_array( $code, array( 0, 408, 429 ), true ) || ( $code >= 500 && $code < 600 ) ) {
				return null;
			}

			return Background_Job_Result::from_site(
				Background_Job_Result::STATUS_FAILED,
				array(),
				array(
					'code'        => $code,
					'description' => $this->get_beautified_error_message( $e->getMessage() ),
				)
			);
		}

		if ( Instagram_Api_Caller::is_media_processing( $result ) ) {
			return null;
		}

		return Background_Job_Result::from_site( Background_Job_Result::STATUS_COMPLETED, is_array( $result ) ? $result : array(), array() );
	}

	/**
	 * The page, image URL and caption the publish sends.
	 *
	 * @param array $parsed The parsed field values.
	 *
	 * @return array{0: string, 1: string, 2: string}
	 * @throws \Exception When the image URL cannot be resolved.
	 */
	private function publish_args( array $parsed ) {

		$page_id   = sanitize_text_field( $parsed['INSTAGRAM_PUBLISH_PHOTO_ACCOUNT_ID'] );
		$image_uri = sanitize_text_field( $parsed['INSTAGRAM_IMAGE_URL'] );
		$caption   = sanitize_textarea_field( $parsed['INSTAGRAM_HASHTAGS'] );
		$image_uri = $this->resolve_image_url( $image_uri );

		if ( ! empty( $caption ) ) {
			// Replace new lines with double new lines.
			$caption = str_replace( "\r\n", "\n\n", $caption );
		}

		return array( $page_id, $image_uri, $caption );
	}

	/**
	 * The action's failure for what the publish threw: an account the page cannot load is not found; a typed
	 * failure from the caller keeps its code; anything else failed to execute, with the beautified text.
	 *
	 * @param \Throwable $e What the publish threw.
	 *
	 * @return Action_Error_Exception
	 */
	private function action_error( \Throwable $e ) {
		if ( $this->is_missing_permissions( $e->getMessage() ) ) {
			return Action_Error_Exception::not_found( esc_html( $this->get_beautified_error_message( $e->getMessage() ) ) );
		}
		if ( $e instanceof Action_Error_Exception ) {
			return $e;
		}

		return Action_Error_Exception::execution_failed( esc_html( $this->get_beautified_error_message( $e->getMessage() ) ) );
	}

	/**
	 * Whether Meta refused the page's Instagram account for missing permissions.
	 *
	 * @param string $message The error message.
	 *
	 * @return bool
	 */
	private function is_missing_permissions( $message ) {
		return false !== strpos( (string) $message, 'cannot be loaded due to missing permissions' );
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
		if ( $this->is_missing_permissions( $error_message ) ) {
			return esc_html_x( 'Instagram account not found. Check that the requested account is connected to the associated Facebook page.', 'Instagram', 'uncanny-automator' );
		}
		return $error_message;
	}
}
