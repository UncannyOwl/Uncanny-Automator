<?php
namespace Uncanny_Automator\Services\Recipe\Structure\Triggers\Trigger;

use stdClass;
use Uncanny_Automator\Services\Recipe\Common;

final class Tokens implements \JsonSerializable {

	use Common\Trait_JSON_Serializer;

	protected $tokens = array();

	/**
	 * @var int Defaults to null.
	 */
	private static $trigger_id = null;

	/**
	 * @var mixed[] $trigger Defaults to null.
	 */
	private static $trigger = null;

	/**
	 * @param mixed $trigger
	 *
	 * @return void
	 */
	public function __construct( $trigger ) {

		self::$trigger    = $trigger;
		self::$trigger_id = $trigger['ID'];

	}

	/**
	 * Generates custom tokens.
	 *
	 * @return array
	 */
	private function generate_custom_tokens() {

		$tokens_collection = array();

		$trigger_tokens = self::$trigger['tokens'] ?? array();

		foreach ( (array) $trigger_tokens as $trigger_token ) {

			$token = new stdClass();

			$token->id         = sprintf( '%d:%s:%s', self::$trigger_id, $trigger_token['tokenIdentifier'], $trigger_token['tokenId'] );
			$token->token_type = 'custom';
			$token->data_type  = $trigger_token['tokenType'];
			$token->name       = $trigger_token['tokenName'];

			$tokens_collection[] = $token;
		}

		return $tokens_collection;
	}

	/**
	 * Generate field tokens. These tokens are relevant_tokens.
	 *
	 * @param mixed[] $fields_options.
	 *
	 * @return mixed[]
	 */
	private function generate_field_tokens( $fields_options ) {

		$relevant_tokens_list = array();

		foreach ( $fields_options as $options ) {
			foreach ( $options as $option ) {

				// Check for nested fields.
				if ( ! isset( $option['field_code'] ) && is_array( $option ) ) {

					foreach ( $option as $_option ) {

						// If there is relevant_tokens attributes, add them.
						if ( isset( $_option['relevant_tokens'] ) ) {
							$relevant_tokens = $this->generate_relevant_tokens( $_option['relevant_tokens'] );
							if ( ! empty( $relevant_tokens ) ) {
								$relevant_tokens_list = array_merge( $relevant_tokens_list, $relevant_tokens );
							}
							continue;
						}

						// If there is no relevant_tokens attributes, add the field as token.
						$token                  = new stdClass();
						$token->id              = sprintf( '%d:%s:%s', self::$trigger_id, self::$trigger['meta']['code'], $_option['field_code'] );
						$token->token_type      = 'field';
						$token->data_type       = 'text'; // @todo: Determine datatype
						$token->name            = $_option['label'];
						$relevant_tokens_list[] = $token;
					}

					continue;
				}

				// If there is relevant_tokens attributes, add it.
				if ( isset( $option['relevant_tokens'] ) ) {
					$relevant_tokens = $this->generate_relevant_tokens( $option['relevant_tokens'] );
					if ( ! empty( $relevant_tokens ) ) {
						$relevant_tokens_list = array_merge( $relevant_tokens_list, $relevant_tokens );
					}
					continue;
				}

				// Add each field as token.
				$token                  = new stdClass();
				$token->id              = sprintf( '%d:%s:%s', self::$trigger_id, self::$trigger['meta']['code'], $option['field_code'] );
				$token->token_type      = 'field';
				$token->data_type       = 'text'; // @todo: Determine datatype.
				$token->name            = $option['label'];
				$relevant_tokens_list[] = $token;

			}
		}

		return $relevant_tokens_list;
	}

	/**
	 * Generate relevant tokens.
	 *
	 * @param mixed[] $tokens
	 *
	 * @return array
	 */
	public function generate_relevant_tokens( $tokens ) {

		$relevant_tokens_list = array();

		if ( ! is_array( $tokens ) || empty( $tokens ) ) {
			return $relevant_tokens_list;
		}

		foreach ( $tokens as $token_code => $token_label ) {
			$token                  = new stdClass();
			$token->id              = sprintf( '%d:%s:%s', self::$trigger_id, self::$trigger['meta']['code'], $token_code );
			$token->token_type      = 'relevant_tokens';
			$token->data_type       = 'text'; // @todo: Determine datatype.
			$token->name            = $token_label;
			$relevant_tokens_list[] = $token;
		}

		return $relevant_tokens_list;
	}

	/**
	 * The loopable tokens.
	 *
	 * @return stdClass[]
	 */
	public function generate_loopable_tokens() {

		$loopable_tokens = Automator()->get_trigger( self::$trigger['meta']['code'] )['loopable_tokens'] ?? array();

		$tokens_collection = array();

		foreach ( $loopable_tokens as $token_class ) {

			$token = new stdClass();

			if ( is_string( $token_class ) && class_exists( $token_class ) ) {
				$token_class = new $token_class( self::$trigger_id );
				$_trigger    = Automator()->get_trigger( self::$trigger['meta']['code'] );
				$token_class->register_hooks( $_trigger );
				$token_class->set_trigger( $_trigger );
			}

			$definitions = $token_class->get_definitions();

			foreach ( $definitions as $id => $definition ) {
				$token->id            = sprintf( '%s:%s:%d:%s:%s', 'TOKEN_EXTENDED', 'DATA_TOKEN_' . $id, self::$trigger_id, self::$trigger['meta']['code'], $id );
				$token->token_type    = 'loopable';
				$token->data_type     = 'json';
				$token->name          = $definition['name'] ?? '';
				$token->log_identifer = $token_class->get_log_identifier();
			}

			$tokens_collection[] = $token;

		}

		return $tokens_collection;

	}


	/**
	 * @param mixed[] $fields
	 *
	 * @return mixed[]
	 */
	public function get_tokens( $fields ) {

		$tokens_fields   = $this->generate_field_tokens( $fields );
		$tokens_custom   = $this->generate_custom_tokens();
		$loopable_tokens = $this->generate_loopable_tokens();

		$this->tokens = array_merge( $tokens_fields, $tokens_custom, $loopable_tokens );

		return $this->tokens;
	}

}
