<?php

namespace Uncanny_Automator\Integrations\Thrive_Leads;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class TL_FORM_SUBMITTED
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Thrive_Leads\Thrive_Leads_Helpers get_item_helpers()
 */
class TL_FORM_SUBMITTED extends Trigger {

	const TRIGGER_CODE = 'TL_USER_SUBMIT_FORM';
	const TRIGGER_META = 'TL_FORMS';

	/**
	 * Set up Automator trigger constructor.
	 */
	public function setup_trigger() {
		$this->set_integration( 'THRIVELEADS' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_is_login_required( true );

		/* Translators: Trigger sentence - Thrive leads */
		$this->set_sentence( sprintf( 
			// translators: %1$s: Form name
			esc_html_x( 'A user submits {{a form:%1$s}}', 'Thrive Leads', 'uncanny-automator' ), $this->get_trigger_meta() ) );

		/* Translators: Trigger sentence - Thrive leads */
		$this->set_readable_sentence( esc_html_x( 'A user submits {{a form}}', 'Thrive Leads', 'uncanny-automator' ) ); // Non-active state sentence to show

		$this->add_action( 'tcb_api_form_submit', 20, 1 );
	}

	/**
	 * Loads available options for the Trigger.
	 *
	 * @return array The available trigger options.
	 */
	public function options() {
		$helper = new Thrive_Leads_Helpers( false );
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'required'        => true,
				'label'           => esc_html_x( 'Form', 'Thrive Leads', 'uncanny-automator' ),
				'input_type'      => 'select',
				'options'         => $helper->get_all_thrive_lead_forms( true, true ),
				'relevant_tokens' => array(
					$this->get_trigger_meta() => esc_html_x( 'Form', 'Thrive Leads', 'uncanny-automator' ),
				),
			),
		);
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$submit_data = $hook_args[0] ?? array();
		
		$form_id          = isset( $submit_data['thrive_leads']['tl_data']['form_type_id'] ) ? $submit_data['thrive_leads']['tl_data']['form_type_id'] : '';
		$selected_form_id = intval( $trigger['meta'][ $this->get_trigger_meta() ] );

		return intval( '-1' ) === $selected_form_id || $selected_form_id === intval( $form_id );
	}


	/**
	 * Hydrate tokens with form data.
	 *
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$submit_data = $hook_args[0] ?? array();

		$form_id    = isset( $submit_data['thrive_leads']['tl_data']['form_type_id'] ) ? $submit_data['thrive_leads']['tl_data']['form_type_id'] : '';
		$form_name  = isset( $submit_data['thrive_leads']['tl_data']['form_name'] ) ? $submit_data['thrive_leads']['tl_data']['form_name'] : '';
		$group_id   = isset( $submit_data['thrive_leads']['tl_data']['main_group_id'] ) ? $submit_data['thrive_leads']['tl_data']['main_group_id'] : '';
		$group_name = isset( $submit_data['thrive_leads']['tl_data']['main_group_name'] ) ? $submit_data['thrive_leads']['tl_data']['main_group_name'] : '';

		$field_tokens = $this->get_item_helpers()->get_form_fields_by_form_id( $form_id );
		$parse_field_tokens = array();
		foreach ( $field_tokens as $id => $input ) {
			$parse_field_tokens['FORM_FIELD|' . $id] = $submit_data[$id];
		}

		$parse_common_tokens = array(
			'FORM_ID' => $form_id,
			'FORM_NAME' => $form_name,
			'GROUP_ID' => $group_id,
			'GROUP_NAME' => $group_name,
			'TL_FORMS' => $form_name,
		);
		return array_merge( $parse_common_tokens, $parse_field_tokens );
	}

	/**
	 * Define tokens for this trigger.
	 *
	 * @param $trigger
	 * @param $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$form_id = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? $trigger['meta'][ $this->get_trigger_meta() ] : '';
		return array_merge( $tokens, $this->get_item_helpers()->get_common_tokens(), $this->get_item_helpers()->get_form_field_tokens( $form_id ) );
	}
}
