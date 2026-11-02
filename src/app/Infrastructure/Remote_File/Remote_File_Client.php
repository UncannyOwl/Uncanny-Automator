<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Remote_File;

use Uncanny_Automator\App\Infrastructure\Http\Non_Public_Ranges;
use WP_Error;

/**
 * Checks and downloads a file at a URL a recipe supplies.
 *
 * It applies the rules the Dropbox "Upload a file" pre-flight applies to a
 * remote file ({@see \Uncanny_Automator\Recipe\App_Action::prepare_upload_request()}):
 *
 *  - An address that is, or resolves to, a range that is not public (private,
 *    loopback, link-local, carrier-grade NAT, multicast, documentation,
 *    benchmarking or reserved, IPv4 and IPv6) is refused before anything is
 *    requested ({@see Non_Public_Ranges}).
 *  - Every request goes through WordPress's safe HTTP API, which checks the URL
 *    again when the request is made.
 *  - No request follows a redirect. A redirect comes back as its 3xx status, so
 *    a public URL cannot hand the request on to an address the check refused.
 *  - The check is one HEAD request; the download is one GET.
 *
 * @since 8.0.0
 */
final class Remote_File_Client {

	/**
	 * Error code: the URL's address is in a range that is not public.
	 *
	 * @var string
	 */
	public const PRIVATE_ADDRESS = 'automator_remote_file_private_address';

	/**
	 * Error code: the server answered the download with something other than the file.
	 *
	 * @var string
	 */
	public const NOT_DOWNLOADED = 'automator_remote_file_not_downloaded';

	/**
	 * Ask the server about the file with one HEAD request.
	 *
	 * @param string $url     The file's URL.
	 * @param int    $timeout Seconds to wait for the answer.
	 *
	 * @return Remote_File_Head|WP_Error The answer, or why there is none: the
	 *                                   address is refused ({@see self::PRIVATE_ADDRESS})
	 *                                   or the request failed (WordPress's error).
	 */
	public function head( string $url, int $timeout ) {

		$refusal = $this->refuse_private_address( $url );

		if ( null !== $refusal ) {
			return $refusal;
		}

		$response = wp_safe_remote_head(
			$url,
			array(
				'timeout'     => $timeout,
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new Remote_File_Head(
			(int) wp_remote_retrieve_response_code( $response ),
			self::header( $response, 'content-length' ),
			self::header( $response, 'content-type' )
		);
	}

	/**
	 * Download the file to a temporary file with one GET request.
	 *
	 * Only a 200 answer is the file. Any other answer, a redirect included, is
	 * refused and its temporary file deleted. A Content-MD5 header, when the
	 * server sends one, must match the bytes received, as download_url() requires.
	 *
	 * @param string $url     The file's URL.
	 * @param int    $timeout Seconds to wait for the whole download.
	 *
	 * @return Remote_File_Download|WP_Error The temporary file, which the caller
	 *                                       deletes, with the media type the server
	 *                                       sent; or why there is none: the address
	 *                                       is refused ({@see self::PRIVATE_ADDRESS}),
	 *                                       the answer was not the file
	 *                                       ({@see self::NOT_DOWNLOADED}), the bytes
	 *                                       do not match Content-MD5, or the request
	 *                                       failed.
	 */
	public function download( string $url, int $timeout ) {

		$refusal = $this->refuse_private_address( $url );

		if ( null !== $refusal ) {
			return $refusal;
		}

		if ( ! function_exists( 'wp_tempnam' ) || ! function_exists( 'verify_file_md5' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$file = wp_tempnam( self::file_name( $url ) );

		if ( ! $file || ! is_file( $file ) ) {
			return new WP_Error( 'http_no_file', esc_html_x( 'Could not create temporary file.', 'Remote file download', 'uncanny-automator' ) );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => $timeout,
				'redirection' => 0,
				'stream'      => true,
				'filename'    => $file,
			)
		);

		$refusal = $this->refuse_answer( $response, $file );

		if ( null !== $refusal ) {
			wp_delete_file( $file );
			return $refusal;
		}

		return new Remote_File_Download( $file, self::header( $response, 'content-type' ) );
	}

	/**
	 * Refuse a URL whose address is in a range that is not public.
	 *
	 * @param string $url The file's URL.
	 *
	 * @return WP_Error|null The refusal, or null when the address is public.
	 */
	private function refuse_private_address( string $url ): ?WP_Error {

		if ( ! Non_Public_Ranges::url_resolves_into( $url ) ) {
			return null;
		}

		return new WP_Error(
			self::PRIVATE_ADDRESS,
			esc_html__( 'The URL resolves to a private or reserved IP address and cannot be used.', 'uncanny-automator' )
		);
	}

	/**
	 * Refuse a download answer that is not the file.
	 *
	 * @param array|WP_Error $response The GET response.
	 * @param string         $file     The temporary file the body was written to.
	 *
	 * @return WP_Error|null The refusal, or null when the file arrived whole.
	 */
	private function refuse_answer( $response, string $file ): ?WP_Error {

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			return new WP_Error(
				self::NOT_DOWNLOADED,
				sprintf(
					/* translators: %d: HTTP status code */
					esc_html__( 'The server answered with HTTP status %d instead of the file.', 'uncanny-automator' ),
					$status
				),
				array( 'status' => $status )
			);
		}

		$content_md5 = self::header( $response, 'content-md5' );

		if ( '' === $content_md5 ) {
			return null;
		}

		$md5_check = verify_file_md5( $file, $content_md5 );

		return is_wp_error( $md5_check ) ? $md5_check : null;
	}

	/**
	 * A header's value as one string, the way WordPress's transport joins a repeated header.
	 *
	 * @param array|mixed $response The HTTP response; anything else has no headers.
	 * @param string      $name     The header name.
	 *
	 * @return string Empty when the header is absent.
	 */
	private static function header( $response, string $name ): string {

		$value = wp_remote_retrieve_header( $response, $name );

		return is_array( $value ) ? implode( ',', $value ) : (string) $value;
	}

	/**
	 * The name the temporary file is based on: the last segment of the URL's path.
	 *
	 * @param string $url The file's URL.
	 *
	 * @return string Empty when the URL has no path; wp_tempnam() then picks a name.
	 */
	private static function file_name( string $url ): string {

		$path = wp_parse_url( $url, PHP_URL_PATH );

		return is_string( $path ) && '' !== $path ? basename( $path ) : '';
	}
}
