<?php


namespace Uncanny_Automator;

use Uncanny_Automator\Integrations\Wp\Tokens\Trigger\Loopable\Post_Categories;
use Uncanny_Automator\Integrations\Wp\Tokens\Trigger\Loopable\Post_Tags;
use Uncanny_Automator_Pro\Wp_Pro_Helpers;

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

	/**
	 * @var true
	 */
	public $load_options = true;

	/**
	 * @var int
	 */
	private static $internal_post_id = 288662867; // Automator in numbers


	/**
	 * __construct.
	 */
	public function __construct() {

		add_action( 'wp_ajax_select_custom_post_by_type', array( $this, 'select_custom_post_func' ) );
		add_action( 'wp_ajax_select_post_type_taxonomies', array( $this, 'select_post_type_taxonomies' ) );
		add_action(
			'wp_ajax_select_specific_post_type_taxonomies',
			array(
				$this,
				'select_specific_post_type_taxonomies',
			)
		);
		add_action( 'wp_ajax_select_specific_taxonomy_terms', array( $this, 'select_specific_taxonomy_terms' ) );
		add_action(
			'wp_ajax_select_terms_for_selected_taxonomy',
			array(
				$this,
				'select_terms_for_selected_taxonomy',
			)
		);
		add_action( 'wp_ajax_select_all_post_from_SELECTEDPOSTTYPE', array( $this, 'select_posts_by_post_type_legacy' ) );
		add_action( 'wp_ajax_select_posts_by_post_type', array( $this, 'select_posts_by_post_type' ) );

		// Centralized role change handler for compatibility with User Role Editor and other plugins
		$this->setup_role_change_handlers();
	}

	/**
	 * @param Wp_Helpers $options
	 */
	public function setOptions( Wp_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * @param Wp_Pro_Helpers $pro
	 */
	public function setPro( Wp_Pro_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
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
		$field_id  = automator_filter_input( 'field_id', INPUT_POST );
		// REVIEW maybe an array of Field IDs or filter to exclude would be better here?
		$is_any = 'WP_OLD_POST_TYPE' !== $field_id;

		$args = array(
			// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
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

			if ( $is_any ) {
				$fields[] = array(
					'value' => '-1',
					// translators: 1: Post type label
					'text'  => sprintf( esc_html_x( 'Any %s', 'WordPress post type', 'uncanny-automator' ), strtolower( $post_type_label ) ),
				);
			}

			foreach ( $posts_list as $post_id => $title ) {

				$post_title = ! empty( $title ) ? $title : sprintf(
					// translators: 1: Post ID
					esc_attr_x( 'ID: %1$s (no title)', 'WordPress', 'uncanny-automator' ),
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

			if ( $is_any ) {
				$fields[] = array(
					'value' => '-1',
					// translators: 1: Post type label
					'text'  => sprintf( esc_html_x( 'Any %s', 'WordPress post type', 'uncanny-automator' ), strtolower( $post_type_label ) ),
				);
			}
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
			$label = esc_attr_x( 'Post', 'WordPress', 'uncanny-automator' );
		}

		$args = array(
			// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page' => apply_filters( 'automator_select_all_posts_limit', 999, 'post' ),
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'post',
			'post_status'    => 'publish',
		);

		$all_posts = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr_x( 'Any post', 'WordPress', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $all_posts,
			'relevant_tokens' => array(
				$option_code                         => esc_attr_x( 'Post title', 'WordPress', 'uncanny-automator' ),
				$option_code . '_ID'                 => esc_attr_x( 'Post ID', 'WordPress', 'uncanny-automator' ),
				$option_code . '_URL'                => esc_attr_x( 'Post URL', 'WordPress', 'uncanny-automator' ),
				$option_code . '_POSTNAME'           => esc_attr_x( 'Post slug', 'WordPress', 'uncanny-automator' ),
				$option_code . '_CONTENT'            => esc_attr_x( 'Post content (raw)', 'WordPress', 'uncanny-automator' ),
				$option_code . '_CONTENT_BEAUTIFIED' => esc_attr_x( 'Post content (formatted)', 'WordPress', 'uncanny-automator' ),
				$option_code . '_EXCERPT'            => esc_attr_x( 'Post excerpt', 'WordPress', 'uncanny-automator' ),
				$option_code . '_TYPE'               => esc_attr_x( 'Post type', 'WordPress', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'           => esc_attr_x( 'Post featured image ID', 'WordPress', 'uncanny-automator' ),
				$option_code . '_THUMB_URL'          => esc_attr_x( 'Post featured image URL', 'WordPress', 'uncanny-automator' ),
				'POSTAUTHORFN'                       => esc_attr_x( 'Post author first name', 'WordPress', 'uncanny-automator' ),
				'POSTAUTHORLN'                       => esc_attr_x( 'Post author last name', 'WordPress', 'uncanny-automator' ),
				'POSTAUTHORDN'                       => esc_attr_x( 'Post author display name', 'WordPress', 'uncanny-automator' ),
				'POSTAUTHOREMAIL'                    => esc_attr_x( 'Post author email', 'WordPress', 'uncanny-automator' ),
				'POSTAUTHORURL'                      => esc_attr_x( 'Post author URL', 'WordPress', 'uncanny-automator' ),
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
			$label = esc_attr_x( 'Page', 'WordPress', 'uncanny-automator' );
		}

		$args = array(
			// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page' => apply_filters( 'automator_select_all_pages_limit', 999, 'page' ),
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_type'      => 'page',
			'post_status'    => 'publish',
		);

		$all_pages = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr_x( 'Any page', 'WordPress', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $all_pages,
			'relevant_tokens' => array(
				$option_code                         => esc_attr_x( 'Page title', 'WordPress', 'uncanny-automator' ),
				$option_code . '_ID'                 => esc_attr_x( 'Page ID', 'WordPress', 'uncanny-automator' ),
				$option_code . '_URL'                => esc_attr_x( 'Page URL', 'WordPress', 'uncanny-automator' ),
				$option_code . '_POSTNAME'           => esc_attr_x( 'Page slug', 'WordPress', 'uncanny-automator' ),
				$option_code . '_EXCERPT'            => esc_attr_x( 'Page excerpt', 'WordPress', 'uncanny-automator' ),
				$option_code . '_CONTENT'            => esc_attr_x( 'Page content (raw)', 'WordPress', 'uncanny-automator' ),
				$option_code . '_CONTENT_BEAUTIFIED' => esc_attr_x( 'Page content (formatted)', 'WordPress', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'           => esc_attr_x( 'Page featured image ID', 'WordPress', 'uncanny-automator' ),
				$option_code . '_THUMB_URL'          => esc_attr_x( 'Page featured image URL', 'WordPress', 'uncanny-automator' ),
				'POSTAUTHORFN'                       => esc_attr_x( 'Page author first name', 'WordPress', 'uncanny-automator' ),
				'POSTAUTHORLN'                       => esc_attr_x( 'Page author last name', 'WordPress', 'uncanny-automator' ),
				'POSTAUTHORDN'                       => esc_attr_x( 'Page author display name', 'WordPress', 'uncanny-automator' ),
				'POSTAUTHOREMAIL'                    => esc_attr_x( 'Page author email', 'WordPress', 'uncanny-automator' ),
				'POSTAUTHORURL'                      => esc_attr_x( 'Page author URL', 'WordPress', 'uncanny-automator' ),
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
	public function wp_user_roles( $label = null, $option_code = 'WPROLE', $is_any = false ) {

		if ( ! $this->load_options ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			/* translators: WordPress role */
			$label = esc_attr_x( 'Role', 'WordPress', 'uncanny-automator' );
		}

		$roles = array();
		if ( true === $is_any ) {
			$roles['-1'] = esc_attr_x( 'Any role', 'WordPress', 'uncanny-automator' );
		}

		$default_role           = get_option( 'default_role', 'subscriber' );
		$roles[ $default_role ] = wp_roles()->roles[ $default_role ]['name'];

		foreach ( wp_roles()->roles as $role_name => $role_info ) {
			if ( $role_name !== $default_role ) {
				$roles[ $role_name ] = $role_info['name'];
			}
		}

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $roles,
			'custom_value_description' => esc_attr_x( 'Role slug', 'WordPress', 'uncanny-automator' ),
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
			'text'  => esc_html_x( 'Any taxonomy', 'WordPress', 'uncanny-automator' ),
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
		$values     = automator_filter_has_var( 'values', INPUT_POST )
			? automator_filter_input_array( 'values', INPUT_POST )
			: array();
		$taxonomies = array();

		if ( 'CREATEPOST' === $group_id ) {
			$post_type  = $values['CREATEPOST'] ?? '';
			$taxonomies = $this->get_taxonomies( $post_type );
		}

		if ( empty( $taxonomies ) ) {
			wp_send_json(
				array(
					'success' => true,
					'options' => array(),
				)
			);
		}

		foreach ( $taxonomies as $id => $item ) {

			if ( ! empty( $id ) && ! empty( $item->label ) ) {
				$options[] = array(
					'value' => $id,
					'text'  => $item->label,
				);
			}
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * Send a JSON response [value:integer,text:string] back to an Ajax request
	 *
	 * @return string JSON encoded response.
	 */
	public function select_specific_taxonomy_terms() {

		Automator()->utilities->ajax_auth_check();

		$options    = array();
		$values     = automator_filter_has_var( 'values', INPUT_POST )
			? automator_filter_input_array( 'values', INPUT_POST )
			: array();
		$taxonomies = $values['TAXONOMY'] ?? array();

		if ( empty( $taxonomies ) ) {
			wp_send_json(
				array(
					'success' => true,
					'options' => array(),
				)
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) ) {
			wp_send_json(
				array(
					'success' => true,
					'options' => array(),
				)
			);
		}

		foreach ( $terms as $term ) {
			if ( ! empty( $term->slug ) && ! empty( $term->name ) ) {
				$options[] = array(
					'value' => $term->taxonomy . ':' . $term->slug,
					'text'  => $term->name,
				);
			}
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
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
			'text'  => esc_html_x( 'Any taxonomy term', 'WordPress', 'uncanny-automator' ),
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
						$term_name = ! empty( $term->name ) ? $term->name : sprintf( esc_html_x( 'ID: %1$s (no title)', 'WordPress', 'uncanny-automator' ), $term->term_id );

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
	 * Helper method to get posts by post type
	 *
	 * @param string $post_type The post type
	 * @param string $group_id The group ID
	 * @return array The options array
	 */
	private function get_posts_by_post_type_options( $post_type, $group_id = '' ) {
		global $uncanny_automator;

		$fields = array();

		$args       = array(
			// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page'   => apply_filters( 'automator_select_posts_by_post_type_limit', 999, $post_type ),
			'orderby'          => 'title',
			'order'            => 'ASC',
			'post_type'        => $post_type,
			'post_status'      => 'publish',
			'suppress_filters' => true,
			'fields'           => array( 'ids', 'titles' ),
		);
		$posts_list = $uncanny_automator->helpers->recipe->options->wp_query( $args, false, esc_html_x( 'Any post', 'WordPress', 'uncanny-automator' ) );

		if ( 'CREATEPOST' === $group_id ) {
			$fields[] = array(
				'value' => '0',
				'text'  => esc_html_x( 'No parent', 'WordPress post parent', 'uncanny-automator' ),
			);
		}

		if ( ! empty( $posts_list ) ) {

			$post_type_label = get_post_type_object( $post_type )->labels->singular_name;

			if ( 'CREATEPOST' !== $group_id ) {
				$fields[] = array(
					'value' => '-1',
					// translators: 1: Post type label
					'text'  => sprintf( esc_html_x( 'Any %s', 'WordPress post type', 'uncanny-automator' ), strtolower( $post_type_label ) ),
				);
			}
			foreach ( $posts_list as $post_id => $post_title ) {
				// Check if the post title is defined
				// translators: 1: Post ID
				$post_title = ! empty( $post_title ) ? $post_title : sprintf( esc_html_x( 'ID: %1$s (no title)', 'WordPress', 'uncanny-automator' ), $post_id );

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
					// translators: 1: Post type label
					'text'  => sprintf( esc_html_x( 'Any %s', 'WordPress post type', 'uncanny-automator' ), strtolower( $post_type_label ) ),
				);
			}
		}

		return $fields;
	}

	/**
	 * Return all the specific fields of post type in ajax call (legacy format)
	 *
	 */
	public function select_posts_by_post_type_legacy() {

		Automator()->utilities->ajax_auth_check();

		if ( ! automator_filter_has_var( 'value', INPUT_POST ) ) {
			echo wp_json_encode( array() );
			die();
		}

		$post_type = automator_filter_input( 'value', INPUT_POST );
		$group_id  = automator_filter_input( 'group_id', INPUT_POST );

		$fields = $this->get_posts_by_post_type_options( $post_type, $group_id );

		echo wp_json_encode( $fields );

		die();
	}

	/**
	 * Return all the specific fields of post type in ajax call (modern format)
	 */
	public function select_posts_by_post_type() {

		Automator()->utilities->ajax_auth_check();

		if ( ! automator_filter_has_var( 'values', INPUT_POST ) ) {
			wp_send_json(
				array(
					'success' => true,
					'options' => array(),
				)
			);
		}

		$values    = automator_filter_input_array( 'values', INPUT_POST );
		$group_id  = automator_filter_input( 'group_id', INPUT_POST );
		$post_type = '';
		if ( 'CREATEPOST' === $group_id ) {
			$post_type = $values[ $group_id ] ?? '';
		}

		if ( empty( $post_type ) ) {
			wp_send_json(
				array(
					'success' => true,
					'options' => array(),
				)
			);
		}

		$fields = $this->get_posts_by_post_type_options( $post_type, $group_id );

		wp_send_json(
			array(
				'success' => true,
				'options' => $fields,
			)
		);
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
				$option_code                => esc_html_x( 'Post title', 'WordPress', 'uncanny-automator' ),
				$option_code . '_ID'        => esc_html_x( 'Post ID', 'WordPress', 'uncanny-automator' ),
				$option_code . '_URL'       => esc_html_x( 'Post URL', 'WordPress', 'uncanny-automator' ),
				$option_code . '_POSTNAME'  => esc_html_x( 'Post slug', 'WordPress', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_html_x( 'Post featured image ID', 'WordPress', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_html_x( 'Post featured image URL', 'WordPress', 'uncanny-automator' ),
			);

		}

		$args = wp_parse_args( $args, apply_filters( 'automator_all_wp_post_types_defaults', $defaults, $option_code, $args, $this ) );

		$options = array();

		if ( true === $args['is_any'] ) {

			$zero_as_default = ( intval( '-1' ) !== intval( $args['use_zero_as_default'] ) ) ? 0 : intval( '-1' );

			// Backwards compatibility for Any option with value of '0' instead of '-1'.
			$options[ $zero_as_default ] = esc_html_x( 'Any post type', 'WordPress', 'uncanny-automator' );

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
			'label'           => ! empty( $label ) ? $label : esc_html_x( 'Post type', 'WordPress', 'uncanny-automator' ),
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
	 * @return string[]|\WP_Taxonomy[] The taxonomies of a given post type.
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

	/**
	 * Conditional child taxonomy checkbox
	 *
	 * @param string $label
	 * @param string $option_code
	 * @param string $comparision_code
	 *
	 * @return array
	 */
	public function conditional_child_taxonomy_checkbox( $label = null, $option_code = 'WPTAXONOMIES_CHILDREN', $comparision_code = 'WPTAXONOMIES' ) {

		if ( empty( $label ) ) {
			$label = esc_attr_x( 'Also include child categories', 'WordPress', 'uncanny-automator' );
		}

		$args = array(
			'option_code'           => $option_code,
			'label'                 => $label,
			'input_type'            => 'checkbox',
			'required'              => false,
			'exclude_default_token' => true,
			/*
			'dynamic_visibility' => array(
				'default_state'    => 'hidden',
				'visibility_rules' => array(
					array(
						'operator'             => 'AND',
						'rule_conditions'      => array(
							array(
								'option_code' => $comparision_code,
								'compare'     => '==',
								'value'       => 'category',
							),
						),
						'resulting_visibility' => 'show',
					),
				),
			),
			*/
		);

		return Automator()->helpers->recipe->field->text( $args );
	}

	/**
	 * Get term child of a parent term from provided post terms.
	 *
	 * @param array $post_terms
	 * @param int $parent_term_id
	 * @param string $taxonomy
	 * @param int $post_id
	 *
	 * @return mixed WP_Term|false
	 */
	public function get_term_child_of( $post_terms, $parent_term_id, $taxonomy, $post_id ) {

		if ( empty( $post_terms ) || ! is_array( $post_terms ) ) {
			return false;
		}

		// Check Post Type for the post
		$allowed_post_types = apply_filters( 'automator_allowed_category_taxonomy_children_post_types', array( 'post' ) );
		if ( ! in_array( get_post_type( $post_id ), $allowed_post_types, true ) ) {
			return false;
		}

		$allowed_taxonomies = apply_filters( 'automator_allowed_category_taxonomy_children_taxonomies', array( 'category' ) );
		if ( ! is_array( $allowed_taxonomies ) || ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
			return false;
		}

		// Get all child terms of the parent term
		$child_terms = get_term_children( $parent_term_id, $taxonomy );
		if ( empty( $child_terms ) || is_wp_error( $child_terms ) ) {
			return false;
		}

		foreach ( $post_terms as $post_term ) {
			if ( in_array( $post_term->term_id, $child_terms, true ) && $post_term->taxonomy === $taxonomy ) {
				return $post_term;
			}
		}

		return false;
	}

	/**
	 * Returns the common trigger loopable tokens.
	 *
	 * @return array{WP_POST_CATEGORIES: class-string<\Uncanny_Automator\Integrations\Woocommerce\Tokens\Trigger\Loopable\Post_Categories>}
	 */
	public static function common_trigger_loopable_tokens() {

		return array(
			'WP_POST_CATEGORIES' => Post_Categories::class,
			'WP_POST_TAGS'       => Post_Tags::class,
		);
	}

	/**
	 * @param $post_id
	 * @param $action_hook
	 *
	 * @return void
	 */
	public static function add_pending_post( $post_id, $action_hook ) {
		// Add the post ID to the post meta table.
		global $wpdb;
		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => absint( self::$internal_post_id ),
				'meta_key'   => $action_hook,
				'meta_value' => absint( $post_id ),
			),
			array(
				'%d',
				'%s',
				'%d',
			)
		);
	}

	/**
	 * @param $action_hook
	 *
	 * @return array|object|\stdClass[]|null
	 */
	public static function get_pending_posts( $action_hook ) {
		// Retrieve the accumulated post IDs from the postmeta table
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM $wpdb->postmeta
				WHERE meta_key = %s
				AND post_id = %d",
				$action_hook,
				self::$internal_post_id
			)
		);
	}

	/**
	 * @param $post_meta
	 *
	 * @return bool|int|\mysqli_result|null
	 */
	public static function delete_post_after_trigger( $post_meta ) {
		global $wpdb;
		// Delete the post meta
		return $wpdb->delete(
			$wpdb->postmeta,
			array(
				'post_id'    => absint( $post_meta->post_id ),
				'meta_key'   => $post_meta->meta_key,
				'meta_value' => absint( $post_meta->meta_value ),
			),
			array(
				'%d',
				'%s',
				'%d',
			)
		);
	}

	/**
	 * @param $post_id
	 * @param $hook
	 *
	 * @return bool
	 */
	public static function maybe_post_postponed( $post_id, $hook ) {

		// Retrieve the accumulated post IDs from the post meta table.
		$post_metas = self::get_pending_posts( $hook );

		if ( empty( $post_metas ) ) {
			return false;
		}

		$post_ids = array_column( $post_metas, 'meta_value' );
		$post_ids = array_unique( $post_ids );

		// Remove duplicates and convert to integers.
		$post_ids = array_map( 'absint', array_unique( $post_ids ) );

		return in_array( absint( $post_id ), $post_ids, true );
	}

	/**
	 * Requeue the post if it was not processed.
	 *
	 * @param $post_id
	 * @param $action_hook
	 *
	 * @return void
	 */
	public static function requeue_post( $post_id, $action_hook ) {
		// Let's try queueing the post only once to see if the terms are populated in the next run.
		if ( empty( get_post_meta( $post_id, $action_hook, true ) ) ) {
			self::add_pending_post( $post_id, $action_hook );
			add_post_meta( $post_id, $action_hook, wp_date( 'c' ) );
		}
	}

	/**
 * Determines whether a comment should be blocked based on Akismet spam filtering.
 *
 * This method checks if the trigger has enabled the Akismet checkbox,
 * whether the Akismet plugin is active, and if the comment has been flagged
 * as spam either via the comment status or Akismet metadata.
 *
 * @param array  $trigger           The trigger data, including the Akismet checkbox setting.
 * @param string $comment_approved  The comment approval status. Can be '1', '0', or 'spam'.
 * @param array  $commentdata       The full comment data array.
 *
 * @return bool True if the comment should be blocked due to Akismet spam detection, false otherwise.
 */
	public function should_block_comment_by_akismet( $trigger, $comment_approved, $commentdata ) {
		// If user has not enabled Akismet checkbox, skip filtering
		if ( ! filter_var( $trigger['meta']['AKISMET_CHECK'], FILTER_VALIDATE_BOOLEAN ) ) {
			return false;
		}

		// If Akismet plugin is not activated, skip filtering
		if ( ! defined( 'AKISMET_VERSION' ) ) {
			return false;
		}

		// If comment is explicitly marked as spam, skip
		if ( 'spam' === $comment_approved ) {
			return true;
		}

		// If comment is marked as spam by Akismet, skip
		if ( isset( $commentdata['akismet_result'] ) && 'spam' === $commentdata['akismet_result'] ) {
			return true;
		}

		// Default: do not skip
		return false;
	}

	/**
	 * Setup role change handlers to support User Role Editor and other plugins
	 *
	 * This method registers hooks for both WordPress core role change methods:
	 * - set_user_role: Fired when WP_User::set_role() is called (replaces all roles)
	 * - add_user_role: Fired when WP_User::add_role() is called (adds a single role)
	 *
	 * Both hooks are normalized and fire a single internal hook 'automator_user_role_changed'
	 * that triggers can register against for consistent behavior.
	 *
	 * @return void
	 */
	public function setup_role_change_handlers() {
		// WordPress core set_role() - replaces all roles
		add_action( 'set_user_role', array( $this, 'handle_set_user_role' ), 10, 3 );

		// WordPress core add_role() - adds a single role (used by User Role Editor)
		add_action( 'add_user_role', array( $this, 'handle_add_user_role' ), 10, 2 );
	}

	/**
	 * Handle set_user_role hook (WordPress core WP_User::set_role method)
	 *
	 * This hook fires when a user's role is SET (replacing all existing roles).
	 * This is used by WordPress core, profile update screens, and some plugins.
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $role      The new role being set.
	 * @param array  $old_roles Array of the user's previous roles.
	 *
	 * @return void
	 */
	public function handle_set_user_role( $user_id, $role, $old_roles ) {
		/**
		 * Fires when a user's role is changed via any WordPress method.
		 *
		 * This internal hook normalizes both set_user_role and add_user_role
		 * into a single consistent hook for Automator triggers.
		 *
		 * @param int    $user_id   The user ID.
		 * @param string $role      The role that was set or added.
		 * @param array  $old_roles Array of the user's roles before this change.
		 */
		do_action( 'automator_user_role_changed', $user_id, $role, $old_roles );
	}

	/**
	 * Handle add_user_role hook (WordPress core WP_User::add_role method)
	 *
	 * This hook fires when a role is ADDED to a user (keeping existing roles).
	 * This is used by User Role Editor plugin and other role management tools.
	 *
	 * Note: This hook fires AFTER the role has been added to the user.
	 * We reconstruct old_roles by removing the newly added role from current roles.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $role    The role being added.
	 *
	 * @return void
	 */
	public function handle_add_user_role( $user_id, $role ) {
		// Get the user object
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return;
		}

		// Get current roles (after the role was added)
		$current_roles = $user->roles;

		// Reconstruct old_roles by removing the newly added role
		// This works because the add_user_role hook fires AFTER the role is added
		$old_roles = array_diff( $current_roles, array( $role ) );

		/**
		 * Fires when a user's role is changed via any WordPress method.
		 *
		 * This internal hook normalizes both set_user_role and add_user_role
		 * into a single consistent hook for Automator triggers.
		 *
		 * @param int    $user_id   The user ID.
		 * @param string $role      The role that was set or added.
		 * @param array  $old_roles Array of the user's roles before this change.
		 */
		do_action( 'automator_user_role_changed', $user_id, $role, $old_roles );
	}
}
