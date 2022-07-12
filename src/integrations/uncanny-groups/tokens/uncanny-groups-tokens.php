<?php

namespace Uncanny_Automator;

/**
 *
 */
class Uncanny_Groups_Tokens {

	/**
	 * Uncanny_Groups_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_uncanny_groups_token' ), 20, 6 );
		add_filter( 'automator_maybe_trigger_uog_groupcreated_tokens', array( $this, 'group_possible_tokens' ), 20, 2 );
	}

	/**
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
				'tokenType'       => 'text',
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
		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
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
