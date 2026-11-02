<?php
/**
 * Dropbox App Helpers
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Exception;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\App_Integrations\App_Helpers;

/**
 * Class Dropbox_App_Helpers
 *
 * @property Dropbox_Api_Caller $api
 * @property Dropbox_Webhooks $webhooks
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_App_Helpers extends App_Helpers {

	/**
	 * Folder picker, shared by triggers and actions.
	 *
	 * Lists the root and the account's top-level folders; a deeper folder is
	 * entered as a custom value. Dropbox can't share its root, so the sharing
	 * actions leave it out.
	 *
	 * @param string $option_code  The option code.
	 * @param bool   $include_root Whether the root is a choice.
	 *
	 * @return array
	 */
	public function get_folder_option_config( $option_code, $include_root = true ) {
		return array(
			'option_code'              => $option_code,
			'input_type'               => 'select',
			'label'                    => esc_html_x( 'Folder', 'Dropbox', 'uncanny-automator' ),
			'required'                 => true,
			'options'                  => array(),
			'options_show_id'          => false,
			'supports_custom_value'    => true,
			'custom_value_description' => esc_html_x( 'Folder path (e.g., /Documents)', 'Dropbox', 'uncanny-automator' ),
			'remote_data'              => $this->remote_data_load_config( $include_root ? 'folders' : 'shareable_folders' ),
		);
	}

	/**
	 * File picker. Lists up to 200 files in the account's top level; a deeper
	 * file is entered as a custom value.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array
	 */
	public function get_file_option_config( $option_code ) {
		return array(
			'option_code'              => $option_code,
			'input_type'               => 'select',
			'label'                    => esc_html_x( 'File', 'Dropbox', 'uncanny-automator' ),
			'required'                 => true,
			'options'                  => array(),
			'options_show_id'          => false,
			'supports_custom_value'    => true,
			'custom_value_description' => esc_html_x( 'File path (e.g., /Documents/report.pdf)', 'Dropbox', 'uncanny-automator' ),
			'remote_data'              => $this->remote_data_load_config( 'files' ),
		);
	}

	/**
	 * Item-type filter for the file-or-folder triggers.
	 *
	 * Dropbox reports files and folders on one change feed, so without this a
	 * recipe that creates a folder and then uploads into it runs twice.
	 *
	 * @param string $option_code Option code.
	 *
	 * @return array
	 */
	public function get_item_type_option_config( $option_code ) {
		return array(
			'option_code'   => $option_code,
			'input_type'    => 'select',
			'label'         => esc_html_x( 'Item type', 'Dropbox', 'uncanny-automator' ),
			'required'      => false,
			'default_value' => '-1',
			'options'       => array(
				array(
					'text'  => esc_html_x( 'A file or folder', 'Dropbox', 'uncanny-automator' ),
					'value' => '-1',
				),
				array(
					'text'  => esc_html_x( 'A file', 'Dropbox', 'uncanny-automator' ),
					'value' => 'file',
				),
				array(
					'text'  => esc_html_x( 'A folder', 'Dropbox', 'uncanny-automator' ),
					'value' => 'folder',
				),
			),
		);
	}

	/**
	 * "Include subfolders" toggle for the folder triggers.
	 *
	 * @return array
	 */
	public function get_include_subfolders_option_config() {
		return array(
			'option_code'   => 'INCLUDE_SUBFOLDERS',
			'label'         => esc_html_x( 'Include subfolders', 'Dropbox', 'uncanny-automator' ),
			'input_type'    => 'checkbox',
			'is_toggle'     => true,
			'required'      => false,
			'default_value' => false,
			'description'   => esc_html_x( 'Also trigger for items in subfolders of the selected folder', 'Dropbox', 'uncanny-automator' ),
		);
	}

	/**
	 * Member email field for the add-member actions.
	 *
	 * @return array
	 */
	public function get_member_email_option_config() {
		return array(
			'option_code' => 'MEMBER_EMAIL',
			'label'       => esc_html_x( 'Member email', 'Dropbox', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
			'placeholder' => esc_html_x( 'user@example.com', 'Dropbox', 'uncanny-automator' ),
		);
	}

	/**
	 * Access level field for the add-member actions.
	 *
	 * @return array
	 */
	public function get_access_level_option_config() {
		return array(
			'option_code' => 'ACCESS_LEVEL',
			'label'       => esc_html_x( 'Access level', 'Dropbox', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => array(
				array(
					'value' => 'viewer',
					'text'  => esc_html_x( 'Viewer (can view and comment)', 'Dropbox', 'uncanny-automator' ),
				),
				array(
					'value' => 'editor',
					'text'  => esc_html_x( 'Editor (can edit)', 'Dropbox', 'uncanny-automator' ),
				),
			),
			'description' => esc_html_x( 'Permission level for the member', 'Dropbox', 'uncanny-automator' ),
		);
	}

	/**
	 * Whether the site has turned Dropbox triggers on ("Enable triggers" in
	 * the Dropbox settings). Read like App_Webhooks reads it: the switch is
	 * stored as 'on'/'off' as well as booleans.
	 *
	 * @return bool
	 */
	public function triggers_enabled() {
		return filter_var( automator_get_option( 'enable_dropbox_webhooks', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * What a recipe holding a Dropbox trigger is told while triggers are off
	 * (decision A4, 29 Oct).
	 *
	 * @return string
	 */
	public function triggers_off_message(): string {
		return esc_html_x( 'Dropbox triggers are turned off on this site, so this trigger never runs. Turn on Enable triggers on the Dropbox settings page.', 'Dropbox', 'uncanny-automator' );
	}

	/**
	 * Folder picker options: the root, then the top-level folders. The root's
	 * value is '/', because the picker drops an empty value.
	 *
	 * @param \Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_folders( $request ): array {
		try {
			$root = array(
				'text'  => esc_html_x( '/ (Root)', 'Dropbox', 'uncanny-automator' ),
				'value' => '/',
			);

			return $this->remote_data_success( array_merge( array( $root ), $this->api->get_folders( $request->is_refresh() ) ) );
		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Folder picker options for the sharing actions: the top-level folders,
	 * without the root.
	 *
	 * @param \Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_shareable_folders( $request ): array {
		try {
			return $this->remote_data_success( $this->api->get_folders( $request->is_refresh() ) );
		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * File picker options.
	 *
	 * @param \Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_files( $request ): array {
		try {
			return $this->remote_data_success( $this->api->get_files( $request->is_refresh() ) );
		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Get a folder path from parsed action data.
	 *
	 * @param array  $parsed   Parsed action data.
	 * @param string $meta_key Meta key for the folder.
	 *
	 * @return string
	 * @throws Action_Error_Exception If the folder is missing.
	 */
	public function get_folder_from_parsed( $parsed, $meta_key ) {
		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw Action_Error_Exception::validation_failed(
				esc_html_x( 'Folder is required.', 'Dropbox', 'uncanny-automator' )
			);
		}
		return $this->normalize_path( $parsed[ $meta_key ] );
	}

	/**
	 * Get the folder a sharing action shares from parsed action data. The
	 * root, which Dropbox can't share, fails here with a message the site
	 * owner can act on.
	 *
	 * @param array  $parsed   Parsed action data.
	 * @param string $meta_key Meta key for the folder.
	 *
	 * @return string
	 * @throws Action_Error_Exception If the folder is missing or is the root.
	 */
	public function get_shareable_folder_from_parsed( $parsed, $meta_key ) {
		$path = $this->normalize_path( $parsed[ $meta_key ] ?? '' );

		if ( '' === $path ) {
			throw Action_Error_Exception::validation_failed(
				esc_html_x( 'Select a folder to share. Dropbox does not share the root folder.', 'Dropbox', 'uncanny-automator' )
			);
		}

		return $path;
	}

	/**
	 * Normalize a Dropbox path: leading slash, no trailing slash, and the
	 * empty string for the root (Dropbox's own spelling of it).
	 *
	 * @param string $path The path.
	 *
	 * @return string
	 */
	public function normalize_path( $path ) {
		$path = trim( (string) $path );

		if ( '' === $path || '/' === $path ) {
			return '';
		}

		if ( '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . $path;
		}

		return rtrim( $path, '/' );
	}

	/**
	 * Whether an event in `$event_folder` belongs to the folder a trigger
	 * monitors.
	 *
	 * The folder pill may hold a custom value, which a trigger's meta keeps
	 * under `FOLDER_PATH_custom`. Dropbox paths ignore case, and the picker
	 * stores the lowercase path while events carry the displayed one, so the
	 * comparison ignores case too. An empty selection matches nothing.
	 *
	 * @param array  $meta         The trigger's meta.
	 * @param string $event_folder The folder the event happened in.
	 *
	 * @return bool
	 */
	public function trigger_folder_matches( $meta, $event_folder ) {
		$monitored = (string) ( $meta['FOLDER_PATH'] ?? '' );

		if ( 'automator_custom_value' === $monitored ) {
			$monitored = (string) ( $meta['FOLDER_PATH_custom'] ?? '' );
		}

		if ( '' === trim( $monitored ) ) {
			return false;
		}

		$monitored    = $this->path_key( $monitored );
		$event_folder = $this->path_key( $event_folder );

		if ( $monitored === $event_folder ) {
			return true;
		}

		$include_subfolders = 'true' === (string) ( $meta['INCLUDE_SUBFOLDERS'] ?? '' );

		return $include_subfolders && 0 === strpos( $event_folder . '/', $monitored . '/' );
	}

	/**
	 * Whether an event's item matches the trigger's item-type filter.
	 *
	 * "Any" and an empty selection both match everything. An entry with no
	 * `.tag` can't be proven to match a specific choice, so it doesn't.
	 *
	 * @param string $selected Stored ITEM_TYPE meta ('', '-1', 'file', 'folder').
	 * @param array  $entry    The Dropbox entry from the event.
	 *
	 * @return bool
	 */
	public function item_type_matches( $selected, $entry ) {
		$selected = (string) $selected;

		if ( '' === $selected || '-1' === $selected ) {
			return true;
		}

		$tag = is_array( $entry ) ? (string) ( $entry['.tag'] ?? '' ) : '';

		return '' !== $tag && $selected === $tag;
	}

	/**
	 * A normalized, lowercase path for comparison.
	 *
	 * @param string $path The path.
	 *
	 * @return string
	 */
	private function path_key( $path ) {
		$path = $this->normalize_path( $path );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $path ) : strtolower( $path );
	}
}
