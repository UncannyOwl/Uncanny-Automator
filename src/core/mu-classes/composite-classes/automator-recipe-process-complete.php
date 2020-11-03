<?php


namespace Uncanny_Automator;


/**
 * Class Automator_Recipe_Process_Complete
 * @package Uncanny_Automator
 */
class Automator_Recipe_Process_Complete {
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
	 * Complete the trigger for the user
	 *
	 * @param array $args
	 *
	 * @return null
	 */
	public function trigger( $args = [] ) {

		$user_id        = absint( $args['user_id'] );
		$trigger_id     = absint( $args['trigger_id'] );
		$recipe_id      = absint( $args['recipe_id'] );
		$trigger_log_id = absint( $args['get_trigger_id'] );
		$recipe_log_id  = absint( $args['recipe_log_id'] );
		$run_number     = absint( $args['run_number'] );

		// Set user ID
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( null === $trigger_id || ! is_numeric( $trigger_id ) ) {
			Utilities::log( 'ERROR: You are trying to complete a trigge without providing a trigger_id', 'complete_trigger ERROR', false, 'uap-errors' );

			return null;
		}

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Utilities::log( 'ERROR: You are trying to complete a trigge without providing a recipe_id', 'complete_trigger ERROR', false, 'uap-errors' );

			return null;
		}
		// The trigger is about to be completed
		do_action( 'uap_before_trigger_completed', $user_id, $trigger_id, $recipe_id, $trigger_log_id, $args );

		global $uncanny_automator;
		$trigger_code        = get_post_meta( $trigger_id, 'code', true );
		$trigger_integration = $uncanny_automator->get->trigger_integration_from_trigger_code( $trigger_code );
		if ( 0 === $uncanny_automator->plugin_status->get( $trigger_integration ) ) {

			// The plugin for this action is NOT active
			Utilities::log( 'ERROR: You are trying to complete ' . $trigger_code . ' and the plugin ' . $trigger_integration . ' is not active. ', 'complete_trigger ERROR', false, 'uap-errors' );

			return null;
		}

		global $wpdb;

		$update = array(
			'completed' => true,
			'date_time' => current_time( 'mysql' ),
		);

		$where = array(
			'user_id'              => $user_id,
			'automator_trigger_id' => $trigger_id,
			'automator_recipe_id'  => $recipe_id,
		);

		if ( null !== $trigger_log_id && is_int( $trigger_log_id ) ) {
			$where['ID'] = (int) $trigger_log_id;
		}

		if ( null !== $recipe_log_id && is_int( $recipe_log_id ) ) {
			$where['automator_recipe_log_id'] = (int) $recipe_log_id;
		}

		$update_format = array(
			'%d',
			'%s',
		);

		$where_format = array(
			'%d',
			'%d',
			'%d',
		);

		if ( ! empty( $trigger_log_id ) && is_int( $trigger_log_id ) ) {
			$where_format[] = '%d';
		}
		if ( ! empty( $recipe_log_id ) && is_int( $recipe_log_id ) ) {
			$where_format[] = '%d';
		}

		$table_name = $wpdb->prefix . 'uap_trigger_log';

		$wpdb->update( $table_name,
			$update,
			$where,
			$update_format,
			$where_format
		);

		$maybe_continue_recipe_process = true;
		$arr                           = [
			'maybe_continue_recipe_process' => $maybe_continue_recipe_process,
			'recipe_id'                     => $recipe_id,
			'user_id'                       => $user_id,
			'recipe_log_id'                 => $recipe_log_id,
			'trigger_log_id'                => $trigger_log_id,
			'trigger_id'                    => $trigger_id,
			'args'                          => $args,
		];

		//New filter.. see usage in pro
		$process_further = apply_filters( 'uap_maybe_continue_recipe_process', $arr );

		extract( $process_further, EXTR_OVERWRITE );

		// The trigger is now completed
		do_action( 'uap_trigger_completed', $process_further );

		// If all triggers for the recipe are completed
		if ( $maybe_continue_recipe_process && $this->triggers_completed( $recipe_id, $user_id, $recipe_log_id, $args ) ) {
			$this->complete_actions( $recipe_id, $user_id, $recipe_log_id, $args );
		}
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
	public function triggers_completed( $recipe_id = 0, $user_id = 0, $recipe_log_id = 0, $args = [] ) {

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Utilities::log( 'ERROR: You are trying to check if triggers are completed without providing a recipe_id', 'triggers_completed ERROR', false, 'uap-errors' );

			return null;
		}
		global $uncanny_automator;
		// Set user ID
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$recipe_triggers = $uncanny_automator->get_recipe_data( 'uo-trigger', $recipe_id );

		// By default the recipe will complete unless there is a trigger that is live(publish status) and its NOT completed
		$triggers_completed = true;

		foreach ( $recipe_triggers as $recipe_trigger ) {
			if ( 'publish' === (string) $recipe_trigger['post_status'] ) {
				$trigger_integration = $recipe_trigger['meta']['integration'];

				if ( 0 === $uncanny_automator->plugin_status->get( $trigger_integration ) ) {
					// The plugin for this trigger is NOT active
					Utilities::log( 'ERROR: You are trying to complete ' . $recipe_trigger['meta']['code'] . ' and the plugin ' . $trigger_integration . ' is not active. @recipe_id ' . $recipe_id, 'complete_trigger ERROR', false, 'uap-errors' );
				}

				$trigger_completed = $uncanny_automator->is_trigger_completed( $user_id, $recipe_trigger['ID'], $recipe_id, $recipe_log_id, $args, true );
				if ( ! $trigger_completed ) {
					return false;
				}
			}
		}

		return $triggers_completed;

	}

	/**
	 * Complete the action for the user
	 *
	 * @param $user_id       null||int
	 * @param $action_data   null||int
	 * @param $recipe_id     null||int
	 * @param $error_message string
	 * @param $recipe_log_id null
	 *
	 * @param array $args
	 *
	 * @return null
	 */
	public function action( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = [] ) {

		// Set user ID
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$action_id = (int) $action_data['ID'];

		if ( null === $action_id || ! is_numeric( $action_id ) ) {
			Utilities::log( 'ERROR: You are trying to complete a trigge without providing a trigger_id', 'complete_uap_action ERROR', false, 'uap-errors' );

			return null;
		}

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Utilities::log( 'ERROR: You are trying to complete a trigge without providing a recipe_id', 'complete_uap_action ERROR', false, 'uap-errors' );

			return null;
		}

		if ( is_null( $recipe_log_id ) && key_exists( 'recipe_log_id', $action_data ) ) {
			$recipe_log_id = $action_data['recipe_log_id'];
		}

		if ( is_null( $recipe_log_id ) || empty( $recipe_log_id ) ) {
			return null;
		}

		if ( empty( $args ) && key_exists( 'args', $action_data ) ) {
			$args = $action_data['args'];
		}

		// The trigger is about to be completed
		do_action( 'uap_before_action_completed', $user_id, $action_id, $recipe_id, $error_message, $recipe_log_id, $args );

		$completed = 0;

		if ( is_array( $action_data ) && ! empty( $error_message ) && key_exists( 'complete_with_errors', $action_data ) ) {
			$completed = 2;
		}

		if ( ( is_array( $action_data ) && key_exists( 'do-nothing', $action_data ) ) ) {
			if ( key_exists( 'complete_with_errors', $action_data ) ) {
				$completed = 2;
			} else {
				$completed = 9;
			}

			$error_message = key_exists( 'user_action_message', $args ) ? $args['user_action_message'] : $error_message;
		} elseif ( empty( $error_message ) ) {
			$completed = 1;
			if ( key_exists( 'user_action_message', $args ) && ! empty( $args['user_action_message'] ) ) {
				$error_message = $args['user_action_message'];
			}
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'uap_action_log';

		$action_log_id = $wpdb->insert( $table_name,
			array(
				'date_time'               => current_time( 'mysql' ),
				'user_id'                 => $user_id,
				'automator_action_id'     => $action_id,
				'automator_recipe_id'     => $recipe_id,
				'automator_recipe_log_id' => $recipe_log_id,
				'completed'               => $completed,
				'error_message'           => ! empty( $error_message ) ? $error_message : ''
			), array(
				'%s',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%s',
			) );


		global $uncanny_automator;

		$sentence_human_readable = $uncanny_automator->get->action_sentence( $action_id, 'sentence_human_readable' );

		if ( ! empty( $sentence_human_readable ) ) {
			// Store action sentence details for the completion
			$wpdb->insert(
				$wpdb->prefix . 'uap_action_log_meta',
				[
					'user_id'                 => $user_id,
					'automator_action_log_id' => $action_log_id,
					'automator_action_id'     => $action_id,
					'meta_key'                => 'sentence_human_readable',
					'meta_value'              => $sentence_human_readable,
				], [
					'%d',
					'%d',
					'%d',
					'%s',
					'%s',
				]
			);
		}
		$action_detail = $uncanny_automator->get->action_sentence( $action_id, 'action_detail' );

		// Store action sentence details for the completion
		$wpdb->insert(
			$wpdb->prefix . 'uap_action_log_meta',
			array(
				'user_id'                 => $user_id,
				'automator_action_log_id' => $action_log_id,
				'automator_action_id'     => $action_id,
				'meta_key'                => 'complete_action_detail',
				'meta_value'              => serialize( $action_detail ),
			), array(
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
			)
		);

		// Store complete trigger sentence for the completion
		$wpdb->insert(
			$wpdb->prefix . 'uap_action_log_meta',
			array(
				'user_id'                 => $user_id,
				'automator_action_log_id' => $action_log_id,
				'automator_action_id'     => $action_id,
				'meta_key'                => 'complete_action_sentence',
				'meta_value'              => $action_detail['complete_sentence'],
			), array(
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
			)
		);

		// The actions is now completed
		do_action( 'uap_action_completed', $user_id, $action_id, $recipe_id, $error_message, $args );

	}

	/**
	 * Complete a recipe
	 *
	 * @param $recipe_id     null||int
	 * @param $user_id       null||int
	 * @param $recipe_log_id null||int
	 *
	 * @param array $args
	 *
	 * @return null|true
	 */
	public function recipe( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = [] ) {

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Utilities::log( 'ERROR: You are trying to completed a recipe without providing a recipe_id', 'complete_recipe ERROR', false, 'uap-errors' );

			return null;
		}
		global $uncanny_automator;
		if ( ( is_array( $args ) && key_exists( 'do-nothing', $args ) ) ) {
			$completed  = 9;
			$run_number = 1;
		} else {
			$completed  = 1;
			$run_number = $uncanny_automator->get->next_run_number( $recipe_id, $user_id, true );
		}

		do_action( 'uap_before_recipe_completed', $recipe_id, $user_id, $recipe_log_id, $args );

		global $wpdb;
		$table_name = $wpdb->prefix . 'uap_recipe_log';
		if ( null === $recipe_log_id ) {
			$wpdb->insert( $table_name,
				array(
					'date_time'           => current_time( 'mysql' ),
					'user_id'             => $user_id,
					'automator_recipe_id' => $recipe_id,
					'completed'           => $completed,
					'run_number'          => $run_number,
				), array(
					'%s',
					'%d',
					'%d',
					'%d',
					'%d',
				) );

			$recipe_log_id = $wpdb->insert_id;

		} else {
			$wpdb->update( $table_name,
				array(
					'date_time'  => current_time( 'mysql' ),
					'completed'  => $completed,
					'run_number' => $run_number
				),
				array(
					'ID'                  => $recipe_log_id,
					'automator_recipe_id' => $recipe_id,
					'user_id'             => $user_id,
				),
				array(
					'%s',
					'%d',
					'%d',
				),
				array(
					'%d',
					'%d',
					'%d',
				) );
		}

		// If actions error occured, change the recipe status to 2
		$action_table_name = $wpdb->prefix . 'uap_action_log';
		$q                 = "SELECT error_message, completed FROM {$action_table_name} WHERE error_message != '' AND automator_recipe_log_id = $recipe_log_id";
		$maybe_error       = $wpdb->get_row( $q );
		/*Utilities::log( [
			'$q'           => $q,
			'$maybe_error' => $maybe_error,
		], '', true, 'step-final' );*/

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
			}

			if ( ! $skip ) {
				$wpdb->update( $table_name,
					array(
						'completed' => 9 === (int) $completed ? 9 : $complete,
					),
					array(
						'ID' => $recipe_log_id,
					),
					array( '%d', ),
					array( '%d', ) );
			}
		}

		do_action( 'uap_recipe_completed', $recipe_id, $user_id, $recipe_log_id, $args );

		$this->closures( $recipe_id, $user_id, $recipe_log_id, $args );
	}

	/**
	 * Complete all actions in recipe
	 *
	 * @param null $recipe_id
	 * @param null $user_id
	 * @param null $recipe_log_id
	 *
	 * @param array $args
	 *
	 * @return bool
	 */
	public function complete_actions( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = [] ) {
		global $uncanny_automator;
		$recipe_action_data = $uncanny_automator->get_recipe_data( 'uo-action', $recipe_id );

		foreach ( $recipe_action_data as $action_data ) {

			$action_code                  = $action_data['meta']['code'];
			$action_status                = $action_data['post_status'];
			$action_data['recipe_log_id'] = $recipe_log_id;
			$action_integration           = $uncanny_automator->get->action_integration_from_action_code( $action_code );

			if ( 1 === $uncanny_automator->plugin_status->get( $action_integration ) && 'publish' === $action_status ) {
				// The plugin for this action is active .. execute
				$action_execution_function = $uncanny_automator->get->action_execution_function_from_action_code( $action_code );

				$valid_function = true;
				if ( null === $action_execution_function ) {
					$valid_function = false;
				} elseif ( is_array( $action_execution_function ) && ! method_exists( $action_execution_function[0], $action_execution_function[1] ) ) {
					$valid_function = false;
				} elseif ( is_string( $action_execution_function ) && ! function_exists( $action_execution_function ) ) {
					$valid_function = false;
				}

				if ( ! $valid_function ) {
					global $uncanny_automator;
					$error_message                       = $uncanny_automator->error_message->get( 'action-function-not-exist' );
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

					call_user_func_array( $action_execution_function, array(
						$user_id,
						$action_data,
						$recipe_id,
						$args,
					) );
				}

			} elseif ( 0 === $uncanny_automator->plugin_status->get( $action_integration ) ) {
				global $uncanny_automator;
				$error_message                       = $uncanny_automator->error_message->get( 'action-not-active' );
				$action_data['complete_with_errors'] = true;
				$this->action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
			} elseif ( 0 === $uncanny_automator->plugin_status->get( $action_integration ) ) {
				global $uncanny_automator;
				$error_message                       = $uncanny_automator->error_message->get( 'plugin-not-active' );
				$action_data['complete_with_errors'] = true;
				$this->action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
			} elseif ( 1 === $uncanny_automator->plugin_status->get( $action_integration ) && 'draft' === $action_status ) {
				continue;
			} else {
				global $uncanny_automator;
				$error_message                       = esc_attr__( 'Unknown error occurred.', 'uncanny-automator' );
				$action_data['complete_with_errors'] = true;
				$this->action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
			}
		}

		$this->recipe( $recipe_id, $user_id, $recipe_log_id, $args );

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
	 *
	 */
	public function closures( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = [] ) {

		/*if ( ! is_user_logged_in() ) {
			return false;
		}*/
		global $uncanny_automator;
		$recipe_closure_data = $uncanny_automator->get_recipe_data( 'uo-closure', $recipe_id );
		foreach ( $recipe_closure_data as $closure_data ) {

			$closure_code                  = $closure_data['meta']['code'];
			$closure_status                = $closure_data['post_status'];
			$closure_data['recipe_log_id'] = $recipe_log_id;
			$closure_integration           = $uncanny_automator->get->closure_integration_from_closure_code( $closure_code );

			if ( 1 === $uncanny_automator->plugin_status->get( $closure_integration ) && 'publish' === $closure_status ) {

				// The plugin for this action is active .. execute
				$closure_execution_function = $uncanny_automator->get->closure_execution_function_from_closure_code( $closure_code );
				call_user_func_array( $closure_execution_function, array(
					$user_id,
					$closure_data,
					$recipe_id,
					$args,
				) );
			} else {

				// The plugin for this action is NOT active
				Utilities::log( 'ERROR: You are trying to complete ' . $closure_code . ' and the plugin ' . $closure_integration . ' is not active. ', 'complete_closures ERROR', false, 'uap-errors' );
			}
		}

		do_action( 'uap_closures_completed', $recipe_id, $user_id, $args );

		return true;
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
	 * @since 2.8
	 *
	 * @author Saad
	 */
	public function parse_custom_value( $action_data, $user_id, $recipe_id, $args ) {

		if ( ! isset( $action_data['meta'] ) ) {
			return $action_data;
		}
		$updated_values = [];
		$meta           = $action_data['meta'];
		// use regex to see if there's a _cutome key
		$custom_keys = preg_grep( '/(automator_custom_value)/', $meta );

		if ( ! $custom_keys ) {
			return $action_data;
		}

		global $uncanny_automator;
		foreach ( $custom_keys as $action_meta => $custom_value ) {
			$k = "{$action_meta}_custom";
			if ( ! key_exists( $k, $meta ) ) {
				continue;
			}
			// parse token here
			$v = $uncanny_automator->parse->text( $action_data['meta'][ $k ], $recipe_id, $user_id, $args );
			if ( $v ) {
				$action_data['meta'][ $action_meta ] = $v;
				$updated_values[ $action_meta ]      = $v;
			}
		}

		if ( $updated_values ) {
			foreach ( $updated_values as $meta_key => $meta_value ) {
				$args = [
					'user_id'        => $user_id,
					'trigger_id'     => $args['trigger_id'],
					'meta_key'       => $meta_key,
					'meta_value'     => $meta_value,
					'run_number'     => $args['run_number'], //get run number
					'trigger_log_id' => $args['get_trigger_id'],
				];
				$uncanny_automator->insert_trigger_meta( $args );
			}
		}

		return $action_data;
	}
}