<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Integrations\Buddypress\Tokens\Loopable\Universal\User_Groups;

/**
 * Class Add_Bp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Bp_Integration {
	use Recipe\Integrations;

	/**
	 * Add_Bp_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'BP' );
		$this->set_name( 'BuddyPress' );
		$this->set_icon( 'buddypress-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'buddypress/bp-loader.php' );
		$this->set_loopable_tokens( $this->create_loopable_tokens() );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'BuddyPress' );
	}

	/**
	 * Create loopable tokens.
	 *
	 * @return array
	 */
	public function create_loopable_tokens() {
		return array(
			'USER_GROUPS' => User_Groups::class,
		);
	}

}
