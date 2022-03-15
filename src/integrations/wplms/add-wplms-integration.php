<?php

namespace Uncanny_Automator;

/**
 * Class Add_WPLMS_Integration
 *
 * @package Uncanny_Automator
 */
class Add_WPLMS_Integration {

	use Recipe\Integrations;

	/**
	 * Add_WPLMS_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPLMS' );
		$this->set_name( 'WP LMS' );
		$this->set_icon( 'wplms-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		if ( function_exists( 'is_wplms_4_0' ) || class_exists( 'WPLMS_Front_End' ) ) {
			add_action( 'admin_init', array( $this, 'migrate_wplms_old_version_to_latest' ) );

			return true;
		}

		return false;
	}

	/**
	 *
	 */
	public function migrate_wplms_old_version_to_latest() {
		if ( 'yes' === get_option( 'automator_wplms4_to_wplms_migration' ) ) {
			return;
		}

		global $wpdb;
		$current_triggers = $wpdb->get_results( "SELECT post_id FROM $wpdb->postmeta WHERE meta_value = 'WPLMS4' AND meta_key = 'integration'" );

		if ( empty( $current_triggers ) ) {
			update_option( 'automator_wplms4_to_wplms_migration', 'yes', false );

			return;
		}

		foreach ( $current_triggers as $t ) {
			$trigger_id = $t->post_id;
			update_post_meta( $trigger_id, 'integration', 'WPLMS' );
		}

		update_option( 'automator_wplms4_to_wplms_migration', 'yes', false );

	}
}
