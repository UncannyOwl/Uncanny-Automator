<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Services\Email\Attachment\Validator;
use WP_REST_Request;

class Emails_Helpers {

	public function __construct( $load_hooks = true ) {

		// Migrate existing actions to emails.
		$this->migrate_action();

		if ( $load_hooks ) {
			# Disable recipe builder validation: add_action( 'automator_recipe_before_options_update', array( $this, 'file_attachments_validate' ) );
		}

	}

	/**
	 * Validates file attachments.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return void
	 */
	public function file_attachments_validate( $request ) {

		$key = 'attachment_been_pre_validated';

		$recipe_id      = $request->get_param( 'recipe_id' );
		$action_id      = $request->get_param( 'itemId' );
		$options_values = $request->get_param( 'optionValue' );

		$file_attachment_url = trim( $options_values['FILE_ATTACHMENT_URL'] ?? '' );

		// Bail if file attachment url is empty.
		if ( empty( $file_attachment_url ) ) {
			return;
		}

		$validator = new Validator( $file_attachment_url, true ); // Skips the validation if it does contains a token.

		$validated = $validator->validate();

		// Clear the meta on update.
		delete_post_meta( $action_id, $key );

		if ( is_wp_error( $validated ) ) {
			$response = array(
				'message'       => $validated->get_error_message(),
				'success'       => false,
				'data'          => array(),
				'recipe_object' => Automator()->get_recipes_data( true, $recipe_id ),
				'_recipe'       => Automator()->get_recipe_object( $recipe_id ),
			);

			wp_send_json( $response );
		}

		if ( ! $validator->contains_tokens() ) {
			update_post_meta( $action_id, $key, 'yes' );
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
			__( 'Please ensure the file has a valid extension (e.g., .pdf, .png, .doc) and does not exceed the file size limit of %d MB.', 'uncanny-automator' ),
			Validator::to_megabytes(
				Validator::get_file_size_limit()
			)
		);

		return $attachment_description;

	}

}
