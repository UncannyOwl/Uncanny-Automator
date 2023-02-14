<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class Drip_Helpers
 *
 * @package Uncanny_Automator
 */
class Drip_Helpers {

	public $functions;

	public function __construct() {

		$this->functions = new Drip_Functions();

		add_action( 'init', array( $this->functions, 'capture_oauth_tokens' ) );
		add_action( 'wp_ajax_automator_drip_disconnect', array( $this->functions, 'disconnect' ) );

		require_once __DIR__ . '/../settings/settings-drip.php';
		new Drip_Settings( $this );
	}
}
