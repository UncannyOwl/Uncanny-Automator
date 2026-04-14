<?php

namespace Uncanny_Automator\Integrations\ConvertKit;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

/**
 * Class ConvertKit_Integration
 *
 * @package Uncanny_Automator
 */
class ConvertKit_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'CONVERTKIT',
			'name'         => 'Kit',
			'api_endpoint' => 'v2/convertkit',
			'settings_id'  => 'convertkit',
		);
	}

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new ConvertKit_App_Helpers( self::get_config() );

		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/kit-icon.svg' );

		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		new ConvertKit_Vault_Migration(
			'convertkit_vault_migration',
			$this->dependencies->api
		);

		new ConvertKit_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load actions.
		new CONVERTKIT_FORM_SUBSCRIBER_ADD( $this->dependencies );
		new CONVERTKIT_SUBSCRIBER_SEQUENCE_ADD( $this->dependencies );
		new CONVERTKIT_SUBSCRIBER_TAG_ADD( $this->dependencies );
		new CONVERTKIT_SUBSCRIBER_TAG_REMOVE( $this->dependencies );

		// V4 only actions ( restricted by requirements_met checks ).
		new CONVERTKIT_SUBSCRIBER_UNSUBSCRIBE( $this->dependencies );
		new CONVERTKIT_SUBSCRIBER_CREATE_UPDATE( $this->dependencies );
		new CONVERTKIT_TAG_CREATE( $this->dependencies );
		new CONVERTKIT_CUSTOM_FIELD_CREATE( $this->dependencies );
		new CONVERTKIT_BROADCAST_CREATE( $this->dependencies );
		new CONVERTKIT_PURCHASE_CREATE( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$this->helpers->get_credentials();
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Register additional hooks for the integration.
	 *
	 * @return void
	 */
	protected function register_hooks() {
		add_action( 'wp_ajax_automator_convertkit_forms_dropdown_handler', array( $this->helpers, 'get_form_options_ajax' ) );
		add_action( 'wp_ajax_automator_convertkit_sequence_dropdown_handler', array( $this->helpers, 'get_sequence_options_ajax' ) );
		add_action( 'wp_ajax_automator_convertkit_tags_dropdown_handler', array( $this->helpers, 'get_tag_options_ajax' ) );
		add_action( 'wp_ajax_automator_convertkit_custom_fields_handler', array( $this->helpers, 'get_custom_field_rows_ajax' ) );
	}
}
