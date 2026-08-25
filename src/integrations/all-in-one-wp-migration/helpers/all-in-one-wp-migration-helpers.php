<?php

namespace Uncanny_Automator\Integrations\All_In_One_Wp_Migration;

use Ai1wm_Backups;
use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class All_In_One_Wp_Migration_Helpers
 *
 * Shared option builders and token hydrators for the All-in-One WP Migration
 * integration. Backups are files on disk, not database records — there is no
 * post type or custom table — so the backup-file picker is built from
 * `Ai1wm_Backups::get_files()` and exposed through the unified remote_data REST
 * framework.
 *
 * @package Uncanny_Automator\Integrations\All_In_One_Wp_Migration
 */
class All_In_One_Wp_Migration_Helpers extends Abstract_Helpers {

	/**
	 * Re-entrancy guard. Set true while this integration's "Delete a backup
	 * file" ACTION is calling `Ai1wm_Backups::delete_file()` — that method fires
	 * `ai1wm_status_backup_deleted`, the hook the "A site backup is deleted"
	 * TRIGGER listens on. The trigger bails while this is set, so an Automator
	 * delete action never re-fires the Automator trigger (preventing recipe
	 * loops). Deletions from the plugin's own Backups screen leave it false and
	 * still fire the trigger normally.
	 *
	 * @var bool
	 */
	public static $is_deleting_via_action = false;

	/**
	 * Reset the re-entrancy guard. Exposed for tests.
	 *
	 * @return void
	 */
	public static function reset_deleting_guard() {
		self::$is_deleting_via_action = false;
	}

	// =========================================================================
	// Remote_Data handlers — backup-file picker for the recipe builder.
	//
	// Route: POST /wp-json/uap/v2/remote-data/all_in_one_wp_migration/{segment}
	// Reached via $this->{$method}() from Abstract_Helpers' dispatcher;
	// visibility is `protected` to keep the REST-reachable surface explicit.
	// =========================================================================

	/**
	 * Backup-file picker for actions. `_strict` — no "Any" sentinel, because a
	 * destructive/mutating action must target one specific file. The field also
	 * accepts a token for a dynamic filename.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_backup_files_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->build_backup_file_options() );
	}

	/**
	 * Build backup-file options from the backups directory.
	 *
	 * `get_files()` returns newest-first and each entry carries the subpath-aware
	 * `filename`, which is exactly what the `Ai1wm_Backups::*` file methods
	 * expect. The label appends the human-readable size so two backups taken on
	 * the same day are distinguishable in the dropdown.
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	private function build_backup_file_options() {

		$options = array();

		foreach ( $this->get_backup_files() as $backup ) {

			$filename = isset( $backup['filename'] ) ? (string) $backup['filename'] : '';

			if ( '' === $filename ) {
				continue;
			}

			$options[] = array(
				'text'  => $this->format_backup_label( $filename, $backup ),
				'value' => $filename,
			);
		}

		return $options;
	}

	/**
	 * Backup files currently on disk, or an empty array when the plugin is not
	 * loaded.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_backup_files() {

		if ( ! class_exists( '\Ai1wm_Backups' ) ) {
			return array();
		}

		$backups = Ai1wm_Backups::get_files();

		return is_array( $backups ) ? $backups : array();
	}

	/**
	 * Dropdown label for a backup: its user-assigned label when one exists,
	 * otherwise the filename, with the size appended when known.
	 *
	 * The size is separated with a plain ASCII hyphen, the separator every other
	 * option-label builder in the codebase uses — not an em dash.
	 *
	 * @param string $filename
	 * @param array  $backup   Entry from Ai1wm_Backups::get_files().
	 *
	 * @return string
	 */
	private function format_backup_label( $filename, $backup ) {

		$labels = $this->get_backup_labels();
		$label  = isset( $labels[ $filename ] ) ? (string) $labels[ $filename ] : '';
		$text   = '' !== $label ? $label . ' (' . $filename . ')' : $filename;
		$size   = isset( $backup['size'] ) ? $backup['size'] : null;

		if ( null === $size ) {
			return $text;
		}

		return $text . ' - ' . size_format( (int) $size );
	}

	/**
	 * Whether a filename is safe to hand to the `Ai1wm_Backups::*` file methods.
	 *
	 * The backup-file field is a `select` fed by remote_data, but Automator
	 * fields also accept tokens, so at runtime the value is not guaranteed to
	 * be one of the options we offered — this is the trust boundary.
	 *
	 * `basename()` is deliberately NOT used: `Ai1wm_Backups::get_files()`
	 * returns `getSubPathname()` from a recursive iterator, so a legitimate
	 * value can carry a subdirectory (`daily/site.wpress`), and `basename()`
	 * would silently rewrite it to a different file. The correct boundary is
	 * the plugin's own — `ai1wm_validate_file()`, which rejects the illegal
	 * character set and then defers to core's `validate_file()` for `..`
	 * traversal and Windows drive prefixes. `ai1wm_backup_path()` applies it
	 * too, but by throwing `Ai1wm_Archive_Exception`; checking here turns that
	 * into an explicit, readable action log instead.
	 *
	 * @param string $file Filename, possibly with a subpath under the backups directory.
	 *
	 * @return bool
	 */
	public function is_safe_backup_filename( $file ) {

		$file = trim( (string) $file );

		if ( '' === $file ) {
			return false;
		}

		if ( function_exists( 'ai1wm_validate_file' ) ) {
			return 0 === ai1wm_validate_file( $file );
		}

		return 0 === validate_file( $file );
	}

	/**
	 * All backup labels keyed by filename, or an empty array when the plugin is
	 * not loaded.
	 *
	 * @return array<string,string>
	 */
	public function get_backup_labels() {

		if ( ! class_exists( '\Ai1wm_Backups' ) ) {
			return array();
		}

		$labels = Ai1wm_Backups::get_labels();

		return is_array( $labels ) ? $labels : array();
	}

	// =========================================================================
	// Token definitions and hydration.
	//
	// Hydrators always return the full keyset — a recipe must never see a
	// partial token map when the plugin hands us an incomplete payload.
	// =========================================================================

	/**
	 * Token definitions describing a completed backup (trigger format).
	 *
	 * @return array<int,array<string,string>>
	 */
	public function backup_token_definitions() {
		return array(
			$this->token_def( 'AI1WM_BACKUP_FILENAME', esc_html_x( 'Backup filename', 'All-in-One WP Migration', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'AI1WM_BACKUP_SIZE', esc_html_x( 'Backup size', 'All-in-One WP Migration', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'AI1WM_BACKUP_BYTES', esc_html_x( 'Backup size in bytes', 'All-in-One WP Migration', 'uncanny-automator' ), 'int' ),
			$this->token_def( 'AI1WM_BACKUP_URL', esc_html_x( 'Backup download URL', 'All-in-One WP Migration', 'uncanny-automator' ), 'url' ),
			$this->token_def( 'AI1WM_BACKUP_PATH', esc_html_x( 'Backup file path', 'All-in-One WP Migration', 'uncanny-automator' ), 'text' ),
		);
	}

	/**
	 * Hydrate the backup tokens from an export `$params` array.
	 *
	 * The archive is renamed into the backups directory before
	 * `ai1wm_status_export_done` fires, so the size/URL/path getters must be the
	 * `ai1wm_backup_*` variants — the `ai1wm_archive_*` ones point at the
	 * already-removed temp file.
	 *
	 * @param array $params Export parameters.
	 *
	 * @return array<string,mixed>
	 */
	public function hydrate_backup_tokens( $params ) {

		$empty = array(
			'AI1WM_BACKUP_FILENAME' => '',
			'AI1WM_BACKUP_SIZE'     => '',
			// Declared as an int token, so the unknown value is 0 — not ''.
			'AI1WM_BACKUP_BYTES'    => 0,
			'AI1WM_BACKUP_URL'      => '',
			'AI1WM_BACKUP_PATH'     => '',
		);

		if ( ! is_array( $params ) || empty( $params['archive'] ) ) {
			return $empty;
		}

		$path = function_exists( 'ai1wm_backup_path' ) ? (string) ai1wm_backup_path( $params ) : '';

		// Size getters call filesize() unguarded; a missing file would emit a
		// PHP warning and return false, so resolve the size only once the file
		// is confirmed present.
		$exists = '' !== $path && file_exists( $path );

		return array(
			'AI1WM_BACKUP_FILENAME' => function_exists( 'ai1wm_archive_name' ) ? (string) ai1wm_archive_name( $params ) : (string) $params['archive'],
			'AI1WM_BACKUP_SIZE'     => $exists && function_exists( 'ai1wm_backup_size' ) ? (string) ai1wm_backup_size( $params ) : '',
			'AI1WM_BACKUP_BYTES'    => $exists && function_exists( 'ai1wm_backup_bytes' ) ? (int) ai1wm_backup_bytes( $params ) : 0,
			'AI1WM_BACKUP_URL'      => function_exists( 'ai1wm_backup_url' ) ? (string) ai1wm_backup_url( $params ) : '',
			'AI1WM_BACKUP_PATH'     => $path,
		);
	}

	/**
	 * Token definitions describing a failed backup (trigger format).
	 *
	 * @return array<int,array<string,string>>
	 */
	public function backup_failure_token_definitions() {
		return array(
			$this->token_def( 'AI1WM_BACKUP_FILENAME', esc_html_x( 'Backup filename', 'All-in-One WP Migration', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'AI1WM_ERROR_MESSAGE', esc_html_x( 'Error message', 'All-in-One WP Migration', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'AI1WM_ERROR_CODE', esc_html_x( 'Error code', 'All-in-One WP Migration', 'uncanny-automator' ), 'text' ),
		);
	}

	/**
	 * Hydrate the failure tokens from the export `$params` and the thrown
	 * exception.
	 *
	 * The filename is read straight off `$params` rather than through
	 * `ai1wm_backup_path()` — on a failed export the archive was never renamed
	 * into the backups directory, so nothing on disk is guaranteed.
	 *
	 * @param array           $params    Export parameters at time of failure.
	 * @param \Throwable|null $exception The thrown exception.
	 *
	 * @return array<string,mixed>
	 */
	public function hydrate_backup_failure_tokens( $params, $exception ) {

		$filename = '';

		if ( is_array( $params ) && ! empty( $params['archive'] ) ) {
			$filename = basename( (string) $params['archive'] );
		}

		return array(
			'AI1WM_BACKUP_FILENAME' => $filename,
			'AI1WM_ERROR_MESSAGE'   => $exception instanceof \Throwable ? $exception->getMessage() : '',
			'AI1WM_ERROR_CODE'      => $exception instanceof \Throwable ? (string) $exception->getCode() : '',
		);
	}

	/**
	 * Build a single trigger token definition.
	 *
	 * @param string $id
	 * @param string $name
	 * @param string $type
	 *
	 * @return array<string,string>
	 */
	private function token_def( $id, $name, $type ) {
		return array(
			'tokenId'   => $id,
			'tokenName' => $name,
			'tokenType' => $type,
		);
	}
}
