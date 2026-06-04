<?php

namespace Uncanny_Automator\Integrations\Learndash;

/**
 * Class LD_MAKEUSERLEADER
 *
 * @package Uncanny_Automator\Integrations\Learndash
 *
 * @property \Uncanny_Automator\Integrations\Learndash\Ld_Helpers $item_helpers
 */
class LD_MAKEUSERLEADER extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Set up the action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'LD' );
		$this->set_action_code( 'MAKEUSERLEADER' );
		$this->set_action_meta( 'LDGROUP' );

		$this->set_sentence(
			sprintf(
				esc_html_x( 'Make the user the leader of {{a group:%1$s}}', 'LearnDash', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Make the user the leader of {{a group}}', 'LearnDash', 'uncanny-automator' )
		);

	}

	/**
	 * Define action tokens.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function define_tokens() {
		return array(
			'GROUP_TITLE' => array(
				'name' => esc_html_x( 'Group title', 'LearnDash', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$args = array(
			'post_type'      => 'groups',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = automator_wp_query( $args, 'legacy' );

		return array(
			array(
				'option_code' => 'LDGROUP',
				'label'       => esc_html_x( 'Group', 'LearnDash', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => $options,
			),
			array(
				'input_type'            => 'select',
				'option_code'           => 'GROUP_LEADER_ROLE_ASSIGNMENT',
				'label'                 => esc_html_x( 'If the user does not currently have the Group Leader role', 'LearnDash', 'uncanny-automator' ),
				'description'           => '<div class="user-selector__warning">' . esc_html_x( 'Only users with the Group Leader role can be made the leader of a group.', 'LearnDash', 'uncanny-automator' ) . '</div>',
				'required'              => true,
				'default_value'         => 'do_nothing',
				'options'               => array(
					'do_nothing' => esc_html_x( 'Do nothing', 'LearnDash', 'uncanny-automator' ),
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

		$uo_group                     = isset( $parsed['LDGROUP'] ) ? absint( $parsed['LDGROUP'] ) : 0;
		$group_leader_role_assignment = isset( $parsed['GROUP_LEADER_ROLE_ASSIGNMENT'] ) ? sanitize_text_field( $parsed['GROUP_LEADER_ROLE_ASSIGNMENT'] ) : '';

		$user = get_user_by( 'ID', $user_id );

		if ( is_wp_error( $user ) ) {
			return false;
		}

		$this->hydrate_tokens(
			array(
				'GROUP_TITLE' => get_the_title( $uo_group ),
			)
		);

		if ( user_can( $user, 'group_leader' ) ) {
			ld_update_leader_group_access( $user_id, $uo_group );

			return true;
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

		return true;
	}
}
