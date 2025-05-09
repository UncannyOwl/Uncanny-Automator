<?php

namespace Uncanny_Automator\Services\Plugin;

/**
 * Trait Manager_Translations
 *
 * @package Uncanny_Automator\Services\Plugin
 */
trait Manager_Translations {

	/**
	 * Translations for the plugin manager.
	 *
	 * @var array $i18n
	 */
	public $i18n = array();

	/**
	 * Translations for the action being performed.
	 *
	 * @var array $action_i18n
	 */
	public $action_i18n = array();

	/**
	 * Set translations for the action being performed.
	 *
	 * @return void
	 */
	public function set_translations() {

		$this->i18n = array(
			// translators: %s the successful action performed.
			'success'        => esc_html_x( 'Plugin successfully %s', 'Plugin Manager', 'uncanny-automator' ),
			'security'       => esc_html_x( 'Security check failed. Please refresh the page and try again.', 'Plugin Manager', 'uncanny-automator' ),
			'unknown'        => esc_html_x( 'Unknown error', 'Plugin Manager', 'uncanny-automator' ),
			// translators: %s: Error message.
			'error'          => esc_html_x( 'Error: %s', 'Plugin Manager', 'uncanny-automator' ),
			// translators: %s: Error code.
			'code'           => esc_html_x( '(Code: %s)', 'Plugin Manager', 'uncanny-automator' ),
			// translators: %s: Action success translation.
			'activation'     => esc_html_x( '%s but automatic activation failed. Please activate manually.', 'Plugin Manager', 'uncanny-automator' ),
			'invalid_url'    => esc_html_x( 'Invalid download URL.', 'Plugin Manager', 'uncanny-automator' ),
			'invalid_action' => esc_html_x( 'Invalid operation.', 'Plugin Manager', 'uncanny-automator' ),
			'multisite'      => esc_html_x( 'Please contact your network administrator.', 'Plugin Manager', 'uncanny-automator' ),
			'invalid_plugin' => esc_html_x( 'Invalid plugin.', 'Plugin Manager', 'uncanny-automator' ),
		);

		switch ( $this->operation ) {
			case 'install':
				$this->set_install_i18n();
				break;
			case 'remote_update':
			case 'update':
				$this->set_update_i18n();
				break;
			case 'activate':
				$this->set_activate_i18n();
				break;
		}
	}

	/**
	 * Generate error message.
	 *
	 * @param string $message
	 * @param string $error
	 * @param int $code
	 *
	 * @return string
	 */
	public function get_error_message( $message, $error, $code ) {
		// Add error details if available
		$message .= ! empty( $error )
			? ' ' . sprintf( $this->i18n['error'], $error )
			: '';

		$message .= ! empty( $code )
			? ' ' . sprintf( $this->i18n['code'], $code )
			: '';

		return $message;
	}

	/**
	 * Generate success message.
	 */
	public function get_success_message() {

		if ( 'activate' === $this->operation ) {
			return sprintf(
				$this->i18n['success'],
				$this->action_i18n['success']['action']
			);
		}

		// Was activation set and is plugin active.
		$completed = $this->activate
			? $this->activated
			: true;

		// Action(s) completed.
		$action = $completed && $this->activate
			? $this->action_i18n['success']['activation']
			: $this->action_i18n['success']['action'];

		// Success message.
		$message = sprintf( $this->i18n['success'], $action );

		// Return with conditional failed activation messaging.
		return ! $completed
			? sprintf( $this->i18n['activation'], $message )
			: $message;
	}

	/**
	 * Set translations for install
	 *
	 * @return void
	 */
	public function set_install_i18n() {
		$this->action_i18n = array(
			'success' => array(
				'action'     => esc_html_x( 'installed', 'Plugin Manager', 'uncanny-automator' ),
				'activation' => esc_html_x( 'installed and activated', 'Plugin Manager', 'uncanny-automator' ),
			),
			'error'   => array(
				'failed'      => esc_html_x( 'Plugin installation failed.', 'Plugin Manager', 'uncanny-automator' ),
				'permissions' => esc_html_x( 'You do not have permission to install plugins.', 'Plugin Manager', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Set translations for update
	 *
	 * @return void
	 */
	public function set_update_i18n() {
		$this->action_i18n = array(
			'success' => array(
				'action'     => esc_html_x( 'updated', 'Plugin Manager', 'uncanny-automator' ),
				'activation' => esc_html_x( 'updated and activated', 'Plugin Manager', 'uncanny-automator' ),
			),
			'error'   => array(
				'failed'      => esc_html_x( 'Plugin upgrade failed.', 'Plugin Manager', 'uncanny-automator' ),
				'permissions' => esc_html_x( 'You do not have permission to upgrade plugins.', 'Plugin Manager', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Set translations for activate
	 *
	 * @return void
	 */
	public function set_activate_i18n() {
		$this->action_i18n = array(
			'success' => array(
				'action' => esc_html_x( 'activated', 'Plugin Manager', 'uncanny-automator' ),
			),
			'error'   => array(
				'failed'      => esc_html_x( 'Plugin activation failed.', 'Plugin Manager', 'uncanny-automator' ),
				'permissions' => esc_html_x( 'You do not have permission to activate plugins.', 'Plugin Manager', 'uncanny-automator' ),
			),
		);
	}
}
