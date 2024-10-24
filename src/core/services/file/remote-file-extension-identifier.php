<?php
namespace Uncanny_Automator\Services\File;

use Exception;

/**
 * Class Remote_File_Extension_Identifier
 *
 * A class to detect the file extension from a given URL.
 */
class Remote_File_Extension_Identifier {

	/**
	 * Get the file extension from a URL.
	 *
	 * This method attempts to extract the file extension from the provided URL.
	 * If the extension is not present, it tries to detect it using the Content-Type header.
	 *
	 * @param string $url The URL of the file.
	 *
	 * @throws Exception
	 *
	 * @return string The file extension, or an empty string if not found.
	 */
	public function get_file_extension( $url ) {

		// Validate the input.
		if ( ! is_string( $url ) || empty( $url ) ) {
			throw new Exception( 'URL is invalid or empty', 400 );
		}

		// Use pathinfo to get the extension.
		$path_info = pathinfo( wp_parse_url( $url, PHP_URL_PATH ) );

		// Check if an extension is available.
		if ( isset( $path_info['extension'] ) ) {
			return strtolower( $path_info['extension'] );
		}

		// Attempt to determine the extension from the Content-Type header
		$content_type = $this->get_content_type( $url );

		if ( $content_type ) {
			return $this->map_content_type_to_extension( $content_type );
		}

		throw new Exception( 'Cannot determine file type', 400 );

	}

	/**
	 * Get the Content-Type of a URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return string|null The Content-Type, or null if not found.
	 */
	private function get_content_type( $url ) {

		$headers = get_headers( $url, 1 );

		return isset( $headers['Content-Type'] ) ? $headers['Content-Type'] : null;

	}

	/**
	 * Map a Content-Type to a file extension.
	 *
	 * @param string $content_type The Content-Type.
	 *
	 * @return string The corresponding file extension, or an empty string if not found.
	 */
	private function map_content_type_to_extension( $content_type ) {

		$mime_types = array(
			'text/plain'                              => 'txt',   // Plain text file
			'application/pdf'                         => 'pdf',   // Portable Document Format
			'application/msword'                      => 'doc',   // Microsoft Word document
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',  // Microsoft Word document (XML-based)
			'application/vnd.ms-excel'                => 'xls',   // Microsoft Excel spreadsheet
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',  // Microsoft Excel spreadsheet (XML-based)
			'application/vnd.ms-powerpoint'           => 'ppt',   // Microsoft PowerPoint presentation
			'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx', // Microsoft PowerPoint presentation (XML-based)
			'application/vnd.oasis.opendocument.text' => 'odt',   // OpenDocument text document
			'application/vnd.oasis.opendocument.spreadsheet' => 'ods',   // OpenDocument spreadsheet
			'image/jpeg'                              => 'jpg',   // JPEG image
			'image/jpeg'                              => 'jpeg',  // JPEG image
			'image/png'                               => 'png',   // Portable Network Graphics
			'image/gif'                               => 'gif',   // Graphics Interchange Format
			'image/bmp'                               => 'bmp',   // Bitmap image
			'image/tiff'                              => 'tiff',  // Tagged Image File Format
			'application/zip'                         => 'zip',   // Compressed archive
			'application/x-rar-compressed'            => 'rar',   // Compressed archive
			'application/x-7z-compressed'             => '7z',    // 7-Zip compressed archive
			'audio/mpeg'                              => 'mp3',   // MP3 audio file
			'audio/wav'                               => 'wav',   // WAV audio file
			'video/mp4'                               => 'mp4',   // MP4 video file
			'video/x-msvideo'                         => 'avi',   // AVI video file
			'video/quicktime'                         => 'mov',   // QuickTime video file
			'text/csv'                                => 'csv',   // Comma-separated values
			'application/rtf'                         => 'rtf',   // Rich Text Format
			'application/x-apple-diskimage'           => 'dmg',   // macOS Disk Image
			'application/vnd.apple.installer+xml'     => 'pkg',   // macOS Installer Package
			'application/vnd.apple.pages'             => 'pages', // Apple Pages document
			'application/vnd.apple.numbers'           => 'numbers', // Apple Numbers spreadsheet
			'application/vnd.apple.keynote'           => 'key',   // Apple Keynote presentation
		);

		// Allow plugin users to identify other file extensions.
		$mime_types = apply_filters( 'automator_email_remote_file_extensions_identifier', $mime_types, $content_type );

		return isset( $mime_types[ $content_type ] ) ? $mime_types[ $content_type ] : '';

	}

}
