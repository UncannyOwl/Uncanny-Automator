<?php

namespace Uncanny_Automator;

class Affwp_Tokens {

	/** Integration code
	 *
	 * @var string
	 */
	public static $integration = 'AFFWP';

	public function __construct() {
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_affwp_trigger_tokens' ), 20, 6 );
		add_filter(
			'automator_maybe_trigger_affwp_tokens',
			array(
				$this,
				'affwp_possible_affiliate_tokens',
			),
			20,
			2
		);
		add_filter(
			'automator_maybe_trigger_affwp_specificetyperef_tokens',
			array(
				$this,
				'affwp_possible_affiliate_ref_tokens',
			),
			20,
			2
		);
	}

	function affwp_possible_affiliate_tokens( $tokens = array(), $args = array() ) {

		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$metas = array( 'APPROVEDAFFILIATE', 'NEWAFFILIATE', 'APPROVALWAITING' );

		if ( $trigger_integration === 'AFFWP' && in_array( $trigger_meta, $metas ) ) {
			$fields = array(
				array(
					'tokenId'         => 'AFFILIATEWPID',
					'tokenName'       => __( 'Affiliate ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPURL',
					'tokenName'       => __( 'Affiliate URL', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPSTATUS',
					'tokenName'       => __( 'Affiliate status', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPREGISTERDATE',
					'tokenName'       => __( 'Registration date', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPWEBSITE',
					'tokenName'       => __( 'Website', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPREFRATETYPE',
					'tokenName'       => __( 'Referral rate type', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPREFRATE',
					'tokenName'       => __( 'Referral rate', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPCOUPON',
					'tokenName'       => __( 'Dynamic coupon', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPACCEMAIL',
					'tokenName'       => __( 'Account email', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPPAYMENTEMAIL',
					'tokenName'       => __( 'Payment email', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPPROMOMETHODS',
					'tokenName'       => __( 'Promotion methods', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPNOTES',
					'tokenName'       => __( 'Affiliate notes', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
			);
			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	function affwp_possible_affiliate_ref_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = array(
			array(
				'tokenId'         => 'AFFILIATEWPID',
				'tokenName'       => __( 'Affiliate ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPURL',
				'tokenName'       => __( 'Affiliate URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPSTATUS',
				'tokenName'       => __( 'Affiliate status', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPREGISTERDATE',
				'tokenName'       => __( 'Registration date', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPWEBSITE',
				'tokenName'       => __( 'Website', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPREFRATETYPE',
				'tokenName'       => __( 'Referral rate type', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPREFRATE',
				'tokenName'       => __( 'Referral rate', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPCOUPON',
				'tokenName'       => __( 'Dynamic coupon', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPACCEMAIL',
				'tokenName'       => __( 'Account email', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPPAYMENTEMAIL',
				'tokenName'       => __( 'Payment email', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPPROMOMETHODS',
				'tokenName'       => __( 'Promotion methods', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPNOTES',
				'tokenName'       => __( 'Affiliate notes', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALAMOUNT',
				'tokenName'       => __( 'Referral amount', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALDATE',
				'tokenName'       => __( 'Referral date', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALDESCRIPTION',
				'tokenName'       => __( 'Referral description', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALREFERENCE',
				'tokenName'       => __( 'Referral reference', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALCONTEXT',
				'tokenName'       => __( 'Referral context', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALCUSTOM',
				'tokenName'       => __( 'Referral custom', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALSTATUS',
				'tokenName'       => __( 'Referral status', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
		);

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * @param     $value
	 * @param     $pieces
	 * @param     $recipe_id
	 * @param     $trigger_data
	 * @param int $user_id
	 * @param     $replace_args
	 *
	 * @return int|mixed|string
	 */
	function parse_affwp_trigger_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'SPECIFICETYPEREF', $pieces ) ||
				 in_array( 'SENTENCE_HUMAN_READABLE', $pieces ) ||
				 in_array( 'AFFWPAPPROVAL', $pieces ) ||
				 in_array( 'APPROVALWAITING', $pieces ) ||
				 in_array( 'APPROVEDAFFILIATE', $pieces ) ||
				 in_array( 'USERBECOMESAFFILIATE', $pieces ) ||
				 in_array( 'NEWAFFILIATE', $pieces ) ||
				 in_array( 'ACCOUNTAPPROVED', $pieces ) ||
				 in_array( 'AFFWPREJECTREFERRAL', $pieces ) ||
				 in_array( 'AFFWPPAIDREFERRAL', $pieces )
			) {

				if ( 'SPECIFICETYPEREF' === $pieces[1] || 'AFFWPREFERRAL' === $pieces[1] ) {
					$to_replace = $pieces[2];
					/** @var \AffWP\Referral $referral */
					$referral    = maybe_unserialize( Automator()->db->token->get( 'referral', $replace_args ) );
					$referral_id = $referral->referral_id;
					// Refresh Referral object
					$referral = affwp_get_referral( $referral_id );

					switch ( $to_replace ) {
						case 'SPECIFICETYPEREF':
							$value = $referral->type;
							break;
						case 'AFFILIATEWPID':
							$value = $referral->affiliate_id;
							break;
						case 'REFERRALAMOUNT':
							$value = $referral->amount;
							break;
						case 'REFERRALDATE':
							$value = $referral->date;
							break;
						case 'REFERRALDESCRIPTION':
							$value = $referral->description;
							break;
						case 'REFERRALCONTEXT':
							$value = $referral->context;
							break;
						case 'REFERRALREFERENCE':
							$value = $referral->reference;
							break;
						case 'REFERRALCUSTOM':
							$value = $referral->custom;
							break;
						case 'REFERRALSTATUS':
							$value = $referral->status;
							break;
						case 'AFFILIATEWPCOUPON':
							$dynamic_coupons = affwp_get_dynamic_affiliate_coupons( $referral->affiliate_id, false );
							$coupons         = '';
							if ( isset( $dynamic_coupons ) && is_array( $dynamic_coupons ) ) {
								foreach ( $dynamic_coupons as $coupon ) {
									$coupons .= $coupon->coupon_code . '<br/>';
								}
							}
							$value = $coupons;
							break;
						case 'AFFILIATEWPSTATUS':
							$affiliate = affwp_get_affiliate( $referral->affiliate_id );
							$value     = $affiliate->status;
							break;
						case 'AFFILIATEWPREGISTERDATE':
							$affiliate = affwp_get_affiliate( $referral->affiliate_id );
							$value     = $affiliate->date_registered;
							break;
						case 'AFFILIATEWPPAYMENTEMAIL':
							$value = affwp_get_affiliate_payment_email( $referral->affiliate_id );
							break;
						case 'AFFILIATEWPREFRATE':
							$affiliate = affwp_get_affiliate( $referral->affiliate_id );
							$value     = ! empty( $affiliate->rate ) ? $affiliate->rate : '0';
							break;
						case 'AFFILIATEWPREFRATETYPE':
							$affiliate = affwp_get_affiliate( $referral->affiliate_id );
							$value     = ! empty( $affiliate->rate_type ) ? $affiliate->rate_type : '0';
							break;
						case 'AFFILIATEWPPROMOMETHODS':
							$affiliate = affwp_get_affiliate( $referral->affiliate_id );
							$value     = get_user_meta( $affiliate->user_id, 'affwp_promotion_method', true );
							break;
						case 'AFFILIATEWPNOTES':
							$value = affwp_get_affiliate_meta( $referral->affiliate_id, 'notes', true );
							break;
						case 'AFFILIATEWPACCEMAIL':
							$user_id = affwp_get_affiliate_user_id( $referral->affiliate_id );
							$user    = get_user_by( 'id', $user_id );
							$value   = $user->user_email;
							break;
						case 'AFFILIATEWPWEBSITE':
							$user_id = affwp_get_affiliate_user_id( $referral->affiliate_id );
							$user    = get_user_by( 'id', $user_id );
							$value   = $user->user_url;
							break;
						case 'AFFILIATEWPURL':
							$value = affwp_get_affiliate_referral_url( array( 'affiliate_id' => $referral->affiliate_id ) );
							break;
					}
				} else {
					global $wpdb;
					$trigger_id     = $pieces[0];
					$trigger_meta   = $pieces[2];
					$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;

					$entry = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT meta_value
						FROM {$wpdb->prefix}uap_trigger_log_meta
						WHERE meta_key = %s
						AND automator_trigger_log_id = %d
						AND automator_trigger_id = %d
						LIMIT 0,1",
							$trigger_meta,
							$trigger_log_id,
							$trigger_id
						)
					);

					$value = maybe_unserialize( $entry );
				}
			}
		}

		return $value;
	}

}
