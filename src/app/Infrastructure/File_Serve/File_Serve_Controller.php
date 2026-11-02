<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\File_Serve;

use Throwable;
use Uncanny_Automator\App\Infrastructure\Remote_File\Remote_File_Client;
use WP_Error;

/**
 * Listens for `?automator_serve=1` requests and streams source bytes
 * back to the platform after verifying the signed URL.
 *
 * HEAD branch emits headers and zero body — used by the platform to
 * verify size + MIME before issuing the GET. GET branch emits the same
 * headers and streams the body in 64 KB chunks via `fopen` + `fread` +
 * `flush()`. Both branches set `Content-Encoding: identity` and
 * `Cache-Control: no-transform` and call `header_remove('Content-Encoding')`
 * + `apache_setenv('no-gzip', '1')` (when available) as belt-and-braces
 * against PHP/web-server compression that would break the platform's
 * `source_size_bytes == Content-Length` invariant.
 *
 * @since 8.0.0
 */
final class File_Serve_Controller {

	/**
	 * Streaming chunk size in bytes (64 KB).
	 */
	private const CHUNK_BYTES = 65536;

	/**
	 * HEAD/GET timeout for proxied external fetches (seconds).
	 */
	private const PROXY_TIMEOUT = 30;

	/**
	 * @var Signed_Url
	 */
	private $signer;

	/**
	 * @var Allowed_Path_Policy
	 */
	private $policy;

	/**
	 * Fetches an external URL: address check first, no redirects.
	 *
	 * @var Remote_File_Client
	 */
	private $client;

	/**
	 * @param Signed_Url              $signer Signed-URL verifier for the request.
	 * @param Allowed_Path_Policy     $policy Allow-list gate for the served path/URL.
	 * @param Remote_File_Client|null $client Fetches an external URL; the shared client when omitted.
	 */
	public function __construct( Signed_Url $signer, Allowed_Path_Policy $policy, ?Remote_File_Client $client = null ) {

		$this->signer = $signer;
		$this->policy = $policy;
		$this->client = $client ?? new Remote_File_Client();
	}

	/**
	 * Hook the request handler on `init` priority 1 so we run before
	 * WP loads the request layer (rewrite, query parsing). The query
	 * parameter `automator_serve=1` is the on-switch — anything else
	 * short-circuits cheaply.
	 *
	 * @return void
	 */
	public function register_hooks(): void {

		add_action( 'init', array( $this, 'handle_request' ), 1 );
	}

	/**
	 * Entry point. Validates, dispatches by method, exits cleanly.
	 *
	 * @return void
	 */
	public function handle_request(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed URL is the auth surface
		if ( empty( $_GET['automator_serve'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed URL is the auth surface
		$query = wp_unslash( $_GET );
		if ( ! is_array( $query ) ) {
			$query = array();
		}

		// `p` is URL-encoded on the wire but the canonical string uses
		// the RAW value. WordPress already url-decodes $_GET, so $query['p']
		// is raw — exactly what verify() needs.
		$verified = $this->signer->verify( $query );
		if ( $verified instanceof WP_Error ) {
			$this->emit_error( $verified );
			return;
		}

		$p = isset( $query['p'] ) ? (string) $query['p'] : '';
		if ( ! $this->policy->is_allowed( $p ) ) {
			$this->emit_error(
				new WP_Error(
					'forbidden_path',
					'Requested path is not on the allow-list.',
					array( 'status' => 404 )
				)
			);
			return;
		}

		$method = $this->detect_method();
		if ( 'HEAD' === $method ) {
			$this->handle_head( $p );
			return;
		}
		if ( 'GET' === $method ) {
			$this->handle_get( $p );
			return;
		}

		$this->emit_error(
			new WP_Error(
				'method_not_allowed',
				'Only GET and HEAD are accepted.',
				array( 'status' => 405 )
			)
		);
	}

	/**
	 * HEAD branch — headers only, zero body bytes.
	 *
	 * @param string $p Verified, allow-listed path or URL.
	 *
	 * @return void
	 */
	private function handle_head( string $p ): void {

		$resolved = $this->resolve_local_path( $p );
		if ( null !== $resolved ) {
			$size = $this->safe_filesize( $resolved );
			if ( null === $size ) {
				$this->emit_error(
					new WP_Error( 'not_found', 'File not found on disk.', array( 'status' => 404 ) )
				);
				return;
			}
			$this->emit_streaming_headers( $this->resolve_local_mime( $resolved, $p ), $size );
			$this->terminate();
			return;
		}

		// External URL — proxy the HEAD upstream.
		$probed = $this->probe_external_url( $p );
		if ( is_wp_error( $probed ) ) {
			$this->emit_error( $probed );
			return;
		}
		$this->emit_streaming_headers( $probed['mime'], $probed['size'] );
		$this->terminate();
	}

	/**
	 * GET branch — headers + body.
	 *
	 * Local files stream chunked from disk; external URLs are downloaded
	 * synchronously to a temp file and then `readfile()`d in one go. The
	 * download-then-readfile flow exists because upstream hotlink-reject
	 * responses (or any mid-stream upstream failure) used to surface as a
	 * Cloudflare 520 wrapping a half-finished response: emitting a 200
	 * with `Content-Length: N` and then sending fewer than N bytes makes
	 * Cloudflare drop the connection. Downloading first lets the plugin
	 * convert upstream failures into a clean 502 BEFORE any response body
	 * is committed to the wire.
	 *
	 * @param string $p Verified, allow-listed path or URL.
	 *
	 * @return void
	 */
	private function handle_get( string $p ): void {

		$resolved = $this->resolve_local_path( $p );
		if ( null !== $resolved ) {
			$size = $this->safe_filesize( $resolved );
			if ( null === $size ) {
				$this->emit_error(
					new WP_Error( 'not_found', 'File not found on disk.', array( 'status' => 404 ) )
				);
				return;
			}
			$this->emit_streaming_headers( $this->resolve_local_mime( $resolved, $p ), $size );
			$this->stream_local_file( $resolved );
			$this->terminate();
			return;
		}

		$download = $this->download_external_url( $p );
		if ( is_wp_error( $download ) ) {
			$this->emit_error( $download );
			return;
		}

		$temp_path = (string) $download['path'];

		// Belt-and-braces cleanup — finally covers normal exit, exceptions,
		// and PHP returns; the shutdown hook covers fatals (OOM, max
		// execution time, etc.) that bypass try/finally. Either path
		// reaches `cleanup_temp_file`, which is idempotent.
		register_shutdown_function( array( $this, 'cleanup_temp_file' ), $temp_path );

		try {
			$this->emit_streaming_headers( (string) $download['mime'], (int) $download['size'] );
			readfile( $temp_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- direct dump; bounded by upstream size, no buffering by design
		} finally {
			$this->cleanup_temp_file( $temp_path );
		}

		$this->terminate();
	}

	/**
	 * Probe an upstream URL via HEAD to obtain Content-Type + Content-Length
	 * for the streaming-headers emit. Used by the HEAD path for external-URL
	 * `p` values.
	 *
	 * The shared remote-file client refuses an address that is not public
	 * before asking, and follows no redirect.
	 *
	 * @param string $url
	 *
	 * @return array{mime: string, size: int}|WP_Error
	 */
	private function probe_external_url( string $url ) {

		$head = $this->client->head( $url, self::PROXY_TIMEOUT );
		if ( is_wp_error( $head ) ) {
			return $this->upstream_refusal( $head, 'HEAD' );
		}

		if ( 200 !== $head->status() ) {
			return $this->upstream_refusal(
				new WP_Error( Remote_File_Client::NOT_DOWNLOADED, '', array( 'status' => $head->status() ) ),
				'HEAD'
			);
		}

		$mime = $head->content_type();

		return array(
			'mime' => '' !== $mime ? $mime : 'application/octet-stream',
			'size' => (int) $head->size(),
		);
	}

	/**
	 * The error the platform gets when the client refused or could not fetch
	 * an external URL.
	 *
	 * An address that is not public is answered as the allow-list answers it.
	 * An answer that was not the file (a redirect or an error status) and a
	 * failed request are upstream failures.
	 *
	 * @since 8.0.0
	 *
	 * @param WP_Error $error  The client's refusal.
	 * @param string   $method The upstream request: HEAD or GET.
	 *
	 * @return WP_Error Carries the status code in `data.status`.
	 */
	private function upstream_refusal( WP_Error $error, string $method ): WP_Error {

		if ( Remote_File_Client::PRIVATE_ADDRESS === $error->get_error_code() ) {
			return new WP_Error(
				'forbidden_path',
				'Requested path is not on the allow-list.',
				array( 'status' => 404 )
			);
		}

		if ( Remote_File_Client::NOT_DOWNLOADED === $error->get_error_code() ) {
			$data = $error->get_error_data();

			return new WP_Error(
				'upstream_error_status',
				sprintf( 'Upstream returned HTTP %d (likely hotlink-reject or auth required).', is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0 ),
				array( 'status' => 502 )
			);
		}

		return new WP_Error(
			'upstream_unreachable',
			'Upstream ' . $method . ' failed: ' . $error->get_error_message(),
			array( 'status' => 502 )
		);
	}

	/**
	 * Resolve `$p` to an absolute local file path, or null if it points
	 * elsewhere (external URL).
	 *
	 * @param string $p Allow-listed candidate.
	 *
	 * @return string|null Absolute path on disk, or null for external URL.
	 */
	private function resolve_local_path( string $p ): ?string {

		$uploads = wp_get_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : '';
		$basedir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';

		if ( '' === $basedir ) {
			return null;
		}

		$candidate = null;

		// Candidate is already an absolute filesystem path?
		if ( '' !== $p && DIRECTORY_SEPARATOR === $p[0] && false === strpos( $p, '://' ) ) {
			$candidate = $p;
		} elseif ( '' !== $baseurl && 0 === strpos( $p, rtrim( $baseurl, '/' ) . '/' ) ) {
			// Anchor on `baseurl + "/"` — the SAME boundary the gate uses
			// (Allowed_Path_Policy::is_allowed_external_url). A bare prefix
			// match mapped `{baseurl}-backups/x` onto `{basedir}-backups/x`,
			// a sibling dir the gate classified as an external own-host URL
			// and never realpath-contained; this keeps the two in lockstep.
			$relative  = substr( $p, strlen( rtrim( $baseurl, '/' ) ) );
			$candidate = rtrim( $basedir, DIRECTORY_SEPARATOR ) . $relative;
		}

		if ( null === $candidate ) {
			return null;
		}

		// Re-assert containment on the EXACT path we are about to open. The
		// request was gated by is_allowed() on `p`; this validates the resolved
		// disk bytes with the policy's realpath-under-basedir check — never
		// trust a second, independent re-derivation of a validated path. Closes
		// the prefix-boundary drift above and a symlink swapped between the
		// gate and the open (TOCTOU). A rejected candidate returns null, so the
		// caller routes it through the SSRF-guarded external fetch instead of
		// a raw disk read.
		if ( ! $this->policy->is_within_basedir( $candidate, $basedir ) ) {
			return null;
		}

		return $candidate;
	}

	/**
	 * Resolve MIME for a local file. Prefers the attachment row's
	 * recorded mime_type, falls back to PHP's fileinfo, then to
	 * `application/octet-stream`.
	 *
	 * @param string $absolute Absolute path.
	 * @param string $original Original `p` value (URL or path).
	 *
	 * @return string
	 */
	private function resolve_local_mime( string $absolute, string $original ): string {

		$attachment_id = attachment_url_to_postid( $original );
		if ( $attachment_id > 0 ) {
			$mime = get_post_mime_type( $attachment_id );
			if ( false !== $mime && '' !== $mime ) {
				return (string) $mime;
			}
		}

		if ( function_exists( 'mime_content_type' ) ) {
			$detected = @mime_content_type( $absolute ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort detection
			if ( false !== $detected && '' !== $detected ) {
				return (string) $detected;
			}
		}

		return 'application/octet-stream';
	}

	/**
	 * Emit the streaming-friendly header set.
	 *
	 * @param string $mime Resolved MIME type.
	 * @param int    $size Content length in bytes (0 if unknown).
	 *
	 * @return void
	 */
	private function emit_streaming_headers( string $mime, int $size ): void {

		// Belt-and-braces against PHP zlib output handler / Apache
		// mod_deflate / nginx gzip. The `Content-Encoding: identity` +
		// `Cache-Control: no-transform` headers are the wire-level
		// directive; the function calls below prevent the local PHP
		// runtime from undoing them.
		if ( ! headers_sent() ) {
			header_remove( 'Content-Encoding' );
		}
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- harmless if not Apache
		}

		// Disable PHP's output compression for this response.
		if ( function_exists( 'ini_set' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky, Squiz.PHP.DiscouragedFunctions.Discouraged -- Runtime flip, this request only; a compressed body would corrupt the Content-Length of a streamed file.
			@ini_set( 'zlib.output_compression', '0' );
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: ' . $mime );
			header( 'Content-Length: ' . $size );
			header( 'Content-Encoding: identity' );
			header( 'Cache-Control: no-transform' );
			// The body is attacker-influenceable bytes (a local upload or an
			// upstream fetch) served from this site's own origin. Stop the
			// browser MIME-sniffing it into something executable, and force
			// download rather than inline render. The platform (the real
			// consumer) reads the body regardless of both headers; a browser
			// that somehow reaches a valid signed URL is what they protect.
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Content-Disposition: attachment' );
		}
	}

	/**
	 * Stream the local file body in 64 KB chunks. No `file_get_contents`
	 * — would balloon PHP's resident memory on the cap. `fread` + `flush`
	 * keeps memory bounded.
	 *
	 * @param string $absolute Absolute file path.
	 *
	 * @return void
	 */
	private function stream_local_file( string $absolute ): void {

		$handle = fopen( $absolute, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- chunked streaming read; WP_Filesystem can only read a whole file into memory
		if ( false === $handle ) {
			automator_log( 'File_Serve_Controller: fopen failed for ' . $absolute, 'File_Serve_Controller' );
			return;
		}

		try {
			while ( ! feof( $handle ) ) {
				$buf = fread( $handle, self::CHUNK_BYTES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- reads one bounded chunk of the open stream; WP_Filesystem has no streaming read
				if ( false === $buf ) {
					break;
				}
				echo $buf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw file bytes; escaping would corrupt
				flush();
			}
		} catch ( Throwable $e ) {
			automator_log( 'File_Serve_Controller: stream error: ' . $e->getMessage(), 'File_Serve_Controller' );
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes the stream opened by fopen() above
		}
	}

	/**
	 * Download an upstream URL synchronously to a temp file, returning
	 * the resolved path + size + MIME, or a WP_Error on any failure.
	 *
	 * The shared remote-file client streams the body to a tempfile and
	 * BLOCKS until the request completes — by the time this method
	 * returns, no further upstream I/O is needed. That lets the GET
	 * branch convert upstream hotlink-reject / 4xx / connection-drop
	 * responses into a clean 502 BEFORE emitting any response bytes,
	 * instead of getting wrapped as a Cloudflare 520 from a half-written
	 * 200 response. The client refuses an address that is not public
	 * before asking and follows no redirect, so a URL the gate allowed
	 * cannot hand the download on to an address it would refuse; only a
	 * 200 is the file.
	 *
	 * Caller MUST clean up `path` via {@see cleanup_temp_file()}.
	 *
	 * @param string $url External URL.
	 *
	 * @return array{path: string, size: int, mime: string}|WP_Error
	 */
	private function download_external_url( string $url ) {

		$download = $this->client->download( $url, self::PROXY_TIMEOUT );
		if ( is_wp_error( $download ) ) {
			return $this->upstream_refusal( $download, 'GET' );
		}

		$temp_path = $download->path();

		if ( ! is_readable( $temp_path ) ) {
			return new WP_Error(
				'upstream_no_body',
				'Upstream returned no readable body.',
				array( 'status' => 502 )
			);
		}

		$size = filesize( $temp_path );
		if ( false === $size ) {
			$this->cleanup_temp_file( $temp_path );
			return new WP_Error(
				'upstream_no_size',
				'Could not stat upstream temp file.',
				array( 'status' => 502 )
			);
		}

		$mime = $download->content_type();
		if ( '' === $mime && function_exists( 'mime_content_type' ) ) {
			$detected = @mime_content_type( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort fallback
			if ( false !== $detected && '' !== $detected ) {
				$mime = (string) $detected;
			}
		}
		if ( '' === $mime ) {
			$mime = 'application/octet-stream';
		}

		return array(
			'path' => $temp_path,
			'size' => (int) $size,
			'mime' => $mime,
		);
	}

	/**
	 * Idempotent temp-file cleanup. Safe to call multiple times — checks
	 * existence first, then reports only a file that is still there after the
	 * delete (a file already gone or lost to a race is not a failure).
	 *
	 * Public so {@see register_shutdown_function()} can invoke it from the
	 * shutdown hook even if the request errored before the finally block
	 * could run.
	 *
	 * @param string $path Temp file path.
	 *
	 * @return void
	 */
	public function cleanup_temp_file( string $path ): void {

		if ( '' === $path ) {
			return;
		}
		if ( ! file_exists( $path ) ) {
			return;
		}
		wp_delete_file( $path );
		if ( file_exists( $path ) ) {
			automator_log( 'File_Serve_Controller: temp cleanup failed for ' . $path, 'File_Serve_Controller' );
		}
	}

	/**
	 * Detect the request method, defaulting to GET.
	 *
	 * @return string Upper-case method name.
	 */
	private function detect_method(): string {

		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';

		return $method;
	}

	/**
	 * Get filesize, returning null for missing/unreadable files.
	 *
	 * @param string $absolute Absolute path.
	 *
	 * @return int|null
	 */
	private function safe_filesize( string $absolute ): ?int {

		if ( ! is_file( $absolute ) || ! is_readable( $absolute ) ) {
			return null;
		}
		$size = filesize( $absolute );
		return false === $size ? null : (int) $size;
	}

	/**
	 * Emit a structured JSON error response with the carrier status code.
	 *
	 * @param WP_Error $error Carries the status code in `data.status`.
	 *
	 * @return void
	 */
	private function emit_error( WP_Error $error ): void {

		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

		if ( ! headers_sent() ) {
			status_header( $status );
			header( 'Content-Type: application/json; charset=utf-8' );
		}

		echo wp_json_encode(
			array(
				'error'   => $error->get_error_code(),
				'message' => $error->get_error_message(),
			)
		);

		$this->terminate();
	}

	/**
	 * End the request after a serve. Wrapped so tests can override via
	 * subclassing or a mock-friendly seam in the future. For now, exits
	 * cleanly — the controller has no further work to do.
	 *
	 * @return void
	 */
	private function terminate(): void {

		if ( defined( 'AUTOMATOR_FILE_SERVE_TESTING' ) && AUTOMATOR_FILE_SERVE_TESTING ) {
			return;
		}
		exit;
	}
}
