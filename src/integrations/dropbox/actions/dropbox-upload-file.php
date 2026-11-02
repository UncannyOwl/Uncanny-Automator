<?php
/**
 * Upload a file to Dropbox
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\Recipe\App_Action;

/**
 * Class Dropbox_Upload_File
 *
 * The plugin signs a short-lived URL to the file, and the platform streams it
 * into Dropbox (`/v3/dropbox/upload-from-url`); the pre-flight runs in
 * {@see App_Action::prepare_upload_request()}.
 *
 * @property Dropbox_App_Helpers $helpers
 * @property Dropbox_Api_Caller $api
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_Upload_File extends App_Action {

	/**
	 * A valid Dropbox path: a leading slash, no NUL bytes, at most 1023 more
	 * characters.
	 */
	const DROPBOX_PATH_PATTERN = '#^/[^\x00]{0,1023}$#';

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'DROPBOX' );
		$this->set_action_code( 'DROPBOX_UPLOAD_FILE' );
		$this->set_action_meta( 'FILE_NAME' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Folder path */
				esc_html_x( 'Upload a file to {{a folder:%1$s}}', 'Dropbox', 'uncanny-automator' ),
				'FOLDER_PATH:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Upload a file to {{a folder}}', 'Dropbox', 'uncanny-automator' ) );
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'UPLOADED_FILE_PATH'      => array(
				'name' => esc_html_x( 'Uploaded file path', 'Dropbox', 'uncanny-automator' ),
				'type' => 'text',
			),
			'UPLOADED_FILE_ID'        => array(
				'name' => esc_html_x( 'Uploaded file ID', 'Dropbox', 'uncanny-automator' ),
				'type' => 'text',
			),
			'UPLOADED_FILE_SIZE'      => array(
				'name' => esc_html_x( 'Uploaded file size (bytes)', 'Dropbox', 'uncanny-automator' ),
				'type' => 'int',
			),
			'UPLOADED_FILE_MIME_TYPE' => array(
				'name' => esc_html_x( 'Uploaded file MIME type', 'Dropbox', 'uncanny-automator' ),
				'type' => 'text',
			),
			'UPLOADED_FILE_URL'       => array(
				'name' => esc_html_x( 'Uploaded file shareable URL', 'Dropbox', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_folder_option_config( 'FOLDER_PATH' ),
			array(
				'option_code'   => 'USE_FILE_PATH',
				'label'         => esc_html_x( 'Use file path or URL', 'Dropbox', 'uncanny-automator' ),
				'input_type'    => 'checkbox',
				'is_toggle'     => true,
				'required'      => false,
				'default_value' => false,
				'description'   => esc_html_x( 'Enable to enter a file path or URL instead of selecting from media library', 'Dropbox', 'uncanny-automator' ),
			),
			array(
				'option_code'              => 'FILE_URL',
				'label'                    => esc_html_x( 'File', 'Dropbox', 'uncanny-automator' ),
				'input_type'               => 'file',
				'required'                 => false,
				'supports_multiple_values' => false,
				'description'              => esc_html_x( 'Select a file from WordPress media library', 'Dropbox', 'uncanny-automator' ),
				// Visible by default, hidden once the toggle is on.
				//
				// Two traps here, both of which fail silently as "field never
				// appears". `visible` is the ONLY spelling the rule evaluator
				// accepts for default_state — `show` reads correctly but
				// evaluates false, leaving the field hidden. And the rule has
				// to be hide-on-true rather than show-on-false, because the
				// evaluator only flips visibility when a rule MATCHES; a
				// show-on-false rule can never hide anything.
				'dynamic_visibility'       => array(
					'default_state'    => 'visible',
					'visibility_rules' => array(
						array(
							'operator'             => 'AND',
							'rule_conditions'      => array(
								array(
									'option_code' => 'USE_FILE_PATH',
									'compare'     => '==',
									'value'       => true,
								),
							),
							'resulting_visibility' => 'hide',
						),
					),
				),
			),
			array(
				'option_code'           => 'FILE_PATH',
				'label'                 => esc_html_x( 'File path, URL, or media ID', 'Dropbox', 'uncanny-automator' ),
				'input_type'            => 'text',
				'required'              => false,
				'supports_tokens'       => true,
				'supports_custom_value' => true,
				'description'           => esc_html_x( 'WordPress media ID, /wp-content/ path, or HTTPS URL', 'Dropbox', 'uncanny-automator' ),
				'dynamic_visibility'    => array(
					'default_state'    => 'hidden',
					'visibility_rules' => array(
						array(
							'operator'             => 'AND',
							'rule_conditions'      => array(
								array(
									'option_code' => 'USE_FILE_PATH',
									'compare'     => '==',
									'value'       => true,
								),
							),
							'resulting_visibility' => 'show',
						),
					),
				),
			),
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'File name (optional)', 'Dropbox', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
				'description' => esc_html_x( 'Leave blank to use the original file name', 'Dropbox', 'uncanny-automator' ),
			),
			array(
				'option_code'   => 'CREATE_SHARED_LINK',
				'label'         => esc_html_x( 'Create shareable link', 'Dropbox', 'uncanny-automator' ),
				'input_type'    => 'checkbox',
				'is_toggle'     => true,
				'required'      => false,
				'default_value' => true,
				'description'   => esc_html_x( 'Generate a shareable URL for the uploaded file', 'Dropbox', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     User ID.
	 * @param array $action_data Action data.
	 * @param int   $recipe_id   Recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed values.
	 *
	 * @return bool
	 * @throws \Exception If pre-flight or platform call fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$source             = $this->resolve_source( $parsed );
		$destination_path   = $this->resolve_destination_path( $parsed );
		$create_shared_link = isset( $parsed['CREATE_SHARED_LINK'] )
			? (bool) filter_var( $parsed['CREATE_SHARED_LINK'], FILTER_VALIDATE_BOOLEAN )
			: true;

		// prepare_upload_request() signs the source URL, checks its size
		// (32 MB at most) and MIME type, renames rather than overwrites on a
		// name clash, and saves the request with the action log so a retry
		// sends the same upload.
		$request = $this->prepare_upload_request(
			array(
				'source'             => $source,
				'vendor_body'        => array(
					'destination_path' => $destination_path,
				),
				'create_shared_link' => $create_shared_link,
			)
		);

		$result = $this->api->upload_from_url( $request['body'], $request['headers'], $action_data );

		if ( $this->deferred( $result ) ) {
			return true;
		}

		return $this->process_job( is_array( $result ) ? $result : array(), $parsed, $action_data );
	}

	/**
	 * Fill the upload tokens from the platform's answer: the unwrapped result of a synchronous upload, or the
	 * completion envelope of a background job, whose result sits under `data`.
	 *
	 * @param array $response    The upload result, or the job's completion envelope.
	 * @param array $parsed      The parsed field values.
	 * @param array $action_data The action data.
	 *
	 * @return bool
	 */
	protected function process_job( array $response, array $parsed, array $action_data ): bool {
		$data = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : $response;

		$this->hydrate_tokens( $this->result_to_token_map( $data ) );

		return true;
	}

	/**
	 * Resolve the recipe-author file input to a single string the
	 * pre-flight signer accepts.
	 *
	 * Four input shapes:
	 *  - Numeric `FILE_URL` or `FILE_PATH` → wp_get_attachment_url
	 *  - Media-picker JSON (FILE_URL field) → unwrap `[0].url`
	 *  - String starting with `/wp-content/` → return as-is
	 *  - String `https://...` → return as-is
	 *
	 * The pre-flight's signer accepts media-library paths verbatim and
	 * external HTTPS URLs via the allow-list policy.
	 *
	 * @param array $parsed Parsed action input.
	 *
	 * @return string A path or URL the signer can mint a signed URL over.
	 *
	 * @throws Action_Error_Exception When the input can't be resolved.
	 */
	protected function resolve_source( array $parsed ) {

		$use_file_path = isset( $parsed['USE_FILE_PATH'] ) && 'true' === $parsed['USE_FILE_PATH'];
		$raw           = $use_file_path
			? (string) ( $parsed['FILE_PATH'] ?? '' )
			: (string) ( $parsed['FILE_URL'] ?? '' );

		if ( '' === $raw ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'File is required.', 'Dropbox', 'uncanny-automator' ) );
		}

		// Numeric → attachment ID.
		if ( is_numeric( $raw ) ) {
			$url = wp_get_attachment_url( (int) $raw );
			if ( false === $url || '' === (string) $url ) {
				throw Action_Error_Exception::validation_failed(
					esc_html(
						sprintf(
							// translators: %s: Attachment ID.
							esc_html_x( 'WordPress media file not found for ID: %s', 'Dropbox', 'uncanny-automator' ),
							(string) $raw
						)
					)
				);
			}
			return (string) $url;
		}

		// Media-picker JSON shape `[{id, url, filename, ...}]`.
		if ( '[' === substr( $raw, 0, 1 ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) && isset( $decoded[0]['url'] ) && '' !== (string) $decoded[0]['url'] ) {
				return (string) $decoded[0]['url'];
			}
			throw Action_Error_Exception::validation_failed( esc_html_x( 'Invalid media file data.', 'Dropbox', 'uncanny-automator' ) );
		}

		// /wp-content/ path → pass to signer verbatim.
		if ( 0 === strpos( $raw, '/wp-content/' ) ) {
			return $raw;
		}

		// External HTTPS URL → pass to signer verbatim (allow-list policy
		// applies inside the signer).
		if ( 0 === stripos( $raw, 'https://' ) ) {
			return $raw;
		}

		// Absolute filesystem path under uploads — accept; pre-flight's
		// inspect_upload_source handles the local-file branch.
		if ( '/' === substr( $raw, 0, 1 ) && file_exists( $raw ) ) {
			return $raw;
		}

		throw Action_Error_Exception::validation_failed(
			esc_html(
				sprintf(
					// translators: %s: Recipe author input.
					esc_html_x( 'Could not resolve file source: %s', 'Dropbox', 'uncanny-automator' ),
					$raw
				)
			)
		);
	}

	/**
	 * Resolve the Dropbox destination path.
	 *
	 * Combines the parent folder (token-resolved by recipe runner) with
	 * the file name (custom override or input-derived). Validates the
	 * final path against the Dropbox path regex
	 * {@see DROPBOX_PATH_PATTERN}.
	 *
	 * @param array $parsed Parsed action input.
	 *
	 * @return string Validated Dropbox path.
	 *
	 * @throws Action_Error_Exception When the resolved path is not a valid Dropbox path.
	 */
	protected function resolve_destination_path( array $parsed ) {

		$folder_path      = $this->helpers->get_folder_from_parsed( $parsed, 'FOLDER_PATH' );
		$custom_file_name = (string) ( $parsed[ $this->get_action_meta() ] ?? '' );

		// Fall back to the source's basename when the recipe author left
		// the optional override blank.
		$file_name = '' !== $custom_file_name
			? $custom_file_name
			: $this->derive_file_name_from_input( $parsed );

		$file_name = ltrim( $file_name, '/' );
		if ( '' === $file_name ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'File name is required.', 'Dropbox', 'uncanny-automator' ) );
		}

		// Dropbox API treats `'/' . $folder . '/' . $name` as ambiguous on
		// root — folder is empty string at root in helpers->normalize_path.
		$path = $folder_path . '/' . $file_name;

		// Collapse accidental double-slashes from concatenation.
		$path = '/' . ltrim( preg_replace( '#/+#', '/', $path ), '/' );

		if ( 1 !== preg_match( self::DROPBOX_PATH_PATTERN, $path ) ) {
			throw Action_Error_Exception::validation_failed(
				esc_html(
					sprintf(
						// translators: %s: Invalid path.
						esc_html_x( 'Invalid Dropbox path: %s', 'Dropbox', 'uncanny-automator' ),
						$path
					)
				)
			);
		}

		return $path;
	}

	/**
	 * Build the token map from the upload result, covering
	 * all three success shapes (link populated / null with warning /
	 * null when create_shared_link=false). UPLOADED_FILE_URL is an empty
	 * string when `shared_url` is null. Empty-input tolerance is a safety net.
	 *
	 * @param array $result The upload result.
	 *
	 * @return array<string, mixed> Token map for hydrate_tokens().
	 */
	protected function result_to_token_map( array $result ): array {

		$shared_url = $result['shared_url'] ?? null;

		return array(
			'UPLOADED_FILE_PATH'      => isset( $result['path'] ) ? (string) $result['path'] : '',
			'UPLOADED_FILE_ID'        => isset( $result['id'] ) ? (string) $result['id'] : '',
			'UPLOADED_FILE_SIZE'      => isset( $result['size'] ) ? (int) $result['size'] : 0,
			'UPLOADED_FILE_MIME_TYPE' => isset( $result['mime_type'] ) ? (string) $result['mime_type'] : '',
			'UPLOADED_FILE_URL'       => null === $shared_url ? '' : (string) $shared_url,
		);
	}

	/**
	 * Derive a fallback file name from the parsed input when the
	 * `FILE_NAME` override is empty.
	 *
	 * @param array $parsed Parsed action input.
	 *
	 * @return string Best-effort basename.
	 */
	private function derive_file_name_from_input( array $parsed ) {

		$use_file_path = isset( $parsed['USE_FILE_PATH'] ) && 'true' === $parsed['USE_FILE_PATH'];
		$raw           = $use_file_path
			? (string) ( $parsed['FILE_PATH'] ?? '' )
			: (string) ( $parsed['FILE_URL'] ?? '' );

		// Media-picker JSON carries an explicit filename.
		if ( '[' === substr( $raw, 0, 1 ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) && ! empty( $decoded[0]['filename'] ) ) {
				return (string) $decoded[0]['filename'];
			}
			if ( is_array( $decoded ) && ! empty( $decoded[0]['url'] ) ) {
				return basename( wp_parse_url( (string) $decoded[0]['url'], PHP_URL_PATH ) );
			}
		}

		if ( is_numeric( $raw ) ) {
			$url = wp_get_attachment_url( (int) $raw );
			return basename( wp_parse_url( (string) $url, PHP_URL_PATH ) );
		}

		if ( 0 === stripos( $raw, 'https://' ) || 0 === stripos( $raw, 'http://' ) ) {
			return basename( wp_parse_url( $raw, PHP_URL_PATH ) );
		}

		return basename( $raw );
	}
}
