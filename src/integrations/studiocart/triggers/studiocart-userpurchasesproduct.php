<?php

namespace Uncanny_Automator;

/**
 * Studiocart Trigger
 */
class STUDIOCART_USERPURCHASESPRODUCT {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'STUDIOCART';

	/**
	 * Trigger code
	 *
	 * @var string
	 */
	private $trigger_code;
	/**
	 * Trigger meta
	 *
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'USERPURCHASESPRODUCT';
		$this->trigger_meta = 'STUDIOCARTORDER';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 *
	 * @throws \Exception
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/studiocart/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Studiocart */
			'sentence'            => sprintf( esc_attr__( 'A user purchases {{a product:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Studiocart */
			'select_option_name'  => esc_attr__( 'A user purchases {{a product}}', 'uncanny-automator' ),
			'action'              => 'sc_order_complete',
			'priority'            => 100,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'sc_product_purchased' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array
	 */
	public function load_options() {
		$options = array(
			'options' => array(
				Automator()->helpers->recipe->studiocart->options->all_products( null, $this->trigger_meta, array( 'uo_include_any' => true ) ),
			),
		);

		return Automator()->utilities->keep_order_of_options( $options );
	}

	/**
	 * Trigger handler function
	 *
	 * @param $fields_values
	 * @param $et_contact_error
	 * @param $contact_form_info
	 */
	public function sc_product_purchased( $status, $order_data, $order_type ) {

		if ( 'paid' !== (string) $status ) {
			return;
		}

		if ( ! is_array( $order_data ) ) {
			return;
		}

		if ( ! isset( $order_data['product_id'] ) ) {
			return;
		}

		// This is logged in trigger
		if ( 0 === (int) $order_data['user_account'] ) {
			return;
		}

		$recipes          = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_product = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$user_id          = get_current_user_id();

		// making sure we connect the trigger to an actual user. Important if admin is managing the order.
		$sc_user_id = (int) get_post_meta( $order_data['ID'], '_sc_user_account', true );
		if ( $sc_user_id ) {
			$user_id = $sc_user_id;
		}

		if ( empty( $recipes ) || empty( $required_product ) ) {
			return;
		}

		$matched_recipe_ids = array();
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {

				$trigger_id = $trigger['ID'];
				if ( ! isset( $required_product[ $recipe_id ] ) ) {
					continue;
				}
				if ( ! isset( $required_product[ $recipe_id ][ $trigger_id ] ) ) {
					continue;
				}
				if ( intval( '-1' ) === intval( $required_product[ $recipe_id ][ $trigger_id ] ) || in_array( $required_product[ $recipe_id ][ $trigger_id ], array( $order_data['product_id'] ), true ) ) {
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
			$pass_args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'user_id'          => $user_id,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
				'ignore_post_id'   => true,
			);

			$args = Automator()->process->user->maybe_add_trigger_entry( $pass_args, false );

			if ( $args ) {

				foreach ( $args as $result ) {
					if ( true === $result['result'] ) {

						$run_number = Automator()->get->trigger_run_number(
							$result['args']['trigger_id'],
							$result['args']['get_trigger_id'],
							$result['args']['user_id']
						);

						$trigger_id     = (int) $result['args']['trigger_id'];
						$user_id        = (int) $result['args']['user_id'];
						$trigger_log_id = (int) $result['args']['get_trigger_id'];
						$run_number     = (int) $result['args']['run_number'];

						$pass_args = array(
							'user_id'        => $user_id,
							'trigger_id'     => $trigger_id,
							'meta_key'       => 'sc_order_id',
							'meta_value'     => $order_data['ID'],
							'run_number'     => $run_number,
							'trigger_log_id' => $trigger_log_id,
						);

						Automator()->insert_trigger_meta( $pass_args );

						Automator()->process->user->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}

	}
}
