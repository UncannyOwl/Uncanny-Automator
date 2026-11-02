<?php
/**
 * Bounded file transfer for the native REST bridge, outside model arguments.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Atomic_Option_Lock;
use WP_Error;
use WP_REST_Request;

/** One pending file per site, verified user and client; no remote downloads. */
final class Media_Upload_Transfer {

	public const LIFETIME = 300;
	/** Consuming a staged file holds about two copies; allow four bytes per file byte. */
	private const MEMORY_PER_FILE_BYTE = 4;
	/** Free memory WordPress keeps for its own work while a file is staged or consumed. */
	private const MEMORY_RESERVE_BYTES = 8388608;
	/** Uploads subfolder for pending bytes; only their metadata is cached. */
	private const STAGING_DIRECTORY = 'automator-mcp-transfers';
	/** Common aliases for the media type WordPress assigns to a filename extension. */
	private const MEDIA_TYPE_ALIASES = array(
		'image/jpg'                => 'image/jpeg',
		'image/vnd.microsoft.icon' => 'image/x-icon',
	);

	private Atomic_Option_Lock $lock;

	/** Use the existing atomic option lock for transfer replacement and consumption. */
	public function __construct( ?Atomic_Option_Lock $lock = null ) {
		$this->lock = $lock ?? new Atomic_Option_Lock();
	}

	/** Only the original core media collection's create operation accepts bytes. */
	public static function is_operation( string $route, string $method, array $handler ): bool {
		$callback = $handler['callback'] ?? null;
		return '/wp/v2/media' === $route && 'POST' === $method
			&& is_array( $callback ) && isset( $callback[0], $callback[1] )
			&& is_object( $callback[0] ) && \WP_REST_Attachments_Controller::class === get_class( $callback[0] )
			&& 'create_item' === $callback[1]
			&& array( $callback[0], 'create_item_permissions_check' ) === ( $handler['permission_callback'] ?? null );
	}

	/** Discovery tells clients how to transfer before invoking the native write. */
	public static function contract(): array {
		return array(
			'transfer_path'          => '/automator/v1/mcp/media-transfer',
			'transfer_method'        => 'POST',
			'encoding'               => 'raw',
			'headers'                => array( 'Content-Type', 'Content-Disposition' ),
			'reference_argument'     => 'upload_reference',
			'max_file_bytes'         => self::max_file_bytes(),
			'expires_in_seconds'     => self::LIFETIME,
			'unsupported_parameters' => array( 'url' ),
			'instructions'           => 'Transfer one file with the same authenticated client and user. Pass the returned upload_reference to rest_request POST /wp/v2/media within five minutes. Each new transfer replaces that client and user\'s pending file. A reference is consumed before native execution; check the Media Library before retrying an uncertain write.',
		);
	}

	/**
	 * Bound discovery by WordPress's upload limit and current PHP headroom.
	 * The body itself needs one allocation before the later working allocations
	 * checked by has_memory_for(). Other plugins may change memory use between
	 * discovery and transfer, so the transfer checks again.
	 */
	public static function max_file_bytes(): int {
		return self::file_limit( 0 );
	}

	/**
	 * Calculate the limit without counting a request body twice.
	 *
	 * @since 8.0.0
	 */
	private static function file_limit( int $body_bytes ): int {
		$limit  = wp_max_upload_size();
		$memory = self::memory_limit();
		if ( $memory > 0 ) {
			$used     = max( 0, memory_get_usage( true ) - $body_bytes );
			$headroom = max( 0, $memory - $used - self::MEMORY_RESERVE_BYTES );
			$limit    = min(
				$limit,
				intdiv( $memory, 2 * self::MEMORY_PER_FILE_BYTE ),
				intdiv( $headroom, 1 + self::MEMORY_PER_FILE_BYTE )
			);
		}
		return max( 0, $limit );
	}

	/** The free-memory form of the advertised limit, for this request's actual usage. */
	private static function has_memory_for( int $bytes ): bool {
		$memory = self::memory_limit();
		return $memory <= 0 || $bytes * self::MEMORY_PER_FILE_BYTE + self::MEMORY_RESERVE_BYTES <= $memory - memory_get_usage( true );
	}

	/** A negative PHP limit means unlimited, not a negative upload allowance. */
	private static function memory_limit(): int {
		$configured = ini_get( 'memory_limit' );
		if ( false === $configured || '' === $configured ) {
			$configured = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '-1';
		}
		return wp_convert_hr_to_bytes( (string) $configured );
	}

	/** Check scope, native capability and owner switches before accepting bytes. */
	private function authorize( User_Context $context ): ?WP_Error {
		$operation = ( new Rest_Operation_Service() )->resolve( '/wp/v2/media', 'POST' );
		if ( is_wp_error( $operation ) ) {
			return self::error( 'upload_unavailable', 403 );
		}
		$reason = ( new Rest_Operation_Policy() )->unavailable_reason( $operation['policy'], $context, 'rest_request' );
		if ( null !== $reason ) {
			return self::error( 'upload_' . $reason, 403 );
		}
		if ( ! user_can( $context->get_executor(), 'upload_files' ) ) {
			return self::error( 'upload_permission_denied', 403 );
		}
		return null;
	}

	/** Transfer from the HTTP body; no file URL or base64 may be supplied by AI. */
	public function stage( User_Context $context, WP_REST_Request $request ) {
		$error = $this->authorize( $context );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		// Plain permalinks route every REST request through ?rest_route=.
		$query = array_diff_key( $request->get_query_params(), array( 'rest_route' => true ) );
		if ( $query || $request->get_body_params() || $request->get_file_params() ) {
			return self::error( 'upload_raw_required', 400 );
		}
		$filename = self::filename( $request );
		if ( null === $filename ) {
			return self::error( 'upload_filename_required', 400 );
		}
		$filename = sanitize_file_name( $filename );
		$bytes    = (string) $request->get_body();
		$error    = self::validate_file( $bytes, $filename, (string) $request->get_header( 'content-type' ) );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		// Refuse now a file this site could not consume after an unusually large bootstrap.
		if ( ! self::has_memory_for( strlen( $bytes ) ) ) {
			return self::error( 'upload_memory_unavailable', 503 );
		}
		$mime  = self::filename_mime( $filename );
		$key   = self::storage_key( $context );
		$owner = $this->lock->acquire( $key . '_lock', 5, 1 );
		if ( false === $owner ) {
			return self::error( 'upload_busy', 409 );
		}
		try {
			$previous  = get_transient( $key );
			$file_id   = wp_generate_password( 64, false, false );
			$path      = self::staged_path( $file_id );
			$reference = wp_generate_password( 48, false, false );
			// Bytes go to a file: cache items (Memcached's 1 MB) and SQL packets limit transients.
			$written = null === $path ? false : file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem may need FTP credentials; this is a private staging file.
			$saved   = strlen( $bytes ) === $written && set_transient(
				$key,
				array(
					'reference' => $reference,
					'filename'  => $filename,
					'mime_type' => $mime,
					'file'      => $file_id,
					'size'      => strlen( $bytes ),
					'sha256'    => hash( 'sha256', $bytes ),
					'expires'   => time() + self::LIFETIME,
				),
				self::LIFETIME
			);
			if ( ! $saved ) {
				self::delete_staged_file( $file_id );
				return self::error( 'upload_storage_failed', 503 );
			}
			// The new transfer replaces this owner's pending file.
			if ( is_array( $previous ) && is_string( $previous['file'] ?? null ) ) {
				self::delete_staged_file( $previous['file'] );
			}
			self::delete_expired_files();
			return array(
				'upload_reference'   => $reference,
				'filename'           => $filename,
				'mime_type'          => $mime,
				'bytes'              => strlen( $bytes ),
				'expires_in_seconds' => self::LIFETIME,
			);
		} finally {
			$this->lock->release( $key . '_lock', $owner );
		}
	}

	/** Atomically consume the owner's reference before any native side effect. */
	public function take( User_Context $context, string $reference ) {
		$error = $this->authorize( $context );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		if ( '' === $reference ) {
			return self::error( 'upload_reference_required', 400 );
		}
		$key   = self::storage_key( $context );
		$owner = $this->lock->acquire( $key . '_lock', 5, 1 );
		if ( false === $owner ) {
			return self::error( 'upload_busy', 409 );
		}
		try {
			$file = get_transient( $key );
			if ( ! is_array( $file ) || ! isset( $file['file'], $file['size'], $file['sha256'] ) || $file['expires'] <= time() || ! hash_equals( $file['reference'], $reference ) ) {
				return self::error( 'upload_reference_unavailable', 410 );
			}
			// Refuse before consuming so the same reference can be retried.
			if ( ! self::has_memory_for( (int) $file['size'] ) ) {
				return self::error( 'upload_memory_unavailable', 503 );
			}
			// If deletion fails, never dispatch a write that could be replayed.
			if ( ! delete_transient( $key ) ) {
				return self::error( 'upload_storage_failed', 503 );
			}
			$path  = self::staged_path( (string) $file['file'] );
			$bytes = null !== $path && is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local staging file, not a URL.
			self::delete_staged_file( (string) $file['file'] );
			// Another web server without this file, or a changed file, cannot be uploaded.
			if ( ! is_string( $bytes ) || strlen( $bytes ) !== (int) $file['size'] || ! hash_equals( (string) $file['sha256'], hash( 'sha256', $bytes ) ) ) {
				return self::error( 'upload_storage_failed', 503 );
			}
			$file['bytes'] = $bytes;
			unset( $file['file'], $file['sha256'] );
			return $file;
		} finally {
			$this->lock->release( $key . '_lock', $owner );
		}
	}

	/**
	 * Staging path for one random file ID, or null when uploads is not writable.
	 *
	 * Uploads is shared by load-balanced web servers, unlike a server's temp
	 * folder. The 64-character random name keeps pending bytes unguessable
	 * where the web server ignores the deny file.
	 */
	private static function staged_path( string $file_id ): ?string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9]{64}$/D', $file_id ) ) {
			return null;
		}
		$directory = self::staging_directory();
		return null === $directory ? null : $directory . '/' . $file_id;
	}

	/** Create the private staging folder with deny and index files once. */
	private static function staging_directory(): ?string {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return null;
		}
		$directory = untrailingslashit( $uploads['basedir'] ) . '/' . self::STAGING_DIRECTORY;
		if ( ! wp_mkdir_p( $directory ) ) {
			return null;
		}
		$guards = array(
			'.htaccess' => "# Pending Automator MCP file transfers.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n",
			'index.php' => "<?php\n// Silence is golden.\n",
		);
		foreach ( $guards as $name => $contents ) {
			if ( ! file_exists( $directory . '/' . $name ) ) {
				file_put_contents( $directory . '/' . $name, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Static guard file in the private staging folder.
			}
		}
		return $directory;
	}

	/** Remove one staged file; a missing file is already gone. */
	private static function delete_staged_file( string $file_id ): void {
		$path = self::staged_path( $file_id );
		if ( null !== $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/** Abandoned transfers leave files behind; remove those well past a transfer's lifetime. */
	private static function delete_expired_files(): void {
		$directory = self::staging_directory();
		$names     = null === $directory ? false : scandir( $directory );
		foreach ( false === $names ? array() : $names as $name ) {
			$path = $directory . '/' . $name;
			if ( 1 === preg_match( '/^[A-Za-z0-9]{64}$/D', $name ) && (int) filemtime( $path ) < time() - 2 * self::LIFETIME ) {
				wp_delete_file( $path );
			}
		}
	}

	/**
	 * Storage key.
	 *
	 * @param User_Context $context The context.
	 * @return string
	 */
	public static function storage_key( User_Context $context ): string {
		return 'automator_mcp_upload_' . hash( 'sha256', wp_json_encode( array( get_current_blog_id(), $context->get_executor(), $context->get_client_id(), $context->is_first_party_caller() ) ) );
	}
	/**
	 * Filename.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return ?
	 */
	private static function filename( WP_REST_Request $request ): ?string {
		$value = (string) $request->get_header( 'content-disposition' );
		return 1 === preg_match( '/^attachment; filename="([^"\\\\\/\x00-\x1f\x7f]+)"$/D', $value, $match ) && strlen( $match[1] ) <= 180 ? $match[1] : null;
	}
	/**
	 * Validate file.
	 *
	 * @param string $bytes The bytes.
	 * @param string $filename The name.
	 * @param string $mime The mime.
	 * @return ?
	 */
	private static function validate_file( string $bytes, string $filename, string $mime ): ?WP_Error {
		if ( '' === $bytes ) {
			return self::error( 'upload_empty_file', 400 );
		}
		$limit = self::file_limit( strlen( $bytes ) );
		if ( 0 === $limit && wp_max_upload_size() > 0 ) {
			return self::error( 'upload_memory_unavailable', 503 );
		}
		if ( strlen( $bytes ) > $limit ) {
			return new WP_Error(
				'upload_file_too_large',
				sprintf( 'This file exceeds the upload limit of %s.', size_format( $limit ) ),
				array(
					'status'         => 413,
					'max_file_bytes' => $limit,
					'next_action'    => 'Choose a smaller file or compress it before uploading again.',
				)
			);
		}
		// Parameters such as "; charset=binary" do not change the media type.
		$declared = strtolower( trim( (string) strtok( $mime, ';' ) ) );
		if ( '' === $declared ) {
			return self::error( 'upload_content_type_required', 400 );
		}
		// WordPress decides the type from the filename; a generic or alias header agrees with it.
		$mime = self::filename_mime( $filename );
		if ( '' === $mime || ( 'application/octet-stream' !== $declared && ( self::MEDIA_TYPE_ALIASES[ $declared ] ?? $declared ) !== $mime ) ) {
			return self::error( 'upload_type_not_allowed', 415 );
		}
		if ( 0 === strpos( $mime, 'image/' ) && ! self::is_image_of_type( $bytes, $mime ) ) {
			return self::error( 'upload_invalid_file', 415 );
		}
		return null;
	}

	/** The type WordPress allows for this filename, or an empty string. */
	private static function filename_mime( string $filename ): string {
		$type = wp_check_filetype( $filename, get_allowed_mime_types() );
		return is_string( $type['type'] ) ? $type['type'] : '';
	}

	/** Check the bytes, not the name, using the readers WordPress itself relies on. */
	private static function is_image_of_type( string $bytes, string $mime ): bool {
		if ( 'image/svg+xml' === $mime ) {
			// Allowed only when the site allows SVG; core checks the upload again.
			return false !== stripos( $bytes, '<svg' );
		}
		$image    = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Malformed user bytes are reported as a friendly validation error.
		$detected = $image['mime'] ?? false;
		if ( ! is_string( $detected ) ) {
			// PHP cannot read HEIC, or AVIF before 8.2; WordPress recognizes their signatures.
			$detected = self::native_image_mime( $bytes );
		}
		return is_string( $detected ) && self::is_same_image_type( self::MEDIA_TYPE_ALIASES[ $detected ] ?? $detected, $mime );
	}

	/** PHP 8.5 reads every HEIF-family image as image/heif, so a HEIC file matches its family rather than its exact type. */
	private static function is_same_image_type( string $detected, string $mime ): bool {
		if ( $detected === $mime ) {
			return true;
		}
		return function_exists( 'wp_is_heic_image_mime_type' ) && wp_is_heic_image_mime_type( $detected ) && wp_is_heic_image_mime_type( $mime );
	}

	/** PHP's image-size reader can reject formats WordPress supports, such as HEIC and AVIF. */
	private static function native_image_mime( string $bytes ) {
		$file = tmpfile();
		if ( false === $file ) {
			return false;
		}
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- anonymous tmpfile() stream that PHP deletes on close; WP_Filesystem needs site credentials a REST request cannot supply
			if ( strlen( $bytes ) !== fwrite( $file, $bytes ) ) {
				return false;
			}
			$metadata = stream_get_meta_data( $file );
			return @wp_get_image_mime( $metadata['uri'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Core falls back to MIME signatures when PHP cannot read HEIC dimensions.
		} finally {
			fclose( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes (and so deletes) the tmpfile() stream opened above
		}
	}

	/** Core 7.1 can sideload a URL instead of bytes; this transfer only accepts files. */
	public static function validate_metadata( WP_REST_Request $request ): ?WP_Error {
		if ( null !== $request->get_param( 'url' ) ) {
			return self::error( 'upload_remote_url_unsupported', 400 );
		}
		return null;
	}

	/** Rechecked before validation and callbacks, including after third-party hooks. */
	public static function validate_native_request( WP_REST_Request $request ): ?WP_Error {
		if ( '/wp/v2/media' !== $request->get_route() || 'POST' !== $request->get_method() || $request->get_file_params() ) {
			return self::error( 'upload_wrong_operation', 400 );
		}
		$error = self::validate_metadata( $request );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		$filename = self::filename( $request );
		if ( null === $filename || sanitize_file_name( $filename ) !== $filename ) {
			return self::error( 'upload_filename_required', 400 );
		}
		return self::validate_file( (string) $request->get_body(), $filename, (string) $request->get_header( 'content-type' ) );
	}

	/** Hooks may not replace the transferred file or its generated headers. */
	public static function fingerprint( WP_REST_Request $request ): string {
		return hash( 'sha256', (string) $request->get_body() . "\0" . wp_json_encode( $request->get_headers() ) );
	}

	/** Friendly known failures retain a machine-readable code and recovery action. */
	public static function error( string $code, int $status ): WP_Error {
		$messages = array(
			'upload_connection_unavailable' => array( 'This site connection could not be verified for the upload.', 'Reconnect the site, then transfer the file again.' ),
			'upload_reference_required'     => array( 'No uploaded file was selected.', 'Transfer the file first, then pass its upload_reference to POST /wp/v2/media.' ),
			'upload_remote_url_unsupported' => array( 'This upload cannot download a file from a URL.', 'Remove the url argument. Transfer the file bytes first, then pass the returned upload_reference to POST /wp/v2/media.' ),
			'upload_reference_unavailable'  => array( 'This upload reference is unavailable. It may have expired, been replaced or already been used, or belong to another user or client.', 'Check the Media Library for an earlier upload. If it is absent, transfer the file again using the same site, user and client.' ),
			'upload_write_scope_required'   => array( 'This connection has read-only access.', 'Reconnect this site with write access before uploading.' ),
			'upload_administrator_required' => array( 'This upload requires a WordPress administrator.', 'Connect the site using an administrator account.' ),
			'upload_tool_disabled'          => array( 'The site owner has disabled a tool required for uploads.', 'Ask the site owner to enable rest_request and get_posts in MCP settings > Site tools.' ),
			'upload_permission_denied'      => array( 'Your WordPress user cannot upload files.', 'Ask a site administrator to grant upload_files permission or connect an account that can upload.' ),
			'upload_raw_required'           => array( 'The file transfer must contain raw file bytes.', 'Send one file as the HTTP body, with Content-Type and Content-Disposition headers; do not send JSON, base64 or multipart data.' ),
			'upload_content_type_required'  => array( 'The file transfer has no Content-Type.', 'Send the correct MIME type for the file, for example Content-Type: image/png.' ),
			'upload_filename_required'      => array( 'A valid filename is required.', 'Send Content-Disposition: attachment; filename="example.png". Use a filename under 181 bytes without paths or control characters.' ),
			'upload_empty_file'             => array( 'The selected file is empty.', 'Choose a file that contains data and upload it again.' ),
			'upload_type_not_allowed'       => array( 'This file type or filename extension is not allowed on this site.', 'Choose a file type allowed by WordPress and send the matching Content-Type; do not rename a file to bypass its type.' ),
			'upload_invalid_file'           => array( 'The file contents do not match a valid file of the declared type.', 'Open or export the original file again, then upload the valid file with its correct extension and Content-Type.' ),
			'upload_busy'                   => array( 'Another file transfer is being processed for this user and client.', 'Wait a moment, then transfer the file again.' ),
			'upload_storage_failed'         => array( 'WordPress could not store or read the temporary upload.', 'Check that the uploads folder is writable and that the site database or object cache is healthy, then transfer the file again.' ),
			'upload_memory_unavailable'     => array( 'WordPress does not have enough free memory to stage this file.', 'Choose a smaller file or ask the site administrator to check PHP memory usage before transferring it again.' ),
			'upload_changed'                => array( 'The transferred file changed before WordPress could save it.', 'Check plugins that modify REST requests, then transfer the original file again.' ),
			'upload_wrong_operation'        => array( 'This upload reference cannot be used for this REST operation.', 'Use the reference only with POST /wp/v2/media on the same site.' ),
			'upload_origin_denied'          => array( 'This file transfer came from an unapproved origin.', 'Use the approved Uncanny Automator connection to upload the file.' ),
			'upload_rate_limited'           => array( 'Too many file transfers were started in a short time.', 'Wait one minute before uploading again.' ),
			'upload_unavailable'            => array( 'The native WordPress media upload operation is unavailable.', 'Ask a site administrator to check the media REST route and plugins that modify it.' ),
		);
		$message  = $messages[ $code ] ?? array( 'WordPress could not complete this upload.', 'Check the Media Library before retrying. Ask a site administrator to check the WordPress error log if the problem continues.' );
		return new WP_Error(
			$code,
			$message[0],
			array(
				'status'      => $status,
				'next_action' => $message[1],
			)
		);
	}

	/** Do not expose filesystem paths or arbitrary plugin messages from core errors. */
	public static function native_error( array $body, int $status, int $attachment_id = 0 ): array {
		$known_codes = array(
			'rest_cannot_create',
			'rest_upload_cannot_create',
			'rest_cannot_edit',
			'rest_forbidden',
			'rest_invalid_param',
			'rest_invalid_json',
			'rest_missing_callback_param',
			'rest_invalid_url',
			'rest_invalid_type',
			'rest_not_in_enum',
			'rest_post_invalid_id',
			'rest_no_route',
			'rest_upload_file_error',
			'rest_upload_sideload_error',
			'rest_upload_unknown_error',
			'rest_upload_no_data',
			'rest_upload_no_content_type',
			'rest_upload_no_content_disposition',
			'rest_upload_invalid_disposition',
			'rest_upload_hash_mismatch',
			'rest_upload_limited_space',
			'rest_upload_file_too_big',
			'rest_upload_user_quota_exceeded',
			'rest_upload_image_type_not_supported',
			'rest_upload_invalid_dimensions',
			'rest_upload_dimension_mismatch',
			'rest_upload_unknown_size',
			'rest_upload_invalid_image',
			'upload_space_exceeded',
			'upload_file_too_big',
			'upload_limit_exceeded',
			'upload_connection_unavailable',
			'upload_reference_required',
			'upload_remote_url_unsupported',
			'upload_reference_unavailable',
			'upload_write_scope_required',
			'upload_administrator_required',
			'upload_tool_disabled',
			'upload_permission_denied',
			'upload_raw_required',
			'upload_content_type_required',
			'upload_filename_required',
			'upload_empty_file',
			'upload_type_not_allowed',
			'upload_invalid_file',
			'upload_busy',
			'upload_storage_failed',
			'upload_memory_unavailable',
			'upload_changed',
			'upload_wrong_operation',
			'upload_origin_denied',
			'upload_rate_limited',
			'upload_unavailable',
			'upload_failed',
		);
		$code        = is_string( $body['code'] ?? null ) && in_array( $body['code'], $known_codes, true ) ? $body['code'] : 'upload_failed';
		$mapped      = in_array( $code, array( 'rest_cannot_create', 'rest_upload_cannot_create', 'rest_cannot_edit', 'rest_forbidden' ), true ) ? 'upload_permission_denied' : $code;
		$error       = self::error( $mapped, $status );
		if ( in_array( $code, array( 'rest_invalid_param', 'rest_invalid_json', 'rest_missing_callback_param' ), true ) ) {
			$error = new WP_Error(
				$code,
				'WordPress rejected the attachment metadata.',
				array(
					'status'      => $status,
					'next_action' => 'Discover POST /wp/v2/media again, correct the title, caption, alt text or post arguments, then transfer the file again.',
				)
			);
		}
		if ( in_array( $code, array( 'upload_space_exceeded', 'upload_file_too_big', 'upload_limit_exceeded' ), true ) ) {
			$error = new WP_Error(
				$code,
				'This site has reached its WordPress upload size or storage allowance.',
				array(
					'status'      => $status,
					'next_action' => 'Choose a smaller file or ask the network administrator to check the site storage and upload allowance.',
				)
			);
		}
		if ( 'rest_upload_sideload_error' === $code || 'rest_upload_file_error' === $code ) {
			$error = new WP_Error(
				$code,
				'WordPress could not save this file to the Media Library.',
				array(
					'status'      => $status,
					'next_action' => 'Ask a site administrator to check the uploads directory permissions, available disk space and allowed file types. Check the Media Library before retrying.',
				)
			);
		}
		$data = $error->get_error_data();
		if ( $attachment_id > 0 ) {
			// A native metadata failure is not a rollback. Retain the attachment and
			// identify it for readback; deleting or replaying could discard saved work.
			$data['action']        = 'partially_saved';
			$data['attachment_id'] = $attachment_id;
			$data['read_path']     = '/wp/v2/media/' . $attachment_id;
			$data['next_action']   = 'The file was created, but WordPress did not complete the upload operation. Read ' . $data['read_path'] . ' and inspect the saved attachment before correcting metadata. Do not upload the file again.';
		} else {
			$data['next_action'] = 'Check the Media Library first. Do not retry until its state is confirmed. ' . $data['next_action'];
		}
		return array(
			'code'    => $code,
			'message' => $attachment_id > 0 ? 'WordPress created the attachment but could not complete the upload operation.' : $error->get_error_message(),
			'data'    => $data,
		);
	}
}
