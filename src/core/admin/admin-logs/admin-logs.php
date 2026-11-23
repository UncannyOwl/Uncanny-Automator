<?php
/**
 * Uncanny_Automator\Admin_Logs
 *
 * @since 4.5
 */
namespace Uncanny_Automator;

class Admin_Logs {

	/**
	 * Asset.
	 *
	 * @var mixed
	 */
	protected $asset;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {

		// Register the options menu
		$this->submenu_page();
	}
	/**
	 * Set asset.
	 *
	 * @param mixed $asset The asset.
	 */
	public function set_asset( $asset ) {

		$this->asset = $asset;
	}

	/**
	 * Adds the "Settings" submenu page
	 */
	private function submenu_page() {

		add_action(
			'admin_menu',
			function () {
				add_submenu_page(
					'edit.php?post_type=uo-recipe',
					esc_attr__( 'Logs', 'uncanny-automator' ),
					esc_attr__( 'Logs', 'uncanny-automator' ),
					automator_get_capability(),
					'uncanny-automator-admin-logs',
					array( $this, 'submenu_page_output' ),
					700
				);
			}
		);
	}

	/**
	 * Creates the output of the Logs page: the Logs viewer and the run dialog.
	 *
	 * @since 8.1.0 Renders the Logs viewer in place of the list tables.
	 *
	 * @return void
	 */
	public function submenu_page_output() {

		// A link to one of the old Logs tabs (?tab=trigger) opens the viewer's matching tab.
		$legacy_tabs   = array(
			'recipe'      => 'recipe',
			'trigger'     => 'trigger',
			'action'      => 'action',
			'ai-activity' => 'ai-activity',
		);
		$requested_tab = automator_filter_has_var( 'tab' ) ? sanitize_text_field( automator_filter_input( 'tab' ) ) : '';

		// A link to one run leaves the hash to the viewer, which opens the run only while the address has none, over the tab ?tab= names.
		$names_a_run     = automator_filter_has_var( 'log_dialog_id' ) && automator_filter_has_var( 'log_recipe_id' ) && automator_filter_has_var( 'log_run_number' );
		$legacy_tab_hash = $names_a_run ? '' : ( $legacy_tabs[ $requested_tab ] ?? '' );

		// The AI activity tab shows only to a user who can manage AI, as before.
		$show_ai_activity = Admin_Ai_Settings::current_user_can_manage_ai();

		// The App events tab shows only to an administrator of Automator, since an app event carries what the app sent,
		// and only on a site that has app events, or an app connected that sends them.
		$show_app_events = current_user_can( automator_get_admin_capability() ) // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- The admin capability is filterable.
			&& ( new \Uncanny_Automator\App\Recipe_Logs\App_Events\Services\App_Events_Availability() )->has_app_events();

		// Load the view.
		include Utilities::automator_get_view( 'admin-logs/admin-logs.php' );
	}

	/**
	 * Returns the top level tabs
	 */
	public function get_top_level_tabs() {

		return apply_filters( 'automator_admin_logs_top_level_tabs_items', array() );
	}

	/**
	 * Returns the link of the settings page.
	 *
	 * @param  string $selected_tab Optional. The ID of the selected tab.
	 *
	 * @return string The URL.
	 */
	public static function utility_get_settings_page_link( $selected_tab = '' ) {

		// Define the list of URL parameters.
		$url_parameters = array(
			'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
			'page'      => 'uncanny-automator-admin-logs',
		);

		// Check if there is a selected tab defined.
		if ( ! empty( $selected_tab ) ) {
			$url_parameters['tab'] = $selected_tab;
		}

		// Return the URL.
		return add_query_arg(
			$url_parameters,
			admin_url( 'edit.php' )
		);
	}
	/**
	 * Get current tab.
	 *
	 * @return mixed
	 */
	public function get_current_tab() {

		if ( automator_filter_has_var( 'tab' ) ) {
			return automator_filter_input( 'tab' );
		}

		return 'recipe';
	}
}
