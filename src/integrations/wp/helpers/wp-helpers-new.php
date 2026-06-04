<?php

namespace Uncanny_Automator\Integrations\Wp;

use Uncanny_Automator\Integrations\Wp\Tokens\Trigger\Loopable\Post_Categories;
use Uncanny_Automator\Integrations\Wp\Tokens\Trigger\Loopable\Post_Tags;
use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Wp_Helpers
 *
 * Modern helper class for the WordPress integration. Lives alongside the old
 * \Uncanny_Automator\Wp_Helpers (which remains for Pro backward-compat). This
 * class copies the essential logic from the old helper so that the old class
 * is never instantiated (avoiding duplicate AJAX hook registration).
 *
 * @package Uncanny_Automator\Integrations\Wp
 */
class Wp_Helpers extends Abstract_Helpers {

	/**
	 * Backward-compat shim so `->options->method()` still resolves.
	 *
	 * @deprecated 7.2
	 * @var Wp_Helpers
	 */
	public $options;

	/**
	 * Backward-compat shim for `->pro->method()`.
	 *
	 * @deprecated 7.2
	 * @var object|null
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options = true;

	/**
	 * Roles removed within the current request, keyed by user_id -> role slug.
	 *
	 * Used to detect the remove-then-re-add no-op cycle that plugins such as
	 * User Role Editor perform on every profile save.
	 *
	 * @var array<int,array<string,bool>>
	 */
	private static $pending_role_removals = array();

	/**
	 * __construct.
	 */
	public function __construct() {
		$this->options = $this;

		// Backward-compat for OLD Pro (< 7.3): wire old Pro's helper into ->pro AND register
		// the legacy Free Wp_Helpers' admin-ajax field endpoints (e.g.
		// select_post_type_taxonomies) with role handlers OFF — this modern helper already
		// owns them (see false arg), and the legacy instance persists via its own hooks.
		//
		// ->pro MUST be wired HERE. In the Free 7.3 + old-Pro combo nothing else sets
		// recipe->wp->pro: Pro's wire_pro_helper_chains() (automator_add_integration_helpers:11)
		// only wires helpers whose class is in the Uncanny_Automator_Pro namespace, but
		// recipe->wp is THIS modern Free helper, so it is skipped and setPro() is never
		// called for wp. Meanwhile old Pro's WP_SETPOSTSTATUS::define_action() dereferences
		// recipe->wp->options->pro->get_post_relevant_tokens() very early — synchronously in
		// the item ctor (init:30) or on wp_loaded:99 (edit pages) — so a null ->pro fatals.
		// Regression-confirmed: removing this line reintroduced that exact fatal. New Pro
		// 7.3+ ships Wp_Pro_Integration and self-wires, so this is a no-op then.
		if ( $this->legacy_pro_active() ) {
			$this->pro = new \Uncanny_Automator_Pro\Wp_Pro_Helpers();
			new \Uncanny_Automator\Wp_Helpers( false );
		}
	}

	/**
	 * Backward-compat shim. No-op — the new class IS the options object.
	 *
	 * @deprecated 7.2
	 *
	 * @param mixed $options Ignored.
	 *
	 * @return void
	 */
	public function setOptions( $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	}

	/**
	 * Backward-compat shim — stores the Pro helper reference.
	 *
	 * @deprecated 7.2
	 *
	 * @param object $pro The Pro helper instance.
	 *
	 * @return void
	 */
	public function setPro( $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		// Reuse the instance the caller passed (old Pro's bootstrap, or new Pro 7.3+)
		// rather than constructing another Wp_Pro_Helpers — its ctor re-registers the
		// same admin-ajax handlers. The ctor already guarantees ->pro is non-null for
		// old Pro, so ignore a null hand-off and keep that instance.
		if ( null !== $pro ) {
			$this->pro = $pro;
		}
	}

	/**
	 * Whether OLD Pro (< 7.3) is the active paired add-on: its root helper class is
	 * present but the new namespaced Pro WP integration is not. Per-integration idiom.
	 *
	 * @return bool
	 */
	private function legacy_pro_active() {
		return class_exists( '\Uncanny_Automator_Pro\Wp_Pro_Helpers' )
			&& ! class_exists( '\Uncanny_Automator_Pro\Integrations\Wp\Wp_Pro_Integration' );
	}

	// =========================================================================
	// Remote_Data handlers — option-data endpoints for the recipe builder.
	//
	// Resolved via REST: POST /wp-json/uap/v2/remote-data/wp/{segment}.
	// The dispatcher reaches each method via $this->{$method}(); visibility is
	// `protected` to keep the surface explicit.
	// =========================================================================

	/**
	 * Remote-data handler: posts of a given post type for the change-post-type cascade.
	 *
	 * Listens to WP_OLD_POST_TYPE; emits posts of that type with an "Any" sentinel
	 * (the legacy "no any when same field" behaviour is preserved by checking the
	 * requesting field id).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_custom_post_by_type( $request ): array {

		$post_type = $request->get_field_value( 'WP_OLD_POST_TYPE' );
		$field_id  = $request->get_field_id();
		$is_any    = 'WP_OLD_POST_TYPE' !== $field_id;

		if ( '' === $post_type ) {
			return $this->remote_data_success( array() );
		}

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

		$posts_list = \Automator()->helpers->recipe->options->wp_query( $args );
		$options    = array();

		if ( $is_any ) {
			$post_type_label = $this->get_post_type_singular_label( $post_type );
			$options[]       = array(
				'value' => '-1',
				// translators: %s: Post type label.
				'text'  => sprintf( esc_html_x( 'Any %s', 'WordPress post type', 'uncanny-automator' ), strtolower( $post_type_label ) ),
			);
		}

		if ( ! empty( $posts_list ) ) {
			foreach ( $posts_list as $post_id => $title ) {
				$post_title = ! empty( $title ) ? $title : sprintf(
					// translators: %1$s: Post ID.
					esc_attr_x( 'ID: %1$s (no title)', 'WordPress', 'uncanny-automator' ),
					$post_id
				);

				$options[] = array(
					'value' => $post_id,
					'text'  => $post_title,
				);
			}
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Get a post type's singular label, falling back to "post" when not found.
	 *
	 * @param string $post_type The post type slug.
	 *
	 * @return string
	 */
	private function get_post_type_singular_label( $post_type ) {

		$pt_object = get_post_type_object( $post_type );

		if ( null === $pt_object || empty( $pt_object->labels->singular_name ) ) {
			return 'post';
		}

		return $pt_object->labels->singular_name;
	}

	/**
	 * Remote-data handler: taxonomies of a post type for the create-post cascade.
	 *
	 * Listens to CREATEPOST; emits the taxonomies registered for that post type.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_specific_post_type_taxonomies( $request ): array {

		$post_type  = $request->get_field_value( 'CREATEPOST' );
		$taxonomies = '' === $post_type ? array() : $this->get_taxonomies( $post_type );

		$options = array();

		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $id => $item ) {
				if ( ! empty( $id ) && ! empty( $item->label ) ) {
					$options[] = array(
						'value' => $id,
						'text'  => $item->label,
					);
				}
			}
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: terms across multiple taxonomies (for create-post TERM field).
	 *
	 * Listens to TAXONOMY (an array of taxonomy slugs); emits terms keyed by
	 * "{taxonomy}:{slug}" so the consumer can persist taxonomy + term.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_specific_taxonomy_terms( $request ): array {

		$values     = $request->get_values();
		$taxonomies = $values['TAXONOMY'] ?? array();

		if ( empty( $taxonomies ) ) {
			return $this->remote_data_success( array() );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return $this->remote_data_success( array() );
		}

		$options = array();

		foreach ( $terms as $term ) {
			if ( ! empty( $term->slug ) && ! empty( $term->name ) ) {
				$options[] = array(
					'value' => $term->taxonomy . ':' . $term->slug,
					'text'  => $term->name,
				);
			}
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Helper method to get posts by post type.
	 *
	 * @param string $post_type The post type.
	 * @param string $group_id  The group ID.
	 *
	 * @return array The options array.
	 */
	private function get_posts_by_post_type_options( $post_type, $group_id = '' ) {

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
		$posts_list = \Automator()->helpers->recipe->options->wp_query( $args, false, esc_html_x( 'Any post', 'WordPress', 'uncanny-automator' ) );

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
					// translators: 1: Post type label.
					'text'  => sprintf( esc_html_x( 'Any %s', 'WordPress post type', 'uncanny-automator' ), strtolower( $post_type_label ) ),
				);
			}

			foreach ( $posts_list as $post_id => $post_title ) {
				// translators: 1: Post ID.
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
					// translators: 1: Post type label.
					'text'  => sprintf( esc_html_x( 'Any %s', 'WordPress post type', 'uncanny-automator' ), strtolower( $post_type_label ) ),
				);
			}
		}

		return $fields;
	}

	/**
	 * Remote-data handler: posts by post type for the create-post PARENT_POST cascade.
	 *
	 * Listens to CREATEPOST. When the cascade pivot is the create-post action's
	 * post-type field, prepends a "No parent" sentinel; otherwise behaves like a
	 * generic posts-by-type cascade.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_posts_by_post_type( $request ): array {

		$values    = $request->get_values();
		$group_id  = $request->get_group_id();
		$post_type = ( 'CREATEPOST' === $group_id ) ? ( $values[ $group_id ] ?? '' ) : '';

		if ( '' === $post_type ) {
			return $this->remote_data_success( array() );
		}

		return $this->remote_data_success( $this->get_posts_by_post_type_options( $post_type, $group_id ) );
	}

	// =========================================================================
	// Legacy dropdown field builders (called by old Pro code via singleton)
	// =========================================================================

	/**
	 * Build the "all posts" dropdown field.
	 *
	 * @param string|null $label       The field label.
	 * @param string      $option_code The option code.
	 * @param bool        $any_option  Whether to include "Any post".
	 *
	 * @return mixed
	 */
	public function all_posts( $label = null, $option_code = 'WPPOST', $any_option = true ) {

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

		$all_posts = \Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr_x( 'Any post', 'WordPress', 'uncanny-automator' ) );

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
	 * Build the "all pages" dropdown field.
	 *
	 * @param string|null $label       The field label.
	 * @param string      $option_code The option code.
	 * @param bool        $any_option  Whether to include "Any page".
	 *
	 * @return mixed
	 */
	public function all_pages( $label = null, $option_code = 'WPPAGE', $any_option = true ) {

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

		$all_pages = \Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr_x( 'Any page', 'WordPress', 'uncanny-automator' ) );

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
	 * Build the "WordPress user roles" dropdown field.
	 *
	 * @param string|null $label       The field label.
	 * @param string      $option_code The option code.
	 * @param bool        $is_any      Whether to include "Any role".
	 *
	 * @return mixed
	 */
	public function wp_user_roles( $label = null, $option_code = 'WPROLE', $is_any = false ) {

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
	 * Get all post types (without relevant tokens).
	 *
	 * @param string|null $label       The field label.
	 * @param string      $option_code The option code.
	 * @param array       $args        Additional arguments.
	 *
	 * @return mixed
	 */
	public function all_post_types( $label = null, $option_code = 'WPPOSTTYPES', $args = array() ) {

		$apply_relevant_tokens = false;

		$post_types = $this->get_post_types_options( $label, $option_code, $args, $apply_relevant_tokens );

		return apply_filters( 'uap_option_all_post_types', $post_types, $option_code, $args, $this );
	}

	/**
	 * Get all WordPress post types (with relevant tokens).
	 *
	 * @param string|null $label       The field label.
	 * @param string      $option_code The option code.
	 * @param array       $args        Additional arguments.
	 *
	 * @return mixed|void
	 */
	public function all_wp_post_types( $label = null, $option_code = 'WPPOSTTYPES', $args = array() ) {

		$apply_relevant_tokens = true;

		$post_types = $this->get_post_types_options( $label, $option_code, $args, $apply_relevant_tokens );

		return apply_filters( 'uap_option_all_wp_post_types', $post_types, $option_code, $args, $this );
	}

	/**
	 * Method get_post_types_options.
	 *
	 * @param string  $label                 The label of the field.
	 * @param string  $option_code           The option code of the field.
	 * @param array   $args                  The field arguments.
	 * @param bool    $apply_relevant_tokens Whether to apply relevant tokens.
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

			$options[ $zero_as_default ] = esc_html_x( 'Any post type', 'WordPress', 'uncanny-automator' );
		}

		$post_types = get_post_types( array(), 'objects' );

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				if ( $this->is_post_type_valid( $post_type ) ) {
					$options[ $post_type->name ] = ( true === $args['plural_label'] )
						? esc_html( $post_type->labels->name )
						: esc_html( $post_type->labels->singular_name );
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
	 * @param object $post_type The post type object.
	 *
	 * @return bool True if post type meets the criteria. Otherwise, false.
	 */
	public function is_post_type_valid( $post_type ) {

		$invalid_post_types = $this->get_disabled_post_types();

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
	 * Conditional child taxonomy checkbox.
	 *
	 * @param string|null $label           The field label.
	 * @param string      $option_code     The option code.
	 * @param string      $comparision_code The comparison code.
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
		);

		return \Automator()->helpers->recipe->field->text( $args );
	}

	/**
	 * Get term child of a parent term from provided post terms.
	 *
	 * @param array  $post_terms     The post terms.
	 * @param int    $parent_term_id The parent term ID.
	 * @param string $taxonomy       The taxonomy.
	 * @param int    $post_id        The post ID.
	 *
	 * @return mixed WP_Term|false
	 */
	public function get_term_child_of( $post_terms, $parent_term_id, $taxonomy, $post_id ) {

		if ( empty( $post_terms ) || ! is_array( $post_terms ) ) {
			return false;
		}

		$allowed_post_types = apply_filters( 'automator_allowed_category_taxonomy_children_post_types', array( 'post' ) );

		if ( ! in_array( get_post_type( $post_id ), $allowed_post_types, true ) ) {
			return false;
		}

		$allowed_taxonomies = apply_filters( 'automator_allowed_category_taxonomy_children_taxonomies', array( 'category' ) );

		if ( ! is_array( $allowed_taxonomies ) || ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
			return false;
		}

		$child_terms = get_term_children( $parent_term_id, $taxonomy );

		if ( empty( $child_terms ) || is_wp_error( $child_terms ) ) {
			return false;
		}

		foreach ( $post_terms as $post_term ) {
			if ( in_array( $post_term->term_id, $child_terms, true ) && $taxonomy === $post_term->taxonomy ) {
				return $post_term;
			}
		}

		return false;
	}

	/**
	 * Returns the common trigger loopable tokens.
	 *
	 * @return array
	 */
	public static function common_trigger_loopable_tokens() {

		return array(
			'WP_POST_CATEGORIES' => Post_Categories::class,
			'WP_POST_TAGS'       => Post_Tags::class,
		);
	}

	/**
	 * Determines whether a comment should be blocked based on Akismet spam filtering.
	 *
	 * @param array  $trigger          The trigger data.
	 * @param string $comment_approved The comment approval status.
	 * @param array  $commentdata      The full comment data array.
	 *
	 * @return bool True if the comment should be blocked.
	 */
	public function should_block_comment_by_akismet( $trigger, $comment_approved, $commentdata ) {

		if ( ! filter_var( $trigger['meta']['AKISMET_CHECK'], FILTER_VALIDATE_BOOLEAN ) ) {
			return false;
		}

		if ( ! defined( 'AKISMET_VERSION' ) ) {
			return false;
		}

		if ( 'spam' === $comment_approved ) {
			return true;
		}

		if ( isset( $commentdata['akismet_result'] ) && 'spam' === $commentdata['akismet_result'] ) {
			return true;
		}

		return false;
	}

	// =========================================================================
	// Role change handlers
	// =========================================================================

	/**
	 * Setup role change handlers to support User Role Editor and other plugins.
	 *
	 * @return void
	 */
	public function setup_role_change_handlers() {

		add_action( 'remove_user_role', array( $this, 'track_role_removal' ), 5, 2 );
		add_action( 'add_user_role', array( $this, 'handle_add_user_role' ), 10, 2 );
		add_action( 'set_user_role', array( $this, 'handle_set_user_role' ), 10, 3 );
	}

	/**
	 * Records that a role was explicitly removed in this request.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $role    The role that was removed.
	 *
	 * @return void
	 */
	public function track_role_removal( $user_id, $role ) {
		self::$pending_role_removals[ $user_id ][ $role ] = true;
	}

	/**
	 * Handles WP_User::add_role() and the add_user_role path inside WP_User::set_role().
	 *
	 * @param int    $user_id The user ID.
	 * @param string $role    The role that was added.
	 *
	 * @return void
	 */
	public function handle_add_user_role( $user_id, $role ) {

		if ( isset( self::$pending_role_removals[ $user_id ][ $role ] ) ) {
			unset( self::$pending_role_removals[ $user_id ][ $role ] );
			return;
		}

		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			return;
		}

		$old_roles = array_values( array_diff( $user->roles, array( $role ) ) );

		do_action( 'automator_user_role_changed', $user_id, $role, $old_roles );
	}

	/**
	 * Handles WP_User::set_role() via the set_user_role hook.
	 *
	 * @param int      $user_id   The user ID.
	 * @param string   $role      The new role.
	 * @param string[] $old_roles The user's roles before the change.
	 *
	 * @return void
	 */
	public function handle_set_user_role( $user_id, $role, $old_roles ) {

		if ( ! in_array( $role, (array) $old_roles, true ) ) {
			return;
		}

		do_action( 'automator_user_role_changed', $user_id, $role, $old_roles );
	}

	// =========================================================================
	// Remote-data handlers (continued) — modern endpoints for the recipe builder.
	// =========================================================================

	/**
	 * Remote-data handler: load all valid post types (with "Any" sentinel).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_post_types( $request ): array {

		unset( $request );

		$options = array(
			array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any post type', 'WordPress', 'uncanny-automator' ),
			),
		);

		foreach ( get_post_types( array(), 'objects' ) as $post_type ) {
			if ( $this->is_post_type_valid( $post_type ) ) {
				$options[] = array(
					'value' => $post_type->name,
					'text'  => esc_html( $post_type->labels->singular_name ),
				);
			}
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: all valid post types, without the "Any post type"
	 * sentinel. For action / condition / loop-filter consumers that target a
	 * concrete post type per row (no wildcard semantics).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_post_types_strict( $request ): array {

		unset( $request );

		$options = array();

		foreach ( get_post_types( array(), 'objects' ) as $post_type ) {
			if ( $this->is_post_type_valid( $post_type ) ) {
				$options[] = array(
					'value' => $post_type->name,
					'text'  => esc_html( $post_type->labels->singular_name ),
				);
			}
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: post types that have at least one taxonomy registered.
	 *
	 * Used by triggers/actions that cascade post_type → taxonomy → term. Hides
	 * post types like `page` that have no taxonomies attached, since picking
	 * them leaves the downstream taxonomy field empty and the recipe can never
	 * fire. Custom post types with a registered custom taxonomy are included
	 * automatically because get_object_taxonomies() picks them up.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_post_types_with_taxonomies( $request ): array {

		unset( $request );

		$options = array(
			array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any post type', 'WordPress', 'uncanny-automator' ),
			),
		);

		foreach ( get_post_types( array(), 'objects' ) as $post_type ) {

			if ( ! $this->is_post_type_valid( $post_type ) ) {
				continue;
			}

			$taxonomies = get_object_taxonomies( $post_type->name );
			if ( empty( $taxonomies ) ) {
				continue;
			}

			$options[] = array(
				'value' => $post_type->name,
				'text'  => esc_html( $post_type->labels->singular_name ),
			);
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: posts filtered by a parent post-type field.
	 *
	 * Cascade: parent supplies a post type slug. When parent is the "Any" sentinel
	 * the response is a single "Any post" entry.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_posts_by_type( $request ): array {

		$values    = $request->get_values();
		$post_type = $this->extract_post_type_from_values( $values );

		if ( '' === $post_type ) {
			if ( in_array( '-1', array_map( 'strval', $values ), true ) ) {
				return $this->remote_data_success(
					array(
						array(
							'value' => '-1',
							'text'  => esc_html_x( 'Any post', 'WordPress', 'uncanny-automator' ),
						),
					)
				);
			}

			return $this->remote_data_success( array() );
		}

		$any_label = sprintf(
			// translators: %s: Post type singular name.
			esc_html_x( 'Any %s', 'WordPress post type', 'uncanny-automator' ),
			strtolower( $this->get_post_type_singular_label( $post_type ) )
		);

		return $this->remote_data_success( $this->build_post_type_options( $post_type, true, $any_label ) );
	}

	/**
	 * Remote-data handler: all published pages.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_pages( $request ): array {

		unset( $request );

		return $this->remote_data_success(
			$this->build_post_type_options( 'page', true, esc_html_x( 'Any page', 'WordPress', 'uncanny-automator' ) )
		);
	}

	/**
	 * Remote-data handler: all published pages without the "Any page" sentinel.
	 *
	 * For action / condition / loop-filter consumers — picking "Any page" has
	 * no meaningful interpretation when the recipe needs to write to a
	 * specific page (e.g. WP_SET_READING_SETTINGS's page_on_front).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_pages_strict( $request ): array {

		unset( $request );

		return $this->remote_data_success(
			$this->build_post_type_options( 'page', false, '' )
		);
	}

	/**
	 * Remote-data handler: all published posts (post_type = post).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_posts( $request ): array {

		unset( $request );

		return $this->remote_data_success(
			$this->build_post_type_options( 'post', true, esc_html_x( 'Any post', 'WordPress', 'uncanny-automator' ) )
		);
	}

	/**
	 * Remote-data handler: all published posts of post type `post`, without
	 * the "Any post" sentinel. For action / condition / loop-filter consumers
	 * that target a concrete post per row.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_posts_strict( $request ): array {

		unset( $request );

		return $this->remote_data_success(
			$this->build_post_type_options( 'post', false, '' )
		);
	}

	/**
	 * Remote-data handler: taxonomies registered for a parent post type.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_taxonomies_by_type( $request ): array {

		$values    = $request->get_values();
		$post_type = $this->extract_post_type_from_values( $values );

		if ( '' === $post_type ) {
			if ( in_array( '-1', array_map( 'strval', $values ), true ) ) {
				return $this->remote_data_success(
					array(
						array(
							'value' => '-1',
							'text'  => esc_html_x( 'Any taxonomy', 'WordPress', 'uncanny-automator' ),
						),
					)
				);
			}

			return $this->remote_data_success( array() );
		}

		$options = array(
			array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any taxonomy', 'WordPress', 'uncanny-automator' ),
			),
		);

		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy ) {
			$options[] = array(
				'value' => $taxonomy->name,
				'text'  => esc_html( $taxonomy->labels->singular_name ),
			);
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: terms registered under a parent taxonomy.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_terms_by_taxonomy( $request ): array {

		$values   = $request->get_values();
		$taxonomy = '';

		foreach ( $values as $value ) {
			if ( ! empty( $value ) && is_string( $value ) && taxonomy_exists( $value ) ) {
				$taxonomy = $value;
				break;
			}
		}

		if ( '' === $taxonomy ) {
			if ( in_array( '-1', array_map( 'strval', $values ), true ) ) {
				return $this->remote_data_success(
					array(
						array(
							'value' => '-1',
							'text'  => esc_html_x( 'Any term', 'WordPress', 'uncanny-automator' ),
						),
					)
				);
			}

			return $this->remote_data_success( array() );
		}

		$options = array(
			array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any term', 'WordPress', 'uncanny-automator' ),
			),
		);

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_name = ! empty( $term->name )
					? $term->name
					// translators: %1$s: Term ID.
					: sprintf( esc_html_x( 'ID: %1$s (no title)', 'WordPress', 'uncanny-automator' ), $term->term_id );

				// term_id is the pre-release shape — triggers' validate() does
				// `absint( $selected_term )` / compares against term_taxonomy_id,
				// and actions do `get_term( absint( $value ), $taxonomy )`. Loop
				// filters that need slug (tax_query `'field' => 'slug'`) use the
				// `term_slugs_by_taxonomy` cascade below instead.
				$options[] = array(
					'value' => (string) $term->term_id,
					'text'  => $term_name,
				);
			}
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: term slugs filtered by a parent taxonomy field.
	 *
	 * Same cascade as `terms_by_taxonomy` but emits `$term->slug` instead of
	 * `$term->term_id`. Loop filters and conditions that build a WP_Query
	 * `tax_query` with `'field' => 'slug'` need this shape; triggers and
	 * actions that read term IDs use `terms_by_taxonomy` instead.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_term_slugs_by_taxonomy( $request ): array {

		$values   = $request->get_values();
		$taxonomy = '';

		foreach ( $values as $value ) {
			if ( ! empty( $value ) && is_string( $value ) && taxonomy_exists( $value ) ) {
				$taxonomy = $value;
				break;
			}
		}

		if ( '' === $taxonomy ) {
			if ( in_array( '-1', array_map( 'strval', $values ), true ) ) {
				return $this->remote_data_success(
					array(
						array(
							'value' => '-1',
							'text'  => esc_html_x( 'Any term', 'WordPress', 'uncanny-automator' ),
						),
					)
				);
			}

			return $this->remote_data_success( array() );
		}

		$options = array(
			array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any term', 'WordPress', 'uncanny-automator' ),
			),
		);

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_name = ! empty( $term->name )
					? $term->name
					// translators: %1$s: Term ID.
					: sprintf( esc_html_x( 'ID: %1$s (no title)', 'WordPress', 'uncanny-automator' ), $term->term_id );

				$options[] = array(
					'value' => $term->slug,
					'text'  => $term_name,
				);
			}
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: all registered user roles (default role is hoisted first).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_roles( $request ): array {

		unset( $request );

		$options = array(
			array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any role', 'WordPress', 'uncanny-automator' ),
			),
		);

		$default_role = get_option( 'default_role', 'subscriber' );

		// Place the default role first.
		if ( isset( wp_roles()->roles[ $default_role ] ) ) {
			$options[] = array(
				'value' => $default_role,
				'text'  => wp_roles()->roles[ $default_role ]['name'],
			);
		}

		foreach ( wp_roles()->roles as $role_name => $role_info ) {
			if ( $role_name !== $default_role ) {
				$options[] = array(
					'value' => $role_name,
					'text'  => $role_info['name'],
				);
			}
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: all registered user roles, without the "Any role"
	 * sentinel. For action / condition / loop-filter consumers that target a
	 * concrete role per row (no wildcard semantics).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_roles_strict( $request ): array {

		unset( $request );

		$options = array();

		$default_role = get_option( 'default_role', 'subscriber' );

		if ( isset( wp_roles()->roles[ $default_role ] ) ) {
			$options[] = array(
				'value' => $default_role,
				'text'  => wp_roles()->roles[ $default_role ]['name'],
			);
		}

		foreach ( wp_roles()->roles as $role_name => $role_info ) {
			if ( $role_name !== $default_role ) {
				$options[] = array(
					'value' => $role_name,
					'text'  => $role_info['name'],
				);
			}
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: all registered post statuses.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_post_statuses( $request ): array {

		unset( $request );

		$options = array();

		foreach ( get_post_stati( array(), 'objects' ) as $name => $status ) {
			$options[] = array(
				'value' => $name,
				'text'  => esc_html( $status->label ),
			);
		}

		// Sort alphabetically by visible label — `get_post_stati()` returns in
		// registration order which is meaningless to end users picking from a
		// dropdown.
		usort(
			$options,
			static function ( $a, $b ) {
				return strcasecmp( $a['text'], $b['text'] );
			}
		);

		return $this->remote_data_success( $options );
	}

	// =========================================================================
	// DRY helpers for remote-data handlers
	// =========================================================================

	/**
	 * Build a post-type dropdown options array.
	 *
	 * @param string $post_type   The post type slug.
	 * @param bool   $include_any Whether to prepend an "Any" option.
	 * @param string $any_label   Label for the Any option.
	 *
	 * @return array
	 */
	private function build_post_type_options( $post_type, $include_any = true, $any_label = '' ): array {

		return automator_wp_query(
			array(
				'post_type'   => $post_type,
				'include_any' => $include_any,
				'any_label'   => $any_label,
			)
		);
	}

	/**
	 * Extract a valid post type from a parent_fields_change values array.
	 *
	 * Iterates through the values and returns the first string that corresponds
	 * to an existing post type.
	 *
	 * @param array $values The values array from the AJAX request.
	 *
	 * @return string The post type slug, or empty string if none found.
	 */
	private function extract_post_type_from_values( array $values ) {

		foreach ( $values as $value ) {
			if ( ! empty( $value ) && is_string( $value ) && post_type_exists( $value ) ) {
				return $value;
			}
		}

		return '';
	}

	// =========================================================================
	// Shared utilities
	// =========================================================================

	/**
	 * Resolve a user from an email, username, or user ID input.
	 *
	 * Tries email first, then login, then numeric ID.
	 *
	 * @param string|int $input The user identifier (email, username, or ID).
	 *
	 * @return \WP_User|false WP_User on success, false on failure.
	 */
	public static function resolve_user( $input ) {

		$input = trim( (string) $input );

		if ( '' === $input ) {
			return false;
		}

		if ( is_email( $input ) ) {
			$user = get_user_by( 'email', $input );
			if ( false !== $user ) {
				return $user;
			}
		}

		$user = get_user_by( 'login', $input );
		if ( false !== $user ) {
			return $user;
		}

		if ( is_numeric( $input ) ) {
			$user = get_user_by( 'ID', absint( $input ) );
			if ( false !== $user ) {
				return $user;
			}
		}

		return false;
	}

	/**
	 * Compare two values using a criteria operator.
	 *
	 * Supports: is, is_not, contains, does_not_contain, is_greater_than,
	 * is_greater_than_or_equal_to, is_less_than, is_less_than_or_equal_to,
	 * starts_with, does_not_start_with, ends_with, does_not_end_with.
	 *
	 * @param string $actual   The actual value.
	 * @param string $criteria The comparison operator.
	 * @param string $expected The expected value.
	 *
	 * @return bool
	 */
	public static function compare_values( $actual, $criteria, $expected ) {

		$actual   = mb_strtolower( (string) $actual );
		$expected = mb_strtolower( (string) $expected );

		switch ( $criteria ) {
			case 'is':
				return $actual == $expected; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			case 'is_not':
				return $actual != $expected; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			case 'contains':
				return false !== mb_strpos( $actual, $expected );
			case 'does_not_contain':
				return false === mb_strpos( $actual, $expected );
			case 'is_greater_than':
				return floatval( $actual ) > floatval( $expected );
			case 'is_greater_than_or_equal_to':
				return floatval( $actual ) >= floatval( $expected );
			case 'is_less_than':
				return floatval( $actual ) < floatval( $expected );
			case 'is_less_than_or_equal_to':
				return floatval( $actual ) <= floatval( $expected );
			case 'starts_with':
				return 0 === mb_strpos( $actual, $expected );
			case 'does_not_start_with':
				return 0 !== mb_strpos( $actual, $expected );
			case 'ends_with':
				return mb_substr( $actual, -mb_strlen( $expected ) ) === $expected;
			case 'does_not_end_with':
				return mb_substr( $actual, -mb_strlen( $expected ) ) !== $expected;
			default:
				return true;
		}
	}

	// =========================================================================
	// Meta key & option name remote-data handlers (search_options)
	// =========================================================================

	/**
	 * Remote-data handler: search unique post meta keys.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_post_meta_keys( $request ): array {

		return $this->remote_data_success(
			$this->search_meta_keys_table( $GLOBALS['wpdb']->postmeta, $request->get_search_query() )
		);
	}

	/**
	 * Remote-data handler: search unique user meta keys.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_user_meta_keys( $request ): array {

		return $this->remote_data_success(
			$this->search_meta_keys_table( $GLOBALS['wpdb']->usermeta, $request->get_search_query() )
		);
	}

	/**
	 * Remote-data handler: search option names from wp_options (excludes transients).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_option_names( $request ): array {

		global $wpdb;

		$q = $request->get_search_query();

		if ( '' === $q ) {
			$results = $wpdb->get_col(
				"SELECT option_name FROM {$wpdb->options}
				WHERE option_name NOT LIKE '\_transient\_%'
				AND option_name NOT LIKE '\_site\_transient\_%'
				ORDER BY option_name ASC LIMIT 100"
			);
		} else {
			$results = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options}
					WHERE option_name LIKE %s
					AND option_name NOT LIKE '\_transient\_%'
					AND option_name NOT LIKE '\_site\_transient\_%'
					ORDER BY option_name ASC LIMIT 100",
					'%' . $wpdb->esc_like( $q ) . '%'
				)
			);
		}

		return $this->remote_data_success( $this->stringify_distinct_options( $results ) );
	}

	/**
	 * Search a meta_key column on a meta table (postmeta or usermeta).
	 *
	 * @param string $table The meta table name.
	 * @param string $query The search term.
	 *
	 * @return array
	 */
	private function search_meta_keys_table( $table, $query ): array {

		global $wpdb;

		if ( '' === $query ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$table} ORDER BY meta_key ASC LIMIT 100" );
		} else {
			$results = $wpdb->get_col(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT DISTINCT meta_key FROM {$table} WHERE meta_key LIKE %s ORDER BY meta_key ASC LIMIT 100",
					'%' . $wpdb->esc_like( $query ) . '%'
				)
			);
		}

		return $this->stringify_distinct_options( $results );
	}

	/**
	 * Map a flat list of strings to value/text option pairs.
	 *
	 * @param array $items Flat list of strings.
	 *
	 * @return array
	 */
	private function stringify_distinct_options( $items ): array {

		$options = array();

		foreach ( (array) $items as $item ) {
			$options[] = array(
				'value' => $item,
				'text'  => $item,
			);
		}

		return $options;
	}

	// =========================================================================
	// Nav menu remote-data handlers
	// =========================================================================

	/**
	 * Remote-data handler: all nav menus on the site.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_nav_menus( $request ): array {

		unset( $request );

		$options = array();

		foreach ( wp_get_nav_menus() as $menu ) {
			$options[] = array(
				'value' => (string) $menu->term_id,
				'text'  => esc_html( $menu->name ),
			);
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: registered nav menu locations.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_menu_locations( $request ): array {

		unset( $request );

		$options = array();

		foreach ( get_registered_nav_menus() as $slug => $label ) {
			$options[] = array(
				'value' => $slug,
				'text'  => esc_html( $label ),
			);
		}

		return $this->remote_data_success( $options );
	}

	// =========================================================================
	// Plugin management remote-data handlers
	// =========================================================================

	/**
	 * Remote-data handler: all installed plugins (label includes version).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_all_plugins( $request ): array {

		unset( $request );

		$options = array();

		foreach ( $this->get_installed_plugins() as $path => $data ) {
			$options[] = array(
				'value' => $path,
				'text'  => esc_html( $data['Name'] . ' (' . $data['Version'] . ')' ),
			);
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: only currently-active plugins.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_active_plugins( $request ): array {

		unset( $request );

		$options        = array();
		$active_plugins = (array) get_option( 'active_plugins', array() );

		foreach ( $this->get_installed_plugins() as $path => $data ) {
			if ( in_array( $path, $active_plugins, true ) ) {
				$options[] = array(
					'value' => $path,
					'text'  => esc_html( $data['Name'] ),
				);
			}
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: only currently-inactive plugins.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_inactive_plugins( $request ): array {

		unset( $request );

		$options        = array();
		$active_plugins = (array) get_option( 'active_plugins', array() );

		foreach ( $this->get_installed_plugins() as $path => $data ) {
			if ( ! in_array( $path, $active_plugins, true ) ) {
				$options[] = array(
					'value' => $path,
					'text'  => esc_html( $data['Name'] ),
				);
			}
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Lazy-load wp-admin's plugin functions and return the installed plugins map.
	 *
	 * @return array
	 */
	private function get_installed_plugins(): array {

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugins();
	}

	// =========================================================================
	// Pro-delegated AJAX methods
	//
	// The Pro integration's register_hooks() hooks these methods to wp_ajax_*
	// actions so that the old Pro helper class does NOT need to be instantiated
	// (avoiding double AJAX hook registration). The logic is copied verbatim
	// from \Uncanny_Automator_Pro\Wp_Pro_Helpers.
	// =========================================================================

	/**
	 * Remote-data handler: terms of a parent taxonomy, with group-aware sentinel.
	 *
	 * The leading sentinel depends on the action's group code:
	 *   - WPREMOVETAXONOMY → "All terms"
	 *   - WPSETTAXONOMY    → no sentinel (forces explicit term)
	 *   - everything else  → "Any term"
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_terms_by_taxonomy_with_groups( $request ): array {

		$values   = $request->get_values();
		$group_id = $request->get_group_id();
		$fields   = array();

		if ( 'WPREMOVETAXONOMY' === $group_id ) {
			$fields[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'All terms', 'WordPress', 'uncanny-automator' ),
			);
		} elseif ( 'WPSETTAXONOMY' !== $group_id ) {
			$fields[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any term', 'WordPress', 'uncanny-automator' ),
			);
		}

		$taxonomy = '';

		foreach ( $values as $value ) {
			if ( ! empty( $value ) && is_string( $value ) && taxonomy_exists( $value ) ) {
				$taxonomy = $value;
				break;
			}
		}

		if ( '' === $taxonomy || '-1' === (string) $taxonomy ) {
			return $this->remote_data_success( $fields );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return $this->remote_data_success( $fields );
		}

		foreach ( $terms as $term ) {
			$term_name = ! empty( $term->name )
				? $term->name
				// translators: 1: Term ID.
				: sprintf( esc_html_x( 'ID: %1$s (no title)', 'WordPress', 'uncanny-automator' ), $term->term_id );

			$fields[] = array(
				'value' => $term->term_id,
				'text'  => $term_name,
			);
		}

		return $this->remote_data_success( $fields );
	}

	/**
	 * Remote-data handler: posts of a parent post type, with "All {post type}" sentinel.
	 *
	 * The sentinel uses the post type's plural label and "All" wording, and is
	 * suppressed when group_id === 'SETPOSTMETA' (forces an explicit post pick).
	 *
	 * Pro-bridge replacement for `pro_select_all_posts_by_post_type`.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_all_posts_by_post_type( $request ): array {

		$values    = $request->get_values();
		$group_id  = $request->get_group_id();
		$post_type = $this->extract_post_type_from_values( $values );

		if ( '' === $post_type ) {
			return $this->remote_data_success( array() );
		}

		$args = array(
			// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page'   => 999,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'post_type'        => $post_type,
			'post_status'      => 'publish',
			'suppress_filters' => true,
			'fields'           => array( 'ids', 'titles' ),
		);

		$posts_list   = \Automator()->helpers->recipe->options->wp_query( $args, false, esc_html_x( 'Any Post', 'WordPress', 'uncanny-automator' ) );
		$fields       = array();
		$show_all_row = ( 'SETPOSTMETA' !== $group_id );

		if ( $show_all_row ) {
			$pt_object = get_post_type_object( $post_type );
			$plural    = ( $pt_object && ! empty( $pt_object->labels->name ) ) ? $pt_object->labels->name : 'posts';
			$fields[]  = array(
				'value' => '-1',
				// translators: 1: Post type label (plural).
				'text'  => sprintf( esc_html_x( 'All %s', 'WordPress post type', 'uncanny-automator' ), strtolower( $plural ) ),
			);
		}

		if ( ! empty( $posts_list ) ) {
			foreach ( $posts_list as $post_id => $post_title ) {
				$post_title = ! empty( $post_title )
					? $post_title
					// translators: 1: Post ID.
					: sprintf( esc_html_x( 'ID: %1$s (no title)', 'WordPress', 'uncanny-automator' ), $post_id );

				$fields[] = array(
					'value' => $post_id,
					'text'  => $post_title,
				);
			}
		}

		return $this->remote_data_success( $fields );
	}

	/**
	 * Remote-data handler: taxonomies registered for a parent post type, group-aware.
	 *
	 * Reads the post type from `WPSPOSTTYPES` (trigger fields) or `WPPOSTTYPES`
	 * (action fields), defaulting to `post`. Prepends an "Any taxonomy" sentinel
	 * unless group_id === 'WPSETTAXONOMY'.
	 *
	 * Pro-bridge replacement for `pro_endpoint_all_taxonomies_by_post_type`.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_all_taxonomies_by_post_type( $request ): array {

		$values   = $request->get_values();
		$group_id = $request->get_group_id();
		$fields   = apply_filters( 'automator_endpoint_all_taxonomies_by_post_type_fields_default', array() );

		// Check post type trigger key (Actions VS Triggers).
		$post_type_key = isset( $values['WPSPOSTTYPES'] ) ? 'WPSPOSTTYPES' : false;
		$post_type_key = ! $post_type_key && isset( $values['WPPOSTTYPES'] ) ? 'WPPOSTTYPES' : $post_type_key;

		$request_post_type = $post_type_key ? sanitize_text_field( $values[ $post_type_key ] ) : 'post';
		$post_type         = get_post_type_object( $request_post_type );

		if ( 'WPSETTAXONOMY' !== $group_id ) {
			$fields[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any taxonomy', 'WordPress', 'uncanny-automator' ),
			);
		}

		if ( null !== $post_type ) {
			$taxonomies = get_object_taxonomies( $post_type->name, 'object' );

			if ( ! empty( $taxonomies ) ) {
				foreach ( $taxonomies as $taxonomy ) {
					$fields[] = array(
						'value' => $taxonomy->name,
						'text'  => esc_html( $taxonomy->labels->singular_name ),
					);
				}
			}
		}

		return $this->remote_data_success( $fields );
	}

	/**
	 * Remote-data handler: post meta keys of a parent-selected post, with "Any" sentinel.
	 *
	 * Cascade pivot supplies a post ID; returns the post's user-defined meta
	 * keys (underscore-prefixed keys excluded) prepended with an "Any field"
	 * sentinel.
	 *
	 * Pro-bridge replacement for `pro_select_all_fields_of_selected_post`.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_post_meta_keys_for_post( $request ): array {

		$values  = $request->get_values();
		$post_id = 0;

		foreach ( $values as $value ) {
			if ( ! empty( $value ) && is_numeric( $value ) ) {
				$post_id = (int) $value;
				break;
			}
		}

		$items = array(
			array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any field', 'WordPress', 'uncanny-automator' ),
			),
		);

		if ( 0 === $post_id ) {
			return $this->remote_data_success( $items );
		}

		foreach ( $this->get_post_fields( $post_id ) as $field ) {
			$items[] = array(
				'value' => $field,
				'text'  => $field,
			);
		}

		return $this->remote_data_success( $items );
	}

	/**
	 * Remote-data handler: posts of a parent post type, no "All"/"Any" sentinel.
	 *
	 * Pro-bridge replacement for `pro_select_all_posts_by_post_type_no_all`.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_all_posts_by_post_type_strict( $request ): array {

		$values    = $request->get_values();
		$post_type = $this->extract_post_type_from_values( $values );

		if ( '' === $post_type ) {
			return $this->remote_data_success( array() );
		}

		$args = array(
			// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page'   => 999,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'post_type'        => $post_type,
			'post_status'      => 'publish',
			'suppress_filters' => true,
			'fields'           => array( 'ids', 'titles' ),
		);

		$posts_list = \Automator()->helpers->recipe->options->wp_query( $args, false, esc_html_x( 'Any Post', 'WordPress', 'uncanny-automator' ) );
		$fields     = array();

		if ( ! empty( $posts_list ) ) {
			foreach ( $posts_list as $post_id => $post_title ) {
				$post_title = ! empty( $post_title )
					? $post_title
					// translators: 1: Post ID.
					: sprintf( esc_html_x( 'ID: %1$s (no title)', 'WordPress', 'uncanny-automator' ), $post_id );

				$fields[] = array(
					'value' => $post_id,
					'text'  => $post_title,
				);
			}
		}

		return $this->remote_data_success( $fields );
	}

	/**
	 * Remote-data handler: WordPress users (display name + ID).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_users( $request ): array {

		unset( $request );

		$users   = \Automator()->helpers->recipe->wp_users();
		$options = array();

		foreach ( $users as $user ) {
			$options[] = array(
				'value' => (int) $user->ID,
				'text'  => $user->display_name,
			);
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Get all custom fields (meta keys) for a given post.
	 *
	 * Excludes meta keys starting with an underscore.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array
	 */
	public function get_post_fields( $post_id = 0 ) {

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d",
				$post_id
			),
			OBJECT
		);

		$fields = array();

		if ( empty( $results ) ) {
			return $fields;
		}

		foreach ( $results as $row ) {
			if ( '_' !== substr( $row->meta_key, 0, 1 ) ) {
				$fields[] = $row->meta_key;
			}
		}

		return $fields;
	}
}
