<?php

namespace Uncanny_Automator\Integrations\OpenAI\Hydrators;

use Exception;

/**
 * Processes OpenAI image-generation responses, saves the image,
 * and returns tokens for Automator actions.
 */
class Image_Response_Hydrator {

	/**
	 * The image format to use when saving (png, jpeg, webp).
	 *
	 * @var string
	 */
	protected $output_format = 'png';

	/**
	 * Set the output image format.
	 *
	 * @param string $format
	 * @return self
	 */
	public function set_output_format( string $format ): self {
		$this->output_format = $format;
		return $this;
	}

	/**
	 * Main entry: validates response, saves image, and builds token map.
	 *
	 * @param array $response The OpenAI response.
	 * @throws Exception
	 * @return array{IMAGE_ID:int,IMAGE_URL:string,INPUT_TOKENS:int,INPUT_TOKENS_IMAGE_TOKENS:int,INPUT_TOKENS_TEXT_TOKENS:int,OUTPUT_TOKENS:int,TOTAL_TOKENS:int}
	 */
	public function hydrate_from_response( array $response ): array {
		$b64       = $this->validate_and_get_b64( $response );
		$raw       = $this->decode_image( $b64 );
		$path      = $this->save_to_disk( $raw );
		$attach_id = $this->insert_media( $path );
		$usage     = $this->extract_usage( $response );
		return $this->build_token_map( $attach_id, $usage );
	}

	/**
	 * Ensure image data exists and return base64 string.
	 *
	 * @param array $response
	 * @throws Exception
	 * @return string
	 */
	protected function validate_and_get_b64( array $response ): string {
		if ( empty( $response['data'][0]['b64_json'] ) ) {
			throw new Exception( 'OpenAI returned no image data.' );
		}
		return $response['data'][0]['b64_json'];
	}

	/**
	 * Decode base64 image to binary.
	 *
	 * @param string $b64
	 * @throws Exception
	 * @return string
	 */
	protected function decode_image( string $b64 ): string {

		// Decode the base64 image from the response.
		$raw = base64_decode( $b64 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		// If the decoding failed, throw an exception.
		if ( false === $raw ) {
			throw new Exception( 'Failed to base64-decode image data.' );
		}

		// Return the decoded image data.
		return $raw;
	}

	/**
	 * Save raw image data to uploads and return the file path.
	 *
	 * @param string $raw
	 * @throws Exception
	 * @return string
	 */
	protected function save_to_disk( string $raw ): string {
		$uploads = wp_upload_dir();

		// Initialize the WordPress filesystem
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() ) {
			throw new Exception( 'Could not initialize WordPress filesystem.' );
		}

		if ( ! $wp_filesystem->is_dir( $uploads['path'] ) ) {
			if ( ! $wp_filesystem->mkdir( $uploads['path'], FS_CHMOD_DIR ) ) {
				throw new Exception( 'Could not create uploads directory.' );
			}
		}

		$ext      = strtolower( $this->output_format );
		$filename = uniqid( 'openai-image-' ) . '.' . $ext;
		$filepath = trailingslashit( $uploads['path'] ) . $filename;

		if ( ! $wp_filesystem->put_contents( $filepath, $raw, FS_CHMOD_FILE ) ) {
			throw new Exception( 'Failed to write image to disk.' );
		}

		return $filepath;
	}

	/**
	 * Insert saved file into WP media library and return attachment ID.
	 *
	 * @param string $filepath
	 * @throws Exception
	 * @return int
	 */
	protected function insert_media( string $filepath ): int {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$filename   = basename( $filepath );
		$mime       = wp_check_filetype( $filename, null )['type'] ?? 'image/' . $this->output_format;
		$attachment = array(
			'post_mime_type' => $mime,
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id  = wp_insert_attachment( $attachment, $filepath );
		if ( is_wp_error( $attach_id ) ) {
			throw new Exception( esc_html( $attach_id->get_error_message() ) );
		}

		$metadata = wp_generate_attachment_metadata( $attach_id, $filepath );
		wp_update_attachment_metadata( $attach_id, $metadata );

		return $attach_id;
	}

	/**
	 * Extract usage metrics from the API response.
	 *
	 * @param array $response
	 * @return array{input_tokens:int,input_image_tokens:int,input_text_tokens:int,output_tokens:int,total_tokens:int}
	 */
	protected function extract_usage( array $response ): array {
		$usage = $response['usage'] ?? array();
		return array(
			'input_tokens'       => $usage['input_tokens'] ?? 0,
			'input_image_tokens' => $usage['input_tokens_details']['image_tokens'] ?? 0,
			'input_text_tokens'  => $usage['input_tokens_details']['text_tokens'] ?? 0,
			'output_tokens'      => $usage['output_tokens'] ?? 0,
			'total_tokens'       => $usage['total_tokens'] ?? 0,
		);
	}

	/**
	 * Build the final token map for Automator.
	 *
	 * @param int   $attach_id
	 * @param array $usage
	 * @return array
	 */
	protected function build_token_map( int $attach_id, array $usage ): array {
		$url = wp_get_attachment_url( $attach_id );
		return array(
			'IMAGE_ID'                  => $attach_id,
			'IMAGE_URL'                 => $url,
			'INPUT_TOKENS'              => $usage['input_tokens'],
			'INPUT_TOKENS_IMAGE_TOKENS' => $usage['input_image_tokens'],
			'INPUT_TOKENS_TEXT_TOKENS'  => $usage['input_text_tokens'],
			'OUTPUT_TOKENS'             => $usage['output_tokens'],
			'TOTAL_TOKENS'              => $usage['total_tokens'],
		);
	}
}
