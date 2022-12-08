<?php

namespace Uncanny_Automator;

use EasyAffiliate\Lib\Db;
use EasyAffiliate\Lib\ModelFactory;
use EasyAffiliate\Models\User;

/**
 * Class Esaf_Tokens
 *
 * @package Uncanny_Automator
 */
class Esaf_Tokens {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_esaf_tokens', array( $this, 'esaf_possible_tokens' ), 20, 2 );
		add_filter(
			'automator_maybe_trigger_esaf_sale_recorded_code_tokens',
			array(
				$this,
				'esaf_commission_possible_tokens',
			),
			20,
			2
		);
		add_filter(
			'automator_maybe_trigger_esaf_sale_recorded_meta_tokens',
			array(
				$this,
				'esaf_sale_possible_tokens',
			),
			20,
			2
		);
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_esaf_tokens' ), 20, 6 );
	}

	/**
	 * save_token_data
	 *
	 * @param mixed $args
	 * @param mixed $trigger
	 *
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {
		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$trigger_meta_validations = apply_filters(
			'automator_easy_affiliate_validate_trigger_meta_pieces',
			array( 'AFFILIATE_ADDED_CODE', 'SALE_RECORDED_CODE' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_validations ) ) {
			$event             = array_shift( $args['trigger_args'] );
			$data              = ModelFactory::fetch( $event->evt_id_type, $event->evt_id );
			$trigger_log_entry = $args['trigger_entry'];
			if ( ! empty( $event ) ) {
				Automator()->db->token->save( 'event_data', maybe_serialize( $data ), $trigger_log_entry );
			}
		}
	}

	/**
	 * Affiliate possible tokens.
	 *
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed|\string[][]
	 */
	public function esaf_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];

		$trigger_meta_validations = apply_filters(
			'automator_easy_affiliate_validate_trigger_meta_pieces',
			array( 'AFFILIATE_ADDED_CODE', 'SALE_RECORDED_CODE' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {

			$fields = array(
				array(
					'tokenId'         => 'AFFILIATE_ID',
					'tokenName'       => __( 'Affiliate ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_USERNAME',
					'tokenName'       => __( 'Affiliate username', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_EMAIL',
					'tokenName'       => __( 'Affiliate email', 'uncanny-automator' ),
					'tokenType'       => 'email',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_NAME',
					'tokenName'       => __( 'Affiliate name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_STATUS',
					'tokenName'       => __( 'Affiliate status', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_ADDRESS_LINE1',
					'tokenName'       => __( 'Affiliate address - Line 1', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_ADDRESS_LINE2',
					'tokenName'       => __( 'Affiliate address - Line 2', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_ADDRESS_CITY',
					'tokenName'       => __( 'Affiliate address - City', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_ADDRESS_STATE',
					'tokenName'       => __( 'Affiliate address - State', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_ADDRESS_ZIP',
					'tokenName'       => __( 'Affiliate address - Zip', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_ADDRESS_COUNTRY',
					'tokenName'       => __( 'Affiliate address - Country', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_SIGNUP_DATE',
					'tokenName'       => __( 'Affiliate sign-up date', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_REFERRER',
					'tokenName'       => __( 'Affiliate referrer', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_NOTES',
					'tokenName'       => __( 'Affiliate notes', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
			);

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	/**
	 * Affiliate commission possible tokens.
	 *
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed|\string[][]
	 */
	public function esaf_commission_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];

		$trigger_meta_validations = apply_filters(
			'automator_easy_affiliate_validate_trigger_meta_pieces',
			array( 'AFFILIATE_ADDED_CODE', 'SALE_RECORDED_CODE' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {
			$fields = array(
				array(
					'tokenId'         => 'AFFILIATE_MTD_CLICKS',
					'tokenName'       => __( 'Affiliate MTD-clicks', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_YTD_CLICKS',
					'tokenName'       => __( 'Affiliate YTD-clicks', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_MTD_COMMISSIONS',
					'tokenName'       => __( 'Affiliate MTD commissions', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AFFILIATE_YTD_COMMISSIONS',
					'tokenName'       => __( 'Affiliate YTD commissions', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
			);

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	/**
	 * Affiliate sales possible tokens.
	 *
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed|\string[][]
	 */
	public function esaf_sale_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];

		$fields = array(
			array(
				'tokenId'         => 'SALE_AMOUNT',
				'tokenName'       => __( 'Sale amount', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'ORDER_SOURCE',
				'tokenName'       => __( 'Order source', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
		);

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * parse_tokens
	 *
	 * @param mixed $value
	 * @param mixed $pieces
	 * @param mixed $recipe_id
	 * @param mixed $trigger_data
	 * @param mixed $user_id
	 * @param mixed $replace_args
	 *
	 * @return void
	 */
	public function parse_esaf_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		$trigger_meta_validations = apply_filters(
			'automator_easy_affiliate_validate_trigger_meta_pieces',
			array( 'AFFILIATE_ADDED_CODE', 'SALE_RECORDED_CODE' ),
			array(
				'pieces'       => $pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);

		if ( ! array_intersect( $trigger_meta_validations, $pieces ) ) {
			return $value;
		}

		$to_replace = $pieces[2];
		$event_data = Automator()->db->token->get( 'event_data', $replace_args );
		if ( 'AFFILIATE_ADDED_CODE' === $pieces[1] ) {
			$affiliate_data = maybe_unserialize( $event_data );
		} else {
			$data           = maybe_unserialize( $event_data );
			$affiliate_data = new User( $data->affiliate_id );
		}

		switch ( $to_replace ) {
			case 'AFFILIATE_USERNAME':
				$value = $affiliate_data->user_login;
				break;
			case 'AFFILIATE_EMAIL':
				$value = $affiliate_data->user_email;
				break;
			case 'AFFILIATE_NAME':
				$value = $affiliate_data->display_name;
				break;
			case 'AFFILIATE_STATUS':
				$value = ( $affiliate_data->user_status == 0 ) ? 'Inactive' : 'Active';
				break;
			case 'AFFILIATE_ADDRESS_LINE1':
				$value = $affiliate_data->address_one;
				break;
			case 'AFFILIATE_ADDRESS_LINE2':
				$value = $affiliate_data->address_two;
				break;
			case 'AFFILIATE_ADDRESS_CITY':
				$value = $affiliate_data->city;
				break;
			case 'AFFILIATE_ADDRESS_STATE':
				$value = $affiliate_data->state;
				break;
			case 'AFFILIATE_ADDRESS_ZIP':
				$value = $affiliate_data->zip;
				break;
			case 'AFFILIATE_ADDRESS_COUNTRY':
				$value = $affiliate_data->country;
				break;
			case 'AFFILIATE_SIGNUP_DATE':
				$value = $affiliate_data->user_registered;
				break;
			case 'AFFILIATE_REFERRER':
				$value = empty( $affiliate_data->referrer ) ? '-' : $affiliate_data->referrer;
				break;
			case 'AFFILIATE_NOTES':
				$value = $affiliate_data->notes;
				break;
			case 'SALE_AMOUNT':
				$value = $data->sale_amount;
				break;
			case 'ORDER_SOURCE':
				$value = $data->source;
				break;
			case 'AFFILIATE_MTD_CLICKS':
				$value = $this->get_mtd_clicks( $data->affiliate_id );
				break;
			case 'AFFILIATE_YTD_CLICKS':
				$value = $this->get_ytd_clicks( $data->affiliate_id );
				break;
			case 'AFFILIATE_MTD_COMMISSIONS':
				$value = $this->get_mtd_commissions( $data->affiliate_id );
				break;
			case 'AFFILIATE_YTD_COMMISSIONS':
				$value = $this->get_ytd_commissions( $data->affiliate_id );
				break;
			case 'AFFILIATE_ID';
			default:
				$value = $affiliate_data->ID;
				break;
		}

		return $value;
	}

	/**
	 * @param $affiliate_id
	 *
	 * @return string|null
	 */
	public function get_mtd_clicks( $affiliate_id ) {
		$db = Db::fetch();
		global $wpdb;
		$year       = date( 'Y' );
		$month      = date( 'm' );
		$mtd_clicks = $wpdb->get_var( $wpdb->prepare( "SELECT IFNULL(COUNT(*),0) FROM {$db->clicks} as clk WHERE clk.affiliate_id = %d AND created_at BETWEEN '{$year}-{$month}-01 00:00:00' AND NOW()", $affiliate_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $mtd_clicks;
	}

	/**
	 * @param $affiliate_id
	 *
	 * @return string|null
	 */
	public function get_ytd_clicks( $affiliate_id ) {
		$db = Db::fetch();
		global $wpdb;
		$year       = date( 'Y' );
		$ytd_clicks = $wpdb->get_var( $wpdb->prepare( "SELECT IFNULL(COUNT(*),0) FROM {$db->clicks} as clk WHERE clk.affiliate_id = %d AND created_at BETWEEN '{$year}-01-01 00:00:00' AND NOW()", $affiliate_id ) );  //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $ytd_clicks;
	}

	/**
	 * @param $affiliate_id
	 *
	 * @return string|null
	 */
	public function get_mtd_commissions( $affiliate_id ) {
		$db = Db::fetch();
		global $wpdb;
		$year            = date( 'Y' );
		$month           = date( 'm' );
		$mtd_commissions = $wpdb->get_var( $wpdb->prepare( "SELECT IFNULL(SUM(commish.commission_amount),0.00) FROM {$db->commissions} AS commish LEFT JOIN {$db->transactions} txn ON commish.transaction_id = txn.id WHERE txn.status = 'complete' AND commish.affiliate_id = %d AND commish.created_at BETWEEN '{$year}-{$month}-01 00:00:00' AND NOW()", $affiliate_id ) );  //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $mtd_commissions;
	}

	/**
	 * @param $affiliate_id
	 *
	 * @return string|null
	 */
	public function get_ytd_commissions( $affiliate_id ) {
		$db = Db::fetch();
		global $wpdb;
		$year            = date( 'Y' );
		$ytd_commissions = $wpdb->get_var( $wpdb->prepare( "SELECT IFNULL(SUM(commish.commission_amount),0.00) FROM {$db->commissions} AS commish LEFT JOIN {$db->transactions} txn ON commish.transaction_id = txn.id WHERE txn.status = 'complete' AND commish.affiliate_id = %d AND commish.created_at BETWEEN '{$year}-01-01 00:00:00' AND NOW()", $affiliate_id ) );  //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $ytd_commissions;
	}
}
