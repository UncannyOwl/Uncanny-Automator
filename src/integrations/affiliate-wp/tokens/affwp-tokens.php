<?php

namespace Uncanny_Automator;

class Affwp_Tokens {

	/** Integration code
	 *
	 * @var string
	 */
	public static $integration = 'AFFWP';

	/**
	 * Constructor
	 */
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

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|array[]|mixed
	 */
	public function affwp_possible_affiliate_tokens( $tokens = array(), $args = array() ) {

		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['triggers_meta']['code'];

		$metas = array( 'APPROVEDAFFILIATE', 'NEWAFFILIATE', 'APPROVALWAITING' );

		if ( 'AFFWP' === $trigger_integration && in_array( $trigger_meta, $metas, true ) ) {
			$fields = array(
				array(
					'tokenId'         => 'AFFILIATEWPID',
					'tokenName'       => esc_html_x( 'Affiliate ID', 'AffiliateWP token', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPURL',
					'tokenName'       => esc_html_x( 'Affiliate URL', 'AffiliateWP token', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPSTATUS',
					'tokenName'       => esc_html_x( 'Affiliate status', 'AffiliateWP token', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPREGISTERDATE',
					'tokenName'       => esc_html_x( 'Registration date', 'AffiliateWP token', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPWEBSITE',
					'tokenName'       => esc_html_x( 'Website', 'AffiliateWP token', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPREFRATETYPE',
					'tokenName'       => esc_html_x( 'Referral rate type', 'AffiliateWP token', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPREFRATE',
					'tokenName'       => esc_html_x( 'Referral rate', 'AffiliateWP token', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPCOUPON',
					'tokenName'       => esc_html_x( 'Dynamic coupon', 'AffiliateWP token', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPACCEMAIL',
					'tokenName'       => esc_html_x( 'Account email', 'AffiliateWP token', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPPAYMENTEMAIL',
					'tokenName'       => esc_html_x( 'Payment email', 'AffiliateWP token', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPPROMOMETHODS',
					'tokenName'       => esc_html_x( 'Promotion methods', 'AffiliateWP token', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => 'AFFILIATEWPNOTES',
					'tokenName'       => esc_html_x( 'Affiliate notes', 'AffiliateWP token', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
			);
			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	public function affwp_possible_affiliate_ref_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = array(
			array(
				'tokenId'         => 'AFFILIATEWPID',
				'tokenName'       => esc_html_x( 'Affiliate ID', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPURL',
				'tokenName'       => esc_html_x( 'Affiliate URL', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPSTATUS',
				'tokenName'       => esc_html_x( 'Affiliate status', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPREGISTERDATE',
				'tokenName'       => esc_html_x( 'Registration date', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPWEBSITE',
				'tokenName'       => esc_html_x( 'Website', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPREFRATETYPE',
				'tokenName'       => esc_html_x( 'Referral rate type', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPREFRATE',
				'tokenName'       => esc_html_x( 'Referral rate', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPCOUPON',
				'tokenName'       => esc_html_x( 'Dynamic coupon', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPACCEMAIL',
				'tokenName'       => esc_html_x( 'Account email', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPPAYMENTEMAIL',
				'tokenName'       => esc_html_x( 'Payment email', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPPROMOMETHODS',
				'tokenName'       => esc_html_x( 'Promotion methods', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'AFFILIATEWPNOTES',
				'tokenName'       => esc_html_x( 'Affiliate notes', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALAMOUNT',
				'tokenName'       => esc_html_x( 'Referral amount', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALDATE',
				'tokenName'       => esc_html_x( 'Referral date', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALDESCRIPTION',
				'tokenName'       => esc_html_x( 'Referral description', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALREFERENCE',
				'tokenName'       => esc_html_x( 'Referral reference', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALCONTEXT',
				'tokenName'       => esc_html_x( 'Referral context', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALCUSTOM',
				'tokenName'       => esc_html_x( 'Referral custom', 'AffiliateWP token', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'REFERRALSTATUS',
				'tokenName'       => esc_html_x( 'Referral status', 'AffiliateWP token', 'uncanny-automator' ),
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
	public function parse_affwp_trigger_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'SPECIFICETYPEREF', $pieces, true ) ||
				in_array( 'SENTENCE_HUMAN_READABLE', $pieces, true ) ||
				in_array( 'AFFWPAPPROVAL', $pieces, true ) ||
				in_array( 'APPROVALWAITING', $pieces, true ) ||
				in_array( 'APPROVEDAFFILIATE', $pieces, true ) ||
				in_array( 'USERBECOMESAFFILIATE', $pieces, true ) ||
				in_array( 'NEWAFFILIATE', $pieces, true ) ||
				in_array( 'ACCOUNTAPPROVED', $pieces, true ) ||
				in_array( 'AFFWPREJECTREFERRAL', $pieces, true ) ||
				in_array( 'AFFWPPAIDREFERRAL', $pieces, true )
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
