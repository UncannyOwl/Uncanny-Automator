<?php

namespace Uncanny_Automator;

/**
 * \Uncanny_Automator\Advanced_Ads_Tokens
 */
class Advanced_Ads_Tokens {
	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_advads_tokens', array( $this, 'advads_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_advads_tokens' ), 20, 6 );
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
			'automator_advanced_ads_validate_trigger_meta_pieces_save',
			array( 'AD_STATUS_SET_CODE' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_validations ) ) {
			$ad                = array_shift( $args['trigger_args'] );
			$trigger_log_entry = $args['trigger_entry'];
			if ( ! empty( $ad ) ) {
				Automator()->db->token->save( 'save_ad', maybe_serialize( $ad ), $trigger_log_entry );
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
	public function advads_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];

		$trigger_meta_validations = apply_filters(
			'automator_advanced_ads_validate_trigger_meta_pieces_common',
			array( 'AD_STATUS_SET_CODE' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {

			$fields = array(
				array(
					'tokenId'         => 'AD_ID',
					'tokenName'       => __( 'Ad ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AD_TITLE',
					'tokenName'       => __( 'Ad title', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AD_STATUS',
					'tokenName'       => __( 'Ad status', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AD_GROUP',
					'tokenName'       => __( 'Ad group', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AD_EXPIRY_DATE',
					'tokenName'       => __( 'Ad expiry date', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AD_TYPE',
					'tokenName'       => __( 'Ad type', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AD_DETAILS',
					'tokenName'       => __( 'Ad details', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'AD_AUTHOR',
					'tokenName'       => __( 'Ad author', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
			);

			$tokens = array_merge( $tokens, $fields );
		}

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
	public function parse_advads_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		$trigger_meta_validations = apply_filters(
			'automator_advanced_ads_validate_trigger_meta_pieces_common',
			array( 'AD_STATUS_SET_CODE' ),
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
		$ad_data    = Automator()->db->token->get( 'save_ad', $replace_args );
		$ad         = maybe_unserialize( $ad_data );
		$post       = get_post( $ad->id );

		switch ( $to_replace ) {
			case 'AD_TITLE':
				$value = $post->post_title;
				break;
			case 'AD_STATUS':
				$value = $post->post_status;
				break;
			case 'AD_GROUP':
				$terms = wp_get_post_terms( $post->ID, 'advanced_ads_groups', array( 'fields' => 'names' ) );
				$value = join( ', ', $terms );
				break;
			case 'AD_TYPE':
				$types = \Advanced_Ads::get_instance()->ad_types;
				$value = $types[ $ad->type ]->title;
				break;
			case 'AD_EXPIRY_DATE':
				$value = get_post_meta( $post->ID, 'advanced_ads_expiration_time', true );
				break;
			case 'AD_DETAILS':
				$url     = ( ! empty( $ad->url ) ) ? $ad->url : '-';
				$content = ( ! empty( $ad->content ) ) ? $ad->content : '-';
				$width   = ( ! empty( $ad->width ) ) ? $ad->width : '-';
				$height  = ( ! empty( $ad->height ) ) ? $ad->height : '-';
				$value   = "Ad URL: {$url}<br/> Ad width: {$width}<br/>Ad height: {$height}<br/>Ad content: {$content}";
				break;
			case 'AD_AUTHOR':
				$value = get_the_author_meta( 'display_name', $post->post_author );
				break;
			case 'AD_ID';
			default:
				$value = $post->ID;
				break;
		}

		return $value;
	}
}
