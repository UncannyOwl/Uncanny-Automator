<?php
namespace Uncanny_Automator\Services\Recipe\Action\Token;

/**
 * Class Hydrator
 *
 * This class is responsible for handling the hydration of action tokens.
 * It provides methods to set user, action log, and action IDs, as well as process arguments.
 * It also includes methods to store and update tokens in the database.
 */
class Hydrator {

	/**
	 * @var string
	 * The meta key for storing action tokens.
	 */
	const META_KEY = 'action_tokens';

	/**
	 * @var int|null The ID of the user.
	 */
	protected $user_id = null;

	/**
	 * @var int|null The ID of the action log.
	 */
	protected $action_log_id = null;

	/**
	 * @var int|null The ID of the action.
	 */
	protected $action_id = null;

	/**
	 * @var array The process arguments.
	 */
	protected $process_args = array();

	/**
	 * Set the user ID.
	 *
	 * @param int $id The user ID.
	 */
	public function set_user_id( $id ) {
		$this->user_id = $id;
	}

	/**
	 * Set the action log ID.
	 *
	 * @param int $action_log_id The action log ID.
	 */
	public function set_action_log_id( $action_log_id ) {
		$this->action_log_id = $action_log_id;
	}

	/**
	 * Set the action ID.
	 *
	 * @param int $action_id The action ID.
	 */
	public function set_action_id( $action_id ) {
		$this->action_id = $action_id;
	}

	/**
	 * Set the process arguments.
	 *
	 * @param array $process_args The process arguments.
	 */
	public function set_process_args( $process_args ) {
		$this->process_args = $process_args;
	}

	/**
	 * Hydrate the action token with the given value.
	 *
	 * @param mixed $value The value to hydrate.
	 * @param bool $should_encode Whether to JSON encode the value before storing.
	 *
	 * @return bool|int The result of the store operation. False if value is empty or store operation fails.
	 */
	public function hydrate( $value = '', $should_encode = true ) {

		if ( empty( $value ) ) {
			return false;
		}

		$action_token_props = array(
			'should_skip_add_meta' => false,
			'value'                => $should_encode ? wp_json_encode( $value ) : $value,
		);

		// Allow other processes to modify the action token properties before storing.
		$action_token_props = apply_filters(
			'automator_action_tokens_hydrated_tokens',
			$action_token_props,
			$this->process_args,
			new Store()
		);

		return $this->insert_token( $action_token_props['value'] );

	}

	/**
	 * Perform an insert_token operation on the action token stored value.
	 *
	 * @param string $value The token value to store.
	 *
	 * @return int|bool The number of rows added or updated. Otherwise, false if not successful.
	 */
	public function insert_token( $value ) {

		// Otherwise, add a new meta value.
		return $this->store_add_value( $value );

	}

	/**
	 * Add a new token value to the database.
	 *
	 * @param string $value The token value to add.
	 *
	 * @return int|bool The number of rows added. Otherwise, false if not successful.
	 */
	protected function store_add_value( $value ) {

		return Automator()->db->action->add_meta(
			$this->user_id,
			$this->action_log_id,
			$this->action_id,
			self::META_KEY,
			$value
		);

	}

}
