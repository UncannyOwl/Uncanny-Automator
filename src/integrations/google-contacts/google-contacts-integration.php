<?php

namespace Uncanny_Automator\Integrations\Google_Contacts;

use Uncanny_Automator\Integration;

/**
 * @package Uncanny_Automator\Integrations\Google_Contacts
 */
class Google_Contacts_Integration extends Integration {

	/**
	 * @var Google_Contacts_Helpers
	 */
	protected $helpers;

	/**
	 * @return void
	 */
	protected function setup() {

		// Overwrite the parent's helper property with our helper.
		$this->helpers = new Google_Contacts_Helpers();

		$this->load_hooks();

		$connected = false;

		if ( false !== $this->get_google_client() ) {
			$connected = true;
		}

		$this->set_integration( 'GOOGLE_CONTACTS' );
		$this->set_name( 'Google Contacts' );
		$this->set_connected( $connected );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/google-contacts-icon.svg' );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'google-contacts' ) );
	}

	/**
	 * @return void
	 */
	protected function load_hooks() {

		// Contact fields.
		add_action(
			'wp_ajax_automator_google_contacts_render_contact_fields',
			array(
				$this->helpers,
				'render_contact_fields',
			)
		);

		// Process code callback.
		add_action(
			'wp_ajax_automator_google_contacts_process_code_callback',
			array(
				$this->helpers,
				'automator_google_contacts_process_code_callback',
			)
		);

		// Disconnect.
		add_action( 'wp_ajax_automator_google_contacts_disconnect', array( $this->helpers, 'disconnect' ) );

		// Disconnect.
		add_action( 'wp_ajax_automator_google_contacts_fetch_labels', array( $this->helpers, 'fetch_labels' ) );

	}

	/**
	 * @return void
	 */
	public function load() {

		// Helpers.
		new Google_Contacts_Settings( $this->helpers );

		// Actions.
		new CREATE( $this->helpers );

		// Contact group add.
		new CONTACT_GROUP_ADD_TO( $this->helpers );

		// Contact create.
		new CONTACT_GROUP_CREATE( $this->helpers );

	}

	/**
	 * @return false|mixed
	 */
	public function get_google_client() {

		$access_token = automator_get_option( 'automator_google_contacts_credentials', array() );

		if ( empty( $access_token ) || ! isset( $access_token['access_token'] ) ) {
			return false;
		}

		return $access_token;
	}
}
