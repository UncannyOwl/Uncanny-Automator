<?php

namespace Uncanny_Automator;

/**
 * Class WPSP_PURCHWITHFORM
 *
 * @package Uncanny_Automator
 */
class WPSP_PURCHWITHFORM {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPSIMPLEPAY';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WPSPPURCHAFORMS';
		$this->trigger_meta = 'WPSPFORMS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-simple-pay/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WP Job Manager */
			'sentence'            => sprintf(
				esc_attr__(
					'A user completes a purchase with {{a form:%1$s}}',
					'uncanny-automator'
				),
				$this->trigger_meta
			),
			/* translators: Logged-in trigger - WP Job Manager */
			'select_option_name'  => esc_attr__(
				'A user completes a purchase with {{a form}}',
				'uncanny-automator'
			),
			'action'              => 'simpay_charge_created',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'simple_pay_charge_created' ),
			'options'             => array(
				Automator()->helpers->recipe->wp_simple_pay->options->list_wp_simpay_forms(
					null,
					$this->trigger_meta,
					array( 'is_any' => true )
				),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @param $charge
	 * @param $payintent
	 */
	public function simple_pay_charge_created( $charge, $payintent ) {

		$form_id = $payintent->simpay_form_id;

		if ( empty( $form_id ) ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			// Its a logged in recipe and
			// user ID is 0. Skip process
			return;
		}

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_form      = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$form_name          = get_post_field( 'post_title', $form_id );
		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( $required_form[ $recipe_id ][ $trigger_id ] == $form_id ||
					 $required_form[ $recipe_id ][ $trigger_id ] == '-1' ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$pass_args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'post_id'          => intval( $form_id ),
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				);

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {
							$trigger_meta = array(
								'user_id'        => $user_id,
								'trigger_id'     => $result['args']['trigger_id'],
								'trigger_log_id' => $result['args']['get_trigger_id'],
								'run_number'     => $result['args']['run_number'],
							);

							$trigger_meta['meta_key']   = $this->trigger_code;
							$trigger_meta['meta_value'] = $form_name;
							Automator()->insert_trigger_meta( $trigger_meta );

							Automator()->maybe_trigger_complete( $result['args'] );
							break;
						}
					}
				}
			}
		}
	}

}
