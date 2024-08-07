<?php

namespace Uncanny_Automator\Recipe;

use WP_Error;

/**
 * Trait Action_Parser
 *
 * @package Uncanny_Automator\Recipe
 */
trait Action_Parser {

	/**
	 * @var bool
	 */
	protected $do_shortcode = true;

	/**
	 * @var bool
	 */
	protected $wpautop = true;
	/**
	 * @var
	 */
	protected $parsed;
	/**
	 * @var array
	 */
	protected $not_token_keys = array();

	/**
	 * @return array
	 */
	public function get_not_token_keys() {
		return $this->not_token_keys;
	}

	/**
	 * @param $not_token_keys
	 */
	public function set_not_token_keys( $not_token_keys ) {
		$this->not_token_keys = $not_token_keys;
	}

	/**
	 * @return bool
	 */
	public function is_do_shortcode() {
		return $this->do_shortcode;
	}

	/**
	 * @param $do_shortcode
	 */
	public function set_do_shortcode( $do_shortcode ) {
		$this->do_shortcode = $do_shortcode;
	}

	/**
	 * @return bool
	 */
	public function is_wpautop() {
		return apply_filters( 'automator_mail_wpautop', $this->wpautop, $this );
	}

	/**
	 * @param $wpautop
	 */
	public function set_wpautop( $wpautop ) {
		$this->wpautop = $wpautop;
	}

	/**
	 * @return mixed
	 */
	public function get_parsed() {
		return $this->parsed;
	}

	/**
	 * @param mixed $parsed
	 */
	public function set_parsed( $meta_key, $parsed ) {
		$this->parsed[ $meta_key ] = $parsed;
	}

	/**
	 *
	 */
	protected function pre_parse() {
		$not_tokens = apply_filters(
			'automator_skip_meta_parsing_keys',
			array(
				'code',
				'integration',
				'sentence',
				'uap_action_version',
				'integration_name',
				'sentence',
				'sentence_human_readable',
			)
		);

		$this->set_not_token_keys( $not_tokens );
		$this->set_wpautop( $this->is_wpautop() );
		$this->set_do_shortcode( true );
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 *
	 * @return mixed
	 */
	public function maybe_parse_tokens( $user_id, $action_data, $recipe_id, $args ) {

		// Allows the parser to know that this action is under a loop.
		if ( isset( $action_data['loop'] ) ) {
			$args['loop'] = $action_data['loop'];
		}

		if ( ! array_key_exists( 'meta', $action_data ) ) {
			return $this->get_parsed();
		}

		$metas = $action_data['meta'];
		if ( empty( $metas ) ) {
			return $this->get_parsed();
		}

		$this->pre_parse();

		// Pass the 'should_apply_extra_formatting_property' to the $args['action_meta'] key.
		$args['action_meta']['should_apply_extra_formatting'] = $this->get_should_apply_extra_formatting();

		foreach ( $metas as $meta_key => $meta_value ) {

			$is_json_string = Automator()->utilities->is_json_string( $meta_value );

			// Parse the json string.
			if ( true === $is_json_string ) {

				// @added 5.9
				$parser_args = array(
					'recipe_id' => $recipe_id,
					'user_id'   => $user_id,
					'args'      => $args,
				);

				$json_parsed_string = $this->parse_json_string( $meta_value, $parser_args );

				// Only skip if self::parse_json_string is successful.
				if ( ! is_wp_error( $json_parsed_string ) ) {
					$this->set_parsed( $meta_key, $json_parsed_string );
					continue;
				}
			}

			// Prevents autop when text only contains a single line.
			if ( ! Automator()->utilities->has_multiple_lines( $meta_value ) ) {
				$this->set_parsed( $meta_key, Automator()->parse->text( $meta_value, $recipe_id, $user_id, $args ) );
				continue;
			}

			if ( ! $this->is_valid_token( $meta_key, $meta_value ) ) {
				$parsed = Automator()->parse->text( $meta_value, $recipe_id, $user_id, $args );
				$this->set_parsed( $meta_key, $this->should_wpautop( $parsed, $meta_key ) );
				continue;
			}

			$parsed = Automator()->parse->text( $meta_value, $recipe_id, $user_id, $args );

			$token_args = array(
				'user_id'     => $user_id,
				'action_data' => $action_data,
				'recipe_id'   => $recipe_id,
				'args'        => $args,
			);

			$parsed = apply_filters( 'automator_pre_token_parsed', $parsed, $meta_key, $token_args );

			$should_process_shortcode = apply_filters( 'automator_trait_action_parser_maybe_parse_tokens_should_process_shortcode', true, $token_args );

			if ( true === $should_process_shortcode && $this->is_do_shortcode() ) {
				$parsed = do_shortcode( $parsed );
			}

			$parsed = apply_filters( 'automator_post_token_parsed', $this->should_wpautop( $parsed, $meta_key ), $meta_key, $token_args );
			$this->set_parsed( $meta_key, $parsed );
		}

		return $this->get_parsed();
	}

	/**
	 * Parses the JSON string.
	 *
	 * Iterates through the JSON data and replace the token with an actual value individually.
	 *
	 * @todo Break the function into smaller components. Maybe create a new class.
	 *
	 * @param string $json_string
	 * @param array  $args
	 *
	 * @return WP_Error|string Returns WP_Error if there is an issue extracting or encoding the value.
	 *                         Otherwise, the JSON string containing the token values. Returned string for backwards compatibility.
	 *
	 * @since 5.9
	 */
	protected function parse_json_string( $json_string = '', $args = array() ) {

		if ( ! is_string( $json_string ) ) {
			return new WP_Error( 'automator-invalid-json', 'Invalid parameter type: $json_string must be a string.' );
		}

		if ( ! is_array( $args ) ) {
			return new WP_Error( 'automator-invalid-parameter-type', 'Invalid parameter type: $args must be an array.' );
		}

		$params = wp_parse_args(
			$args,
			array(
				'recipe_id' => null,
				'user_id'   => null,
				'args'      => array(),
			)
		);

		$extracted = (array) json_decode( $json_string, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'automator-invalid-json-string', 'Invalid JSON string: ' . json_last_error_msg() );
		}

		// Bail if there are no extracted key value pairs from JSON.
		if ( empty( $extracted ) ) {
			return new WP_Error( 'automator-json-key-value-pairs', 'JSON string does not contain any valid key-value pairs.' );
		}

		$key_value_pairs = array();

		// Replace each value one by one.
		foreach ( $extracted as $repeated_key => $repeated_key_values ) {
			// Only support array of array which is repeater.
			if ( ! is_array( $repeated_key_values ) ) {
				continue;
			}
			foreach ( $repeated_key_values as $key => $value ) {
				$key_value_pairs[ $repeated_key ][ $key ] = is_string( $value )
				? Automator()->parse->text( $value, $params['recipe_id'], $params['user_id'], $params['args'] )
				: $value;
			}
		}

		if ( empty( $key_value_pairs ) ) {
			return new WP_Error( 'automator-json-unable-to-parse', 'Unable to parse any text from value. Value must be array of array (repeater field).' );
		}

		// Then encode it.
		$encoded = wp_json_encode( $key_value_pairs );

		// Bail if there are no extracted key value pairs from JSON.
		if ( false === $encoded ) {
			return new WP_Error( 'automator-json-unable-encode-json', 'Unable to encode as JSON string.' );
		}

		return $encoded;
	}

	/**
	 * @param $parsed
	 * @param $meta_key
	 *
	 * @return mixed|string
	 */
	private function should_wpautop( $parsed, $meta_key ) {
		$is_wpautop = apply_filters( 'automator_mail_wpautop', $this->is_wpautop(), $this );
		if ( $is_wpautop && ! is_email( $parsed ) && false === $this->validate_if_email( $parsed, $meta_key ) ) {
			$parsed = wpautop( $parsed );
		}

		return $parsed;
	}

	/**
	 * @param $content
	 * @param $meta_key
	 *
	 * @return false|string|void
	 */
	private function validate_if_email( $content, $meta_key ) {
		if ( 0 === preg_match( '/EMAIL/', $meta_key ) ) {
			return false;
		}

		$list = array(
			'EMAILFROM',
			'EMAILFROMNAME',
			'EMAILTO',
			'EMAILCC',
			'EMAILBCC',
			'AFFILIATEWPACCEMAIL',
			'AFFILIATEWPPAYMENTEMAIL',
			'EDDCUSTOMER_EMAIL',
			'MCFROMEMAILADDRESS',
			'POSTCOMMENTEREMAIL',
			'POSTAUTHOREMAIL',
			'SENDREGEMAIL',
			'WPJMAPPLICATIONEMAIL',
			'WPJMRESUMEEMAIL',
			'WPJMJOBOWNEREMAIL',
			'MCFROMEMAILADDRESS',
		);

		$list = apply_filters( 'automator_ignore_wpautop_list', $list, $content, $meta_key );

		if ( in_array( $meta_key, $list, true ) ) {
			return true;
		}

		if ( is_array( $content ) ) {
			foreach ( $content as $email ) {
				return is_email( $email );
			}
		}
	}

	/**
	 * @param $meta_key
	 * @param $meta_value
	 *
	 * @return bool
	 */
	public function is_valid_token( $meta_key, $meta_value ) {

		if ( array_intersect( array( $meta_key ), $this->get_not_token_keys() ) ) {
			return false;
		}

		if ( preg_match_all( '/{{(.*)}}/', $meta_value ) || empty( $meta_value ) ) {
			return true;
		}

		return false;
	}
}
