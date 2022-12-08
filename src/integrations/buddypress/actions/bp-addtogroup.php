<?php

namespace Uncanny_Automator;

/**
 * Class BP_ADDTOGROUP
 *
 * @package Uncanny_Automator
 */
class BP_ADDTOGROUP {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'BP';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'BPADDTOGROUP';
		$this->action_meta = 'BPGROUPS';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/buddypress/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - BuddyPress */
			'sentence'           => sprintf( esc_attr__( 'Add the user to {{a group:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - BuddyPress */
			'select_option_name' => esc_attr__( 'Add the user to {{a group}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'add_to_bp_group' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * Loads the options.
	 *
	 * @return array The options.
	 */
	public function load_options() {

		$bp_group_args = array(
			'uo_include_any' => false,
			'status'         => array( 'public', 'hidden', 'private' ),
		);

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->buddypress->options->all_buddypress_groups( null, 'BPGROUPS', $bp_group_args ),
				),
			)
		);
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function add_to_bp_group( $user_id, $action_data, $recipe_id, $args ) {

		$group_id = absint( $action_data['meta'][ $this->action_meta ] );

		$has_joined_groups = groups_join_group( $group_id, $user_id );

		if ( true !== $has_joined_groups ) {

			$action_data['complete_with_errors'] = true;

			return Automator()->complete->action(
				$user_id,
				$action_data,
				$recipe_id,
				sprintf(
					/* translators: Error message */
					esc_html__( 'Failed to add member into the group. User ID: %1$d | Group ID %2$d', 'uncanny-automator' ),
					$user_id,
					$group_id
				)
			);
		}

		return Automator()->complete->action( $user_id, $action_data, $recipe_id );

	}

}
