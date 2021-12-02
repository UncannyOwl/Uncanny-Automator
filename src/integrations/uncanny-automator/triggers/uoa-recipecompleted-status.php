<?php

namespace Uncanny_Automator;

/**
 * Class UOA_RECIPECOMPLETED_STATUS
 *
 * @package Uncanny_Automator
 */
class UOA_RECIPECOMPLETED_STATUS {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UOA';

	/**
	 * @var string
	 */
	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'UOARECIPESSTATUS';
		$this->trigger_meta = 'UOARECIPE';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/automator-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'type'                => 'anonymous',
			/* translators: Logged-in trigger - Uncanny Automator */
			'sentence'            => sprintf( esc_attr__( '{{A recipe:%1$s}} completes with a {{specific:%2$s}} status', 'uncanny-automator' ), $this->trigger_meta, 'RECIPESTATUS' ),
			/* translators: Logged-in trigger - Uncanny Automator */
			'select_option_name'  => esc_attr__( '{{A recipe}} completes with a {{specific}} status', 'uncanny-automator' ),
			'action'              => array( 'automator_recipe_completed', 'automator_recipe_completed_with_errors' ),
			'priority'            => 99,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'on_completion' ),
			'options'             => array(
				Automator()->helpers->recipe->uncanny_automator->options->get_recipes( null, $this->trigger_meta, true ),
				array(
					'option_code'     => 'RECIPESTATUS',
					/* translators: Noun */
					'label'           => esc_attr__( 'Status', 'uncanny-automator' ),
					'input_type'      => 'select',
					'required'        => true,
					'options'         => array(
						'0' => esc_attr__( 'In progress', 'uncanny-automator' ),
						'1' => esc_attr__( 'Completed', 'uncanny-automator' ),
						'2' => esc_attr__( 'Completed with errors', 'uncanny-automator' ),
						//'5' => esc_attr__( 'Scheduled', 'uncanny-automator' ), will be dealt in a separate trigger
						'9' => esc_attr__( 'Completed - do nothing', 'uncanny-automator' ),
					),
					'relevant_tokens' => array(
						'UOARECIPES_recipe_status' => esc_attr__( 'Recipe status', 'uncanny-automator' ),
					),
				),
			),
		);
		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $recipe_id
	 * @param $user_id
	 * @param $recipe_log_id
	 * @param $args
	 */
	public function on_completion( $recipe_id, $user_id, $recipe_log_id, $args ) {
		$recipes              = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_recipe      = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$required_status      = Automator()->get->meta_from_recipes( $recipes, 'RECIPESTATUS' );
		$required_status_text = Automator()->get->meta_from_recipes( $recipes, 'RECIPESTATUS_readable' );
		$matched_recipe_ids   = array();

		global $wpdb;
		// get recipe actions
		$recipe_status = $wpdb->get_var( $wpdb->prepare( "SELECT `completed` FROM {$wpdb->prefix}uap_recipe_log WHERE ID = %d AND automator_recipe_id = %d", $recipe_log_id, $recipe_id ) );

		if ( ! isset( $recipe_status ) ) {
			return;
		}
		//Add where option is set to Any product
		foreach ( $recipes as $_recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = absint( $trigger['ID'] );
				if ( intval( '-1' ) === intval( $required_recipe[ $_recipe_id ][ $trigger_id ] ) || (int) $recipe_id === (int) $required_recipe[ $_recipe_id ][ $trigger_id ] ) {
					if ( ! isset( $required_status[ $_recipe_id ] ) ) {
						continue;
					}
					if ( ! isset( $required_status[ $_recipe_id ][ $trigger_id ] ) ) {
						continue;
					}
					if ( intval( '-1' ) === intval( $required_status[ $_recipe_id ][ $trigger_id ] ) || (int) $required_status[ $_recipe_id ][ $trigger_id ] === (int) $recipe_status ) {
						$matched_recipe_ids[ $_recipe_id ] = array(
							'recipe_id'  => $_recipe_id,
							'trigger_id' => $trigger_id,
						);
					}
				}
			}
		}

		if ( empty( $matched_recipe_ids ) ) {
			return;
		}

		foreach ( $matched_recipe_ids as $matched_recipe_id ) {
			$pass_args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
				'user_id'          => 0,
				'post_id'          => $recipe_id,
			);

			$args = Automator()->maybe_add_trigger_entry( $pass_args, false );
			if ( $args ) {
				foreach ( $args as $result ) {
					if ( true === $result['result'] ) {
						$recipe = get_post( $recipe_id );
						if ( $recipe ) {
							$trigger_meta = array(
								'user_id'        => 0,
								'trigger_id'     => $result['args']['trigger_id'],
								'trigger_log_id' => $result['args']['get_trigger_id'],
								'run_number'     => $result['args']['run_number'],
							);

							$trigger_meta['meta_key']   = 'UOARECIPES_recipe_id';
							$trigger_meta['meta_value'] = $recipe->ID;
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'UOARECIPES_recipe_title';
							$trigger_meta['meta_value'] = $recipe->post_title;
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'UOARECIPES_recipe_status';
							$trigger_meta['meta_value'] = $recipe_status;
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'UOARECIPES_recipe_edit_link';
							$trigger_meta['meta_value'] = get_edit_post_link( $recipe->ID );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'UOARECIPES_recipe_log_url';
							$trigger_meta['meta_value'] = "recipe_id=$recipe_id&user_id=$user_id";
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'UOARECIPES_action_log_url';
							$trigger_meta['meta_value'] = "recipe_id=$recipe_id&user_id=$user_id";
							Automator()->insert_trigger_meta( $trigger_meta );
						}
						Automator()->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}
	}

}
