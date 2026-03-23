<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Integrations\WP\Tokens\Loopable\Universal\Post_Categories;
use Uncanny_Automator\Integrations\WP\Tokens\Loopable\Universal\Post_Tags;

/**
 * Class Add_Wp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wp_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wp_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Explicitly return true because WordPress is always active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}

	/**
	 * Creates a set of loopable tokens.
	 *
	 * @return array{mixed[]}
	 */
	public function create_loopable_tokens() {
		return array(
			'WP_POST_TAGS'       => Post_Tags::class,
			'WP_POST_CATEGORIES' => Post_Categories::class,
		);
	}

	/**
	 * Setup Integration
	 */
	protected function setup() {
		$this->set_integration( 'WP' );
		$this->set_name( 'WordPress' );
		$this->set_icon( __DIR__ . '/img/wordpress-icon.svg' );
		$this->set_plugin_file_path( '' );
		$this->set_loopable_tokens( $this->create_loopable_tokens() );
	}
}
