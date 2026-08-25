<?php

namespace Uncanny_Automator\Integrations\Really_Simple_Ssl;

/**
 * Class Really_Simple_Ssl_Integration
 *
 * Really Simple Security (formerly Really Simple SSL). The free plugin is
 * SSL/HTTPS plus a bundled vulnerability scanner, and the scanner's
 * sync-completed action is its only public automation hook — everything else
 * (firewall, IP blocking, login limiting) writes straight to the
 * `rsssl_event_logs` table with no do_action(). The 2FA hooks that make up the
 * rest of this plugin's automation surface live in Really Simple Security Pro,
 * so they ship as Automator Pro items.
 *
 * No helper class: the single trigger has no options and no tokens, so there is
 * nothing to share.
 *
 * @package Uncanny_Automator
 */
class Really_Simple_Ssl_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'REALLY_SIMPLE_SECURITY' );
		$this->set_name( 'Really Simple Security' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/really-simple-security-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Triggers.
		new Rsssl_Vulnerability_Scan_Completed();
	}

	/**
	 * Check if Really Simple Security is active. `rsssl_version` is defined in
	 * the plugin's main file (`rlrsssl-really-simple-ssl.php`) at load, so it is
	 * the earliest reliable signal.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'rsssl_version' );
	}
}
