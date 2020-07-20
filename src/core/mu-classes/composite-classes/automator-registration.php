<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Registration
 * @package Uncanny_Automator
 */
class Automator_Registration {

	public function __construct() {
	}

	/**
	 * @param $type
	 * @param $details
	 *
	 * @return bool|null
	 */
	public function recipe_type( $type, $details ) {

		global $uncanny_automator;

		if ( null === $type || ! is_string( $type ) ) {
			Utilities::log( 'ERROR: You are trying to register an integration without passing an integration code.', 'register_integration ERROR', false, 'uap-errors' );

			return null;
		}

		/*if ( null === $integration || ! is_array( $integration ) ) {
			Utilities::log( 'ERROR: You are trying to register an integration without passing an integration object.', 'register_integration ERROR', false, 'uap-errors' );

			return null;
		}*/

		// Register integration if it doesn't already exist
		if ( ! key_exists( $type, $uncanny_automator->get_recipe_types() ) ) {
			$uncanny_automator->recipe_types[ $type ] = $details;
		} else {
			Utilities::log( 'ERROR: You are trying to register an integration code that already exists.', 'register_integration ERROR', false, 'uap-errors' );

			return null;
		}

		// Order integrations alphabetically
		//$uncanny_automator->actions[] = $uncanny_automator->utilities->keep_order_of_options( $uap_action );

		return true;
	}

	/**
	 * Add a new integration
	 *
	 * @param $integration_code null||string
	 * @param $integration      null||array
	 *
	 * @return null|                 |bool
	 */
	public function integration( $integration_code = null, $integration = null ) {

		global $uncanny_automator;

		if ( null === $integration_code || ! is_string( $integration_code ) ) {
			Utilities::log( 'ERROR: You are trying to register an integration without passing an integration code.', 'register_integration ERROR', false, 'uap-errors' );

			return null;
		}

		if ( null === $integration || ! is_array( $integration ) ) {
			Utilities::log( 'ERROR: You are trying to register an integration without passing an integration object.', 'register_integration ERROR', false, 'uap-errors' );

			return null;
		}

		// Register integration if it doesn't already exist
		if ( ! key_exists( $integration_code, $uncanny_automator->get_integrations() ) ) {
			$uncanny_automator->integrations[ $integration_code ] = $integration;
		} else {
			Utilities::log( 'ERROR: You are trying to register an integration code that already exists.', 'register_integration ERROR', false, 'uap-errors' );

			return null;
		}

		// Order integrations alphabetically
		$uncanny_automator->utilities->sort_integrations_alphabetically();

		return true;
	}

	/**
	 * Register a new trigger and creates a type if defined and the type does not exist
	 *
	 * @param $trigger          null||array
	 * @param $integration_code null|string
	 * @param $integration      null||array
	 *
	 * @return null|                 |true
	 */
	public function trigger( $trigger = null, $integration_code = null, $integration = null ) {

		global $uncanny_automator;


		// Sanity check that there was a trigger passed
		if ( null === $trigger || ! is_array( $trigger ) ) {
			Utilities::log( 'ERROR: You are try to register a trigger without passing a trigger object.', 'register_trigger ERROR', false, 'uap-errors' );

			return null;
		}

		/**
		 * Use this hook the stop specific triggers from being registered by returning true
		 */
		$skip_trigger_registration = apply_filters( 'skip_trigger_registration', false, $trigger, $integration_code, $integration );
		if ( true === $skip_trigger_registration ) {
			return null;
		}

		/**
		 * Use this hook the override specific triggers type, i.e., utility or user
		 */
		if ( ! key_exists( 'type', $trigger ) ) {
			$trigger_type    = apply_filters( 'uap_trigger_type', 'user', $trigger, $integration_code, $integration );
			$trigger['type'] = $trigger_type;
		}

		/**
		 * Use this hook to modify the trigger before it it error checked and registered
		 */
		$trigger = apply_filters( 'uap_register_trigger', $trigger, $integration_code, $integration );

		/**
		 * Use this hook to modify the integration_code before it is error checked and registered
		 */
		$integration_code = apply_filters( 'uap_register_trigger_integration_code', $integration_code, $trigger, $integration );

		/**
		 * Use this hook to modify the integration_code before it is error checked and registered
		 */
		$integration = apply_filters( 'uap_register_trigger_integration', $integration, $trigger, $integration_code );

		// Integration was passed in, lets try to register it
		if ( null !== $integration_code ) {
			if ( ! is_string( $integration_code ) ) {
				Utilities::log( 'ERROR: You are try to register a trigger without passing an proper integration code.', 'register_trigger ERROR', false, 'uap-errors' );

				return null;
			} else {
				if ( null === $integration && is_array( $integration ) ) {
					Utilities::log( 'ERROR: You are try to register a trigger without passing an proper integration object.', 'register_trigger ERROR', false, 'uap-errors' );

					return null;
				} else {
					// Sanity check that the integration code does not exist already
					if ( ! key_exists( $integration_code, $uncanny_automator->get_integrations() ) ) {
						$uncanny_automator->register->integration( $integration_code, $integration );
					} else {
						Utilities::log( 'ERROR: You are try to register a trigger with a integration code that already exists.', 'register_trigger ERROR', false, 'uap-errors' );
						// Since it already exist we can safely try to add the trigger anyway
					}
				}
			}
		}

		// Sanity check that trigger_integration isset
		if ( ! isset( $trigger['integration'] ) ) {
			Utilities::log( 'ERROR: You are try to register a trigger without setting it\'s trigger_integration', 'register_trigger ERROR', false, 'uap-errors' );

			return null;
		}

		// Sanity check that the trigger has a integration that is defined
		if ( ! key_exists( $trigger['integration'], $uncanny_automator->get_integrations() ) ) {
			Utilities::log( 'ERROR: You are try to register a trigger to a integration that doesn\'t exist.', 'register_trigger ERROR', false, 'uap-errors' );

			return null;
		}

		// Sanity check that trigger_code isset
		if ( ! isset( $trigger['code'] ) ) {
			Utilities::log( 'ERROR: You are try to register a trigger without setting it\'s trigger_code', 'register_trigger ERROR', false, 'uap-errors' );

			return null;
		}

		// Sanity check that trigger_name isset
		if ( ! isset( $trigger['select_option_name'] ) ) {
			Utilities::log( 'ERROR: You are try to register a trigger without setting it\'s trigger_name', 'register_trigger ERROR', false, 'uap-errors' );

			return null;
		}

		// Sanity check that trigger_action isset
		if ( ! isset( $trigger['action'] ) ) {
			Utilities::log( 'ERROR: You are try to register a trigger without setting it\'s trigger_action', 'register_trigger ERROR', false, 'uap-errors' );

			return null;
		}

		// Sanity check that trigger_validation_function isset
		if ( ! isset( $trigger['validation_function'] ) ) {
			Utilities::log( 'ERROR: You are try to register a trigger without setting it\'s trigger_validation_function', 'register_trigger ERROR', false, 'uap-errors' );

			return null;
		}

		// Loop through existing to force only unique values for trigger_code and trigger_name
		foreach ( $uncanny_automator->get_triggers() as $existing_trigger ) {

			// Sanity check that trigger_code is unique
			if ( $existing_trigger['code'] === $trigger['code'] ) {
				Utilities::log( 'ERROR: You are try to register a trigger with a trigger_code that was already registered.', 'register_trigger ERROR', false, 'uap-errors' );

				return null;
			}

		}

		// Register the trigger into the system
		$uncanny_automator->triggers[] = $uncanny_automator->utilities->keep_order_of_options( $trigger );

		return true;

	}

	/**
	 * Register a new uap action and creates a type if defined and the type does not exist
	 *
	 * @param $uap_action       null||array
	 * @param $integration_code null|string
	 * @param $integration      null||array
	 *
	 * @return null|                 |true
	 */
	public function action( $uap_action = null, $integration_code = null, $integration = null ) {

		global $uncanny_automator;

		// Sanity check that there was a trigger passed
		if ( null === $uap_action || ! is_array( $uap_action ) ) {
			Utilities::log( 'ERROR: You are trying to register an action without passing a action object.', 'register_action ERROR', false, 'uap-errors' );

			return null;
		}

		/**
		 * Use this hook the stop specific actions from being registered by returning true
		 */
		$skip_uap_action_registration = apply_filters( 'skip_uap_action_registration', false, $uap_action, $integration_code, $integration );
		if ( true === $skip_uap_action_registration ) {
			return null;
		}

		/**
		 * Use this hook to modify the uap action before it it error checked and registered
		 */
		$uap_action = apply_filters( 'uap_register_action', $uap_action, $integration_code, $integration );

		/**
		 * Use this hook to modify the integration_code before it is error checked and registered
		 */
		$integration_code = apply_filters( 'uap_register_action_integration_code', $integration_code, $uap_action, $integration );

		/**
		 * Use this hook to modify the integration_code before it is error checked and registered
		 */
		$integration = apply_filters( 'uap_register_action_integration', $integration, $uap_action, $integration_code );

		// Integration was passed in, lets try to register it
		if ( null !== $integration_code ) {
			if ( ! is_string( $integration_code ) ) {
				Utilities::log( 'ERROR: You are trying to register an action without passing an proper integration code.', 'register_action ERROR', false, 'uap-errors' );

				return null;
			} else {
				if ( null === $integration && is_array( $integration ) ) {
					Utilities::log( 'ERROR: You are trying to register an action without passing an proper integration object.', 'register_action ERROR', false, 'uap-errors' );

					return null;
				} else {
					// Sanity check that the integration code does not exist already
					if ( ! key_exists( $integration_code, $uncanny_automator->get_integrations() ) ) {
						$uncanny_automator->register->integration( $integration_code, $integration );
					} else {
						Utilities::log( 'ERROR: You are trying to register an action with a integration code that already exists.', 'register_action ERROR', false, 'uap-errors' );
						// Since it already exist we can safely try to add the trigger anyway
					}
				}
			}
		}

		// Sanity check that trigger_integration isset
		if ( ! isset( $uap_action['integration'] ) ) {
			Utilities::log( 'ERROR: You are trying to register an action without setting it\'s action_integration', 'register_action ERROR', false, 'uap-errors' );

			return null;
		}

		// Sanity check that the trigger has a integration that is defined
		if ( ! key_exists( $uap_action['integration'], $uncanny_automator->get_integrations() ) ) {
			Utilities::log( 'ERROR: You are trying to register an action to a integration that doesn\'t exist.', 'register_action ERROR', false, 'uap-errors' );

			return null;
		}

		// Sanity check that trigger_code isset
		if ( ! isset( $uap_action['code'] ) ) {
			Utilities::log( 'ERROR: You are trying to register an action without setting it\'s action_code', 'register_action ERROR', false, 'uap-errors' );

			return null;
		}

		// Sanity check that execution_function isset
		if ( ! isset( $uap_action['execution_function'] ) ) {
			Utilities::log( 'ERROR: You are trying to register an action without setting it\'s execution_function', 'register_action ERROR', false, 'uap-errors' );

			return null;
		}


		// Loop through existing to force only unique values for action_code and action_name
		foreach ( $uncanny_automator->get_actions() as $existing_action ) {

			// Sanity check that action_code is unique
			if ( $existing_action['code'] === $uap_action['code'] ) {
				Utilities::log( 'ERROR: You are trying to register an action with a action_code that was already registered.', 'register_action ERROR', false, 'uap-errors' );

				return null;
			}
		}

		$uncanny_automator->actions[] = $uncanny_automator->utilities->keep_order_of_options( $uap_action );

		return true;
	}

	/**
	 * Registers a new closure and creates a type if defined and the type does not exist
	 *
	 * @param $closure          null||array
	 * @param $integration_code null|string
	 * @param $integration      null||array
	 *
	 * @return null|                 |true
	 */
	public function closure( $closure = null, $integration_code = null, $integration = null ) {

		global $uncanny_automator;

		// Sanity check that there was a trigger passed
		if ( null === $closure || ! is_array( $closure ) ) {
			Utilities::log( 'ERROR: You are try to register a closure without passing a closure object.', 'register_closure ERROR', false, 'uap-errors' );

			return null;
		}

		/**
		 * Use this hook the stop specific closures from being registered by returning true
		 */
		$skip_closure_registration = apply_filters( 'skip_closure_registration', false, $closure, $integration_code, $integration );
		if ( true === $skip_closure_registration ) {
			return null;
		}

		/**
		 * Use this hook to modify the uap closures before it it error checked and registered
		 */
		$closure = apply_filters( 'uap_register_closure', $closure, $integration_code, $integration );

		/**
		 * Use this hook to modify the integration_code before it is error checked and registered
		 */
		$integration_code = apply_filters( 'uap_register_closure_integration_code', $integration_code, $closure, $integration );

		/**
		 * Use this hook to modify the integration_code before it is error checked and registered
		 */
		$integration = apply_filters( 'uap_register_closure_integration', $integration, $closure, $integration_code );

		// Integration was passed in, lets try to register it
		if ( null !== $integration_code ) {
			if ( ! is_string( $integration_code ) ) {
				Utilities::log( 'ERROR: You are try to register a closure without passing an proper integration code.', 'register_uap_closure ERROR', false, 'uap-errors' );

				return null;
			} else {
				if ( null === $integration && is_array( $integration ) ) {
					Utilities::log( 'ERROR: You are try to register a closure without passing an proper integration object.', 'register_closure ERROR', false, 'uap-errors' );

					return null;
				} else {
					// Sanity check that the integration code does not exist already
					if ( ! key_exists( $integration_code, $uncanny_automator->get_integrations() ) ) {
						$uncanny_automator->register->integration( $integration_code, $integration );
					} else {
						Utilities::log( 'ERROR: You are try to register a closure with a integration code that already exists.', 'register_closure ERROR', false, 'uap-errors' );
						// Since it already exist we can safely try to add the trigger anyway
					}
				}
			}
		}

		// Sanity check that trigger_integration isset
		if ( ! isset( $closure['integration'] ) ) {
			Utilities::log( 'ERROR: You are try to register a closure without setting it\'s closure_integration', 'register_closure ERROR', false, 'uap-errors' );

			return null;
		}

		// Sanity check that the trigger has a integration that is defined
		if ( ! key_exists( $closure['integration'], $uncanny_automator->get_integrations() ) ) {
			Utilities::log( 'ERROR: You are try to register a closure to a integration that doesn\'t exist.', 'register_closure ERROR', false, 'uap-errors' );

			return null;
		}

		// Sanity check that trigger_code isset
		if ( ! isset( $closure['code'] ) ) {
			Utilities::log( 'ERROR: You are try to register a closure without setting it\'s closure_code', 'register_closure ERROR', false, 'uap-errors' );

			return null;
		}

		// Sanity check that trigger_validation_function isset
		if ( ! isset( $closure['execution_function'] ) ) {
			Utilities::log( 'ERROR: You are try to register a closure without setting it\'s closure_execution_function', 'register_closure ERROR', false, 'uap-errors' );

			return null;
		}

		// Loop through existing to force only unique values for closure_code and closure_name
		foreach ( $uncanny_automator->get_closures() as $existing_closure ) {

			// Sanity check that action_code is unique
			if ( $existing_closure['code'] === $closure['code'] ) {
				Utilities::log( 'ERROR: You are try to register a closure with a closure_code that was already registered.', 'register_closure ERROR', false, 'uap-errors' );

				return null;
			}

			// Sanity check that closure_name is unique
			if ( $existing_closure['name'] === $closure['name'] ) {
				Utilities::log( 'ERROR: You are try to register a closure with a closure_name that was already registered.', 'register_closure ERROR', false, 'uap-errors' );

				return null;
			}
		}

		$uncanny_automator->closures[] = $uncanny_automator->utilities->keep_order_of_options( $closure );

		return true;
	}
}
