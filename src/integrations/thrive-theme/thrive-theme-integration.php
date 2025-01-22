<?php

namespace Uncanny_Automator\Integrations\Thrive_Theme;

/**
 * Class Thrive_Theme_Integration
 * @package Uncanny_Automator
 */
class Thrive_Theme_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Must use function in new integration to setup all required values
	 *
	 * @return mixed
	 */
	protected function setup() {
		$this->helpers = new Thrive_Theme_Helpers();
		$this->set_integration( 'THRIVE_THEME_BUILDER' );
		$this->set_name( 'Thrive Theme Builder' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/thrive-theme-builder-icon.svg' );
	}

	/**
	 * @return void
	 */
	protected function load() {
		// Load triggers
		new THR_USER_SUBMITS_FORM( $this->helpers );
		new THR_ANON_FORM_SUBMITTED( $this->helpers );
	}

	/**
	 * Pass plugin path to check if plugin is active. By default, it
	 * returns true for an integration.
	 *
	 * @return mixed|bool
	 */
	public function plugin_active() {
		$theme = wp_get_theme(); // gets the current theme
		if ( 'thrive-theme' === $theme->get_template() ) {
			return true;
		}

		return false;
	}
}
