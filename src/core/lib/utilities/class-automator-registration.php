<?php

namespace Uncanny_Automator;

use Exception;

/**
 * Class Automator_Registration
 *
 * @package Uncanny_Automator
 */
class Automator_Registration {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * Automator_Registration constructor.
	 */
	public function __construct() {
		add_filter(
			'automator_register_integration',
			array(
				$this,
				'set_connected_to_false_if_site_inactive',
			),
			99,
			2
		);
	}

	/**
	 * Set `connected` for each API integration to false IF the site is no
	 * longer connected to automatorplugin.com OR has a valid PRO license.
	 *
	 * @param $integration
	 * @param $integration_code
	 *
	 * @return mixed
	 */
	public function set_connected_to_false_if_site_inactive( $integration, $integration_code ) {
		// Not a Recipe Builder page
		if ( ! Automator()->helpers->recipe->is_edit_page() ) {
			return $integration;
		}
		// Doesn't have `connected` key
		if ( ! isset( $integration['connected'] ) ) {
			return $integration;
		}
		// Check if the site is connected
		$is_connected = Api_Server::get_license_type();
		// Site is no longer connected
		if ( false === $is_connected ) {
			$integration['connected'] = false;
		}

		return $integration;
	}

	/**
	 * @return Automator_Registration
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param $type
	 * @param $details
	 *
	 * @return bool|null
	 */
	public function recipe_type( $type, $details ) {

		if ( null === $type || ! is_string( $type ) ) {
			Automator()->wp_error->add_error( 'register_integration', 'ERROR: You are trying to register an integration without passing an integration code.', $this );

			return null;
		}

		// Register integration if it doesn't already exist
		if ( ! key_exists( $type, Automator()->get_recipe_types() ) ) {
			Automator()->set_recipe_type( $type, $details );
		}

		return true;
	}

	/**
	 * Register a new trigger and creates a type if defined and the type does not exist
	 *
	 * @param null $trigger
	 * @param null|string $integration_code
	 * @param null $integration
	 *
	 * @return null
	 * @throws Exception
	 */
	public function trigger( $trigger = null, $integration_code = null, $integration = null ) {

		// Sanity check that there was a trigger passed
		if ( null === $trigger || ! is_array( $trigger ) ) {
			throw new Automator_Exception( 'You are trying to register a trigger without passing a trigger object.', 1001 );
		}

		/**
		 * Use this hook the stop specific triggers from being registered by returning true
		 */
		$skip_trigger_registration = false;
		$skip_trigger_registration = apply_filters_deprecated(
			'skip_trigger_registration',
			array(
				$skip_trigger_registration,
				$trigger,
				$integration_code,
				$integration,
			),
			'3.0',
			'automator_skip_trigger_registration'
		);
		$skip_trigger_registration = apply_filters( 'automator_skip_trigger_registration', $skip_trigger_registration, $trigger, $integration_code, $integration );
		if ( true === $skip_trigger_registration ) {
			return null;
		}

		/**
		 * Use this hook the override specific triggers type, i.e., utility or user
		 */
		if ( ! key_exists( 'type', $trigger ) ) {
			$trigger_type    = 'user';
			$trigger_type    = apply_filters_deprecated(
				'uap_trigger_type',
				array(
					$trigger_type,
					$trigger,
					$integration_code,
					$integration,
				),
				'3.0',
				'automator_trigger_type'
			);
			$trigger_type    = apply_filters( 'automator_trigger_type', $trigger_type, $trigger, $integration_code, $integration );
			$trigger['type'] = $trigger_type;
		}

		/**
		 * Use this hook to modify the trigger before it it error checked and registered
		 */
		$trigger = apply_filters_deprecated(
			'uap_register_trigger',
			array( $trigger, $integration_code, $integration ),
			'3.0',
			'automator_register_trigger'
		);
		$trigger = apply_filters( 'automator_register_trigger', $trigger, $integration_code, $integration );

		/**
		 * Use this hook to modify the integration_code before it is error checked and registered
		 */
		$integration_code = apply_filters_deprecated(
			'uap_register_trigger_integration_code',
			array( $integration_code, $trigger, $integration ),
			'3.0',
			'automator_register_trigger_integration_code'
		);
		$integration_code = apply_filters( 'automator_register_trigger_integration_code', $integration_code, $trigger, $integration );

		/**
		 * Use this hook to modify the integration_code before it is error checked and registered
		 */
		$integration = apply_filters_deprecated(
			'uap_register_trigger_integration',
			array( $integration, $trigger, $integration_code ),
			'3.0',
			'automator_register_trigger_integration'
		);
		$integration = apply_filters( 'automator_register_trigger_integration', $integration, $trigger, $integration_code );
		//      $integrations = Automator()->get_integrations();
		// Integration was passed in, lets try to register it
		if ( null !== $integration_code ) {
			if ( ! is_string( $integration_code ) || null === $integration || is_array( $integration ) ) {
				throw new Automator_Exception( 'You are trying to register a trigger without passing integration code.', 1001 );
			}

			// Sanity check that the integration code does not exist already
			//          if ( ! key_exists( $integration_code, $integrations ) ) {
			//              Automator()->register->integration( $integration_code, $integration );
			//          }
		}

		// Sanity check that trigger_integration isset
		if ( ! isset( $trigger['integration'] ) ) {
			throw new Automator_Exception( 'You are trying to register a trigger without setting its trigger_integration', 1001 );
		}

		// Sanity check that the trigger has a integration that is defined
		//      if ( ! key_exists( $trigger['integration'], $integrations ) ) {
		//          throw new Automator_Exception( 'You are trying to register a trigger to an integration that does not exist.', 1001 );
		//      }

		// Sanity check that trigger_code isset
		if ( ! isset( $trigger['code'] ) ) {
			throw new Automator_Exception( 'You are trying to register a trigger without setting its trigger_code', 1001 );
		}

		// Sanity check that trigger_name isset
		if ( ! isset( $trigger['select_option_name'] ) ) {
			throw new Automator_Exception( 'You are trying to register a trigger without setting its trigger_name', 1001 );

		}

		// Sanity check that trigger_action isset
		if ( ! isset( $trigger['action'] ) ) {
			throw new Automator_Exception( 'You are trying to register a trigger without setting its trigger_action', 1001 );
		}

		// Sanity check that trigger_validation_function isset
		if ( ! isset( $trigger['validation_function'] ) ) {
			throw new Automator_Exception( 'You are trying to register a trigger without setting its trigger_validation_function', 1001 );
		}

		// Register trigger loopable tokens.
		// @since 5.10
		if ( isset( $trigger['loopable_tokens'] ) ) {

			foreach ( (array) $trigger['loopable_tokens'] as $key => $loopable_tokens ) {

				$loopable_token = new $loopable_tokens();

				$loopable_token->set_trigger( $trigger );
				$loopable_token->register_hooks( $trigger );

				$trigger['loopable_tokens'][ $key ] = $loopable_token; // @todo: Create a filter and use pro to overwrite.
			}
		}

		// Register the trigger into the system
		Automator()->set_triggers( Automator()->utilities->keep_order_of_options( $trigger ) );

		return true;
	}

	/**
	 * Add a new integration
	 *
	 * @param null $integration_code
	 * @param null $integration
	 *
	 * @return null
	 * @throws Exception
	 */
	public function integration( $integration_code = null, $integration = null ) {

		// Only registers the integration if its not yet registered.
		if ( ! isset( Automator()->all_integrations[ $integration_code ] ) ) {
			Automator()->set_all_integrations( $integration_code, $integration );
		}

		if ( null === $integration_code || ! is_string( $integration_code ) ) {
			throw new Automator_Exception( 'You are trying to register an integration without passing an integration code.', 1002 );
		}

		if ( null === $integration || ! is_array( $integration ) ) {
			throw new Automator_Exception( 'You are trying to register an integration without passing an integration object.', 1002 );
		}

		$integration = apply_filters( 'automator_register_integration', $integration, $integration_code );

		// Register integration if it doesn't already exist
		if ( ! array_key_exists( $integration_code, Automator()->integrations ) ) {
			Automator()->set_integrations( $integration_code, $integration );
		} elseif ( array_key_exists( 'icon_svg', $integration ) ) {
			Automator()->set_integrations( $integration_code, $integration );
		}

		// Fire up the loopable tokens.
		if ( isset( $integration['loopable_tokens'] ) ) {
			foreach ( (array) $integration['loopable_tokens'] as $id => $loopable_token_class ) {
				( new $loopable_token_class( $integration_code ) )->register_hooks();
			}
		}

		// Order integrations alphabetically
		Automator()->utilities->sort_integrations_alphabetically();

		return true;

	}

	/**
	 * Register a new uap action and creates a type if defined and the type does not exist
	 *
	 * @param null $uap_action
	 * @param null|string $integration_code
	 * @param null $integration
	 *
	 * @return null|                 |true
	 * @throws Exception
	 */
	public function action( $uap_action = null, $integration_code = null, $integration = null ) {

		// Sanity check that there was a trigger passed
		if ( null === $uap_action || ! is_array( $uap_action ) ) {
			throw new Automator_Exception( 'You are trying to register an action without passing a action object.', 1003 );
		}

		/**
		 * Use this hook the stop specific actions from being registered by returning true
		 */
		$skip_uap_action_registration = false;
		$skip_uap_action_registration = apply_filters_deprecated(
			'skip_uap_action_registration',
			array( $skip_uap_action_registration, $uap_action, $integration_code, $integration ),
			'3.0',
			'automator_skip_action_registration'
		);
		$skip_uap_action_registration = apply_filters( 'automator_skip_action_registration', $skip_uap_action_registration, $uap_action, $integration_code, $integration );

		if ( true === $skip_uap_action_registration ) {
			return null;
		}

		/**
		 * Use this hook to modify the uap action before it it error checked and registered
		 */
		$uap_action = apply_filters_deprecated(
			'uap_register_action',
			array( $uap_action, $integration_code, $integration ),
			'3.0',
			'automator_register_action'
		);
		$uap_action = apply_filters( 'automator_register_action', $uap_action, $integration_code, $integration );
		/**
		 * Add default requires_user to all actions
		 *
		 * @since 3.1
		 * @version 3.1
		 */
		if ( ! isset( $uap_action['requires_user'] ) ) {
			$uap_action['requires_user'] = true;
		}
		/**
		 * Use this hook to modify the integration_code before it is error checked and registered
		 */
		$integration_code = apply_filters_deprecated(
			'uap_register_action_integration_code',
			array( $integration_code, $uap_action, $integration ),
			'3.0',
			'automator_register_action_integration_code'
		);
		$integration_code = apply_filters( 'automator_register_action_integration_code', $integration_code, $uap_action, $integration );

		/**
		 * Use this hook to modify the integration_code before it is error checked and registered
		 */
		$integration = apply_filters_deprecated(
			'uap_register_action_integration',
			array( $integration, $uap_action, $integration_code ),
			'3.0',
			'automator_register_action_integration'
		);
		$integration = apply_filters( 'automator_register_action_integration', $integration, $uap_action, $integration_code );
		//$integrations = Automator()->get_integrations();

		// Integration was passed in, lets try to register it
		if ( null !== $integration_code ) {
			if ( ! is_string( $integration_code ) ) {
				throw new Automator_Exception( 'You are trying to register an action without passing an proper integration code.', 1003 );
			}
			if ( null === $integration && is_array( $integration ) ) {
				throw new Automator_Exception( 'You are trying to register an action without passing an proper integration object.', 1003 );
			}                    // Sanity check that the integration code does not exist already
			//          if ( ! key_exists( $integration_code, $integrations ) ) {
			//              Automator()->register->integration( $integration_code, $integration );
			//          }
		}

		// Sanity check that trigger_integration isset
		if ( ! isset( $uap_action['integration'] ) ) {
			throw new Automator_Exception( 'You are trying to register an action without setting its action_integration.', 1003 );
		}

		// Sanity check that the trigger has a integration that is defined
		//      if ( ! key_exists( $uap_action['integration'], $integrations ) ) {
		//          throw new Automator_Exception( 'You are trying to register an action to an integration that does not exist.', 1003 );
		//      }

		// Sanity check that trigger_code isset
		if ( ! isset( $uap_action['code'] ) ) {
			throw new Automator_Exception( 'You are trying to register an action without setting its action_code.', 1003 );
		}

		// Sanity check that execution_function isset
		if ( ! isset( $uap_action['execution_function'] ) ) {
			throw new Automator_Exception( 'You are trying to register an action without setting its execution_function.', 1003 );
		}

		// Register action loopable tokens.
		// @since 6.0
		if ( isset( $uap_action['loopable_tokens'] ) ) {

			foreach ( (array) $uap_action['loopable_tokens'] as $key => $loopable_tokens ) {

				$loopable_token = new $loopable_tokens();
				$loopable_token->set_action( $uap_action );
				$loopable_token->register_hooks( $uap_action );

				$uap_action['loopable_tokens'][ $key ] = $loopable_token; // @todo: Create a filter and use pro to overwrite.
			}
		}

		Automator()->set_actions( Automator()->utilities->keep_order_of_options( $uap_action ) );

		return true;
	}

	/**
	 * Registers a new closure and creates a type if defined and the type does not exist
	 *
	 * @param null $closure
	 * @param null|string $integration_code
	 * @param null $integration
	 *
	 * @return null
	 * @throws Automator_Exception
	 */
	public function closure( $closure = null, $integration_code = null, $integration = null ) {

		// Sanity check that there was a trigger passed
		if ( null === $closure || ! is_array( $closure ) ) {
			throw new Automator_Exception( 'You are trying to register a closure without passing a closure object.', 1004 );
		}

		/**
		 * Use this hook the stop specific closures from being registered by returning true
		 */
		$skip_closure_registration = false;
		$skip_closure_registration = apply_filters_deprecated(
			'skip_closure_registration',
			array( $skip_closure_registration, $closure, $integration_code, $integration ),
			'3.0',
			'automator_skip_closure_registration'
		);
		$skip_closure_registration = apply_filters( 'automator_skip_closure_registration', $skip_closure_registration, $closure, $integration_code, $integration );
		if ( true === $skip_closure_registration ) {
			return null;
		}

		/**
		 * Use this hook to modify the uap closures before it it error checked and registered
		 */
		$closure = apply_filters_deprecated(
			'uap_register_closure',
			array( $closure, $integration_code, $integration ),
			'3.0',
			'automator_register_closure'
		);
		$closure = apply_filters( 'automator_register_closure', $closure, $integration_code, $integration );

		/**
		 * Use this hook to modify the integration_code before it is error checked and registered
		 */
		$integration_code = apply_filters_deprecated(
			'uap_register_closure_integration_code',
			array( $integration_code, $closure, $integration ),
			'3.0',
			'automator_register_closure_integration_code'
		);
		$integration_code = apply_filters( 'automator_register_closure_integration_code', $integration_code, $closure, $integration );

		/**
		 * Use this hook to modify the integration_code before it is error checked and registered
		 */
		$integration = apply_filters_deprecated(
			'uap_register_closure_integration',
			array(
				$integration,
				$closure,
				$integration_code,
			),
			'3.0',
			'automator_register_closure_integration'
		);
		$integration = apply_filters( 'automator_register_closure_integration', $integration, $closure, $integration_code );
		//$integrations = Automator()->get_integrations();

		// Integration was passed in, lets try to register it
		if ( null !== $integration_code ) {
			if ( ! is_string( $integration_code ) ) {
				throw new Automator_Exception( 'You are trying to register a closure without passing an proper integration code.', 1004 );
			}
			if ( null === $integration && is_array( $integration ) ) {
				throw new Automator_Exception( 'You are trying to register a closure without passing an proper integration object.', 1004 );
			}
			// Sanity check that the integration code does not exist already
			//          if ( ! key_exists( $integration_code, $integrations ) ) {
			//              Automator()->register->integration( $integration_code, $integration );
			//          }
		}

		// Sanity check that trigger_integration isset
		if ( ! isset( $closure['integration'] ) ) {
			throw new Automator_Exception( 'You are trying to register a closure without setting its closure_integration.', 1004 );
		}

		// Sanity check that the trigger has a integration that is defined
		//      if ( ! key_exists( $closure['integration'], $integrations ) ) {
		//          throw new Automator_Exception( 'You are trying to register a closure to an integration that does not exist.', 1004 );
		//      }

		// Sanity check that trigger_code isset
		if ( ! isset( $closure['code'] ) ) {
			throw new Automator_Exception( 'You are trying to register a closure without setting its closure_code.', 1004 );
		}

		// Sanity check that trigger_validation_function isset
		if ( ! isset( $closure['execution_function'] ) ) {
			throw new Automator_Exception( 'You are trying to register a closure without setting its closure_execution_function.', 1004 );
		}

		Automator()->set_closures( Automator()->utilities->keep_order_of_options( $closure ) );

		return true;
	}
}
