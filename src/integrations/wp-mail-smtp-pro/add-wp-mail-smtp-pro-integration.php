<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wp_Mail_Smtp_Pro_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wp_Mail_Smtp_Pro_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->set_integration( 'WPMAILSMTPPRO' );
		$this->set_name( 'WP Mail SMTP Pro' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_icon( 'wp-mail-smtp-icon.svg' );
	}

	/**
	 * Method plugin_active
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'wp_mail_smtp' );
	}
}
