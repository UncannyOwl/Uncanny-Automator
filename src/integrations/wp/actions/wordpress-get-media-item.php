<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * @property Wp_Helpers $item_helpers
 */
class WP_GET_MEDIA_ITEM extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_GET_MEDIA' );
		$this->set_action_meta( 'WP_MEDIA_ID' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Get {{a media item:%1$s}} from the media library', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Get {{a media item}} from the media library', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'MEDIA_ID',
				'tokenName' => esc_html_x( 'Media ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MEDIA_URL',
				'tokenName' => esc_html_x( 'Media URL', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'MEDIA_TITLE',
				'tokenName' => esc_html_x( 'Media title', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MEDIA_ALT_TEXT',
				'tokenName' => esc_html_x( 'Alt text', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MEDIA_CAPTION',
				'tokenName' => esc_html_x( 'Caption', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MEDIA_DESCRIPTION',
				'tokenName' => esc_html_x( 'Description', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MEDIA_MIME_TYPE',
				'tokenName' => esc_html_x( 'MIME type', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MEDIA_FILE_SIZE',
				'tokenName' => esc_html_x( 'File size', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MEDIA_WIDTH',
				'tokenName' => esc_html_x( 'Width', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MEDIA_HEIGHT',
				'tokenName' => esc_html_x( 'Height', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MEDIA_DATE',
				'tokenName' => esc_html_x( 'Date', 'WordPress', 'uncanny-automator' ),
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
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Media ID', 'WordPress', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				// Suppress the auto-derived "Media ID" token — MEDIA_ID is
				// declared in define_tokens() as the canonical token instead.
				'relevant_tokens' => array(),
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
		$media_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( 0 === $media_id ) {
			$this->add_log_error( esc_html_x( 'Media ID cannot be empty.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		$post = get_post( $media_id );

		if ( null === $post ) {
			$this->add_log_error( esc_html_x( 'Invalid media ID.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		if ( 'attachment' !== $post->post_type ) {
			$this->add_log_error( esc_html_x( 'The specified ID does not belong to a media item.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		$metadata  = wp_get_attachment_metadata( $media_id );
		$file_path = get_attached_file( $media_id );
		$file_size = '';

		if ( false !== $file_path && file_exists( $file_path ) ) {
			$file_size = size_format( filesize( $file_path ) );
		}

		$width  = 0;
		$height = 0;

		if ( is_array( $metadata ) ) {
			$width  = isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
			$height = isset( $metadata['height'] ) ? (int) $metadata['height'] : 0;
		}

		$this->hydrate_tokens(
			array(
				'MEDIA_ID'          => $media_id,
				'MEDIA_URL'         => wp_get_attachment_url( $media_id ),
				'MEDIA_TITLE'       => $post->post_title,
				'MEDIA_ALT_TEXT'    => get_post_meta( $media_id, '_wp_attachment_image_alt', true ),
				'MEDIA_CAPTION'     => $post->post_excerpt,
				'MEDIA_DESCRIPTION' => $post->post_content,
				'MEDIA_MIME_TYPE'   => $post->post_mime_type,
				'MEDIA_FILE_SIZE'   => $file_size,
				'MEDIA_WIDTH'       => $width,
				'MEDIA_HEIGHT'      => $height,
				'MEDIA_DATE'        => $post->post_date,
			)
		);

		return true;
	}
}
