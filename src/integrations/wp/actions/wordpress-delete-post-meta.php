<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * @property Wp_Helpers $item_helpers
 */
class WP_DELETE_POST_META extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_DELETE_POST_META' );
		$this->set_action_meta( 'WP_POST_ID' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Delete {{a meta key:%2$s}} from {{a post:%1$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta(),
				'WP_META_KEY:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Delete {{a meta key}} from {{a post}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'POST_ID',
				'tokenName' => esc_html_x( 'Post ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'META_KEY',
				'tokenName' => esc_html_x( 'Meta key', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DELETED_VALUE',
				'tokenName' => esc_html_x( 'Deleted value', 'WordPress', 'uncanny-automator' ),
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
				'label'           => esc_html_x( 'Post ID', 'WordPress', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				// Suppress the auto-derived "Post ID" token — POST_ID is
				// declared in define_tokens() as the canonical token instead.
				'relevant_tokens' => array(),
			),
			array(
				'option_code'           => 'WP_META_KEY',
				'label'                 => esc_html_x( 'Meta key', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_search_config( 'post_meta_keys' ),
				// Suppress the auto-derived "Meta key" token — META_KEY is
				// declared in define_tokens() as the canonical token instead.
				'relevant_tokens'       => array(),
			),
			array(
				'option_code' => 'WP_META_VALUE',
				'label'       => esc_html_x( 'Meta value', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
				'description' => esc_html_x( 'When provided, only deletes the meta entry with this specific value.', 'WordPress', 'uncanny-automator' ),
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
		$post_id    = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$meta_key   = sanitize_text_field( $parsed['WP_META_KEY'] ?? '' );
		$meta_value = sanitize_text_field( $parsed['WP_META_VALUE'] ?? '' );

		if ( 0 === $post_id ) {
			$this->add_log_error( esc_html_x( 'Post ID cannot be empty.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		if ( '' === $meta_key ) {
			$this->add_log_error( esc_html_x( 'Meta key cannot be empty.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		$post = get_post( $post_id );

		if ( null === $post ) {
			$this->add_log_error(
				sprintf(
					/* translators: %d: Post ID */
					esc_html_x( 'Post %d does not exist.', 'WordPress', 'uncanny-automator' ),
					$post_id
				)
			);
			return false;
		}

		// Capture existing value before deletion.
		$old_value = get_post_meta( $post_id, $meta_key, true );

		if ( '' !== $meta_value ) {
			$result = delete_post_meta( $post_id, $meta_key, $meta_value );
		} else {
			$result = delete_post_meta( $post_id, $meta_key );
		}

		if ( false === $result ) {
			$this->add_log_error(
				sprintf(
					/* translators: %1$s: Meta key, %2$d: Post ID */
					esc_html_x( 'Failed to delete meta key "%1$s" from post %2$d.', 'WordPress', 'uncanny-automator' ),
					$meta_key,
					$post_id
				)
			);
			return false;
		}

		$this->hydrate_tokens(
			array(
				'POST_ID'       => $post_id,
				'META_KEY'      => $meta_key,
				'DELETED_VALUE' => is_scalar( $old_value ) ? (string) $old_value : wp_json_encode( $old_value ),
			)
		);

		return true;
	}
}
