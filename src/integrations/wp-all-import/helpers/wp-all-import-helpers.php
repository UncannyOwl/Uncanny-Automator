<?php

namespace Uncanny_Automator;

/**
 * Class Wp_All_Import_Helpers
 *
 * @package Uncanny_Automator
 */
class Wp_All_Import_Helpers {

	/**
	 * Method get_post_types_options
	 *
	 * @param string $label The label of the field.
	 * @param string $option_code The option code of the field.
	 * @param array $args The field arguments.
	 * @param boolean $apply_relevant_tokens Previous method `all_post_types` does not apply the relevant tokens. Set to true to apply the 'relevant_tokens' argument.
	 *
	 * @return array The option.
	 */
	public function get_post_types_options( $label = '', $option_code = 'ALLPOSTTYPES', $args = array() ) {

		$defaults = array(
			'token'               => false,
			'comments'            => false,
			'is_ajax'             => false,
			'is_any'              => true,
			'plural_label'        => false,
			'target_field'        => '',
			'endpoint'            => '',
			'options_show_id'     => true,
			'use_zero_as_default' => intval( '-1' ),
		);

		$args = wp_parse_args( $args, $defaults );

		$options = array();

		if ( true === $args['is_any'] ) {
			$zero_as_default = ( intval( '-1' ) !== intval( $args['use_zero_as_default'] ) ) ? 0 : intval( '-1' );

			// Backwards compatibility for Any option with value of '0' instead of '-1'.
			$options[ $zero_as_default ] = __( 'Any post type', 'uncanny-automator' );
		}

		$post_types = get_post_types( array(), 'objects' );

		if ( ! empty( $post_types ) ) {

			foreach ( $post_types as $post_type ) {

				if ( $this->is_post_type_valid( $post_type ) ) {

					$options[ $post_type->name ] = ( true === $args['plural_label'] ) ? esc_html( $post_type->labels->name ) : esc_html( $post_type->labels->singular_name );

				}
			}
		}

		// Dropdown supports comments.
		if ( $args['comments'] ) {

			foreach ( $options as $post_type => $opt ) {

				if ( intval( $post_type ) !== intval( '-1' ) && ! post_type_supports( $post_type, 'comments' ) ) {

					unset( $options[ $post_type ] );

				}
			}
		}

		$option = array(
			'input_type'      => 'select',
			'option_code'     => $option_code,
			'label'           => ! empty( $label ) ? $label : __( 'Post type', 'uncanny-automator' ),
			'required'        => true,
			'supports_tokens' => $args['token'],
			'is_ajax'         => $args['is_ajax'],
			'fill_values_in'  => $args['target_field'],
			'endpoint'        => $args['endpoint'],
			'options'         => $options,
			'relevant_tokens' => array(),
			'options_show_id' => false,
		);

		return apply_filters( 'uap_option_get_post_types_options', $option );

	}

	/**
	 * Method is_post_type_valid.
	 *
	 * @param string $post_type The post type name.
	 *
	 * @return boolean True if post type meets the criteria. Otherwise, false.
	 */
	public function is_post_type_valid( $post_type ) {
		$invalid_post_types = $this->get_disabled_post_types();

		// Disable attachments.
		if ( in_array( $post_type->name, $invalid_post_types, true ) ) {

			return false;

		}

		return ! empty( $post_type->name ) && ! empty( $post_type->labels->name ) && ! empty( $post_type->labels->singular_name );
	}

	/**
	 * Method get_disabled_post_types.
	 *
	 * @return array A list of post types that should be disabled in dropdown.
	 */
	public function get_disabled_post_types() {

		$post_types = array(
			'attachment',
			'uo-action',
			'uo-closure',
			'uo-trigger',
			'uo-recipe',
			'customize_changeset',
			'custom_css',
			'wp_global_styles',
			'wp_template',
			'wp_template_part',
			'wp_block',
			'user_request',
			'oembed_cache',
			'revision',
			'wp_navigation',
			'nav_menu_item',
		);

		return apply_filters( 'automator_wp_get_disabled_post_types', $post_types );

	}

}
