<?php

namespace Uncanny_Automator;

/**
 * Class Add_WpFusion_Integration
 * @package Uncanny_Automator
 */
class Add_Wf_Integration {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WF';

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
	 * @param $code
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $code ) {

		if ( self::$integration === $code ) {
			if ( class_exists( 'WP_Fusion_Lite' ) || class_exists( 'WP_Fusion' ) ) {
				$status = true;
			}
		}

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

		return $directory;
	}

	/**
	 * Register the integration by pushing it into the global automator object
	 */
	public function add_integration_func() {

		// global $uncanny_automator;

		Automator()->register->integration( self::$integration, array(
			'name'     => 'WP Fusion',
			'icon_svg' => Utilities::automator_get_integration_icon( __DIR__ . '/img/wp-fusion-icon.svg' ),
		) );
	}
}
