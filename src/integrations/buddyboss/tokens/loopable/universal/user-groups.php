<?php
namespace Uncanny_Automator\Integrations\Buddyboss\Tokens\Loopable\Universal;

use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;
use Uncanny_Automator\Services\Loopable\Universal_Loopable_Token;

/**
 * User_Groups
 *
 * @package Uncanny_Automator\Integrations\Buddyboss\Tokens\Loopable\Universal
 */
class User_Groups extends Universal_Loopable_Token {

	/**
	 * Register loopable token.
	 *
	 * @return void
	 */
	public function register_loopable_token() {

		$child_tokens = array(
			'GROUP_ID'   => array(
				'name'       => _x( 'Group ID', 'LearnDash', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
			'GROUP_NAME' => array(
				'name' => _x( 'Group name', 'LearnDash', 'uncanny-automator' ),
			),
		);

		$this->set_id( 'USER_GROUPS' );
		$this->set_name( _x( "User's groups", 'LearnDash', 'uncanny-automator' ) );
		$this->set_log_identifier( '#{{GROUP_ID}} {{GROUP_NAME}}' );
		$this->set_child_tokens( $child_tokens );

	}

	/**
	 * Hydrate the tokens.
	 *
	 * @param mixed $args
	 * @return Loopable_Token_Collection
	 */
	public function hydrate_token_loopable( $args ) {

		$loopable = new Loopable_Token_Collection();

		$user_groups = $this->get_user_groups( absint( $args['user_id'] ?? 0 ) );

		// Bail.
		if ( false === $user_groups ) {
			return $loopable;
		}

		foreach ( $user_groups as $group ) {
			$loopable->create_item(
				array(
					'GROUP_ID'   => $group['group_id'],
					'GROUP_NAME' => $group['name'],
				)
			);
		}

		return $loopable;

	}

	/**
	 * Retrieves all groups a user belongs to in BuddyBoss.
	 *
	 * @param int $user_id The ID of the user whose groups are being retrieved.
	 * @return array|false An array of group details or false on failure.
	 */
	private function get_user_groups( $user_id ) {

		// Check if the BuddyPress Groups component is active.
		if ( ! function_exists( 'groups_get_user_groups' ) ) {
			return false; // BuddyPress Groups component is not active or not available.
		}

		// Validate the user ID.
		if ( ! is_int( $user_id ) || $user_id <= 0 ) {
			return false; // Invalid user ID provided.
		}

		// Get the groups for the specified user ID
		$user_groups = groups_get_user_groups( $user_id );
		$group_ids   = $user_groups['groups'];

		// Check if any groups were found
		if ( empty( $group_ids ) ) {
			return false; // No groups found for this user.
		}

		$groups_data = array();

		// Loop through each group ID and gather relevant details
		foreach ( $group_ids as $group_id ) {
			$group = groups_get_group( $group_id );

			if ( ! is_object( $group ) ) {
				continue; // Skip if the group object is not valid
			}

			$groups_data[] = array(
				'group_id'    => $group->id,
				'name'        => $group->name,
				'description' => $group->description,
				'status'      => $group->status,
			);
		}

		// Return the groups data or false if no valid groups were processed
		return ! empty( $groups_data ) ? $groups_data : false;
	}

}
