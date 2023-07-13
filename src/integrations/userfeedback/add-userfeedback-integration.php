<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class Add_UserFeedback_Integration
 */
class Add_Userfeedback_Integration {
	use Recipe\Integrations;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Setup integration
	 */
	protected function setup() {
		$this->set_integration( 'USERFEEDBACK' );
		$this->set_name( 'UserFeedback' );
		$this->set_icon( 'userfeedback-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( plugin_dir_path( dirname( dirname( __FILE__ ) ) ) );
		$this->set_external_integration( true );
	}

	/**
	 * Check if the integration should be active
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( '\UserFeedback_Base' );
	}
}
