<?php

namespace Uncanny_Automator\Integrations\Cfdb7;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Cfdb7_Entry_Saved
 *
 * Fires on `cfdb7_after_save_data`, which CFDB7 fires right after it inserts a
 * Contact Form 7 submission into `db7_forms`. That is what separates this from
 * the Contact Form 7 integration's submit trigger: the entry is known to be
 * stored, and its ID is available to later actions.
 *
 * Anonymous by design — CF7 forms are usually submitted by guests.
 *
 * @package Uncanny_Automator\Integrations\Cfdb7
 * @since 8.1.0
 *
 * @property Cfdb7_Helpers $item_helpers
 */
class Cfdb7_Entry_Saved extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'CFDB7_ENTRY_SAVED', 'CFDB7' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'CFDB7_FORM' )
			->hook( 'cfdb7_after_save_data', 10, 2 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Contact form */
				esc_html_x( 'An entry is saved for {{a form:%1$s}}', 'Contact Form CFDB7', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'An entry is saved for {{a form}}', 'Contact Form CFDB7', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Form', 'Contact Form CFDB7', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'forms' ),
				// Tokens come from define_tokens(), per selected form.
				'relevant_tokens'       => array(),
			),
		);
	}

	/**
	 * Validate trigger.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments: entry ID, saved form data.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $entry_id, $form_data ) = array_pad( (array) $hook_args, 2, null );

		$entry_id = absint( $entry_id );
		$selected = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? '' );

		if ( 0 === $entry_id || ! is_array( $form_data ) || '' === $selected ) {
			return false;
		}

		// The hook does not pass the form, so it is read from the stored row.
		if ( '-1' !== $selected && absint( $selected ) !== $this->item_helpers->get_entry_form_id( $entry_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$form_id = $trigger['meta'][ $this->get_trigger_meta() ] ?? '-1';

		return array_merge(
			$tokens,
			$this->item_helpers->get_entry_tokens_config(),
			$this->item_helpers->get_field_tokens_config( $form_id )
		);
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $entry_id, $form_data ) = array_pad( (array) $hook_args, 2, null );

		return $this->item_helpers->hydrate_entry_tokens( absint( $entry_id ), is_array( $form_data ) ? $form_data : array() );
	}
}
