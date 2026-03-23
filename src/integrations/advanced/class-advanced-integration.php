<?php

namespace Uncanny_Automator\Integrations\Advanced;

/**
 * Class Advanced_Integration
 *
 * @package Uncanny_Automator
 */
class Advanced_Integration extends \Uncanny_Automator\Integration {

	use \Uncanny_Automator\Integration_Manifest;

	public $tokens;

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'ADVANCED' );
		$this->set_name( 'Advanced' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/advanced-icon.svg' );

		// Set manifest data for built-in integration.
		$this->set_integration_type( 'built-in' );
		$this->set_integration_tier( 'lite' );
		$this->set_integration_version( AUTOMATOR_PLUGIN_VERSION );
		$this->set_short_description( esc_html_x( 'Advanced tokens for calculations, metadata, and recipe run tracking.', 'Advanced', 'uncanny-automator' ) );
		$this->set_full_description( esc_html_x( 'Provides advanced tokens including calculation tokens, post metadata, user metadata, and recipe run tracking tokens for enhanced automation workflows.', 'Advanced', 'uncanny-automator' ) );
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
