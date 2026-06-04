<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * @property Wp_Helpers $item_helpers
 */
class WP_UPDATE_MEDIA_ITEM extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_UPDATE_MEDIA' );
		$this->set_action_meta( 'WP_MEDIA_ID' );
		$this->set_requires_user( false );
		$this->set_sentence( sprintf( esc_html_x( 'Update {{a media item:%1$s}} in the media library', 'WordPress', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Update {{a media item}} in the media library', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Media ID', 'WordPress', 'uncanny-automator' ),
				'required'    => true,
			),
			array(
				'option_code' => 'WP_MEDIA_TITLE',
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Title', 'WordPress', 'uncanny-automator' ),
				'required'    => false,
			),
			array(
				'option_code' => 'WP_MEDIA_CAPTION',
				'input_type'  => 'textarea',
				'label'       => esc_html_x( 'Caption', 'WordPress', 'uncanny-automator' ),
				'required'    => false,
			),
			array(
				'option_code' => 'WP_MEDIA_DESCRIPTION',
				'input_type'  => 'textarea',
				'label'       => esc_html_x( 'Description', 'WordPress', 'uncanny-automator' ),
				'required'    => false,
			),
			array(
				'option_code' => 'WP_MEDIA_ALT_TEXT',
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Alt text', 'WordPress', 'uncanny-automator' ),
				'required'    => false,
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$media_id    = absint( $parsed[ $this->get_action_meta() ] );
		$title       = isset( $parsed['WP_MEDIA_TITLE'] ) ? sanitize_text_field( $parsed['WP_MEDIA_TITLE'] ) : '';
		$caption     = isset( $parsed['WP_MEDIA_CAPTION'] ) ? sanitize_text_field( $parsed['WP_MEDIA_CAPTION'] ) : '';
		$description = isset( $parsed['WP_MEDIA_DESCRIPTION'] ) ? sanitize_text_field( $parsed['WP_MEDIA_DESCRIPTION'] ) : '';
		$alt_text    = isset( $parsed['WP_MEDIA_ALT_TEXT'] ) ? sanitize_text_field( $parsed['WP_MEDIA_ALT_TEXT'] ) : '';

		$post = get_post( $media_id );

		if ( null === $post ) {
			$this->add_log_error( esc_html_x( 'Invalid media ID.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		if ( 'attachment' !== $post->post_type ) {
			$this->add_log_error( esc_html_x( 'The specified ID does not belong to a media item.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		$post_data = array( 'ID' => $media_id );

		if ( '' !== $title ) {
			$post_data['post_title'] = $title;
		}

		if ( '' !== $caption ) {
			$post_data['post_excerpt'] = $caption;
		}

		if ( '' !== $description ) {
			$post_data['post_content'] = $description;
		}

		// Only call wp_update_post if there are fields beyond the ID.
		if ( count( $post_data ) > 1 ) {
			$result = wp_update_post( $post_data, true );

			if ( is_wp_error( $result ) ) {
				$this->add_log_error( sprintf( esc_html_x( '(%s)', 'WordPress', 'uncanny-automator' ), $result->get_error_message() ) );
				return false;
			}
		}

		if ( '' !== $alt_text ) {
			update_post_meta( $media_id, '_wp_attachment_image_alt', $alt_text );
		}

		$this->hydrate_tokens(
			array(
				'MEDIA_ID'    => $media_id,
				'MEDIA_TITLE' => get_the_title( $media_id ),
				'MEDIA_URL'   => wp_get_attachment_url( $media_id ),
			)
		);

		return true;
	}
}
