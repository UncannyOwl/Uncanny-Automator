<?php
namespace Uncanny_Automator;

/**
 * Class LINKEDIN_POST_PUBLISH_IMAGE
 *
 * @package Uncanny_Automator
 */
class LINKEDIN_POST_PUBLISH_IMAGE {

	use \Uncanny_Automator\Recipe\Actions;

	public function __construct() {

		$this->setup_action();

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'LINKEDIN' );

		$this->set_action_code( 'LINKEDIN_POST_PUBLISH_IMAGE' );

		$this->set_action_meta( 'LINKEDIN_POST_PUBLISH_IMAGE_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/linkedin/' ) );

		$this->set_requires_user( false );

		/* translators: tag name */
		$this->set_sentence( sprintf( esc_attr__( 'Publish a post with an image to {{a LinkedIn page:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );

		$this->set_readable_sentence( esc_attr__( 'Publish a post with an image to {{a LinkedIn page}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_wpautop( false );

		$this->register_action();

	}

	/**
	 * Loads the options.
	 *
	 * @return array The option loaded.
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						array(
							'option_code'           => $this->get_action_meta(),
							'label'                 => esc_attr__( 'LinkedIn Page', 'uncanny-automator' ),
							'input_type'            => 'select',
							'is_ajax'               => true,
							'endpoint'              => 'automator_linkedin_get_pages',
							'supports_custom_value' => false,
							'required'              => true,
						),
						array(
							'option_code'     => 'IMAGE',
							'label'           => esc_attr__( 'Image URL or Media library ID', 'uncanny-automator' ),
							'description'     => esc_attr__( 'The image must be in a JPG, JPEG or PNG format. The file name must not contain spaces and extended JPEG formats (such as MPO and JPS) are not supported.', 'uncanny-automator' ),
							'input_type'      => 'text',
							'supports_tokens' => true,
							'required'        => true,
						),
						array(
							'option_code'     => 'BODY',
							'label'           => esc_attr__( 'Content', 'uncanny-automator' ),
							'input_type'      => 'textarea',
							'supports_tokens' => true,
							'required'        => true,
						),
					),
				),
			)
		);

	}

	/**
	 * Processes action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$helper = new Linkedin_Helpers( false );

		$content = isset( $parsed['BODY'] ) ? sanitize_textarea_field( $parsed['BODY'] ) : '';

		// Sanitize.
		$content = preg_replace_callback(
			'/([\(\)\{\}\[\]])|([@*<>\\\\\_~])/m',
			function ( $matches ) {
				return '\\' . $matches[0];
			},
			$content
		);

		$urn = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';

		$image = isset( $parsed['IMAGE'] ) ? sanitize_text_field( $parsed['IMAGE'] ) : '';

		try {

			$image_url = $this->resolve_image_url( $image );

			// Handles invalid Media ID or URL.
			if ( false === $image_url || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
				throw new \Exception( 'Input error: Check token value. The provided image URL is empty or invalid.' . $image_url );
			}

			$body = array(
				'access_token' => $helper->get_client()['access_token'],
				'content'      => $content,
				'image_url'    => $image_url,
				'urn'          => $urn,
				'action'       => 'post_media_publish',
			);

			$response = $helper->api_call( $body, $action_data );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

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
	private function resolve_image_url( $media ) {

		if ( is_numeric( $media ) ) {
			return wp_get_attachment_url( $media );
		}

		return $media;

	}


}
