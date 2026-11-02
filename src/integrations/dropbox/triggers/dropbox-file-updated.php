<?php
/**
 * A file is updated in a Dropbox folder
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Uncanny_Automator\Recipe\App_Trigger;

/**
 * Class Dropbox_File_Updated
 *
 * New content in an existing file: an edit, or an upload that overwrites it
 * with different bytes. A move or rename alone is not an update. Folders have
 * no content, so they are never "updated".
 *
 * @property Dropbox_App_Helpers $helpers
 * @property Dropbox_Api_Caller $api
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_File_Updated extends App_Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'DROPBOX_FILE_UPDATED', 'DROPBOX' )
			->trigger_meta( 'FOLDER_PATH' )
			->trigger_type( 'anonymous' )
			->hook( 'automator_dropbox_file_updated', 10, 3 );
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
				esc_html_x( 'A file is updated in {{a folder:%1$s}}', 'Dropbox', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'A file is updated in {{a folder}}', 'Dropbox', 'uncanny-automator' ) );
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
		return array_merge( Dropbox_Item_Tokens::define_item_tokens(), $tokens );
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
	 * Hydrate tokens.
	 *
	 * @param array $completed_trigger Completed trigger data.
	 * @param array $hook_args         ( $entry, $folder_path, $previous_path ).
	 *
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		return Dropbox_Item_Tokens::hydrate_item_tokens( $hook_args[0], $hook_args[1] ?? '' );
	}
}
