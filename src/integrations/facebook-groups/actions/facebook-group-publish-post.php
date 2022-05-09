<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class FACEBOOK_GROUP_PUBLISH_POST
 *
 * @package Uncanny_Automator
 */
class FACEBOOK_GROUP_PUBLISH_POST {

	use Recipe\Actions;

	const INTEGRATION = 'FACEBOOK_GROUPS';

	const CODE = 'FACEBOOK_GROUPS_PUBLISH_POST';

	const META = 'FACEBOOK_GROUPS_PUBLISH_POST_META';

	public function __construct() {

		$this->setup_action();

	}

	/**
	 * Setups action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_action_code( self::CODE );

		$this->set_action_meta( self::META );

		$this->set_integration( self::INTEGRATION );

		$this->set_is_pro( false );

		$this->set_requires_user( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/facebook-groups' ) );

		$this->set_sentence(
			/* translators: The action sentence */
			sprintf( esc_attr__( 'Publish a post to {{a Facebook group:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() )
		);

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Publish a post to {{a Facebook group}}', 'uncanny-automator' ) );

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
	 * Returns the list of options for the action.
	 *
	 * @return array The fields.
	 */
	public function get_options_group() {
		return array(
			$this->get_action_meta() => array(
				Automator()->helpers->recipe->facebook_groups->options->get_groups_field( $this->get_action_meta() ),
				array(
					'option_code' => 'FACEBOOK_GROUP_MESSAGE',
					'input_type'  => 'textarea',
					'label'       => esc_attr__( 'Message', 'uncanny-automator' ),
					'description' => esc_attr__( 'Enter the message that you want to post on Facebook. Please take note that this action might fail when posting the same messages within short intervals.', 'uncanny-automator' ),
					'required'    => true,
				),
			),
		);
	}

	/**
	 * Proccess the action.
	 *
	 * @return void.
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$helper = Automator()->helpers->recipe->facebook_groups->options;

		$group_id = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;

		$message = isset( $parsed['FACEBOOK_GROUP_MESSAGE'] ) ? sanitize_textarea_field( $parsed['FACEBOOK_GROUP_MESSAGE'] ) : '';

		$body = array(
			'action'       => 'send_message',
			'access_token' => $helper->get_user_access_token(),
			'message'      => $message,
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

}
