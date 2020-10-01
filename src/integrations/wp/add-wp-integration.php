<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wp_Integration
 * @package Uncanny_Automator
 */
class Add_Wp_Integration {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {

		// Add directories to auto loader
		// add_filter( 'uncanny_automator_integration_directory', [ $this, 'add_integration_directory_func' ], 11 );

		// Add code, name and icon set to automator
		// add_action( 'uncanny_automator_add_integration', [ $this, 'add_integration_func' ] );

		// Verify is the plugin is active based on integration code
//		add_filter( 'uncanny_automator_maybe_add_integration', [
//			$this,
//			'plugin_active',
//		], 30, 2 );
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {
		$status = true;

		return $status;
	}

	/**
	 * Set the directories that the auto loader will run in
	 *
	 * @param $directory
	 *
	 * @return array
	 */
	public function add_integration_directory_func( $directory ) {

		$directory[] = dirname( __FILE__ ) . '/helpers';
		$directory[] = dirname( __FILE__ ) . '/actions';
		$directory[] = dirname( __FILE__ ) . '/triggers';
		$directory[] = dirname( __FILE__ ) . '/tokens';
		$directory[] = dirname( __FILE__ ) . '/closures';

		return $directory;
	}

	/**
	 * Register the integration by pushing it into the global automator object
	 */
	public function add_integration_func() {

		global $uncanny_automator;

		$uncanny_automator->register->integration( self::$integration, array(
			'name'     => 'WordPress',
			'icon_svg' => Utilities::get_integration_icon( 'wordpress-icon.svg' ),
		) );

		$uncanny_automator->register->integration( 'UA', array(
			'name'     => 'Uncanny Automator',
			'icon_svg' => Utilities::get_integration_icon( 'uncanny-automator-icon.svg' ),
		) );
	}
}
