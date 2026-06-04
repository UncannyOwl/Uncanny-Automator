<?php

namespace Uncanny_Automator\Integrations\Learndash;

use Uncanny_Automator\Integrations\Uncanny_Toolkit\Ut_Helpers as Uncanny_Toolkit_Helpers;

/**
 * Class LD_CREATEGROUP
 *
 * @package Uncanny_Automator\Integrations\Learndash
 *
 * @property \Uncanny_Automator\Integrations\Learndash\Ld_Helpers $item_helpers
 */
class LD_CREATEGROUP extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Set up the action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'LD' );
		$this->set_action_code( 'CREATEGROUP' );
		$this->set_action_meta( 'LDGROUP' );

		$this->set_sentence(
			sprintf(
				esc_html_x( 'Create {{a group:%1$s}}', 'LearnDash', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Create {{a group}}', 'LearnDash', 'uncanny-automator' )
		);

	}

	/**
	 * Define action tokens.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function define_tokens() {

		$tokens = array(
			'GROUP_ID'            => array(
				'name' => esc_html_x( 'Group ID', 'LearnDash', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GROUP_COURSES'       => array(
				'name' => esc_html_x( 'Group courses', 'LearnDash', 'uncanny-automator' ),
				'type' => 'text',
			),
			'GROUP_LEADER_EMAILS' => array(
				'name' => esc_html_x( 'Group Leader emails', 'LearnDash', 'uncanny-automator' ),
				'type' => 'text',
			),
		);

		if ( Uncanny_Toolkit_Helpers::is_group_sign_up_activated() ) {
			$tokens['GROUP_SIGNUP_URL'] = array(
				'name' => esc_html_x( 'Group signup URL', 'LearnDash', 'uncanny-automator' ),
				'type' => 'text',
			);
		}

		return $tokens;
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$args = array(
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => 999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = automator_wp_query( $args, 'legacy' );

		return array(
			array(
				'option_code' => 'LDGROUPTITLE',
				'label'       => esc_html_x( 'Group name', 'LearnDash', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code'              => 'LDGROUPCOURSES',
				'label'                    => esc_html_x( 'Group courses', 'LearnDash', 'uncanny-automator' ),
				'input_type'               => 'select',
				'supports_multiple_values' => true,
				'required'                 => true,
				'token_name'               => esc_html_x( 'Group course IDs', 'LearnDash', 'uncanny-automator' ),
				'options'                  => $options,
			),
			array(
				'input_type'            => 'select',
				'option_code'           => 'GROUP_LEADER_ROLE_ASSIGNMENT',
				'label'                 => esc_html_x( 'If the user does not currently have the Group Leader role', 'LearnDash', 'uncanny-automator' ),
				'description'           => '<div class="user-selector__warning">' . esc_html_x( 'Only users with the Group Leader role can be made the leader of a group.', 'LearnDash', 'uncanny-automator' ) . '</div>',
				'required'              => true,
				'default_value'         => 'do_nothing',
				'options'               => array(
					'do_nothing' => esc_html_x( 'Do not add the Group Leader role', 'LearnDash', 'uncanny-automator' ),
					'add'        => esc_html_x( 'Add the role to their existing role(s)', 'LearnDash', 'uncanny-automator' ),
					'replace'    => esc_html_x( 'Replace their existing role(s) with the Group Leader role', 'LearnDash', 'uncanny-automator' ),
				),
				'supports_custom_value' => false,
				'supports_tokens'       => false,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$uo_group_title               = isset( $parsed['LDGROUPTITLE'] ) ? sanitize_text_field( $parsed['LDGROUPTITLE'] ) : '';
		$uo_group_courses             = isset( $parsed['LDGROUPCOURSES'] ) ? $parsed['LDGROUPCOURSES'] : '';
		$group_leader_role_assignment = isset( $parsed['GROUP_LEADER_ROLE_ASSIGNMENT'] ) ? sanitize_text_field( $parsed['GROUP_LEADER_ROLE_ASSIGNMENT'] ) : '';

		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			$this->add_log_error( esc_html_x( 'User not found.', 'LearnDash', 'uncanny-automator' ) );

			return false;
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
			$this->add_log_error( $group_id->get_error_message() );

			return false;
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

		$tokens = array(
			'GROUP_ID'            => $group_id,
			'GROUP_COURSES'       => $this->get_group_names( $group_id ),
			'GROUP_LEADER_EMAILS' => $this->get_group_leaders_email_addresses( $group_id ),
		);

		if ( Uncanny_Toolkit_Helpers::is_group_sign_up_activated() ) {
			$tokens['GROUP_SIGNUP_URL'] = Uncanny_Toolkit_Helpers::get_group_sign_up_url( $group_id );
		}

		$this->hydrate_tokens( $tokens );

		return true;
	}

	/**
	 * Get group leader email addresses.
	 *
	 * @param int $group_id
	 *
	 * @return string|array
	 */
	private function get_group_leaders_email_addresses( $group_id ) {

		$group_leaders = learndash_get_groups_administrators( $group_id );

		return ( is_array( array_column( $group_leaders, 'user_email' ) ) ) ? implode( ', ', array_column( $group_leaders, 'user_email' ) ) : array();
	}

	/**
	 * Get group course names.
	 *
	 * @param int $group_id
	 *
	 * @return string
	 */
	private function get_group_names( $group_id ) {

		$groups = learndash_group_enrolled_courses( $group_id );

		$group_names = array();

		foreach ( $groups as $group_id ) {
			$group_names[] = get_the_title( $group_id );
		}

		return implode( ', ', $group_names );
	}
}
