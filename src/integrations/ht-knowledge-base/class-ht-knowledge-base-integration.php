<?php

namespace Uncanny_Automator\Integrations\Ht_Knowledge_Base;

/**
 * Class Ht_Knowledge_Base_Integration
 *
 * @package Uncanny_Automator
 */
class Ht_Knowledge_Base_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Ht_Knowledge_Base_Helpers();
		$this->set_integration( 'HT_KB' );
		$this->set_name( 'Heroic Knowledge Base' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/ht-kb-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		new HT_KB_USER_GIVES_POSITIVE_RATING( $this->helpers );
		new HT_KB_USER_GIVES_NEGATIVE_RATING( $this->helpers );
		new HT_KB_ANON_GIVES_POSITIVE_RATING( $this->helpers );
		new HT_KB_ANON_GIVES_NEGATIVE_RATING( $this->helpers );
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 *
	 */
	public function plugin_active() {
		return class_exists( 'HT_Knowledge_Base' );
	}

}
