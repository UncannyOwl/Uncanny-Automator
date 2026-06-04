<?php

namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Trait Mailchimp_Trigger_Audience
 *
 * Provides common audience selection options and validation for Mailchimp triggers.
 *
 * @package Uncanny_Automator\Integrations\Mailchimp
 */
trait Mailchimp_Trigger_Audience {

	/**
	 * Get audience selector options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Audience', 'Mailchimp', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'relevant_tokens' => array(),
				'options'         => array(),
				'remote_data'     => $this->helpers->remote_data_load_config( 'trigger_audiences' ),
			),
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * Returns true if audience is set to 'Any' (-1) or if audience id matches the webhook data.
	 *
	 * @param array $trigger   The trigger data.
	 * @param array $hook_args The hook arguments from the webhook.
	 *
	 * @return bool True if trigger should fire, false otherwise.
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! is_array( $hook_args ) || empty( $hook_args ) ) {
			return false;
		}

		$event = $hook_args[0];

		if ( ! is_array( $event ) || empty( $event['data']['list_id'] ) ) {
			return false;
		}

		$selected_audience = $trigger['meta'][ $this->get_trigger_meta() ] ?? 0;
		$webhook_list_id   = $event['data']['list_id'];

		// -1 means "Any audience" was selected.
		return ( -1 === intval( $selected_audience ) || $selected_audience === $webhook_list_id );
	}
}
