<?php

namespace Uncanny_Automator;

/**
 * Class ADVANCED_COUPONS_USER_RECEIVES_CREDIT
 *
 * @package Uncanny_Automator
 */
class ADVANCED_COUPONS_USER_RECEIVES_CREDIT {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'ACFWC';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'ACFWCUSERRECEIVESCREDIT';
		$this->trigger_meta = 'ACFWCRECEIVESCREDIT';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/advanced-coupons/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Advanced Coupons */
			'sentence'            => sprintf( esc_attr__( 'A user receives {{greater than, less than, or equal to:%1$s}} {{a specific amount:%2$s}} of store credit', 'uncanny-automator' ), $this->trigger_meta, 'ACFWC_AMOUNT' ),
			/* translators: Logged-in trigger - Advanced Coupons */
			'select_option_name'  => esc_attr__( 'A user receives {{greater than, less than, or equal to}} {{a specific amount}} of store credit', 'uncanny-automator' ),
			'action'              => 'acfw_create_store_credit_entry',
			'priority'            => 10,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'user_receives_credit' ),
			'options_callback'    => array( $this, 'load_options' ),
		);
		Automator()->register->trigger( $trigger );

	}

	/**
	 * Load options method
	 *
	 * @return array
	 */
	public function load_options() {

		Automator()->helpers->recipe->advanced_coupons->options->load_options = true;

		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->advanced_coupons->get_options_for_credit( __( 'Condition', 'uncanny-automator' ), $this->trigger_meta, array( 'uo_include_any' => false ) ),
					Automator()->helpers->recipe->field->text(
						array(
							'option_code' => 'ACFWC_AMOUNT',
							'label'       => __( 'Amount', 'uncanny-automator' ),
							'token_name'  => __( 'Store credit spent', 'uncanny-automator' ),
							'input_type'  => 'float',
							'tokens'      => false,
						)
					),
				),
			)
		);

		return $options;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $data
	 */
	public function user_receives_credit( $data ) {

		if ( ! isset( $data['type'] ) || 'decrease' === $data['type'] ) {
			return;
		}

		$user_id = ( isset( $data['user_id'] ) ) ? intval( $data['user_id'] ) : 0;

		if ( 0 === $user_id ) {
			// Its a logged in recipe and
			// user ID is 0. Skip process
			return;
		}

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$conditions         = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$acfwc_amount       = Automator()->get->meta_from_recipes( $recipes, 'ACFWC_AMOUNT' );
		$matched_recipe_ids = array();
		$balance            = floatval( $data['amount'] );
		$order_id           = 0;

		if ( isset( $data['action'] ) && isset( $data['object_id'] ) && intval( $data['object_id'] ) > 0 ) {
			$order_id = intval( $data['object_id'] );
		}

		//Add where Point Type & Current Balances Matches
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( intval( '-1' ) === intval( $conditions[ $recipe_id ][ $trigger_id ] ) ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				} else {
					if ( 'GT' === $conditions[ $recipe_id ][ $trigger_id ] ) {
						if ( $balance > floatval( $acfwc_amount[ $recipe_id ][ $trigger_id ] ) ) {
							$matched_recipe_ids[] = array(
								'recipe_id'  => $recipe_id,
								'trigger_id' => $trigger_id,
							);
						}
					} elseif ( 'EQ' === $conditions[ $recipe_id ][ $trigger_id ] || intval( '-1' ) === intval( $conditions[ $recipe_id ][ $trigger_id ] ) ) {
						if ( floatval( $acfwc_amount[ $recipe_id ][ $trigger_id ] ) === $balance ) {
							$matched_recipe_ids[] = array(
								'recipe_id'  => $recipe_id,
								'trigger_id' => $trigger_id,
							);
						}
					} elseif ( 'NOT_EQ' === $conditions[ $recipe_id ][ $trigger_id ] || intval( '-1' ) === intval( $conditions[ $recipe_id ][ $trigger_id ] ) ) {
						if ( floatval( $acfwc_amount[ $recipe_id ][ $trigger_id ] ) !== $balance ) {
							$matched_recipe_ids[] = array(
								'recipe_id'  => $recipe_id,
								'trigger_id' => $trigger_id,
							);
						}
					} elseif ( 'LT' === $conditions[ $recipe_id ][ $trigger_id ] || intval( '-1' ) === intval( $conditions[ $recipe_id ][ $trigger_id ] ) ) {
						if ( $balance < floatval( $acfwc_amount[ $recipe_id ][ $trigger_id ] ) ) {
							$matched_recipe_ids[] = array(
								'recipe_id'  => $recipe_id,
								'trigger_id' => $trigger_id,
							);
						}
					} elseif ( 'GT_EQ' === $conditions[ $recipe_id ][ $trigger_id ] || intval( '-1' ) === intval( $conditions[ $recipe_id ][ $trigger_id ] ) ) {
						if ( $balance >= floatval( $acfwc_amount[ $recipe_id ][ $trigger_id ] ) ) {
							$matched_recipe_ids[] = array(
								'recipe_id'  => $recipe_id,
								'trigger_id' => $trigger_id,
							);
						}
					} elseif ( 'LT_EQ' === $conditions[ $recipe_id ][ $trigger_id ] || intval( '-1' ) === intval( $conditions[ $recipe_id ][ $trigger_id ] ) ) {
						if ( $balance <= floatval( $acfwc_amount[ $recipe_id ][ $trigger_id ] ) ) {
							$matched_recipe_ids[] = array(
								'recipe_id'  => $recipe_id,
								'trigger_id' => $trigger_id,
							);
						}
					}
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$pass_args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
					'is_signed_in'     => true,
				);

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {
							if ( isset( $result['args'] ) && isset( $result['args']['get_trigger_id'] ) ) {
								$trigger_meta = array(
									'user_id'        => $user_id,
									'trigger_id'     => (int) $result['args']['trigger_id'],
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								);

								if ( 0 !== $order_id ) {
									$trigger_meta['meta_key']   = 'order_id';
									$trigger_meta['meta_value'] = $order_id;
									Automator()->insert_trigger_meta( $trigger_meta );
								}

								$trigger_meta['meta_key']   = 'ACFWC_AMOUNT';
								$trigger_meta['meta_value'] = $balance;
								Automator()->insert_trigger_meta( $trigger_meta );

								$trigger_meta['meta_key']   = 'USERTOTALCREDIT';
								$trigger_meta['meta_value'] = Automator()->helpers->recipe->advanced_coupons->get_current_balance_of_the_customer( $user_id );

								Automator()->insert_trigger_meta( $trigger_meta );

								$trigger_meta['meta_key']   = 'USERLIFETIMECREDIT';
								$trigger_meta['meta_value'] = Automator()->helpers->recipe->advanced_coupons->get_total_credits_of_the_user( $user_id );

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

