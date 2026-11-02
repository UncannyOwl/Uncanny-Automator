<?php
/**
 * A file or folder is deleted in a Dropbox folder
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Uncanny_Automator\Recipe\App_Trigger;

/**
 * Class Dropbox_File_Folder_Deleted
 *
 * A rename or move is not a deletion. No item-type pill: Dropbox describes a
 * deletion with DeletedMetadata, which says neither whether the item was a
 * file or a folder nor what its id was.
 *
 * @property Dropbox_App_Helpers $helpers
 * @property Dropbox_Api_Caller $api
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_File_Folder_Deleted extends App_Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'DROPBOX_FILE_FOLDER_DELETED', 'DROPBOX' )
			->trigger_meta( 'FOLDER_PATH' )
			->trigger_type( 'anonymous' )
			->hook( 'automator_dropbox_item_deleted', 10, 3 );
	}

	/**
	 * Setup the trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_uses_api( true );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Folder path */
				esc_html_x( 'A file or folder is deleted in {{a folder:%1$s}}', 'Dropbox', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'A file or folder is deleted in {{a folder}}', 'Dropbox', 'uncanny-automator' ) );
	}

	/**
	 * Show only once the site has enabled Dropbox triggers.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return $this->helpers->triggers_enabled();
	}

	/**
	 * Why a recipe holding this trigger never runs while Dropbox triggers are off.
	 *
	 * @return string
	 */
	public function requirements_unmet_message(): string {
		return $this->helpers->triggers_off_message();
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_folder_option_config( $this->get_trigger_meta() ),
			$this->helpers->get_include_subfolders_option_config(),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger Trigger data.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$dropbox_tokens = array(
			array(
				'tokenId'   => 'FILE_NAME',
				'tokenName' => esc_html_x( 'File/Folder name', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FILE_PATH',
				'tokenName' => esc_html_x( 'File/Folder path', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DELETED_AT',
				'tokenName' => esc_html_x( 'Date deletion detected', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PARENT_FOLDER',
				'tokenName' => esc_html_x( 'Parent folder path', 'Dropbox', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return array_merge( $dropbox_tokens, $tokens );
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger   Trigger data.
	 * @param array $hook_args ( $entry, $folder_path, $previous_path ).
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! is_array( $hook_args[0] ?? null ) ) {
			return false;
		}

		return $this->helpers->trigger_folder_matches( $trigger['meta'], (string) ( $hook_args[1] ?? '' ) );
	}

	/**
	 * Hydrate tokens. DeletedMetadata carries no timestamp, so the deletion
	 * date is when the site learned of it.
	 *
	 * @param array $completed_trigger Completed trigger data.
	 * @param array $hook_args         ( $entry, $folder_path, $previous_path ).
	 *
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		$entry = $hook_args[0];

		return array(
			'FILE_NAME'     => (string) ( $entry['name'] ?? '' ),
			'FILE_PATH'     => (string) ( $entry['path_display'] ?? '' ),
			'DELETED_AT'    => current_time( 'mysql' ),
			'PARENT_FOLDER' => (string) ( $hook_args[1] ?? '' ),
		);
	}
}
