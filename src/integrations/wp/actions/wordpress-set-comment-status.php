<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_SET_COMMENT_STATUS
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_SET_COMMENT_STATUS extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_SET_COMMENT_STATUS' );
		$this->set_action_meta( 'WP_COMMENT_ID' );
		$this->set_requires_user( false );
		// translators: 1: Comment ID, 2: Status.
		$this->set_sentence( sprintf( esc_html_x( 'Set the status of {{a comment:%1$s}} to {{a status:%2$s}}', 'WordPress', 'uncanny-automator' ), $this->get_action_meta(), 'WP_COMMENT_STATUS:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Set the status of {{a comment}} to {{a status}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'COMMENT_ID',
				'tokenName' => esc_html_x( 'Comment ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'COMMENT_STATUS',
				'tokenName' => esc_html_x( 'Comment status', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'COMMENT_AUTHOR',
				'tokenName' => esc_html_x( 'Comment author', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Comment ID', 'WordPress', 'uncanny-automator' ),
				'required'    => true,
			),
			array(
				'option_code'           => 'WP_COMMENT_STATUS',
				'input_type'            => 'select',
				'label'                 => esc_html_x( 'Status', 'WordPress', 'uncanny-automator' ),
				'required'              => true,
				'supports_custom_value' => true,
				'options'               => array(
					array(
						'value' => '1',
						'text'  => esc_html_x( 'Approved', 'WordPress', 'uncanny-automator' ),
					),
					array(
						'value' => '0',
						'text'  => esc_html_x( 'Unapproved/Pending', 'WordPress', 'uncanny-automator' ),
					),
					array(
						'value' => 'spam',
						'text'  => esc_html_x( 'Spam', 'WordPress', 'uncanny-automator' ),
					),
				),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The arguments.
	 * @param array $parsed      The parsed data.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$comment_id = absint( sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ) );
		$status     = sanitize_text_field( $parsed['WP_COMMENT_STATUS'] ?? '' );

		if ( 0 === $comment_id ) {
			$this->add_log_error( esc_html_x( 'Invalid comment ID.', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		if ( '' === $status ) {
			$this->add_log_error( esc_html_x( 'Status is required.', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		$comment = get_comment( $comment_id );

		if ( null === $comment ) {
			// translators: %d: Comment ID.
			$this->add_log_error( sprintf( esc_html_x( 'Comment with ID %d does not exist.', 'WordPress', 'uncanny-automator' ), $comment_id ) );

			return false;
		}

		// Map DB values to wp_set_comment_status values.
		$status_map = array(
			'0'    => 'hold',
			'1'    => 'approve',
			'spam' => 'spam',
		);

		$wp_status = isset( $status_map[ $status ] ) ? $status_map[ $status ] : $status;
		$result    = wp_set_comment_status( $comment_id, $wp_status );

		if ( false === $result ) {
			$this->add_log_error( esc_html_x( 'Failed to update the comment status.', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		if ( is_wp_error( $result ) ) {
			$this->add_log_error( $result->get_error_message() );

			return false;
		}

		// Map back to human-readable status labels.
		$status_labels = array(
			'hold'    => esc_html_x( 'Unapproved/Pending', 'WordPress', 'uncanny-automator' ),
			'approve' => esc_html_x( 'Approved', 'WordPress', 'uncanny-automator' ),
			'spam'    => esc_html_x( 'Spam', 'WordPress', 'uncanny-automator' ),
		);

		$this->hydrate_tokens(
			array(
				'COMMENT_ID'     => $comment_id,
				'COMMENT_STATUS' => isset( $status_labels[ $wp_status ] ) ? $status_labels[ $wp_status ] : $wp_status,
				'COMMENT_AUTHOR' => $comment->comment_author,
			)
		);

		return true;
	}
}
