<?php

namespace Uncanny_Automator\Integrations\Uncanny_Groups;

use Uncanny_Automator\Integrations\Uncanny_Toolkit\Ut_Helpers as Uncanny_Toolkit_Helpers;

/**
 * Class UOG_CREATEUNCANNYGROUP
 *
 * @package Uncanny_Automator
 * @property \Uncanny_Automator\Integrations\Uncanny_Groups\Uog_Helpers $item_helpers
 */
class UOG_CREATEUNCANNYGROUP extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action configuration.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'UOG' );
		$this->set_action_code( 'CREATEUNCANNYGROUP' );
		$this->set_action_meta( 'UNCANNYGROUP' );

		// translators: %1$s is the group name.
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Create {{an Uncanny group:%1$s}}', 'Uncanny Groups', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Create {{an Uncanny group}}', 'Uncanny Groups', 'uncanny-automator' ) );

		$tokens = array(
			'GROUP_ID'            => array(
				'name' => esc_html_x( 'Group ID', 'Uncanny Groups', 'uncanny-automator' ),
			),
			'GROUP_COURSES'       => array(
				'name' => esc_html_x( 'Group courses', 'Uncanny Groups', 'uncanny-automator' ),
			),
			'GROUP_LEADER_EMAILS' => array(
				'name' => esc_html_x( 'Group Leader emails', 'Uncanny Groups', 'uncanny-automator' ),
			),
		);

		if ( class_exists( '\Uncanny_Automator\Integrations\Uncanny_Toolkit\Ut_Helpers' ) && Uncanny_Toolkit_Helpers::is_group_sign_up_activated() ) {
			$tokens['GROUP_SIGNUP_URL'] = array(
				'name' => esc_html_x( 'Group signup URL', 'Uncanny Groups', 'uncanny-automator' ),
			);
		}

		$this->set_action_tokens( $tokens, $this->get_action_code() );
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {

		$args = array(
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => 999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$courses        = get_posts( $args );
		$course_options = array();

		if ( ! empty( $courses ) ) {
			foreach ( $courses as $course ) {
				$course_options[] = array(
					'value' => (string) $course->ID,
					'text'  => $course->post_title,
				);
			}
		}

		$user_warning = sprintf(
			'%s <em>%s</em>',
			esc_html_x( 'Only users with the Group Leader role can be made the leader of a group.', 'Uncanny Groups', 'uncanny-automator' ),
			esc_html_x( 'This action will not alter the roles of Admin users.', 'Uncanny Groups', 'uncanny-automator' )
		);

		return array(
			array(
				'option_code' => 'UOGROUPTITLE',
				'label'       => esc_html_x( 'Group name', 'Uncanny Groups', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code'              => 'UOGROUPCOURSES',
				'label'                    => esc_html_x( 'Group courses', 'Uncanny Groups', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'supports_multiple_values' => true,
				'options'                  => $course_options,
				'token_name'               => esc_html_x( 'Group course IDs', 'Uncanny Groups', 'uncanny-automator' ),
				'supports_custom_value'    => true,
			),
			array(
				'option_code' => 'UOGROUPNUMSEATS',
				'label'       => esc_html_x( 'Number of seats', 'Uncanny Groups', 'uncanny-automator' ),
				'input_type'  => 'int',
				'required'    => true,
			),
			array(
				'option_code'           => 'GROUP_LEADER_ROLE_ASSIGNMENT',
				'label'                 => esc_html_x( 'If the user does not currently have the Group Leader role', 'Uncanny Groups', 'uncanny-automator' ),
				'description'           => '<div class="user-selector__warning">' . $user_warning . '</div>',
				'input_type'            => 'select',
				'required'              => true,
				'default_value'         => 'do_nothing',
				'options'               => array(
					array(
						'value' => 'do_nothing',
						'text'  => esc_html_x( 'Do nothing', 'Uncanny Groups', 'uncanny-automator' ),
					),
					array(
						'value' => 'add',
						'text'  => esc_html_x( 'Add the role to their existing role(s)', 'Uncanny Groups', 'uncanny-automator' ),
					),
					array(
						'value' => 'replace',
						'text'  => esc_html_x( 'Replace their existing role(s) with the Group Leader role', 'Uncanny Groups', 'uncanny-automator' ),
					),
				),
				'supports_custom_value' => false,
				'supports_tokens'       => false,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$uo_group_title               = $parsed['UOGROUPTITLE'] ?? '';
		$uo_group_num_seats           = absint( $parsed['UOGROUPNUMSEATS'] ?? 0 );
		$uo_group_courses             = $parsed['UOGROUPCOURSES'] ?? '';
		$group_leader_role_assignment = $parsed['GROUP_LEADER_ROLE_ASSIGNMENT'] ?? 'do_nothing';

		$user = get_user_by( 'ID', $user_id );

		if ( is_wp_error( $user ) || false === $user ) {
			$this->add_log_error( esc_html_x( 'User not found.', 'Uncanny Groups', 'uncanny-automator' ) );
			return false;
		}

		$create_group = false;

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
			return false;
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
			$this->add_log_error( $group_id->get_error_message() );
			return false;
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

		$tokens = array(
			'GROUP_ID'            => $group_id,
			'GROUP_COURSES'       => $this->get_group_names( $group_id ),
			'GROUP_LEADER_EMAILS' => $this->item_helpers->get_group_leaders_email_addresses( $group_id ),
		);

		if ( class_exists( '\Uncanny_Automator\Integrations\Uncanny_Toolkit\Ut_Helpers' ) && Uncanny_Toolkit_Helpers::is_group_sign_up_activated() ) {
			$tokens['GROUP_SIGNUP_URL'] = Uncanny_Toolkit_Helpers::get_group_sign_up_url( $group_id );
		}

		$this->hydrate_tokens( $tokens );

		return true;
	}

	/**
	 * Get comma-separated course names for a group.
	 *
	 * @param int $group_id The group ID.
	 *
	 * @return string
	 */
	private function get_group_names( $group_id ) {

		$courses = learndash_group_enrolled_courses( $group_id );

		$course_names = array();

		foreach ( $courses as $course_id ) {
			$course_names[] = get_the_title( $course_id );
		}

		return implode( ', ', $course_names );
	}
}
