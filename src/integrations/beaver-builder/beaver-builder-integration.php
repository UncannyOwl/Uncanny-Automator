<?php

namespace Uncanny_Automator\Integrations\Beaver_Builder;

/**
 * Class Beaver_Builder_Integration
 *
 * @package Uncanny_Automator
 */
class Beaver_Builder_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Set up integration code, name, icon and helpers.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Beaver_Builder_Helpers();
		$this->set_integration( 'BEAVER_BUILDER' );
		$this->set_name( 'Beaver Builder' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/beaver-builder-icon.svg' );
	}

	/**
	 * Bootstrap triggers and actions.
	 *
	 * @return void
	 */
	public function load() {
		// Triggers.
		new Bb_Subscribe_Form_Submitted( $this->helpers );
		new Bb_Contact_Form_Submitted( $this->helpers );
	}

	/**
	 * Active when Beaver Builder (Lite or Pro) is installed. The MVP form
	 * triggers require the Pro form modules, which is gated per-trigger via
	 * requirements_met().
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'FL_BUILDER_VERSION' );
	}
}
