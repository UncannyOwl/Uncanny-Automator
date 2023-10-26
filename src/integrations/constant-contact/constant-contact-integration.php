<?php

namespace Uncanny_Automator\Integrations\Constant_Contact;

/**
 * Class Constant_Contact_Integration
 *
 * @package Uncanny_Automator
 */
class Constant_Contact_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Constant_Contact_Helpers();

		$this->register_hooks();

		$this->set_integration( 'CONSTANT_CONTACT' );
		$this->set_name( 'Constant Contact' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/constant-contact-icon.svg' );
		$this->set_connected( $this->helpers->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'constant-contact' ) );

	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Fires the settings class.
		new Constant_Contact_Settings( $this->helpers );
		// Fires the upsert action.
		new CREATE( $this->helpers );
		// Contact list add to.
		new CONTACT_LIST_ADD_TO( $this->helpers );
		// Contact tag add to.
		new CONTACT_TAG_ADD_TO( $this->helpers );
		// Delete a contact.
		new CONTACT_DELETE( $this->helpers );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_automator_constant_contact_handle_disconnect', array( $this->helpers, 'handle_disconnect' ) );
		add_action( 'wp_ajax_automator_constant_contact_handle_credentials', array( $this->helpers, 'handle_credentials_callback' ) );
		add_action( 'wp_ajax_automator_constant_contact_list_memberships_get', array( $this->helpers, 'list_memberships_get' ) );
		add_action( 'wp_ajax_automator_constant_contact_tags_get', array( $this->helpers, 'tag_list' ) );
		add_action( 'wp_ajax_automator_constant_contact_contact_fields_get', array( $this->helpers, 'contact_contact_fields_get' ) );
		add_action( 'automator_constant_contact_settings_before', array( $this->helpers, 'fetch_user_info' ) );
	}

}
