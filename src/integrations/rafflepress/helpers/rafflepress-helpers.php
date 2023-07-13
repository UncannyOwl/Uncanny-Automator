<?php

namespace Uncanny_Automator\Integrations\RafflePress;

/**
 * Class Rafflepress_Helpers
 *
 * @package Uncanny_Automator
 */
class Rafflepress_Helpers {

	/**
	 * Get all RafflePress giveaways
	 *
	 * @return array
	 */
	public function get_all_rafflepress_giveaway() {
		global $wpdb;
		$options   = array();
		$options[] = array(
			'text'  => esc_attr_x( 'Any giveaway', 'RafflePress', 'uncanny-automator' ),
			'value' => - 1,
		);
		$giveaways = $wpdb->get_results( "SELECT id,name FROM {$wpdb->prefix}rafflepress_giveaways WHERE deleted_at is null ORDER BY name ASC", ARRAY_A );
		foreach ( $giveaways as $giveaway ) {
			$options[] = array(
				'text'  => $giveaway['name'],
				'value' => $giveaway['id'],
			);
		}

		return $options;
	}

	/**
	 * Get common tokens for a giveaway
	 *
	 * @return array[]
	 */
	public function rafflepress_common_tokens_for_giveaway() {

		return array(
			// Giveaway tokens
			array(
				'tokenId'   => 'GIVEAWAY_TITLE',
				'tokenName' => __( 'Name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'GIVEAWAY_START_DATE',
				'tokenName' => __( 'Start date', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'GIVEAWAY_END_DATE',
				'tokenName' => __( 'End date', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'GIVEAWAY_ENTRIES',
				'tokenName' => __( 'Entries', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'GIVEAWAY_USER_COUNT',
				'tokenName' => __( 'User count', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'GIVEAWAY_STATUS',
				'tokenName' => __( 'Status', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

	}

	/**
	 * Get common tokens for a contestant
	 *
	 * @return array[]
	 */
	public function rafflepress_common_tokens_for_contestant() {

		return array(
			// Contestant tokens
			array(
				'tokenId'   => 'CONTESTANT_NAME',
				'tokenName' => __( 'Contestant name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CONTESTANT_EMAIL',
				'tokenName' => __( 'Contestant email', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'CONTESTANT_EMAIL_VERIFIED',
				'tokenName' => __( 'Contestant email verified', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

	}

	/**
	 * Parse giveaway token values
	 *
	 * @param $giveaway_id
	 *
	 * @return array
	 */
	public function hydrate_giveaway_tokens( $giveaway_id ) {
		// Generate array of empty default values.
		$defaults = wp_list_pluck( $this->rafflepress_common_tokens_for_giveaway(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );
		global $wpdb;
		$giveaway = $wpdb->get_row( $wpdb->prepare( "SELECT name,starts,ends,active FROM {$wpdb->prefix}rafflepress_giveaways WHERE id=%d ORDER BY name ASC", $giveaway_id ), ARRAY_A );
		if ( ! $giveaway ) {
			return $tokens;
		}
		$giveaway_total_entries = $wpdb->get_var( $wpdb->prepare( "SELECT count(id) FROM {$wpdb->prefix}rafflepress_entries WHERE giveaway_id = %d", $giveaway_id ) );
		$giveaway_total_users   = $wpdb->get_var( $wpdb->prepare( "SELECT count(id) FROM {$wpdb->prefix}rafflepress_contestants WHERE giveaway_id = %d", $giveaway_id ) );

		$tokens['GIVEAWAY_TITLE']      = $giveaway['name'];
		$tokens['GIVEAWAY_START_DATE'] = date( get_option( 'date_format' ), strtotime( $giveaway['starts'] ) );
		$tokens['GIVEAWAY_END_DATE']   = date( get_option( 'date_format' ), strtotime( $giveaway['ends'] ) );
		$tokens['GIVEAWAY_ENTRIES']    = $giveaway_total_entries;
		$tokens['GIVEAWAY_USER_COUNT'] = $giveaway_total_users;
		$tokens['GIVEAWAY_STATUS']     = ( true === $giveaway['active'] ) ? 'Active' : 'Inactive';

		return $tokens;
	}

	/**
	 * Parse contestant token values
	 *
	 * @param $contestant_id
	 *
	 * @return array
	 */
	public function hydrate_contestant_tokens( $contestant_id ) {
		// Generate array of empty default values.
		$defaults = wp_list_pluck( $this->rafflepress_common_tokens_for_contestant(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );
		global $wpdb;
		$contestant = $wpdb->get_row( $wpdb->prepare( "SELECT fname,lname,email,status FROM {$wpdb->prefix}rafflepress_contestants WHERE id=%d", $contestant_id ), ARRAY_A );
		if ( ! $contestant ) {
			return $tokens;
		}

		$tokens['CONTESTANT_NAME']           = $contestant['fname'] . ' ' . $contestant['lname'];
		$tokens['CONTESTANT_EMAIL']          = $contestant['email'];
		$tokens['CONTESTANT_EMAIL_VERIFIED'] = ( 'confirmed' === $contestant['status'] ) ? 'Yes' : 'No';

		return $tokens;
	}
}
