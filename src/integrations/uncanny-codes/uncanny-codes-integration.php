<?php

namespace Uncanny_Automator\Integrations\Uncanny_Codes;

/**
 * Class Uc_Integration
 *
 * @package Uncanny_Automator
 */
class Uc_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Uc_Helpers();
		$this->set_integration( 'UNCANNYCODE' );
		$this->set_name( 'Uncanny Redemption Codes' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/uncanny-owl-icon.svg' );
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {
		// Legacy token class for backward compatibility.
		new \Uncanny_Automator\Uc_Tokens();

		// Triggers.
		new UC_CODEREDEEMED( $this->helpers );
		new UC_CODESSUFFIX( $this->helpers );
		new UC_CODESPREFIX( $this->helpers );
		new UC_CODESBATCH( $this->helpers );
		new UC_ANON_CODEBATCHCREATED( $this->helpers );

		// Actions.
		new UC_GENERATE_CODES( $this->helpers );
		new UC_CANCEL_CODE( $this->helpers );
		new UC_ADD_BATCH_CODES( $this->helpers );
		new UC_DELETE_BATCH_CODES( $this->helpers );
	}

	/**
	 * Check if Uncanny Codes is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'UNCANNY_LEARNDASH_CODES_VERSION' );
	}
}
