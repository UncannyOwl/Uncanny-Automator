<?php

namespace Uncanny_Automator;

/**
 * Class WP_CHANGE_POST_TYPE
 *
 * @package Uncanny_Automator
 */
class WP_CHANGE_POST_TYPE extends \Uncanny_Automator\Recipe\Action {
	/**
	 * @return mixed
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_CHANGE_POST_TYPE' );
		$this->set_action_meta( 'WP_POST_TYPES' );
		$this->set_requires_user( false );
		$this->set_sentence( sprintf( esc_attr_x( 'Change the post type of {{a post:%2$s}} to {{a post type:%1$s}}', 'WordPress', 'uncanny-automator' ), $this->get_action_meta(), 'WP_POSTS:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Change the post type of {{a post}} to {{a post type}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		$post_type_options = Automator()->helpers->recipe->wp->options->all_wp_post_types( '', '', array( 'is_any' => false ) );
		$post_types        = array();
		foreach ( $post_type_options['options'] as $k => $post_type_option ) {
			$post_types[] = array(
				'text'  => esc_attr_x( $post_type_option, 'WordPress', 'uncanny-automator' ),
				'value' => $k,
			);
		}

		return array(
			array(
				'option_code'           => 'WP_OLD_POST_TYPE',
				'input_type'            => 'select',
				'label'                 => esc_attr_x( 'Current post type', 'WordPress', 'uncanny-automator' ),
				'options'               => $post_types,
				'supports_custom_value' => true,
				'supports_tokens'       => true,
				'relevant_tokens'       => array(),
				'is_ajax'               => true,
				'endpoint'              => 'select_custom_post_by_type',
				'fill_values_in'        => 'WP_POSTS',
				'required'              => true,
			),
			array(
				'option_code'           => 'WP_POSTS',
				'input_type'            => 'select',
				'label'                 => esc_attr_x( 'Post', 'WordPress', 'uncanny-automator' ),
				'required'              => true,
				'supports_custom_value' => true,
				'supports_tokens'       => true,
			),
			array(
				'option_code'           => $this->get_action_meta(),
				'input_type'            => 'select',
				'label'                 => esc_attr_x( 'New post type', 'WordPress', 'uncanny-automator' ),
				'options'               => $post_types,
				'supports_custom_value' => false,
				'required'              => true,
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param       $parsed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$old_post_type = sanitize_text_field( $parsed['WP_OLD_POST_TYPE'] );
		$post_id       = absint( sanitize_text_field( $parsed['WP_POSTS'] ) );
		$post_type     = sanitize_text_field( $parsed[ $this->get_action_meta() ] );

		if ( $old_post_type === $post_type ) {
			$this->add_log_error( sprintf( esc_attr_x( 'Current post type (%1$s) is already set to new post type (%2$s)', 'WordPress', 'uncanny-automator' ), $old_post_type, $post_type ) );

			return null;
		}

		if ( ! get_post( $post_id ) ) {
			$this->add_log_error( esc_attr_x( 'Selected post not found', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		$post_type_updated = set_post_type( $post_id, $post_type );

		if ( false === $post_type_updated ) {
			$this->add_log_error( sprintf( esc_attr_x( 'Sorry, we were unable to change the post type of the selected post (%s)', 'WordPress', 'uncanny-automator-pro' ), get_the_title( $post_id ) ) );

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
