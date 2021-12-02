<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Tokens
 * @since   3.0
 * @version 3.0
 * @package Uncanny_Automator
 * @author  Saad S.
 */

namespace Uncanny_Automator\Recipe;

/**
 * Trait Tokens
 *
 * @package Uncanny_Automator
 */
trait Tokens {

	/**
	 * @var mixed|void
	 */
	public static $user_tokens;
	//  /**
	//   * @var
	//   */
	//  protected static $token_parser;

	/**
	 * Tokens constructor.
	 */
	public function __construct() {
		self::$user_tokens = apply_filters(
			'automator_user_tokens',
			array(
				'user_id',
				'user_username',
				'user_firstname',
				'user_lastname',
				'user_email',
				'user_displayname',
			)
		);
	}

	//  /**
	//   * @return mixed
	//   */
	//  public static function get_token_parser() {
	//      return self::$token_parser;
	//  }
	//
	//  /**
	//   * @param mixed $token_parser
	//   */
	//  public static function set_token_parser( $token_parser ) {
	//      self::$token_parser = $token_parser;
	//  }

	/**
	 * @param $trigger_code
	 * @param $token_id
	 *
	 * @return mixed|null
	 */
	public static function get_parser_object_from_trigger( $trigger_code, $token_id = '' ) {
		if ( empty( $trigger_code ) || empty( $token_id ) ) {
			return null;
		}
		$tokens = Automator()->get->value_from_trigger_meta( $trigger_code, 'tokens' );
		if ( empty( $tokens ) ) {
			return null;
		}
		foreach ( $tokens as $token ) {
			if ( strtoupper( $token_id ) === strtoupper( $token['tokenId'] ) ) {
				return isset( $token['parserObject'] ) ? $token['parserObject'] : null;
			}
		}

		return null;
	}

	/**
	 * Get value of the token
	 */
	public static function get() {

	}

	/**
	 * Build token sentence, {{trigger_code:token}}
	 *
	 * @param $trigger_code
	 * @param $token_id
	 * @param $type
	 * @param $prefix
	 *
	 * @return array
	 */
	public static function build( $args ) {
		$trigger_code = isset( $args['trigger_code'] ) ? esc_attr( $args['trigger_code'] ) : null;
		if ( null === $trigger_code || empty( $trigger_code ) ) {
			return array();
		}
		$token_id = isset( $args['token_id'] ) ? esc_attr( $args['token_id'] ) : null;
		$type     = isset( $args['type'] ) ? esc_attr( $args['type'] ) : null;
		$prefix   = isset( $args['prefix'] ) ? esc_attr( $args['prefix'] ) : '';
		//      $parsing_func = self::get_token_parser();
		//      $parse_from = isset( $args['parse_from'] ) ? $args['parse_from'] : null;

		return array(
			'tokenId'         => strtoupper( $token_id ),
			'tokenName'       => self::generate_token_name( $token_id, $prefix ),
			'tokenType'       => $type,
			'tokenIdentifier' => $trigger_code,
		//          'tokenParser'     => $parsing_func,
		//          'parserObject'    => $parse_from,
		);
	}

	/**
	 * @param $token_id
	 * @param $prefix
	 *
	 * @return string
	 */
	public static function generate_token_name( $token_id, $prefix = '' ) {
		if ( empty( $prefix ) ) {
			return ucfirst( str_replace( array( '_', '-' ), ' ', $token_id ) );
		}

		return sprintf(
			'%s: %s',
			$prefix,
			ucfirst(
				str_replace(
					array(
						'_',
						'-',
					),
					' ',
					$token_id
				)
			)
		);
	}

	/**
	 * @param $token
	 *
	 * @return string
	 */
	public static function reverse_token_id( $token ) {
		return 'get_' . strtolower( $token );
	}

	/**
	 * Function to parse token {{trigger_id:trigger_code:token}}
	 *
	 * @param $trigger_id
	 * @param $trigger_code
	 * @param $token
	 * @param $args
	 */
	public function parse( $trigger_id, $trigger_code, $token, $args ) {

	}

	/**
	 * Store token in to the trigger meta table
	 */
	public function store() {

	}

	/**
	 * Human readable string of the token
	 */
	public function human_readable() {

	}

	/**
	 *
	 */
	protected function possible_tokens() {

	}

	/**
	 *
	 */
	protected function relevant_tokens() {
	}

	/**
	 * @param $meta
	 * @param $token
	 *
	 * @return bool
	 */
	public static function is_valid( $meta, $token ) {
		/**
		 * @var mixed $value
		 * @var $pieces
		 * @var $recip_id
		 * @var $trigger_data
		 * @var $user_id
		 * @var $replace_args
		 */
		extract( $token ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		if ( ! $pieces ) {
			// Token array is empty.
			return false;
		}

		if ( ! array_key_exists( 2, $pieces ) || empty( $pieces[2] ) ) {
			// Token is missing.
			return false;
		}

		if ( $meta !== $pieces[2] ) {
			// Token doesn't match trigger meta.
			return false;
		}

		if ( ! array_key_exists( 0, $trigger_data ) || empty( $trigger_data[0] ) ) {
			// Trigger data is empty.
			return false;
		}

		if ( empty( $trigger_data[0]['meta'] ) ) {
			// Trigger meta is empty.
			return false;
		}

		return true;
	}

	/**
	 * @param $meta
	 * @param $token
	 *
	 * @return bool
	 */
	public static function is_valid_user_token( $token ) {
		/**
		 * @var mixed $value
		 * @var $pieces
		 * @var $recip_id
		 * @var $trigger_data
		 * @var $user_id
		 * @var $replace_args
		 */
		extract( $token ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		if ( ! $pieces ) {
			// Token array is empty.
			return false;
		}
		$replaceable = str_replace( array( '{{', '}}', '', $pieces ) );
		if ( ! in_array( $replaceable, self::$user_tokens, false ) ) {
			// Token is missing.
			return false;
		}

		return true;
	}


	/**
	 * @param $meta
	 * @param $token
	 * @param false $readable
	 *
	 * @return mixed|void
	 */
	public static function parse_from_trigger_meta( $meta, $token, $readable = false ) {
		/**
		 * @var mixed $value
		 * @var $pieces
		 * @var $recip_id
		 * @var $trigger_data
		 * @var $user_id
		 * @var $replace_args
		 */
		extract( $token ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		if ( ! self::is_valid( $meta, $token ) ) {
			return apply_filters( 'automator_parse_from_trigger_meta', $value, $meta, $token, $readable );
		}

		$value = $trigger_data[0]['meta'];

		if ( true === $readable && isset( $trigger_meta[ $meta . '_readable' ] ) && ! empty( $trigger_meta[ $meta . '_readable' ] ) ) {
			$value = $trigger_meta[ $meta . '_readable' ];
		}

		return apply_filters( 'automator_parse_from_trigger_meta', $value, $meta, $token, $readable );
	}

	/**
	 * @param $meta
	 * @param $token
	 *
	 * @return mixed|void
	 */
	public static function parse_user_tokens( $meta, $token ) {
		/**
		 * @var mixed $value
		 * @var $pieces
		 * @var $recip_id
		 * @var $trigger_data
		 * @var $user_id
		 * @var $replace_args
		 */
		extract( $token ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		if ( ! self::is_valid( $meta, $token ) ) {
			return apply_filters( 'automator_parse_user_tokens', $value, $meta, $token );
		}
	}

	/**
	 * @param $tokens
	 *
	 * @return array|mixed
	 */
	public static function remove_duplicate_token_ids( $tokens ) {
		$new_tokens = array();
		if ( empty( $tokens ) ) {
			return $tokens;
		}
		foreach ( $tokens as $token ) {
			if ( ! array_key_exists( $token['tokenId'], $new_tokens ) ) {
				$new_tokens[ $token['tokenId'] ] = $token;
			}
		}

		return array_values( $new_tokens );
	}
}
