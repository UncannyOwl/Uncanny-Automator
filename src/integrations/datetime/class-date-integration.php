<?php

namespace Uncanny_Automator\Integrations\DateTime;

/**
 * Class DateTime_Integration
 *
 * @package Uncanny_Automator
 */
class DateTime_Integration extends \Uncanny_Automator\Integration {

	public $tokens;

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'DATETIME' );
		$this->set_name( 'Date and time' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/date-time-icon.svg' );
	}

	/**
	 * load
	 *
	 * @return void
	 */
	protected function load() {
		new Tokens\Current_Date_Time();
		new Tokens\Current_Date_Timestamp();
		new Tokens\Current_Date();
		new Tokens\Current_Time();
		new Tokens\Current_Timestamp();
	}
}
