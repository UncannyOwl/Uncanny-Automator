<?php
namespace Uncanny_Automator\Services\Loopable;

use Uncanny_Automator\Logger\Recipe_Objects_Logger;
use Uncanny_Automator_Pro\Loops\Loop\Entity_Factory;

/**
 * Abstract class for universal loopable tokens in Uncanny Automator.
 *
 * This class provides a base for loopable tokens that can be used across different integrations.
 * It handles token registration, hydration, and looping logic.
 *
 * @since 5.10
 *
 * @package Uncanny_Automator\Services\Loopable\Universal_Loopable_Token
 */
abstract class Universal_Loopable_Token extends Loopable_Token {

	/**
	 * @var bool Whether this token requires a user.
	 */
	protected $requires_user = true;

	/**
	 * Initializes the token.
	 *
	 * @param string $integration The integration ID.
	 */
	final public function __construct( $integration ) {
		$this->set_integration( $integration );
		$this->register_loopable_token();
	}

	/**
	 * Sets if this token requires a user.
	 *
	 * @param bool $requires_user True if user is needed, false otherwise.
	 */
	public function set_requires_user( $requires_user ) {
		$this->requires_user = $requires_user;
	}

	/**
	 * Gets whether this token requires a user.
	 *
	 * @return bool True if user is needed, false otherwise.
	 */
	public function get_requires_user() {
		return $this->requires_user;
	}

	/**
	 * Registers WordPress hookss for this token.
	 */
	public function register_hooks() {

		$integration = $this->get_integration();

		add_filter( 'automator_integration_items', array( $this, 'register_token' ), 90, 2 );

		$tokens_parser_hook       = strtolower( 'automator_token_parser_extended_data_token_' . $this->get_id() );
		$child_tokens_parser_hook = strtolower( 'automator_token_parser_extended_data_token_children_' . $this->get_id() );

		add_filter( $tokens_parser_hook, array( $this, 'hydrate_tokens' ), 10, 4 );
		add_filter( $child_tokens_parser_hook, array( $this, 'hydrate_tokens_children' ), 10, 4 );

		$closure = function( $tokens, $loop ) use ( $integration ) {
			return $this->loop_list( $tokens, $loop, $integration );
		};

		add_filter( 'automator_recipe_main_object_loop_tokens_items', $closure, 10, 2 );
		add_action( 'automator_pro_before_loop_is_queued', array( $this, 'on_before_loop_is_queued' ), 10, 1 );

	}

	/**
	 * Registers this token with Uncanny Automator.
	 *
	 * @param array $items Existing items.
	 * @param Uncanny_Automator\Services\Integrations\Structure $structure Integration structure.
	 *
	 * @return array Modified items.
	 */
	public function register_token( $items, $structure ) {

		$id          = $this->get_id();
		$integration = $this->get_integration();

		if ( true === $items[ $this->get_integration() ]['is_available'] ) {

			$items[ $this->get_integration() ]['tokens'][] = array(
				'id'             => "TOKEN_EXTENDED:DATA_TOKEN_{$id}:UNIVERSAL:{$integration}:{$id}",
				'name'           => $this->get_name(),
				'cacheable'      => true,
				'requiresUser'   => $this->get_requires_user(),
				'type'           => 'loopable',
				'log_identifier' => $this->get_log_identifier(),
			);

		}

		return $items;

	}

	/**
	 * Hydrates child tokens in a field.
	 *
	 * @param string $field_text Text containing tokens.
	 * @param array $match Regex match result.
	 * @param array $args Additional arguments.
	 * @param array $process_args Process arguments.
	 *
	 * @return string Hydrated field text.
	 */
	public function hydrate_tokens_children( $field_text, $match, $args, $process_args ) {

		$entity_id = $process_args['loop']['entity_id'] ?? null;

		$extracted_tokens = $this->extract_token_children( $field_text );

		$recipe_objects_logger = new Recipe_Objects_Logger();

		// Retrieve the parent token value.
		$token_value = $recipe_objects_logger->get_meta( $process_args, 'LOOPABLE_' . $this->get_id() );

		$tokens_reference = json_decode( $token_value ?? '', true );

		if ( empty( $tokens_reference ) ) {
			return $field_text;
		}

		$key_value_pairs = array();

		foreach ( $extracted_tokens as $extracted_token ) {

			if ( isset( $extracted_token['token_id'] ) ) {

				$value = $tokens_reference[ $entity_id ][ $extracted_token['token_id'] ] ?? '';

				$key_value_pairs[ '{{' . implode( ':', array_values( $extracted_token ) ) . '}}' ] = $value;

			}
		}

		return strtr( $field_text, $key_value_pairs );

	}

	/**
	 * Saves loopable data after triggers complete.
	 *
	 * @param array $args Recipe arguments.
	 *
	 * @return void
	 */
	public function on_before_loop_is_queued( $args ) {

		$fields    = $args['iterable_expression']['fields'] ?? '';
		$loop_type = $args['iterable_expression']['type'] ?? '';

		// Bail if the token type is not 'token'.
		if ( 'token' !== $loop_type ) {
			return;
		}

		// Get the loopable field value.
		$loopable_field       = json_decode( $fields, true );
		$loopable_field_value = explode( ':', $loopable_field['TOKEN']['value'] ?? '' );

		// Get the token id from field value.
		$loopable_token_id = preg_replace( '/[^A-Za-z0-9_]/', '', $loopable_field_value[4] ?? '' );

		if ( $loopable_token_id === $this->get_id() ) {
			$recipe_objects_logger = new Recipe_Objects_Logger();
			$loopable              = $this->hydrate_token_loopable( $args );
			$recipe_objects_logger->add_meta( $args, 'LOOPABLE_' . $this->get_id(), $loopable, false );
		}

	}

	/**
	 * Hydrates tokens in a field.
	 *
	 * @param string $field_text Text containing tokens.
	 * @param string $match Regex match result.
	 * @param array $args Additional arguments.
	 * @param array $process_args Process arguments.
	 *
	 * @return string|null Hydrated field text.
	 */
	public function hydrate_tokens( $field_text, $match, $args, $process_args ) {

		// Bail if its not universal to avoid conflict.
		if ( false === strpos( $match, ':UNIVERSAL:' ) ) {
			return $field_text;
		}

		$recipe_objects_logger = new Recipe_Objects_Logger();
		$field_text            = $recipe_objects_logger->get_meta( $process_args, 'LOOPABLE_' . $this->get_id() );

		return $field_text;

	}

	/**
	 * Hydrates tokens from an loopable source. (Abstract method to be implemented by subclasses)
	 *
	 * @param array $args Recipe arguments.
	 *
	 * @return Loopable_Token_Collection Yields hydrated tokens.
	 */
	abstract public function hydrate_token_loopable( $args );

	/**
	 * Loops through a list of tokens to assign to child tokens.
	 *
	 * @param array $tokens List of tokens.
	 * @param mixed $loop Loop object.
	 * @param string $integration Integration ID.
	 *
	 * @return array Modified tokens list.
	 */
	public function loop_list( $tokens, $loop, $integration ) {

		$loop_id             = $loop->get( 'id' );
		$loopable_expression = $loop->get( 'iterable_expression' );

		$extracted_tokens       = (array) json_decode( $loopable_expression['fields'] ?? '', true );
		$extracted_tokens_value = $this->extract_token( $extracted_tokens['TOKEN']['value'] ?? '' );

		$loopable_token_id         = $extracted_tokens_value['id'] ?? null;
		$current_loopable_token_id = $this->get_id();

		$token_integration = $extracted_tokens_value['integration'] ?? '';

		if ( $integration !== $token_integration ) {
			return $tokens;
		}

		if ( $loopable_token_id !== $this->get_id() ) {
			return $tokens; // Avoid conflict. Only display the child tokens of the specific loopable token.
		}

		if ( 'token' !== $loopable_expression['type'] ) {
			return $tokens;
		}

		foreach ( $this->child_tokens as $id => $child_token ) {

			$tokens[] = array(
				'data_type'  => $child_token['token_type'] ?? 'text',
				'id'         => "TOKEN_EXTENDED:DATA_TOKEN_CHILDREN_{$current_loopable_token_id}:{$loop_id}:{$integration}:{$id}",
				'name'       => $child_token['name'] ?? '',
				'token_type' => 'custom',
			);
		}

		return $tokens;

	}

	/**
	 * Extracts token information from input.
	 *
	 * @param string $input Input text.
	 *
	 * @return array|false Extracted token info or false if no match.
	 */
	public function extract_token( $input ) {

		$pattern = '/\{\{(TOKEN_EXTENDED):(DATA_TOKEN_' . $this->get_id() . '):(UNIVERSAL):(\w+):([A-Z_]+)\}\}/';

		if ( preg_match( $pattern, $input, $matches ) ) {
			return array(
				'signature'   => $matches[1],
				'parser_hook' => $matches[2],
				'type'        => $matches[3],
				'integration' => $matches[4],
				'id'          => $matches[5],
			);
		}

		return false; // Pattern did not match

	}

	/**
	 * Extracts child token information from input.
	 *
	 * @param string $input Input text.
	 *
	 * @return array Extracted child token info (empty if no match).
	 */
	public function extract_token_children( $input ) {

		$pattern = '/{{TOKEN_EXTENDED:DATA_TOKEN_CHILDREN_' . $this->get_id() . ':(\d+):(\w+):(\w+)}}/';
		$matches = array();

		preg_match_all( $pattern, $input, $matches, PREG_SET_ORDER );

		$results = array();

		foreach ( $matches as $match ) {
			$results[] = array(
				'signature'   => 'TOKEN_EXTENDED',
				'parse_hook'  => 'DATA_TOKEN_CHILDREN_' . $this->get_id(),
				'loop_id'     => (int) $match[1],
				'integration' => $match[2],
				'token_id'    => $match[3],
			);
		}

		if ( empty( $results ) ) {
			return array();
		}

		return $results;
	}

}
