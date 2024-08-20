<?php
namespace Uncanny_Automator\Services\Loopable;

use Uncanny_Automator_Pro\Loops\Loop\Entity_Factory;

/**
 * The abstract class representation for Trigger Iterabe Tokens.
 *
 * @package Uncanny_Automator\Services\Loopable
 */
abstract class Trigger_Loopable_Token extends Loopable_Token {

	/**
	 * This property refers to the trigger properties ('trigger_code', 'sentence', 'etc') which is an array. Not collection of triggers.
	 *
	 * @var mixed[] The trigger data associated with this token.
	 */
	protected $trigger = array();

	/**
	 * Registers the loopable token on object creation.
	 *
	 * @return void
	 */
	final public function __construct() {
		$this->register_loopable_token();
	}

	/**
	 * Returns the current trigger properties.
	 *
	 * @return mixed[]
	 */
	public function get_trigger() {
		return $this->trigger;
	}

	/**
	 * Registers hooks for the loopable token.
	 *
	 * @param mixed[] $trigger
	 *
	 * @return void
	 */
	public function register_hooks( $trigger ) {

		$this->trigger = $trigger;

		$closure = function( $tokens, $loop ) use ( $trigger ) {
			return $this->loop_list( $tokens, $loop, $trigger );
		};

		$tokens_parser_hook       = strtolower( 'automator_token_parser_extended_data_token_' . $this->get_id() );
		$child_tokens_parser_hook = strtolower( 'automator_token_parser_extended_data_token_children_' . $this->get_id() );

		add_filter( $tokens_parser_hook, array( $this, 'hydrate_tokens' ), 10, 4 );
		add_filter( $child_tokens_parser_hook, array( $this, 'hydrate_tokens_children' ), 10, 4 );

		add_filter( 'automator_recipe_main_object_loop_tokens_items', $closure, 10, 2 );
		add_action( 'automator_loopable_token_hydrate', array( $this, 'on_before_trigger_complete' ), 10, 2 );

	}

	/**
	 * Hydrates the tokens by retrieving the value saved in the trigger meta.
	 *
	 * This context refers to the parent token.
	 *
	 * @param string $field_text
	 * @param string $match
	 * @param mixed[] $args
	 * @param mixed[] $process_args
	 *
	 * @return string - The JSON string of the loopable token.
	 */
	public function hydrate_tokens( $field_text = '', $match = '', $args = array(), $process_args = array() ) {

		// Bail if its universal to avoid conflict.
		if ( strpos( $match, ':UNIVERSAL:' ) ) {
			return $field_text;
		}

		$field_text = Automator()->db->token->get( 'LOOPABLE_' . $this->get_id(), $process_args );

		return $field_text;

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
	public function hydrate_tokens_children( $field_text = '', $match = array(), $args = array(), $process_args = array() ) {

		$entity_id = $process_args['loop']['entity_id'] ?? null;

		$extracted_tokens = $this->extract_token_children( $field_text );

		$tokens_reference = json_decode( Automator()->db->token->get( 'LOOPABLE_' . $this->get_id(), $process_args ), true );

		if ( empty( $tokens_reference ) ) {
			return $field_text;
		}

		$key_value_pairs = array();

		foreach ( $extracted_tokens as $extracted_token ) {

			if ( isset( $extracted_token['token_id'] ) ) {

				$value = $tokens_reference[ $entity_id ][ $extracted_token['token_id'] ] ?? '';

				// Construct the key value pairs.
				$key_value_pairs[ '{{' . implode( ':', array_values( $extracted_token ) ) . '}}' ] = $value;

			}
		}

		return strtr( $field_text, $key_value_pairs );

	}

	/**
	 * Callback method on 'automator_loopable_token_hydrate' action hook.
	 *
	 * @param array $result_args
	 * @param array $trigger_args
	 *
	 * @return bool|int|null
	 */
	public function on_before_trigger_complete( $result_args, $trigger_args ) {

		$parameters_reference = array(
			'trigger_args' => $trigger_args,
			'result_args'  => $result_args,
		);

		// We're using closure because we want to easily pass arguments.
		$closure = function( $args ) use ( $parameters_reference ) {
			$this->handle_token_trigger_log_meta_entry( $args, $parameters_reference );
		};

		// Send the hydration just before the loop is queued.
		add_action( 'automator_pro_before_loop_is_queued', $closure, 10, 1 );

	}

	/**
	 * Handles the insertion of the trigger loopable token.
	 *
	 * @param mixed[] $args The process args.
	 * @param array{trigger_args:mixed[],result_args:mixed[]} $reference
	 *
	 * @return bool|int|null|void
	 */
	public function handle_token_trigger_log_meta_entry( $args, $reference ) {

		$fields                 = $args['iterable_expression']['fields'] ?? '';
		$loop_type              = $args['iterable_expression']['type'] ?? '';
		$extracted_tokens       = (array) json_decode( $fields, true );
		$extracted_tokens_value = $this->extract_token( $extracted_tokens['TOKEN']['value'] ?? '' );

		$loopable_token_in_used = $extracted_tokens_value['token_id'] ?? '';

		// Bail if the token type is not 'token'.
		if ( 'token' !== $loop_type ) {
			return;
		}

		if ( $this->get_id() === $loopable_token_in_used ) {
			return $this->save_trigger_loopable_token_data( $reference['trigger_args'], $reference['result_args'] );
		}
	}

	/**
	 * Save the loopable trigger token data.
	 *
	 * @param mixed[] $trigger_args
	 * @param mixed[] $result_args
	 *
	 * @return bool|int|null|void
	 */
	public function save_trigger_loopable_token_data( $trigger_args, $result_args ) {

		// Bail if any of the required trigger codes is empty.
		// The variable or property $this->trigger is set during the registration.
		if ( ! isset( $this->trigger['code'] ) || ! $result_args['code'] ) {
			return;
		}

		// Bail if current trigger is not equals the current trigger being processed.
		if ( $this->trigger['code'] !== $result_args['code'] ) {
			return;
		}

		$loopable = $this->hydrate_token_loopable( $trigger_args );

		$user_id        = $result_args['user_id'] ?? '';
		$trigger_id     = $result_args['trigger_id'] ?? '';
		$run_number     = $result_args['run_number'] ?? '';
		$trigger_log_id = $result_args['trigger_log_id'] ?? '';

		$args = array(
			'user_id'        => $user_id,
			'trigger_id'     => $trigger_id,
			'meta_key'       => 'LOOPABLE_' . $this->get_id(),
			'meta_value'     => wp_json_encode( $loopable, true ),
			'run_number'     => $run_number,
			'trigger_log_id' => $trigger_log_id,
		);

		return Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args );

	}

	/**
	 * Hydrates token loopables based on trigger arguments.
	 *
	 * This abstract method defines the structure for generating a sequence of tokens.
	 *
	 * @param mixed $trigger_args Arguments used to trigger the hydration process and determine the token generation logic.
	 *
	 * @return Loopable_Token_Collection Contains individual tokens as they are hydrated.
	 */
	abstract public function hydrate_token_loopable( $trigger_args );

	/**
	 * Sets the trigger property.
	 *
	 * @param object $trigger
	 *
	 * @return void
	 */
	public function set_trigger( $trigger ) {
		$this->trigger = $trigger;
	}

	/**
	 * Loops list.
	 *
	 * @param mixed $tokens
	 * @param mixed $loop
	 * @param mixed $trigger
	 * @return mixed
	 */
	public function loop_list( $tokens, $loop, $trigger ) {

		$loop_id             = $loop->get( 'id' );
		$loopable_expression = $loop->get( 'iterable_expression' );

		$extracted_tokens = (array) json_decode( $loopable_expression['fields'] ?? '', true );

		$extracted_tokens_value = $this->extract_token( $extracted_tokens['TOKEN']['value'] ?? '' );

		$trigger_id                = $extracted_tokens_value['trigger_id'] ?? null;
		$trigger_code              = $extracted_tokens_value['trigger_code'] ?? null;
		$loopable_token_id         = $extracted_tokens_value['token_id'] ?? null;
		$current_loopable_token_id = $this->get_id();

		$loopable_token_trigger_code = $trigger['code'] ?? null;

		if ( $loopable_token_trigger_code !== $trigger_code ) {
			return $tokens; // Avoid conflict. Only display the child tokens of the specific loopable trigger.
		}

		if ( $loopable_token_id !== $current_loopable_token_id ) {
			return $tokens; // Avoid conflict. Only display the child tokens of the specific loopable token.
		}

		if ( empty( $trigger_id ) ) {
			return $tokens;
		}

		if ( 'token' !== $loopable_expression['type'] ) {
			return $tokens;
		}

		foreach ( $this->child_tokens as $id => $child_token ) {

			$tokens[] = array(
				'data_type'  => $child_token['token_type'] ?? 'text',
				'id'         => "TOKEN_EXTENDED:DATA_TOKEN_CHILDREN_{$current_loopable_token_id}:{$loop_id}:{$trigger_id}:{$trigger['code']}:{$id}",
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
	public function extract_token( $input = '' ) {

		$pattern = '/\{\{(TOKEN_EXTENDED):(DATA_TOKEN_' . $this->get_id() . '):(\d+):([A-Z_]+):([A-Z_]+)\}\}/';

		if ( preg_match( $pattern, $input, $matches ) ) {
			return array(
				'signature'    => $matches[1],
				'type'         => $matches[2],
				'trigger_id'   => (int) $matches[3],
				'trigger_code' => $matches[4],
				'token_id'     => $matches[5],
			);
		}

		return false; // Pattern did not match
	}


	/**
	 * Extracts the token from the child token.
	 *
	 * @param string $input
	 *
	 * @return array
	 */
	public function extract_token_children( $input = '' ) {

		$pattern = '/{{TOKEN_EXTENDED:DATA_TOKEN_CHILDREN_' . $this->get_id() . ':(\d+):(\d+):(\w+):(\w+)}}/';
		$matches = array();

		preg_match_all( $pattern, $input, $matches, PREG_SET_ORDER );

		$results = array();

		foreach ( $matches as $match ) {
			$results[] = array(
				'signature'    => 'TOKEN_EXTENDED',
				'type'         => 'DATA_TOKEN_CHILDREN_' . $this->get_id(),
				'loop_id'      => (int) $match[1],
				'trigger_id'   => (int) $match[2],
				'trigger_code' => $match[3],
				'token_id'     => $match[4],
			);
		}

		if ( empty( $results ) ) {
			return array();
		}

		return $results;
	}

}
