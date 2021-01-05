<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Get_Data
 * @package Uncanny_Automator
 */
class Automator_Get_Data {

	/**
	 * Automator_Get_Data constructor.
	 */
	public function __construct() {
	}

	/**
	 * Get token data for recipe triggers
	 *
	 * @param $triggers_meta null||int
	 * @param $recipe_id     null||int
	 *
	 * @return null|              |array
	 */
	public function recipe_trigger_tokens( $triggers_meta = null, $recipe_id = null ) {
		if ( is_null( $triggers_meta ) && is_null( $recipe_id ) ) {
			return null;
		}

		$tokens = apply_filters( 'automator_maybe_trigger_pre_tokens', array(), $triggers_meta, $recipe_id );
		global $uncanny_automator;
		//Only load these when on edit recipe page or is automator ajax is happening!
		if ( $uncanny_automator->helpers->recipe->is_edit_page() || $uncanny_automator->helpers->recipe->is_rest() || $uncanny_automator->helpers->recipe->is_ajax() ) {
			//Add custom tokens regardless of integration / trigger code
			$filters = array();
			if ( $triggers_meta ) {
				$trigger_integration = '';
				$trigger_meta        = '';
				$trigger_value       = '';
				$trigger_code        = isset( $triggers_meta['code'] ) ? $triggers_meta['code'] : '';
				foreach ( $triggers_meta as $meta_key => $meta_value ) {
					if ( empty( $meta_value ) ) {
						continue;
					}

					if ( 'INTEGRATION_NAME' === (string) strtoupper( $meta_key ) ) {
						continue;
					}

					if ( 'NUMBERCOND' === (string) strtoupper( $meta_key ) ) {
						continue;
					}

					if ( 'uap_trigger_version' === (string) $meta_key ) {
						continue;
					}

					if ( 'sentence' === (string) $meta_key ) {
						continue;
					}

					if ( 'sentence_human_readable' === (string) $meta_key ) {
						continue;
					}

					if ( strpos( $meta_key, 'readable' ) ) {
						continue;
					}

					if ( 'integration' === (string) $meta_key ) {
						$trigger_integration = strtolower( $meta_value );
					}

					//Ignore NUMTIMES and trigger_integration/trigger_code metas
					if ( 'NUMTIMES' !== (string) strtoupper( $meta_key ) && 'integration' !== (string) strtolower( $meta_key ) ) {
						$trigger_meta  = strtolower( $meta_key );
						$trigger_value = $meta_value;
					}

					//Deal with trigger_meta special cases
					if ( 'trigger_meta' === $meta_key ) {
						$trigger_meta  = strtolower( $meta_value );
						$trigger_value = $meta_value;
					}

					//Deal with trigger_meta special cases
					if ( 'code' === (string) $meta_key ) {
						$trigger_meta  = strtolower( $meta_value );
						$trigger_value = $meta_value;
					}

					//Add general Integration based filter, like automator_maybe_trigger_gf_tokens
					if ( ! empty( $trigger_integration ) ) {

						$filter = 'automator_maybe_trigger_' . $trigger_integration . '_tokens';
						$filter = str_replace( '__', '_', $filter );

						$filters[ $filter ] = array(
							'integration'   => strtoupper( $trigger_integration ),
							'meta'          => strtoupper( $trigger_meta ),
							'triggers_meta' => $triggers_meta,
							'recipe_id'     => $recipe_id,
						);

					}
					//Add trigger code specific filter, like automator_maybe_trigger_gf_gfforms_tokens
					if ( ! empty( $trigger_integration ) && ! empty( $triggers_meta ) ) {
						$filter = 'automator_maybe_trigger_' . $trigger_integration . '_' . $trigger_meta . '_tokens';
						$filter = str_replace( '__', '_', $filter );

						$filters[ $filter ] = array(
							'value'         => $trigger_value,
							'integration'   => strtoupper( $trigger_integration ),
							'meta'          => strtoupper( $trigger_meta ),
							'recipe_id'     => $recipe_id,
							'triggers_meta' => $triggers_meta,
						);
					}
				}
			}

			/* Filter to add/remove custom filter */
			/** @var  $filters */
			$filters = apply_filters( 'automator_trigger_filters', $filters, $triggers_meta );

			if ( $filters ) {
				foreach ( $filters as $filter => $args ) {
					$tokens = apply_filters( $filter, $tokens, $args );
				}
			}
		}
		//Adds the opportunity to modify final tokens list
		// (i.e., remove middle name from GF tokens list)
		return apply_filters( 'automator_maybe_trigger_tokens', $tokens, $recipe_id );
	}

	/**
	 * Accepts a trigger, action, or closure id and return the corresponding trigger_code, action_code, or closure_code
	 *
	 * @param null||int $item_id
	 *
	 * @return null
	 */
	public function item_code_from_item_id( $item_id = null ) {

		$item_code = null;

		global $uncanny_automator;

		$recipes_data = $uncanny_automator->get_recipes_data( true );

		$item_codes = [];

		foreach ( $recipes_data as $recipe_data ) {

			foreach ( $recipe_data['triggers'] as $trigger ) {
				$item_codes[ $trigger['ID'] ] = $trigger['meta']['code'];
			}

			foreach ( $recipe_data['actions'] as $action ) {
				$item_codes[ $action['ID'] ] = $action['meta']['code'];
			}

			foreach ( $recipe_data['closures'] as $closure ) {
				$item_codes[ $closure['ID'] ] = $closure['meta']['code'];
			}
		}

		if ( isset( $item_codes[ $item_id ] ) ) {
			$item_code = $item_codes[ $item_id ];
		}

		return $item_code;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger add_action hook
	 *
	 * @param $trigger_code null||string
	 *
	 * @return bool
	 */
	public function trigger_actions_from_trigger_code( $trigger_code = null ) {

		global $uncanny_automator;

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Utilities::log( 'ERROR: You are trying to get a trigger action from a trigger code without providing a $trigger_code', 'get_trigger_action_from_trigger_code ERROR', false, 'uap-errors' );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = $uncanny_automator->get_triggers();

		$trigger_action = null;
		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				$trigger_action = $system_trigger['action'];

				return $trigger_action;
			}
		}

		return $trigger_action;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger add_action hook
	 *
	 * @param $trigger_code null||string
	 *
	 * @return bool
	 */
	public function trigger_meta_from_trigger_code( $trigger_code = null ) {

		global $uncanny_automator;

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Utilities::log( 'ERROR: You are trying to get a trigger action from a trigger code without providing a $trigger_code', 'get_trigger_action_from_trigger_code ERROR', false, 'uap-errors' );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = $uncanny_automator->get_triggers();

		$trigger_meta = null;
		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				$trigger_meta = isset( $system_trigger['meta'] ) ? $system_trigger['meta'] : null;

				return $trigger_meta;
			}
		}

		return $trigger_meta;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger sentence
	 *
	 * @param $trigger_code null||string
	 *
	 * @return string
	 */
	public function trigger_title_from_trigger_code( $trigger_code = null ) {

		global $uncanny_automator;

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Utilities::log( 'ERROR: You are trying to get a title sentence from a trigger code without providing a $trigger_code', 'get_trigger_title_from_trigger_code ERROR', false, 'uap-errors' );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = $uncanny_automator->get_triggers();

		$trigger_title = null;

		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				$trigger_title = str_replace( array(
					'{',
					'}',
				), '', $system_trigger['select_option_name'] );

				return $trigger_title;
			}
		}

		return $trigger_title;
	}

	/**
	 * Accepts a action code(most like from action meta) and returns that associated action title
	 *
	 * @param $action_code null||string
	 *
	 * @return string
	 */
	public function action_title_from_action_code( $action_code = null ) {

		global $uncanny_automator;

		if ( null === $action_code || ! is_string( $action_code ) ) {
			Utilities::log( 'ERROR: You are trying to get an action title from an action code without providing a $action_code', 'get_title_from_action_code ERROR', false, 'uap-errors' );

			return null;
		}

		// Load all default trigger settings
		$system_actions = $uncanny_automator->get_actions();

		$action_title = null;

		foreach ( $system_actions as $system_action ) {

			if ( $system_action['code'] === $action_code ) {
				$action_title = str_replace( array( '{', '}' ), '', $system_action['select_option_name'] );

				return $action_title;
			}
		}

		return $action_title;
	}

	/**
	 * @param        $id
	 * @param string $type
	 *
	 * @return array|mixed|string
	 */
	public function action_sentence( $id, $type = '' ) {

		global $wpdb;

		if ( 0 === absint( $id ) ) {
			return '';
		}


		$action_meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
				$id
			)
		);

		if ( empty( $action_meta ) ) {
			return '';
		}

		$code                    = false;
		$raw_sentence            = false;
		$sentence_human_readable = false;

		foreach ( $action_meta as $action ) {
			if ( 'code' === $action->meta_key ) {
				$code = $action->meta_value;
			}
			if ( 'sentence' === $action->meta_key ) {
				$raw_sentence = $action->meta_value;
			}
			if ( 'sentence_human_readable' === $action->meta_key ) {
				$sentence_human_readable = $action->meta_value;
			}

		}

		if ( false == $code || false === $raw_sentence ) {
			return '';
		}

		$re = '/\{\{(.*?)\}\}/m';
		preg_match_all( $re, $raw_sentence, $matches, PREG_SET_ORDER, 0 );

		$tokens = [];
		foreach ( $matches as $key => $match ) {
			$tokens[ $key ]['brackets']       = $match[0];
			$tokens[ $key ]['inner_brackets'] = $match[1];
			$token                            = explode( ':', $match[1] );
			$tokens[ $key ]['token']          = $token[1];
			foreach ( $action_meta as $action ) {
				if ( $token[1] === $action->meta_key ) {
					$tokens[ $key ]['token_value'] = $action->meta_value;
				}
			}
		}

		$complete_sentence = $raw_sentence;
		foreach ( $tokens as $token ) {
			if ( key_exists( 'token', $token ) && key_exists( 'token_value', $token ) ) {
				$complete_sentence = str_replace( $token['token'], $token['token_value'], $complete_sentence );
			}
		}

		$sentence = [
			'code'                    => $code,
			'raw_sentence'            => $raw_sentence,
			'tokens'                  => $tokens,
			'complete_sentence'       => $complete_sentence,
			'sentence_human_readable' => $sentence_human_readable
		];

		$sentence = apply_filters( 'get_action_sentence', $sentence, $type, $action_meta );

		if ( in_array( $type, array_keys( $sentence ), true ) ) {
			return $sentence[ $type ];
		}

		return $sentence;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger validation function
	 *
	 * @param $trigger_code null||string
	 *
	 * @return null|             |array||string String is the function is not within a class and array if it is
	 */
	public function trigger_validation_function_from_trigger_code( $trigger_code = null ) {

		global $uncanny_automator;

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Utilities::log( 'ERROR: You are trying to get a trigger validation function from a trigger code without providing a $trigger_code', 'get_trigger_validation_function_from_trigger_code ERROR', false, 'uap-errors' );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = $uncanny_automator->get_triggers();

		$trigger_validation_function = null;

		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				$trigger_validation_function = $system_trigger['validation_function'];

				return $trigger_validation_function;
			}
		}

		return $trigger_validation_function;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger sentence
	 *
	 * @param $trigger_code null||string
	 *
	 * @return string
	 */
	public function trigger_integration_from_trigger_code( $trigger_code = null ) {

		global $uncanny_automator;

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Utilities::log( 'ERROR: You are trying to get a trigger integration code from a trigger code without providing an $trigger_code', 'get_trigger_integration_from_trigger_code ERROR', false, 'uap-errors' );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = $uncanny_automator->get_triggers();

		$trigger_integration = null;

		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				$trigger_integration = $system_trigger['integration'];

				return $trigger_integration;
			}
		}

		if ( null === $trigger_integration ) {
			global $wpdb;
			// Integration is not active ... get integration from DB
			$trigger_integration = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value
					FROM {$wpdb->postmeta}
					WHERE post_id IN (
					SELECT post_id
					FROM {$wpdb->postmeta}
					WHERE meta_key = 'code'
					AND meta_value = %s
					) 
					AND meta_key = 'integration'",
					$trigger_code
				)
			);
		}

		return $trigger_integration;
	}

	/**
	 * Accepts a action code(most like from action meta) and returns that associated action sentence
	 *
	 * @param $action_code null||string
	 *
	 * @return string
	 */
	public function action_integration_from_action_code( $action_code = null ) {

		global $uncanny_automator;

		if ( null === $action_code || ! is_string( $action_code ) ) {
			Utilities::log( 'ERROR: You are trying to get a action integration code from a action code without providing an $action_code', 'get_action_integration_from_action_code ERROR', false, 'uap-errors' );

			return null;
		}

		// Load all default trigger settings
		$system_actions = $uncanny_automator->get_actions();

		$action_integration = null;

		foreach ( $system_actions as $system_action ) {

			if ( $system_action['code'] === $action_code ) {
				$action_integration = $system_action['integration'];

				return $action_integration;
			}
		}

		if ( null === $action_integration ) {
			global $wpdb;
			// Integration is not active ... get integration from DB
			$action_integration = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value
					FROM {$wpdb->postmeta}
					WHERE post_id IN (
					SELECT post_id
					FROM {$wpdb->postmeta}
					WHERE meta_key = 'code'
					AND meta_value = %s
					) 
					AND meta_key = 'integration'",
					$action_code
				)
			);

		}

		return $action_integration;
	}

	/**
	 * Accepts a closure code(most like from closure meta) and returns that associated closure integration
	 *
	 * @param $closure_code null||string
	 *
	 * @return string
	 */
	public function closure_integration_from_closure_code( $closure_code = null ) {

		global $uncanny_automator;

		if ( null === $closure_code || ! is_string( $closure_code ) ) {
			Utilities::log( 'ERROR: You are trying to get a action integration code from a action code without providing an $action_code', 'get_closure_integration_from_closure_code ERROR', false, 'uap-errors' );

			return null;
		}

		// Load all default trigger settings
		$system_closures = $uncanny_automator->get_closures();

		$closure_integration = null;

		foreach ( $system_closures as $system_closure ) {

			if ( $system_closure['code'] === $closure_code ) {
				$closure_integration = $system_closure['integration'];

				return $closure_integration;
			}
		}

		if ( null === $closure_integration ) {
			global $wpdb;
			// Integration is not active ... get integration from DB
			$closure_integration = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value
					FROM {$wpdb->postmeta}
					WHERE post_id IN (
					SELECT post_id
					FROM {$wpdb->postmeta}
					WHERE meta_key = 'code'
					AND meta_value = %s
					) 
					AND meta_key = 'integration'",
					$closure_code
				)
			);
		}

		return $closure_integration;
	}

	/**
	 * Accepts an action code(most like from trigger meta) and returns that associated action execution function
	 *
	 * @param $action_code null||string
	 *
	 * @return null|            |array||string String is the function is not within a class and array if it is
	 */
	public function action_execution_function_from_action_code( $action_code = null ) {

		global $uncanny_automator;

		if ( null === $action_code || ! is_string( $action_code ) ) {
			Utilities::log( 'ERROR: You are trying to get an action execution function from an action code without providing a $action_code', 'get_action_execution_function_from_action_code ERROR', false, 'uap-errors' );

			return null;
		}

		// Load all default trigger settings
		$system_action = $uncanny_automator->get_actions();

		$action_execution_function = null;

		foreach ( $system_action as $system_action ) {

			if ( $system_action['code'] === $action_code ) {

				$action_execution_function = $system_action['execution_function'];

				return $action_execution_function;
			}
		}

		return $action_execution_function;
	}

	/**
	 * Accepts an action code(most like from trigger meta) and returns that associated action execution function
	 *
	 * @param $closure_code null||string
	 *
	 * @return null|             |array||string String is the public function is not within a class and array if it is
	 */
	public function closure_execution_function_from_closure_code( $closure_code = null ) {

		global $uncanny_automator;

		if ( null === $closure_code || ! is_string( $closure_code ) ) {
			Utilities::log( 'ERROR: You are trying to get an action execution function from an action code without providing a $action_code', 'get_action_execution_function_from_action_code ERROR', false, 'uap-errors' );

			return null;
		}

		// Load all default trigger settings
		$system_closures = $uncanny_automator->get_closures();

		$closure_execution_function = null;

		foreach ( $system_closures as $system_closure ) {

			if ( $system_closure['code'] === $closure_code ) {

				$closure_execution_function = $system_closure['execution_function'];

				return $closure_execution_function;
			}
		}

		return $closure_execution_function;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger validation function priority
	 *
	 * @param $trigger_code null||string
	 *
	 * @return null|             |int Default priority is always 10
	 */
	public function trigger_priority_from_trigger_code( $trigger_code = null ) {

		global $uncanny_automator;

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Utilities::log( 'ERROR: You are trying to get a trigger priority from a trigger code without providing a $trigger_code', 'get_trigger_priority_from_trigger_code ERROR', false, 'uap-errors' );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = $uncanny_automator->get_triggers();

		// Default priority if not set
		$trigger_priority = 10;

		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				$trigger_priority = $system_trigger['priority'];

				return $trigger_priority;
			}
		}

		return $trigger_priority;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger validation function accepted args
	 *
	 * @param $trigger_code null||string
	 *
	 * @return null|             |int Default arguments is always 1
	 */
	public function trigger_accepted_args_from_trigger_code( $trigger_code = null ) {

		global $uncanny_automator;

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Utilities::log( 'ERROR: You are trying to get a trigger validation function accepted args from a trigger code without providing a $trigger_code', 'get_trigger_accepted_args_from_trigger_code ERROR', false, 'uap-errors' );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = $uncanny_automator->get_triggers();

		$trigger_accepted_args = 1;

		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				$trigger_accepted_args = $system_trigger['accepted_args'];

				return $trigger_accepted_args;
			}
		}

		return $trigger_accepted_args;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger options
	 *
	 * @param $trigger_code null||string
	 *
	 * @return array
	 */
	public function trigger_options_from_trigger_code( $trigger_code ) {

		global $uncanny_automator;

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Utilities::log( 'ERROR: You are trying to get a trigger options from a trigger code without providing a $trigger_code', 'get_trigger_options_from_trigger_code ERROR', false, 'uap-errors' );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = $uncanny_automator->get_triggers();

		$trigger_options = [];

		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				$trigger_options = $system_trigger['accepted_args'];

				return $trigger_options;
			}
		}

		return $trigger_options;
	}

	/**
	 * Get the trigger log ID for the user
	 *
	 * @param      $user_id       null||int
	 * @param      $trigger_id    null||int
	 * @param null $recipe_id null||int
	 * @param null $recipe_log_id null||int
	 *
	 * @return null|int
	 */
	public function trigger_log_id( $user_id = null, $trigger_id = null, $recipe_id = null, $recipe_log_id = null ) {

		// Set user ID
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( null === $trigger_id || ! is_numeric( $trigger_id ) ) {
			Utilities::log( 'ERROR: You are trying to get a trigger log ID without providing a trigger_id', 'get_trigger_log_id ERROR', false, 'uap-errors' );

			return null;
		}


		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Utilities::log( 'ERROR: You are trying to get a trigger lod ID without providing a recipe_id', 'get_trigger_log_id ERROR', false, 'uap-errors' );

			return null;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'uap_trigger_log';
		$results    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $table_name WHERE user_id = %d AND automator_trigger_id = %d AND automator_recipe_id = %d AND automator_recipe_log_id = %d",
				$user_id,
				$trigger_id,
				$recipe_id,
				$recipe_log_id
			)
		);

		if ( empty( $results ) || null === $results ) {
			return null;
		}

		return (int) $results;
	}

	/**
	 * Get the trigger for the user
	 *
	 * @param $user_id        null||int
	 * @param $trigger_id     null||int
	 * @param $meta_key       null||string
	 * @param $trigger_log_id int
	 *
	 * @return null|string
	 */
	public function trigger_meta( $user_id = null, $trigger_id = null, $meta_key = null, $trigger_log_id = null ) {

		// Set user ID
		if ( ! absint( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( null === $trigger_id || ! is_numeric( $trigger_id ) ) {
			Utilities::log( 'ERROR: You are trying to get trigger meta without providing a trigger_id', 'get_trigger_meta ERROR', false, 'uap-errors' );

			return null;
		}

		if ( null === $meta_key || ! is_string( $meta_key ) ) {
			Utilities::log( 'ERROR: You are trying to get trigger meta without providing a meta_key', 'get_trigger_meta ERROR', false, 'uap-errors' );

			return null;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'uap_trigger_log_meta';
		$results    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(meta_value) 
					FROM $table_name 
					WHERE 1=1 
					AND user_id = %d 
					AND meta_key LIKE %s 
					AND automator_trigger_id = %d 
					AND automator_trigger_log_id = %d",
				$user_id,
				$meta_key,
				$trigger_id,
				$trigger_log_id
			)
		);

		return $results;
	}

	/**
	 * @param      $recipe_id
	 * @param      $user_id
	 * @param bool $fetch_current
	 *
	 * @return int|null|string
	 */
	public function next_run_number( $recipe_id, $user_id, $fetch_current = false ) {
		if ( 0 !== absint( $user_id ) ) {
			global $wpdb;
			$run_number = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(run_number) 
						FROM {$wpdb->prefix}uap_recipe_log 
						WHERE 1=1 
						AND completed NOT IN (2,9)
						AND automator_recipe_id = %d 
						AND user_id = %d",
					$recipe_id,
					$user_id
				)
			);

			if ( is_numeric( $run_number ) ) {
				if ( $fetch_current ) {
					$run_number;
				} else {
					$run_number ++;
				}

				return $run_number;
			}
		}

		return 1;
	}

	/**
	 * @param        $id
	 * @param string $type
	 *
	 * @return array|mixed|string
	 */
	public function trigger_sentence( $id, $type = '' ) {

		global $wpdb;

		if ( 0 === absint( $id ) ) {
			return '';
		}


		$trigger_meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
				$id
			)
		);

		if ( empty( $trigger_meta ) ) {
			return '';
		}

		$code                    = false;
		$raw_sentence            = false;
		$sentence_human_readable = false;

		foreach ( $trigger_meta as $trigger ) {
			if ( 'code' === $trigger->meta_key ) {
				$code = $trigger->meta_value;
			}
			if ( 'sentence' === $trigger->meta_key ) {
				$raw_sentence = $trigger->meta_value;
			}
			if ( 'sentence_human_readable' === $trigger->meta_key ) {
				$sentence_human_readable = $trigger->meta_value;
			}
		}

		if ( false == $code || false === $raw_sentence ) {
			return '';
		}

		$re = '/\{\{(.*?)\}\}/m';
		preg_match_all( $re, $raw_sentence, $matches, PREG_SET_ORDER, 0 );

		$tokens = [];
		foreach ( $matches as $key => $match ) {
			$tokens[ $key ]['brackets']       = $match[0];
			$tokens[ $key ]['inner_brackets'] = $match[1];
			$token                            = explode( ':', $match[1] );
			$tokens[ $key ]['token']          = $token[1];
			foreach ( $trigger_meta as $trigger ) {
				if ( $token[1] === $trigger->meta_key ) {
					$tokens[ $key ]['token_value'] = $trigger->meta_value;
				}
			}
		}

		$complete_sentence = $raw_sentence;
		foreach ( $tokens as $token ) {
			if ( key_exists( 'token', $token ) && key_exists( 'token_value', $token ) ) {
				$complete_sentence = str_replace( $token['token'], $token['token_value'], $complete_sentence );
			}
		}

		$sentence = [
			'code'                    => $code,
			'raw_sentence'            => $raw_sentence,
			'tokens'                  => $tokens,
			'complete_sentence'       => $complete_sentence,
			'sentence_human_readable' => $sentence_human_readable,

		];

		$sentence = apply_filters( 'get_trigger_sentence', $sentence, $type, $trigger_meta );

		if ( in_array( $type, array_keys( $sentence ), true ) ) {
			return $sentence[ $type ];
		}

		return $sentence;
	}

	/**
	 * @param $trigger_id
	 * @param $trigger_log_id
	 * @param $user_id
	 *
	 * @return int|null|string
	 */
	public function trigger_run_number( $trigger_id, $trigger_log_id, $user_id ) {
		if ( 0 === absint( $user_id ) ) {
			return 1;
		}

		global $wpdb;

		$run_number = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(run_number) 
					FROM {$wpdb->prefix}uap_trigger_log_meta 
					WHERE 1=1 
					AND user_id = %d 
					AND automator_trigger_id = %d 
					AND automator_trigger_log_id = %d",
				$user_id,
				$trigger_id,
				$trigger_log_id
			)
		);

		if ( empty( $run_number ) ) {
			return 1;
		} else {
			//return $run_number + 1;
			return $run_number;
		}
	}

	/**
	 * @param $check_trigger_code
	 * @param null $recipe_id
	 *
	 * @return array
	 */
	public function recipes_from_trigger_code( $check_trigger_code = null, $recipe_id = null ) {

		global $uncanny_automator;

		if ( null === $check_trigger_code ) {
			return [];
		}

		$recipes_to_return = [];
		$recipes           = $uncanny_automator->get_recipes_data( true, $recipe_id );

		foreach ( $recipes as $recipe ) {

			if ( 'publish' !== $recipe['post_status'] ) {
				continue;
			}

			foreach ( $recipe['triggers'] as $trigger ) {

				$trigger_code = $trigger['meta']['code'];

				// Skip if the executed trigger doesn't match
				if ( $check_trigger_code !== $trigger_code ) {
					continue;
				}
				$recipe_id                       = $recipe['ID'];
				$recipes_to_return[ $recipe_id ] = $recipe;
			}
		}

		return $recipes_to_return;

	}

	/**
	 * @param $recipes
	 * @param $trigger_meta
	 *
	 * @return array
	 */
	public function meta_from_recipes( $recipes = [], $trigger_meta = null ) {
		$metas = [];
		if ( null === $trigger_meta ) {
			return $metas;
		}
		if ( ! empty( $recipes ) ) {
			foreach ( $recipes as $recipe ) {
				foreach ( $recipe['triggers'] as $trigger ) {
					$recipe_id = $recipe['ID'];
					if ( key_exists( $trigger_meta, $trigger['meta'] ) ) {
						$metas[ $recipe_id ][ $trigger['ID'] ] = $trigger['meta'][ $trigger_meta ];
					}
				}
			}
		}

		return $metas;
	}

	/**
	 * @param null $run_number
	 * @param null $trigger_id
	 * @param null $trigger_log_id
	 * @param null $meta_key
	 * @param null $user_id
	 *
	 * @return array|null|string
	 */
	public function maybe_get_meta_id_from_trigger_log( $run_number = null, $trigger_id = null, $trigger_log_id = null, $meta_key = null, $user_id = null ) {
		if ( is_null( $run_number ) || is_null( $trigger_id ) || is_null( $trigger_log_id ) || is_null( $meta_key ) || is_null( $user_id ) ) {
			return null;
		}

		global $wpdb;

		return $wpdb->get_var( "SELECT ID FROM {$wpdb->prefix}uap_trigger_log_meta 
									WHERE user_id = $user_id 
									AND automator_trigger_log_id = $trigger_log_id 
									AND automator_trigger_id = $trigger_id 
									AND meta_key LIKE '$meta_key'
									AND run_number = $run_number
									LIMIT 0,1" );

	}

	/**
	 * @param null $meta_key
	 * @param null $trigger_id
	 * @param null $trigger_log_id
	 * @param null $run_number
	 * @param null $user_id
	 *
	 * @return null|string
	 */
	public function maybe_get_meta_value_from_trigger_log( $meta_key = null, $trigger_id = null, $trigger_log_id = null, $run_number = null, $user_id = null ) {
		if ( is_null( $run_number ) || is_null( $trigger_id ) || is_null( $trigger_log_id ) || is_null( $meta_key ) || is_null( $user_id ) ) {
			return null;
		}

		global $wpdb;

		return $wpdb->get_var( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta 
									WHERE user_id = $user_id 
									#AND automator_trigger_log_id = $trigger_log_id 
									AND automator_trigger_id = $trigger_id 
									AND meta_key LIKE '$meta_key'
									AND run_number = $run_number
									LIMIT 0,1" );

	}

	/**
	 * @param null $meta_key
	 * @param null $trigger_id
	 * @param null $trigger_log_id
	 * @param null $run_number
	 * @param null $user_id
	 *
	 * @return null|string
	 */
	public function get_trigger_log_meta( $meta_key = null, $trigger_id = null, $trigger_log_id = null, $run_number = null, $user_id = null ) {

		if ( is_null( $run_number ) || is_null( $trigger_id ) || is_null( $trigger_log_id ) || is_null( $meta_key ) || is_null( $user_id ) ) {
			return null;
		}

		global $wpdb;

		$meta_value = $wpdb->get_var( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta 
									WHERE user_id = $user_id 
									AND automator_trigger_log_id = $trigger_log_id 
									AND automator_trigger_id = $trigger_id 
									AND meta_key = '$meta_key'
									AND run_number = $run_number
									LIMIT 0,1" );

		if ( ! empty( $meta_value ) ) {
			return $meta_value;
		}

		return null;

	}

	/**
	 * @param $id
	 *
	 * @return int
	 */
	public function maybe_get_recipe_id( $id ) {
		if ( is_object( $id ) ) {
			$id = isset( $id->ID ) ? $id->ID : null;
		}

		if ( is_null( $id ) || ! is_numeric( $id ) ) {
			return 0;
		}

		$allowed_post_types = [
			'uo-recipe',
			'uo-trigger',
			'uo-action',
			'uo-closure',
		];

		$post = get_post( $id );

		if ( $post instanceof \WP_Post && in_array( $post->post_type, $allowed_post_types ) ) {
			return (int) $post->post_parent;
		}

		return 0;
	}
}
