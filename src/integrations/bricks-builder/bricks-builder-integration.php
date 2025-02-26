<?php

namespace Uncanny_Automator\Integrations\Bricks_Builder;

use Uncanny_Automator\Bricks_Builder_Helpers;
use UncannyAutomator\Integrations\Bricks_Builder\BB_USER_SUBMITS_FORM;
use UncannyAutomator\Integrations\Bricks_Builder\BRICKS_BUILDER_USER_SUBMITS_FORM;

/**
 * Class Bricks_Builder_Integration
 *
 * @pacakge Uncanny_Automator
 */
class Bricks_Builder_Integration extends \Uncanny_Automator\Integration {

	/**
	 * @return void
	 */
	public function load() {
		new BRICKS_BUILDER_USER_SUBMITS_FORM( $this->helpers );
		new BRICKS_BUILDER_ANON_FORM_SUBMIT( $this->helpers );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		$theme = wp_get_theme(); // gets the current theme
		if ( 'bricks' === $theme->get_template() ) {
			return true;
		}

		return false;
	}

	/**
	 * Must use function in new integration to setup all required values
	 *
	 * @return mixed
	 */
	protected function setup() {
		$this->helpers = new Bricks_Builder_Helpers();
		$this->set_integration( 'BRICKS_BUILDER' );
		$this->set_name( 'Bricks Builder' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/bricks-builder-icon.svg' );
	}
}
