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
		self::maybe_migrate_trigger_hook();
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

		// Idempotency: claim the user+product slot only when we're actually about to fire.
		// Keyed by user+product (not txn ID) so that when Stripe creates both a confirmation
		// and a payment transaction via separate webhooks for the same checkout, only the
		// first request wins. Set AFTER the count guard so renewals don't burn the slot.
		$idempotency_key = 'uap_mp_added_' . $user_id . '_' . $product_id;
		if ( get_transient( $idempotency_key ) ) {
			return;
		}
		set_transient( $idempotency_key, true, HOUR_IN_SECONDS );

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
	 * this, Automator's trigger binder filters the trigger out (post_meta is the
	 * source of truth at bind time) and the validation callback never runs.
	 *
	 * The done-flag is set only when the UPDATE actually mutates rows or when
	 * a verification SELECT confirms no stale rows remain. This keeps sites whose
	 * pre-migration hook value differs from the expected old value from getting
	 * permanently locked into "already migrated" state.
	 *
	 * On a successful migration we also bust the active-trigger cache so the
	 * binder picks up the corrected post_meta on the very next request rather
	 * than waiting for the 60s TTL.
	 */
	public static function maybe_migrate_trigger_hook() {

		// Keyed with a version suffix so a release can force the migration to
		// re-run on sites that were locked into a half-migrated state by the
		// previous, non-idempotent implementation. Bump the suffix any time
		// the migration logic changes in a way that needs a one-shot replay.
		$option_key = 'uap_mp_membership_trigger_hook_migrated_v2';

		if ( automator_get_option( $option_key, '' ) ) {
			return;
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->query(
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
		// phpcs:enable

		// $wpdb->query returns false on error, otherwise the affected row count.
		// Treat false as a transient failure — leave the flag unset so the next
		// request retries instead of locking the site into a half-migrated state.
		if ( false === $updated ) {
			return;
		}

		// If nothing was updated, only mark migrated once we've confirmed there
		// are no stale rows left to migrate (a fresh install, or a site already
		// on the new hook). Otherwise re-attempt next request.
		if ( 0 === (int) $updated && self::has_stale_hook_rows() ) {
			return;
		}

		automator_update_option( $option_key, time() );

		if ( $updated > 0 ) {
			self::invalidate_active_trigger_cache();
		}
	}

	/**
	 * Detect any USERADDEDTOMEMBERSHIP trigger postmeta still bound to the
	 * pre-7.2.0 hook. Used to gate the "no rows changed" branch of the
	 * migration so we don't set the done-flag prematurely on sites whose
	 * stored hook value never matched the expected legacy string.
	 *
	 * @return bool
	 */
	private static function has_stale_hook_rows() {

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stale = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->postmeta} pm_code
					ON pm_code.post_id = pm.post_id
					AND pm_code.meta_key = 'code'
					AND pm_code.meta_value = %s
				WHERE pm.meta_key = 'add_action'
				AND pm.meta_value <> %s",
				'USERADDEDTOMEMBERSHIP',
				'mepr-account-is-active'
			)
		);
		// phpcs:enable

		return (int) $stale > 0;
	}

	/**
	 * Bust the cached active-trigger map so the binder re-reads post_meta
	 * on the next request. Tolerant of either cache implementation present
	 * in the codebase (transient or Automator cache wrapper).
	 *
	 * @return void
	 */
	private static function invalidate_active_trigger_cache() {

		delete_transient( 'automator_actionified_triggers' );

		if ( function_exists( 'Automator' ) && isset( Automator()->cache ) && method_exists( Automator()->cache, 'remove' ) ) {
			Automator()->cache->remove( 'automator_actionified_triggers' );
		}
	}

}
