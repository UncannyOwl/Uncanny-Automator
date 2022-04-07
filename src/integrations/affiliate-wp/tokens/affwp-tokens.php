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
					'tokenType'       => 'text',
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
				'tokenType'       => 'text',
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

		return $value;
	}

}
