<?php

namespace Uncanny_Automator\Integrations\Smush;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Smush_Restore_Image
 *
 * @package Uncanny_Automator
 *
 * @property Smush_Helpers $item_helpers
 */
class Smush_Restore_Image extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SMUSH' );
		$this->set_action_code( 'SMUSH_RESTORE_IMAGE' );
		$this->set_action_meta( 'SMUSH_IMAGE' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		// No background processing: restore is a local file operation with no
		// remote call, unlike the optimize action.
		$this->set_sentence(
			sprintf(
				/* translators: 1: Image to restore */
				esc_html_x( 'Restore {{an image:%1$s}} to its original', 'Smush', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Restore {{an image}} to its original', 'Smush', 'uncanny-automator' ) );
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Image', 'Smush', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_search_config( 'images_strict' ),
			),
		);
	}

	/**
	 * Define the action output tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return $this->item_helpers->get_image_action_token_definitions();
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$attachment_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( ! $this->item_helpers->is_image_attachment( $attachment_id ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: 1: Attachment ID */
					esc_html_x( 'The selected attachment (%d) does not exist or is not an image.', 'Smush', 'uncanny-automator' ),
					$attachment_id
				)
			);

			return false;
		}

		if ( ! class_exists( '\Smush\Core\Media\Media_Item_Cache' ) || ! class_exists( '\Smush\Core\Media\Media_Item_Optimizer' ) ) {
			$this->add_log_error( esc_html_x( 'The Smush media classes are not available.', 'Smush', 'uncanny-automator' ) );

			return false;
		}

		$this->load_wp_admin_image_api();

		$media_item = \Smush\Core\Media\Media_Item_Cache::get_instance()->get( $attachment_id );
		$optimizer  = new \Smush\Core\Media\Media_Item_Optimizer( $media_item );

		// Returns false when no backup exists, or when an optimize/restore is
		// already running for this attachment.
		if ( ! $optimizer->restore() ) {
			$this->add_log_error(
				$this->item_helpers->get_error_message(
					$optimizer->get_restoration_errors(),
					esc_html_x( 'Smush could not restore the image. A backup of the original may not exist, or an optimization is already in progress.', 'Smush', 'uncanny-automator' )
				)
			);

			return false;
		}

		$this->hydrate_tokens( $this->item_helpers->hydrate_image_tokens( $attachment_id, false ) );

		return true;
	}

	/**
	 * Load the wp-admin image API that Smush's restore path depends on.
	 *
	 * `Backups::restore_backup()` calls `wp_generate_attachment_metadata()`
	 * unqualified (`wp-smushit/core/backups/class-backups.php:176`) to rebuild
	 * the thumbnails from the restored original. That function lives in
	 * `wp-admin/includes/image.php`, which WordPress only autoloads on admin
	 * requests. Smush never loads it itself because its own restore is only
	 * ever reached from admin-ajax — but a recipe action runs on the front end,
	 * over REST, or under cron, where the call fatals with
	 * `Call to undefined function Smush\Core\Backups\wp_generate_attachment_metadata()`
	 * (unqualified calls resolve to the caller's namespace before the global one,
	 * which is why the error names the Smush namespace).
	 *
	 * `media.php` and `file.php` are pulled in alongside it because
	 * `wp_generate_attachment_metadata()` reaches into both for non-image
	 * sub-types and filesystem helpers.
	 *
	 * @return void
	 */
	protected function load_wp_admin_image_api() {

		if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
}
