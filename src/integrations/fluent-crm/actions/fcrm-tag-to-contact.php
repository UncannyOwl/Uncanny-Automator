<?php
namespace Uncanny_Automator;

use FluentCrm\App\Models\Subscriber as FluentCRM_Subscriber;

/**
 * Class FCRM_TAG_TO_CONTACT
 *
 * @package Uncanny_Automator
 */
class FCRM_TAG_TO_CONTACT {

	use \Uncanny_Automator\Recipe\Actions;

	public function __construct() {

		$this->setup_action();

		$this->register_action();

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'FCRM' );

		$this->set_action_code( 'FCRM_TAG_TO_CONTACT' );

		$this->set_action_meta( 'FCRM_TAG_TO_CONTACT_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'integration/fluentcrm/' ) );

		$this->set_requires_user( false );

		/* translators: tag name */
		$this->set_sentence( sprintf( esc_attr__( 'Add {{tags:%1$s}} to a contact', 'uncanny-automator' ), $this->get_action_meta() ) );

		$this->set_readable_sentence( esc_attr__( 'Add {{tags}} to a contact', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

	}

	/**
	 * Method load_options
	 *
	 * @return void
	 */
	public function load_options() {

		$options[] = Automator()->helpers->recipe->fluent_crm->options->fluent_crm_tags( null, $this->action_meta, array( 'supports_multiple_values' => true ) );

		$options[] = Automator()->helpers->recipe->fluent_crm->options->get_email_field();

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => $options,
				),
			)
		);

	}

	/**
	 * Method process_action.
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

		$tags = array_map( 'intval', json_decode( $action_data['meta'][ $this->get_action_meta() ] ) );

		$email_address = isset( $parsed['EMAIL'] ) ? sanitize_text_field( $parsed['EMAIL'] ) : '';

		try {

			if ( ! class_exists( 'FluentCrm\App\Models\Subscriber' ) ) {
				throw new \Exception( 'FluentCRM is not active.' );
			}

			$subscriber = FluentCRM_Subscriber::where( 'email', $email_address )->first();

			// Contact must exists and must have valid email address.
			$this->validate( $email_address, $subscriber );

			$subscriber->attachTags( $tags );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

	/**
	 * Validate the action before it actually tries to execute.
	 *
	 * @param string $email_address
	 * @param array $subscriber
	 *
	 * @return boolean True if no \Exception occurs.
	 */
	public function validate( $email_address = '', $subscriber = array() ) {

		if ( empty( $email_address ) ) {
			throw new \Exception( 'Cannot assign tag(s) to a contact with empty email address.' );
		}

		if ( ! filter_var( $email_address, FILTER_VALIDATE_EMAIL ) ) {
			throw new \Exception( sprintf( 'The email address (%s) contains invalid format.', $email_address ) );
		}

		if ( empty( $subscriber ) ) {
			throw new \Exception( sprintf( 'Adding tag(s) to a non-existing contact (%s).', $email_address ) );
		}

		return true;

	}


}
