<?php

namespace Uncanny_Automator\Integrations\All_In_One_Wp_Migration;

use Ai1wm_Backups;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Ai1wm_Set_Backup_Label
 *
 * Labels a `.wpress` backup via `Ai1wm_Backups::set_label()`, which writes the
 * `ai1wm_backups_labels` option (filename => label). Non-destructive: it only
 * touches the label map, never the archive itself.
 *
 * @package Uncanny_Automator\Integrations\All_In_One_Wp_Migration
 *
 * @property All_In_One_Wp_Migration_Helpers $item_helpers
 */
class Ai1wm_Set_Backup_Label extends Action {

	/**
	 * Option code of the label text field.
	 */
	const LABEL = 'AI1WM_LABEL';

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'ALL_IN_ONE_WP_MIGRATION' );
		$this->set_action_code( 'AI1WM_SET_BACKUP_LABEL' );
		$this->set_action_meta( 'AI1WM_BACKUP_FILE' );
		$this->set_is_pro( false );
		// Writes an option, no recipe user needed.
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Backup file selector, %2$s: the label to store */
				esc_html_x( "Set {{a backup file's:%1\$s}} label to {{a specific value:%2\$s}}", 'All-in-One WP Migration', 'uncanny-automator' ),
				$this->get_action_meta(),
				self::LABEL . ':' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( "Set {{a backup file's}} label to {{a specific value}}", 'All-in-One WP Migration', 'uncanny-automator' ) );
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
			array(
				'option_code' => self::LABEL,
				'label'       => esc_html_x( 'Label', 'All-in-One WP Migration', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'AI1WM_BACKUP_FILENAME' => array(
				'name' => esc_html_x( 'Backup filename', 'All-in-One WP Migration', 'uncanny-automator' ),
				'type' => 'text',
			),
			'AI1WM_BACKUP_LABEL'    => array(
				'name' => esc_html_x( 'Backup label', 'All-in-One WP Migration', 'uncanny-automator' ),
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

		$file  = isset( $parsed[ $this->get_action_meta() ] ) ? trim( (string) $parsed[ $this->get_action_meta() ] ) : '';
		$label = isset( $parsed[ self::LABEL ] ) ? trim( (string) $parsed[ self::LABEL ] ) : '';

		if ( '' === $file || '' === $label ) {
			$this->add_log_error( esc_html_x( 'A backup file and a label are required.', 'All-in-One WP Migration', 'uncanny-automator' ) );
			return false;
		}

		// set_label() writes straight into the ai1wm_backups_labels option
		// without validating the key, so an arbitrary token value would persist
		// junk into the label map. Same boundary as the delete action.
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

		// set_label() returns update_option()'s result, which is false when the
		// stored value is unchanged. Re-labelling a backup to its current label
		// is a no-op, not a failure, so compare before treating false as an
		// error.
		$labels = $this->item_helpers->get_backup_labels();

		if ( isset( $labels[ $file ] ) && $label === (string) $labels[ $file ] ) {
			$this->hydrate_tokens(
				array(
					'AI1WM_BACKUP_FILENAME' => $file,
					'AI1WM_BACKUP_LABEL'    => $label,
				)
			);
			return true;
		}

		if ( ! Ai1wm_Backups::set_label( $file, $label ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s: the backup filename */
					esc_html_x( 'The label for %s could not be saved.', 'All-in-One WP Migration', 'uncanny-automator' ),
					$file
				)
			);
			return false;
		}

		$this->hydrate_tokens(
			array(
				'AI1WM_BACKUP_FILENAME' => $file,
				'AI1WM_BACKUP_LABEL'    => $label,
			)
		);

		return true;
	}
}
