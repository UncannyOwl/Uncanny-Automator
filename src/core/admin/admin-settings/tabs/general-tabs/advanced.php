<?php

namespace Uncanny_Automator;

/**
 * Class Admin_Settings_General_Advanced
 *
 * @since   4.2
 * @version 4.2
 * @package Uncanny_Automator
 * @author  Ajay Verma.
 */
class Admin_Settings_General_Advanced {

	const SETTINGSGROUP = 'uncanny_automator_advanced';

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_filter( 'automator_settings_general_tabs', array( $this, 'create_tab' ), 99, 1 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'update_option_' . self::SETTINGSGROUP . '_settings_timestamp', array( $this, 'settings_updated' ), 100, 3 );
		add_action( 'add_option_' . self::SETTINGSGROUP . '_settings_timestamp', array( $this, 'settings_updated' ), 100, 3 );
	}

	/**
	 * Adds the tab using the automator_settings_tab filter
	 */
	public function create_tab( $tabs ) {
		// General
		$tabs['advanced'] = (object) array(
			'name'     => esc_html__( 'Advanced', 'uncanny-automator' ),
			'function' => array( $this, 'tab_output' ),
			'preload'  => true, // Determines if the content should be loaded even if the tab is not selected
			'icon'     => 'code',
		);

		return $tabs;

	}

	public function register_settings() {
		register_setting( self::SETTINGSGROUP, self::SETTINGSGROUP . '_settings_timestamp' );
	}

	/**
	 * Outputs the content of the "General" tab
	 */
	public function tab_output() {

		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/general/advanced.php' );
	}

	public function settings_updated() {
		wp_safe_redirect(
			$this->get_advanced_settings_url()
		);
		exit;
	}

	public function get_advanced_settings_url() {
		return add_query_arg(
			array(
				'post_type' => 'uo-recipe',
				'page'      => 'uncanny-automator-config',
				'tab'       => 'general',
				'general'   => 'advanced',
			),
			admin_url( 'edit.php' )
		);
	}

}

new Admin_Settings_General_Advanced();
