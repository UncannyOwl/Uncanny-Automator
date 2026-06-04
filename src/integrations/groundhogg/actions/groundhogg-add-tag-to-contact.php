<?php

namespace Uncanny_Automator\Integrations\Groundhogg;

/**
 * Class GH_ADD_TAG_TO_CONTACT
 *
 * @package Uncanny_Automator\Integrations\Groundhogg
 *
 * @property Groundhogg_Helpers $item_helpers
 */
class GH_ADD_TAG_TO_CONTACT extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GH' );
		$this->set_action_code( 'GH_ADD_TAG_TO_CONTACT' );
		$this->set_action_meta( 'GH_TAGS' );
		$this->set_requires_user( false );
		/* translators: Action - Groundhogg */
		$this->set_sentence( sprintf( esc_html_x( 'Add {{a tag:%1$s}} to {{a contact:%2$s}}', 'Groundhogg', 'uncanny-automator' ), $this->get_action_meta(), 'CONTACT_EMAIL:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Add {{a tag}} to a contact', 'Groundhogg', 'uncanny-automator' ) );
	}

	/**
	 * Define options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->item_helpers->action_tag_option_config( $this->get_action_meta() ),
			$this->item_helpers->contact_email_option_config(),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws \Exception If the email is invalid or the contact is not found.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$helpers = $this->item_helpers;
		$contact = $helpers->get_contact_by_email( $parsed['CONTACT_EMAIL'] ?? '' );
		$tag_id  = $helpers->resolve_tag_id( $parsed[ $this->get_action_meta() ] ?? '' );

		$contact->apply_tag( array( $tag_id ) );

		return true;
	}
}
