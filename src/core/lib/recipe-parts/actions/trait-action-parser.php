<?php

namespace Uncanny_Automator\Recipe;

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
		if ( ! array_key_exists( 'meta', $action_data ) ) {
			return $this->get_parsed();
		}

		$metas = $action_data['meta'];
		if ( empty( $metas ) ) {
			return $this->get_parsed();
		}

		$this->pre_parse();

		foreach ( $metas as $meta_key => $meta_value ) {
			if ( ! $this->is_valid_token( $meta_key, $meta_value ) ) {
				$parsed = Automator()->parse->text( $meta_value, $recipe_id, $user_id, $args );
				$this->set_parsed( $meta_key, $this->should_wpautop( $parsed, $meta_key ) );
				continue;
			}

			$parsed     = Automator()->parse->text( $meta_value, $recipe_id, $user_id, $args );
			$token_args = array(
				'user_id'     => $user_id,
				'action_data' => $action_data,
				'recipe_id'   => $recipe_id,
				'args'        => $args,
			);

			$parsed = apply_filters( 'automator_pre_token_parsed', $parsed, $meta_key, $token_args );

			if ( $this->is_do_shortcode() ) {
				$parsed = do_shortcode( $parsed );
			}

			$parsed = apply_filters( 'automator_post_token_parsed', $this->should_wpautop( $parsed, $meta_key ), $meta_key, $token_args );
			$this->set_parsed( $meta_key, $parsed );
		}

		return $this->get_parsed();
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
