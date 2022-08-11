<?php

namespace Uncanny_Automator;

/**
 * Class Admin_Settings_Advanced_Automator_Cache
 *
 * @since   4.2
 * @version 4.2
 * @package Uncanny_Automator
 * @author  Ajay Verma.
 */
class Admin_Settings_Advanced_Automator_Cache {

	/**
	 *
	 */
	const SETTINGSGROUP = 'uncanny_automator_advanced_automator_cache';

	/**
	 * Class constructor
	 */
	public function __construct() {

		add_filter( 'automator_settings_advanced_tabs', array( $this, 'create_tab' ), 99, 1 );
	}

	/**
	 * Adds the tab using the automator_settings_tab filter
	 */
	public function create_tab( $tabs ) {

		// Background actions.
		$tabs['automator_cache'] = (object) array(
			'name'     => esc_html__( 'Automator cache', 'uncanny-automator' ),
			'function' => array( $this, 'tab_output' ),
			'preload'  => false, // Determines if the content should be loaded even if the tab is not selected
		);

		return $tabs;

	}

	/**
	 * Outputs the content of the "General" tab
	 */
	public function tab_output() {
		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/advanced/automator-cache.php' );
	}

	/**
	 * @return string
	 */
	public function get_advanced_settings_url() {
		return add_query_arg(
			array(
				'post_type' => 'uo-recipe',
				'page'      => 'uncanny-automator-cache',
				'tab'       => 'advanced',
				'advanced'  => 'automator_cache',
			),
			admin_url( 'edit.php' )
		);
	}

}

new Admin_Settings_Advanced_Automator_Cache();
