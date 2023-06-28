<?php

namespace Uncanny_Automator;

/**
 * Class WM_ADDUSER_A
 *
 * @package Uncanny_Automator
 */
class WM_ADDUSER_A {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WISHLISTMEMBER';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'WMADDUSER';
		$this->action_meta = 'WMMEMBERSHIPLEVELS';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 * Add the user to {a membership level}
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/wishlist-member/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - Wishlist Member */
			'sentence'           => sprintf( esc_attr__( 'Add the user to {{a membership level:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - Wishlist Member */
			'select_option_name' => esc_attr__( 'Add the user to {{a membership level}}', 'uncanny-automator' ),
			'priority'           => 99,
			'accepted_args'      => 1,
			'execution_function' => array(
				$this,
				'add_user_to_membership_levels',
			),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->wishlist_member->options->wm_get_all_membership_levels(
						null,
						$this->action_meta,
						array(
							'include_all' => true,
						)
					),
				),
			)
		);
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function add_user_to_membership_levels( $user_id, $action_data, $recipe_id, $args ) {
		global $WishListMemberInstance;

		$level_ids = array();
		$wm_level  = (int) $action_data['meta'][ $this->action_meta ];

		$get_levels_function = method_exists( $WishListMemberInstance, 'get_membership_levels' ) ? 'get_membership_levels' : 'GetMembershipLevels';
		$user_level_ids      = $WishListMemberInstance->$get_levels_function( $user_id );
		$error               = false;

		// Add to one level.
		if ( $wm_level > 0 ) {
			if ( ! in_array( $wm_level, $user_level_ids ) ) {
				$level_ids[] = $wm_level;
			} else {
				$error = _x( 'User is already a member of the selected level.', 'WishList Member', 'uncanny-automator' );
			}
		} else {
			// Add to all levels.
			$get_option_function = method_exists( $WishListMemberInstance, 'get_option' ) ? 'get_option' : 'GetOption';
			$all_levels          = $WishListMemberInstance->$get_option_function( 'wpm_levels' );
			if ( is_array( $all_levels ) ) {
				foreach ( $all_levels as $id => $levels ) {
					if ( ! in_array( $id, $user_level_ids ) ) {
						$level_ids[] = $id;
					}
				}
				$error = empty( $level_ids ) ? _x( 'User is already a member of all levels.', 'WishList Member', 'uncanny-automator' ) : false;
			} else {
				$error = _x( 'No levels found.', 'WishList Member', 'uncanny-automator' );
			}
		}

		if ( $error ) {
			$args['do-nothing']                  = true;
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error, $action_data['recipe_log_id'], $args );

			return;
		}

		$args = array(
			'Users'                        => array(
				$user_id,
			),
			'SendMail'                     => false,
			'SendMailPerLevel'             => false,
			'ObeyLevelsAdditionalSettings' => false,
		);
		foreach ( $level_ids as $level_id ) {
			wlmapi_add_member_to_level( $level_id, $args );
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

}
