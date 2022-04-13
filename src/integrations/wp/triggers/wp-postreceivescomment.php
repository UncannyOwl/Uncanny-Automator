<?php

namespace Uncanny_Automator;

/**
 *
 * Class WP_POSTRECEIVESCOMMENT
 *
 * @package Uncanny_Automator
 */
class WP_POSTRECEIVESCOMMENT {

	/**
	 * Integration code
	 *
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
		$this->trigger_code = 'WPCOMMENTRECEIVED';
		$this->trigger_meta = 'USERSPOSTCOMMENT';
		//      if ( is_admin() ) {
		//          add_action( 'wp_loaded', array( $this, 'plugins_loaded' ), 99 );
		//      } else {
		$this->define_trigger();
		//      }
	}

	/**
	 *
	 */
	public function plugins_loaded() {
		$this->define_trigger();
	}

	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WordPress */
			'sentence'            => sprintf( esc_attr__( "{{A user's post:%1\$s}} receives a comment", 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr__( "{{A user's post}} receives a comment", 'uncanny-automator' ),
			'action'              => 'comment_post',
			'priority'            => 90,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'post_receives_comment' ),
			'options_callback'    => array( $this, 'load_options' ),
		);
		Automator()->register->trigger( $trigger );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		
		Automator()->helpers->recipe->wp->options->load_options = true;

		$all_post_types = Automator()->helpers->recipe->wp->options->all_post_types(
			null,
			'WPPOSTTYPES',
			array(
				'token'        => false,
				'is_ajax'      => true,
				'target_field' => $this->trigger_meta,
				'endpoint'     => 'select_all_post_from_SELECTEDPOSTTYPE',
			)
		);

		// now get regular post types.
		$args = array(
			'public'   => true,
			'_builtin' => true,
		);

		$output         = 'object';
		$operator       = 'and';
		$options        = array();
		$options['- 1'] = __( 'Any post type', 'uncanny-automator' );
		$post_types     = get_post_types( $args, $output, $operator );
		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				$options[ $post_type->name ] = esc_html( $post_type->labels->singular_name );
			}
		}
		$options                   = array_merge( $options, $all_post_types['options'] );
		$all_post_types['options'] = $options;

		$temp = array(
			'options_group' => array(
				$this->trigger_meta => array(
					$all_post_types,
					Automator()->helpers->recipe->field->select_field(
						$this->trigger_meta,
						__( 'Post', 'uncanny-automator' ),
						array(),
						null,
						false,
						false,
						array(
							'POSTTITLE'          => esc_attr__( 'Post title', 'uncanny-automator' ),
							'POSTURL'            => esc_attr__( 'Post URL', 'uncanny-automator' ),
							'POSTAUTHORDN'       => esc_attr__( 'Post author', 'uncanny-automator' ),
							'POSTEXCERPT'        => esc_attr__( 'Post excerpt', 'uncanny-automator' ),
							'POSTCOMMENTCONTENT' => esc_attr__( 'Comment', 'uncanny-automator' ),
							'POSTCOMMENTDATE'    => esc_attr__( 'Comment date', 'uncanny-automator' ),
							'POSTCOMMENTEREMAIL' => esc_attr__( 'Commenter email', 'uncanny-automator' ),
							'POSTCOMMENTERNAME'  => esc_attr__( 'Commenter name', 'uncanny-automator' ),
							'POSTCOMMENTSTATUS'  => esc_attr__( 'Commenter status', 'uncanny-automator' ),
						)
					),
				),
			),
		);

		$temp = Automator()->utilities->keep_order_of_options( $temp );

		return $temp;

	}

	/**
	 * @param $comment_id
	 * @param $comment_approved
	 * @param $commentdata
	 */
	public function post_receives_comment( $comment_id, $comment_approved, $commentdata ) {
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_post      = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$user_id            = get_post_field( 'post_author', (int) $commentdata['comment_post_ID'] );
		$user_obj           = get_user_by( 'ID', (int) $user_id );
		$matched_recipe_ids = array();

		//Add where option is set to Any post / specific post
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( - 1 === intval( $required_post[ $recipe_id ][ $trigger_id ] ) ||
					 $required_post[ $recipe_id ][ $trigger_id ] == $commentdata['comment_post_ID'] ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				if ( ! Automator()->is_recipe_completed( $matched_recipe_id['recipe_id'], $user_id ) ) {
					$pass_args = array(
						'code'             => $this->trigger_code,
						'meta'             => $this->trigger_meta,
						'user_id'          => $user_id,
						'recipe_to_match'  => $matched_recipe_id['recipe_id'],
						'trigger_to_match' => $matched_recipe_id['trigger_id'],
						'post_id'          => $commentdata['comment_post_ID'],
					);

					$args = Automator()->maybe_add_trigger_entry( $pass_args, false );
					if ( $args ) {
						foreach ( $args as $result ) {
							if ( true === $result['result'] ) {
								$trigger_meta = array(
									'user_id'        => (int) $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								);

								$trigger_meta['meta_key']   = 'WPPOSTTYPES';
								$trigger_meta['meta_value'] = maybe_serialize( get_post_field( 'post_type', (int) $commentdata['comment_post_ID'] ) );
								Automator()->insert_trigger_meta( $trigger_meta );

								$trigger_meta['meta_key']   = 'POSTTITLE';
								$trigger_meta['meta_value'] = maybe_serialize( get_post_field( 'post_title', (int) $commentdata['comment_post_ID'] ) );
								Automator()->insert_trigger_meta( $trigger_meta );

								$trigger_meta['meta_key']   = 'POSTURL';
								$trigger_meta['meta_value'] = maybe_serialize( get_permalink( (int) $commentdata['comment_post_ID'] ) );
								Automator()->insert_trigger_meta( $trigger_meta );

								$trigger_meta['meta_key']   = 'POSTAUTHORDN';
								$trigger_meta['meta_value'] = maybe_serialize( $user_obj->display_name );
								Automator()->insert_trigger_meta( $trigger_meta );

								$trigger_meta['meta_key']   = 'POSTEXCERPT';
								$trigger_meta['meta_value'] = maybe_serialize( get_the_excerpt( (int) $commentdata['comment_post_ID'] ) );
								Automator()->insert_trigger_meta( $trigger_meta );

								$trigger_meta['meta_key']   = 'POSTCOMMENTCONTENT';
								$trigger_meta['meta_value'] = maybe_serialize( $commentdata['comment_content'] );
								Automator()->insert_trigger_meta( $trigger_meta );

								$trigger_meta['meta_key']   = 'POSTCOMMENTDATE';
								$trigger_meta['meta_value'] = maybe_serialize( $commentdata['comment_date'] );
								Automator()->insert_trigger_meta( $trigger_meta );

								$trigger_meta['meta_key']   = 'POSTCOMMENTERNAME';
								$trigger_meta['meta_value'] = maybe_serialize( $commentdata['comment_author'] );
								Automator()->insert_trigger_meta( $trigger_meta );

								$trigger_meta['meta_key']   = 'POSTCOMMENTEREMAIL';
								$trigger_meta['meta_value'] = maybe_serialize( $commentdata['comment_author_email'] );
								Automator()->insert_trigger_meta( $trigger_meta );

								$trigger_meta['meta_key']   = 'POSTCOMMENTSTATUS';
								$trigger_meta['meta_value'] = maybe_serialize( wp_get_comment_status( (int) $comment_id ) );
								Automator()->insert_trigger_meta( $trigger_meta );

								Automator()->maybe_trigger_complete( $result['args'] );
							}
						}
					}
				}
			}
		}

	}

}
