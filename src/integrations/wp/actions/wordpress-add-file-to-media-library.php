<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * @property Wp_Helpers $item_helpers
 */
class WP_ADD_FILE_TO_MEDIA_LIBRARY extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_ADD_FILE_TO_LIBRARY' );
		$this->set_action_meta( 'WP_FILE_URL' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Add {{a file:%1$s}} to the media library', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add {{a file}} to the media library', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'ATTACHMENT_ID',
				'tokenName' => esc_html_x( 'Attachment ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ATTACHMENT_URL',
				'tokenName' => esc_html_x( 'Attachment URL', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'ATTACHMENT_TITLE',
				'tokenName' => esc_html_x( 'Attachment title', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FILE_TYPE',
				'tokenName' => esc_html_x( 'File type', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
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
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'File URL', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'url',
				'required'    => true,
			),
			array(
				'option_code' => 'WP_FILE_TITLE',
				'label'       => esc_html_x( 'Title', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'WP_FILE_DESCRIPTION',
				'label'       => esc_html_x( 'Description', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
			),
			array(
				'option_code' => 'WP_FILE_ALT_TEXT',
				'label'       => esc_html_x( 'Alt text', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
				'description' => esc_html_x( 'Only applies to image files.', 'WordPress', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$url         = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		$title       = sanitize_text_field( $parsed['WP_FILE_TITLE'] ?? '' );
		$description = sanitize_text_field( $parsed['WP_FILE_DESCRIPTION'] ?? '' );
		$alt_text    = sanitize_text_field( $parsed['WP_FILE_ALT_TEXT'] ?? '' );

		if ( '' === $url ) {
			$this->add_log_error( esc_html_x( 'File URL cannot be empty.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$this->add_log_error( esc_html_x( 'The provided URL is not valid.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		// Block requests to private/reserved IP ranges (SSRF protection).
		if ( function_exists( 'automator_resolves_to_private_ip' ) && automator_resolves_to_private_ip( $url ) ) {
			$this->add_log_error( esc_html_x( 'The URL resolves to a private or reserved IP address and cannot be used.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		// Ensure required WordPress media functions are available.
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp_file = download_url( $url );

		if ( is_wp_error( $tmp_file ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s: Error message */
					esc_html_x( 'Failed to download file: %s', 'WordPress', 'uncanny-automator' ),
					$tmp_file->get_error_message()
				)
			);
			return false;
		}

		$file_array = array(
			'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp_file,
		);

		$attachment_id = media_handle_sideload( $file_array, 0 );

		if ( is_wp_error( $attachment_id ) ) {
			// Clean up temp file if sideload failed.
			if ( file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
			}
			$this->add_log_error(
				sprintf(
					/* translators: %s: Error message */
					esc_html_x( 'Failed to add file to the media library: %s', 'WordPress', 'uncanny-automator' ),
					$attachment_id->get_error_message()
				)
			);
			return false;
		}

		// Update optional fields.
		$post_data = array( 'ID' => $attachment_id );

		if ( '' !== $title ) {
			$post_data['post_title'] = $title;
		}

		if ( '' !== $description ) {
			$post_data['post_content'] = $description;
		}

		if ( count( $post_data ) > 1 ) {
			wp_update_post( $post_data );
		}

		if ( '' !== $alt_text ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );
		$mime_type      = get_post_mime_type( $attachment_id );

		$this->hydrate_tokens(
			array(
				'ATTACHMENT_ID'    => $attachment_id,
				'ATTACHMENT_URL'   => $attachment_url,
				'ATTACHMENT_TITLE' => get_the_title( $attachment_id ),
				'FILE_TYPE'        => $mime_type,
			)
		);

		return true;
	}
}
