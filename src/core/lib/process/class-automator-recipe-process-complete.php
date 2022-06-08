<?php


namespace Uncanny_Automator;

/**
 * Class Automator_Recipe_Process_Complete
 *
 * @package Uncanny_Automator
 */
class Automator_Recipe_Process_Complete {
	/**
	 * @var
	 */
	public static $instance;
	/**
	 * @var $this
	 */
	public $user;

	/**
	 * Automator_Recipe_Process constructor.
	 */
	public function __construct() {
		$this->user = $this;
	}

	/**
	 * @return Automator_Recipe_Process_Complete
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Complete the trigger for the user
	 *
	 * @param array $args
	 *
	 * @return null
	 */
	public function trigger( $args = array() ) {

		$user_id        = absint( $args['user_id'] );
		$trigger_id     = absint( $args['trigger_id'] );
		$recipe_id      = absint( $args['recipe_id'] );
		$trigger_log_id = absint( $args['trigger_log_id'] );
		$recipe_log_id  = absint( $args['recipe_log_id'] );

		// Set user ID
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( null === $trigger_id || ! is_numeric( $trigger_id ) ) {
			Automator()->error->add_error( 'complete_trigger', 'ERROR: You are trying to complete a trigger without providing a trigger_id.', $this );

			return null;
		}

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Automator()->error->add_error( 'complete_trigger', 'ERROR: You are trying to complete a trigger without providing a recipe_id.', $this );

			return null;
		}
		// The trigger is about to be completed
		do_action_deprecated(
			'uap_before_trigger_completed',
			array( $user_id, $trigger_id, $recipe_id, $trigger_log_id, $args ),
			'3.0',
			'automator_before_trigger_is_completed'
		);
		do_action( 'automator_before_trigger_is_completed', $user_id, $trigger_id, $recipe_id, $trigger_log_id, $args );

		$trigger_code        = get_post_meta( $trigger_id, 'code', true );
		$trigger_integration = Automator()->get->trigger_integration_from_trigger_code( $trigger_code );
		if ( 0 === Automator()->plugin_status->get( $trigger_integration ) ) {
			// The plugin for this action is NOT active
			Automator()->error->add_error( 'complete_trigger', 'ERROR: You are trying to complete ' . $trigger_code . ' and the plugin ' . $trigger_integration . ' is not active.', $this );

			return null;
		}

		Automator()->db->trigger->mark_complete( $trigger_id, $user_id, $recipe_id, $recipe_log_id, $trigger_log_id );

		$maybe_continue  = true;
		$process_further = array(
			'maybe_continue_recipe_process' => $maybe_continue,
			'recipe_id'                     => $recipe_id,
			'user_id'                       => $user_id,
			'recipe_log_id'                 => $recipe_log_id,
			'trigger_log_id'                => $trigger_log_id,
			'trigger_id'                    => $trigger_id,
			'args'                          => $args,
		);

		//New filter.. see usage in pro
		$process_further = apply_filters_deprecated( 'uap_maybe_continue_recipe_process', array( $process_further ), '3.0', 'automator_maybe_continue_recipe_process' );
		$process_further = apply_filters( 'automator_maybe_continue_recipe_process', $process_further );

		extract( $process_further, EXTR_OVERWRITE ); //phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		// The trigger is now completed
		do_action_deprecated( 'uap_trigger_completed', array( $process_further ), '3.0', 'automator_trigger_completed' );
		do_action( 'automator_trigger_completed', $process_further );

		// If all triggers for the recipe are completed
		if ( $maybe_continue && $this->triggers_completed( $recipe_id, $user_id, $recipe_log_id, $args ) ) {
			$this->complete_actions( $recipe_id, $user_id, $recipe_log_id, $args );
		}

		return true;
	}

	/**
	 * Are all triggers in the recipe completed
	 *
	 * @param int $recipe_id null||int
	 * @param int $user_id null||int
	 * @param int $recipe_log_id null||int
	 *
	 * @param array $args
	 *
	 * @return bool|null
	 */
	public function triggers_completed( $recipe_id = 0, $user_id = 0, $recipe_log_id = 0, $args = array() ) {

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Automator()->error->add_error( 'triggers_completed', 'ERROR: You are trying to check if triggers are completed without providing a recipe_id.', $this );

			return null;
		}
		// Set user ID
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$recipe_triggers  = Automator()->get_recipe_data( 'uo-trigger', $recipe_id );
		$trigger_statuses = array();
		foreach ( $recipe_triggers as $recipe_trigger ) {
			if ( 'publish' === (string) $recipe_trigger['post_status'] ) {
				$trigger_integration = $recipe_trigger['meta']['integration'];

				if ( 0 === Automator()->plugin_status->get( $trigger_integration ) ) {
					// The plugin for this trigger is NOT active
					Automator()->error->add_error( 'complete_trigger', 'ERROR: You are trying to complete ' . $recipe_trigger['meta']['code'] . ' and the plugin ' . $trigger_integration . ' is not active. @recipe_id ' . $recipe_id, $this );
				}

				$trigger_completed                         = Automator()->db->trigger->is_completed( $user_id, $recipe_trigger['ID'], $recipe_id, $recipe_log_id, true, $args );
				$trigger_statuses[ $recipe_trigger['ID'] ] = $trigger_completed;
			}
		}
		if ( empty( $trigger_statuses ) ) {
			return false;
		}

		// If "Any" trigger option is set
		if ( true === $this->is_any_trigger_option_set( $recipe_id ) ) {
			return $this->is_any_recipe_trigger_completed( $trigger_statuses );
		}

		// Default logic, All triggers
		return $this->are_all_recipe_triggers_completed( $trigger_statuses );
	}

	/**
	 * Check if "Any" option is selected for triggers
	 *
	 * @param $recipe_id
	 *
	 * @return bool
	 */
	public function is_any_trigger_option_set( $recipe_id ) {
		$value = get_post_meta( $recipe_id, 'run_when_any_trigger_complete', true );
		if ( empty( $value ) ) {
			return apply_filters( 'automator_recipe_any_trigger_complete', false, $recipe_id );
		}
		if ( 'any' !== $value ) {
			return apply_filters( 'automator_recipe_any_trigger_complete', false, $recipe_id );
		}

		return apply_filters( 'automator_recipe_any_trigger_complete', true, $recipe_id );
	}

	/**
	 * @param $statuses
	 *
	 * @return bool
	 */
	public function are_all_recipe_triggers_completed( $statuses ) {
		// if "All" option is set (default)
		foreach ( $statuses as $_trigger_status ) {
			if ( ! $_trigger_status ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param $statuses
	 *
	 * @return bool
	 */
	public function is_any_recipe_trigger_completed( $statuses ) {
		// if "All" option is set (default)
		foreach ( $statuses as $_trigger_status ) {
			if ( $_trigger_status ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Complete all actions in recipe
	 *
	 * @param null $recipe_id
	 * @param null $user_id
	 * @param null $recipe_log_id
	 * @param array $args
	 *
	 * @return bool
	 */
	public function complete_actions( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = array() ) {
		$recipe_action_data = Automator()->get_recipe_data( 'uo-action', $recipe_id );

		foreach ( $recipe_action_data as $action_data ) {

			$action_code                  = $action_data['meta']['code'];
			$action_status                = $action_data['post_status'];
			$action_data['recipe_log_id'] = $recipe_log_id;
			$action_integration           = Automator()->get->action_integration_from_action_code( $action_code );

			if ( 1 === Automator()->plugin_status->get( $action_integration ) && 'publish' === (string) $action_status ) {
				// The plugin for this action is active .. execute
				$action_execution_function = Automator()->get->action_execution_function_from_action_code( $action_code );

				$valid_function = true;
				if ( null === $action_execution_function ) {
					$valid_function = false;
				} elseif ( is_array( $action_execution_function ) && ! method_exists( $action_execution_function[0], $action_execution_function[1] ) ) {
					$valid_function = false;
				} elseif ( is_string( $action_execution_function ) && ! function_exists( $action_execution_function ) ) {
					$valid_function = false;
				}

				if ( ! $valid_function ) {
					$error_message                       = Automator()->error_message->get( 'action-function-not-exist' );
					$action_data['complete_with_errors'] = true;
					$this->action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
				} else {

					//fallback...
					$action_data['args'] = $args;

					/*
					 * See function notes
					 *
					 * @since 2.8
					 */
					$action_data = $this->parse_custom_value( $action_data, $user_id, $recipe_id, $args );

					$action = array(
						'user_id'     => $user_id,
						'action_data' => $action_data,
						'recipe_id'   => $recipe_id,
						'args'        => $args,
					);

					$action = apply_filters( 'automator_before_action_executed', $action );

					if ( isset( $action['process_further'] ) ) {

						if ( false === $action['process_further'] ) {
							Utilities::log( 'Action was skipped by uap_before_action_executed filter.' );
							continue;
						}

						unset( $action['process_further'] );
					}

					call_user_func_array( $action_execution_function, $action );
				}
			} elseif ( 'draft' === (string) $action_status ) {
				continue;
			} elseif ( 0 === Automator()->plugin_status->get( $action_integration ) ) {
				$error_message                       = Automator()->error_message->get( 'action-not-active' );
				$action_data['complete_with_errors'] = true;
				$this->action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
			} else {
				$error_message                       = esc_attr__( 'Unknown error occurred.', 'uncanny-automator' );
				$action_data['complete_with_errors'] = true;
				Automator()->error->add_error( 'complete_action', $error_message, array( $action_data, $this ) );
				$this->action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
			}
		}

		$this->closures( $recipe_id, $user_id, $recipe_log_id, $args );

		return true;
	}

	/**
	 * Complete the action for the user
	 *
	 * @param null $user_id
	 * @param null $action_data
	 * @param null $recipe_id
	 * @param string $error_message
	 * @param null $recipe_log_id
	 * @param array $args
	 *
	 * @return null|void
	 */
	public function action( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = array() ) {

		// Set user ID
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$action_id = (int) $action_data['ID'];

		if ( null === $action_id || ! is_numeric( $action_id ) ) {
			Automator()->error->add_error( 'complete_action', 'ERROR: You are trying to complete an action without providing a action_id.', $this );

			return null;
		}

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Automator()->error->add_error( 'complete_action', 'ERROR: You are trying to complete an action without providing a recipe_id.', $this );

			return null;
		}

		if ( is_null( $recipe_log_id ) && array_key_exists( 'recipe_log_id', $action_data ) ) {
			$recipe_log_id = absint( $action_data['recipe_log_id'] );
		}

		if ( is_null( $recipe_log_id ) || empty( $recipe_log_id ) ) {
			Automator()->error->add_error( 'complete_action', 'ERROR: You are trying to complete an action without providing a recipe_log_id.', $this );

			return null;
		}

		if ( empty( $args ) && array_key_exists( 'args', $action_data ) ) {
			$args = $action_data['args'];
		}

		$action_data['completed'] = $this->get_action_completed_status( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );

		// The action is about to be completed
		do_action_deprecated(
			'uap_before_action_completed',
			array(
				$user_id,
				$action_id,
				$recipe_id,
				$error_message,
				$recipe_log_id,
				$args,
			),
			'3.0',
			'automator_before_action_completed'
		);
		$do_action_args = array(
			'user_id'       => $user_id,
			'action_id'     => $action_id,
			'recipe_id'     => $recipe_id,
			'error_message' => $error_message,
			'recipe_log_id' => $recipe_log_id,
			'args'          => $args,
		);
		do_action( 'automator_before_action_completed', $do_action_args );

		$error_message = $this->get_action_error_message( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );

		$process_further = apply_filters( 'automator_before_action_created', true, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );

		if ( ! $process_further ) {
			return;
		}

		$action_log_id = $this->create_action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );

		// The action is about to be completed
		do_action_deprecated(
			'uap_action_completed',
			array(
				$user_id,
				$action_id,
				$recipe_id,
				$error_message,
				$args,
			),
			'3.0',
			'automator_action_created'
		);

		$do_action_args = array(
			'user_id'       => $user_id,
			'action_id'     => $action_id,
			'action_data'   => $action_data,
			'action_log_id' => $action_log_id,
			'recipe_id'     => $recipe_id,
			'error_message' => $error_message,
			'recipe_log_id' => $recipe_log_id,
			'args'          => $args,
		);

		do_action( 'automator_action_created', $do_action_args );

		$this->recipe( $recipe_id, $user_id, $recipe_log_id, $args );
	}

	/**
	 * @param null $user_id
	 * @param null $action_data
	 * @param null $recipe_id
	 * @param string $error_message
	 * @param null $recipe_log_id
	 * @param array $args
	 *
	 * @return mixed|string
	 */
	public function get_action_error_message( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = array() ) {

		if ( ! empty( $error_message ) && key_exists( 'complete_with_errors', $action_data ) ) {
			return $error_message;
		}

		if ( key_exists( 'user_action_message', $args ) && ! empty( $args['user_action_message'] ) ) {
			return $args['user_action_message'];
		}

		return '';

	}

	/**
	 * @param null $user_id
	 * @param null $action_data
	 * @param null $recipe_id
	 * @param string $error_message
	 * @param null $recipe_log_id
	 * @param array $args
	 *
	 * @return mixed|void
	 */
	public function get_action_completed_status( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = array() ) {

		/**
		 * @var $completed
		 * Meaning of each number
		 *
		 * 0 = not completed
		 * 1 = completed
		 * 2 = completed with errors, error message provided
		 * 5 = scheduled
		 * 7 = cancelled
		 * 8 = skipped
		 * 9 = completed, do nothing
		 */
		$completed = 0;

		if ( is_array( $action_data ) && ! empty( $error_message ) && key_exists( 'complete_with_errors', $action_data ) ) {
			$completed = 2;
		} elseif ( ( is_array( $action_data ) && key_exists( 'do-nothing', $action_data ) ) ) {
			$completed = 9;
		} elseif ( empty( $error_message ) ) {
			$completed = 1;
		}

		return apply_filters( 'automator_get_action_completed_status', $completed, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
	}

	/**
	 * @param null $user_id
	 * @param null $action_data
	 * @param null $recipe_id
	 * @param string $error_message
	 * @param null $recipe_log_id
	 * @param array $args
	 *
	 * @return bool
	 */
	public function create_action( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = array() ) {

		$action_id     = (int) $action_data['ID'];
		$completed     = (int) $action_data['completed'];
		$date_time     = apply_filters( 'automator_action_log_date_time', null, $action_data );
		$values        = array(
			'user_id'       => $user_id,
			'action_id'     => $action_id,
			'recipe_id'     => $recipe_id,
			'recipe_log_id' => $recipe_log_id,
			'completed'     => $completed,
			'error_message' => $error_message,
			'date_time'     => $date_time,
		);
		$action_log_id = Automator()->db->action->add( $values );
		$sentences     = Automator()->get->action_sentence( $action_id );
		if ( ! empty( $sentences ) ) {
			foreach ( $sentences as $meta_key => $meta_value ) {
				if ( ! empty( $meta_value ) ) {
					Automator()->db->action->add_meta( $user_id, $action_log_id, $action_id, $meta_key, maybe_serialize( $meta_value ) );
				}
			}
		}

		return $action_log_id;
	}

	/**
	 * this code is to parse new "Use custom value" functionality before an action
	 * function is called. We will not have to modify each integration to support it.
	 *
	 * @param $action_data
	 * @param $user_id
	 * @param $recipe_id
	 * @param $args
	 *
	 * @return mixed
	 * @since  2.8
	 *
	 * @author Saad
	 */
	public function parse_custom_value( $action_data, $user_id, $recipe_id, $args ) {

		if ( ! isset( $action_data['meta'] ) ) {
			return $action_data;
		}
		$updated_values = array();
		$meta           = $action_data['meta'];
		// use regex to see if there's a _custom key
		$custom_keys = preg_grep( '/(automator_custom_value)/', $meta );

		if ( ! $custom_keys ) {
			return $action_data;
		}

		foreach ( $custom_keys as $action_meta => $custom_value ) {
			$k = "{$action_meta}_custom";
			if ( ! key_exists( $k, $meta ) ) {
				continue;
			}
			// parse token here
			$v = Automator()->parse->text( $action_data['meta'][ $k ], $recipe_id, $user_id, $args );
			if ( $v ) {
				$action_data['meta'][ $action_meta ] = $v;
				$updated_values[ $action_meta ]      = $v;
			}
		}

		if ( $updated_values ) {
			foreach ( $updated_values as $meta_key => $meta_value ) {
				$pass_args = array(
					'user_id'        => $user_id,
					'trigger_id'     => $args['trigger_id'],
					'meta_key'       => $meta_key,
					'meta_value'     => $meta_value,
					'run_number'     => $args['run_number'], //get run number
					'trigger_log_id' => $args['trigger_log_id'],
				);
				Automator()->db->trigger->add_meta( $args['trigger_id'], $args['trigger_log_id'], $args['run_number'], $pass_args );
			}
		}

		return $action_data;
	}

	/**
	 * Complete a recipe
	 *
	 * @param null $recipe_id
	 * @param null $user_id
	 * @param null $recipe_log_id
	 *
	 * @param array $args
	 *
	 * @return null|true
	 */
	public function recipe( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = array() ) {

		/**
		 * @var $completed
		 * Meaning of each number
		 *
		 * 0 = not completed
		 * 1 = completed
		 * 2 = completed with errors, error message provided
		 * 5 = in progress (some actions are scheduled)
		 * 9 = completed, do nothing
		 */

		$run_number = Automator()->get->next_run_number( $recipe_id, $user_id, true );
		if ( $recipe_log_id && Automator()->db->recipe->get_scheduled_actions_count( $recipe_log_id, $args ) > 0 ) {
			$completed = 5;
		} elseif ( ( is_array( $args ) && key_exists( 'do-nothing', $args ) ) ) {
			$completed  = 9;
			$run_number = 1;
		} else {
			$completed = 1;
		}

		do_action_deprecated(
			'uap_before_recipe_completed',
			array(
				$recipe_id,
				$user_id,
				$recipe_log_id,
				$args,
			),
			'3.0',
			'automator_before_recipe_completed'
		);
		do_action( 'automator_before_recipe_completed', $recipe_id, $user_id, $recipe_log_id, $args );

		if ( null === $recipe_log_id ) {
			if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
				Automator()->error->add_error( 'complete_recipe', 'ERROR: You are trying to completed a recipe without providing a recipe_id', $this );

				return null;
			}
			$recipe_log_id = Automator()->db->recipe->add( $user_id, $recipe_id, $completed, $run_number );
		} else {
			Automator()->db->recipe->mark_complete( $recipe_log_id, $completed );
		}

		// If actions error occurred, change the recipe status to 2
		$maybe_error = Automator()->db->action->get_error_message( $recipe_log_id );

		if ( ! empty( $maybe_error ) ) {
			$skip     = false;
			$message  = $maybe_error->error_message;
			$complete = $maybe_error->completed;

			if ( strpos( $message, 'Existing user found matching' ) || strpos( $message, 'User not found matching' ) || strpos( $message, 'User found matching' ) ) {
				$skip = true;
			} elseif ( strpos( $message, 'New user created' ) || strpos( $message, 'Create new user failed' ) ) {
				$skip = true;
			} elseif ( 9 === (int) $complete ) {
				$skip = true;
			} elseif ( 8 === (int) $complete ) {
				$skip = true;
			}

			if ( ! $skip ) {
				$comp = 9 === absint( $completed ) ? 9 : $complete;
				Automator()->db->recipe->mark_complete_with_error( $recipe_id, $recipe_log_id, $comp );
				do_action( 'automator_recipe_completed_with_errors', $recipe_id, $user_id, $recipe_log_id, $args );
			}
		}

		do_action_deprecated(
			'uap_recipe_completed',
			array(
				$recipe_id,
				$user_id,
				$recipe_log_id,
				$args,
			),
			'3.0',
			'automator_recipe_completed'
		);
		do_action( 'automator_recipe_completed', $recipe_id, $user_id, $recipe_log_id, $args );

		return true;
	}

	/**
	 * Complete all closures in recipe
	 *
	 * @param null $recipe_id
	 * @param null $user_id
	 * @param null $recipe_log_id
	 * @param array $args
	 *
	 * @return bool
	 */
	public function closures( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = array() ) {

		$recipe_closure_data = Automator()->get_recipe_data( 'uo-closure', $recipe_id );
		foreach ( $recipe_closure_data as $closure_data ) {

			$closure_code                  = $closure_data['meta']['code'];
			$closure_status                = $closure_data['post_status'];
			$closure_data['recipe_log_id'] = $recipe_log_id;
			$closure_integration           = Automator()->get->closure_integration_from_closure_code( $closure_code );

			if ( 1 === Automator()->plugin_status->get( $closure_integration ) && 'publish' === $closure_status ) {

				// The plugin for this action is active .. execute
				$closure_execution_function = Automator()->get->closure_execution_function_from_closure_code( $closure_code );
				call_user_func_array(
					$closure_execution_function,
					array(
						$user_id,
						$closure_data,
						$recipe_id,
						$args,
					)
				);
			} else {

				// The plugin for this action is NOT active
				Automator()->error->add_error( 'complete_closures', 'ERROR: You are trying to complete ' . $closure_code . ' and the plugin ' . $closure_integration . ' is not active.', $this );
			}
		}

		do_action_deprecated(
			'uap_closures_completed',
			array(
				$recipe_id,
				$user_id,
				$args,
			),
			'3.0',
			'automator_closures_completed'
		);
		do_action( 'automator_closures_completed', $recipe_id, $user_id, $args );

		return true;
	}
}
