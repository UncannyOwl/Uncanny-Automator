<?php

namespace Uncanny_Automator;

/**
 * Class WPUM_USERREGISTER
 * @package Uncanny_Automator
 */
class WPUM_USERREGISTER {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPUSERMANAGER';

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
		$this->trigger_code = 'WPUMUSERREGISTERED';
		$this->trigger_meta = 'WPUMREGFORM';
		if ( class_exists( 'WPUM_Registration_Forms' ) ) {
			$this->define_trigger();
		}
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {


		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-user-manager/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WP User Manager */
			'sentence'            => sprintf( __( 'A user registers with {{a form:%1$s}}', 'uncanny-automator' ),
				$this->trigger_meta ),
			/* translators: Logged-in trigger - WP User Manager */
			'select_option_name'  => __( 'A user registers with {{a form}}', 'uncanny-automator' ),
			'action'              => 'wpum_after_registration',
			'priority'            => 99,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'wpum_register_user' ),
			'options'             => [
				Automator()->helpers->recipe->wp_user_manager->options->get_all_forms(
					__( 'Form', 'uncanny-automator' ), $this->trigger_meta, [ 'is_any' => true ] ),
			],
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @param $new_user_id
	 * @param $values
	 * @param $form
	 */
	public function wpum_register_user( $new_user_id, $values, $form ) {


		if ( 0 === absint( $new_user_id ) ) {
			// Its a logged in recipe and
			// user ID is 0. Skip process
			return;
		}

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_form      = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( $form->id == $required_form[ $recipe_id ][ $trigger_id ] ||
				     $required_form[ $recipe_id ][ $trigger_id ] == '-1' ) {
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
					'user_id'          => $new_user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
					'is_signed_in'     => true,
				];

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );
				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {
							$trigger_meta = [
								'user_id'        => $new_user_id,
								'trigger_id'     => $result['args']['trigger_id'],
								'trigger_log_id' => $result['args']['get_trigger_id'],
								'run_number'     => $result['args']['run_number'],
							];

							$trigger_meta['meta_key']   = $this->trigger_meta;
							$trigger_meta['meta_value'] = maybe_serialize( $form->name );
							Automator()->insert_trigger_meta( $trigger_meta );

							foreach ( $values['register'] as $key => $value ) {
								$trigger_meta['meta_key']   = $key;
								$trigger_meta['meta_value'] = maybe_serialize( $value );
								Automator()->insert_trigger_meta( $trigger_meta );
							}

							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}
	}

}
