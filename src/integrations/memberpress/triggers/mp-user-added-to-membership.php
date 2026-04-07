<?php

namespace Uncanny_Automator;

/**
 * Class MP_USER_ADDED_TO_MEMBERSHIP
 *
 * @package Uncanny_Automator
 */
class MP_USER_ADDED_TO_MEMBERSHIP {

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
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'USERADDEDTOMEMBERSHIP';
		$this->trigger_meta = 'MPPRODUCT';
		$this->define_trigger();
		$this->maybe_migrate_trigger_hook();
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
			'sentence'            => sprintf( esc_attr__( 'A user is added to a {{membership:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - MemberPress */
			'select_option_name'  => esc_attr__( 'A user is added to a {{membership}}', 'uncanny-automator' ),
			'action'              => 'mepr-account-is-active',
			'priority'            => 18,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'mp_user_added_to_product' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->memberpress->options->all_memberpress_products(
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
	 * @param \MeprTransaction $transaction
	 */
	public function mp_user_added_to_product( \MeprTransaction $transaction ) {

		/** @var \MeprProduct $product */
		$product    = $transaction->product();
		$product_id = $product->ID;
		$user_id    = absint( $transaction->user()->ID );

		// Idempotency: prevent the trigger from firing more than once per user + product.
		// Keyed by user+product (not txn ID) so that when Stripe creates both a confirmation
		// and a payment transaction via separate webhooks, the first request wins and the
		// second is blocked — avoiding a race where both see count > 1 and neither fires.
		$idempotency_key = 'uap_mp_added_' . $user_id . '_' . $product_id;
		if ( get_transient( $idempotency_key ) ) {
			return;
		}
		set_transient( $idempotency_key, true, HOUR_IN_SECONDS );

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
				if ( ! isset( $required_product[ $recipe_id ][ $trigger_id ] ) ) {
					continue; // Skip trigger when required product data is not available
				}
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

		// Only fire on the user's very first membership access — whether that's a free trial,
		// a paid trial, or a standard purchase. Skips renewals and prevents double-firing in
		// cases where MemberPress creates both a confirmation and a payment transaction
		// back-to-back during the same checkout (e.g. Stripe subscriptions).
		$complete_count  = absint( \MeprTransaction::get_count_by_user_and_product( $user_id, $product_id, \MeprTransaction::$complete_str ) );
		$confirmed_count = absint( \MeprTransaction::get_count_by_user_and_product( $user_id, $product_id, \MeprTransaction::$confirmed_str ) );
		if ( ( $complete_count + $confirmed_count ) > 1 ) {
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
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['trigger_log_id'],
						'run_number'     => $result['args']['run_number'],
					);

					$trigger_meta['meta_key']   = $this->trigger_meta;
					$trigger_meta['meta_value'] = $product_id;
					Automator()->insert_trigger_meta( $trigger_meta );

					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}

	/**
	 * One-time migration: update the stored `add_action` hook for any existing
	 * USERADDEDTOMEMBERSHIP triggers that still reference the old hook. Without
	 * this, Automator won't bind the validation callback for recipes created
	 * before this change.
	 */
	public function maybe_migrate_trigger_hook() {

		$option_key = 'uap_mp_membership_trigger_hook_migrated';

		if ( automator_get_option( $option_key, '' ) ) {
			return;
		}

		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->postmeta} pm_code
					ON pm_code.post_id = pm.post_id
					AND pm_code.meta_key = 'code'
					AND pm_code.meta_value = %s
				SET pm.meta_value = %s
				WHERE pm.meta_key = 'add_action'
				AND pm.meta_value = %s",
				'USERADDEDTOMEMBERSHIP',
				'mepr-account-is-active',
				'mepr-event-transaction-completed'
			)
		);

		automator_update_option( $option_key, time() );
	}

}
