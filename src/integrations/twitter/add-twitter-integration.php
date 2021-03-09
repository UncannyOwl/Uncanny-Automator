<?php
/**
 * Contains Integration class.
 *
 * @version 2.4.0
 * @since 2.4.0
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Integration to Automator.
 * @since 2.4.0
 */
class Add_Twitter_Integration {

	/**
	 * Integration Identifier
	 *
	 * @var   string
	 * @since 2.4.0
	 */
	public static $integration = 'TWITTER';

	/**
	 * Constructs the class.
	 *
	 * @since 2.4.0
	 */
	public function __construct() {}

	/**
	 * Registers Integration.
	 *
	 * @since 2.4.0
	 */
	public function add_integration_func() {

		// set up configuration.
		$integration_config = array(
			'name'     => 'Twitter',
			'icon_svg' => Utilities::get_integration_icon( 'twitter-icon.svg' ),
		);

		// global automator object.
		global $uncanny_automator;

		// register integration into automator.
		$uncanny_automator->register->integration( self::$integration, $integration_config );

	}

	/**
	 * Set the directories that the auto loader will run in.
	 *
	 * @param $directory
	 *
	 * @return array
	 */
	public function add_integration_directory_func( $directory ) {

		$directory[]    = dirname( __FILE__ ) . '/helpers';
		$twitter_client = get_option( '_uncannyowl_twitter_settings', array() );

		if ( isset( $twitter_client['oauth_token'] ) && ! empty( $twitter_client['oauth_token_secret'] ) ) {
			$directory[] = dirname( __FILE__ ) . '/actions';
		}

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
