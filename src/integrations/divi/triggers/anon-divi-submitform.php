<?php

namespace Uncanny_Automator\Integrations\Divi;

/**
 * Class ANON_DIVI_SUBMIT_FORM
 *
 * Anonymous variant — fires whether or not the submitter is logged in.
 *
 * @method Divi_Helpers get_item_helpers()
 *
 * @package Uncanny_Automator\Integrations\Divi
 */
class ANON_DIVI_SUBMIT_FORM extends \Uncanny_Automator\Recipe\Trigger {

	const TRIGGER_CODE = 'ANON_DIVI_SUBMIT_FORM';
	const TRIGGER_META = 'DIVIFORM';

	/**
	 * Lazy-load definition.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( self::TRIGGER_CODE, 'DIVI' )
			->trigger_meta( self::TRIGGER_META )
			->trigger_type( 'anonymous' )
			->hook( 'et_pb_contact_form_submit', 100, 3 );
	}

	/**
	 * setup_trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().

		// translators: %1$s is the form picker token name
		$this->set_sentence( sprintf( esc_html_x( '{{A form:%1$s}} is submitted', 'Divi', 'uncanny-automator' ), self::TRIGGER_META ) );
		$this->set_readable_sentence( esc_html_x( '{{A form}} is submitted', 'Divi', 'uncanny-automator' ) );
	}

	/**
	 * options.
	 *
	 * @return array
	 */
	public function options() {
		return array( $this->get_item_helpers()->form_select_config( self::TRIGGER_META ) );
	}

	/**
	 * define_tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return $this->get_item_helpers()->tokens()->define_form_tokens(
			$tokens,
			$trigger['meta'][ self::TRIGGER_META ] ?? '',
			self::TRIGGER_META
		);
	}

	/**
	 * validate.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		return Divi_Helpers::matches_form_selection( $hook_args, $trigger['meta'][ self::TRIGGER_META ] ?? '' );
	}

	/**
	 * hydrate_tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return $this->get_item_helpers()->tokens()->hydrate_from_hook_args( $hook_args );
	}
}
