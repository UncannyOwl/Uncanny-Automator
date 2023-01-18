<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class Drip_Helpers
 *
 * @package Uncanny_Automator
 */
class Drip_Helpers {

	public function __construct() {

		$functions = new Drip_Functions();

		add_action( 'init', array( $functions, 'capture_oauth_tokens' ) );
		add_action( 'wp_ajax_automator_drip_disconnect', array( $functions, 'disconnect' ) );

		require_once __DIR__ . '/../settings/settings-drip.php';
	}
}
