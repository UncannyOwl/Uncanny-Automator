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
	 * Define and register the action by pushing it into the Automator object Add the user to {a membership level}
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
			'execution_function' => array( $this, 'add_user_to_membership_levels' ),
			'options'            => array(
				Automator()->helpers->recipe->wishlist_member->options->wm_get_all_membership_levels(
					null,
					$this->action_meta,
					array(
						'include_all' => true,
					)
				),
			),
		);

		Automator()->register->action( $action );
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
		$wm_level  = $action_data['meta'][ $this->action_meta ];

		if ( $wm_level == '-1' ) {
			$all_levels = $WishListMemberInstance->GetOption( 'wpm_levels' );
			if ( is_array( $all_levels ) ) {
				foreach ( $all_levels as $Id => $levels ) {
					$level_ids = $Id;
				}
			}
		} else {
			$level_ids = $WishListMemberInstance->GetMembershipLevels( $user_id );
			if ( ! in_array( $wm_level, $level_ids ) ) {
				$level_ids[] = $wm_level;
			}
		}

		$WishListMemberInstance->SetMembershipLevels( $user_id, $level_ids );
		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}
}
