<?php

namespace Uncanny_Automator;

/**
 * Class Uc_Tokens
 *
 * @package Uncanny_Automator
 */
class Uc_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UNCANNYCODE';

	/**
	 * Uc_Tokens constructor.
	 */
	public function __construct() {

		// Adding code token for logged-in triggers
		add_filter(
			'automator_maybe_trigger_uncannycode_tokens',
			array(
				$this,
				'uc_codes_code_token',
			),
			20,
			2
		);

		//Adding Batch Codes tokens
		add_filter(
			'automator_maybe_trigger_uncannycode_anoncodebatchcreated_tokens',
			array(
				$this,
				'uc_codes_possible_tokens',
			),
			20,
			2
		);

		add_filter(
			'automator_maybe_parse_token',
			array(
				$this,
				'parse_uncanny_codes_token',
			),
			20,
			6
		);
	}

	public function uc_codes_code_token( $tokens = array(), $args = array() ) {

		$trigger_code = $args['triggers_meta']['code'];

		$trigger_meta_validations = apply_filters(
			'automator_uncannycodes_validate_triggers_for_code_token',
			array( 'CODEREDEEMED', 'UCBATCH', 'UCPREFIX', 'UCSUFFIX' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {

			$fields = array(
				array(
					'tokenId'         => 'CODE_REDEEMED',
					'tokenName'       => __( 'Code', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
			);

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;

	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function uc_codes_possible_tokens( $tokens = array(), $args = array() ) {

		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_code = $args['triggers_meta']['code'];

		$fields = array(
			array(
				'tokenId'         => 'UNCANNYCODESBATCH_ID',
				'tokenName'       => __( 'Batch ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'UNCANNYCODESTYPE',
				'tokenName'       => __( 'Type', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'UNCANNYCODESPREFIXBATCH',
				'tokenName'       => __( 'Prefix', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'UNCANNYCODESSUFFIXBATCH',
				'tokenName'       => __( 'Suffix', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'UNCANNYCODESLD_TYPE',
				'tokenName'       => __( 'LD Type', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'UNCANNYCODESMAX_PER_CODE',
				'tokenName'       => __( 'Max per code', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'UNCANNYCODESCODES_GENERATED',
				'tokenName'       => __( 'Codes Generated', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'UNCANNYCODESEXPIRY_DATE',
				'tokenName'       => __( 'Expiry date', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'UNCANNYCODESLIST_OF_CODES',
				'tokenName'       => __( 'Codes (CSV list of codes)', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
		);

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * Only load this integration and its triggers and actions if the related
	 * plugin is active
	 *
	 * @param $status
	 * @param $code
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $code ) {

		if ( self::$integration === $code ) {

			$status = true;
		}

		return $status;
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
	public function parse_uncanny_codes_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$tokens = array(
			'UNCANNYCODESPREFIX',
			'UNCANNYCODESSUFFIX',
			'UNCANNYCODESBATCH',
			'UNCANNYCODESBATCHEXPIRY',
			'UNCANNYCODESBATCH_ID',
			'UNCANNYCODESTYPE',
			'UNCANNYCODESPREFIXBATCH',
			'UNCANNYCODESSUFFIXBATCH',
			'UNCANNYCODESLD_TYPE',
			'UNCANNYCODESMAX_PER_CODE',
			'UNCANNYCODESCODES_GENERATED',
			'UNCANNYCODESEXPIRY_DATE',
			'UNCANNYCODESLIST_OF_CODES',
			'CODE_REDEEMED',
		);

		if ( $pieces && isset( $pieces[2] ) ) {
			$meta_field = $pieces[2];
			if ( ! empty( $meta_field ) && in_array( $meta_field, $tokens ) ) {
				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						switch ( $meta_field ) {
							case 'UNCANNYCODESBATCHEXPIRY':
								global $wpdb;
								$batch_id         = isset( $trigger['meta']['UNCANNYCODESBATCH'] ) && intval( '-1' ) !== intval( $trigger['meta']['UNCANNYCODESBATCH'] ) ? $trigger['meta']['UNCANNYCODESBATCH'] : absint( Automator()->db->token->get( 'UNCANNYCODESBATCH', $replace_args ) ); // Fix warning in error log.
								$expiry_date      = $wpdb->get_var( $wpdb->prepare( "SELECT expire_date FROM `{$wpdb->prefix}uncanny_codes_groups` WHERE ID = %d", $batch_id ) );
								$expiry_timestamp = strtotime( $expiry_date );

								// Check if the date is in future to filter out empty dates
								if ( $expiry_timestamp > time() ) {

									// Get the format selected in general WP settings
									$date_format = get_option( 'date_format' );
									$time_format = get_option( 'time_format' );

									// Get the formattted time according to the selected time zone
									$value = date_i18n( "$date_format $time_format", strtotime( $expiry_date ) );
								}
								break;
							case 'UNCANNYCODESBATCH':
								$value = $trigger['meta']['UNCANNYCODESBATCH_readable'];
								break;
							case 'UNCANNYCODESBATCH_ID':
								$value = Automator()->db->token->get( 'UNCANNYCODESBATCH_ID', $replace_args );
								break;
							case 'UNCANNYCODESTYPE':
								$value = Automator()->db->token->get( 'UNCANNYCODESTYPE', $replace_args );
								break;
							case 'UNCANNYCODESPREFIXBATCH':
								$value = Automator()->db->token->get( 'UNCANNYCODESPREFIXBATCH', $replace_args );
								break;
							case 'UNCANNYCODESSUFFIXBATCH':
								$value = Automator()->db->token->get( 'UNCANNYCODESSUFFIXBATCH', $replace_args );
								break;
							case 'UNCANNYCODESLD_TYPE':
								$value = Automator()->db->token->get( 'UNCANNYCODESLD_TYPE', $replace_args );
								break;
							case 'UNCANNYCODESMAX_PER_CODE':
								$value = Automator()->db->token->get( 'UNCANNYCODESMAX_PER_CODE', $replace_args );
								break;
							case 'UNCANNYCODESCODES_GENERATED':
								$value = Automator()->db->token->get( 'UNCANNYCODESCODES_GENERATED', $replace_args );
								break;
							case 'UNCANNYCODESEXPIRY_DATE':
								$value = Automator()->db->token->get( 'UNCANNYCODESEXPIRY_DATE', $replace_args );
								break;
							case 'UNCANNYCODESLIST_OF_CODES':
								$value = Automator()->db->token->get( 'UNCANNYCODESLIST_OF_CODES', $replace_args );
								break;
							case 'CODE_REDEEMED':
								$value = Automator()->db->token->get( 'CODE_REDEEMED', $replace_args );
								break;
							default:
								$value = isset( $trigger['meta'][ $meta_field ] ) ? $trigger['meta'][ $meta_field ] : '';
								break;
						}
					}
				}
			}
		}

		return $value;
	}

}
