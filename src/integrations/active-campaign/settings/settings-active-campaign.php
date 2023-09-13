<?php
/**
 * Active_Campaign settings page
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Ajay Verma.
 */

namespace Uncanny_Automator;

/**
 * Active_Campaign Settings
 */
class Active_Campaign_Settings extends Settings\Premium_Integration_Settings {

	public $helpers;
	protected $account_url;
	protected $api_key;
	protected $is_connected;
	protected $users;
	protected $enable_triggers;
	protected $disconnect_url;
	protected $button_labels;

	/**
	 *
	 * @var string $kb_link
	 */
	public $kb_link = '';

	/**
	 * The webhook URL.
	 *
	 * @var string $webhook_url
	 */
	public $webhook_url = '';

	/**
	 * The regenerate key url.
	 *
	 * @var string $regenerate_key_url
	 */
	public $regenerate_key_url = '';

	/**
	 * The regenerate alert.
	 *
	 * @var string $regenerate_alert
	 */
	public $regenerate_alert = '';

	public function set_properties() {

		$this->set_id( 'active-campaign' );

		$this->set_icon( 'ACTIVE_CAMPAIGN' );

		$this->set_name( 'ActiveCampaign' );

		$this->register_option( 'uap_active_campaign_api_url' );

		$this->register_option( 'uap_active_campaign_api_key' );

		$this->register_option( 'uap_active_campaign_settings_timestamp' );

		$this->register_option( 'uap_active_campaign_enable_webhook' );

	}

	/**
	 * Sets up the properties of the settings page
	 */
	public function get_status() {
		return $this->helpers->integration_status();
	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		// Localize button labels.
		add_action( 'admin_enqueue_scripts', array( $this, 'localize_button_labels' ), 20 );

		$this->load_js( '/active-campaign/settings/assets/script.js' );

		$this->is_connected = 'success' === $this->helpers->integration_status();

		$this->users = get_option( 'uap_active_campaign_connected_user', array() );

		$this->account_url = get_option( 'uap_active_campaign_api_url', '' );

		$this->api_key = get_option( 'uap_active_campaign_api_key', '' );

		$this->enable_triggers = $this->helpers->is_webhook_enabled() ? 'checked' : '';

		$this->kb_link = automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/activecampaign-triggers/', 'settings', 'active-campaign-triggers-kb_article' );

		$this->webhook_url = get_rest_url() . AUTOMATOR_REST_API_END_POINT . $this->helpers->get_webhook_url();

		$this->button_labels = $this->helpers->get_sync_btn_label();

		$this->regenerate_key_url = add_query_arg(
			array(
				'action' => 'active-campaign-regenerate-webhook-key',
			),
			admin_url( 'admin-ajax.php' )
		);

		$this->regenerate_alert = __( 'Regenerating the URL will prevent ActiveCampaign triggers from working until the new webhook URL is set in ActiveCampaign. Continue?', 'uncanny-automator' );

		$this->disconnect_url = add_query_arg(
			array(
				'action' => 'active-campaign-disconnect',
				'nonce'  => wp_create_nonce( 'active-campaign-disconnect' ),
			),
			admin_url( 'admin-ajax.php' )
		);

		include_once 'view-active-campaign.php';

	}

	public function localize_button_labels() {

		wp_localize_script( 'uap-premium-integration-active-campaign', 'AutomatorActiveCampaignSettingsL10n', $this->helpers->get_sync_btn_label() );

	}


}

