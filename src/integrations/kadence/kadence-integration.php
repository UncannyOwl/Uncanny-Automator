<?php

namespace Uncanny_Automator\Integrations\Kadence;

use Uncanny_Automator\Integration;

/**
 * Registers the Kadence integration and its form-submission triggers with
 * Uncanny Automator.
 *
 * @package Uncanny_Automator\Integrations\Kadence
 */
class Kadence_Integration extends Integration {

	/**
	 * Set the integration's identity (code, name, icon) and instantiate its
	 * helpers.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Kadence_Helpers();
		$this->set_integration( 'KADENCE' );
		$this->set_name( 'Kadence' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/kadence-icon.svg' );
	}

	/**
	 * Load the integration's triggers. Each declares its Kadence hooks via
	 * definition() (code-defined hooks), so they register directly with the
	 * engine — no relay or shared-hook wiring is needed here.
	 *
	 * @return void
	 */
	public function load() {
		new KADENCE_FORM_SUBMITTED( $this->helpers );
		new KADENCE_ANON_FORM_SUBMITTED( $this->helpers );
	}

	/**
	 * Whether the integration's dependency (the Kadence theme or the Kadence
	 * Blocks plugin) is present.
	 *
	 * @return bool
	 */
	public function plugin_active() {

		if ( defined( 'KADENCE_BLOCKS_VERSION' ) ) {
			return true;
		}

		$theme = wp_get_theme();

		return 'Kadence' === $theme->name || 'Kadence' === $theme->parent_theme;
	}
}
