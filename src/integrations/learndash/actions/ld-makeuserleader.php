<?php

namespace Uncanny_Automator;

/**
 * Class LD_MAKEUSERLEADER
 *
 * @package Uncanny_Automator
 */
class LD_MAKEUSERLEADER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'LD';
	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'MAKEUSERLEADER';
		$this->action_meta = 'LDGROUP';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$args = array(
			'post_type'      => 'groups',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args );

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/learndash/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Logged-in trigger - Uncanny Groups */
			'sentence'           => sprintf( esc_attr__( 'Make the user the leader of {{a group:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Logged-in trigger - Uncanny Groups */
			'select_option_name' => esc_attr__( 'Make the user the leader of {{a group}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'make_user_leader_of_group' ),
			'options_group'      =>
				array(
					$this->action_meta =>
						array(
							array(
								'option_code' => 'LDGROUP',
								'label'       => esc_attr__( 'Group', 'uncanny-automator' ),
								'input_type'  => 'select',
								'required'    => true,
								'options'     => $options,
							),
							array(
								'input_type'            => 'select',
								'option_code'           => 'GROUP_LEADER_ROLE_ASSIGNMENT',
								/* translators: Uncanny Groups */
								'label'                 => esc_attr__( 'If the user does not currently have the Group Leader role', 'uncanny-automator' ),
								'description'           => '<div class="user-selector__warning">' . esc_attr__( 'Only users with the Group Leader role can be made the leader of a group.', 'uncanny-automator' ) . '</div>',
								'required'              => true,
								'default_value'         => 'do_nothing',
								'options'               => array(
									'do_nothing' => esc_attr__( 'Do nothing', 'uncanny-automator' ),
									'add'        => esc_attr__( 'Add the role to their existing role(s)', 'uncanny-automator' ),
									'replace'    => esc_attr__( 'Replace their existing role(s) with the Group Leader role', 'uncanny-automator' ),
								),
								'supports_custom_value' => false,
								'supports_tokens'       => false,
							),
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
	 * @param $args
	 */
	public function make_user_leader_of_group( $user_id, $action_data, $recipe_id, $args ) {

		$uo_group                     = Automator()->parse->text( $action_data['meta']['LDGROUP'], $recipe_id, $user_id, $args );
		$group_leader_role_assignment = Automator()->parse->text( $action_data['meta']['GROUP_LEADER_ROLE_ASSIGNMENT'], $recipe_id, $user_id, $args );

		$user = get_user_by( 'ID', $user_id );

		if ( is_wp_error( $user ) ) {
			return;
		}

		if ( user_can( $user, 'group_leader' ) ) {
			ld_update_leader_group_access( $user_id, $uo_group );
			Automator()->complete_action( $user_id, $action_data, $recipe_id );

			return;
		}

		switch ( trim( $group_leader_role_assignment ) ) {
			case 'add':
				$user->add_role( 'group_leader' );
				ld_update_leader_group_access( $user_id, $uo_group );
				break;
			case 'replace':
				$user->set_role( 'group_leader' );
				ld_update_leader_group_access( $user_id, $uo_group );
				break;
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id );

		return;
	}
}
