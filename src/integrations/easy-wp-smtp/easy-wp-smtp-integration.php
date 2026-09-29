<?php

namespace Uncanny_Automator\Integrations\Easy_Wp_Smtp;

/**
 * Class Easy_Wp_Smtp_Integration
 *
 * @package Uncanny_Automator
 */
class Easy_Wp_Smtp_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'EASY_WP_SMTP' );
		$this->set_name( 'Easy WP SMTP' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/easy-wp-smtp-icon.svg' );

		$this->set_plugin_file_path( 'easy-wp-smtp/easy-wp-smtp.php' );
		$this->set_developer_name( 'WPForms' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load triggers.
	 *
	 * @return void
	 */
	public function load() {
		new Ewpsmtp_Email_Sent();
		new Ewpsmtp_Email_Failed();
		new Ewpsmtp_Email_Blocked();
	}

	/**
	 * Check if Easy WP SMTP is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'easy_wp_smtp' );
	}
}
