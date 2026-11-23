<?php

namespace Uncanny_Automator\Integrations\Cfdb7;

use Uncanny_Automator\Integrations\Cfdb7\Tokens\Loopable\Universal\Cfdb7_All_Entries;

/**
 * Class Cfdb7_Integration
 *
 * @package Uncanny_Automator
 */
class Cfdb7_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Cfdb7_Helpers();
		$this->set_integration( 'CFDB7' );
		$this->set_name( 'Contact Form CFDB7' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/contact-form-7-database-addon-icon.svg' );

		$this->set_plugin_file_path( 'contact-form-cfdb7/contact-form-cfdb-7.php' );
		$this->set_developer_name( 'Arshid' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );

		// Declared, not inferred: plugin_active() gates on class_exists( 'WPCF7' ).
		$this->set_plugin_required( 'contact-form-7/wp-contact-form-7.php' );
		$this->set_integration_required( 'CF7' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		$this->load_shared_hooks();

		// Triggers.
		new Cfdb7_Entry_Saved( $this->helpers );

		// Actions.
		new Cfdb7_Delete_Entry( $this->helpers );
		new Cfdb7_Mark_Entry_Read( $this->helpers );
	}

	/**
	 * Register the run-time hooks.
	 *
	 * The loopable token belongs here rather than in load(): a token loop runs
	 * on the front end, where targeted loading skips load() entirely and the
	 * framework calls this method instead.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {
		( new Cfdb7_All_Entries( 'CFDB7' ) )->register_hooks();
	}

	/**
	 * Check if CFDB7 and Contact Form 7 are active.
	 *
	 * CFDB7 defines no version constant at any version, so the function it
	 * hooks into `wpcf7_before_send_mail` is the stable signal. CFDB7 only ever
	 * saves a CF7 submission, so CF7 is required as well.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'cfdb7_before_send_mail' ) && class_exists( 'WPCF7' );
	}
}
