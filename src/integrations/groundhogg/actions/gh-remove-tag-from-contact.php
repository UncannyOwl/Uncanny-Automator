<?php
namespace Uncanny_Automator;

use Groundhogg\DB\Tags;
use Groundhogg\Plugin;

/**
 * Class GH_REMOVE_TAG_FROM_CONTACT
 *
 * @package Uncanny_Automator
 */
class GH_REMOVE_TAG_FROM_CONTACT extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	protected function setup_action() {
		$this->set_integration( 'GH' );
		$this->set_action_code( 'GH_REMOVE_TAG_FROM_CONTACT' );
		$this->set_action_meta( 'GH_TAGS' );
		$this->set_requires_user( false );
		/* translators: Action - Groundhogg */
		$this->set_sentence( sprintf( esc_attr_x( 'Remove {{a tag:%1$s}} from {{a contact:%2$s}}', 'Groundhogg', 'uncanny-automator' ), $this->get_action_meta(), 'CONTACT_EMAIL:' . $this->get_action_meta() ) );
		/* translators: Action - Groundhogg */
		$this->set_readable_sentence( esc_attr_x( 'Remove {{a tag}} from a contact', 'Groundhogg', 'uncanny-automator' ) );
	}

	/**
	 * Define options for the action
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Tag', 'Groundhogg', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'supports_custom_value' => false,
				'options'     => Groundhogg_Helpers::get_tag_options(),
			),
			array(
				'option_code' => 'CONTACT_EMAIL',
				'label' => esc_html_x( 'Contact email', 'Groundhogg', 'uncanny-automator' ),
				'input_type' => 'email',
				'required' => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$contact_email = isset( $parsed['CONTACT_EMAIL'] ) ? sanitize_email( $parsed['CONTACT_EMAIL'] ) : '';

		if ( ! is_email( $contact_email ) ) {
			$this->add_log_error( esc_html_x( 'Invalid email adderss', 'Groundhogg', 'uncanny-automator' ) );

			return false;
		}

		$contact = Plugin::$instance->utils->get_contact( $contact_email, false );

		if ( ! $contact ) {
			$this->add_log_error( esc_html_x( 'Contact was not found', 'Groundhogg', 'uncanny-automator' ) );

			return false;
		}

		$tag_id = isset( $parsed[ $this->get_action_meta() ] ) ? $parsed[ $this->get_action_meta() ] : '';
		$tags   = new Tags();

		if ( false === $tags->exists( $tag_id ) ) {
			$this->add_log_error( esc_html_x( 'Tag does not exists', 'Groundhogg', 'uncanny-automator' ) );

			return false;
		}

		$tags_to_remove = array( absint( $tag_id ) );
		$contact->remove_tag( $tags_to_remove );

		return true;
	}
}
