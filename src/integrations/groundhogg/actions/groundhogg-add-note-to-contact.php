<?php

namespace Uncanny_Automator\Integrations\Groundhogg;

/**
 * Class GH_ADD_NOTE_TO_CONTACT
 *
 * @package Uncanny_Automator\Integrations\Groundhogg
 *
 * @property Groundhogg_Helpers $item_helpers
 */
class GH_ADD_NOTE_TO_CONTACT extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Define and register the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GH' );
		$this->set_action_code( 'GH_ADD_NOTE_TO_CONTACT' );
		$this->set_action_meta( 'GH_NOTE_CONTACT' );
		$this->set_requires_user( false );
		// translators: %1$s is the contact email, %2$s is the note type
		$this->set_sentence( sprintf( esc_html_x( 'Add {{a note:%1$s}} to {{a contact:%2$s}}', 'Groundhogg', 'uncanny-automator' ), 'NOTE_TYPE:' . $this->get_action_meta(), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Add {{a note}} to {{a contact}}', 'Groundhogg', 'uncanny-automator' ) );
	}

	/**
	 * Define options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->item_helpers->contact_email_option_config(),
			array(
				'option_code'           => 'NOTE_TYPE',
				'input_type'            => 'select',
				'label'                 => esc_html_x( 'Note type', 'Groundhogg', 'uncanny-automator' ),
				'required'              => true,
				'supports_custom_value' => true,
				'options_show_id'       => false,
				'default_value'         => 'note',
				'options'               => array(
					array(
						'value' => 'note',
						'text'  => esc_html_x( 'Note', 'Groundhogg', 'uncanny-automator' ),
					),
					array(
						'value' => 'call',
						'text'  => esc_html_x( 'Call', 'Groundhogg', 'uncanny-automator' ),
					),
					array(
						'value' => 'email',
						'text'  => esc_html_x( 'Email', 'Groundhogg', 'uncanny-automator' ),
					),
					array(
						'value' => 'meeting',
						'text'  => esc_html_x( 'Meeting', 'Groundhogg', 'uncanny-automator' ),
					),
				),
			),
			array(
				'option_code'      => 'NOTE_CONTENT',
				'input_type'       => 'textarea',
				'label'            => esc_html_x( 'Note', 'Groundhogg', 'uncanny-automator' ),
				'required'         => true,
				'supports_tinymce' => true,
			),
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
	 * @throws \Exception If the contact is not found.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$contact = $this->item_helpers->get_contact_by_email( $parsed['CONTACT_EMAIL'] ?? '' );
		$type    = strtolower( sanitize_text_field( $parsed['NOTE_TYPE'] ?? 'note' ) );
		$content = wp_kses_post( $parsed['NOTE_CONTENT'] ?? '' );

		if ( empty( $content ) ) {
			throw new \Exception( esc_html_x( 'Note content is required.', 'Groundhogg', 'uncanny-automator' ) );
		}

		$contact->add_note( $content, 'user', 0, array( 'type' => $type ) );

		return true;
	}
}
