<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Integrations\Buddyboss\Tokens\Loopable\Universal\User_Groups;

/**
 * Class Add_Bdb_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Bdb_Integration {

	use Recipe\Integrations;

	/**
	 * @var string
	 */
	const INTEGRATION = 'BDB';

	/**
	 * Add_Bdb_Integration constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->setup();
		$this->create_loopable_tokens();
	}

	/**
	 * Setup the integration.
	 *
	 * @return mixed
	 */
	protected function setup() {

		$this->set_integration( self::INTEGRATION );
		$this->set_name( 'BuddyBoss' );
		$this->set_icon( 'buddyboss-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'buddyboss-platform/bp-loader.php' );
		$this->set_loopable_tokens( $this->create_loopable_tokens() );

	}

	/**
	 * Determine whether the plugin is active or not.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'buddypress' ) && isset( buddypress()->buddyboss ) && buddypress()->buddyboss;
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
