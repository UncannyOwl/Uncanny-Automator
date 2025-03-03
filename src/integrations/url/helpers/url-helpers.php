<?php

namespace Uncanny_Automator\Integrations\URL;

/**
 * Class URL_Helpers
 *
 * @package Uncanny_Automator
 */
class URL_Helpers {

	/**
	 * URL_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * URL_HAS_PARAM & URL_HAS_PARAM_LOGGED_IN options
	 *
	 * @param $trigger_code
	 *
	 * @return array
	 */
	public function url_has_param_get_options( $trigger_code ) {
		return array(
			array(
				'option_code'              => 'URL_CONDITION',
				'label'                    => esc_attr_x( 'Condition', 'URL', 'uncanny-automator' ),
				'description'              => esc_attr_x( 'Whether any or all of the following parameters are set in the URL', 'URL', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'options_show_id'          => false,
				'supports_multiple_values' => false,
				'supports_custom_value'    => false,
				'relevant_tokens'          => array(),
				'options'                  => array(
					array(
						'value' => '-1',
						'text'  => esc_attr__( 'Any', 'uncanny-automator' ),
					),
					array(
						'value' => 'all',
						'text'  => esc_attr__( 'All', 'uncanny-automator' ),
					),
				),
			),
			array(
				'option_code'       => 'URL_PARAMETERS',
				'label'             => esc_attr__( 'Parameters', 'uncanny-automator' ),
				'input_type'        => 'repeater',
				'required'          => true,
				'relevant_tokens'   => array(),
				'fields'            => array(
					array(
						'option_code'           => 'PARAM_NAME',
						'label'                 => esc_attr__( 'Parameter name', 'uncanny-automator' ),
						'input_type'            => 'text',
						'required'              => true,
						'supports_tokens'       => true,
						'supports_custom_value' => true,
					),
				),
				'add_row_button'    => esc_attr__( 'Add parameter', 'uncanny-automator' ),
				'remove_row_button' => esc_attr__( 'Remove parameter', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * URL_HAS_PARAM & URL_HAS_PARAM_LOGGED_IN validation
	 *
	 * @param $trigger_data
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function url_has_param_validate_trigger( $trigger_data, $hook_args ) {
		if ( empty( $trigger_data['meta']['URL_CONDITION'] ) || empty( $trigger_data['meta']['URL_PARAMETERS'] ) ) {
			return false;
		}

		// Get request data, fallback to GET and POST superglobals securely
		$request_data = $hook_args['request'] ?? array();
		$get_params   = $request_data['get'] ?? filter_input_array( INPUT_GET, FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? array();
		$post_params  = $request_data['post'] ?? filter_input_array( INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?? array();

		$condition  = sanitize_text_field( $trigger_data['meta']['URL_CONDITION'] );
		$parameters = json_decode( $trigger_data['meta']['URL_PARAMETERS'], true ) ?? array();

		if ( empty( $parameters ) || false === is_array( $parameters ) ) {
			return false;
		}

		$found_params = 0;

		foreach ( $parameters as $param ) {
			$name = sanitize_text_field( $param['PARAM_NAME'] ?? '' );

			if ( '' !== $name && ( isset( $get_params[ $name ] ) || isset( $post_params[ $name ] ) ) ) {
				++ $found_params;
			}
		}

		return '-1' === $condition ? 0 < $found_params : ( 'all' === $condition && count( $parameters ) === $found_params );
	}

	/**
	 * URL_HAS_PARAM & URL_HAS_PARAM_LOGGED_IN hydrate tokens
	 *
	 * @param $trigger_data
	 * @param $hook_args
	 *
	 * @return array
	 */
	public function url_has_param_hydrate_tokens( $trigger_data, $hook_args ) {
		$trigger = array(
			'meta'      => $trigger_data['meta'],
			'hook_args' => $hook_args,
		);

		return array(
			'URL'                 => $this->get_token_value( 'URL', array( $trigger ) ),
			'ALL_REPEATER_PARAMS' => $this->get_token_value( 'ALL_REPEATER_PARAMS', array( $trigger ) ),
			'ALL_POST_PARAMS'     => $this->get_token_value( 'ALL_POST_PARAMS', array( $trigger ) ),
			'ALL_GET_PARAMS'      => $this->get_token_value( 'ALL_GET_PARAMS', array( $trigger ) ),
		);
	}

	/**
	 * URL_HAS_PARAM & URL_HAS_PARAM_LOGGED_IN Token definitions
	 *
	 * @param $trigger_code
	 *
	 * @return array
	 */
	public function get_url_tokens( $trigger_code ) {
		return array(
			array(
				'tokenId'         => 'URL',
				'tokenName'       => esc_attr__( 'URL visited', 'uncanny-automator' ),
				'tokenType'       => 'url',
				'tokenIdentifier' => $trigger_code,
			),
		);
	}

	/**
	 * URL_HAS_PARAM & URL_HAS_PARAM_LOGGED_IN Get token value
	 *
	 * @param $token_id
	 * @param $trigger_data
	 *
	 * @return string
	 */
	public function get_token_value( $token_id, $trigger_data ) {
		if ( empty( $trigger_data ) ) {
			return '';
		}

		switch ( $token_id ) {
			case 'URL':
				return $this->get_current_url();
		}

		return '';
	}

	/**
	 * Get current URL
	 *
	 * @return string
	 */
	private function get_current_url() {
		$protocol = is_ssl() ? 'https://' : 'http://';

		// Retrieve and sanitize HTTP_HOST safely
		$host = wp_unslash( $_SERVER['HTTP_HOST'] ) ?? wp_parse_url( site_url(), PHP_URL_HOST ); //phpcs:ignore
		$host = sanitize_text_field( $host ); // Sanitize

		// Retrieve and sanitize REQUEST_URI safely
		$uri = wp_unslash( $_SERVER['REQUEST_URI'] ) ?? '/'; //phpcs:ignore
		$uri = esc_url_raw( $uri ); // Ensure safe URL

		return $protocol . $host . $uri;
	}
}

