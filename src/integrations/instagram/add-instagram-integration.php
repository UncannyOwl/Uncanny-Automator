<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

/**
 * Contains Integration class.
 *
 * @since   2.4.0
 * @version 2.4.0
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Integration to Automator.
 *
 * @since 2.4.0
 */
class Add_Instagram_Integration {

	/**
	 * Integration Identifier
	 *
	 * @since 2.4.0
	 * @var   string
	 */
	public static $integration = 'INSTAGRAM';

	/**
	 * Connected status
	 *
	 * @var bool
	 */
	public $connected = false;

	/**
	 * Registers Integration.
	 *
	 * @since 2.4.0
	 */
	public function add_integration_func() {

		$ig_account_connected_count = $this->get_total_ig_accounts_connected();

		if ( $ig_account_connected_count >= 1 ) {

			$this->connected = true;

		}

		// Set up integration configuration.
		$integration_config = array(
			'name'         => 'Instagram',
			'icon_svg'     => Utilities::automator_get_integration_icon( __DIR__ . '/img/instagram-icon.svg' ),
			'connected'    => $this->connected,
			'settings_url' => automator_get_premium_integrations_settings_url( 'instagram' ),
		);

		// Register the integration into Automator.
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

	/**
	 * Check if there pages contains an instagram account.
	 *
	 * @return integer The total number of instagram account connected.
	 */
	public function get_total_ig_accounts_connected() {

		$options_facebook_pages = get_option( '_uncannyowl_facebook_pages_settings', array() );

		$total = 0;

		foreach ( $options_facebook_pages as $page ) {

			$ig_account = isset( $page['ig_account'] ) ? $page['ig_account'] : '';

			if ( ! empty( $ig_account ) ) {

				$total ++;
			}
		}

		return $total;

	}

}
