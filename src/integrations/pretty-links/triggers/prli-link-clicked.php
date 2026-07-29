<?php

namespace Uncanny_Automator\Integrations\Pretty_Links;

/**
 * Class PRLI_LINK_CLICKED
 *
 * @package Uncanny_Automator
 *
 * @method \Uncanny_Automator\Integrations\Pretty_Links\Pretty_Links_Helpers get_item_helpers()
 */
class PRLI_LINK_CLICKED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Declare the trigger's identity and monitored hook for the code-defined loading path.
	 *
	 * Pretty Links 4.0 records the click and redirects during init (priority 1),
	 * before Automator's normal listener registration — the code-defined metadata
	 * cache gets this hook attached at plugins_loaded so the event is still captured.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'PRLI_LINK_CLICKED', 'PRETTY_LINKS' )
			->trigger_meta( 'PRLI_LINKS' )
			->hook( 'prli_record_click' );
	}

	/**
	 * Set up the trigger's sentences.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// Trigger sentence - Pretty Links
		// translators: 1: Pretty link
		$this->set_sentence( sprintf( esc_attr_x( 'A user clicks {{a pretty link:%1$s}}', 'Pretty Links', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A user clicks {{a pretty link}}', 'Pretty Links', 'uncanny-automator' ) );
	}

	/**
	 * Define the trigger's option fields.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			Automator()->helpers->recipe->field->select_field_args(
				array(
					'input_type'      => 'select',
					'option_code'     => $this->get_trigger_meta(),
					'label'           => esc_html_x( 'Pretty link', 'Pretty Links', 'uncanny-automator' ),
					'required'        => true,
					'options'         => array(),
					'remote_data'     => $this->get_item_helpers()->remote_data_load_config( 'links' ),
					'options_show_id' => false,
					'relevant_tokens' => array(),
				)
			),
		);
	}

	/**
	 * Validate the trigger against the incoming hook arguments.
	 *
	 * @param array $trigger   The trigger definition and selected options.
	 * @param array $hook_args The prli_record_click hook arguments.
	 *
	 * @return bool True when the clicked link matches the trigger's selection.
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		// Fail the trigger if incoming link_id from action hook is not set.
		if ( ! isset( $hook_args[0]['link_id'] ) ) {
			return false;
		}

		$selected_link_id = $trigger['meta'][ $this->get_trigger_meta() ];
		// Any pretty link
		if ( intval( '-1' ) === intval( $selected_link_id ) ) {
			return true;
		}

		if ( absint( $hook_args[0]['link_id'] ) === absint( $selected_link_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Define the tokens exposed by the trigger.
	 *
	 * @param array $trigger The trigger definition and selected options.
	 * @param array $tokens  The existing tokens.
	 *
	 * @return array The tokens merged with the common clicked-link tokens.
	 */
	public function define_tokens( $trigger, $tokens ) {
		$prli_tokens = $this->get_item_helpers()->prli_common_tokens_for_link_clicked();

		return array_merge( $tokens, $prli_tokens );
	}

	/**
	 * Populate the tokens with actual values when the trigger runs.
	 *
	 * @param array $trigger   The trigger definition and selected options.
	 * @param array $hook_args The prli_record_click hook arguments.
	 *
	 * @return array The hydrated token values keyed by token ID.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$parse_token_values = array();

		if ( ! empty( $hook_args ) ) {
			// Hydrate Pretty Links tokens.
			$parse_token_values = $this->get_item_helpers()->hydrate_prli_link_clicked_tokens( $hook_args );
		}

		return $parse_token_values;
	}
}
