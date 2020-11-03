<?php

namespace Uncanny_Automator;

/**
 * Class Add_Uoa_Integration
 * @package Uncanny_Automator
 */
class Add_Uoa_Integration {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'UOA';

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {

		// Add directories to auto loader
		// add_filter( 'uncanny_automator_integration_directory', [ $this, 'add_integration_directory_func' ], 11 );

		// Update previous triggers moved to this integration
		$this->update_script();

		// Add code, name and icon set to automator
		// add_action( 'uncanny_automator_add_integration', [ $this, 'add_integration_func' ] );

		// Verify is the plugin is active based on integration code
//		add_filter( 'uncanny_automator_maybe_add_integration', [
//			$this,
//			'plugin_active',
//		], 30, 2 );

	}

	/**
	 * Update previous triggers moved to this integration
	 */
	public function update_script() {
		if ( false === get_option( '_uoa_sendwebhook_wp_uoa_any', false ) ) {
			$args = [
				'post_type'   => 'uo-action',
				'post_status' => 'any',
				'meta_query'  => [
					'relation' => 'AND',
					[
						'key'     => 'integration',
						'value'   => 'WP',
						'compare' => 'LIKE',
					],
					[
						'key'     => 'code',
						'value'   => 'WPSENDWEBHOOK',
						'compare' => 'LIKE',
					],
				],
			];
			$old_triggers = get_posts( $args );
			if ( ! empty( $old_triggers ) ) {
				foreach ( $old_triggers as $old_trigger ) {
					update_post_meta( $old_trigger->ID, 'integration', self::$integration );
					update_post_meta( $old_trigger->ID, 'integration_name', 'Uncanny Automator' );
				}
			}
			update_option( '_uoa_sendwebhook_wp_uoa_any', 'updated' );
		}
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {
		$status = true;
	
		return $status;
	}

	/**
	 * Set the directories that the auto loader will run in
	 *
	 * @param $directory
	 *
	 * @return array
	 */
	public function add_integration_directory_func( $directory ) {

		$directory[] = dirname( __FILE__ ) . '/helpers';
		$directory[] = dirname( __FILE__ ) . '/actions';
		$directory[] = dirname( __FILE__ ) . '/triggers';
		$directory[] = dirname( __FILE__ ) . '/tokens';
		$directory[] = dirname( __FILE__ ) . '/closures';

		return $directory;
	}

	/**
	 * Register the integration by pushing it into the global automator object
	 */
	public function add_integration_func() {

		global $uncanny_automator;

		$uncanny_automator->register->integration( self::$integration, array(
			'name'     => 'Automator Core',
			'icon_svg' => Utilities::get_integration_icon( 'automator-core-icon.svg' ),
		) );

	}
}
