<?php

namespace Uncanny_Automator\Integrations\All_In_One_Wp_Migration;

use Ai1wm_Backups;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Ai1wm_Delete_Backup_File
 *
 * Deletes a `.wpress` backup via `Ai1wm_Backups::delete_file()`. The static
 * model method is called directly — the plugin's AJAX controllers enforce a
 * secret key, the model layer does not.
 *
 * @package Uncanny_Automator\Integrations\All_In_One_Wp_Migration
 *
 * @property All_In_One_Wp_Migration_Helpers $item_helpers
 */
class Ai1wm_Delete_Backup_File extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'ALL_IN_ONE_WP_MIGRATION' );
		$this->set_action_code( 'AI1WM_DELETE_BACKUP_FILE' );
		$this->set_action_meta( 'AI1WM_BACKUP_FILE' );
		$this->set_is_pro( false );
		// File maintenance needs no recipe user, so this runs in anonymous and
		// scheduled recipes too.
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Backup file selector token placeholder */
				esc_html_x( 'Delete {{a backup file:%1$s}}', 'All-in-One WP Migration', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Delete {{a backup file}}', 'All-in-One WP Migration', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Backup file', 'All-in-One WP Migration', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'backup_files_strict' ),
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'AI1WM_DELETED_FILENAME' => array(
				'name' => esc_html_x( 'Deleted backup filename', 'All-in-One WP Migration', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( '\Ai1wm_Backups' ) ) {
			$this->add_log_error( esc_html_x( 'All-in-One WP Migration is not available.', 'All-in-One WP Migration', 'uncanny-automator' ) );
			return false;
		}

		$file = isset( $parsed[ $this->get_action_meta() ] ) ? trim( (string) $parsed[ $this->get_action_meta() ] ) : '';

		if ( '' === $file ) {
			$this->add_log_error( esc_html_x( 'A backup file is required.', 'All-in-One WP Migration', 'uncanny-automator' ) );
			return false;
		}

		// The field is a select fed by remote_data, but it also accepts a token,
		// so the value reaching a destructive delete is not guaranteed to be one
		// of the options we offered. Keep the containment check explicit here
		// rather than relying on ai1wm_backup_path() throwing downstream.
		if ( ! $this->item_helpers->is_safe_backup_filename( $file ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s: the backup filename */
					esc_html_x( '%s is not a valid backup filename.', 'All-in-One WP Migration', 'uncanny-automator' ),
					$file
				)
			);
			return false;
		}

		// delete_file() silently returns false for anything that is not a
		// .wpress file, so check the extension first to give a useful log line.
		if ( function_exists( 'ai1wm_is_filename_supported' ) && ! ai1wm_is_filename_supported( $file ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s: the backup filename */
					esc_html_x( '%s is not a supported backup file.', 'All-in-One WP Migration', 'uncanny-automator' ),
					$file
				)
			);
			return false;
		}

		// Guard against this deletion firing the integration's own "A site
		// backup is deleted" trigger — delete_file() dispatches that exact hook.
		All_In_One_Wp_Migration_Helpers::$is_deleting_via_action = true;
		try {
			$deleted = Ai1wm_Backups::delete_file( $file );
		} finally {
			All_In_One_Wp_Migration_Helpers::$is_deleting_via_action = false;
		}

		if ( empty( $deleted ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s: the backup filename */
					esc_html_x( '%s could not be deleted. It may be missing or not writable.', 'All-in-One WP Migration', 'uncanny-automator' ),
					$file
				)
			);
			return false;
		}

		$this->hydrate_tokens( array( 'AI1WM_DELETED_FILENAME' => $file ) );

		return true;
	}
}
