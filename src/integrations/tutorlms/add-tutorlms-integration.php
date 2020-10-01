<?php
/**
 * Contains Integration class.
 *
 * @version 2.4.0
 * @since 2.4.0
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

defined( '\ABSPATH' ) || exit;

/**
 * Adds Integration to Automator.
 * @since 2.4.0
 */
class Add_Tutorlms_Integration {

	/**
	 * Integration Identifier.
	 *
	 * @var   string
	 * @since 2.4.0
	 */
	public static $integration = 'TUTORLMS';

	/**
	 * Constructs the class.
	 *
	 * @since 2.4.0
	 */
	public function __construct() {
		// Add directories to auto loader
		// add_filter( 'uncanny_automator_integration_directory', [ $this, 'add_integration_directory_func' ], 11 );

		// Add code, name and icon set to automator
		// add_action( 'uncanny_automator_add_integration', [ $this, 'add_integration_func' ] );

		// Verify is the plugin is active based on integration code
		// add_filter( 'uncanny_automator_maybe_add_integration', [ $this, 'plugin_active' ], 30, 2 );
	}

	/**
	 * Registers Integration.
	 *
	 * @since 2.4.0
	 */
	public function add_integration_func() {

		// set up configuration.
		$integration_config = [
			'name'     => 'Tutor LMS',
			'icon_svg' => Utilities::get_integration_icon( 'tutorlms-icon.svg' ),
		];

		// global automator object.
		global $uncanny_automator;

		// register integration into automator.
		$uncanny_automator->register->integration( self::$integration, $integration_config );

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

		return $directory;
	}

	/**
	 * Conditionally Loads Integration.
	 *
	 * @param bool $status Is Integration already active?
	 * @param string $plugin The integration identifier.
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {

		// not our code, bail early.
		if ( self::$integration !== $plugin ) {
			return $status;
		}

		// otherwise, return if Tutor LMS is active.
		return class_exists( '\TUTOR\Tutor' );
	}

}
