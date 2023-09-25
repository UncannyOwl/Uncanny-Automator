<?php


namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Wp_Pro_Helpers;
use WP_Error;

/**
 * Class Wp_Helpers
 *
 * @package Uncanny_Automator
 */
class Wp_Helpers {

	/**
	 * @var Wp_Helpers
	 */
	public $options;
	/**
	 * @var Wp_Pro_Helpers
	 */
	public $pro;

	public $load_options;

	/**
	 * Wp_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = true;

		add_action(
			'wp_ajax_select_custom_post_by_type',
			array(
				$this,
				'select_custom_post_func',
			)
		);

		add_action(
			'wp_ajax_select_post_type_taxonomies',
			array(
				$this,
				'select_post_type_taxonomies',
			)
		);

		add_action(
			'wp_ajax_select_specific_post_type_taxonomies',
			array(
				$this,
				'select_specific_post_type_taxonomies',
			)
		);

		add_action(
			'wp_ajax_select_specific_taxonomy_terms',
			array(
				$this,
				'select_specific_taxonomy_terms',
			)
		);

		add_action(
			'wp_ajax_select_terms_for_selected_taxonomy',
			array(
				$this,
				'select_terms_for_selected_taxonomy',
			)
		);

		add_action(
			'wp_ajax_select_all_post_from_SELECTEDPOSTTYPE',
			array(
				$this,
				'select_posts_by_post_type',
			)
		);

	}

	/**
	 * @param Wp_Helpers $options
	 */
	public function setOptions( Wp_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Wp_Pro_Helpers $pro
	 */
	public function setPro( Wp_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * Return all the specific fields of post type in ajax call
	 */
	public function select_custom_post_func() {

		Automator()->utilities->ajax_auth_check();

		$fields = array();

		if ( ! automator_filter_has_var( 'value', INPUT_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$post_type = automator_filter_input( 'value', INPUT_POST );

		$args = array(
			//phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page'   => apply_filters( 'automator_select_custom_post_limit', 999, $post_type ),
			'orderby'          => 'title',
			'order'            => 'ASC',
			'post_type'        => $post_type,
			'post_status'      => 'publish',
			'suppress_filters' => true,
			'fields'           => array( 'ids', 'titles' ),
		);

		$posts_list = Automator()->helpers->recipe->options->wp_query( $args );

		if ( ! empty( $posts_list ) ) {
			$post_type_label = get_post_type_object( $post_type )->labels->singular_name;
			$fields[]        = array(
				'value' => '-1',
				'text'  => sprintf( _x( 'Any %s', 'WordPress post type', 'uncanny-automator' ), strtolower( $post_type_label ) ),
			);
			foreach ( $posts_list as $post_id => $title ) {

				$post_title = ! empty( $title ) ? $title : sprintf(
				/* translators: %1$s The ID of the post */
					esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ),
					$post_id
				);

				$fields[] = array(
					'value' => $post_id,
					'text'  => $post_title,
				);
			}
		} else {
			$post_type_label = 'post';

			if ( intval( $post_type ) !== intval( '-1' ) ) {
				$post_type_label = get_post_type_object( $post_type )->labels->singular_name;
			}

			$fields[] = array(
				'value' => '-1',
				'text'  => sprintf( _x( 'Any %s', 'WordPress post type', 'uncanny-automator' ), strtolower( $post_type_label ) ),
			);
		}

		echo wp_json_encode( $fields );

		die();

	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_posts( $label = null, $option_code = 'WPPOST', $any_option = true ) {

		if ( ! $this->load_options ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			/* translators: Noun */
			$label = esc_attr__( 'Post', 'uncanny-automator' );
		}

		$args = array(
			//phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page' => apply_filters( 'automator_select_all_posts_limit', 999, 'post' ),
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'post',
			'post_status'    => 'publish',
		);

		$all_posts = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any post', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $all_posts,
			'relevant_tokens' => array(
				$option_code                => esc_attr__( 'Post title', 'uncanny-automator' ),
				$option_code . '_ID'        => esc_attr__( 'Post ID', 'uncanny-automator' ),
				$option_code . '_URL'       => esc_attr__( 'Post URL', 'uncanny-automator' ),
				$option_code . '_CONTENT'   => esc_attr__( 'Post content', 'uncanny-automator' ),
				$option_code . '_EXCERPT'   => esc_attr__( 'Post excerpt', 'uncanny-automator' ),
				$option_code . '_TYPE'      => esc_attr__( 'Post type', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Post featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Post featured image URL', 'uncanny-automator' ),
				'POSTAUTHORFN'              => esc_attr__( 'Post author first name', 'uncanny-automator' ),
				'POSTAUTHORLN'              => esc_attr__( 'Post author last name', 'uncanny-automator' ),
				'POSTAUTHORDN'              => esc_attr__( 'Post author display name', 'uncanny-automator' ),
				'POSTAUTHOREMAIL'           => esc_attr__( 'Post author email', 'uncanny-automator' ),
				'POSTAUTHORURL'             => esc_attr__( 'Post author URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_posts', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_pages( $label = null, $option_code = 'WPPAGE', $any_option = true ) {
		if ( ! $this->load_options ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Page', 'uncanny-automator' );
		}

		$args = array(
			//phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page' => apply_filters( 'automator_select_all_pages_limit', 999, 'page' ),
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_type'      => 'page',
			'post_status'    => 'publish',
		);

		$all_pages = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any page', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $all_pages,
			'relevant_tokens' => array(
				$option_code                => esc_attr__( 'Page title', 'uncanny-automator' ),
				$option_code . '_ID'        => esc_attr__( 'Page ID', 'uncanny-automator' ),
				$option_code . '_URL'       => esc_attr__( 'Page URL', 'uncanny-automator' ),
				$option_code . '_EXCERPT'   => esc_attr__( 'Page excerpt', 'uncanny-automator' ),
				$option_code . '_CONTENT'   => esc_attr__( 'Page content', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Page featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Page featured image URL', 'uncanny-automator' ),
				'POSTAUTHORFN'              => esc_attr__( 'Page author first name', 'uncanny-automator' ),
				'POSTAUTHORLN'              => esc_attr__( 'Page author last name', 'uncanny-automator' ),
				'POSTAUTHORDN'              => esc_attr__( 'Page author display name', 'uncanny-automator' ),
				'POSTAUTHOREMAIL'           => esc_attr__( 'Page author email', 'uncanny-automator' ),
				'POSTAUTHORURL'             => esc_attr__( 'Page author URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_pages', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function wp_user_roles( $label = null, $option_code = 'WPROLE' ) {

		if ( ! $this->load_options ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			/* translators: WordPress role */
			$label = esc_attr__( 'Role', 'uncanny-automator' );
		}

		$roles                  = array();
		$default_role           = get_option( 'default_role', 'subscriber' );
		$roles[ $default_role ] = wp_roles()->roles[ $default_role ]['name'];

		foreach ( wp_roles()->roles as $role_name => $role_info ) {
			if ( $role_name != $default_role ) {
				$roles[ $role_name ] = $role_info['name'];
			}
		}

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $roles,
			'custom_value_description' => esc_attr__( 'Role slug', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_wp_user_roles', $option );
	}

	/**
	 * Get all post types.
	 *
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_post_types( $label = null, $option_code = 'WPPOSTTYPES', $args = array() ) {

		$apply_relevant_tokens = false;

		$post_types = $this->get_post_types_options( $label, $option_code, $args, $apply_relevant_tokens );

		return apply_filters( 'uap_option_all_post_types', $post_types, $option_code, $args, $this );

	}

	/**
	 * Return all the specific taxonomies of selected post type in ajax call
	 */
	public function select_post_type_taxonomies() {

		Automator()->utilities->ajax_auth_check();

		$fields = array();

		if ( ! automator_filter_has_var( 'value', INPUT_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$fields[] = array(
			'value' => '0',
			'text'  => __( 'Any taxonomy', 'uncanny-automator' ),
		);

		$post_type = automator_filter_input( 'value', INPUT_POST );

		$post_type = get_post_type_object( $post_type );

		if ( null !== $post_type ) {

			$output = 'object';

			$taxonomies = get_object_taxonomies( $post_type->name, $output );

			if ( ! empty( $taxonomies ) ) {
				foreach ( $taxonomies as $taxonomy ) {
					$fields[] = array(
						'value' => $taxonomy->name,
						'text'  => esc_html( $taxonomy->labels->singular_name ),
					);
				}
			}
		}

		echo wp_json_encode( $fields );

		die();
	}

	/**
	 * Send a JSON response [value:integer,text:string] back to an Ajax request
	 *
	 * @return string JSON encoded response.
	 */
	public function select_specific_post_type_taxonomies() {

		Automator()->utilities->ajax_auth_check();

		$options = array();

		$group_id   = automator_filter_input( 'group_id', INPUT_POST );
		$taxonomies = $this->get_taxonomies( automator_filter_input( 'value', INPUT_POST ) );
		if ( 'CREATEPOST' === $group_id ) {
			$post_type  = sanitize_text_field( $_POST['values']['CREATEPOST'] ); //phpcs:ignore
			$taxonomies = $this->get_taxonomies( $post_type );
		}

		if ( empty( $taxonomies ) ) {
			wp_send_json( array() );
		}

		foreach ( $taxonomies as $id => $item ) {

			if ( ! empty( $id ) && ! empty( $item->label ) ) {
				$options[] = array(
					'value' => $id,
					'text'  => $item->label,
				);
			}
		}

		wp_send_json( $options );

	}

	/**
	 * Send a JSON response [value:integer,text:string] back to an Ajax request
	 *
	 * @return string JSON encoded response.
	 */
	public function select_specific_taxonomy_terms() {

		Automator()->utilities->ajax_auth_check();

		$options = array();

		$taxonomies = automator_filter_input_array( 'value', INPUT_POST );

		if ( empty( $taxonomies ) ) {
			wp_send_json( array() );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) ) {
			wp_send_json( array() );
		}

		foreach ( $terms as $term ) {
			if ( ! empty( $term->slug ) && ! empty( $term->name ) ) {
				$options[] = array(
					'value' => $term->taxonomy . ':' . $term->slug,
					'text'  => $term->name,
				);
			}
		}

		wp_send_json( $options );

	}

	/**
	 * Return all the specific terms of the selected taxonomy in ajax call
	 */
	public function select_terms_for_selected_taxonomy() {

		Automator()->utilities->ajax_auth_check();

		$fields = array();

		if ( ! automator_filter_has_var( 'value', INPUT_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$fields[] = array(
			'value' => '0',
			'text'  => __( 'Any taxonomy term', 'uncanny-automator' ),
		);

		$taxonomy = automator_filter_input( 'value', INPUT_POST );

		if ( '0' !== $taxonomy ) {

			$taxonomy = get_taxonomy( $taxonomy );

			if ( false !== $taxonomy ) {

				$terms = get_terms(
					array(
						'taxonomy'   => $taxonomy->name,
						'hide_empty' => false,
					)
				);

				if ( ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						/* translators: %1$s The ID of the post. */
						$term_name = ! empty( $term->name ) ? $term->name : sprintf( __( 'ID: %1$s (no title)', 'uncanny-automator' ), $term->term_id );

						$fields[] = array(
							'value' => $term->term_id,
							'text'  => $term_name,
						);
					}
				}
			}
		}

		echo wp_json_encode( $fields );

		die();

	}

	/**
	 * Return all the specific fields of post type in ajax call
	 */
	public function select_posts_by_post_type() {

		global $uncanny_automator;

		$uncanny_automator->utilities->ajax_auth_check();

		$fields = array();

		if ( ! automator_filter_has_var( 'value', INPUT_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$post_type = automator_filter_input( 'value', INPUT_POST );
		$group_id  = automator_filter_input( 'group_id', INPUT_POST );

		$args       = array(
			//phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page'   => apply_filters( 'automator_select_posts_by_post_type_limit', 999, $post_type ),
			'orderby'          => 'title',
			'order'            => 'ASC',
			'post_type'        => $post_type,
			'post_status'      => 'publish',
			'suppress_filters' => true,
			'fields'           => array( 'ids', 'titles' ),
		);
		$posts_list = $uncanny_automator->helpers->recipe->options->wp_query( $args, false, __( 'Any post', 'uncanny-automator' ) );

		if ( 'CREATEPOST' === $group_id ) {
			$fields[] = array(
				'value' => '0',
				'text'  => _x( 'No parent', 'WordPress post parent', 'uncanny-automator' ),
			);
		}

		if ( ! empty( $posts_list ) ) {

			$post_type_label = get_post_type_object( $post_type )->labels->singular_name;

			if ( 'CREATEPOST' !== $group_id ) {
				$fields[] = array(
					'value' => '-1',
					'text'  => sprintf( _x( 'Any %s', 'WordPress post type', 'uncanny-automator' ), strtolower( $post_type_label ) ),
				);
			}
			foreach ( $posts_list as $post_id => $post_title ) {
				// Check if the post title is defined
				$post_title = ! empty( $post_title ) ? $post_title : sprintf( __( 'ID: %1$s (no title)', 'uncanny-automator' ), $post_id );

				$fields[] = array(
					'value' => $post_id,
					'text'  => $post_title,
				);
			}
		} else {
			if ( 'CREATEPOST' !== $group_id ) {
				$post_type_label = 'post';

				if ( intval( $post_type ) !== intval( '-1' ) ) {
					$post_type_label = get_post_type_object( $post_type )->labels->singular_name;
				}

				$fields[] = array(
					'value' => '-1',
					'text'  => sprintf( _x( 'Any %s', 'WordPress post type', 'uncanny-automator' ), strtolower( $post_type_label ) ),
				);
			}
		}

		echo wp_json_encode( $fields );

		die();

	}

	/**
	 * @param null $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed|void
	 */
	public function all_wp_post_types( $label = null, $option_code = 'WPPOSTTYPES', $args = array() ) {

		$apply_relevant_tokens = true;

		$post_types = $this->get_post_types_options( $label, $option_code, $args, $apply_relevant_tokens );

		return apply_filters( 'uap_option_all_wp_post_types', $post_types, $option_code, $args, $this );

	}

	/**
	 * Method get_post_types_options
	 *
	 * @param string $label The label of the field.
	 * @param string $option_code The option code of the field.
	 * @param array $args The field arguments.
	 * @param boolean $apply_relevant_tokens Previous method `all_post_types` does not apply the relevant tokens. Set
	 *                                       to true to apply the 'relevant_tokens' argument.
	 *
	 * @return array The option.
	 */
	public function get_post_types_options( $label = '', $option_code = 'WPPOSTTYPES', $args = array(), $apply_relevant_tokens = true ) {

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
			'default_value'       => null,
		);

		// For backwards compatiblity.
		if ( true === $apply_relevant_tokens ) {

			$defaults['relevant_tokens'] = array(
				$option_code                => __( 'Post title', 'uncanny-automator' ),
				$option_code . '_ID'        => __( 'Post ID', 'uncanny-automator' ),
				$option_code . '_URL'       => __( 'Post URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => __( 'Post featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => __( 'Post featured image URL', 'uncanny-automator' ),
			);

		}

		$args = wp_parse_args( $args, apply_filters( 'automator_all_wp_post_types_defaults', $defaults, $option_code, $args, $this ) );

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

		// Sort alphabetically.
		// asort( $options, SORT_STRING );

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
			'relevant_tokens' => ! empty( $args['relevant_tokens'] ) ? $args['relevant_tokens'] : array(),
			'options_show_id' => $args['options_show_id'],
			'default_value'   => $args['default_value'],
		);

		return apply_filters( 'uap_option_all_wp_post_types', $option );

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
	 * Returns the taxonomies of the given post type.
	 *
	 * @param string $post_type The WordPress post type.
	 *
	 * @return object The taxonomies of a given post type.
	 */
	public function get_taxonomies( $post_type = 'post' ) {

		return get_object_taxonomies( $post_type, 'objects' );

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
