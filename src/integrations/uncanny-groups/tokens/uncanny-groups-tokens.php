<?php

namespace Uncanny_Automator;

/**
 * Tokens class for Uncanny Groups
 */
class Uncanny_Groups_Tokens {

	/**
	 * Uncanny_Groups_Tokens constructor.
	 */
	public function __construct( $load_action_hook = true ) {

		if ( true === $load_action_hook ) {
			add_filter( 'automator_maybe_parse_token', array( $this, 'parse_uncanny_groups_token' ), 20, 6 );
			add_filter(
				'automator_maybe_trigger_uog_groupcreated_tokens',
				array(
					$this,
					'group_possible_tokens',
				),
				20,
				2
			);
		}

	}

	/**
	 * Tokens added for Seats added triggers.
	 *
	 * @return array[]
	 */
	public function seats_added_tokens() {

		return array(
			'GROUP_ID'            => array(
				'name' => __( 'Group ID', 'uncanny-automator' ),
			),
			'GROUP_TITLE'         => array(
				'name' => __( 'Group title', 'uncanny-automator' ),
			),
			'SEATS_ADDED'         => array(
				'name' => __( 'Seats added', 'uncanny-automator' ),
			),
			'GROUP_LEADER_EMAILS' => array(
				'name' => __( 'Group leader email(s)', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Tokens added for Seats removed triggers.
	 *
	 * @return array[]
	 */
	public function seats_removed_tokens() {

		return array(
			'GROUP_ID'            => array(
				'name' => __( 'Group ID', 'uncanny-automator' ),
			),
			'GROUP_TITLE'         => array(
				'name' => __( 'Group title', 'uncanny-automator' ),
			),
			'SEATS_REMOVED'       => array(
				'name' => __( 'Seats removed', 'uncanny-automator' ),
			),
			'GROUP_LEADER_EMAILS' => array(
				'name' => __( 'Group leader email(s)', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Hydrate token method for Seats added trigger.
	 *
	 * @param $parsed
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function seats_added_tokens_hydrate_tokens( $parsed, $args, $trigger ) {

		list( $count, $ld_group_id ) = $args['trigger_args'];

		return $parsed + array(
			'NUMBERCOND'          => $this->get_trigger_option_selected_value( $args['trigger_entry']['trigger_to_match'], 'NUMBERCOND' ),
			'GROUP_ID'            => absint( $ld_group_id ),
			'GROUP_TITLE'         => get_the_title( absint( $ld_group_id ) ),
			'SEATS_ADDED'         => absint( $count ),
			'GROUP_LEADER_EMAILS' => Automator()->helpers->recipe->uncanny_groups->options->get_group_leaders_email_addresses( $ld_group_id ),
		);

	}

	/**
	 * Directly fetches the value from db.
	 *
	 * @return string The field value.
	 */
	private function get_trigger_option_selected_value( $trigger_id = 0, $meta_key = '' ) {

		if ( empty( $trigger_id ) || empty( $meta_key ) ) {
			return null;
		}

		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$trigger_id,
				$meta_key
			)
		);

		if ( 'NUMBERCOND' === $meta_key ) {
			return Automator()->helpers->recipe->uncanny_groups->options->get_number_conditions_values( $value );
		}

		return $value;

	}

	/**
	 * Hydrate token method for Seats removed trigger.
	 *
	 * @param $parsed
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function seats_removed_tokens_hydrate_tokens( $parsed, $args, $trigger ) {

		list( $diff, $ld_group_id ) = $args['trigger_args'];

		return $parsed + array(
			'NUMBERCOND'          => $this->get_trigger_option_selected_value( $args['trigger_entry']['trigger_to_match'], 'NUMBERCOND' ),
			'GROUP_ID'            => absint( $ld_group_id ),
			'GROUP_TITLE'         => get_the_title( absint( $ld_group_id ) ),
			'SEATS_REMOVED'       => absint( $diff ),
			'GROUP_LEADER_EMAILS' => Automator()->helpers->recipe->uncanny_groups->options->get_group_leaders_email_addresses( $ld_group_id ),
		);

	}



	/**
	 * Uncanny groups possible tokens add
	 *
	 * @param $tokens
	 * @param $args
	 *
	 * @return array
	 */
	public function group_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$trigger_meta = $args['meta'];

		$fields = array(
			array(
				'tokenId'         => 'UNCANNYGROUP',
				'tokenName'       => __( 'Group title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'UNCANNYGROUP_ID',
				'tokenName'       => __( 'Group ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'UNCANNYGROUP_URL',
				'tokenName'       => __( 'Group URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'UNCANNYGROUP_SEATS',
				'tokenName'       => __( 'Group seats', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'UNCANNYGROUP_COURSES',
				'tokenName'       => __( 'Group courses', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'UNCANNYGROUP_LEADER',
				'tokenName'       => __( 'Group leader email', 'uncanny-automator' ),
				'tokenType'       => 'email',
				'tokenIdentifier' => $trigger_meta,
			),
		);

		if ( Uncanny_Toolkit_Helpers::is_group_sign_up_activated() ) {
			$fields[] = array(
				'tokenId'         => 'UNCANNYGROUP_SIGNUP_URL',
				'tokenName'       => __( 'Group signup URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);
		}

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * Parse tokens method
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed|string
	 */
	public function parse_uncanny_groups_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$tokens = array(
			'REGISTEREDWITHGROUPKEY',
			'REDEEMSGROUPKEY',
			'GROUPCREATED',
		);
		if ( empty( $pieces ) || empty( $trigger_data ) ) {
			return $value;
		}
		if ( ! isset( $pieces[2] ) ) {
			return $value;
		}

		$meta_code = $pieces[1];
		if ( empty( $meta_code ) ) {
			return $value;
		}
		if ( ! in_array( $meta_code, $tokens, true ) ) {
			return $value;
		}
		$meta_field = $pieces[2];
		foreach ( $trigger_data as $trigger ) {
			if ( empty( $trigger ) ) {
				continue;
			}
			$code_details = Automator()->db->token->get( 'code_details', $replace_args );
			switch ( $meta_field ) {
				case 'UNCANNYGROUPS':
					$value = get_the_title( $code_details['ld_group_id'] );
					break;
				case 'UNCANNYGROUPS_ID':
					$value = $code_details['ld_group_id'];
					break;
				case 'UNCANNYGROUPS_URL':
					$value = get_permalink( $code_details['ld_group_id'] );
					break;
				case 'UNCANNYGROUPS_KEY':
					$value = $code_details['key'];
					break;
				case 'UNCANNYGROUPS_KEY_BATCH_ID':
					$value = $code_details['group_id'];
					break;
			}
		}

		if ( in_array( 'GROUPCREATED', $pieces, true ) ) {
			foreach ( $trigger_data as $trigger ) {
				if ( empty( $trigger ) ) {
					continue;
				}

				$group_id = Automator()->db->token->get( 'group_id', $replace_args );
				switch ( $pieces[2] ) {
					case 'UNCANNYGROUP_URL':
						$value = get_permalink( $group_id );
						break;
					case 'UNCANNYGROUP':
						$value = get_the_title( $group_id );
						break;
					case 'UNCANNYGROUP_LEADER':
						$leader_id = Automator()->db->token->get( 'leader_id', $replace_args );
						$leader    = get_userdata( $leader_id );
						$value     = $leader->user_email;
						break;
					case 'UNCANNYGROUP_SEATS':
						$value = ulgm()->group_management->seat->total_seats( $group_id );
						break;
					case 'UNCANNYGROUP_COURSES':
						$courses_ids = learndash_group_enrolled_courses( $group_id );
						$courses     = array();
						if ( ! empty( $courses_ids ) ) {
							foreach ( $courses_ids as $courses_id ) {
								$courses[] = get_the_title( $courses_id );
							}
						}

						if ( is_array( $courses ) ) {
							$value = join( ', ', $courses );
						}
						break;
					case 'UNCANNYGROUP_SIGNUP_URL':
						if ( Uncanny_Toolkit_Helpers::is_group_sign_up_activated() ) {
							$value = Uncanny_Toolkit_Helpers::get_group_sign_up_url( $group_id );
						} else {
							$value = '';
						}
						break;
					case 'UNCANNYGROUP_ID':
					default:
						$value = $group_id;
						break;
				}
			}
		}

		return $value;
	}

}
