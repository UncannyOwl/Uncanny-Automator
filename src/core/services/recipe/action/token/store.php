<?php
namespace Uncanny_Automator\Services\Recipe\Action\Token;

/**
 * Handles the storing of action tokens from ta simple action run instance.
 *
 * @package Uncanny_Automator\Services\Recipe\Action\Token
 */
class Store {

	/**
	 * @var string
	 */
	protected $key_value_pairs = array();

	/**
	 * Sets a key value pairs.
	 *
	 * @param array $args
	 *
	 * @return void
	 */
	public function set_key_value_pairs( $args = array() ) {
		 $this->key_value_pairs = $args;
	}

	/**
	 * @return string
	 */
	public function get_key_value_pairs() {
		return $this->key_value_pairs;
	}

	/**
	 * Backwards compatibility (e.g. updated free, outdated pro)
	 *
	 * @deprecated 6.0
	 *
	 * @return string
	 */
	public function get_hydrated_tokens_replace_pairs() {
		return $this->get_key_value_pairs();
	}

	/**
	 * Persist the token value into the database. This is an internal method.
	 *
	 * Callback method to `automator_action_created`.
	 *
	 * @param array $args The accepted parameters from `automator_action_created`
	 *
	 * @return bool|int False if db insert is not successul. Otherwise, the last inserted ID (int).
	 */
	public function store( $args ) {

		$supported_hooks = array(
			'automator_action_created',
			'automator_pro_async_action_after_run_execution',
		);

		if ( ! in_array( current_action(), $supported_hooks, true ) ) {

			_doing_it_wrong( __FUNCTION__, 'This trait method is not intended to be called directly', '4.6' );

			return false;

		}

		$action_token = array(
			'should_skip_add_meta' => false,
			'value'                => $this->get_key_value_pairs(),
		);

		$action_token = apply_filters( 'automator_action_tokens_hydrated_tokens', $action_token, $args, $this );

		// Allows custom flows to skip adding entry to action meta.
		if ( true === $action_token['should_skip_add_meta'] ) {
			return false;
		}

		// Dont allow empty string values.
		if ( '' === $action_token['value'] ) {
			return false;
		}

		$user_id       = $args['user_id'];
		$action_log_id = isset( $args['action_log_id'] ) ? $args['action_log_id'] : null;
		$action_id     = isset( $args['action_id'] ) ? $args['action_id'] : null;

		// @todo - This is a core feature. Should provide a filter for pro to overwrite instead of processing from here.
		if ( 'automator_pro_async_action_after_run_execution' === current_action() ) {
			$action_data   = $args['action_data'];
			$user_id       = $args['user_id'];
			$action_log_id = $action_data['action_log_id'];
			$action_id     = $action_data['ID'];
		}

		// Hydrate the token. This is for simple actions. Loops are handled in its own run. Check Process_Hooks_Callback@hydrate_action_tokens (Pro)
		$hydrator = Automator()->action_tokens()->hydrator();

		$hydrator->set_user_id( $user_id );
		$hydrator->set_action_id( $action_id );
		$hydrator->set_action_log_id( $action_log_id );
		$hydrator->set_process_args( $args );

		$hydrator->hydrate( $action_token['value'], false );

		return true;

	}

}
