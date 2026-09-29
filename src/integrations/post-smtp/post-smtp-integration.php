<?php

namespace Uncanny_Automator\Integrations\Post_Smtp;

/**
 * Class Post_Smtp_Integration
 *
 * @package Uncanny_Automator\Integrations\Post_Smtp
 */
class Post_Smtp_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Post_Smtp_Helpers();
		$this->set_integration( 'POST_SMTP' );
		$this->set_name( 'Post SMTP' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/post-smtp-icon.svg' );

		$this->set_plugin_file_path( 'post-smtp/postman-smtp.php' );
		$this->set_developer_name( 'Post SMTP' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Triggers.
		new Post_Smtp_Email_Sent( $this->helpers );
		new Post_Smtp_Email_Failed( $this->helpers );
	}

	/**
	 * Check if Post SMTP is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'POST_SMTP_VER' );
	}
}
