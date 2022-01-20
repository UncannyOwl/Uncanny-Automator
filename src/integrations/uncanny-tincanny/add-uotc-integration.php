<?php

namespace Uncanny_Automator;

/**
 * Class Add_UOTC_Integration
 *
 * @package Uncanny_Automator
 */
class Add_UOTC_Integration {
	use Recipe\Integrations;

	/**
	 * Add_UOTC_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
		add_action( 'admin_init', array( $this, 'migrate_tin_canny_reporting' ) );
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'UOTC' );
		$this->set_name( 'Tin Canny Reporting' );
		$this->set_icon( 'uncanny-owl-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'tin-canny-learndash-reporting/tin-canny-learndash-reporting.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'LEARNDASH_VERSION' ) && defined( 'UNCANNY_REPORTING_VERSION' );
	}

	/**
	 *
	 */
	public function migrate_tin_canny_reporting() {
		if ( 'yes' === get_option( 'automator_tin_canny_trigger_moved' ) ) {
			return;
		}

		global $wpdb;
		$current_triggers = $wpdb->get_results( "SELECT post_id FROM $wpdb->postmeta WHERE meta_value = 'MODULEINTERACTION' AND meta_key = 'code'" );
		if ( empty( $current_triggers ) ) {
			update_option( 'automator_tin_canny_trigger_moved', 'yes', false );

			return;
		}
		foreach ( $current_triggers as $t ) {
			$trigger_id = $t->post_id;
			update_post_meta( $trigger_id, 'integration', 'UOTC' );
			update_post_meta( $trigger_id, 'integration_name', 'Tin Canny Reporting' );
		}

		update_option( 'automator_tin_canny_trigger_moved', 'yes', false );

	}
}
