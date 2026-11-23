<?php

namespace Uncanny_Automator\Integrations\Mainwp;

use MainWP\Dashboard\MainWP_Common_Functions;
use MainWP\Dashboard\MainWP_DB;
use MainWP\Dashboard\MainWP_DB_Client;
use MainWP\Dashboard\MainWP_DB_Common;
use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Mainwp_Helpers
 *
 * Child sites and clients live in MainWP's own tables, so every read goes
 * through MainWP's DB classes.
 *
 * @package Uncanny_Automator
 */
class Mainwp_Helpers extends Abstract_Helpers {

	/**
	 * Child sites for trigger pickers ("Any child site" first).
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_sites( $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return $this->remote_data_success( $this->get_site_options( true ) );
	}

	/**
	 * Child sites for condition pickers (no "Any").
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_sites_strict( $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return $this->remote_data_success( $this->get_site_options( false ) );
	}

	/**
	 * Child sites the dashboard user can access, labelled `Name (host)` so two
	 * sites sharing a name stay distinguishable.
	 *
	 * @param bool $include_any Prepend the "Any child site" option.
	 *
	 * @return array[]
	 */
	public function get_site_options( $include_any ) {

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any child site', 'MainWP', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		if ( ! class_exists( MainWP_DB::class ) ) {
			return $options;
		}

		foreach ( (array) MainWP_DB::instance()->get_websites_for_current_user() as $site ) {

			$site_id = $this->resolve_site_id( $site );

			if ( 0 === $site_id ) {
				continue;
			}

			$name = (string) ( $site->name ?? '' );
			$host = (string) wp_parse_url( (string) ( $site->url ?? '' ), PHP_URL_HOST );

			$options[] = array(
				'text'  => '' !== $host ? sprintf( '%1$s (%2$s)', $name, $host ) : $name,
				'value' => (string) $site_id,
			);
		}

		return $options;
	}

	/**
	 * The software types MainWP updates, with an optional "Any" option.
	 *
	 * @param bool $include_any Prepend the "Any" option.
	 *
	 * @return array[]
	 */
	public function get_software_type_options( $include_any ) {

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any plugin, theme or translation', 'MainWP', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		foreach ( $this->get_software_type_labels() as $value => $label ) {
			$options[] = array(
				'text'  => $label,
				'value' => $value,
			);
		}

		return $options;
	}

	/**
	 * Labels for the software types MainWP passes.
	 *
	 * @return array<string,string>
	 */
	public function get_software_type_labels() {
		return array(
			'plugin'      => esc_html_x( 'Plugin', 'MainWP', 'uncanny-automator' ),
			'theme'       => esc_html_x( 'Theme', 'MainWP', 'uncanny-automator' ),
			'translation' => esc_html_x( 'Translation', 'MainWP', 'uncanny-automator' ),
		);
	}

	/**
	 * Whether the MainWP Dashboard is active.
	 *
	 * `MAINWP_PLUGIN_DIR` is defined on every request. `MAINWP_VERSION` and the
	 * `mainwp_activated_check` filter carry a value only in wp-admin, so cron and
	 * REST events would see MainWP as inactive.
	 *
	 * @return bool
	 */
	public function dashboard_active() {
		return defined( 'MAINWP_PLUGIN_DIR' );
	}

	/**
	 * A child site's row, or null when it doesn't exist.
	 *
	 * @param mixed $site_id   The site ID.
	 * @param bool  $with_tags Add `wpgroups`, the site's tag names.
	 *
	 * @return object|null
	 */
	public function get_site( $site_id, $with_tags = false ) {

		$site_id = $this->resolve_site_id( $site_id );

		if ( 0 === $site_id || ! class_exists( MainWP_DB::class ) ) {
			return null;
		}

		$site = MainWP_DB::instance()->get_website_by_id( $site_id, (bool) $with_tags );

		return is_object( $site ) ? $site : null;
	}

	/**
	 * A client's row, or null when it doesn't exist.
	 *
	 * @param mixed $client_id The client ID.
	 *
	 * @return object|null
	 */
	public function get_client( $client_id ) {

		$client_id = $this->to_id( $client_id );

		if ( 0 === $client_id || ! class_exists( MainWP_DB_Client::class ) ) {
			return null;
		}

		$client = MainWP_DB_Client::instance()->get_wp_client_by( 'client_id', $client_id );

		return is_object( $client ) ? $client : null;
	}

	/**
	 * The site ID from whatever a hook passes: a row object, an array or an ID.
	 *
	 * @param mixed $site Site object, array or ID.
	 *
	 * @return int The ID, or 0.
	 */
	public function resolve_site_id( $site ) {

		if ( is_object( $site ) ) {
			return $this->to_id( $site->id ?? 0 );
		}

		if ( is_array( $site ) ) {
			return $this->to_id( $site['id'] ?? 0 );
		}

		return $this->to_id( $site );
	}

	/**
	 * Whether a site has available updates, counted the way the Updates column of
	 * MainWP's Manage Sites screen counts them: core, plugins and themes, premium
	 * included, ignored updates excluded, translations not counted.
	 *
	 * @param object|null $site A row from get_site().
	 *
	 * @return bool
	 */
	public function site_has_updates( $site ) {

		if ( ! is_object( $site ) || ! class_exists( MainWP_Common_Functions::class ) ) {
			return false;
		}

		// Pass the ID, never the row: given a row, MainWP returns the row's own
		// premium_upgrades column, which is always empty, instead of the site option.
		$site_id      = $this->resolve_site_id( $site );
		$options      = MainWP_DB::instance()->get_website_options_array( $site_id, array( 'wp_upgrades', 'ignored_wp_upgrades', 'premium_upgrades' ) );
		$global_lists = MainWP_DB_Common::instance()->get_user_extension();
		$global_lists = is_object( $global_lists ) ? $global_lists : null;
		$premium      = $this->decode_list( $options['premium_upgrades'] ?? '' );

		$count = $this->count_core_update( $site, $options, $global_lists )
			+ $this->count_updates( $site, 'plugin', $premium, $global_lists )
			+ $this->count_updates( $site, 'theme', $premium, $global_lists );

		return 0 < $count;
	}

	/**
	 * Whether a site is connected: MainWP's own test is an empty sync error.
	 *
	 * @param object|null $site A row from get_site().
	 *
	 * @return bool
	 */
	public function site_is_connected( $site ) {
		return is_object( $site ) && '' === (string) ( $site->sync_errors ?? '' );
	}

	/**
	 * 1 when the site has a pending core update that isn't ignored.
	 *
	 * @param object      $site    The site row.
	 * @param array       $options The site's options.
	 * @param object|null $global_lists The dashboard-wide ignore lists.
	 *
	 * @return int
	 */
	private function count_core_update( $site, array $options, $global_lists ) {

		$core = $this->decode_list( $options['wp_upgrades'] ?? '' );

		if ( array() === $core || ! empty( $site->is_ignoreCoreUpdates ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MainWP's column.
			return 0;
		}

		$ignore_lists = array( $options['ignored_wp_upgrades'] ?? '', $global_lists->ignored_wp_upgrades ?? '' );

		foreach ( $ignore_lists as $ignored ) {
			if ( MainWP_Common_Functions::instance()->is_ignored_updates( $core, $this->decode_list( $ignored ), 'core' ) ) {
				return 0;
			}
		}

		return 1;
	}

	/**
	 * Pending plugin or theme updates, premium merged in by slug, ignored ones removed.
	 *
	 * @param object      $site    The site row.
	 * @param string      $type    `plugin` or `theme`.
	 * @param array       $premium The site's premium updates.
	 * @param object|null $global_lists The dashboard-wide ignore lists.
	 *
	 * @return int
	 */
	private function count_updates( $site, $type, array $premium, $global_lists ) {

		$columns = array(
			'plugin' => array( 'is_ignorePluginUpdates', 'plugin_upgrades', 'ignored_plugins' ),
			'theme'  => array( 'is_ignoreThemeUpdates', 'theme_upgrades', 'ignored_themes' ),
		);

		list( $ignore_flag, $updates_column, $ignored_column ) = $columns[ $type ];

		if ( ! empty( $site->{$ignore_flag} ) ) {
			return 0;
		}

		$updates = $this->decode_list( $site->{$updates_column} ?? '' );

		// A slug in both lists counts once: the premium entry replaces the other.
		foreach ( $premium as $slug => $update ) {
			if ( is_array( $update ) && isset( $update['type'] ) && $type === $update['type'] ) {
				$updates[ $slug ] = $update;
			}
		}

		$ignore_lists = array( $site->{$ignored_column} ?? '', $global_lists->{$ignored_column} ?? '' );

		foreach ( $ignore_lists as $ignored ) {
			$updates = MainWP_Common_Functions::instance()->get_not_ignored_updates_themesplugins( $updates, $this->decode_list( $ignored ) );
		}

		return count( $updates );
	}

	/**
	 * Decode one of MainWP's JSON lists.
	 *
	 * @param mixed $json The stored value.
	 *
	 * @return array An empty array for anything that isn't a JSON list.
	 */
	private function decode_list( $json ) {

		$list = is_string( $json ) && '' !== $json ? json_decode( $json, true ) : null;

		return is_array( $list ) ? $list : array();
	}

	/**
	 * A positive integer ID, or 0. A negative number, text or a boolean is not an ID.
	 *
	 * @param mixed $value The value.
	 *
	 * @return int
	 */
	private function to_id( $value ) {

		if ( is_int( $value ) ) {
			return max( 0, $value );
		}

		if ( is_string( $value ) && ctype_digit( trim( $value ) ) ) {
			return (int) trim( $value );
		}

		return 0;
	}
}
