<?php

namespace Uncanny_Automator\Integrations\Loginizer;

/**
 * Class Loginizer_Integration
 *
 * @package Uncanny_Automator
 */
class Loginizer_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Loginizer_Helpers();
		$this->set_integration( 'LOGINIZER' );
		$this->set_name( 'Loginizer' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/loginizer-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		// Registered here too: the framework auto-calls load_shared_hooks() only in
		// targeted mode, and load() only in full-load mode, so exactly one runs.
		$this->load_shared_hooks();

		// Triggers.
		new Loginizer_Login_Blocked( $this->helpers );
		new Loginizer_Login_Failed( $this->helpers );
		new Loginizer_User_Logs_In( $this->helpers );
		new Loginizer_Social_Registration( $this->helpers );

		// Actions.
		new Loginizer_Add_Ip_To_Blacklist( $this->helpers );
		new Loginizer_Add_Ip_To_Whitelist( $this->helpers );
		new Loginizer_Remove_Ip_From_Blacklist( $this->helpers );
		new Loginizer_Remove_Ip_From_Whitelist( $this->helpers );
		new Loginizer_Clear_Failed_Logins( $this->helpers );
	}

	/**
	 * Run-time bridges. These fire on front-end login requests, so they belong in
	 * load_shared_hooks() rather than load().
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {
		add_filter( 'wp_login_blocked', array( $this, 'bridge_login_blocked' ), 10, 1 );
		add_action( 'wp_login_failed', array( $this, 'bridge_login_failed' ), 20, 1 );
	}

	/**
	 * Bridge for `wp_login_blocked`.
	 *
	 * Loginizer raises this with apply_filters(), not do_action(). The trigger
	 * engine registers one shared callback that returns void, so listening to the
	 * filter directly would replace $username with null inside Loginizer's own
	 * chain. Bridging keeps the filter value untouched and re-broadcasts a
	 * normalized action for the trigger to match, with the block reason resolved
	 * while the request state that produced it is still current.
	 *
	 * @param string $username The username whose login was blocked.
	 *
	 * @return string The username, unmodified.
	 */
	public function bridge_login_blocked( $username ) {

		$ip = $this->helpers->current_ip();

		do_action(
			'automator_loginizer_login_blocked',
			(string) $username,
			$ip,
			$this->helpers->derive_block_reason( $ip, (string) $username )
		);

		return $username;
	}

	/**
	 * Bridge for `wp_login_failed`.
	 *
	 * Loginizer's own handler is registered at the default priority 10 and is what
	 * increments the attempt/lockout counters in `loginizer_logs`. Bridging at 20
	 * guarantees the trigger reads the updated row — the trigger engine pins every
	 * monitored hook to priority 10, so the trigger could not do this itself.
	 *
	 * @param string $username The username that failed to authenticate.
	 *
	 * @return void
	 */
	public function bridge_login_failed( $username ) {

		$ip = $this->helpers->current_ip();

		do_action(
			'automator_loginizer_login_failed',
			(string) $username,
			$ip,
			$this->helpers->get_failed_log_row( $ip )
		);
	}

	/**
	 * Check if Loginizer is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'LOGINIZER_VERSION' );
	}
}
