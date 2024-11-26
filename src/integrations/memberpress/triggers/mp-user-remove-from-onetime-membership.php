<?php

namespace Uncanny_Automator;

/**
 * Class MP_USER_REMOVE_FROM_ONETIME_MEMBERSHIP
 *
 * @package Uncanny_Automator
 */
class MP_USER_REMOVE_FROM_ONETIME_MEMBERSHIP {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MP';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * @var \MeprTransaction
	 */
	private static $txn_object = null;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'USERREMOVEFROMONETIMEMEMBERSHIP';
		$this->trigger_meta = 'MPPRODUCT';
		$this->pre_hook_capture_data();
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/memberpress/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - MemberPress */
			'sentence'            => sprintf( esc_attr__( 'A user is removed from {{a one-time subscription product:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - MemberPress */
			'select_option_name'  => esc_attr__( 'A user is removed from {{a one-time subscription product}}', 'uncanny-automator' ),
			'action'              => 'mepr_post_delete_transaction',
			'priority'            => 1,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'mp_user_removed_from_otp' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 *
	 * We need to capture transaction object.
	 * Uses hook 'mepr_pre_delete_transaction'
	 *
	 * @return void
	 */
	private function pre_hook_capture_data() {
		add_action( 'mepr_txn_destroy', array( $this, 'mepr_set_transaction' ), 1 );
		add_action( 'mepr_pre_delete_transaction', array( $this, 'mepr_set_transaction' ), 1 );
	}

	public function mepr_set_transaction( \MeprTransaction $txn ) {
		// Only consider one-time transactions.
		if ( 0 === absint( $txn->subscription_id ) ) {
			self::$txn_object = $txn;
		} else {
			self::$txn_object = null;
		}
	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->memberpress->options->all_memberpress_products_onetime(
						null,
						$this->trigger_meta,
						array(
							'uo_include_any' => true,
						)
					),
				),
			)
		);
	}

	/**
	 * @param int $id
	 * @param \MeprUser $user
	 * @param mixed $result
	 */
	public function mp_user_removed_from_otp( $id, $user, $result ) {
		// Do we have txn to work on?
		if ( null === self::$txn_object || ! ( self::$txn_object instanceof \MeprTransaction ) ) {
			return;
		}

		/** @var \MeprTransaction $transaction */
		$transaction      = self::$txn_object;
		self::$txn_object = null;

		if ( absint( $transaction->id ) !== absint( $id ) ) {
			return; // Transaction id didn't match. Bailout.
		}

		/** @var \MeprProduct $product */
		$product    = $transaction->product();
		$product_id = $product->ID;
		$user_id    = absint( $transaction->user()->ID );

		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		if ( empty( $recipes ) ) {
			return;
		}

		$required_product   = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();
		//Add where option is set to Any product
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( absint( $required_product[ $recipe_id ][ $trigger_id ] ) === $product_id || intval( '-1' ) === intval( $required_product[ $recipe_id ][ $trigger_id ] ) ) {
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
			$recipe_args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'user_id'          => $user_id,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
				'ignore_post_id'   => true,
				'is_signed_in'     => true,
			);

			$results = Automator()->maybe_add_trigger_entry( $recipe_args, false );
			if ( empty( $results ) ) {
				continue;
			}
			foreach ( $results as $result ) {
				if ( true === $result['result'] ) {

					$trigger_meta = array(
						'user_id' => $user_id,
					);

					$trigger_id     = absint( $result['args']['trigger_id'] );
					$trigger_log_id = absint( $result['args']['trigger_log_id'] );
					$run_number     = absint( $result['args']['run_number'] );

					$trigger_meta['meta_key']   = $this->trigger_meta;
					$trigger_meta['meta_value'] = $product_id;
					Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $trigger_meta );

					$trigger_meta['meta_key']   = 'trans_num';
					$trigger_meta['meta_value'] = $transaction->trans_num;
					Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $trigger_meta );

					$trigger_meta['meta_key']   = 'txn_status';
					$trigger_meta['meta_value'] = $transaction->status;
					Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $trigger_meta );

					Automator()->process->user->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}

}
