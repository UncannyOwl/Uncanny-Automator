<?php
namespace Uncanny_Automator;

class Emails_Helpers {

	public function __construct( $load_hooks = true ) {

		// Migrate existing actions to emails.
		$this->migrate_action();

		if ( $load_hooks ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			// Add action hooks or filters here.
		}

	}

	/**
	 * Migrate existing email action to new Emails integration.
	 *
	 * @return void
	 */
	protected function migrate_action() {

		$option_key = 'automator_wp_send_email_action_moved__4.3';

		if ( 'yes' === automator_get_option( $option_key ) ) {
			return;
		}

		global $wpdb;

		$current_actions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_value = %s AND meta_key = %s",
				'SENDEMAIL',
				'code'
			)
		);

		if ( empty( $current_actions ) ) {
			update_option( $option_key, 'yes', true );
			return;
		}

		foreach ( $current_actions as $action ) {
			update_post_meta( $action->post_id, 'integration', 'EMAILS' );
			update_post_meta( $action->post_id, 'integration_name', 'EMAILS' );
		}

		update_option( $option_key, 'yes', true );

	}
}
