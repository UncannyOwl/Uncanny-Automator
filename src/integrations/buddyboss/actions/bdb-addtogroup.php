<?php

namespace Uncanny_Automator;

/**
 * Class BDB_ADDTOGROUP
 *
 * @package Uncanny_Automator
 */
class BDB_ADDTOGROUP {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'BDB';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'BDBADDTOGROUP';
		$this->action_meta = 'BDBGROUPS';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/buddyboss/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - BuddyBoss */
			'sentence'           => sprintf( esc_attr__( 'Add the user to {{a group:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - BuddyBoss */
			'select_option_name' => esc_attr__( 'Add the user to {{a group}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'add_to_bb_group' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {

		$bp_group_args = array(
			'uo_include_any' => false,
			'status'         => array( 'public', 'hidden', 'private' ),
		);

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->buddyboss->options->all_buddyboss_groups( null, 'BDBGROUPS', $bp_group_args ),
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
	public function add_to_bb_group( $user_id, $action_data, $recipe_id, $args ) {

		$group_id = $action_data['meta'][ $this->action_meta ];
		if ( 'automator_custom_value' === $group_id ) {
			$group_id = Automator()->parse->text( $action_data['meta'][ $this->action_meta . '_custom' ], $recipe_id, $user_id, $args );
		}

		if ( function_exists( 'groups_join_group' ) ) {
			groups_join_group( $group_id, $user_id );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );
		} else {
			Automator()->complete_action( $user_id, $action_data, $recipe_id, esc_html__( ' groups_join_group Function does not exist.', 'uncanny-automator' ) );
		}

	}

}
