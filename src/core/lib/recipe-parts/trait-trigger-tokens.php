<?php
namespace Uncanny_Automator\Recipe;

use Uncanny_Automator\Automator_Exception;

/**
 * Trait Trigger Tokens.
 *
 * @package Uncanny_Automator\Recipe\Trigger_Tokens
 * @since 4.3
 * @version 1.0.3
 */
trait Trigger_Tokens {

	/**
	 * The trigger tokens.
	 *
	 * @var array
	 */
	protected $trigger_tokens = array();

	/**
	 * The tokens.
	 *
	 * @var array
	 */
	private $tokens = array();

	/**
	 * The triggers where the tokens is attached into.
	 *
	 * @var array
	 */
	protected $triggers = array();

	/**
	 * The token values from hydrate.
	 *
	 * @var array
	 */
	protected $values = array();

	/**
	 * Tokens renderable is a collection of qualified tokens from proposed tokens.
	 *
	 * @see $token_properties
	 *
	 * @var array $tokens_renderable;
	 */
	protected $tokens_renderable = array();

	/**
	 * Essential token properties.
	 *
	 * @var array $token_properties.
	 */
	protected $token_properties = array( 'tokenId', 'tokenName', 'tokenType', 'hydrateWith' );

	/**
	 * Add current trigger token data to Automator token registry.
	 *
	 * @filter automator_maybe_trigger_{{integration}}_{{code}}_tokens
	 *
	 * @return void
	 */
	protected function add_trigger_tokens_filter( $trigger_code = '', $integration = '' ) {

		// Integrations uses `$this->set_tokens()` to create tokens.
		// Bail if the tokens are not set, or if they are empty.
		if ( empty( $this->get_tokens() ) ) {
			return;
		}

		$filter = strtr(
			'automator_maybe_trigger_{{integration}}_{{code}}_tokens',
			array(
				'{{integration}}' => strtolower( $integration ),
				'{{code}}'        => strtolower( $trigger_code ),
			)
		);

		$callback_closure = function ( $tokens, $args ) use ( $trigger_code ) {

			if ( $this->has_token_renderable( $trigger_code ) ) {
				return $this->get_tokens_renderable( $trigger_code );
			}

			// Allow modification of renderable tokens before setting.
			// @since 4.5
			$tokens = apply_filters( 'automator_token_renderable_before_set_' . strtolower( $trigger_code ), $this->get_tokens(), $trigger_code, $tokens, $args );

			// Otherwise, set the renderable tokens.
			$this->set_tokens_renderable( $tokens, $trigger_code );

			return $this->get_tokens_renderable( $trigger_code );

		};

		// Add some filter to append the tokens.
		add_filter( $filter, $callback_closure, 99, 2 );

	}

	/**
	 * Attach the methods to various action hook/filter.
	 *
	 * @return void
	 */
	protected function enqueue_token_action_and_filter() {

		// Save token data when `automator_before_trigger_completed`.
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );

		$filter = strtr(
			'automator_parse_token_for_trigger_{{integration}}_{{trigger_code}}',
			array(
				'{{integration}}'  => strtolower( $this->get_integration() ),
				'{{trigger_code}}' => strtolower( $this->get_trigger_code() ),
			)
		);

		// Get the token value when `automator_parse_token_for_trigger_{{integration}}_{{trigger_code}}`.
		add_filter( $filter, array( $this, 'fetch_token_data' ), 20, 6 );

	}

	/**
	 * Allow integrations to set their own tokens.
	 *
	 * @param array $tokens
	 *
	 * @return void
	 */
	public function set_tokens( $tokens = array() ) {

		$this->tokens = $tokens;

	}

	/**
	 * Get the tokens.
	 *
	 * @return array
	 */
	protected function get_tokens() {

		return $this->tokens;

	}

	/**
	 * Set trigger tokens.
	 *
	 * @param array $trigger_tokens
	 */
	public function set_trigger_tokens( $trigger_tokens ) {

		$this->trigger_tokens = $trigger_tokens;

	}

	/**
	 * Get the trigger tokens.
	 *
	 * @return array
	 */
	public function get_trigger_tokens() {

		return $this->trigger_tokens;

	}

	/**
	 * Hydrate the token, e.i add values to the tokens.
	 *
	 * @param $args
	 * @param $trigger
	 *
	 * @return mixed
	 */
	public function hydrate_tokens( $args, $trigger ) {

		$parsed = array();

		foreach ( $this->get_tokens() as $token_id => $token ) {

			if ( isset( $token['hydrate_with'] ) ) {

				$hydrate_with = $token['hydrate_with'];

				if ( is_array( $hydrate_with ) ) {

					$token['id'] = $token_id;

					$parsed[ $token_id ] = call_user_func( $hydrate_with, $token, $args, $trigger );

				} else {

					$pieces = explode( '|', $hydrate_with );

					$parsed[ $token_id ] = isset( $args[ $pieces[0] ][ $pieces[1] ] ) ? $args[ $pieces[0] ][ $pieces[1] ] : 'not matched';

				}
			}
		}

		return $this->parse_additional_tokens( $parsed, $args, $trigger );

	}

	/**
	 * Method parse_additional_tokens.
	 *
	 * @param $parsed
	 * @param $args
	 * @param $trigger
	 *
	 * @return mixed
	 */
	public function parse_additional_tokens( $parsed, $args, $trigger ) {
		return $parsed;
	}


	/**
	 * Create a single token.
	 *
	 * @param $trigger_code
	 * @param $args
	 *
	 * @return array
	 */
	protected function create_token( $trigger_code = '', $args = array() ) {

		$defaults = array(
			'tokenId'         => '',
			'tokenName'       => '',
			'tokenType'       => 'text',
			'tokenIdentifier' => $trigger_code,
			'hydrateWith'     => '',
		);

		return wp_parse_args( $args, $defaults );

	}

	/**
	 * Method hydrate_token.
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return void
	 */
	public function hydrate_token( $key = '', $value = null ) {

		$this->values[ $key ] = $value;

	}

	/**
	 * Method get_values.
	 *
	 * @return array
	 */
	public function get_values() {

		return $this->values;

	}

	/**
	 * Set up renderable tokens.
	 *
	 * @param array $tokens
	 * @param string $trigger_code
	 *
	 * @return array|\WP_Error
	 */
	public function set_tokens_renderable( $tokens = array(), $trigger_code = '' ) {

		if ( empty( $trigger_code ) ) {
			return new \WP_Error( 'automator_trait_trigger_token_error', 'Trying to register a token to with an empty trigger code' );
		}

		if ( empty( $tokens ) ) {
			return new \WP_Error( 'automator_trait_trigger_token_error', 'Trying to register a token to with an empty array' );
		}

		foreach ( $tokens as $id => $props ) {

			$token_validation = $this->validate_token( $id, $props );

			if ( is_wp_error( $token_validation ) ) {
				_doing_it_wrong( 'Trigger_Tokens::register_tokens', esc_html( $token_validation->get_error_message() ), '4.3' );
				continue;
			}

			// Create the token. We can move token as an object with its own props and method in the future.
			// For now, let just use plain and simple arrays.
			$token = $this->create_token(
				$trigger_code,
				array(
					'tokenId'     => $id,
					'tokenName'   => isset( $props['name'] ) ? $props['name'] : '',
					'tokenType'   => isset( $props['type'] ) ? $props['type'] : 'text',
					'hydrateWith' => isset( $props['hydrate_with'] ) ? $props['hydrate_with'] : '',
				)
			);

			$this->add_token_renderable( $trigger_code, $token );

		}

	}

	public function add_token_renderable( $trigger_code, $token = array() ) {

		// Check if proposed tokens has all required properties.
		if ( count( array_intersect_key( array_flip( $this->token_properties ), $token ) ) === count( $this->token_properties ) ) {
			$this->tokens_renderable[ $trigger_code ][] = $token;
		}

	}

	public function get_tokens_renderable( $trigger_code = '' ) {

		if ( ! empty( $this->tokens_renderable[ $trigger_code ] ) ) {

			return $this->tokens_renderable[ $trigger_code ];
		}

		return array();

	}

	public function has_token_renderable( $trigger_code = '' ) {

		return isset( $this->tokens_renderable[ $trigger_code ] );

	}

	/**
	 * Validates the argument pass to the register tokens.
	 *
	 * @return boolean|\WP_Error True if token data is valid. Otherwise, \WP_Error object
	 */
	public function validate_token( ...$args ) {

		foreach ( $args as $arg ) {

			if ( is_array( $arg ) ) {

				foreach ( $arg as $key => $val ) {

					if ( empty( $key ) ) {
						return new \WP_Error( 'broke', 'Trying to register a token with an empty key.' );
					}

					if ( empty( $val ) ) {
						return new \WP_Error( 'broke', 'Trying to register a token with an empty property.' );
					}
				}
			}

			if ( empty( $arg ) ) {
				return new \WP_Error( 'broke', 'Trying to register a token with an empty ID.' );
			}
		}

		return true;
	}


	/**
	 * Saves token data.
	 *
	 * @param $args
	 * @param $trigger
	 *
	 * @return \Automator_DB_Handler_Triggers::add_token_meta
	 */
	public function save_token_data( $args, $trigger ) {

		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		// For good measure.
		if ( ! empty( $args['entry_args']['code'] ) && $this->get_trigger_code() !== $args['entry_args']['code'] ) {
			return;
		}

		$hydrated_tokens = $this->hydrate_tokens( $args, $trigger );

		foreach ( $hydrated_tokens as $id => $value ) {

			$this->hydrate_token( $id, $value );

		}

		return Automator()->db->token->save( $trigger->get_trigger_code(), wp_json_encode( $this->get_values() ), $args['trigger_entry'] );

	}

	/**
	 * Fetches specific token value from uap_trigger_log_meta.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_arg
	 *
	 * @return mixed
	 */
	public function fetch_token_data( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_arg ) {

		if ( empty( $trigger_data ) || ! isset( $trigger_data[0] ) ) {
			return $value;
		}

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		// For brevity.
		list( $recipe_id, $token_identifier, $token_id ) = $pieces;

		$data = Automator()->db->token->get( $token_identifier, $replace_arg );
		$data = is_array( $data ) ? $data : json_decode( $data, true );

		if ( isset( $data[ $token_id ] ) ) {
			return $data[ $token_id ];
		}

		return $value;

	}

}
