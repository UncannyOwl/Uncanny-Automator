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

		foreach ( self::$trigger['tokens'] as $trigger_token ) {

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
				if ( isset( $option['relevant_tokens'] ) ) {
					// If there is relevant_tokens attributes, add it.
					foreach ( $option['relevant_tokens'] as $token_code => $token_label ) {
						$datatype               = 'text'; // @todo: Determine datatype.
						$token                  = new stdClass();
						$token->id              = sprintf( '%d:%s:%s', self::$trigger_id, $option['field_code'], $token_code );
						$token->token_type      = 'relevant_tokens';
						$token->data_type       = $datatype;
						$token->name            = $token_label;
						$relevant_tokens_list[] = $token;
					}
				} else {
					if ( ! isset( $option['field_code'] ) && is_array( $option ) ) {
						foreach ( $option as $_option ) {
							$datatype               = 'text'; // @todo: Determine datatype.
							$token                  = new stdClass();
							$token->id              = sprintf( '%d:%s:%s', self::$trigger_id, self::$trigger['meta']['code'], $_option['field_code'] );
							$token->token_type      = 'field';
							$token->data_type       = $datatype;
							$token->name            = $_option['label'];
							$relevant_tokens_list[] = $token;
						}
					} else {
						$datatype               = 'text'; // @todo: Determine datatype.
						$token                  = new stdClass();
						$token->id              = sprintf( '%d:%s:%s', self::$trigger_id, self::$trigger['meta']['code'], $option['field_code'] );
						$token->token_type      = 'field';
						$token->data_type       = $datatype;
						$token->name            = $option['label'];
						$relevant_tokens_list[] = $token;
					}
					// Add each field as token.

				}
			}
		}

		return $relevant_tokens_list;

	}


	/**
	 * @param mixed[] $fields
	 *
	 * @return mixed[]
	 */
	public function get_tokens( $fields ) {

		$tokens_fields = $this->generate_field_tokens( $fields );

		$tokens_custom = $this->generate_custom_tokens();

		$this->tokens = array_merge( $tokens_fields, $tokens_custom );

		return $this->tokens;
	}

}
