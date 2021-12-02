<?php

namespace Uncanny_Automator;

use SkyVerge\WooCommerce\PluginFramework\v5_7_1\SV_WC_Plugin_Exception;
use WC_REST_Exception;

/**
 * Class WCM_ADDUSER_A
 *
 * @package Uncanny_Automator
 */
class WCM_ADDUSER_A {

	/**
	 * Integration Code
	 *
	 * @var string
	 */
	public static $integration = 'WCMEMBERSHIPS';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'WCMADDUSER';
		$this->action_meta = 'WCMADDTOMEMBERSHIPPLAN';
		$this->define_action();
	}

	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/woocommerce-memberships/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - WooCommerce Memberships */
			'sentence'           => sprintf(
				esc_attr__(
					'Add the user to {{a membership plan:%1$s}}',
					'uncanny-automator'
				),
				$this->action_meta
			),
			/* translators: Action - WooCommerce Memberships */
			'select_option_name' => esc_attr__( 'Add the user to {{a membership plan}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'add_user_to_membership_plan' ),
			'options'            => array(
				Automator()->helpers->recipe->wc_memberships->options->wcm_get_all_membership_plans(
					null,
					$this->action_meta
				),
			),
		);

		Automator()->register->action( $action );
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function add_user_to_membership_plan( $user_id, $action_data, $recipe_id, $args ) {

		$plan                  = $action_data['meta'][ $this->action_meta ];
		$check_membership_plan = wc_memberships_is_user_member( $user_id, $plan );

		if ( true === $check_membership_plan && true === wc_memberships_is_user_active_member( $user_id, $plan ) ) {
			$recipe_log_id             = $action_data['recipe_log_id'];
			$args['do-nothing']        = true;
			$action_data['do-nothing'] = true;
			$action_data['completed']  = true;
			$error_message             = esc_attr__( 'This user has already an active membership in the specified membership plan', 'uncanny-automator' );
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
		} else {

			try {
				$arguments       = array(
					'plan_id' => $plan,
					'user_id' => $user_id,
				);
				$user_membership = wc_memberships_create_user_membership( $arguments );
				Automator()->complete_action( $user_id, $action_data, $recipe_id );
			} catch ( WC_REST_Exception $e ) {
				$error_message                       = $e->getMessage();
				$recipe_log_id                       = $action_data['recipe_log_id'];
				$args['do-nothing']                  = true;
				$action_data['do-nothing']           = true;
				$action_data['complete_with_errors'] = true;
				Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
			} catch ( SV_WC_Plugin_Exception $e ) {
			}
		}

		return;
	}
}
