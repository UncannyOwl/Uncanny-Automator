<?php

namespace Uncanny_Automator;

/**
 * Class Wpdm_Tokens
 *
 * @package Uncanny_Automator
 */
class Wpdm_Tokens {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_wpdm_tokens', array( $this, 'wpdm_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_wpdm_tokens' ), 20, 6 );
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
			'automator_wpdm_validate_trigger_meta_pieces_common',
			array( 'SPECIFIC_FILE_DOWNLOADED_CODE' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_validations ) ) {
			$package           = array_shift( $args['trigger_args'] );
			$trigger_log_entry = $args['trigger_entry'];
			if ( ! empty( $package ) ) {
				Automator()->db->token->save( 'package_data', maybe_serialize( $package ), $trigger_log_entry );
			}
		}
	}

	/**
	 * WP Download Manager possible tokens.
	 *
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed|\string[][]
	 */
	public function wpdm_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];

		$trigger_meta_validations = apply_filters(
			'automator_wpdm_validate_trigger_meta_pieces_common',
			array( 'SPECIFIC_FILE_DOWNLOADED_CODE' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {

			$fields = array(
				array(
					'tokenId'         => 'FILE_ID',
					'tokenName'       => __( 'File ID', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'FILE_TITLE',
					'tokenName'       => __( 'File title', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'FILE_URL',
					'tokenName'       => __( 'File URL', 'uncanny-automator' ),
					'tokenType'       => 'url',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'FILE_DESCRIPTION',
					'tokenName'       => __( 'File description', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'FILE_EXCERPT',
					'tokenName'       => __( 'File excerpt', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'FILE_CATEGORY',
					'tokenName'       => __( 'File category', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'FILE_TAG',
					'tokenName'       => __( 'File tag', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'FILE_AUTHOR',
					'tokenName'       => __( 'File author', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'FILE_DOWNLOADABLE_FILES',
					'tokenName'       => __( 'Downloadable file', 'uncanny-automator' ),
					'tokenType'       => 'url',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'USER_ID',
					'tokenName'       => __( 'User ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'USERNAME',
					'tokenName'       => __( 'Username', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'USER_FIRSTNAME',
					'tokenName'       => __( 'First name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'USER_LASTNAME',
					'tokenName'       => __( 'Last name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'USER_EMAIL',
					'tokenName'       => __( 'Email', 'uncanny-automator' ),
					'tokenType'       => 'email',
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
	public function parse_wpdm_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		$trigger_meta_validations = apply_filters(
			'automator_wpdm_validate_trigger_meta_pieces_common',
			array( 'SPECIFIC_FILE_DOWNLOADED_CODE' ),
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

		$to_replace   = $pieces[2];
		$package_data = Automator()->db->token->get( 'package_data', $replace_args );
		$package      = maybe_unserialize( $package_data );

		switch ( $to_replace ) {
			case 'FILE_TITLE';
				$value = $package['title'];
				break;
			case 'FILE_URL';
				$value = get_permalink( $package['ID'] );
				break;
			case 'FILE_DESCRIPTION';
				$value = $package['description'];
				break;
			case 'FILE_EXCERPT';
				$value = $package['excerpt'];
				break;
			case 'FILE_DOWNLOADABLE_FILES';
				$value = join( ', ', $package['files'] );
				break;
			case 'FILE_TAG';
				$tags  = wp_get_post_terms( $package['ID'], 'wpdmtag', array( 'fields' => 'names' ) );
				$value = join( ', ', $tags );
				break;
			case 'FILE_CATEGORY';
				$categories = wp_get_post_terms( $package['ID'], 'wpdmcategory', array( 'fields' => 'names' ) );
				$value      = join( ', ', $categories );
				break;
			case 'FILE_AUTHOR';
				$value = get_the_author_meta( 'display_name', $package['author'] );
				break;
			case 'FILE_ID';
				$value = $package['ID'];
				break;
			case 'USER_ID';
				$value = ! empty( $user_id ) ? $user_id : '';
				break;
			case 'USERNAME';
				$user  = get_userdata( $user_id );
				$value = $user->user_login;
				break;
			case 'USER_FIRSTNAME';
				$user  = get_userdata( $user_id );
				$value = $user->user_firstname;
				break;
			case 'USER_LASTNAME';
				$user  = get_userdata( $user_id );
				$value = $user->user_lastname;
				break;
			case 'USER_EMAIL';
				$user  = get_userdata( $user_id );
				$value = $user->user_email;
				break;
		}

		return $value;
	}
}
