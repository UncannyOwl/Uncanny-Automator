<?php

namespace Uncanny_Automator\Integrations\URL;

/**
 * Class URL_Helpers
 *
 * @package Uncanny_Automator
 */
class URL_Helpers {
	/**
	 * @var string
	 */
	private $trigger_code;

	/**
	 * URL_Helpers constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'URL_HAS_PARAM';
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
				'description'              => esc_attr_x( 'Whether any or all of the following parameters are set in the URL', 'uncanny-automator' ),
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
					array(
						'option_code'           => 'PARAM_VALUE',
						'label'                 => esc_attr__( 'Parameter value', 'uncanny-automator' ),
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
		if ( ! isset( $trigger_data['meta']['URL_CONDITION'], $trigger_data['meta']['URL_PARAMETERS'] ) ) {
			return false;
		}

		$request_data = isset( $hook_args['request'] ) ? $hook_args['request'] : array();
		$get_params   = isset( $request_data['get'] ) ? $request_data['get'] : $_GET;
		$post_params  = isset( $request_data['post'] ) ? $request_data['post'] : $_POST;

		$condition  = $trigger_data['meta']['URL_CONDITION'];
		$parameters = (array) json_decode( $trigger_data['meta']['URL_PARAMETERS'], true );

		if ( empty( $parameters ) ) {
			return false;
		}

		$found_params = 0;

		foreach ( $parameters as $param ) {
			if ( ! isset( $param['PARAM_NAME'], $param['PARAM_VALUE'] ) ) {
				continue;
			}

			$name  = sanitize_text_field( $param['PARAM_NAME'] );
			$value = sanitize_text_field( $param['PARAM_VALUE'] );

			if ( ( isset( $get_params[ $name ] ) && $value === $get_params[ $name ] ) ||
				 ( isset( $post_params[ $name ] ) && $value === $post_params[ $name ] ) ) {
				$found_params ++;
			}
		}

		if ( '-1' === $condition ) {
			return $found_params > 0;
		}

		return 'all' === $condition && $found_params === count( $parameters );
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
				'tokenName'       => esc_attr__( 'Current URL', 'uncanny-automator' ),
				'tokenType'       => 'url',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'ALL_REPEATER_PARAMS',
				'tokenName'       => esc_attr__( 'All repeater parameters', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'ALL_POST_PARAMS',
				'tokenName'       => esc_attr__( 'All POST parameters', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'ALL_GET_PARAMS',
				'tokenName'       => esc_attr__( 'All GET parameters', 'uncanny-automator' ),
				'tokenType'       => 'text',
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

		$trigger   = array_shift( $trigger_data );
		$hook_args = isset( $trigger['hook_args'] ) ? $trigger['hook_args'] : array();

		switch ( $token_id ) {
			case 'URL':
				return $this->get_current_url();
			case 'ALL_REPEATER_PARAMS':
				return $this->get_repeater_params( $trigger );
			case 'ALL_POST_PARAMS':
				return $this->get_post_params( $hook_args );
			case 'ALL_GET_PARAMS':
				return $this->get_get_params( $hook_args );
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

		return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	/**
	 * Get repeater parameters
	 *
	 * @param $trigger
	 *
	 * @return string
	 */
	private function get_repeater_params( $trigger ) {
		if ( empty( $trigger ) ) {
			return '';
		}

		$parameters       = (array) json_decode( $trigger['meta']['URL_PARAMETERS'], true );
		$formatted_params = array();

		foreach ( $parameters as $param ) {
			if ( isset( $param['PARAM_NAME'], $param['PARAM_VALUE'] ) ) {
				$formatted_params[ $param['PARAM_NAME'] ] = $param['PARAM_VALUE'];
			}
		}

		return wp_json_encode( $formatted_params, JSON_PRETTY_PRINT );
	}

	/**
	 * Get POST parameters
	 *
	 * @param $hook_args
	 *
	 * @return string
	 */
	private function get_post_params( $hook_args ) {
		$request_data = isset( $hook_args['request'] ) ? $hook_args['request'] : array();
		$post_params  = isset( $request_data['post'] ) ? $request_data['post'] : $_POST;

		return wp_json_encode( $post_params, JSON_PRETTY_PRINT );
	}

	/**
	 * Get GET parameters
	 *
	 * @param $hook_args
	 *
	 * @return string
	 */
	private function get_get_params( $hook_args ) {
		$request_data = isset( $hook_args['request'] ) ? $hook_args['request'] : array();
		$get_params   = isset( $request_data['get'] ) ? $request_data['get'] : $_GET;

		return wp_json_encode( $get_params, JSON_PRETTY_PRINT );
	}
}

