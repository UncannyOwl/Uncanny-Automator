<?php
namespace Uncanny_Automator\Services\Recipe\Structure\Actions\Item;

use stdClass;
use Uncanny_Automator\Services\Recipe\Common;

/**
 * A class for generating tokens inside the actions item in the recipe object.
 *
 * @package Uncanny_Automator\Services\Recipe\Structure\Actions\Item
 * @since 5.0
 */
final class Tokens implements \JsonSerializable {

	use Common\Trait_JSON_Serializer;

	protected $tokens = array();

	private static $action_id = null;
	private static $action    = null;

	/**
	 * @param mixed $action
	 *
	 * @return void
	 */
	public function __construct( $action ) {

		self::$action    = $action;
		self::$action_id = $action['ID'];

	}

	/**
	 * Generates custom tokens.
	 *
	 * @return array
	 */
	private function generate_custom_tokens() {

		$tokens_collection = array();

		foreach ( self::$action['tokens'] as $action_token ) {

			$token = new stdClass();

			$token->id = sprintf(
				'ACTION_META:%d:%s:%s',
				self::$action_id,
				self::$action['meta']['code'],
				$action_token['tokenId']
			);

			$token->token_type = 'custom';
			$token->data_type  = $action_token['tokenType'];
			$token->name       = $action_token['tokenName'];

			$tokens_collection[] = $token;

		}

		return $tokens_collection;
	}

	/**
	 * @param array $fields
	 *
	 * @return array
	 */
	private function generate_field_tokens( $fields = array() ) {

		$tokens_collection = array();

		foreach ( (array) $fields as $options ) {

			foreach ( $options as $option ) {

				$relevant_tokens = $this->get_relevant_tokens( $option );

				if ( is_array( $relevant_tokens ) ) {
					$relevant_tokens = $this->generate_relevant_tokens( $relevant_tokens, $option );
					// Empty relevant tokens continue.
					if ( empty( $relevant_tokens ) ) {
						continue;
					}

					// Merge the relevant tokens with the tokens collection.
					$tokens_collection = array_merge( $tokens_collection, $relevant_tokens );
					continue;
				}

				// Generate token for field.
				$token = new stdClass();

				$token->id = sprintf(
					'ACTION_FIELD:%d:%s:%s',
					self::$action_id,
					self::$action['meta']['code'],
					isset( $option['field_code'] ) ? $option['field_code'] : ''
				);

				$token->token_type = 'field';
				$token->data_type  = 'text';
				$token->name       = isset( $option['label'] ) ? $option['label'] : '';

				$tokens_collection[] = $token;

			}
		}

		return $tokens_collection;
	}

	/**
	 * Get the relevant tokens config.
	 *
	 * @param mixed[] $option
	 *
	 * @return mixed[] - array || null
	 */
	private function get_relevant_tokens( $option ) {

		if ( ! is_array( $option ) ) {
			return null;
		}

		$relevant_tokens = $option['relevant_tokens'] ?? null;

		return is_array( $relevant_tokens ) ? $relevant_tokens : null;
	}

	/**
	 * Generate relevant tokens.
	 *
	 * @param array $relevant_tokens
	 * @param array $option
	 *
	 * @return array
	 */
	private function generate_relevant_tokens( $relevant_tokens, $option ) {

		if ( empty( $relevant_tokens ) ) {
			return array();
		}

		$tokens = array();

		// Generate tokens for each relevant token.
		foreach ( $relevant_tokens as $token_code => $token_label ) {

			$token             = new stdClass();
			$token->id         = sprintf( '%d:%s:%s', self::$action_id, $option['field_code'], $token_code );
			$token->token_type = 'relevant_tokens';
			$token->data_type  = 'text'; // @todo: Determine datatype.
			$token->name       = $token_label;

			// Add the token to the tokens collection.
			$tokens[] = $token;
		}

		return $tokens;
	}

	public function generate_loopable_tokens() {

		$action_code = self::$action['meta']['code'] ?? null;
		$action_id   = self::$action_id;

		// Bail if `action code` is falsy.
		if ( empty( $action_code ) ) {
			return array();
		}

		$action = Automator()->get_action( $action_code );

		$loopable_tokens = $action['loopable_tokens'] ?? null;

		// Bail if `loopable tokens` is falsy.
		if ( empty( $loopable_tokens ) ) {
			return array();
		}

		$tokens_collection = array();

		foreach ( $loopable_tokens as $token_class ) {

			$token = new stdClass();

			// Handle new framework.
			if ( is_string( $token_class ) ) {
				$token_class = new $token_class( absint( $action_id ) );
				$token_class->set_action( $action );
				$token_class->register_hooks( $action );
			}

			$definitions = $token_class->get_definitions();

			foreach ( $definitions as $id => $definition ) {
				$token->id            = sprintf( '%s:%s:%s:%d:%s:%s', 'TOKEN_EXTENDED', 'DATA_TOKEN_' . $id, 'ACTION_TOKEN', $action_id, $action_code, $id );
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
	 * Retrieves the Action field tokens and Action meta tokens.
	 *
	 * @param mixed[] $fields
	 *
	 * @return mixed[]
	 */
	public function get_tokens( $fields ) {

		$tokens_fields   = $this->generate_field_tokens( $fields );
		$tokens_custom   = $this->generate_custom_tokens();
		$tokens_loopable = $this->generate_loopable_tokens();

		$this->tokens = array_merge( $tokens_fields, $tokens_custom, $tokens_loopable );

		return $this->tokens;
	}

}
