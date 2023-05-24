<?php

namespace Uncanny_Automator;

/**
 * Class WP_VIEWCUSTOMPOST
 *
 * @package Uncanny_Automator
 */
class WP_VIEWCUSTOMPOST {

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
		$this->trigger_code = 'VIEWCUSTOMPOST';
		$this->trigger_meta = 'WPCUSTOMPOST';
		if ( Automator()->helpers->recipe->is_edit_page() ) {
			add_action(
				'wp_loaded',
				function () {
					$this->define_trigger();
				},
				99
			);

			return;
		}
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WordPress */
			'sentence'            => sprintf( esc_attr__( 'A user views {{a custom post type:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr__( 'A user views {{a custom post type}}', 'uncanny-automator' ),
			'action'              => 'template_redirect',
			'priority'            => 90,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'view_post' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * load_options
	 *
	 * @return array
	 */
	public function load_options() {

		Automator()->helpers->recipe->wp->options->load_options = true;

		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->trigger_meta => array(
						Automator()->helpers->recipe->wp->options->all_post_types(
							null,
							'WPPOSTTYPES',
							array(
								'token'        => false,
								'is_ajax'      => true,
								'target_field' => $this->trigger_meta,
								'endpoint'     => 'select_custom_post_by_type',
							)
						),
						/* translators: Noun */
						Automator()->helpers->recipe->field->select(
							array(
								'option_code'     => $this->trigger_meta,
								'label'           => esc_attr__( 'Post', 'uncanny-automator' ),
								'relevant_tokens' => array(),
							)
						),
					),
				),
				'options'       => array(
					Automator()->helpers->recipe->options->number_of_times(),
				),
			)
		);

		return $options;
	}

	/**
	 *
	 */
	public function plugins_loaded() {
		$this->define_trigger();
	}

	/**
	 *
	 */
	public function view_post() {

		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_post_type = Automator()->get->meta_from_recipes( $recipes, 'WPPOSTTYPES' );
		$required_post      = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();
		$user_id            = get_current_user_id();
		if ( empty( $recipes ) ) {
			return;
		}
		if ( empty( $required_post_type ) ) {
			return;
		}
		if ( empty( $required_post ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			return;
		}

		//Add where option is set to Any post / specific post
		foreach ( $recipes as $recipe_id => $recipe ) {

			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if (
					// Check any or specific post type
					(
						intval( '-1' ) === intval( $required_post_type[ $recipe_id ][ $trigger_id ] ) ||
						$post->post_type === $required_post_type[ $recipe_id ][ $trigger_id ]
					) &&
					//check specific or any post
					(
						intval( '-1' ) === intval( $required_post[ $recipe_id ][ $trigger_id ] ) ||
						absint( $post->ID ) === absint( $required_post[ $recipe_id ][ $trigger_id ] )
					)
				) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		if ( empty( $matched_recipe_ids ) ) {
			return;
		}
		foreach ( $matched_recipe_ids as $matched_recipe_id ) {
			$args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'user_id'          => $user_id,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
				'post_id'          => $post->ID,
			);

			$post_type               = get_post_type_object( $post->post_type );
			$args['post_type_label'] = $post_type->labels->singular_name;

			$arr = Automator()->maybe_add_trigger_entry( $args, false );
			if ( empty( $arr ) ) {
				continue;
			}
			foreach ( $arr as $result ) {
				if ( true === $result['result'] ) {
					$result['args']['post_type_label'] = $post_type->labels->singular_name;

					$trigger_meta = array(
						'user_id'        => (int) $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					);

					// post_id Token
					Automator()->db->token->save( 'post_id', $post->ID, $trigger_meta );

					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
