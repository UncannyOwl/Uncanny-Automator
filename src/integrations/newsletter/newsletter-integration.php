<?php

namespace Uncanny_Automator\Integrations\Newsletter;

use Uncanny_Automator\Integration;

/**
 * Class Newsletter_Integration
 *
 * @pacakge Uncanny_Automator
 */
class Newsletter_Integration extends Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'NEWSLETTER' );
		$this->set_name( 'Newsletter' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/newsletter-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load action.
		new ADD_CONTACT_TO_LIST();
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'NEWSLETTER_VERSION' );
	}
}
