<?php
namespace Uncanny_Automator;

/**
 * Class Add_Ameliabooking_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Ameliabooking_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
		$this->maybe_migrate_amelia_triggers();
	}


	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->set_integration( 'AMELIABOOKING' );
		$this->set_name( 'Amelia' );
		$this->set_icon( __DIR__ . '/img/amelia-icon.svg' );

		$this->set_plugin_file_path( 'ameliabooking/ameliabooking.php' );
		$this->set_developer_name( 'TMS' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'commercial' );
	}

	/**
	 * Explicitly return true because its a 3rd-party plugin.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( '\AmeliaBooking\Plugin' );
	}

	/**
	 * Run migration for Amelia triggers to support unified hook
	 */
	public function maybe_migrate_amelia_triggers() {
		if( ! is_admin() ) {
			return;
		}

		// Check if migration already ran
		if ( '' !== automator_get_option( 'automator_amelia_triggers_unified_hook_migrated', '' ) ) {
			return;
		}

		global $wpdb;

		// New unified hook
		$new_hook = 'automator_amelia_appointment_booked';

		// Target trigger codes to migrate
		$trigger_codes = array( 'AMELIA_APPOINTMENT_BOOKED', 'AMELIA_USER_APPOINTMENT_BOOKED' );

		// Get post IDs for the specific trigger types
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration; no WordPress API finds posts by a raw meta value across post types.
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'code' AND meta_value IN (" . implode( ',', array_fill( 0, count( $trigger_codes ), '%s' ) ) . ')',
				...$trigger_codes
			)
		);

		if ( ! empty( $post_ids ) ) {
			// Update add_action meta for these specific posts
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration; no WordPress API rewrites a meta key or value across posts.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = 'add_action' AND post_id IN (" . implode( ',', array_fill( 0, count( $post_ids ), '%d' ) ) . ')',
					array_merge( array( $new_hook ), $post_ids )
				)
			);
		}

		// Mark migration as complete
		automator_add_option( 'automator_amelia_triggers_unified_hook_migrated', time() );
	}
}
