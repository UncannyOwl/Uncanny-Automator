<?php
namespace Uncanny_Automator;

/**
 * @todo Move this to new token architecture.
 */
class WA_MESSAGE_DELIVERY_TOKENS {

	/**
	 * Constant TRIGGERS.
	 *
	 * The Triggers that uses this tokens.
	 *
	 * @var array The list of trigger codes.
	 */
	const TRIGGERS = array(
		'WA_MESSAGE_NOT_DELIVERED',
		'WA_MESSAGE_NOT_DELIVERED_NO_OPTIN',
	);

	const TOKEN_META = 'WA_MESSAGE_DELIVERY_TOKENS';

	public function __construct() {

		foreach ( self::TRIGGERS as $trigger ) {

			add_filter( 'automator_maybe_trigger_whatsapp_' . strtolower( $trigger ) . '_tokens', array( $this, 'register_tokens' ), 20, 2 );

		}

		add_filter( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_token' ), 20, 6 );

	}

	/**
	 * Method register_tokens.
	 *
	 * Register the tokens for consumption.
	 *
	 * @return array The list of tokens.
	 */
	public function register_tokens( $tokens = array(), $args = array() ) {

		if ( ! automator_do_identify_tokens() ) {

			return $tokens;

		}

		$tokens_collection = array_merge(
			$this->get_message_delivery_tokens()
		);

		$arr_column_tokens_collection = array_column( $tokens_collection, 'name' );

		array_multisort( $arr_column_tokens_collection, SORT_ASC, $tokens_collection );

		foreach ( $tokens_collection as $token ) {
			$tokens[] = array(
				'tokenId'         => str_replace( ' ', '_', $token['id'] ),
				'tokenName'       => $token['name'],
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WHATSAPP_MESSAGE_DELIVERY',
			);
		}

		return $tokens;
	}

	/**
	 * Method save_token_data.
	 *
	 * Save the token data before parsing.
	 */
	public function save_token_data( $args, $trigger ) {

		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {

			return;

		}

		// Check if trigger code is for WhatsApp.
		if ( in_array( $args['entry_args']['code'], self::TRIGGERS, true ) ) {

			$message_delivery = array_shift( $args['trigger_args'] );

			Automator()->db->token->save( self::TOKEN_META, wp_json_encode( $message_delivery ), $args['trigger_entry'] );

		}
	}

	/**
	 * Method parse_token.
	 *
	 * @return mixed $value The token value.
	 */
	public function parse_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$trigger_code = ! empty( $trigger_data[0]['meta']['code'] ) ? $trigger_data[0]['meta']['code'] : '';

		if ( ! $this->is_token_from_trigger( $trigger_code, $pieces ) ) {

			return $value;

		}

		$trigger_meta = json_decode( Automator()->db->token->get( self::TOKEN_META, $replace_args ), true );

		return $this->get_token_value( $pieces[2], $trigger_meta );

	}

	public function get_token_value( $id, $incoming_data ) {

		$token = array(
			'recipient_number' => $incoming_data['to'],
			'delivery_error'   => $incoming_data['errors']['code'],
			'message'          => $incoming_data['errors']['message'],
		);

		return isset( $token[ $id ] ) ? $token[ $id ] : '';

	}

	public function is_token_from_trigger( $trigger_code = '', $pieces = array() ) {

		if ( empty( $trigger_code ) || ! in_array( $trigger_code, self::TRIGGERS, true ) ) {

			return false;

		}

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {

			return false;

		}

		return true;

	}


	/**
	 * Method get_message_delivery_tokens.
	 *
	 * @return array The message delivery tokens.
	 */
	public function get_message_delivery_tokens() {

		return array(
			array(
				'name' => esc_html__( 'Recipient number', 'uncanny-automator' ),
				'id'   => 'recipient_number',
			),
			array(
				'name' => esc_html__( 'Message', 'uncanny-automator' ),
				'id'   => 'message',
			),
			array(
				'name' => esc_html__( 'Delivery error', 'uncanny-automator' ),
				'id'   => 'delivery_error',
			),
		);

	}

}
