<?php

namespace Uncanny_Automator;

use Uncanny_Automator\App\Uncanny_Agent\Application\Check_Uncanny_Agent_Settings_Access;
use Uncanny_Automator\App\Uncanny_Agent\Infrastructure\Automator_License_Facts_Adapter;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * Class Admin_Settings
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Agustin B.
 */
class Admin_Settings {

	/**
	 * Uncanny Agent settings access use case.
	 *
	 * @var Check_Uncanny_Agent_Settings_Access
	 */
	private $check_uncanny_agent_settings_access;

	/**
	 * Class constructor
	 */
	public function __construct() {

		$this->check_uncanny_agent_settings_access = new Check_Uncanny_Agent_Settings_Access(
			new Automator_License_Facts_Adapter( automator_license_manager() )
		);

		add_action( 'admin_menu', array( $this, 'submenu_page' ) );

		// Load the save handlers before admin_init. The settings page output
		// occurs after WordPress builds the admin menu.
		include_once __DIR__ . DIRECTORY_SEPARATOR . 'tabs/uncanny-page-builder-tabs/general.php';
		include_once __DIR__ . DIRECTORY_SEPARATOR . 'tabs/uncanny-agent-tabs/general.php';
	}

	/**
	 * Adds the "Settings" submenu page
	 */
	public function submenu_page() {

		// Add submenu
		add_submenu_page(
			'edit.php?post_type=uo-recipe',
			/* translators: 1. Trademarked term */
			sprintf( esc_attr__( '%1$s settings', 'uncanny-automator' ), 'Uncanny Automator' ),
			esc_attr__( 'Settings', 'uncanny-automator' ),
			automator_get_admin_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined
			'uncanny-automator-config',
			array( $this, 'submenu_page_output' )
		);
	}

	/**
	 * Load the tabs classes
	 */
	private function load_tabs() {
		// Load the files
		$this->load_tab( 'general' );
		$this->load_tab( 'premium-integrations' );

		// Uncanny Agent requires a connected Free or Pro license.
		if ( $this->can_access_uncanny_agent_settings() ) {
			$this->load_tab( 'uncanny-agent' );
		}

		$this->load_tab( 'uncanny-page-builder' );
		$this->load_tab( 'advanced' );
		$this->load_tab( 'addons' );
	}

	/**
	 * Determine if the Uncanny Agent settings tab is available.
	 *
	 * @return bool
	 */
	private function can_access_uncanny_agent_settings() {
		return $this->check_uncanny_agent_settings_access->execute()->is_allowed();
	}

	/**
	 * Loads the PHP file with the class that defines a tab
	 *
	 * @param  string $tab_key The tab ID
	 */
	private function load_tab( $tab_key ) {
		include __DIR__ . DIRECTORY_SEPARATOR . 'tabs/' . $tab_key . '.php';
	}

	/**
	 * Creates the output of the "Settings" page
	 */
	public function submenu_page_output() {

		// Load tabs
		$this->load_tabs();

		// Get the tabs
		$tabs = $this->get_top_level_tabs();

		// Get the current tab
		$current_tab = automator_filter_has_var( 'tab' ) ? sanitize_text_field( automator_filter_input( 'tab' ) ) : 'general';

		// Check if the user is requesting the focus version
		$layout_version = automator_filter_has_var( 'automator_hide_settings_tabs' ) ? 'focus' : 'default';

		// Add the actions and get the selected tab
		foreach ( $tabs as $tab_key => $tab ) {
			// Check if the function is defined
			if ( isset( $tab->function ) ) {
				// Add action
				add_action( 'automator_settings_' . $tab_key . '_tab', $tab->function );
			}

			// Check if this is the selected tab
			$tab->is_selected = $tab_key === $current_tab;
		}

		// Load the view
		include Utilities::automator_get_view( 'admin-settings/admin-settings.php' );
	}

	/**
	 * Returns the top level tabs
	 */
	public function get_top_level_tabs() {
		return apply_filters( 'automator_settings_sections', array() );
	}

	/**
	 * Returns the link of the settings page
	 *
	 * @param  string $selected_tab Optional. The ID of the selected tab
	 * @return string               The URL
	 */
	public static function utility_get_settings_page_link( $selected_tab = '' ) {
		// Define the list of URL parameters
		$url_parameters = array(
			'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
			'page'      => 'uncanny-automator-config',
		);

		// Check if there is a selected tab defined
		if ( ! empty( $selected_tab ) ) {
			$url_parameters['tab'] = $selected_tab;
		}

		// Return the URL
		return add_query_arg(
			$url_parameters,
			admin_url( 'edit.php' )
		);
	}
}
