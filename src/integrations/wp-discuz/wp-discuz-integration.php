<?php

namespace Uncanny_Automator\Integrations\Wp_Discuz;

use Uncanny_Automator\WP_ADD_REPLY_TO_COMMENT;

/**
 * Class Wp_Discuz_Integration
 *
 * @package Uncanny_Automator
 */
class Wp_Discuz_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Wp_Discuz_Helpers();
		$this->set_integration( 'WPDISCUZ' );
		$this->set_name( 'wpDiscuz' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wpdiscuz-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		new WP_DISCUZ_USER_SUBMITS_COMMENT( $this->helpers );
		new WP_DISCUZ_USER_POST_RECEIVES_COMMENT( $this->helpers );

		// Load actions.
		new WP_DISCUZ_ADD_REPLY_TO_COMMENT( $this->helpers );

		// Load ajax methods.
		add_action( 'wp_ajax_get_all_posts_by_post_type', array( $this->helpers, 'get_all_posts_by_post_type' ) );
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WpdiscuzCore' );
	}
}
