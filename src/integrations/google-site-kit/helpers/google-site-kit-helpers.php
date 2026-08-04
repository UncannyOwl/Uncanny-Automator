<?php

namespace Uncanny_Automator\Integrations\Google_Site_Kit;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Google_Site_Kit_Helpers
 *
 * Shared logic for the Site Kit by Google integration: the module slug → name
 * maps, the module-picker option builders, and their remote_data segments.
 *
 * Module slugs are stable `MODULE_SLUG` constants in the plugin
 * (`includes/Modules/*.php`). They're mapped here rather than enumerated at
 * runtime because Site Kit exposes no public accessor for its Modules instance.
 *
 * @package Uncanny_Automator\Integrations\Google_Site_Kit
 */
class Google_Site_Kit_Helpers extends Abstract_Helpers {

	/**
	 * "Any module" sentinel shared by the trigger dropdowns.
	 *
	 * @var string
	 */
	const ANY = '-1';

	/**
	 * Site option holding the array of active module slugs.
	 *
	 * @var string
	 */
	const ACTIVE_MODULES_OPTION = 'googlesitekit_active_modules';

	/**
	 * Force-active modules — always on, cannot be activated/deactivated by users.
	 *
	 * @var string[]
	 */
	const FORCE_ACTIVE_MODULES = array( 'search-console', 'site-verification' );

	/**
	 * Activatable (non-force-active) modules: slug => display name. These are the
	 * modules a user can activate/deactivate, so they populate every dropdown.
	 *
	 * @return array<string,string>
	 */
	public function get_activatable_modules() {
		// Raw _x() (not esc_html_x): these names flow into token values via
		// get_module_name(); token values must not be pre-escaped (the framework
		// escapes at output). Escape only at the UI/echo layer.
		return apply_filters(
			'automator_google_site_kit_activatable_modules',
			array(
				'analytics-4'            => _x( 'Analytics', 'Site Kit by Google', 'uncanny-automator' ),
				'adsense'                => _x( 'AdSense', 'Site Kit by Google', 'uncanny-automator' ),
				'tagmanager'             => _x( 'Tag Manager', 'Site Kit by Google', 'uncanny-automator' ),
				'pagespeed-insights'     => _x( 'PageSpeed Insights', 'Site Kit by Google', 'uncanny-automator' ),
				'ads'                    => _x( 'Ads', 'Site Kit by Google', 'uncanny-automator' ),
				'reader-revenue-manager' => _x( 'Reader Revenue Manager', 'Site Kit by Google', 'uncanny-automator' ),
				'sign-in-with-google'    => _x( 'Sign in with Google', 'Site Kit by Google', 'uncanny-automator' ),
			)
		);
	}

	/**
	 * All modules (activatable + force-active): slug => display name. Used to
	 * resolve a human-readable name for any slug surfaced in tokens.
	 *
	 * @return array<string,string>
	 */
	public function get_all_modules() {
		return apply_filters(
			'automator_google_site_kit_all_modules',
			array_merge(
				$this->get_activatable_modules(),
				array(
					// Raw _x() — see get_activatable_modules(): these feed token values.
					'search-console'    => _x( 'Search Console', 'Site Kit by Google', 'uncanny-automator' ),
					'site-verification' => _x( 'Site Verification', 'Site Kit by Google', 'uncanny-automator' ),
				)
			)
		);
	}

	/**
	 * Resolve a module slug to its display name, falling back to the slug.
	 *
	 * @param string $slug The module slug.
	 *
	 * @return string
	 */
	public function get_module_name( $slug ) {
		$map = $this->get_all_modules();
		return isset( $map[ (string) $slug ] ) ? (string) $map[ (string) $slug ] : (string) $slug;
	}

	/**
	 * Build module option pairs from the activatable module map.
	 *
	 * @param bool $include_any Whether to prepend the "Any module" sentinel.
	 *
	 * @return array
	 */
	public function get_module_options( $include_any = false ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'value' => self::ANY,
				'text'  => esc_html_x( 'Any module', 'Site Kit by Google', 'uncanny-automator' ),
			);
		}

		foreach ( $this->get_activatable_modules() as $slug => $name ) {
			$options[] = array(
				'value' => $slug,
				'text'  => $name,
			);
		}

		return $options;
	}

	/**
	 * Build option pairs for ALL modules (activatable + force-active), no "Any"
	 * sentinel. Consumed by the Pro "{{A module}} is active" condition, which can
	 * assert any module's status (force-active modules always read active).
	 *
	 * @return array
	 */
	public function get_all_module_options() {

		$options = array();

		foreach ( $this->get_all_modules() as $slug => $name ) {
			$options[] = array(
				'value' => $slug,
				'text'  => $name,
			);
		}

		return $options;
	}

	/**
	 * Remote_Data segment: modules incl. the "Any module" sentinel (triggers).
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_modules( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->get_module_options( true ) );
	}

	/**
	 * Remote_Data segment: modules without the "Any" sentinel (actions).
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_modules_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->get_module_options( false ) );
	}

	/**
	 * Remote_Data segment: ALL modules (incl. force-active), no "Any" sentinel.
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_modules_all( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->get_all_module_options() );
	}
}
