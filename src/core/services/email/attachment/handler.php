<?php
namespace Uncanny_Automator\Services\Email\Attachment;

use Exception;
use Uncanny_Automator\Services\File\Remote_File_Extension_Identifier;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Handler
 *
 * Handles file attachments for emails.
 *
 * @package UncannyAutomator
 */
class Handler {

	/**
	 * @var string The URL of the file.
	 */
	private $file_url;

	/**
	 * @var string|null The path to the temporary file.
	 */
	private $temp_file_path;

	/**
	 * @var string|null The path to the new file.
	 */
	private $new_file_path;

	/**
	 * The uploads dir.
	 *
	 * @var string
	 */
	protected $uploads_dir = '';

	/**
	 * Constructor.
	 *
	 * @param string $file_url The URL of the file to handle.
	 */
	public function __construct( $file_url ) {

		$this->file_url       = $file_url;
		$this->temp_file_path = null;

		$uploads_dir = wp_get_upload_dir();
		$dir         = trailingslashit( $uploads_dir['path'] ?? '' ) . 'uncanny-automator';

		$this->uploads_dir = $dir;

	}

	/**
	 * Returns the uploads directory.
	 *
	 * @return string
	 */
	public function get_uploads_dir() {
		return trailingslashit( $this->uploads_dir );
	}

	/**
	 * Determines if the file is already downloaded or not.
	 *
	 * @return true
	 */
	public function is_file_downloaded() {
		return file_exists( $this->get_downloaded_file() );
	}

	/**
	 * Returns the path of the downloaded file.
	 *
	 * @return string
	 */
	public function get_downloaded_file() {
		return $this->get_uploads_dir() . basename( $this->file_url );
	}

	/**
	 * Process the file attachment.
	 *
	 * Downloads the file, validates it, and prepares it for attachment.
	 *
	 * @return string|WP_Error The path to the file, or WP_Error on failure.
	 */
	public function process_attachment() {

		if ( $this->temp_file_path && file_exists( $this->temp_file_path ) ) {
			return $this->temp_file_path;
		}

		if ( $this->is_file_downloaded() ) {
			return $this->get_downloaded_file();
		}

		$validator         = new Validator( $this->file_url );
		$validation_result = $validator->validate();

		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		$media_path = $this->get_file_path_from_media();
		// Return the path of the media file if its uploaded in media.
		if ( false !== $media_path ) {
			return $media_path;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$this->temp_file_path = download_url( $this->file_url );

		if ( is_wp_error( $this->temp_file_path ) ) {
			return new WP_Error( 'download_failed', sprintf( 'We were unable to download the file from the provided URL: %s.', esc_url( $this->file_url ) ) );
		}

		if ( ! file_exists( $this->temp_file_path ) ) {
			return new WP_Error( 'file_not_found', 'The downloaded file could not be found. Please check that the URL is correct and ends with a valid file extension, such as .pdf, .png, or .doc, and try again.' );
		}

		return $this->create_readable_file();
	}

	/**
	 * Copy the file from temporary path to an actual path.
	 *
	 * @return string|WP_Error The path to the new file, or WP_Error on failure.
	 */
	private function create_readable_file() {

		$uploads_dir = wp_get_upload_dir();

		if ( empty( $uploads_dir['path'] ) ) {
			return new WP_Error( 'attachment_failed', 'Failed to copy the file to the uploads directory. Please check the file name, format, and server permissions.' );
		}

		$dir = trailingslashit( $uploads_dir['path'] ) . 'uncanny-automator';

		if ( ! is_dir( $dir ) ) {
			mkdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		}

		$this->new_file_path = trailingslashit( $dir ) . basename( $this->file_url );

		if ( is_string( $this->temp_file_path ) && ! empty( $this->temp_file_path ) ) {
			copy( $this->temp_file_path, $this->new_file_path );
			return $this->new_file_path;
		}

		return new WP_Error(
			'attachment_failed',
			'There was a problem attaching the file. Please ensure the uploads folder is writable.'
		);
	}

	/**
	 * Cleans up the file.
	 *
	 * Deletes the temporary and new file paths if they exist.
	 *
	 * @return void
	 */
	public function cleanup() {
		if ( $this->temp_file_path && file_exists( $this->temp_file_path ) ) {
			wp_delete_file( $this->temp_file_path );
			$this->temp_file_path = null;
		}

		if ( $this->new_file_path && file_exists( $this->new_file_path ) ) {
			wp_delete_file( $this->new_file_path );
			$this->new_file_path = null;
		}
	}

	/**
	 * Get the temporary file path.
	 *
	 * @return null|string The temporary file path or null if not set.
	 */
	public function get_temp_file_path() {
		return $this->temp_file_path;
	}

	/**
	 * Get the file extension from a file path.
	 *
	 * This function extracts the file extension from the provided file path.
	 * If no extension is found, it returns false.
	 *
	 * @param string $file_path The absolute path to the file.
	 *
	 * @return string|false The file extension, or false if not found.
	 */
	public function get_file_extension( $file_path ) {
		// Validate the input.
		if ( ! is_string( $file_path ) || empty( $file_path ) ) {
			return false;
		}

		// Use pathinfo to get the extension
		$path_info = pathinfo( $file_path );

		// Return the extension in lowercase if it exists, otherwise return false
		$extension = isset( $path_info['extension'] ) ? strtolower( $path_info['extension'] ) : false;

		if ( ! empty( $extension ) ) {
			$common_ext = apply_filters( 'automator_email_attachment_allowed_file_extensions', automator_get_allowed_attachment_ext(), $file_path );

			if ( ! in_array( $extension, $common_ext, true ) ) {
				return false;
			}
		}

		return $extension;
	}

	/**
	 * Handles the file names. If there is an extension, use that, otherwise try to detect.
	 *
	 * @param string $file_path The file path.
	 *
	 * @return string|false The file path with extension or false on failure.
	 */
	public function handle_file_name( $file_path ) {
		$extension = $this->get_file_extension( $file_path );

		if ( $extension ) {
			return $file_path;
		}

		try {
			$remote_file_ext_identifier = new Remote_File_Extension_Identifier();
			$extension                  = $remote_file_ext_identifier->get_file_extension( $this->file_url );

			if ( $extension ) {
				return $file_path . '.' . $extension;
			}

			return false;
		} catch ( Exception $e ) {
			automator_log( 'Error: cannot identify file extension from url: ' . $e->getMessage(), __CLASS__ );
			return false;
		}
	}

	/**
	 * Returns the url from the field value.
	 *
	 * @param string $field_value The JSON string of the field value.
	 *
	 * @return string
	 */
	public static function get_url_from_field_value( $field_value ) {

		$field_value = (array) json_decode( $field_value, true );

		if ( isset( $field_value[0]['url'] ) && is_string( $field_value[0]['url'] ) ) {
			return $field_value[0]['url'];
		}

		return '';

	}

	/**
	 * Returns the path of the file if its uploaded in media.
	 *
	 * @return string|false
	 */
	public function get_file_path_from_media() {

		// Get the attachment ID from the file URL.
		$attachment_id = attachment_url_to_postid( $this->file_url );

		if ( $attachment_id ) {
			// Get the file path from the attachment ID.
			$file_path = get_attached_file( $attachment_id );

			// Return the file path if it exists.
			if ( $file_path && file_exists( $file_path ) ) {
				return $file_path;
			}
		}

		// Return false if the file is not found in the Media Library
		return false;
	}

}
