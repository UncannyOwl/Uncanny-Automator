<?php

namespace Uncanny_Automator;

/**
 * Class ELEM_POST_PUBLISHED
 *
 * @package Uncanny_Automator
 */
class ELEM_POST_PUBLISHED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'ELEM';

	/**
	 * Property trigger_code
	 *
	 * @var string
	 */
	private $trigger_code;

	/**
	 * Property trigger_meta
	 *
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Property match_recipes
	 *
	 * @var array
	 */
	private $matched_recipes = array();


	public function __construct() {
		add_action( 'elem_wp_after_insert_post', array( $this, 'post_published' ), 99, 1 );
		$this->trigger_code = 'ELEM_POST_PUBLISHED';
		$this->trigger_meta = 'WPPOSTTYPES';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/elementor/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'type'                => 'anonymous',
			'sentence'            => sprintf(
			/* translators: Logged-in trigger - WordPress */
				esc_attr_x( '{{A type of post:%1$s}} is published with Elementor', 'Elementor', 'uncanny-automator' ),
				$this->trigger_meta
			),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr_x( 'A post is published with Elementor', 'Elementor', 'uncanny-automator' ),
			'action'              => 'wp_insert_post',
			'priority'            => 90,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'schedule_a_post' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Method load_options.
	 *
	 * @return array
	 */
	public function load_options() {

		$all_post_types = Automator()->helpers->recipe->wp->options->all_post_types(
			null,
			'WPPOSTTYPES',
			array(
				'use_zero_as_default' => true,
			)
		);

		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$all_post_types,
				),
			)
		);

		return $options;
	}

	/**
	 * @param $post_id
	 * @param $post
	 * @param $update
	 *
	 * @return bool|void|\WP_Error|null
	 */
	public function schedule_a_post( $post_id, $post, $update ) {
		// only run when posts
		// are published first time
		if ( $update ) {
			return;
		}

		// if post is not published with Elementor.
		$created_by_elem = get_post_meta( $post_id, '_elementor_edit_mode', true );
		if ( empty( $created_by_elem ) ) {
			// Immediately run validate_conditions
			return;
		}

		$cron_enabled = apply_filters( 'automator_elem_user_creates_post_cron_enabled', true, $post_id, $post, $update, $this );

		// Allow people to disable cron processing.
		if ( false === $cron_enabled ) {
			// Immediately run post_publised if cron not enabled.
			$this->post_published( $post_id );

			return;
		}

		if ( wp_next_scheduled( 'elem_wp_after_insert_post', array( $post_id ) ) ) {
			return;
		}

		// Scheduling for 10 sec so that all tax/terms are stored
		return wp_schedule_single_event(
			apply_filters( 'automator_schedule_a_post_time_for_elem', time() + 10, $post_id, $post, $update ),
			'elem_wp_after_insert_post',
			array(
				$post_id,
			)
		);
	}

	/**
	 * Fires when a post is transitioned from one status to another.
	 *
	 * @param $post_id
	 */
	public function post_published( $post_id ) {
		$post               = get_post( $post_id );
		$user_id            = absint( isset( $post->post_author ) ? $post->post_author : wp_get_current_user()->ID );
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_post_type = Automator()->get->meta_from_recipes( $recipes, 'WPPOSTTYPES' );

		// no recipes found, bail
		if ( empty( $recipes ) ) {
			return;
		}

		// Trigger Post types no found
		if ( empty( $required_post_type ) ) {
			return;
		}

		// Match recipe types with current $post
		$post_type_recipes = $this->get_recipes_post_type_matches( $recipes, $required_post_type, $post );

		// No post type matched, bail
		if ( empty( $post_type_recipes ) ) {
			return;
		}

		// Find common recipes between post type + taxonomies + terms
		$matched = $post_type_recipes;

		// build matched recipes ids array
		$matched_recipe_ids = $this->get_matched_recipes( $matched );

		if ( empty( $matched_recipe_ids ) ) {
			return;
		}

		// Complete trigger
		$this->complete_trigger( $matched_recipe_ids, $user_id, $post );
	}

	/**
	 * @param $matched_recipe_ids
	 * @param $user_id
	 * @param $post
	 *
	 * @return void
	 */
	private function complete_trigger( $matched_recipe_ids, $user_id, $post ) {

		foreach ( $matched_recipe_ids as $recipe_trigger ) {

			foreach ( $recipe_trigger as $recipe_id => $trigger_id ) {

				$recipe_id  = absint( $recipe_id );
				$trigger_id = absint( $trigger_id );
				$pass_args  = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $recipe_id,
					'trigger_to_match' => $trigger_id,
					'ignore_post_id'   => true,
					'is_signed_in'     => true,
				);

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );
				if ( empty( $args ) ) {
					continue;
				}
				foreach ( $args as $result ) {
					if ( false === $result['result'] ) {
						continue;
					}
					$this->store_tokens( $result, $user_id, $post, $recipe_id, $trigger_id );
					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}

	/**
	 * @param $result
	 * @param $user_id
	 * @param $post
	 * @param $recipe_id
	 * @param $trigger_id
	 *
	 * @return void
	 */
	private function store_tokens( $result, $user_id, $post, $recipe_id, $trigger_id ) {
		$trigger_meta = array(
			'user_id'        => $user_id,
			'trigger_id'     => $result['args']['trigger_id'],
			'trigger_log_id' => $result['args']['trigger_log_id'],
			'run_number'     => $result['args']['run_number'],
		);

		// post_id Token
		Automator()->db->token->save( 'post_id', $post->ID, $trigger_meta );
	}

	/**
	 * Identify recipes that match criteria based on post type
	 *
	 * @param $recipes
	 * @param $required_post_type
	 * @param $post
	 *
	 * @return array
	 */
	private function get_recipes_post_type_matches( $recipes, $required_post_type, $post ) {
		$matched = array();
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = absint( $trigger['ID'] );
				// required post type
				if ( '0' === (string) $required_post_type[ $recipe_id ][ $trigger_id ] || (string) $post->post_type === (string) $required_post_type[ $recipe_id ][ $trigger_id ] ) {
					$matched[]                           = $recipe_id;
					$this->matched_recipes[ $recipe_id ] = $recipe;
				}
			}
		}

		return array_unique( $matched );
	}

	/**
	 * @param $matched
	 *
	 * @return array
	 */
	private function get_matched_recipes( $matched ) {
		if ( empty( $matched ) || empty( $this->matched_recipes ) ) {
			return array();
		}
		$matched_recipe_ids = array();
		foreach ( $this->matched_recipes as $recipe_id => $recipe ) {
			$recipe_id = absint( $recipe_id );
			if ( ! in_array( $recipe_id, $matched, true ) ) {
				continue;
			}
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id                         = absint( $trigger['ID'] );
				$matched_recipe_ids[][ $recipe_id ] = $trigger_id;
			}
		}

		return $matched_recipe_ids;
	}
}
