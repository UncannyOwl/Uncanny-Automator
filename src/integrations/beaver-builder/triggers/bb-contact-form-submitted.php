<?php

namespace Uncanny_Automator\Integrations\Beaver_Builder;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Bb_Contact_Form_Submitted
 *
 * Fires when a logged-in user submits a Beaver Builder Contact Form module and
 * the email send is attempted. The "Everyone" (anonymous) variant lives in
 * Automator Pro.
 *
 * @package Uncanny_Automator\Integrations\Beaver_Builder
 *
 * @property Beaver_Builder_Helpers $item_helpers
 */
class Bb_Contact_Form_Submitted extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'BB_CONTACT_FORM_SUBMITTED', 'BEAVER_BUILDER' )
			->trigger_meta( 'BB_CONTACT_FORM' )
			->hook( 'fl_module_contact_form_after_send', 10, 6 );
	}

	/**
	 * The Contact Form module ships only with Beaver Builder Pro.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return class_exists( 'FLContactFormModule' );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_sentence(
			sprintf(
				/* translators: 1: Contact form */
				esc_html_x( 'A user submits {{a contact form:%1$s}}', 'Beaver Builder', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user submits {{a contact form}}', 'Beaver Builder', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Contact form', 'Beaver Builder', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'contact_forms' ),
			),
		);
	}

	/**
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->contact_form_tokens() );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		// Logged-in half of the pair — the anonymous "Everyone" variant in Pro
		// handles submissions by visitors who are not signed in.
		$user_id = get_current_user_id();
		if ( 0 === $user_id ) {
			return false;
		}

		// The hook carries no form/page identity — both the node ID and page ID
		// live in the (nonce-verified upstream) AJAX $_POST payload. Match on the
		// module node ID, which is the stable per-form identifier.
		$node_id  = sanitize_text_field( (string) automator_filter_input( 'node_id', INPUT_POST ) );
		$selected = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? (string) $trigger['meta'][ $this->get_trigger_meta() ] : Beaver_Builder_Helpers::ANY_VALUE;

		if ( Beaver_Builder_Helpers::ANY_VALUE !== $selected && $selected !== $node_id ) {
			return false;
		}

		// Intentional: fire on the submission itself, regardless of $hook_args[5]
		// (the wp_mail() result). The event is "a user submitted the form" — the
		// admin-notification email is a side effect, and its outcome is exposed
		// via the BB_CONTACT_SEND_RESULT token so recipes can branch on a failed
		// send (e.g. notify another channel) instead of silently not running.
		// This differs from the subscribe trigger, which gates on the service
		// response because a failed subscription means the core action never
		// happened.

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return $this->item_helpers->hydrate_contact_form_tokens( $hook_args );
	}
}
