<?php

namespace Uncanny_Automator\Integrations\URL;

/**
 * Class URL_Tokens
 *
 * @package Uncanny_Automator
 */
class URL_Tokens {

	/**
	 * URL_Helpers constructor.
	 */
	public function __construct() {
		add_filter(
			'automator_maybe_trigger_url_url_has_param_logged_in_tokens',
			array( $this, 'url_params_possible_tokens' ),
			20,
			2
		);
		add_filter(
			'automator_maybe_trigger_url_url_has_param_tokens',
			array( $this, 'url_params_possible_tokens' ),
			20,
			2
		);

		/**
		 * Token parsing
		 */
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_url_tokens' ), 300, 6 );
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
	public function parse_url_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! in_array( 'URL_HAS_PARAM_LOGGED_IN', $pieces, true ) && ! in_array( 'URL_HAS_PARAM', $pieces, true ) ) {
			return $value;
		}

		// Must contain URL_PARAMETERS, else return
		if ( ! isset( $pieces[2] ) ) {
			return $value;
		}

		if ( ! preg_match( '/URL_PARAMETERS\|\d+\|KEY/', $pieces[2] ) ) {
			return $value;
		}

		// This should contain the param name
		if ( ! isset( $pieces[3] ) ) {
			return $value;
		}

		$param = $pieces[3];

		if ( automator_filter_has_var( $param, INPUT_GET ) ) {
			$value = automator_filter_input( $param, INPUT_GET );

			return $value;
		}

		if ( automator_filter_has_var( $param, INPUT_POST ) ) {
			$value = automator_filter_input( $param, INPUT_POST );

			return $value;
		}

		return '';
	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed
	 */
	public function url_params_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_meta = isset( $args['triggers_meta'] ) && isset( $args['triggers_meta']['code'] ) ? $args['triggers_meta']['code'] : null;
		if ( null === $trigger_meta ) {
			return $tokens;
		}

		// Add tokens for URL Parameter fields.
		if ( ! empty( $args['triggers_meta']['URL_PARAMETERS'] ) ) {
			$url_parameters = json_decode( $args['triggers_meta']['URL_PARAMETERS'] );
			$fields         = 1;
			if ( ! empty( $url_parameters ) ) {
				foreach ( $url_parameters as $k => $field ) {

					$param_name = sanitize_text_field( $field->PARAM_NAME ); // phpcs:ignore

					$tokens[] = array(
						'tokenId'         => sprintf( 'URL_PARAMETERS|%d|KEY:%s', $k, $param_name ),
						'tokenName'       => $param_name,
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					);
					$fields ++;
				}
			}
		}

		return $tokens;
	}
}

