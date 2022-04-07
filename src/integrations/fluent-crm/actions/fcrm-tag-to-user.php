<?php

namespace Uncanny_Automator;

use FluentCrm\App\Models\Subscriber;

/**
 * Class FCRM_TAG_TO_USER
 *
 * @package Uncanny_Automator
 */
class FCRM_TAG_TO_USER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'FCRM';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'FCMRTAGUSER';
		$this->action_meta = 'FCRMTAG';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/fluentcrm/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - LearnDash */
			'sentence'           => sprintf( esc_attr_x( 'Add {{tags:%1$s}} to the user', 'Fluent Forms', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - LearnDash */
			'select_option_name' => esc_attr_x( 'Add {{tags}} to the user', 'Fluent Forms', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'tag_to_user' ),
			'options'            => array(
				Automator()->helpers->recipe->fluent_crm->options->fluent_crm_tags( null, $this->action_meta, array( 'supports_multiple_values' => true ) ),
			),
		);

		Automator()->register->action( $action );
	}


	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function tag_to_user( $user_id, $action_data, $recipe_id, $args ) {

		$tags = array_map( 'intval', json_decode( $action_data['meta'][ $this->action_meta ] ) );

		$user_info = get_userdata( $user_id );

		if ( $user_info ) {

			$subscriber = Subscriber::where( 'email', $user_info->user_email )->first();

			// User exists but is not a FluentCRM contact.
			if ( false === $subscriber || is_null( $subscriber ) ) {

				// Add the user as a contact.
				$subscriber = Automator()->helpers->recipe->fluent_crm->add_user_as_contact( $user_info );

				// Did not create new contact successfully.
				if ( false === $subscriber ) {

					// Do nothing.
					$action_data['do-nothing'] = true;

					// Complete with errors.
					$action_data['complete_with_errors'] = true;

					// Send some error message to the log.
					$message = esc_html__( 'There was an error while trying to add the user as a FluentCRM contact.', 'uncanny-automator' );

					Automator()->complete_action( $user_id, $action_data, $recipe_id, $message );

					return;

				}
			}

			// If already a subscriber.
			if ( $subscriber ) {

				// Attach the tags.
				$subscriber->attachTags( $tags );

				// Complete the action even if tags already exists.
				Automator()->complete_action( $user_id, $action_data, $recipe_id );

				return;
			}
		} else {

			// User does not exist
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			$message                             = sprintf(
			/* translators: 1. The user id */
				_x( 'User does not exist: %1$s', 'FluentCRM', 'uncanny-automator' ),
				$user_id
			);

			Automator()->complete_action( $user_id, $action_data, $recipe_id, $message );

			return;
		}
	}

}
