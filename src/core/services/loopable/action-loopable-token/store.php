<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Services\Loopable\Action_Loopable_Token;

class Store {

	/**
	 * Stores the action loopable tokens to action meta.
	 *
	 * The closure registered on `automator_action_created` lives for the rest
	 * of the request. When `$parent_action_id` is supplied, the closure scopes
	 * itself to children of that specific loopable action — required to
	 * prevent cross-recipe contamination when multiple loopable actions
	 * register closures in the same wp-cron tick (and forward-compatible with
	 * within-recipe-multi-loopable and nested-loop scenarios). When
	 * `$parent_action_id` is null the closure fires for every event, matching
	 * the legacy un-scoped behaviour.
	 *
	 * @param mixed    $tokens
	 * @param int|null $parent_action_id  Action ID of the loopable action that
	 *                                    owns these tokens. Closure only fires
	 *                                    for children whose loop context points
	 *                                    back to this parent.
	 *
	 * @return void
	 */
	public function hydrate_loopable_tokens( $tokens, $parent_action_id = null ) {

		foreach ( $tokens as $key => $collection ) {

			$closure = function( $args ) use ( $key, $collection, $parent_action_id ) {

				// Only fire for OUR loopable action's own action_created event.
				// hydrate_loopable_parent_action_token reads the loopable
				// items meta back via the loopable action's own action_log_id
				// (extracted from the parent token's `ACTION_TOKEN:<id>`
				// segment), so the only write that matters is the one keyed
				// to this parent. Skipping every other action_created event
				// in the request prevents another loopable action's closure
				// from clobbering this parent's meta when both fire in the
				// same wp-cron tick.
				if ( null !== $parent_action_id ) {
					$firing_action_id = (int) ( $args['action_data']['ID'] ?? 0 );
					if ( $firing_action_id !== (int) $parent_action_id ) {
						return;
					}
				}

				$data = $args['action_data'];
				$this->store_action_loopable_tokens( $data['ID'], $key, $collection, $args );
			};

			add_action( 'automator_action_created', $closure, 10, 1 );

		}

	}

	/**
	 * @param mixed $meta
	 * @param mixed $parsed
	 *
	 * @return Loopable_Token_Collection
	 */
	protected function store_action_loopable_tokens( $action_id, $key, $collection, $args ) {

		$user_id       = $args['args']['user_id'] ?? null;
		$action_log_id = $args['action_log_id'] ?? null;

		$meta_value = maybe_serialize( json_decode( wp_json_encode( $collection ), true ) );

		$key = 'LOOPABLE_ACTION_TOKEN_' . $key;

		Automator()->db->action->add_meta( $user_id, absint( $action_log_id ), absint( $action_id ), $key, $meta_value );

		return $collection;

	}

}
