<?php

namespace Uncanny_Automator\Integrations\Learndash_Achievements;

/**
 * Class Ld_Achievements_Integration
 *
 * @package Uncanny_Automator\Integrations\Learndash_Achievements
 */
class Ld_Achievements_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Ld_Achievements_Helpers();

		$this->set_integration( 'LD_ACHIEVEMENTS' );
		$this->set_name( 'LearnDash Achievements' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/learndash-icon.svg' );
	}

	/**
	 * Load actions.
	 *
	 * @return void
	 */
	public function load() {

		$this->maybe_migrate_from_ld();

		new LD_ACHIEVEMENTS_AWARD( $this->helpers );
	}

	/**
	 * Both LearnDash and LearnDash Achievements must be active.
	 *
	 * @return bool
	 */
	public function plugin_active() {

		return defined( 'LEARNDASH_VERSION' ) && defined( 'LEARNDASH_ACHIEVEMENTS_VERSION' );
	}

	/**
	 * Migrate existing LDACHIEVEMENTS actions from the LD integration to LD_ACHIEVEMENTS.
	 *
	 * This runs once. It finds all action posts with code=LDACHIEVEMENTS that still
	 * have integration=LD and updates them to integration=LD_ACHIEVEMENTS.
	 *
	 * @return void
	 */
	private function maybe_migrate_from_ld() {

		$option_key = 'automator_ld_achievements_migrated';

		if ( 'yes' === automator_get_option( $option_key ) ) {
			return;
		}

		global $wpdb;

		// Find action post IDs where code = LDACHIEVEMENTS and integration = LD.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT code_meta.post_id
				FROM {$wpdb->postmeta} code_meta
				INNER JOIN {$wpdb->postmeta} int_meta
					ON int_meta.post_id = code_meta.post_id
					AND int_meta.meta_key = %s
					AND int_meta.meta_value = %s
				WHERE code_meta.meta_key = %s
					AND code_meta.meta_value = %s",
				'integration',
				'LD',
				'code',
				'LDACHIEVEMENTS'
			)
		);

		if ( ! empty( $post_ids ) ) {
			foreach ( $post_ids as $post_id ) {
				update_post_meta( absint( $post_id ), 'integration', 'LD_ACHIEVEMENTS' );
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		automator_update_option( $option_key, 'yes' );
	}
}
