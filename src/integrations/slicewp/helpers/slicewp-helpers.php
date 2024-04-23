<?php

namespace Uncanny_Automator\Integrations\SliceWP;

/**
 * Class Slicewp_Helpers
 *
 * @pacakge Uncanny_Automator
 */
class Slicewp_Helpers {

	/**
	 * @return array
	 */
	public function get_statuses() {
		$option       = array();
		$all_statuses = slicewp_get_affiliate_available_statuses();
		foreach ( $all_statuses as $key => $status ) {
			$option[] = array(
				'text'  => $status,
				'value' => $key,
			);
		}

		return $option;

	}

	/**
	 * get common tokens
	 *
	 * @return array[]
	 */
	public function sliceWP_get_common_tokens() {
		$tokens = array(
			array(
				'tokenId'   => 'SWP_AFFILIATE_USER_ID',
				'tokenName' => __( 'User ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'SWP_AFFILIATE_ID',
				'tokenName' => __( 'Affiliate ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'SWP_AFFILIATE_EMAIL',
				'tokenName' => __( 'Affiliate email', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'SWP_AFFILIATE_NAME',
				'tokenName' => __( 'Affiliate name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SWP_REGISTRATION_DATE',
				'tokenName' => __( 'Registration date', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'SWP_PAYMENT_EMAIL',
				'tokenName' => __( 'Payment email', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'SWP_WEBSITE',
				'tokenName' => __( 'Website', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'SWP_SUBJECT',
				'tokenName' => __( 'How will you promote us?', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SWP_STATUS',
				'tokenName' => __( 'Status', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SWP_AFFILIATE_URL',
				'tokenName' => __( 'Affiliate URL', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'SWP_NOTES',
				'tokenName' => __( 'Notes', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return $tokens;
	}

	/**
	 * @param $affiliate_id
	 * @param $affiliate_data
	 *
	 * @return array
	 */
	public function sliceWP_parse_common_token_values( $affiliate_id, $affiliate_data ) {

		$notes = slicewp_get_notes(
			array(
				'object_context' => 'affiliate',
				'object_id'      => $affiliate_id,
			),
			false
		);

		$note_content = array();
		if ( ! empty( $notes ) ) {
			foreach ( $notes as $note ) {
				$note_content[] = $note->get( 'note_content' );
			}
		}

		if ( ! isset( $affiliate_data['user_id'] ) ) {
			$affiliate                 = slicewp_get_affiliate( $affiliate_id );
			$affiliate_data['user_id'] = $affiliate->get( 'user_id' );
		}

		$affiliate_token_values = array(
			'SWP_AFFILIATE_USER_ID' => $affiliate_data['user_id'],
			'SWP_AFFILIATE_ID'      => $affiliate_id,
			'SWP_AFFILIATE_EMAIL'   => slicewp_get_affiliate_email( $affiliate_id ),
			'SWP_AFFILIATE_NAME'    => slicewp_get_affiliate_name( $affiliate_id ),
			'SWP_REGISTRATION_DATE' => $affiliate_data['date_created'],
			'SWP_PAYMENT_EMAIL'     => $affiliate_data['payment_email'],
			'SWP_WEBSITE'           => $affiliate_data['website'],
			'SWP_SUBJECT'           => slicewp_get_affiliate_meta( $affiliate_id, 'promotional_methods', true ),
			'SWP_STATUS'            => $affiliate_data['status'],
			'SWP_AFFILIATE_URL'     => slicewp_get_affiliate_url( $affiliate_id ),
			'SWP_NOTES'             => join( ' | ', $note_content ),
		);

		return $affiliate_token_values;
	}
}
