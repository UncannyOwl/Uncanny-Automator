<?php

namespace Uncanny_Automator\Integrations\Wp_Fluent_Forms;

/**
 * Fluent Forms integration entry point.
 *
 * @package Uncanny_Automator
 */
class Wp_Fluent_Forms_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Configure the integration metadata and helpers.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Wp_Fluent_Forms_Helpers();

		$this->set_integration( 'WPFF' );
		$this->set_name( 'Fluent Forms' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wp-fluent-forms-icon.svg' );
	}

	/**
	 * Instantiate the Fluent Forms triggers.
	 *
	 * @return void
	 */
	public function load() {
		new WPFF_SUBFORM( $this->helpers );
		new ANON_WPFF_SUBFORM( $this->helpers );
	}

	/**
	 * Determine whether Fluent Forms is currently active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'FLUENTFORM' );
	}
}
