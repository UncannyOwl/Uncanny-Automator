<?php

namespace Uncanny_Automator;

class WP_USERCREATESPOST {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'USERSPOST';
		$this->trigger_meta = 'WPPOSTTYPES';
		if ( is_admin() && empty( $_POST ) ) {
			add_action( 'init', array( $this, 'define_trigger' ), 99 );
		} else {
			$this->define_trigger();
		}
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$all_post_types = $uncanny_automator->helpers->recipe->wp->options->all_post_types( null, 'WPPOSTTYPES', [
			'token'        => false,
			'is_ajax'      => true,
			'target_field' => 'WPTAXONOMIES',
			'endpoint'     => 'select_post_type_taxonomies',
		] );

		// now get regular post types.
		$args = [
			'public'   => true,
			'_builtin' => true,
		];

		$options      = [];
		$options['0'] = __( 'Any post type', 'uncanny-automator' );
		$post_types   = get_post_types( $args, 'object' );
		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				$options[ $post_type->name ] = esc_html( $post_type->labels->singular_name );
			}
		}
		$options                   = array_merge( $options, $all_post_types['options'] );
		$all_post_types['options'] = $options;

		$relevant_tokens = [
			'POSTTITLE'   => __( 'Post title', 'uncanny-automator' ),
			'POSTID'      => __( 'Post ID', 'uncanny-automator' ),
			'POSTURL'     => __( 'Post URL', 'uncanny-automator' ),
			'POSTCONTENT' => __( 'Post content', 'uncanny-automator' ),
		];

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WordPress */
			'sentence'            => sprintf(
				__( 'A user publishes a {{type of:%1$s}} post with {{a taxonomy term:%2$s}} in {{a taxonomy:%3$s}}', 'uncanny-automator' ),
				$this->trigger_meta,
				'WPTAXONOMYTERM' . ':' . $this->trigger_meta,
				'WPTAXONOMIES' . ':' . $this->trigger_meta
			),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr__( 'A user publishes a {{type of}} post with {{a taxonomy term}} in {{a taxonomy}}', 'uncanny-automator' ),
			'action'              => 'transition_post_status',
			'priority'            => 90,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'post_published' ),
			'options'             => [],
			'options_group'       => [
				$this->trigger_meta => [
					$all_post_types,
					/* translators: Noun */
					$uncanny_automator->helpers->recipe->field->select_field_ajax(
						'WPTAXONOMIES',
						esc_attr__( 'Taxonomy', 'uncanny-automator' ),
						[],
						'',
						'',
						false,
						true,
						[
							'target_field' => 'WPTAXONOMYTERM',
							'endpoint'     => 'select_terms_for_selected_taxonomy',
						],
						$relevant_tokens
					),
					$uncanny_automator->helpers->recipe->field->select_field( 'WPTAXONOMYTERM', esc_attr__( 'Taxonomy term', 'uncanny-automator' ) ),
				],
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Fires when a post is transitioned from one status to another.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public function post_published( $new_status, $old_status, $post ) {

		if( 'publish' !== $new_status ){
			return;
		}

		if ( $post->post_type == 'revision' ) {
			// auto save
			return;
		}

		global $uncanny_automator;

		$user_id                = get_current_user_id();
		$recipes                = $uncanny_automator->get->recipes_from_trigger_code( $this->trigger_code );
		$required_post_type     = $uncanny_automator->get->meta_from_recipes( $recipes, 'WPPOSTTYPES' );
		$required_post_taxonomy = $uncanny_automator->get->meta_from_recipes( $recipes, 'WPTAXONOMIES' );
		$required_post_term     = $uncanny_automator->get->meta_from_recipes( $recipes, 'WPTAXONOMYTERM' );

		$matched_recipe_ids = [];
		$terms_list         = [];
		$taxonomy_names     = [];

		$user_obj = get_user_by( 'ID', (int) $post->post_author );

		if ( ! is_wp_error( $user_obj ) ) {
			foreach ( $recipes as $recipe_id => $recipe ) {
				foreach ( $recipe['triggers'] as $trigger ) {

					$trigger_id = $trigger['ID'];

					if ( '0' !== $required_post_term[ $recipe_id ][ $trigger_id ] ) {
						// if the term is specific then tax and post type are also specific
						// check if the post has the required term
						if (
						has_term(
							$required_post_term[ $recipe_id ][ $trigger_id ],
							$required_post_taxonomy[ $recipe_id ][ $trigger_id ],
							$post
						)
						) {
							// Matched the post term
							$matched_recipe_ids[] = [
								'recipe_id'  => $recipe_id,
								'trigger_id' => $trigger_id,
							];

							// Specific Term
							$term = get_term( $required_post_term[ $recipe_id ][ $trigger_id ] );
							$terms_list[] = $term->name;
						}
						continue;
					}

					if ( '0' !== $required_post_taxonomy[ $recipe_id ][ $trigger_id ] ) {
						// let check if the post has any term in the selected taxonomy
						if (
						has_term(
							Null,
							$required_post_taxonomy[ $recipe_id ][ $trigger_id ],
							$post
						)
						) {
							// Matched the post term
							$matched_recipe_ids[] = [
								'recipe_id'  => $recipe_id,
								'trigger_id' => $trigger_id,
							];

							// All Post Terms for specific taxonomy
							$terms = wp_get_post_terms( $post->ID, $required_post_taxonomy[ $recipe_id ][ $trigger_id ] );

							foreach ( $terms as $term ) {
							$terms_list [] = $term->name;
							}
						}
						continue;
					}

					$taxonomies = get_object_taxonomies( $post->post_type, 'object' );

					foreach ( $taxonomies as $taxonomy ) {
						$taxonomy_names [] = $taxonomy->name;
					}

					// All Post Terms for specific taxonomy
					$terms = wp_get_post_terms( $post->ID, $taxonomy_names );

					foreach ( $terms as $term ) {
						$terms_list [] = $term->name;
					}

					if( '0' !== $required_post_type[ $recipe_id ][ $trigger_id ] ){
						if( $post->post_type === $required_post_type[ $recipe_id ][ $trigger_id ]){
							// Matched the post type
							$matched_recipe_ids[] = [
								'recipe_id'  => $recipe_id,
								'trigger_id' => $trigger_id,
							];
						}
						continue;
					}

					// All fields are set to "any" by deductive reasoning
					// Matched the post term
					$matched_recipe_ids[] = [
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					];
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$pass_args = [
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				];

				$args = $uncanny_automator->maybe_add_trigger_entry( $pass_args, false );
				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {

							$trigger_meta = [
								'user_id'        => $user_id,
								'trigger_id'     => $result['args']['trigger_id'],
								'trigger_log_id' => $result['args']['get_trigger_id'],
								'run_number'     => $result['args']['run_number'],
							];

							// Post Title Token
							$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':POSTTITLE';
							$trigger_meta['meta_value'] = maybe_serialize( $post->post_title );
							$uncanny_automator->insert_trigger_meta( $trigger_meta );

							// Post ID Token
							$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':POSTID';
							$trigger_meta['meta_value'] = maybe_serialize( $post->ID );
							$uncanny_automator->insert_trigger_meta( $trigger_meta );

							// Post URL Token
							$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':POSTURL';
							$trigger_meta['meta_value'] = maybe_serialize( get_permalink( $post->ID ) );
							$uncanny_automator->insert_trigger_meta( $trigger_meta );

							// Post Content Token
							$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':POSTCONTENT';
							$trigger_meta['meta_value'] = maybe_serialize( $post->post_content );
							$uncanny_automator->insert_trigger_meta( $trigger_meta );

							// Post Type Token
							$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':WPPOSTTYPES';
							$trigger_meta['meta_value'] = maybe_serialize( $post->post_type );
							$uncanny_automator->insert_trigger_meta( $trigger_meta );

							// Post terms Token
							// All Post Terms for specific taxonomy
							$terms           = wp_get_post_terms( $post->ID, $taxonomy_names );
							$terms_list_save = [];
							foreach ( $terms as $term ) {
								$terms_list_save [] = $term->name;
							}
							$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':WPTAXONOMYTERM';
							$trigger_meta['meta_value'] = maybe_serialize( implode( ", ", $terms_list_save ) );
							$uncanny_automator->insert_trigger_meta( $trigger_meta );

							$uncanny_automator->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}

		return;
	}

}