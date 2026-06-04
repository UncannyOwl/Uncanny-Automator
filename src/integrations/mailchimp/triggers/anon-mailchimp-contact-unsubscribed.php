<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Class ANON_MAILCHIMP_CONTACT_UNSUBSCRIBED
 *
 * @package Uncanny_Automator
 * @property Mailchimp_App_Helpers $helpers
 * @property Mailchimp_Api_Caller $api
 * @property Mailchimp_Webhooks $webhooks
 */
class ANON_MAILCHIMP_CONTACT_UNSUBSCRIBED extends \Uncanny_Automator\Recipe\App_Trigger {

	use Mailchimp_Trigger_Audience;

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'MAILCHIMP' );
		$this->set_trigger_code( 'ANON_MAILCHIMP_CONTACT_UNSUBSCRIBED' );
		$this->set_trigger_meta( 'ANON_MAILCHIMP_CONTACT_UNSUBSCRIBED_META' );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );
		$this->set_is_pro( false );
		$this->set_uses_api( true );
		$this->add_action( 'automator_mailchimp_webhook_received_unsubscribe' );
		$this->set_readable_sentence( esc_html_x( 'A contact is unsubscribed from {{an audience}}', 'Mailchimp', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the trigger meta
				esc_html_x( 'A contact is unsubscribed from {{an audience:%1$s}}', 'Mailchimp', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
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
