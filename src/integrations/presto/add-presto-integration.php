<?php

namespace Uncanny_Automator;

/**
 * Class Add_Presto_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Presto_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Presto_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'PRESTO' );
		$this->set_name( 'Presto' );
		$this->set_icon( 'presto-player-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'presto-player/presto-player.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'PRESTO_PLAYER_PLUGIN_FILE' );
	}
}
