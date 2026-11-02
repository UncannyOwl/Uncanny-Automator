<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Services\Email\Attachment\Validator;

class Emails_Helpers {

	/**
	 * Runs the one-time move of legacy send-email actions to the Emails integration.
	 */
	public function __construct() {

		// Migrate existing actions to emails.
		$this->migrate_action();
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration; no WordPress API finds posts by a raw meta value across post types.
		$current_actions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_value = %s AND meta_key = %s",
				'SENDEMAIL',
				'code'
			)
		);

		if ( empty( $current_actions ) ) {
			automator_update_option( $option_key, 'yes', true );
			return;
		}

		foreach ( $current_actions as $action ) {
			update_post_meta( $action->post_id, 'integration', 'EMAILS' );
			update_post_meta( $action->post_id, 'integration_name', 'EMAILS' );
		}

		automator_update_option( $option_key, 'yes', true );

	}

	/**
	 * Returns the file attachment field description.
	 *
	 * @return string
	 */
	public static function get_file_attachment_field_description() {

		$attachment_description = sprintf(
			// translators: 1: File size limit
			esc_html__( 'Please ensure the file has a valid extension (e.g., .pdf, .png, .doc) and does not exceed the file size limit of %d MB.', 'uncanny-automator' ),
			Validator::to_megabytes(
				Validator::get_file_size_limit()
			)
		);

		return $attachment_description;

	}

}
