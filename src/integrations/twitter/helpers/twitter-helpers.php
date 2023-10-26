<?php

namespace Uncanny_Automator;

/**
 * Class X_Twitter_Helpers
 *
 * @package Uncanny_Automator
 */
class Twitter_Helpers {

	/**
	 * Twitter_Helpers constructor.
	 */
	public function __construct() {

		require_once __DIR__ . '/../functions/twitter-functions.php';

		$functions = new Twitter_Functions();

		add_action( 'init', array( $functions, 'disconnect' ) );
		add_action( 'init', array( $functions, 'capture_legacy_oauth_tokens' ) );

		include_once __DIR__ . '/../settings/settings-twitter.php';

		new Twitter_Settings();
	}
}

