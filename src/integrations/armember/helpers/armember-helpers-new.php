<?php

namespace Uncanny_Automator\Integrations\Armember;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Helpers for the ARMember integration.
 *
 * The legacy `\Uncanny_Automator\Armember_Helpers` is a different class and
 * stays on disk untouched: an un-updated Pro instantiates it by that name from
 * its actions, so removing or namespacing it would break them on load. Nothing
 * here extends or calls it.
 *
 * ARMember ships as two editions with parallel class names — `ARM_members` in
 * Pro, `ARM_members_Lite` in Lite — and only one is ever declared. The service
 * accessors below pick whichever exists so the triggers, actions and tokens
 * never repeat that choice.
 *
 * @package Uncanny_Automator
 */
class Armember_Helpers extends Abstract_Helpers {

	/**
	 * Lazily built token definitions.
	 *
	 * @var Armember_Tokens|null
	 */
	private $tokens = null;

	/**
	 * ARMember's subscription plans service, once resolved.
	 *
	 * @var \ARM_subscription_plans|\ARM_subscription_plans_Lite|null
	 */
	private $subscription_plans = null;

	/**
	 * ARMember's members service, once resolved.
	 *
	 * @var \ARM_members|\ARM_members_Lite|null
	 */
	private $members = null;

	/**
	 * Token definitions and hydration for this integration.
	 *
	 * @return Armember_Tokens
	 */
	public function tokens() {

		if ( null === $this->tokens ) {
			$this->tokens = new Armember_Tokens( $this );
		}

		return $this->tokens;
	}

	// -------------------------------------------------------------------------
	// ARMember services
	// -------------------------------------------------------------------------

	/**
	 * ARMember's subscription plans service.
	 *
	 * @return \ARM_subscription_plans|\ARM_subscription_plans_Lite|null Null when ARMember is not loaded.
	 */
	public function subscription_plans() {

		if ( null === $this->subscription_plans ) {
			$this->subscription_plans = $this->make_service( 'ARM_subscription_plans' );
		}

		return $this->subscription_plans;
	}

	/**
	 * ARMember's members service.
	 *
	 * @return \ARM_members|\ARM_members_Lite|null Null when ARMember is not loaded.
	 */
	public function members() {

		if ( null === $this->members ) {
			$this->members = $this->make_service( 'ARM_members' );
		}

		return $this->members;
	}

	/**
	 * A plan object, initialised from the detail ARMember stored on the user
	 * when one is given, otherwise loaded by id.
	 *
	 * @param int               $plan_id     Plan id.
	 * @param array|object|null $plan_detail The user's stored `arm_current_plan_detail`, if any.
	 *
	 * @return \ARM_Plan|\ARM_Plan_Lite|null Null when ARMember is not loaded.
	 */
	public function plan( $plan_id, $plan_detail = null ) {

		$class = $this->resolve_class( 'ARM_Plan' );

		if ( null === $class ) {
			return null;
		}

		if ( ! empty( $plan_detail ) ) {
			$plan = new $class( 0 );
			$plan->init( (object) $plan_detail );

			return $plan;
		}

		return new $class( absint( $plan_id ) );
	}

	/**
	 * Instantiate whichever edition of an ARMember service is loaded.
	 *
	 * @param string $pro_class The Pro edition's class name; Lite appends `_Lite`.
	 *
	 * @return object|null
	 */
	private function make_service( $pro_class ) {

		$class = $this->resolve_class( $pro_class );

		return null === $class ? null : new $class();
	}

	/**
	 * Resolve an ARMember class name to the edition that is loaded.
	 *
	 * @param string $pro_class The Pro edition's class name.
	 *
	 * @return string|null Fully qualified class name, or null when neither edition is loaded.
	 */
	private function resolve_class( $pro_class ) {

		foreach ( array( '\\' . $pro_class, '\\' . $pro_class . '_Lite' ) as $candidate ) {
			if ( class_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	// -------------------------------------------------------------------------
	// Hook payloads
	// -------------------------------------------------------------------------

	/**
	 * The plan ids carried by a hook argument.
	 *
	 * ARMember passes one id on most hooks. `arm_after_user_plan_change_by_admin`
	 * relays the admin form's `arm_user_plan` value, which ARMember Pro's
	 * multi-plan form posts as a list.
	 *
	 * @param mixed $value The hook argument.
	 *
	 * @return int[] Positive ids, re-indexed.
	 */
	public function normalize_plan_ids( $value ) {

		$ids = array();

		foreach ( (array) $value as $id ) {
			$id = absint( $id );

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	// -------------------------------------------------------------------------
	// Remote data
	// -------------------------------------------------------------------------

	/**
	 * Plans available to a trigger, "Any membership" first.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_plans( $request ): array {
		return $this->remote_data_success( $this->get_plan_options( true ) );
	}

	/**
	 * Plans available to an action, which must never be offered "Any".
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_plans_strict( $request ): array {
		return $this->remote_data_success( $this->get_plan_options() );
	}

	/**
	 * Selectable plans.
	 *
	 * @param bool $include_any Whether to prepend the "Any membership" sentinel.
	 *
	 * @return array
	 */
	public function get_plan_options( $include_any = false ) {

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any membership', 'ARMember', 'uncanny-automator' ),
			);
		}

		$plans = $this->subscription_plans();

		if ( null === $plans ) {
			return $options;
		}

		foreach ( (array) $plans->arm_get_all_subscription_plans( 'arm_subscription_plan_id,arm_subscription_plan_name' ) as $key => $plan ) {

			$plan_id = isset( $plan['arm_subscription_plan_id'] ) ? $plan['arm_subscription_plan_id'] : $key;
			$name    = isset( $plan['arm_subscription_plan_name'] ) ? (string) $plan['arm_subscription_plan_name'] : '';

			if ( '' === $name ) {
				// translators: %1$s is the plan id
				$name = sprintf( esc_html_x( 'ID: %1$s (no title)', 'ARMember', 'uncanny-automator' ), $plan_id );
			}

			$options[] = array(
				'value' => (string) $plan_id,
				'text'  => $name,
			);
		}

		return $options;
	}

	// -------------------------------------------------------------------------
	// Field configuration
	// -------------------------------------------------------------------------

	/**
	 * The plan select, which every trigger and action shares.
	 *
	 * Triggers store it as `ARM_ALL_PLANS` and take the `plans` segment; actions
	 * store it as `ARM_PLANS`, take `plans_strict`, and keep accepting a typed or
	 * tokenised id as the legacy field did.
	 *
	 * @param string $option_code           Code the selected plan is stored under.
	 * @param string $segment               Remote-data segment serving the list.
	 * @param bool   $supports_custom_value Whether a custom value may be typed.
	 *
	 * @return array
	 */
	public function get_plan_option_config( $option_code, $segment = 'plans', $supports_custom_value = false ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'Membership plan', 'ARMember', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'supports_custom_value' => (bool) $supports_custom_value,
			'options'               => array(),
			'remote_data'           => $this->remote_data_load_config( $segment ),
			// The membership tokens are declared in define_tokens(); the field adds none of its own.
			'relevant_tokens'       => array(),
		);
	}
}
