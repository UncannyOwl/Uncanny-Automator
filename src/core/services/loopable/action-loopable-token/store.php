<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Services\Loopable\Action_Loopable_Token;

class Store {

	/**
	 * Stores the action loopable tokens to action meta.
	 *
	 * @param mixed $tokens
	 * @return void
	 */
	public function hydrate_loopable_tokens( $tokens ) {

		foreach ( $tokens as $key => $collection ) {

			$closure = function( $args ) use ( $key, $collection ) {
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
