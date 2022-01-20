<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Get_Data
 *
 * @package Uncanny_Automator
 */
class Automator_Get_Data {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * Automator_Get_Data constructor.
	 */
	public function __construct() {
	}

	/**
	 * @return Automator_Get_Data
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Accepts a trigger, action, or closure id and return the corresponding trigger_code, action_code, or closure_code
	 *
	 * @param null $item_id
	 *
	 * @return null|string
	 */
	public function item_code_from_item_id( $item_id = null ) {

		$item_code = null;

		$recipes_data = Automator()->get_recipes_data( true );

		if ( empty( $recipes_data ) ) {
			return null;
		}

		$item_codes = array();

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
	 * @param $trigger_code null
	 *
	 * @return bool
	 */
	public function trigger_actions_from_trigger_code( $trigger_code = null ) {

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Automator()->error->add_error( 'get_trigger_action_from_trigger_code', 'ERROR: You are trying to get a trigger action from a trigger code without providing a $trigger_code', $this );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = Automator()->get_triggers();

		if ( empty( $system_triggers ) ) {
			return null;
		}

		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				return $system_trigger['action'];
			}
		}

		return null;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger add_action hook
	 *
	 * @param $trigger_code null
	 *
	 * @return bool
	 * @deprecated 3.0
	 */
	public function trigger_meta_from_trigger_code( $trigger_code = null ) {

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Automator()->error->add_error( 'trigger_meta_from_trigger_code', 'ERROR: You are trying to get a trigger meta from a trigger code without providing a $trigger_code', $this );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = Automator()->get_triggers();
		if ( empty( $system_triggers ) ) {
			return null;
		}

		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				return isset( $system_trigger['meta'] ) ? $system_trigger['meta'] : null;
			}
		}

		return null;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger sentence
	 *
	 * @param $trigger_code null
	 *
	 * @return string
	 */
	public function trigger_title_from_trigger_code( $trigger_code = null ) {

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Automator()->error->add_error( 'trigger_title_from_trigger_code', 'ERROR: You are trying to get a title from trigger code without providing a $trigger_code', $this );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = Automator()->get_triggers();

		if ( empty( $system_triggers ) ) {
			return null;
		}

		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				return str_replace( array( '{', '}' ), '', $system_trigger['select_option_name'] );
			}
		}

		return null;
	}

	/**
	 * Accepts a action code(most like from action meta) and returns that associated action title
	 *
	 * @param $action_code null
	 *
	 * @return string
	 */
	public function action_title_from_action_code( $action_code = null ) {

		if ( null === $action_code || ! is_string( $action_code ) ) {
			Automator()->error->add_error( 'action_title_from_action_code', 'ERROR: You are trying to get a title from action code without providing a $trigger_code', $this );

			return null;
		}

		// Load all default trigger settings
		$system_actions = Automator()->get_actions();
		if ( empty( $system_actions ) ) {
			return null;
		}

		foreach ( $system_actions as $system_action ) {

			if ( $system_action['code'] === $action_code ) {
				return str_replace( array( '{', '}' ), '', $system_action['select_option_name'] );
			}
		}

		return null;
	}

	/**
	 * @param        $id
	 * @param $type
	 *
	 * @return array|mixed|string
	 */
	public function action_sentence( $id, $type = 'all' ) {

		global $wpdb;

		if ( 0 === absint( $id ) ) {
			return '';
		}

		$action_meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d",
				$id
			)
		);

		$sentence = $this->get_trigger_action_sentence( $id );
		$sentence = apply_filters_deprecated(
			'get_action_sentence',
			array(
				$sentence,
				$type,
				$action_meta,
			),
			'3.0',
			'automator_get_action_sentence'
		);
		$sentence = apply_filters( 'automator_get_action_sentence', $sentence, $id, $type );

		if ( 'all' === $type ) {
			return $sentence;
		}

		if ( in_array( $type, array_keys( $sentence ), true ) ) {
			return $sentence[ $type ];
		}

		return $sentence;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger validation function
	 *
	 * @param $trigger_code null
	 *
	 * @return null|array String is the function is not within a class and array if it is
	 */
	public function trigger_validation_function_from_trigger_code( $trigger_code = null ) {
		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Automator()->error->add_error( 'get_trigger_validation_function_from_trigger_code', 'ERROR: You are trying to get a trigger validation function from a trigger code without providing a $trigger_code', $this );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = Automator()->get_triggers();
		if ( empty( $system_triggers ) ) {
			return null;
		}

		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				return $system_trigger['validation_function'];
			}
		}

		return null;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger sentence
	 *
	 * @param $trigger_code null
	 *
	 * @return string
	 */
	public function trigger_integration_from_trigger_code( $trigger_code = null ) {

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Automator()->error->add_error( 'trigger_integration_from_trigger_code', 'ERROR: You are trying to get a trigger integration code from a trigger code without providing an $trigger_code', $this );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = Automator()->get_triggers();

		if ( empty( $system_triggers ) ) {
			return null;
		}

		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				return $system_trigger['integration'];
			}
		}

		global $wpdb;

		// Integration is not active ... get integration from DB
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value
					FROM $wpdb->postmeta
					WHERE post_id IN (
					SELECT post_id
					FROM $wpdb->postmeta
					WHERE meta_key = 'code'
					AND meta_value = %s
					)
					AND meta_key = 'integration'",
				$trigger_code
			)
		);
	}

	/**
	 * Accepts a action code(most like from action meta) and returns that associated action sentence
	 *
	 * @param null $action_code
	 *
	 * @return string
	 */
	public function action_integration_from_action_code( $action_code = null ) {

		if ( null === $action_code || ! is_string( $action_code ) ) {
			Automator()->error->add_error( 'action_integration_from_action_code', 'ERROR: You are trying to get a action integration code from a action code without providing an $action_code', $this );

			return null;
		}

		// Load all default trigger settings
		$system_actions = Automator()->get_actions();

		if ( empty( $system_actions ) ) {
			return null;
		}

		foreach ( $system_actions as $system_action ) {

			if ( $system_action['code'] === $action_code ) {
				return $system_action['integration'];
			}
		}

		global $wpdb;

		// Integration is not active ... get integration from DB
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value
				FROM $wpdb->postmeta
				WHERE post_id IN (
				SELECT post_id
				FROM $wpdb->postmeta
				WHERE meta_key = 'code'
				AND meta_value = %s
				)
				AND meta_key = 'integration'",
				$action_code
			)
		);
	}

	/**
	 * Accepts a closure code(most like from closure meta) and returns that associated closure integration
	 *
	 * @param null $closure_code
	 *
	 * @return string
	 */
	public function closure_integration_from_closure_code( $closure_code = null ) {

		if ( null === $closure_code || ! is_string( $closure_code ) ) {
			Automator()->error->add_error( 'closure_integration_from_closure_code', 'ERROR: You are trying to get a action integration code from a action code without providing an $action_code', $this );

			return null;
		}

		// Load all default trigger settings
		$system_closures = Automator()->get_closures();

		if ( empty( $system_closures ) ) {
			return null;
		}

		foreach ( $system_closures as $system_closure ) {

			if ( $system_closure['code'] === $closure_code ) {
				return $system_closure['integration'];
			}
		}

		global $wpdb;

		// Integration is not active ... get integration from DB
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value
				FROM $wpdb->postmeta
				WHERE post_id IN (
				SELECT post_id
				FROM $wpdb->postmeta
				WHERE meta_key = 'code'
				AND meta_value = %s
				)
				AND meta_key = 'integration'",
				$closure_code
			)
		);
	}

	/**
	 * Accepts an action code(most like from trigger meta) and returns that associated action execution function
	 *
	 * @param null $action_code
	 *
	 * @return null|            |array String is the function is not within a class and array if it is
	 */
	public function action_execution_function_from_action_code( $action_code = null ) {

		if ( null === $action_code || ! is_string( $action_code ) ) {
			Automator()->error->add_error( 'action_execution_function_from_action_code', 'ERROR: You are trying to get an action execution function from an action code without providing a $action_code', $this );

			return null;
		}

		// Load all default trigger settings
		$system_actions = Automator()->get_actions();

		if ( empty( $system_actions ) ) {
			return null;
		}
		foreach ( $system_actions as $system_action ) {

			if ( $system_action['code'] === $action_code ) {

				return $system_action['execution_function'];
			}
		}

		return null;
	}

	/**
	 * Accepts an action code(most like from trigger meta) and returns that associated action execution function
	 *
	 * @param null $closure_code
	 *
	 * @return null|             |array String is the public function is not within a class and array if it is
	 */
	public function closure_execution_function_from_closure_code( $closure_code = null ) {

		if ( null === $closure_code || ! is_string( $closure_code ) ) {
			Automator()->error->add_error( 'closure_execution_function_from_closure_code', 'ERROR: You are trying to get an action execution function from an action code without providing a $action_code', $this );

			return null;
		}

		// Load all default trigger settings
		$system_closures = Automator()->get_closures();
		if ( empty( $system_closures ) ) {
			return null;
		}

		foreach ( $system_closures as $system_closure ) {
			if ( $system_closure['code'] === $closure_code ) {

				return $system_closure['execution_function'];
			}
		}

		return null;
	}

	/**
	 * @param null $trigger_code
	 * @param null $meta
	 *
	 * @return mixed|string|null
	 */
	public function value_from_trigger_meta( $trigger_code = null, $meta = null ) {
		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Automator()->error->add_error( 'value_from_trigger_meta', 'ERROR: You are trying to get a meta value from a trigger code without providing a $trigger_code', $this );

			return null;
		}
		if ( null === $meta || ! is_string( $meta ) ) {
			Automator()->error->add_error( 'value_from_trigger_meta', 'ERROR: You are trying to get a meta value from a trigger code without providing a $meta', $this );

			return null;
		}

		// Load all default trigger settings
		$meta_value      = null;
		$system_triggers = Automator()->get_triggers();
		if ( empty( $system_triggers ) ) {
			return $meta_value;
		}

		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				return isset( $system_trigger[ $meta ] ) ? $system_trigger[ $meta ] : $meta_value;
			}
		}

		return $meta_value;
	}

	/**
	 * @param null $action_code
	 * @param null $meta
	 *
	 * @return mixed|string|null
	 */
	public function value_from_action_meta( $action_code = null, $meta = null ) {

		if ( null === $action_code || ! is_string( $action_code ) ) {
			Automator()->error->add_error( 'value_from_action_meta', 'ERROR: You are trying to get a action meta from an action code without providing an $action_code', $this );

			return null;
		}
		if ( null === $meta || ! is_string( $meta ) ) {
			Automator()->error->add_error( 'value_from_action_meta', 'ERROR: You are trying to get an action meta from an action code without providing a $meta', $this );

			return null;
		}

		// Load all default trigger settings
		$meta_value     = null;
		$system_actions = Automator()->get_actions();

		if ( empty( $system_actions ) ) {
			return $meta_value;
		}

		foreach ( $system_actions as $system_action ) {

			if ( $system_action['code'] === $action_code ) {
				return isset( $system_action[ $meta ] ) ? $system_action[ $meta ] : $meta_value;
			}
		}

		return $meta_value;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger validation function
	 * priority
	 *
	 * @param null $trigger_code
	 *
	 * @return null|             |int Default priority is always 10
	 */
	public function trigger_priority_from_trigger_code( $trigger_code = null ) {

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Automator()->error->add_error( 'trigger_priority_from_trigger_code', 'ERROR: You are trying to get a trigger priority from a trigger code without providing a $trigger_code', $this );

			return null;
		}

		// Load all default trigger settings
		// Default priority if not set
		$trigger_priority = 10;
		$system_triggers  = Automator()->get_triggers();
		if ( empty( $system_triggers ) ) {
			return $trigger_priority;
		}

		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				return $system_trigger['priority'];
			}
		}

		return $trigger_priority;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger validation function
	 * priority
	 *
	 * @param null $trigger_code
	 *
	 * @return array
	 */
	public function trigger_tokens_from_trigger_code( $trigger_code = null ) {

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Automator()->error->add_error( 'trigger_priority_from_trigger_code', 'ERROR: You are trying to get a trigger priority from a trigger code without providing a $trigger_code', $this );

			return null;
		}

		// Load all default trigger settings
		// Default priority if not set
		$trigger_tokens  = array();
		$system_triggers = Automator()->get_triggers();
		if ( empty( $system_triggers ) ) {
			return $trigger_tokens;
		}

		foreach ( $system_triggers as $system_trigger ) {
			if ( $system_trigger['code'] === $trigger_code ) {
				return isset( $system_trigger['tokens'] ) ? $system_trigger['tokens'] : $trigger_tokens;
			}
		}

		return $trigger_tokens;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger validation function
	 * accepted args
	 *
	 * @param null $trigger_code
	 *
	 * @return null|             |int Default arguments is always 1
	 */
	public function trigger_accepted_args_from_trigger_code( $trigger_code = null ) {

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Automator()->error->add_error( 'trigger_accepted_args_from_trigger_code', 'ERROR: You are trying to get a trigger validation function accepted args from a trigger code without providing a $trigger_code', $this );

			return null;
		}
		$trigger_accepted_args = 1;
		// Load all default trigger settings
		$system_triggers = Automator()->get_triggers();
		if ( empty( $system_triggers ) ) {
			return $trigger_accepted_args;
		}
		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				return $system_trigger['accepted_args'];
			}
		}

		return $trigger_accepted_args;
	}

	/**
	 * Accepts a trigger code(most like from trigger meta) and returns that associated trigger options
	 *
	 * @param null $trigger_code
	 *
	 * @return array
	 */
	public function trigger_options_from_trigger_code( $trigger_code ) {

		if ( null === $trigger_code || ! is_string( $trigger_code ) ) {
			Automator()->error->add_error( 'trigger_options_from_trigger_code', 'ERROR: You are trying to get a trigger options from a trigger code without providing a $trigger_code', $this );

			return null;
		}

		// Load all default trigger settings
		$system_triggers = Automator()->get_triggers();

		$trigger_options = array();
		if ( empty( $system_triggers ) ) {
			return $trigger_options;
		}
		foreach ( $system_triggers as $system_trigger ) {

			if ( $system_trigger['code'] === $trigger_code ) {
				return $system_trigger['accepted_args'];
			}
		}

		return $trigger_options;
	}

	/**
	 * Get the trigger log ID for the user
	 *
	 * @param      $user_id       null
	 * @param      $trigger_id    null
	 * @param null $recipe_id null
	 * @param null $recipe_log_id null
	 *
	 * @return null|int
	 */
	public function trigger_log_id( $user_id = null, $trigger_id = null, $recipe_id = null, $recipe_log_id = null ) {

		// Set user ID
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( null === $trigger_id || ! is_numeric( $trigger_id ) ) {
			Automator()->error->add_error( 'trigger_log_id', 'ERROR: You are trying to get a trigger log ID without providing a trigger_id', $this );

			return null;
		}

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Automator()->error->add_error( 'trigger_log_id', 'ERROR: You are trying to get a trigger lod ID without providing a recipe_id', $this );

			return null;
		}

		global $wpdb;
		$results = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->prefix}uap_trigger_log WHERE user_id = %d AND automator_trigger_id = %d AND automator_recipe_id = %d AND automator_recipe_log_id = %d",
				$user_id,
				$trigger_id,
				$recipe_id,
				$recipe_log_id
			)
		);

		if ( empty( $results ) ) {
			return null;
		}

		return (int) $results;
	}

	/**
	 * Get the trigger for the user
	 *
	 * @param null $user_id
	 * @param null $trigger_id
	 * @param null $meta_key
	 * @param $trigger_log_id
	 *
	 * @return null|string
	 */
	public function trigger_meta( $user_id = null, $trigger_id = null, $meta_key = null, $trigger_log_id = null ) {

		// Set user ID
		if ( ! absint( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( null === $trigger_id || ! is_numeric( $trigger_id ) ) {
			Automator()->error->add_error( 'trigger_meta', 'ERROR: You are trying to get trigger meta without providing a trigger_id', $this );

			return null;
		}

		if ( null === $meta_key || ! is_string( $meta_key ) ) {
			Automator()->error->add_error( 'trigger_meta', 'ERROR: You are trying to get trigger meta without providing a meta_key', $this );

			return null;
		}

		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(meta_value) FROM {$wpdb->prefix}uap_trigger_log_meta WHERE user_id = %d AND meta_key LIKE %s AND automator_trigger_id = %d AND automator_trigger_log_id = %d",
				$user_id,
				$meta_key,
				$trigger_id,
				$trigger_log_id
			)
		);
	}

	/**
	 * @param      $recipe_id
	 * @param      $user_id
	 * @param $fetch_current
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
				if ( false === $fetch_current ) {
					$run_number ++;
				}

				return $run_number;
			}
		}

		return 1;
	}

	/**
	 * @param        $id
	 * @param $type
	 *
	 * @return array|mixed|string
	 */
	public function trigger_sentence( $id, $type = '' ) {

		global $wpdb;
		$trigger_meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d",
				$id
			)
		);

		$sentence = $this->get_trigger_action_sentence( $id );
		$sentence = apply_filters_deprecated(
			'get_trigger_sentence',
			array( $sentence, $type, $trigger_meta ),
			'3.0',
			'automator_get_trigger_sentence'
		);
		$sentence = apply_filters( 'automator_get_trigger_sentence', $sentence, $id, $type );

		if ( in_array( $type, array_keys( $sentence ), true ) ) {
			return $sentence[ $type ];
		}

		return $sentence;
	}

	/**
	 * @param $id
	 *
	 * @return array
	 */
	public function get_trigger_action_sentence( $id ) {
		global $wpdb;

		if ( 0 === absint( $id ) ) {
			return array();
		}

		$metas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d",
				$id
			)
		);

		if ( empty( $metas ) ) {
			return array();
		}

		$code                         = false;
		$raw_sentence                 = false;
		$sentence_human_readable      = false;
		$sentence_human_readable_html = false;

		foreach ( $metas as $meta ) {
			if ( 'code' === $meta->meta_key ) {
				$code = $meta->meta_value;
			}
			if ( 'sentence' === $meta->meta_key ) {
				$raw_sentence = $meta->meta_value;
			}
			if ( 'sentence_human_readable' === $meta->meta_key ) {
				$sentence_human_readable = $meta->meta_value;
			}
			if ( 'sentence_human_readable_html' === $meta->meta_key ) {
				$sentence_human_readable_html = $meta->meta_value;
			}
		}

		if ( false === $code || false === $raw_sentence ) {
			return array();
		}

		$re = '/{{(.*?)}}/m';
		preg_match_all( $re, $raw_sentence, $matches, PREG_SET_ORDER, 0 );

		$tokens = array();
		if ( ! empty( $matches ) ) {
			foreach ( $matches as $key => $match ) {
				$tokens[ $key ]['brackets']       = $match[0];
				$tokens[ $key ]['inner_brackets'] = $match[1];
				$token                            = explode( ':', $match[1] );
				$tokens[ $key ]['token']          = $token[1];
				foreach ( $metas as $trigger ) {
					if ( $token[1] === $trigger->meta_key ) {
						$tokens[ $key ]['token_value'] = $trigger->meta_value;
					}
				}
			}
		}

		$complete_sentence = $raw_sentence;

		if ( ! empty( $tokens ) ) {
			foreach ( $tokens as $token ) {
				if ( key_exists( 'token', $token ) && key_exists( 'token_value', $token ) ) {
					$complete_sentence = str_replace( $token['token'], $token['token_value'], $complete_sentence );
				}
			}
		}

		return array(
			'code'                         => $code,
			'raw_sentence'                 => $raw_sentence,
			'tokens'                       => $tokens,
			'complete_sentence'            => $complete_sentence,
			'sentence_human_readable'      => $sentence_human_readable,
			'sentence_human_readable_html' => $sentence_human_readable_html,
			'metas'                        => $metas,
		);
	}

	/**
	 * @param $trigger_id
	 * @param $trigger_log_id
	 * @param $user_id
	 *
	 * @return int|null|string
	 */
	public function trigger_run_number( $trigger_id, $trigger_log_id, $user_id ) {
		// Seems like Anonymous trigger. Return 1.
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
		}

		return $run_number;
	}

	/**
	 * @param      $check_trigger_code
	 * @param null $recipe_id
	 *
	 * @return array
	 */
	public function recipes_from_trigger_code( $check_trigger_code = null, $recipe_id = null ) {
		if ( null === $check_trigger_code ) {
			return array();
		}
		$key = 'automator_recipes_of_' . $check_trigger_code;
		// If recipe id is set then only specific recipe data needed instead of all recipes by code
		if ( ! empty( $recipe_id ) ) {
			$key .= '_' . $recipe_id;
		}

		$return = Automator()->cache->get( $key );
		if ( ! empty( $return ) ) {
			return $return;
		}
		$return = array();
		// Get recipes that are in the memory right now.
		$recipes = Automator()->get_recipes_data( false, $recipe_id );

		if ( empty( $recipes ) ) {
			return array();
		}

		foreach ( $recipes as $recipe ) {

			if ( 'publish' !== (string) $recipe['post_status'] ) {
				continue;
			}

			foreach ( $recipe['triggers'] as $trigger ) {

				$trigger_code = $trigger['meta']['code'];

				// Skip if the executed trigger doesn't match
				if ( (string) $check_trigger_code !== (string) $trigger_code ) {
					continue;
				}

				$recipe_id            = absint( $recipe['ID'] );
				$return[ $recipe_id ] = $recipe;
			}
		}

		Automator()->cache->set( $key, $return );

		return $return;
	}

	/**
	 * @param $recipes
	 * @param $trigger_meta
	 *
	 * @return array
	 */
	public function meta_from_recipes( $recipes = array(), $trigger_meta = null ) {
		$metas = array();
		if ( empty( $recipes ) ) {
			return $metas;
		}
		if ( null === $trigger_meta ) {
			return $metas;
		}

		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$recipe_id = $recipe['ID'];
				if ( array_key_exists( $trigger_meta, $trigger['meta'] ) ) {
					$value = $trigger['meta'][ $trigger_meta ];

					// If the meta is a custom value
					if ( 'automator_custom_value' === $value ) {
						$value = $trigger['meta'][ $trigger_meta . '_custom' ];
					}
					
					$metas[ $recipe_id ][ $trigger['ID'] ] = $value;
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
	 * @return string|null
	 */
	public function maybe_get_meta_id_from_trigger_log( $run_number = null, $trigger_id = null, $trigger_log_id = null, $meta_key = null, $user_id = null ) {
		if ( is_null( $run_number ) || is_null( $trigger_id ) || is_null( $trigger_log_id ) || is_null( $meta_key ) || is_null( $user_id ) ) {
			return null;
		}

		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->prefix}uap_trigger_log_meta
									WHERE user_id = %d
									AND automator_trigger_log_id = %d
									AND automator_trigger_id = %d
									AND meta_key = %s
									AND run_number = %d
									LIMIT 0,1",
				$user_id,
				$trigger_log_id,
				$trigger_id,
				$meta_key,
				$run_number
			)
		);
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

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta
									WHERE user_id = %d
									AND automator_trigger_log_id = %d
									AND automator_trigger_id = %d
									AND meta_key = %s
									AND run_number = %d
									LIMIT 0,1",
				$user_id,
				$trigger_log_id,
				$trigger_id,
				$meta_key,
				$run_number
			)
		);

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
		$meta_value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta
									WHERE user_id = %d
									AND automator_trigger_log_id = %d
									AND automator_trigger_id = %d
									AND meta_key = %s
									AND run_number = %d
									LIMIT 0,1",
				$user_id,
				$trigger_log_id,
				$trigger_id,
				$meta_key,
				$run_number
			)
		);
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

		$allowed_post_types = apply_filters(
			'automator_allowed_post_types',
			array(
				'uo-recipe',
				'uo-trigger',
				'uo-action',
				'uo-closure',
			)
		);

		$post = get_post( $id );

		if ( $post instanceof \WP_Post && 'uo-recipe' === $post->post_type ) {
			return absint( $post->ID );
		}

		if ( $post instanceof \WP_Post && in_array( $post->post_type, $allowed_post_types, true ) ) {
			return absint( $post->post_parent );
		}

		return 0;
	}

	/**
	 * @param $recipe_id
	 *
	 * @return false|mixed
	 */
	public function get_recipe_requires_user( $recipe_id ) {
		$requires_user  = get_post_meta( $recipe_id, 'recipe_requires_user', true );
		$recipe_version = get_post_meta( $recipe_id, 'uap_recipe_version', true );
		if ( empty( $requires_user ) ) {
			if ( version_compare( $recipe_version, 3.1, '>=' ) ) {
				return false;
			}

			return true;
		}

		return $requires_user;
	}

	/**
	 * @param $trigger_id
	 * @param $run_number
	 * @param $recipe_id
	 * @param $meta_key
	 * @param $user_id
	 * @param $recipe_log_id
	 *
	 * @return string|null
	 */
	public function mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, $meta_key, $user_id, $recipe_log_id ) {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tm.meta_value
FROM {$wpdb->prefix}uap_trigger_log_meta tm
LEFT JOIN {$wpdb->prefix}uap_trigger_log t
ON tm.automator_trigger_log_id = t.ID
WHERE t.automator_trigger_id = %d
  AND t.automator_recipe_log_id = %d
  AND t.automator_recipe_id = %d
  AND t.user_id = %d
  AND tm.run_number = %d
  AND tm.meta_key = %s",
				$trigger_id,
				$recipe_log_id,
				$recipe_id,
				$user_id,
				$run_number,
				$meta_key
			)
		);
	}

	/**
	 * @param $trigger_id
	 * @param $run_number
	 * @param $recipe_id
	 * @param $meta_key
	 * @param $user_id
	 * @param $recipe_log_id
	 *
	 * @return string|null
	 */
	public function mayabe_get_real_trigger_log_id( $trigger_id, $run_number, $recipe_id, $user_id, $recipe_log_id ) {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tm.automator_trigger_log_id
FROM {$wpdb->prefix}uap_trigger_log_meta tm
LEFT JOIN {$wpdb->prefix}uap_trigger_log t
ON tm.automator_trigger_log_id = t.ID
WHERE t.automator_trigger_id = %d
  AND t.automator_recipe_log_id = %d
  AND t.automator_recipe_id = %d
  AND t.user_id = %d
  AND tm.run_number = %d",
				$trigger_id,
				$recipe_log_id,
				$recipe_id,
				$user_id,
				$run_number
			)
		);
	}

	/**
	 * total_completed_runs
	 *
	 * @return void
	 */
	public function total_completed_runs() {
		global $wpdb;

		$tbl = Automator()->db->tables->recipe;

		$results = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}{$tbl} WHERE completed=1" ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return apply_filters( 'automator_total_completed_runs', absint( $results ) );
	}

	/**
	 * completed_runs
	 *
	 * @param mixed $seconds_to_include
	 *
	 * @return void
	 */
	public function completed_runs( $seconds_to_include = null ) {
		global $wpdb;

		$tbl   = Automator()->db->tables->recipe;
		$query = "SELECT COUNT(*) FROM {$wpdb->prefix}{$tbl} WHERE completed=1";

		if ( null !== $seconds_to_include ) {
			$timestamp = current_time( 'timestamp' );
			$time_ago  = strtotime( "-$seconds_to_include Seconds", $timestamp );
			$date      = date_i18n( 'Y-m-d H:i:s', $time_ago );
			$query     .= " AND date_time >= '$date'";
		}

		$results = $wpdb->get_var( $query ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return apply_filters( 'automator_completed_runs', absint( $results ) );
	}

	/**
	 * Return the number of times recipe is completed successfully
	 *
	 * @param $recipe_id
	 *
	 * @return string|null
	 */
	public function recipe_completed_times( $recipe_id ) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(run_number) FROM {$wpdb->prefix}uap_recipe_log WHERE automator_recipe_id=%d AND completed = %d", $recipe_id, 1 ) );

		return is_numeric( $count ) ? $count : 0;
	}

	/**
	 * Return the number of times recipe is completed successfully by a user
	 *
	 * @param $recipe_id
	 * @param $user_id
	 *
	 * @return string|null
	 */
	public function recipe_completed_times_by_user( $recipe_id, $user_id ) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(run_number) FROM {$wpdb->prefix}uap_recipe_log WHERE automator_recipe_id=%d AND completed=%d AND user_id=%d", $recipe_id, 1, $user_id ) );

		return is_numeric( $count ) ? $count : 0;
	}
}
