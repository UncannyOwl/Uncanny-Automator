<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class FACEBOOK_GROUP_PUBLISH_PHOTO
 *
 * @package Uncanny_Automator
 */
class FACEBOOK_GROUP_PUBLISH_PHOTO {

	use Recipe\Actions;

	const INTEGRATION = 'FACEBOOK_GROUPS';

	const CODE = 'FACEBOOK_GROUPS_PUBLISH_PHOTO';

	const META = 'FACEBOOK_GROUPS_PUBLISH_PHOTO_META';

	public function __construct() {

		$this->setup_action();

	}

	/**
	 * Setups our action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_action_code( self::CODE );

		$this->set_action_meta( self::META );

		$this->set_integration( self::INTEGRATION );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/facebook-groups' ) );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators:The action sentence */
				esc_attr__( 'Publish a post with an image to {{a Facebook group:%1$s}}', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Publish a post with an image to {{a Facebook group}}', 'uncanny-automator' ) );

		$this->set_options_group( $this->get_options_group() );

		$this->set_buttons(
			Automator()->helpers->recipe->facebook_groups->options->buttons(
				$this->get_action_meta(),
				automator_utm_parameters( $this->get_support_link(), 'facebook-group_publish_post', 'help_button' )
			)
		);

		$this->register_action();

	}

	/**
	 * Returns the list of options for our action.
	 *
	 * @return array The fields.
	 */
	public function get_options_group() {

		$facebook_groups = Automator()->helpers->recipe->facebook_groups->options;

		return array(
			$this->get_action_meta() => array(
				Automator()->helpers->recipe->facebook_groups->options->get_groups_field( $this->get_action_meta() ),
				array(
					'option_code' => 'FACEBOOK_GROUPS_PUBLISH_PHOTO_IMAGE_URL',
					/* translators: Email field */
					'label'       => esc_attr__( 'Image URL', 'uncanny-automator' ),
					'placeholder' => esc_attr__( 'https://examplewebsite.com/path/to/image.jpg', 'uncanny-automator' ),
					'input_type'  => 'url',
					'required'    => true,
					'description' => esc_attr__( 'Enter the URL of the image you wish to share. The URL must be publicly accessible.', 'uncanny-automator' ),
				),
				array(
					'option_code' => 'FACEBOOK_GROUPS_PUBLISH_MESSAGE',
					/* translators: Email field */
					'label'       => esc_attr__( 'Message', 'uncanny-automator' ),
					'placeholder' => esc_attr__( 'The context of the image or description.', 'uncanny-automator' ),
					'input_type'  => 'textarea',
				),
			),
		);

	}

	/**
	 * Proccess our action.
	 *
	 * @return void.
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$helper = Automator()->helpers->recipe->facebook_groups->options;
		// The group id.
		$group_id = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;
		// The caption.
		$caption = isset( $parsed['FACEBOOK_GROUPS_PUBLISH_MESSAGE'] ) ? sanitize_textarea_field( $parsed['FACEBOOK_GROUPS_PUBLISH_MESSAGE'] ) : '';
		// The image url.
		$image_url = isset( $parsed['FACEBOOK_GROUPS_PUBLISH_PHOTO_IMAGE_URL'] ) ? sanitize_text_field( $parsed['FACEBOOK_GROUPS_PUBLISH_PHOTO_IMAGE_URL'] ) : '';

		$body = array(
			'action'       => 'send_photo',
			'access_token' => $helper->get_user_access_token(),
			'caption'      => $caption,
			'group_id'     => $group_id,
			'url'          => $image_url,
		);

		try {

			$helper->api_request( $body, $action_data );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			// Log error if there are any error messages.
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}

	}

}
