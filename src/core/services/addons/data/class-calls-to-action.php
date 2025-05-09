<?php

namespace Uncanny_Automator\Services\Addons\Data;

/**
 * Calls_To_Action
 *
 * @package Uncanny_Automator\Services\Addons
 */
class Calls_To_Action {

	/**
	 * Get the pro plugin CTA.
	 *
	 * @param string $type The type of CTA.
	 * @link https://automatorplugin.com/pricing/
	 *
	 * @return array
	 */
	public static function get_pro_plugin( $type = 'success' ) {
		return array(
			'label' => esc_html_x( 'Get Automator Pro', 'Addons', 'uncanny-automator' ),
			'icon'  => 'gem',
			'type'  => $type,
			'url'   => automator_utm_parameters(
				AUTOMATOR_STORE_URL . 'pricing/',
				'upgrade_to_pro',
				'upgrade_to_pro_button'
			),
		);
	}

	/**
	 * Get the upgrade plan CTA.
	 *
	 * @param string $type The type of CTA.
	 * @link https://automatorplugin.com/pricing/
	 *
	 * @return array
	 */
	public static function get_upgrade_plan( $type = 'success' ) {
		return array(
			'label' => esc_html_x( 'Upgrade Pro plan', 'Addons', 'uncanny-automator' ),
			'icon'  => 'gem',
			'type'  => $type,
			'url'   => automator_utm_parameters(
				AUTOMATOR_STORE_URL . 'pricing/',
				'upgrade_to_pro',
				'upgrade_to_pro_button'
			),
		);
	}

	/**
	 * Get the activate pro CTA.
	 *
	 * @param string $type The type of CTA.
	 *
	 * @return array
	 */
	public static function get_activate_pro( $type = 'warning' ) {
		return array(
			'label'  => esc_html_x( 'Activate Automator Pro', 'Addons', 'uncanny-automator' ),
			'icon'   => 'plug-circle-bolt',
			'type'   => $type, // Secondary
			'action' => 'activate',
		);
	}

	/**
	 * Get the fix license CTA.
	 *
	 * @param string $type The type of CTA.
	 * @link [site_url]/wp-admin/edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=general&general=license
	 *
	 * @return array
	 */
	public static function get_fix_license( $type = 'error' ) {
		return array(
			'label' => esc_html_x( 'Fix your license', 'Addons', 'uncanny-automator' ),
			'icon'  => 'wrench',
			'type'  => $type, // Secondary?
			'url'   => add_query_arg(
				array(
					'post_type' => 'uo-recipe',
					'page'      => 'uncanny-automator-config',
					'tab'       => 'general',
					'general'   => 'license',
				),
				admin_url( 'edit.php' )
			),
		);
	}

	/**
	 * Get the learn more CTA.
	 *
	 * @param string $url - The URL to learn more.
	 * @param string $type - The type of CTA.
	 *
	 * @return array
	 */
	public static function get_learn_more( $url, $type = 'secondary' ) {
		return array(
			'label' => esc_html_x( 'Learn more', 'Addons', 'uncanny-automator' ),
			'type'  => $type,
			'url'   => esc_url( $url ),
		);
	}

	/**
	 * Get the install addon CTA.
	 *
	 * @param int $addon_id - The addon ID.
	 *
	 * @return array
	 */
	public static function get_install_addon( $addon_id, $type = 'primary' ) {

		if ( ! self::can_user_perform_plugin_actions() ) {
			return self::get_download_addon( $addon_id, $type );
		}

		return array(
			'label'  => esc_html_x( 'Install addon', 'Addons', 'uncanny-automator' ),
			'type'   => $type,
			'action' => 'install',
			'addon'  => $addon_id,
			'icon'   => 'download',
		);
	}

	/**
	 * Get the activate addon CTA.
	 *
	 * @param array $addon The addon.
	 *
	 * @return array
	 */
	public static function get_activate_addon( $addon_id, $type = 'primary' ) {
		return array(
			'label'  => esc_html_x( 'Activate', 'Addons', 'uncanny-automator' ),
			'type'   => $type,
			'action' => 'activate',
			'addon'  => $addon_id,
			'icon'   => 'bolt',
		);
	}

	/**
	 * Get the update addon CTA.
	 *
	 * @param int $addon_id - The addon ID.
	 *
	 * @return array
	 */
	public static function get_update_addon( $addon_id, $type = 'primary' ) {

		if ( ! self::can_user_perform_plugin_actions() ) {
			return self::get_download_addon( $addon_id, $type, 'update' );
		}

		return array(
			'label'  => esc_html_x( 'Update addon', 'Addons', 'uncanny-automator' ),
			'type'   => $type,
			'icon'   => 'repeat',
			'action' => 'update',
			'addon'  => $addon_id,
		);
	}

	/**
	 * Get the download addon CTA.
	 *
	 * @param int $addon_id - The addon ID.
	 * @param string $type - The type of CTA.
	 * @param string $action - The reason for the download.
	 * @return array
	 */
	public static function get_download_addon( $addon_id, $type = 'primary', $action = 'install' ) {

		$label = 'update' === $action
			? esc_html_x( 'Download update', 'Addons', 'uncanny-automator' )
			: esc_html_x( 'Download', 'Addons', 'uncanny-automator' );

		return array(
			'label'  => $label,
			'type'   => $type,
			'icon'   => 'download',
			'action' => 'download',
			'addon'  => $addon_id,
		);
	}

	/**
	 * Get the direct download addon CTA.
	 *
	 * @param string $url - The URL to download.
	 * @param string $type - The type of CTA.
	 *
	 * @return array
	 */
	public static function get_direct_download_addon( $url, $type = 'primary' ) {
		return array(
			'label'  => esc_html_x( 'Download', 'Addons', 'uncanny-automator' ),
			'type'   => $type,
			'icon'   => 'download',
			'url'    => $url,
		);
	}

	/**
	 * Get the redirect to plugins page CTA.
	 *
	 * @param string $label - The label of the CTA.
	 * @param string $type - The type of CTA.
	 * @param string $url - The URL to redirect to.
	 * @param string $name - The name of the plugin to search for.
	 * @return array
	 */
	public static function get_redirect_to_plugins_page( $label = '', $type = 'error', $url = '', $name = '' ) {

		if ( empty( $label ) ) {
			$label = esc_html_x( 'Activate', 'Addons', 'uncanny-automator' );
		}

		if ( empty( $url ) ) {
			$url = ! empty( $name ) 
				? \Uncanny_Automator\Services\Plugin\Info::get_plugin_search_url( $name ) 
				: admin_url( 'plugins.php' );
		}

		return array(
			'label' => esc_html( $label ),
			'type'  => esc_attr( $type ),
			'url'   => esc_url( $url ),
		);
	}

	/**
	 * Check if multisite or user can't install plugins.
	 *
	 * @return bool True if user can perform plugin actions, false otherwise.
	 */
	public static function can_user_perform_plugin_actions() {
		return ! is_multisite() && current_user_can( 'install_plugins' );
	}

	/**
	 * Get the settings page CTA.
	 *
	 * @param string $tab The tab.
	 * @param string $type The type of CTA.
	 *
	 * @return array
	 */
	public static function get_addon_settings_page( $tab, $type = 'secondary' ) {
		// Bail if no tab string is provided.
		if ( empty( $tab ) ) {
			return array();
		}

		return array(
			'label'    => esc_html_x( 'Settings', 'Addons', 'uncanny-automator' ),
			'type'     => $type,
			'url'      => \Uncanny_Automator\Admin_Settings_Addons::utility_get_addons_page_link( $tab ),
			'icon'     => 'cog',
			'disabled' => false,
		);
	}

	/**
	 * Get the error refresh CTA.
	 *
	 * @return array
	 */
	public static function get_error_refresh() {
		return array(
			'label'  => esc_html_x( 'Refresh', 'Addons', 'uncanny-automator' ),
			'type'   => 'error',
			'action' => 'refresh',
			'url'    => '',
		);
	}

	/**
	 * Get the error my account CTA.
	 *
	 * @return array
	 */
	public static function get_my_account_redirect( $type = 'error' ) {
		return array(
			'label'  => esc_html_x( 'My account', 'Addons', 'uncanny-automator' ),
			'type'   => $type,
			'url'    => AUTOMATOR_STORE_URL . 'my-account/downloads/',
		);
	}
}
