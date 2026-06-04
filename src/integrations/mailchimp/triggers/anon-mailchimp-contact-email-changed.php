<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Class ANON_MAILCHIMP_CONTACT_EMAIL_CHANGED
 *
 * @package Uncanny_Automator
 * @property Mailchimp_App_Helpers $helpers
 * @property Mailchimp_Api_Caller $api
 * @property Mailchimp_Webhooks $webhooks
 */
class ANON_MAILCHIMP_CONTACT_EMAIL_CHANGED extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'MAILCHIMP' );
		$this->set_trigger_code( 'ANON_MAILCHIMP_CONTACT_EMAIL_CHANGED' );
		$this->set_trigger_meta( 'ANON_MAILCHIMP_CONTACT_EMAIL_CHANGED_META' );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );
		$this->set_is_pro( false );
		$this->set_uses_api( true );
		$this->add_action( 'automator_mailchimp_webhook_received_upemail' );
		$this->set_readable_sentence( esc_html_x( 'A contact email is changed', 'Mailchimp', 'uncanny-automator' ) );
		$this->set_sentence( esc_html_x( 'A contact email is changed', 'Mailchimp', 'uncanny-automator' ) );
	}

	/**
	 * Check if the trigger requirements are met.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return $this->webhooks->get_webhooks_enabled_status();
	}
}
