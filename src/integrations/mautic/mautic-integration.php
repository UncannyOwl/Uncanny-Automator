<?php

namespace Uncanny_Automator\Integrations\Mautic;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Bootstraps the Mautic app integration, wiring up the helpers, API caller,
 * settings page, action classes, and AJAX endpoints.
 *
 * @package Uncanny_Automator\Integrations\Mautic
 */
class Mautic_Integration extends App_Integration {

	/**
	 * Return the static configuration shared between the integration, helpers, and settings.
	 *
	 * @return array{integration: string, name: string, api_endpoint: string, settings_id: string}
	 */
	public static function get_config() {
		return array(
			'integration'  => 'MAUTIC',
			'name'         => 'Mautic',
			'api_endpoint' => 'v2/mautic',
			'settings_id'  => 'mautic',
		);
	}

	/**
	 * Initialize the helpers, set the icon, and finalize the app integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		// Define helpers with common config values.
		$this->helpers = new Mautic_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/mautic-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Determine whether the Mautic account is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		return ! empty( $this->helpers->get_account_info() );
	}

	/**
	 * Register AJAX hooks for the recipe editor dropdowns.
	 *
	 * @return void
	 */
	protected function register_hooks() {
		add_action( 'wp_ajax_automator_mautic_segment_fetch', array( $this->helpers, 'segments_fetch' ) );
		add_action( 'wp_ajax_automator_mautic_tags_fetch', array( $this->helpers, 'tags_fetch' ) );
		add_action( 'wp_ajax_automator_mautic_render_contact_fields', array( $this->helpers, 'render_contact_fields' ) );
	}

	/**
	 * Instantiate the settings page and all action classes.
	 *
	 * @return void
	 */
	public function load() {

		// Load settings page.
		new Mautic_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load actions.
		new CONTACT_UPSERT( $this->dependencies );
		new SEGMENT_CREATE( $this->dependencies );
		new SEGMENT_CONTACT_ADD( $this->dependencies );
		new SEGMENT_CONTACT_REMOVE( $this->dependencies );
		new TAG_CREATE( $this->dependencies );
		new TAG_CONTACT_ADD( $this->dependencies );
		new TAG_CONTACT_REMOVE( $this->dependencies );
	}
}
