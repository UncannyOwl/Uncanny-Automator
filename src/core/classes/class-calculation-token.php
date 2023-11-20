<?php

namespace Uncanny_Automator;

/**
 * Class Calculation_Token.
 *
 * @package Uncanny_Automator
 */
class Calculation_Token {

	/**
	 * @var \ChrisKonnertz\StringCalc\StringCalc
	 */
	public $string_calc;

	/**
	 * @var
	 */
	public $recipe_id;
	/**
	 * @var
	 */
	public $user_id;
	/**
	 * @var
	 */
	public $replace_args;

	/**
	 * @var
	 */
	public $parsed_formula;
	/**
	 * @var
	 */
	public $result;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

		$this->string_calc = new \ChrisKonnertz\StringCalc\StringCalc();

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_token' ), 999, 6 );

	}

	/**
	 * parse_token
	 *
	 * @param mixed $return
	 * @param mixed $pieces
	 * @param mixed $recipe_id
	 * @param mixed $trigger_data
	 * @param mixed $user_id
	 * @param mixed $replace_args
	 *
	 * @return string
	 */
	public function parse_token( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! $this->is_calculation_token( $replace_args ) ) {
			return $return;
		}

		try {

			$this->hydrate( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args )
				 ->calculate();

			$return = $this->get_result();

		} catch ( \Exception $e ) {
			$return = $e->getMessage();
		}

		return apply_filters( 'automator_calculation_token_output', $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );

	}

	/**
	 * is_calculation_token
	 *
	 * @param mixed $replace_args
	 *
	 * @return void
	 */
	public function is_calculation_token( $replace_args ) {

		if ( empty( $replace_args['pieces'][0] ) || 'CALCULATION' !== $replace_args['pieces'][0] ) {
			return false;
		}

		return true;
	}

	/**
	 * hydrate
	 *
	 * @param mixed $return
	 * @param mixed $pieces
	 * @param mixed $recipe_id
	 * @param mixed $trigger_data
	 * @param mixed $user_id
	 * @param mixed $replace_args
	 *
	 * @return $this
	 */
	public function hydrate( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$this->return       = $return;
		$this->pieces       = $pieces;
		$this->recipe_id    = $recipe_id;
		$this->trigger_data = $trigger_data;
		$this->user_id      = $user_id;
		$this->replace_args = $replace_args;

		$this->formula        = $this->get_formula( $replace_args );
		$this->parsed_formula = $this->parse_inner_tokens( $this->formula );

		return $this;
	}

	/**
	 * get_formula
	 *
	 * @return $this
	 */
	public function get_formula( $replace_args ) {

		if ( empty( $replace_args['pieces'][1] ) ) {
			throw new \Exception( __( 'Error: Missing formula.', 'uncanny-automator' ) );
		}

		$formula = $replace_args['pieces'][1];

		$formula = $this->replace_brackets( $formula );

		return $formula;
	}

	/**
	 * parse_inner_tokens
	 *
	 * @return string
	 */
	public function parse_inner_tokens( $tokens ) {

		if ( false === strpos( $tokens, '{{' ) ) {
			return $tokens;
		}

		// Find all tokens
		preg_match_all( '/{{(.*?)}}/', $tokens, $matches );

		// Walk through each token separately so we can check if it is numeric or not
		foreach ( $matches[0] as $order => $token ) {
			$matches[1][ $order ] = $this->parse_inner_token( $token );
		}

		// Replace the parsed tokens and return
		return str_replace( $matches[0], $matches[1], $tokens );
	}

	/**
	 * parse_inner_token
	 *
	 * @param mixed $token
	 *
	 * @return void
	 */
	public function parse_inner_token( $token ) {

		$parsed_value = Automator()->parse->text( $token, $this->recipe_id, $this->user_id, $this->replace_args );

		// If not numeric, replace the value with a string zero
		if ( ! is_numeric( $parsed_value ) ) {
			$parsed_value = '0';
		}

		return $parsed_value;
	}

	/**
	 * replace_brackets
	 *
	 * @param mixed $string
	 *
	 * @return string
	 */
	public function replace_brackets( $string ) {
		return str_replace(
			array( '««', '»»', '¦' ),
			array(
				'{{',
				'}}',
				':',
			),
			$string
		);
	}

	/**
	 * calculate
	 *
	 * @return $this
	 */
	public function calculate() {

		$this->result = $this->string_calc->calculate( $this->parsed_formula );

		return $this;
	}

	/**
	 * get_result
	 *
	 * @return string
	 */
	public function get_result() {
		return apply_filters( 'automator_calculation_result', $this->result, $this );
	}

}
