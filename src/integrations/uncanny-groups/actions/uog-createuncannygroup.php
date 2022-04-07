<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class UOG_CREATEUNCANNYGROUP
 *
 * @package Uncanny_Automator
 */
class UOG_CREATEUNCANNYGROUP {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UOG';
	/**
	 * @var
	 */
	public static $number_of_keys;
	/**
	 * @var string
	 */
	private $action_code;
	/**
	 * @var string
	 */
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'CREATEUNCANNYGROUP';
		$this->action_meta = 'UNCANNYGROUP';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/uncanny-groups/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Logged-in trigger - Uncanny Groups */
			'sentence'           => sprintf( esc_attr__( 'Create {{an Uncanny group:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Logged-in trigger - Uncanny Groups */
			'select_option_name' => esc_attr__( 'Create {{an Uncanny group}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'create_uncanny_group' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * @return array
	 */
	public function load_options() {

		$args = array(
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args );

		$user_warning = sprintf(
			'%s <em>%s</em>',
			esc_attr__( 'Only users with the Group Leader role can be made the leader of a group.', 'uncanny-automator' ),
			esc_attr__( 'This action will not alter the roles of Admin users.', 'uncanny-automator' )
		);
		$options      = array(
			'options_group' =>
				array(
					$this->action_meta =>
						array(
							array(
								'option_code' => 'UOGROUPTITLE',
								'label'       => esc_attr__( 'Group name', 'uncanny-automator' ),
								'input_type'  => 'text',
								'required'    => true,
							),
							array(
								'option_code'              => 'UOGROUPCOURSES',
								'label'                    => esc_attr__( 'Group courses', 'uncanny-automator' ),
								'input_type'               => 'select',
								'required'                 => true,
								'supports_multiple_values' => true,
								'options'                  => $options,
							),
							array(
								'option_code' => 'UOGROUPNUMSEATS',
								'label'       => esc_attr__( 'Number of seats', 'uncanny-automator' ),
								'input_type'  => 'int',
								'required'    => true,

							),
							array(
								'input_type'            => 'select',
								'option_code'           => 'GROUP_LEADER_ROLE_ASSIGNMENT',
								/* translators: Uncanny Groups */
								'label'                 => esc_attr__( 'If the user does not currently have the Group Leader role', 'uncanny-automator' ),
								'description'           => '<div class="user-selector__warning">' . $user_warning . '</div>',
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

		return Automator()->utilities->keep_order_of_options( $options );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function create_uncanny_group( $user_id, $action_data, $recipe_id, $args ) {

		$uo_group_title               = Automator()->parse->text( $action_data['meta']['UOGROUPTITLE'], $recipe_id, $user_id, $args );
		$uo_group_num_seats           = absint( Automator()->parse->text( $action_data['meta']['UOGROUPNUMSEATS'], $recipe_id, $user_id, $args ) );
		$uo_group_courses             = Automator()->parse->text( $action_data['meta']['UOGROUPCOURSES'], $recipe_id, $user_id, $args );
		$group_leader_role_assignment = Automator()->parse->text( $action_data['meta']['GROUP_LEADER_ROLE_ASSIGNMENT'], $recipe_id, $user_id, $args );

		$create_group = false;
		$user         = get_user_by( 'ID', $user_id );

		if ( is_wp_error( $user ) || false === $user ) {
			return;
		}

		// Check if user has existing 'group_leader' role.
		if ( user_can( $user, 'group_leader' ) ) {

			$create_group = true;

		} else {

			// Only execute role changing or adding if current user is not administrator.
			if ( ! user_can( $user, 'manage_options' ) ) {

				switch ( trim( $group_leader_role_assignment ) ) {
					case 'add':
						$user->add_role( 'group_leader' );
						$create_group = true;
						break;
					case 'replace':
						$user->set_role( 'group_leader' );
						$create_group = true;
						break;
				}
			}

			$create_group = true;

		}

		if ( false === $create_group ) {
			// bail early
			return;
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
			return;
		}

		update_post_meta( $group_id, '_ulgm_is_custom_group_created', 'yes' );

		ld_update_leader_group_access( $user_id, $group_id );

		$group_courses = json_decode( $uo_group_courses );

		if ( ! empty( $group_courses ) ) {
			foreach ( $group_courses as $course_id ) {
				ld_update_course_group_access( (int) $course_id, (int) $group_id, false );
				$transient_key = 'learndash_course_groups_' . $course_id;
				delete_transient( $transient_key );
			}
		}

		update_post_meta( $group_id, '_ulgm_total_seats', $uo_group_num_seats );

		$order_id = \uncanny_learndash_groups\Database::get_random_order_number();

		$attr = array(
			'user_id'    => $user_id,
			'order_id'   => $order_id,
			'group_id'   => $group_id,
			'group_name' => $group_title,
			'qty'        => $uo_group_num_seats,
		);

		$codes         = \uncanny_learndash_groups\SharedFunctions::generate_random_codes( $uo_group_num_seats );
		$code_group_id = \uncanny_learndash_groups\Database::add_codes( $attr, $codes );

		update_post_meta( $group_id, \uncanny_learndash_groups\SharedFunctions::$code_group_id_meta_key, $code_group_id );
		update_user_meta( $user_id, '_ulgm_custom_order_id', $order_id );

		if ( 'yes' !== get_option( 'do_not_add_group_leader_as_member', 'no' ) ) {
			\uncanny_learndash_groups\RestApiEndPoints::add_existing_user( array( 'user_email' => $user->user_email ), true, $group_id, $order_id, 'redeemed', false );
		}

		do_action( 'uo_new_group_created', $group_id, $user_id );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );

	}
}
