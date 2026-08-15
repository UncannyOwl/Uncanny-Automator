<?php

namespace Uncanny_Automator;

use Uncanny_Automator\App\Feature_State\Application\Get_Feature_State;
use Uncanny_Automator\App\Feature_State\Domain\Feature_State;
use Uncanny_Automator\App\Uncanny_Agent\Application\Check_Uncanny_Agent_Settings_Access;

use function Uncanny_Automator\App\Infrastructure\automator_feature_state_query;

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
	 * @var Check_Uncanny_Agent_Settings_Access|null
	 */
	private $check_uncanny_agent_settings_access;

	/**
	 * Request-scoped feature-state query.
	 *
	 * @var Get_Feature_State|null
	 */
	private $feature_state;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->feature_state                       = null;
		$this->check_uncanny_agent_settings_access = null;

		try {
			$this->feature_state = automator_feature_state_query();

			if ( $this->feature_state instanceof Get_Feature_State ) {
				$this->check_uncanny_agent_settings_access = new Check_Uncanny_Agent_Settings_Access( $this->feature_state );
			}
		} catch ( \Throwable $error ) {
			unset( $error );
		}

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

		if ( $this->can_access_uncanny_agent_settings() ) {
			$this->load_tab( 'uncanny-agent' );
		}

		if ( $this->can_access_uncanny_page_builder_settings() ) {
			$this->load_tab( 'uncanny-page-builder' );
		}
		$this->load_tab( 'advanced' );
		$this->load_tab( 'addons' );
	}

	/**
	 * Determine if the Uncanny Agent settings tab is available.
	 *
	 * @return bool
	 */
	private function can_access_uncanny_agent_settings() {
		return $this->check_uncanny_agent_settings_access instanceof Check_Uncanny_Agent_Settings_Access
			&& $this->check_uncanny_agent_settings_access->execute();
	}

	/**
	 * Determine if the Uncanny Page Builder settings tab is available.
	 *
	 * @return bool
	 */
	private function can_access_uncanny_page_builder_settings() {
		return $this->feature_state instanceof Get_Feature_State
			&& $this->feature_state->execute()->is_visible( Feature_State::PAGE_BUILDER_SETTINGS_TAB );
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
		$tabs = is_array( $tabs ) ? $tabs : array();

		// Get the current tab
		$current_tab = automator_filter_has_var( 'tab' ) ? sanitize_text_field( automator_filter_input( 'tab' ) ) : 'general';

		$current_tab = $this->normalize_current_tab( $current_tab, $tabs );

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
	 * Fall back from an unavailable settings tab to General.
	 *
	 * @param string               $current_tab Requested tab key.
	 * @param array<string,object> $tabs        Registered tabs.
	 *
	 * @return string
	 */
	private function normalize_current_tab( $current_tab, array $tabs ) {
		return array_key_exists( $current_tab, $tabs ) ? $current_tab : 'general';
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
