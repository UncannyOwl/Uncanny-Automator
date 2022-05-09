<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class FACEBOOK_GROUP_PUBLISH_LINK
 *
 * @package Uncanny_Automator
 */
class FACEBOOK_GROUP_PUBLISH_LINK {

	use Recipe\Actions;

	const INTEGRATION = 'FACEBOOK_GROUPS';

	const CODE = 'FACEBOOK_GROUPS_PUBLISH_LINK';

	const META = 'FACEBOOK_GROUPS_PUBLISH_LINK_META';

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

		/* translators: Action - WordPress */
		$this->set_sentence(
			/* translators:The action sentence */
			sprintf( esc_attr__( 'Share a link with a message to {{a Facebook group:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() )
		);

		/* translators: Action - WordPress */
		$this->set_readable_sentence(
			esc_attr__(
				'Share a link with a message to {{a Facebook group}}',
				'uncanny-automator'
			)
		);

		$this->set_buttons(
			Automator()->helpers->recipe->facebook_groups->options->buttons(
				$this->get_action_meta(),
				automator_utm_parameters( $this->get_support_link(), 'facebook-group_publish_post', 'help_button' )
			)
		);

		$this->set_options_group( $this->get_options_group() );

		$this->register_action();

	}

	/**
	 * Proccess our action.
	 *
	 * @return void.
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$helper = Automator()->helpers->recipe->facebook_groups->options;

		// Group ID.
		$group_id = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;

		// Message.
		$message = isset( $parsed['FACEBOOK_GROUPS_PUBLISH_LINK_MESSAGE'] ) ? sanitize_textarea_field( $parsed['FACEBOOK_GROUPS_PUBLISH_LINK_MESSAGE'] ) : '';

		// Link.
		$link = isset( $parsed['FACEBOOK_GROUPS_PUBLISH_LINK_URL'] ) ? sanitize_text_field( $parsed['FACEBOOK_GROUPS_PUBLISH_LINK_URL'] ) : '';

		$body = array(
			'access_token' => $helper->get_user_access_token(),
			'message'      => $message,
			'link'         => $link,
			'action'       => 'send_link',
			'group_id'     => $group_id,
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
					'option_code'           => 'FACEBOOK_GROUPS_PUBLISH_LINK_URL',
					'label'                 => esc_attr__( 'External link URL', 'uncanny-automator' ),
					'placeholder'           => esc_attr__( 'https://', 'uncanny-automator' ),
					'description'           => esc_attr__( "Enter the URL of the site you want to share to your Facebook Group. Start with 'https://' to share an external link.", 'uncanny-automator' ),
					'input_type'            => 'url',
					'supports_custom_value' => false,
					'required'              => true,
				),
				array(
					'option_code' => 'FACEBOOK_GROUPS_PUBLISH_LINK_MESSAGE',
					'label'       => esc_attr__( 'Message', 'uncanny-automator' ),
					'input_type'  => 'textarea',
					'required'    => true,
				),
			),
		);
	}

}
