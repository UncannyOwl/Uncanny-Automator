<?php

namespace Uncanny_Automator\Integrations\Thrive_Ultimatum;

/**
 *
 */
class Thrive_Ultimatum_Helpers {

	/**
	 * @return array[]
	 */
	public function get_all_campaign_tokens() {
		return array(
			array(
				'tokenId'   => 'campaign_id',
				'tokenName' => __( 'Campaign ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'campaign_name',
				'tokenName' => __( 'Campaign title', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'campaign_type',
				'tokenName' => __( 'Campaign type', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'campaign_start_date',
				'tokenName' => __( 'Campaign start date', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'campaign_end_date',
				'tokenName' => __( 'Campaign end date', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'campaign_trigger_type',
				'tokenName' => __( 'Campaign trigger type', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'countdown_event_id',
				'tokenName' => __( 'Campaign countdown', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * @return array[]
	 */
	public function get_all_user_tokens() {
		return array(
			array(
				'tokenId'   => 'user_id',
				'tokenName' => __( 'User ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'username',
				'tokenName' => __( 'Username', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'membership_level',
				'tokenName' => __( 'Membership level', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'email',
				'tokenName' => __( 'User email', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'ip_address',
				'tokenName' => __( 'IP address', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'user_agent',
				'tokenName' => __( 'User agent', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'comments',
				'tokenName' => __( 'Number of comments', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'last_logged_in',
				'tokenName' => __( 'Last logged-in', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'registered',
				'tokenName' => __( 'Registered date', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'last_updated',
				'tokenName' => __( 'Last updated', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
		);
	}

	/**
	 * @param $campaign
	 * @param $user_data
	 *
	 * @return array
	 */
	public function parse_all_token_values( $campaign = array(), $user_data = array() ) {
		$campaign_tokens_value = array();
		$user_tokens_value     = array();

		if ( ! empty( $campaign ) ) {
			$campaign_tokens_value = array(
				'campaign_id'           => $campaign['campaign_id'],
				'campaign_name'         => $campaign['campaign_name'],
				'campaign_type'         => $campaign['campaign_type'],
				'campaign_start_date'   => date( get_option( 'date_format' ), strtotime( $campaign['campaign_start_date'] ) ),
				'campaign_end_date'     => date( get_option( 'date_format' ), strtotime( $campaign['campaign_end_date'] ) ),
				'campaign_trigger_type' => $campaign['campaign_trigger_type'],
				'countdown_event_id'    => $campaign['countdown_event_id'],
			);
		}

		if ( ! empty( $user_data ) ) {
			$user_tokens_value = array(
				'user_id'          => $user_data['user_id'],
				'username'         => $user_data['username'],
				'membership_level' => $user_data['membership_level'],
				'email'            => $user_data['email'],
				'ip_address'       => $user_data['ip_address'],
				'user_agent'       => $user_data['user_agent'],
				'comments'         => $user_data['comments'],
				'last_logged_in'   => date( get_option( 'date_format' ), strtotime( $user_data['last_logged_in'] ) ),
				'last_updated'     => ! empty( $user_data['last_updated'] ) ? date( get_option( 'date_format' ), strtotime( $user_data['last_updated'] ) ) : '',
				'registered'       => date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $user_data['registered'] ) ),
			);
		}

		return array_merge( $campaign_tokens_value, $user_tokens_value );
	}
}
