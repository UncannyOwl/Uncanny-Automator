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
		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		add_action( 'wp_ajax_select_custom_post_by_type', array( $this, 'select_custom_post_func' ) );
		add_action( 'wp_ajax_select_post_type_taxonomies', array( $this, 'select_post_type_taxonomies' ) );
		add_action(
			'wp_ajax_select_terms_for_selected_taxonomy',
			array(
				$this,
				'select_terms_for_selected_taxonomy',
			)
		);

		add_action( 'wp_ajax_select_all_post_from_SELECTEDPOSTTYPE', array( $this, 'select_posts_by_post_type' ) );
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
				'text'  => sprintf( _x( 'Any %s', 'WordPress post type', 'uncanny-automator-pro' ), strtolower( $post_type_label ) ),
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
				'text'  => sprintf( _x( 'Any %s', 'WordPress post type', 'uncanny-automator-pro' ), strtolower( $post_type_label ) ),
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
				$option_code . '_EXCERPT'   => esc_attr__( 'Post excerpt', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Post featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Post featured image URL', 'uncanny-automator' ),
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
				$option_code . '_THUMB_ID'  => esc_attr__( 'Page featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Page featured image URL', 'uncanny-automator' ),
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
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_post_types( $label = null, $option_code = 'WPPOSTTYPES', $args = array() ) {
		if ( ! $this->load_options ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Post type', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		$args = array(
			'public'   => true,
			'_builtin' => false,
		);

		$output   = 'object';
		$operator = 'and';

		$post_types = get_post_types( $args, $output, $operator );
		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				$options[ $post_type->name ] = esc_html( $post_type->labels->singular_name );
			}
		}

		$type = 'select';

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		);

		return apply_filters( 'uap_option_all_post_types', $option );
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
		//      if ( empty( automator_filter_input( 'value', INPUT_POST ) ) ) {
		//          echo wp_json_encode( $fields );
		//          die();
		//      }

		$fields[]  = array(
			'value' => '0',
			'text'  => __( 'Any taxonomy', 'uncanny-automator' ),
		);
		$post_type = automator_filter_input( 'value', INPUT_POST );

		$post_type = get_post_type_object( $post_type );

		if ( null !== $post_type ) {
			$output     = 'object';
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
	 * Return all the specific terms of the selected taxonomy in ajax call
	 */
	public function select_terms_for_selected_taxonomy() {
		Automator()->utilities->ajax_auth_check();

		$fields = array();
		if ( ! automator_filter_has_var( 'value', INPUT_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}
		//      if ( empty( automator_filter_input( 'value', INPUT_POST ) ) ) {
		//          echo wp_json_encode( $fields );
		//          die();
		//      }
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

		if ( ! empty( $posts_list ) ) {

			$post_type_label = get_post_type_object( $post_type )->labels->singular_name;

			$fields[] = array(
				'value' => '-1',
				'text'  => sprintf( _x( 'Any %s', 'WordPress post type', 'uncanny-automator' ), strtolower( $post_type_label ) ),
			);
			foreach ( $posts_list as $post_id => $post_title ) {
				// Check if the post title is defined
				$post_title = ! empty( $post_title ) ? $post_title : sprintf( __( 'ID: %1$s (no title)', 'uncanny-automator' ), $post_id );

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
	 * @param null $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed|void
	 */
	public function all_wp_post_types( $label = null, $option_code = 'WPPOSTTYPES', $args = array() ) {

		if ( ! $this->load_options ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = __( 'Post types', 'uncanny-automator' );
		}

		$token           = key_exists( 'token', $args ) ? $args['token'] : false;
		$comments        = key_exists( 'comments', $args ) ? $args['comments'] : false;
		$is_ajax         = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$is_any          = key_exists( 'is_any', $args ) ? $args['is_any'] : true;
		$target_field    = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point       = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$zero_as_default = key_exists( 'use_zero_as_default', $args ) ? 0 : -1;

		$default_tokens = array(
			$option_code                => __( 'Post title', 'uncanny-automator' ),
			$option_code . '_ID'        => __( 'Post ID', 'uncanny-automator' ),
			$option_code . '_URL'       => __( 'Post URL', 'uncanny-automator' ),
			$option_code . '_EXCERPT'   => esc_attr__( 'Post excerpt', 'uncanny-automator' ),
			$option_code . '_THUMB_ID'  => __( 'Post featured image ID', 'uncanny-automator' ),
			$option_code . '_THUMB_URL' => __( 'Post featured image URL', 'uncanny-automator' ),
		);

		$relevant_tokens = key_exists( 'relevant_tokens', $args ) ? $args['relevant_tokens'] : $default_tokens;
		$options         = array();

		if ( $is_any == true ) {
			$options[ $zero_as_default ] = __( 'Any post type', 'uncanny-automator' );
		}

		// now get regular post types.
		$args = array(
			'public'   => true,
			'_builtin' => true,
		);

		$output   = 'object';
		$operator = 'and';

		$post_types = get_post_types( $args, $output, $operator );

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				if ( 'attachment' != $post_type->name ) {
					$options[ $post_type->name ] = esc_html( $post_type->labels->singular_name );
				}
			}
		}

		// now get regular post types.
		$args = array(
			'public'   => false,
			'_builtin' => true,
		);

		$output   = 'object';
		$operator = 'and';

		$post_types = get_post_types( $args, $output, $operator );

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				if ( 'attachment' != $post_type->name ) {
					$options[ $post_type->name ] = esc_html( $post_type->labels->singular_name );
				}
			}
		}

		// get all custom post types
		$args = array(
			'public'   => false,
			'_builtin' => false,
		);

		$output   = 'object';
		$operator = 'and';

		$custom_post_types = get_post_types( $args, $output, $operator );

		if ( ! empty( $custom_post_types ) ) {
			foreach ( $custom_post_types as $custom_post_type ) {
				if ( 'attachment' != $custom_post_type->name ) {
					$options[ $custom_post_type->name ] = esc_html( $custom_post_type->labels->singular_name );
				}
			}
		}
		// get all custom post types
		$args = array(
			'public'   => true,
			'_builtin' => false,
		);

		$output   = 'object';
		$operator = 'and';

		$custom_post_types = get_post_types( $args, $output, $operator );

		if ( ! empty( $custom_post_types ) ) {
			foreach ( $custom_post_types as $custom_post_type ) {
				if ( 'attachment' != $custom_post_type->name ) {
					$options[ $custom_post_type->name ] = esc_html( $custom_post_type->labels->singular_name );
				}
			}
		}

		// post type supports comments
		if ( $comments ) {
			foreach ( $options as $post_type => $opt ) {
				if ( intval( $post_type ) !== intval( '-1' ) && ! post_type_supports( $post_type, 'comments' ) ) {
					unset( $options[ $post_type ] );
				}
			}
		}

		$type = 'select';

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => $relevant_tokens,
		);

		return apply_filters( 'uap_option_all_wp_post_types', $option );
	}
}
