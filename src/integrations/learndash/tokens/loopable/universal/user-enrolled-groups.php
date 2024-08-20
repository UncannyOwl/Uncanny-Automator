<?php
namespace Uncanny_Automator\Integrations\LearnDash\Tokens\Loopable\Universal;

use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;
use Uncanny_Automator\Services\Loopable\Universal_Loopable_Token;

/**
 * User_Enrolled_Groups
 *
 * @package Uncanny_Automator\Integrations\LearnDash\Tokens\Loopable\Universal\User_Enrolled_Groups
 */
class User_Enrolled_Groups extends Universal_Loopable_Token {

	/**
	 * Register loopable token.
	 *
	 * @return void
	 */
	public function register_loopable_token() {

		$child_tokens = array(
			'GROUP_ID'          => array(
				'name'       => _x( 'Group ID', 'LearnDash', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
			'GROUP_NAME'        => array(
				'name' => _x( 'Group name', 'LearnDash', 'uncanny-automator' ),
			),
			'GROUP_DESCRIPTION' => array(
				'name' => _x( 'Group description', 'LearnDash', 'uncanny-automator' ),
			),
			'GROUP_PERMALINK'   => array(
				'name' => _x( 'Group URL', 'LearnDash', 'uncanny-automator' ),
			),
		);

		$this->set_id( 'ENROLLED_GROUPS' );
		$this->set_name( _x( "User's groups", 'LearnDash', 'uncanny-automator' ) );
		$this->set_log_identifier( '#{{GROUP_ID}} {{GROUP_NAME}}' );
		$this->set_child_tokens( $child_tokens );

	}

	/**
	 * Hydrate the tokens.
	 *
	 * @param mixed $args
	 *
	 * @return Loopable_Token_Collection
	 */
	public function hydrate_token_loopable( $args ) {

		$loopable = new Loopable_Token_Collection();

		$groups = $this->get_user_groups( $args['user_id'] );

		if ( false === $groups ) {
			return $loopable;
		}

		// Now you can use the $posts_data array as needed
		foreach ( $groups as $group ) {
			$loopable->create_item(
				array(
					'GROUP_ID'          => $group['GROUP_ID'] ?? '',
					'GROUP_NAME'        => $group['GROUP_NAME'] ?? '',
					'GROUP_DESCRIPTION' => $group['GROUP_DESCRIPTION'] ?? '',
					'GROUP_PERMALINK'   => $group['GROUP_PERMALINK'] ?? '',
				)
			);
		}

		return $loopable;

	}

	/**
	 * Retrieve the user's group.
	 *
	 * @param mixed $user_id
	 *
	 * @return (int|string)[][]|false
	 */
	function get_user_groups( $user_id ) {

		// Check if LearnDash function exists
		if ( ! function_exists( 'learndash_get_users_group_ids' ) ) {
			return false;
		}

		// Get the user's group IDs
		$group_ids = (array) learndash_get_users_group_ids( $user_id );

		// Check if the user is in any groups
		if ( empty( $group_ids ) ) {
			return false;
		}

		// Initialize an array to store group details
		$groups = array();

		// Loop through each group ID and get group details
		foreach ( $group_ids as $id ) {

			$groups[] = array(
				'GROUP_ID'          => $id,
				'GROUP_NAME'        => get_the_title( $id ),
				'GROUP_DESCRIPTION' => get_the_excerpt( $id ),
				'GROUP_PERMALINK'   => get_the_permalink( $id ),
			);

		}

		return $groups;

	}

}
