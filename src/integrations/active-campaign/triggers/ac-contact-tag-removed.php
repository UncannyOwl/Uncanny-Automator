<?php

namespace Uncanny_Automator\Integrations\Active_Campaign;

/**
 * Class AC_CONTACT_TAG_REMOVED
 *
 * @package Uncanny_Automator
 *
 * @property Active_Campaign_App_Helpers $helpers
 * @property Active_Campaign_Api_Caller $api
 * @property Active_Campaign_Webhooks $webhooks
 */
class AC_CONTACT_TAG_REMOVED extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Define and register the trigger by pushing it into the Automator.
	 *
	 * @return void
	 */
	public function setup_trigger() {
		$this->set_integration( 'ACTIVE_CAMPAIGN' );
		$this->set_trigger_code( 'CONTACT_TAG_REMOVED' );
		$this->set_trigger_meta( 'TAG' );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );
		$this->set_uses_api( true );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Tag name
				esc_html_x( '{{A tag:%1$s}} is removed from a contact', 'ActiveCampaign', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( '{{A tag}} is removed from a contact', 'ActiveCampaign', 'uncanny-automator' ) );
		$this->add_action( 'automator_active_campaign_webhook_received' ); // which do_action() fires this trigger
	}

	/**
	 * Check if the trigger requirements are met.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return $this->webhooks->get_webhooks_enabled_status();
	}

	/**
	 * Define the options for the trigger.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_tag_select_config( $this->get_trigger_meta() ),
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! is_array( $hook_args ) || empty( $hook_args ) ) {
			return false;
		}

		$ac_event = $hook_args[0];

		if ( ! is_array( $ac_event ) || ! isset( $ac_event['type'] ) || ! isset( $ac_event['tag'] ) ) {
			return false;
		}

		if ( 'contact_tag_removed' !== $ac_event['type'] ) {
			return false;
		}

		$selected_id   = $trigger['meta'][ $this->get_trigger_meta() ] ?? 0;
		$selected_name = $trigger['meta'][ $this->get_trigger_meta() . '_readable' ] ?? '';
		$tag_name      = $ac_event['tag'];

		return ( -1 === intval( $selected_id ) || strval( $selected_name ) === strval( $tag_name ) );
	}

	/**
	 * Define tokens for the trigger.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return AC_TOKENS::define_contact_tag_tokens();
	}

	/**
	 * Hydrate tokens for the trigger.
	 *
	 * @param array $trigger The trigger configuration.
	 * @param array $hook_args The hook arguments from the webhook.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return AC_TOKENS::hydrate_contact_tag_tokens( $hook_args );
	}
}
