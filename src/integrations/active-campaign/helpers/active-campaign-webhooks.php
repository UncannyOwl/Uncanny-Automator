<?php
namespace Uncanny_Automator\Integrations\Active_Campaign;

use Uncanny_Automator\App_Integrations\App_Webhooks;

class Active_Campaign_Webhooks extends App_Webhooks {

	/**
	 * Set the properties for the webhooks.
	 * - Override defaults for legacy compatibility.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Override webhook endpoint for legacy compatibility.
		$this->set_webhook_endpoint(
			apply_filters(
				'automator_active_campaign_webhook_endpoint',
				'active-campaign',
				$this->helpers
			)
		);

		// Override webhooks enabled option name for legacy compatibility.
		$this->set_webhooks_enabled_option_name( $this->helpers->get_const( 'ENABLE_WEBHOOK_OPTION' ) );

		// Override webhook key option name for legacy compatibility.
		$this->set_webhook_key_option_name( 'uap_active_campaign_webhook_key' );
	}
}
