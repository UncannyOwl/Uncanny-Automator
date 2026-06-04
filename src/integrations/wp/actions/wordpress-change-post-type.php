<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_CHANGE_POST_TYPE
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_CHANGE_POST_TYPE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_CHANGE_POST_TYPE' );
		$this->set_action_meta( 'WP_POST_TYPES' );
		$this->set_requires_user( false );
		// translators: 1: New post type, 2: Post title
		$this->set_sentence( sprintf( esc_html_x( 'Change the post type of {{a post:%2$s}} to {{a post type:%1$s}}', 'WordPress', 'uncanny-automator' ), $this->get_action_meta(), 'WP_POSTS:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Change the post type of {{a post}} to {{a post type}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'           => 'WP_OLD_POST_TYPE',
				'input_type'            => 'select',
				'label'                 => esc_html_x( 'Current post type', 'WordPress', 'uncanny-automator' ),
				'options'               => array(),
				'supports_custom_value' => true,
				'required'              => true,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'post_types_strict' ),
			),
			array(
				'option_code'           => 'WP_POSTS',
				'input_type'            => 'select',
				'label'                 => esc_html_x( 'Post', 'WordPress', 'uncanny-automator' ),
				'required'              => true,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_parent_config( 'custom_post_by_type', array( 'WP_OLD_POST_TYPE' ) ),
			),
			array(
				'option_code'           => $this->get_action_meta(),
				'input_type'            => 'select',
				'label'                 => esc_html_x( 'New post type', 'WordPress', 'uncanny-automator' ),
				'options'               => array(),
				'supports_custom_value' => false,
				'required'              => true,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'post_types_strict' ),
			),
		);
	}

	/**
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The arguments.
	 * @param array $parsed      The parsed data.
	 *
	 * @return bool|null
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$old_post_type = sanitize_text_field( $parsed['WP_OLD_POST_TYPE'] );
		$post_id       = absint( sanitize_text_field( $parsed['WP_POSTS'] ) );
		$post_type     = sanitize_text_field( $parsed[ $this->get_action_meta() ] );

		if ( $old_post_type === $post_type ) {
			// translators: 1: Current post type, 2: New post type
			$this->add_log_error( sprintf( esc_html_x( 'Current post type (%1$s) is already set to new post type (%2$s)', 'WordPress', 'uncanny-automator' ), $old_post_type, $post_type ) );

			return null;
		}

		if ( ! get_post( $post_id ) ) {
			$this->add_log_error( esc_html_x( 'Selected post not found', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		$post_type_updated = set_post_type( $post_id, $post_type );

		if ( false === $post_type_updated ) {
			// translators: 1: Post title
			$this->add_log_error( sprintf( esc_html_x( 'Sorry, we were unable to change the post type of the selected post (%s)', 'WordPress', 'uncanny-automator' ), get_the_title( $post_id ) ) );

			return false;
		}

		$this->hydrate_tokens(
			array(
				'WP_OLD_POST_TYPE'       => $old_post_type,
				$this->get_action_meta() => $post_type,
			)
		);

		return true;
	}
}
