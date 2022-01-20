<?php

namespace Uncanny_Automator;

/**
 * Class LD_CREATEGROUP
 *
 * @package Uncanny_Automator
 */
class LD_CREATEGROUP {

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
		$this->action_code = 'CREATEGROUP';
		$this->action_meta = 'LDGROUP';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$args = array(
			'post_type'      => 'sfwd-courses',
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
			'sentence'           => sprintf( esc_attr__( 'Create {{a group:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Logged-in trigger - Uncanny Groups */
			'select_option_name' => esc_attr__( 'Create {{a group}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'create_group' ),
			'options_group'      =>
				array(
					$this->action_meta =>
						array(
							array(
								'option_code' => 'LDGROUPTITLE',
								'label'       => esc_attr__( 'Group name', 'uncanny-automator' ),
								'input_type'  => 'text',
								'required'    => true,
							),
							array(
								'option_code'              => 'LDGROUPCOURSES',
								'label'                    => esc_attr__( 'Group courses', 'uncanny-automator' ),
								'input_type'               => 'select',
								'supports_multiple_values' => true,
								'required'                 => true,
								'options'                  => $options,
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
									'do_nothing' => esc_attr__( 'Do not add the Group Leader role', 'uncanny-automator' ),
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
	public function create_group( $user_id, $action_data, $recipe_id, $args ) {

		$uo_group_title               = Automator()->parse->text( $action_data['meta']['LDGROUPTITLE'], $recipe_id, $user_id, $args );
		$uo_group_courses             = Automator()->parse->text( $action_data['meta']['LDGROUPCOURSES'], $recipe_id, $user_id, $args );
		$group_leader_role_assignment = Automator()->parse->text( $action_data['meta']['GROUP_LEADER_ROLE_ASSIGNMENT'], $recipe_id, $user_id, $args );

		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			$error_message = __( 'User not found.', 'uncanny-automator' );

			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		switch ( trim( $group_leader_role_assignment ) ) {
			case 'add':
				$user->add_role( 'group_leader' );
				break;
			case 'replace':
				$user->set_role( 'group_leader' );
				break;
		}

		$group_title = $uo_group_title;

		$ld_group_args = array(
			'post_type'    => 'groups',
			'post_status'  => apply_filters( 'uo_create_new_group_status', 'publish' ),
			'post_title'   => $group_title,
			'post_content' => '',
			'post_author'  => $user_id,
		);

		$group_id = wp_insert_post( $ld_group_args );

		if ( is_wp_error( $group_id ) ) {
			$error_message = $group_id->get_error_message();

			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		ld_update_leader_group_access( $user_id, $group_id );

		$group_courses = json_decode( $uo_group_courses );

		if ( ! empty( $group_courses ) ) {
			foreach ( $group_courses as $course_id ) {
				ld_update_course_group_access( (int) $course_id, (int) $group_id, false );
				$transient_key = 'learndash_course_groups_' . $course_id;
				delete_transient( $transient_key );
			}
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}
}
