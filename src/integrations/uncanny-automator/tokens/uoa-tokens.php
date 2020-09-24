<?php

namespace Uncanny_Automator;

/**
 * Class Uoa_Tokens
 * @package Uncanny_Automator
 */
class Uoa_Tokens {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'UOA';

	/**
	 * Wp_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_trigger_uoa_uoaerror_tokens', [ $this, 'possible_tokens' ], 9999, 2 );
		add_filter( 'automator_maybe_trigger_uoa_uoarecipe_tokens', [ $this, 'possible_recipe_tokens' ], 9999, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'uoa_token' ], 20, 6 );
	}


	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function possible_tokens( $tokens = [], $args = [] ) {

		$new_tokens = [];

		$new_tokens[] = [
			'tokenId'         => 'UOAERRORS',
			'tokenName'       => esc_attr__( 'Recipe ID', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOAERRORS_recipe_id',
		];
		$new_tokens[] = [
			'tokenId'         => 'UOAERRORS',
			'tokenName'       => esc_attr__( 'Recipe title', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOAERRORS_recipe_title',
		];
		$new_tokens[] = [
			'tokenId'         => 'UOAERRORS',
			'tokenName'       => esc_attr__( 'Recipe edit link', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOAERRORS_recipe_edit_link',
		];

		$new_tokens[] = [
			'tokenId'         => 'UOAERRORS',
			'tokenName'       => esc_attr__( 'Recipe log URL', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOAERRORS_recipe_log_url',
		];

		$new_tokens[] = [
			'tokenId'         => 'UOAERRORS',
			'tokenName'       => esc_attr__( 'Action log URL', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOAERRORS_action_log_url',
		];

		$tokens = array_merge( $tokens, $new_tokens );

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param array $replace_args
	 *
	 * @return string|null
	 */
	public function uoa_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args = [] ) {

		if ( in_array( 'UOAERRORS', $pieces, true ) || in_array( 'UOARECIPES', $pieces, true ) ) {
			global $wpdb;

			switch ( $pieces[1] ) {
				case 'UOAERRORS_recipe_id':
				case 'UOARECIPES_recipe_id':
					$value = $recipe_id;
					break;
				case 'UOAERRORS_recipe_title':
				case 'UOAERRORS_recipe_edit_link':
				case 'UOAERRORS_recipe_log_url':
				case 'UOAERRORS_action_log_url':
				case 'UOARECIPES_recipe_title':
				case 'UOARECIPES_recipe_edit_link':
				case 'UOARECIPES_recipe_log_url':
				case 'UOARECIPES_action_log_url':
					$value = $wpdb->get_var( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE automator_trigger_log_id = {$replace_args['trigger_log_id']} && meta_key = '{$pieces[1]}'" );

					if ( 'UOAERRORS_recipe_log_url' === $pieces[1] || 'UOARECIPES_recipe_log_url' === $pieces[1] ) {
						$value = admin_url( 'edit.php' ) . "?post_type=uo-recipe&page=uncanny-automator-recipe-log&$value";
					}
					if ( 'UOAERRORS_action_log_url' === $pieces[1] || 'UOARECIPES_action_log_url' === $pieces[1] ) {
						$value = admin_url( 'edit.php' ) . "?post_type=uo-recipe&page=uncanny-automator-action-log&$value";
					}
					break;
			}
		}

		return $value;
	}
	
	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function possible_recipe_tokens( $tokens = [], $args = [] ) {
		
		$new_tokens = [];
		
		$new_tokens[] = [
			'tokenId'         => 'UOARECIPES',
			'tokenName'       => esc_attr__( 'Recipe ID', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOARECIPES_recipe_id',
		];
		$new_tokens[] = [
			'tokenId'         => 'UOARECIPES',
			'tokenName'       => esc_attr__( 'Recipe title', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOARECIPES_recipe_title',
		];
		$new_tokens[] = [
			'tokenId'         => 'UOARECIPES',
			'tokenName'       => esc_attr__( 'Recipe edit link', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOARECIPES_recipe_edit_link',
		];

		$new_tokens[] = [
			'tokenId'         => 'UOARECIPES',
			'tokenName'       => esc_attr__( 'Recipe log URL', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOARECIPES_recipe_log_url',
		];

		$new_tokens[] = [
			'tokenId'         => 'UOARECIPES',
			'tokenName'       => esc_attr__( 'Action log URL', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'UOARECIPES_action_log_url',
		];

		$tokens = array_merge( $tokens, $new_tokens );

		return $tokens;
	}
}
