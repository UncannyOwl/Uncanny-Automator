<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * @property Wp_Helpers $item_helpers
 */
class WP_DELETE_MEDIA_ITEM extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_DELETE_MEDIA' );
		$this->set_action_meta( 'WP_MEDIA_ID' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Delete {{a media item:%1$s}} from the media library', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Delete {{a media item}} from the media library', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'DELETED_MEDIA_TITLE',
				'tokenName' => esc_html_x( 'Deleted media title', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DELETED_MEDIA_URL',
				'tokenName' => esc_html_x( 'Deleted media URL', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
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
				'label'       => esc_html_x( 'Media ID', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'WP_FORCE_DELETE',
				'label'       => esc_html_x( 'Force delete', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'required'    => false,
				'default'     => false,
				'description' => esc_html_x( 'When enabled, permanently deletes the file instead of moving to trash.', 'WordPress', 'uncanny-automator' ),
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
		$media_id     = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$force_delete = ! empty( $parsed['WP_FORCE_DELETE'] );

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

		// Capture data before deletion.
		$title = $post->post_title;
		$url   = wp_get_attachment_url( $media_id );

		$result = wp_delete_attachment( $media_id, $force_delete );

		if ( false === $result || null === $result ) {
			$this->add_log_error(
				sprintf(
					/* translators: %d: Media ID */
					esc_html_x( 'Failed to delete media item %d.', 'WordPress', 'uncanny-automator' ),
					$media_id
				)
			);
			return false;
		}

		$this->hydrate_tokens(
			array(
				'DELETED_MEDIA_TITLE' => $title,
				'DELETED_MEDIA_URL'   => $url,
			)
		);

		return true;
	}
}
