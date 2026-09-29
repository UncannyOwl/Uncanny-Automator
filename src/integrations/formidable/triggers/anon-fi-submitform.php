<?php

namespace Uncanny_Automator\Integrations\Formidable;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class ANON_FI_SUBMITFORM
 *
 * @package Uncanny_Automator
 *
 * @property Formidable_Helpers $item_helpers
 */
class ANON_FI_SUBMITFORM extends Trigger {

	/**
	 * Declare the trigger so the engine can register its hook without
	 * constructing the class on every frontend request.
	 *
	 * @return object
	 */
	public static function definition() {
		return self::new_definition( 'ANONFISUBMITFORM', 'FI' )
			->trigger_meta( 'ANONFIFORM' )
			->trigger_type( 'anonymous' )
			->hook( 'frm_after_create_entry', 20, 2 );
	}

	/**
	 * Trigger setup.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/formidable-forms/' ) );

		/* translators: %1$s is the form selector */
		$this->set_sentence( sprintf( esc_html_x( '{{A form:%1$s}} is submitted', 'Formidable', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( '{{A form}} is submitted', 'Formidable', 'uncanny-automator' ) );
	}

	/**
	 * Trigger fields.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->item_helpers->get_form_option_config( $this->get_trigger_meta() ),
		);
	}

	/**
	 * Token definitions, including one per field of the selected form.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$meta        = $this->get_trigger_meta();
		$form_id     = absint( $trigger['meta'][ $meta ] ?? 0 );
		$token_class = $this->item_helpers->tokens();

		return array_merge(
			$tokens,
			$token_class->entry_tokens(),
			$token_class->form_tokens( $meta ),
			$token_class->form_field_tokens( $form_id, $meta )
		);
	}

	/**
	 * Does this submission match the form the recipe selected?
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $entry_id, $form_id ) = array_pad( $hook_args, 2, null );

		if ( empty( $entry_id ) || empty( $form_id ) ) {
			return false;
		}

		$selected = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		if ( intval( '-1' ) !== intval( $selected ) && absint( $form_id ) !== absint( $selected ) ) {
			return false;
		}

		// Drafts and Form Abandonment "In progress" entries fire this hook too.
		if ( ! $this->item_helpers->is_completed_entry( $entry_id ) ) {
			return false;
		}

		// A logged-in visitor submitting a public form still runs as themselves.
		$user_id = get_current_user_id();

		if ( ! empty( $user_id ) ) {
			$this->set_user_id( $user_id );
		}

		return true;
	}

	/**
	 * Token values for this submission.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $entry_id, $form_id ) = array_pad( $hook_args, 2, null );

		$meta        = $this->get_trigger_meta();
		$token_class = $this->item_helpers->tokens();

		return array_merge(
			$token_class->hydrate_entry_tokens( $entry_id ),
			$token_class->hydrate_form_tokens( $form_id, $meta ),
			$token_class->hydrate_form_field_tokens( $entry_id, $form_id, $meta )
		);
	}
}
