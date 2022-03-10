<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

/**
 * Contains Integration class.
 *
 * @version 2.4.0
 * @since   2.4.0
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Integration to Automator.
 *
 * @since 2.4.0
 */
class Add_Facebook_Integration {

	/**
	 * Integration Identifier
	 *
	 * @var   string
	 * @since 2.4.0
	 */
	public static $integration = 'FACEBOOK';

	/**
	 * Connected status
	 *
	 * @var bool
	 */
	public $connected = false;

	/**
	 * Constructs the class.
	 *
	 * @since 2.4.0
	 */
	public function __construct() {
	}

	/**
	 * Registers Integration.
	 *
	 * @since 2.4.0
	 */
	public function add_integration_func() {

		$facebook_options_user  = get_option( '_uncannyowl_facebook_settings', array() );
		$facebook_options_pages = get_option( '_uncannyowl_facebook_pages_settings', array() );

		if ( ! empty( $facebook_options_user ) && ! empty( $facebook_options_pages ) ) {
			$this->connected = true;
		}

		// set up configuration.
		$integration_config = array(
			'name'         => 'Facebook Pages',
			'icon_svg'     => Utilities::automator_get_integration_icon( __DIR__ . '/img/facebook-icon.svg' ),
			'connected'    => $this->connected, //
			'settings_url' => automator_get_premium_integrations_settings_url( 'facebook-pages' ),
		);

		// register integration into automator.
		Automator()->register->integration( self::$integration, $integration_config );

	}

	/**
	 * Set the directories that the auto loader will run in.
	 *
	 * @param $directory
	 *
	 * @return array
	 */
	public function add_integration_directory_func( $directory ) {

		$directory[] = dirname( __FILE__ ) . '/helpers';
		$directory[] = dirname( __FILE__ ) . '/actions';

		return $directory;
	}

	/**
	 * This integration doesn't require any third-party plugins too be active, so the following function will always
	 * return true.
	 *
	 * @param $status
	 * @param $code
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $code ) {
		return true;
	}

}
