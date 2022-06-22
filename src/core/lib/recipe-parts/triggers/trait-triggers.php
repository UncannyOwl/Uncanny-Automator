<?php
/**
 * Trait - Triggers
 *
 * Has all the functionality of Triggers
 *
 * @trait   Triggers
 * @since   3.0
 * @version 3.0
 * @package Uncanny_Automator
 * @author  Saad S.
 */


namespace Uncanny_Automator\Recipe;

use Exception;

/**
 * Trait Triggers
 *
 * @package Uncanny_Automator
 */
trait Triggers {

	/**
	 * Trigger Setup. This trait handles trigger definitions.
	 */
	use Trigger_Setup;

	/**
	 * Trigger Filters. This trait performs basic trigger filters before an it executes. For example, is user
	 * logged in, or should trigger runs for anonymous users.
	 */
	use Trigger_Filters;

	/**
	 * Trigger Recipe Filters. This trait returns the recipes that matches given parameters.
	 */
	use Trigger_Recipe_Filters;

	/**
	 * Trigger Conditions. This trait handles trigger conditions. This is where trigger conditionally executes. For
	 * example, a form ID has to be matched, a specific field needs to have a certain value/
	 */
	use Trigger_Conditions;

	/**
	 * Trigger Process. This trait handles trigger execution.
	 */
	use Trigger_Process;

	/**
	 * Set values before trigger is processed. For example, setting post id, setting conditional trigger to true etc.
	 *
	 * @param mixed $args
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	abstract protected function prepare_to_run( $args );

	/**
	 * @param $args
	 *
	 * @return mixed
	 */
	protected function do_action_args( $args ) {
		return $args;
	}

	/**
	 * This function will run for each trigger that is using Trait Triggers; Most of the heavy-lifting > 3.0 will be
	 * handled by core instead of each individual trigger. We are still going to allow developers to manipulate values
	 * or override in a trigger if required.
	 *
	 * @param mixed ...$args
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function validate( ...$args ) {
		/**
		 * Grab user_id using WordPress function.
		 */
		$this->set_user_id( $this->get_user_id() );

		/**
		 * By default, ...$args contains all the arguments in array. If a developer wants to manipulate the arguments
		 * array to add assign values as key=>value pair, they can do it here.
		 */
		$args = $this->do_action_args( $args );

		/**
		 * Set conditional triggers to false. Can be overwritten in prepare to run.
		 */
		$this->set_conditional_trigger( false );

		/**
		 * Check if user is logged in.
		 */
		if ( ! is_user_logged_in() ) {
			/**
			 * Allow developers to override and return true to continue running trigger.
			 * use $this->set_is_user_logged_in_required( true|false );
			 */
			if ( true === $this->is_user_logged_in_required( $args ) ) {
				return false;
			}
		}

		if ( is_user_logged_in() ) {
			/**
			 * If this is an anonymous trigger and user is logged in, should it continue running trigger?
			 */
			if ( $this->get_is_anonymous() || 'anonymous' === $this->get_trigger_type() ) {
				/**
				 * Allow developers to override and return true to continue running trigger.
				 */
				if ( ! $this->do_continue_anon_trigger( $args ) ) {
					return false;
				}
			}
		}

		/**
		 * Should the trigger continue executing? This condition has to be satisfied from within each individual trigger.
		 * Each trigger has its own requirements, i.e., is_page(), $order instanceof WC_Order etc.
		 */
		if ( ! $this->validate_trigger( $args ) ) {
			return false;
		}

		/**
		 * This part of the code prepares the trigger and we can set different settings here. For example, most of Automator
		 * trigger passes `post_id` => X to be validated by the core, this is the place the can be used to set values.
		 * This also allows developer to set "Conditional" trigger option to true where certain conditions has to be met
		 * before a trigger could run. For example, if order contains X product, or user completed X course etc.
		 */
		$this->prepare_to_run( $args );

		//TODO: Write $this->is_number_conditional_trigger(); to verify >,<,<=,>=,!=,= etc.
		/**
		 * In-depth validation of the trigger. Filter recipes based on multiple trigger codes & conditions. By default,
		 * trigger condition is true. Set it to false if required for the trigger.
		 */
		if ( $this->is_conditional_trigger() ) {
			/*
			 * Resetting values of match conditions.
			 */
			$this->find_in   = array();
			$this->find_this = array();

			/**
			 * Filters recipes even further based on the conditions. Multiple conditions can be passed to the function.
			 * See sample integration.
			 */
			$this->trigger_conditions( $args );

			/**
			 * Return us filtered recipe ids after validating trigger conditions.
			 */
			$matched_recipe_ids = apply_filters( 'automator_conditionally_matched_recipes', $this->validate_conditions( $args ), $this );
			/**
			 * Trigger failed to satisfy conditions. bailing...
			 */
			if ( empty( $matched_recipe_ids ) ) {
				return false;
			}

			// Set ignore_post_id to true so that Automator can match passed trigger and recipe IDs.
			$this->set_ignore_post_id( true );

			foreach ( $matched_recipe_ids as $recipe_id => $trigger_id ) {
				$this->set_recipe_to_match( $recipe_id );
				$this->set_trigger_to_match( $trigger_id );

				/*
				 * Process each matched recipe.
				 */
				$this->process_trigger( $args );
			}

			return true;
		}

		// Non-conditional triggers. Complete trigger once.
		$this->process_trigger( $args );

		return true;
	}

	/**
	 * Everything has been sorted. Let's go ahead and execute trigger.
	 *
	 * @param $args
	 */
	protected function process_trigger( $args ) {
		/**
		 * Allow developers to manipulate $pass_args with custom arguments. For example, ignore_post_id.
		 */
		$pass_args = $this->prepare_entry_args( $args );

		/**
		 * Should the trigger be autocompleted and continue running trigger?
		 */
		$complete_trigger = apply_filters( 'automator_auto_complete_trigger', $this->do_trigger_autocomplete(), $pass_args, $args );

		/**
		 * Attempt to add trigger entry and autocomplete it if autocomplete is set to true.
		 */
		do_action( 'automator_before_trigger_run', $args, $pass_args );
		if ( true === $this->do_trigger_autocomplete() ) {
			Automator()->process->user->maybe_add_trigger_entry( $pass_args, $complete_trigger );
		} else {
			$entry_args = Automator()->process->user->maybe_add_trigger_entry( $pass_args, $complete_trigger );
			/**
			 * If trigger is not autocompleted, an array is returned which should be handled manually.
			 */
			if ( empty( $entry_args ) ) {
				return;
			}

			foreach ( $entry_args as $result ) {
				if ( true === $result['result'] ) {
					$result_args = $result['args'];
					/**
					 * @var array $args | All the arguments passed to this function
					 * @var array $pass_args | All the arguments passed to run mark a trigger complete
					 * @var array $result_args | All the arguments returned after marking trigger complete
					 */
					$do_action = array(
						'trigger_entry' => $result_args,
						'entry_args'    => $pass_args,
						'trigger_args'  => $args,
					);

					do_action( 'automator_before_trigger_completed', $do_action, $this );

					Automator()->process->user->maybe_trigger_complete( $result_args );

					do_action_deprecated(
						'automator_after_trigger_completed',
						array(
							$do_action,
							$this,
						),
						'4.1',
						'automator_after_maybe_trigger_complete'
					);
					do_action( 'automator_after_maybe_trigger_complete', $do_action, $this );
				}
			}
		}
		do_action( 'automator_after_trigger_run', $args, $pass_args );
	}
}
