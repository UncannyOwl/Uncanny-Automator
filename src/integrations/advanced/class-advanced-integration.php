<?php

namespace Uncanny_Automator\Integrations\Advanced;

/**
 * Class Advanced_Integration
 *
 * @package Uncanny_Automator
 */
class Advanced_Integration extends \Uncanny_Automator\Integration {

	public $tokens;

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'ADVANCED' );
		$this->set_name( 'Advanced' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/advanced-icon.svg' );
	}

	/**
	 * load
	 *
	 * @return void
	 */
	protected function load() {
		new Calculation_Token();
		new Postmeta_Token();
		new Usermeta_Token();
		new Recipe_Run_Token();
		new Recipe_Run_Total_Token();
	}
}
