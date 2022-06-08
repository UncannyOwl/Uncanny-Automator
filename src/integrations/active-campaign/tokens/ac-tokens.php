<?php

namespace Uncanny_Automator;

/**
 * ActiveCampaign Tokens file
 */
class AC_TOKENS {


	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_active_campaign_tag_tokens', array( $this, 'register_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_tokens' ), 20, 6 );
	}

	/**
	 * register_tokens
	 *
	 * @param  mixed $tokens
	 * @param  mixed $args
	 * @return void
	 */
	public function register_tokens( $tokens = array(), $args = array() ) {

		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$tokens[] = array(
			'tokenId'         => 'EMAIL',
			'tokenName'       => __( 'Email address', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'CONTACT_TAG_ADDED',
		);

		$tokens[] = array(
			'tokenId'         => 'TAGS',
			'tokenName'       => __( 'All contact tags (comma separated)', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'CONTACT_TAG_ADDED',
		);

		$tokens[] = array(
			'tokenId'         => 'FIRST_NAME',
			'tokenName'       => __( 'First Name', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'CONTACT_TAG_ADDED',
		);

		$tokens[] = array(
			'tokenId'         => 'LAST_NAME',
			'tokenName'       => __( 'Last name', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'CONTACT_TAG_ADDED',
		);

		$tokens[] = array(
			'tokenId'         => 'PHONE',
			'tokenName'       => __( 'Phone', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'CONTACT_TAG_ADDED',
		);

		$tokens[] = array(
			'tokenId'         => 'CUSTOMER_ACCT_NAME',
			'tokenName'       => __( 'Account', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'CONTACT_TAG_ADDED',
		);

		return $tokens;
	}

	/**
	 * save_token_data
	 *
	 * @param  mixed $args
	 * @param  mixed $trigger
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {

		if ( 'ACTIVE_CAMPAIGN' !== $trigger->get_integration() ) {
			return;
		}

		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$trigger_code = $args['entry_args']['code'];

		if ( 'CONTACT_TAG_ADDED' === $trigger_code || 'CONTACT_TAG_REMOVED' === $trigger_code ) {
			$ac_event = array_shift( $args['trigger_args'] );

			$trigger_log_entry = $args['trigger_entry'];

			if ( ! empty( $ac_event['tag'] ) ) {
				Automator()->db->token->save( 'TAG', $ac_event['tag'], $trigger_log_entry );
			}

			if ( ! empty( $ac_event['contact']['email'] ) ) {
				Automator()->db->token->save( 'EMAIL', $ac_event['contact']['email'], $trigger_log_entry );
			}

			if ( ! empty( $ac_event['contact']['tags'] ) ) {
				Automator()->db->token->save( 'TAGS', $ac_event['contact']['tags'], $trigger_log_entry );
			}

			if ( ! empty( $ac_event['contact']['first_name'] ) ) {
				Automator()->db->token->save( 'FIRST_NAME', $ac_event['contact']['first_name'], $trigger_log_entry );
			}

			if ( ! empty( $ac_event['contact']['last_name'] ) ) {
				Automator()->db->token->save( 'LAST_NAME', $ac_event['contact']['last_name'], $trigger_log_entry );
			}

			if ( ! empty( $ac_event['contact']['phone'] ) ) {
				Automator()->db->token->save( 'PHONE', $ac_event['contact']['phone'], $trigger_log_entry );
			}

			if ( ! empty( $ac_event['contact']['customer_acct_name'] ) ) {
				Automator()->db->token->save( 'CUSTOMER_ACCT_NAME', $ac_event['contact']['customer_acct_name'], $trigger_log_entry );
			}
		}

	}

	/**
	 * parse_tokens
	 *
	 * @param  mixed $value
	 * @param  mixed $pieces
	 * @param  mixed $recipe_id
	 * @param  mixed $trigger_data
	 * @param  mixed $user_id
	 * @param  mixed $replace_args
	 * @return void
	 */
	public function parse_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		if ( 'CONTACT_TAG_ADDED' !== $pieces[1] && 'CONTACT_TAG_REMOVED' !== $pieces[1] ) {
			return $value;
		}

		$meta_key = $pieces[2];

		$value = Automator()->db->token->get( $meta_key, $replace_args );

		return $value;

	}

}
