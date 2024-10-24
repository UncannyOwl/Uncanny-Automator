<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Services\Loopable;

use Uncanny_Automator\Services\Loopable\Data_Integrations\Traits\Token_Loopable_Hydratable;
use Uncanny_Automator\Services\Loopable\Data_Integrations\Utils;
use Uncanny_Automator_Pro\Loops\Loop\Model\Query\Loop_Entry_Query;

/**
 * The abstract class representation for Action Loopable Tokens.
 *
 * @package Uncanny_Automator\Services\Loopable
 */
abstract class Action_Loopable_Token extends Loopable_Token {

	use Token_Loopable_Hydratable;

	/**
	 * This property refers to the action properties ('action_code', 'sentence', 'etc') which is an array. Not collection of actions.
	 *
	 * @var mixed[] The action data associated with this token.
	 */
	protected $action = array();

	/**
	 * Registers the loopable token on object creation.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->register_loopable_token();
	}

	/**
	 * Sets the action property.
	 *
	 * @param object $action
	 *
	 * @return void
	 */
	public function set_action( $action ) {
		$this->action = $action;
	}

	/**
	 * Returns the current action properties.
	 *
	 * @return mixed[]
	 */
	public function get_action() {
		return $this->action;
	}


	/**
	 * Registers hooks for the loopable token.
	 *
	 * @param mixed[] $action
	 *
	 * @return void
	 */
	public function register_hooks( $action ) {

		$this->action = $action;

		$closure = function( $tokens, $loop ) use ( $action ) {
			return $this->loop_list( $tokens, $loop, $action );
		};

		// Renders the child tokens inside recipe object.
		add_filter( 'automator_recipe_main_object_loop_tokens_items', $closure, 10, 2 );

		add_filter( 'automator_action_token_input_parser_text_field_text', array( $this, 'hydrate_loopable_parent_action_token' ), 10, 3 );

	}

	/**
	 * Hydrates the parent loopable action token.
	 *
	 * @param string $text
	 * @param mixed $args
	 * @param mixed $trigger_args
	 *
	 * @return string
	 */
	public function hydrate_loopable_parent_action_token( $text, $args, $trigger_args ) {

		// Only process loops.
		if ( ! str_contains( $text, 'TOKEN_EXTENDED' ) ) {
			return $text;
		}

		$token_parts = $this->extract_parent_token( $text );

		$loopable_type = $token_parts['token_type'] ?? '';
		$action_id     = $token_parts['action_id'] ?? 0;

		// Bail if not action token.
		if ( empty( $action_id ) || 'ACTION_TOKEN' !== $loopable_type ) {
			return $text;
		}

		$action_log_id = Automator()->db->action->get_action_log_id( $action_id, $trigger_args['recipe_log_id'] );

		$key = 'LOOPABLE_ACTION_TOKEN_' . $this->get_id();

		$value = Automator()->db->action->get_meta( $action_log_id, $key );

		if ( ! is_string( $value ) ) {
			return '';
		}

		$to_array = (array) maybe_unserialize( $value );

		if ( ! is_array( $to_array ) ) {
			return '';
		}

		// Hydrate the value.
		return wp_json_encode( $to_array, JSON_HEX_QUOT );

	}

	/**
	 * Registers the child tokens parser.
	 *
	 * @return void
	 */
	public function register_child_parser_hooks() {

		$child_tokens_parser_hook = strtolower( 'automator_token_parser_extended_data_token_children_' . $this->get_id() );

		add_filter( $child_tokens_parser_hook, array( $this, 'hydrate_children_tokens' ), 10, 4 );

	}

	/**
	 * Loops list. Renders the child tokens.
	 *
	 * @param mixed $tokens
	 * @param mixed $loop
	 * @param mixed $action
	 * @return mixed
	 */
	public function loop_list( $tokens, $loop, $action ) {

		$loop_id             = $loop->get( 'id' );
		$loopable_expression = $loop->get( 'iterable_expression' );

		$extracted_tokens = (array) json_decode( $loopable_expression['fields'] ?? '', true );

		$extracted_tokens_value = $this->extract_parent_token( $extracted_tokens['TOKEN']['value'] ?? '' );

		$action_id                 = $extracted_tokens_value['action_id'] ?? null;
		$action_code               = $extracted_tokens_value['action_code'] ?? null;
		$loopable_token_id         = $extracted_tokens_value['token_id'] ?? null;
		$current_loopable_token_id = $this->get_id();

		$loopable_token_action_code = $action['code'] ?? null;

		if ( $loopable_token_action_code !== $action_code ) {
			return $tokens; // Avoid conflict. Only display the child tokens of the specific loopable action.
		}

		if ( $loopable_token_id !== $current_loopable_token_id ) {
			return $tokens; // Avoid conflict. Only display the child tokens of the specific loopable token.
		}

		if ( empty( $action_id ) ) {
			return $tokens;
		}

		if ( 'token' !== $loopable_expression['type'] ) {
			return $tokens;
		}

		foreach ( $this->child_tokens as $id => $child_token ) {
			$tokens[] = array(
				'data_type'  => $child_token['token_type'] ?? 'text',
				'id'         => "TOKEN_EXTENDED:DATA_TOKEN_CHILDREN_{$current_loopable_token_id}:{$loop_id}:{$action_id}:{$action['code']}:{$id}",
				'name'       => $child_token['name'] ?? '',
				'token_type' => 'custom',
			);
		}

		return $tokens;

	}

	/**
	 * Extracts the token from the parent token.
	 *
	 * @param string $input
	 *
	 * @return (string|int)[]|false
	 */
	public function extract_parent_token( $input = '' ) {

		$pattern = '/\{\{(TOKEN_EXTENDED):(DATA_TOKEN_' . $this->get_id() . '):(ACTION_TOKEN):(\d+):([A-Z_]+):([A-Z_]+)\}\}/';

		if ( preg_match( $pattern, $input, $matches ) ) {
			return array(
				'signature'   => $matches[1],
				'type'        => $matches[2],
				'token_type'  => $matches[3],
				'action_id'   => (int) $matches[4],
				'action_code' => $matches[5],
				'token_id'    => $matches[6],
			);
		}

		return false; // Pattern did not match.

	}


	/**
	 * Extracts the token from the child token.
	 *
	 * @param string $input
	 *
	 * @return array
	 */
	public function extract_children_token( $input = '' ) {

		$pattern = '/\{\{TOKEN_EXTENDED:DATA_TOKEN_CHILDREN_' . $this->get_id() . ':(\d+):(\d+):([^:]+):([^}]+)\}\}/';

		$matches = array();

		preg_match_all( $pattern, $input, $matches, PREG_SET_ORDER );

		$results = array();

		foreach ( $matches as $match ) {
			$results[] = array(
				'signature'   => 'TOKEN_EXTENDED',
				'type'        => 'DATA_TOKEN_CHILDREN_' . $this->get_id(),
				'loop_id'     => (int) $match[1],
				'action_id'   => (int) $match[2],
				'action_code' => $match[3],
				'token_id'    => $match[4],
			);
		}

		if ( empty( $results ) ) {
			return array();
		}

		return $results;
	}

	/**
	 * Hydrates the tokens by replacing it with actual value coming from parent token.
	 *
	 * This context refers to the child token.
	 *
	 * @param string $field_text
	 * @param array $match
	 * @param array $args
	 * @param array $process_args
	 *
	 * @return string
	 */
	public function hydrate_children_tokens( $field_text = '', $match = array(), $args = array(), $process_args = array() ) {

		$entity_id  = $process_args['loop']['entity_id'] ?? null;
		$process_id = $process_args['loop']['loop_item']['filter_id'] ?? null;

		$extracted_tokens = $this->extract_children_token( $field_text );

		$loop_query       = new Loop_Entry_Query();
		$loop             = $loop_query->find_entry_by_process_id( $process_id );
		$tokens_reference = (array) json_decode( $loop->get_user_ids(), true );

		if ( empty( $tokens_reference ) ) {
			return $field_text;
		}

		$key_value_pairs = array();

		foreach ( (array) $extracted_tokens as $extracted_token ) {

			if ( ! isset( $extracted_token['token_id'] ) ) {
				continue;
			}

			$value = self::hydrate_loopable_node( $extracted_token, $tokens_reference, $entity_id );

			// Collect key value pairs.
			$key_value_pairs[ '{{' . implode( ':', array_values( $extracted_token ) ) . '}}' ] = Utils::convert_to_string( $value, true );

		}

		return strtr( $field_text, $key_value_pairs );

	}

	/**
	 * Sets the key value pairs to be variable as a reference.
	 *
	 * @param mixed[] &$key_value_pairs
	 * @param mixed[] $extracted_token
	 * @param mixed $value
	 *
	 * @return void
	 */
	private function set_key_value_pairs( &$key_value_pairs, $extracted_token, $value ) {
		// Construct the key value pairs.
		$key_value_pairs[ '{{' . implode( ':', array_values( $extracted_token ) ) . '}}' ] = Utils::convert_to_string( $value, true );
	}

	/**
	 * @param string $string
	 *
	 * @return int|null
	 */
	public static function extract_loopable_item_index_number( $string ) {

		if ( ! is_string( $string ) ) {
			return null;
		}

		// Check if the string matches the pattern 'loopable_item_index_'
		if ( preg_match( '/^loopable_item_index_(\d+)$/', $string, $matches ) ) {
			return (int) $matches[1]; // Return the extracted number
		}

		return null; // Return null if the pattern doesn't match

	}


	/**
	 * @param mixed $array
	 * @param mixed $dot_notation
	 * @return mixed
	 */
	public static function get_value_by_dot_notation( $array, $dot_notation ) {

		$keys = explode( '.', $dot_notation ); // Split dot notation into array keys

		foreach ( $keys as $key ) {

			if ( isset( $array[ $key ] ) ) {
				$array = $array[ $key ]; // Move deeper into the array
			}

			// XML Support.
			if ( isset( $array[0]['_loopable_xml_text'] ) ) {
				$array = $array[0]['_loopable_xml_text'];
			}

			// If the key doesn't exist, stop further processing by returning null
			if ( ! isset( $array ) ) {
				return null; // Early exit without using else
			}
		}

		return $array; // Return the final value
	}


}
