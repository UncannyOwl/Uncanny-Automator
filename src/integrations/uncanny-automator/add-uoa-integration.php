<?php

namespace Uncanny_Automator;

/**
 * Class Add_Uoa_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Uoa_Integration {
	use Recipe\Integrations;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {
		$this->maybe_migrate_numtimes();
		// The UOA actions' target field moved from RECIPE_ID, which shared the structural recipe_id row, to UOA_RECIPE_ID.
		new Integrations\Uncanny_Automator\Uoa_Recipe_Id_Field_Migration( 'uoa_recipe_id_field' );
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'UOA' );
		$this->set_name( 'Automator' );
		$this->set_icon( __DIR__ . '/img/automator-core-icon.svg' );
		$this->set_plugin_file_path( 'uncanny-automator/uncanny-automator.php' );

		$this->set_developer_name( 'Uncanny Automator' );
		$this->set_integration_type( 'built-in' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Explicitly return true because WordPress is always active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}

	/**
	 * @return void
	 */
	private function maybe_migrate_numtimes() {
		if ( 'yes' === automator_get_option( 'uoa_recipenumtimes_changed_to_numtimes', 'no' ) ) {
			return;
		}
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration; no WordPress API rewrites a meta key or value across posts.
		$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = %s WHERE meta_key = %s", 'NUMTIMES', 'RECIPENUMTIMES' ) );

		automator_update_option( 'uoa_recipenumtimes_changed_to_numtimes', 'yes' );
	}
}
