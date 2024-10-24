<?php
namespace Uncanny_Automator\Services\Recipe\Action\Token;

use Exception;

/**
 * Class Registry
 *
 * Registers an action token for a specific action.
 *
 * @since 5.9
 */
class Registry {

	/**
	 * The priority of the hook automator_action_{$action_code}_tokens_renderable.
	 *
	 * @var int
	 */
	protected $renderable_priority = 10;

	/**
	 * The number of arguments for the filter automator_action_{$action_code}_tokens_renderable.
	 *
	 * @var int
	 */
	protected $renderable_number_args = 3;

	/**
	 * Registers the action token parser.
	 *
	 * @return false|void Returns false if the hooks are already registers. Otherwise, true.
	 */
	public function register_hooks() {

		// Automatically register the action hooks once an action has set a token.
		if ( did_action( 'automator_action_tokens_parser_loaded' ) ) {
			return false;
		}

		// Register the parser.
		add_filter( 'automator_action_token_input_parser_text_field_text', array( new Parser(), 'replace_key_value_pairs' ), 10, 3 );

		// Invoke an action hook.
		do_action( 'automator_action_tokens_parser_loaded' );

		return true;

	}

	/**
	 * Register an action tokens for a single action.
	 *
	 * @param mixed $tokens
	 * @param mixed $action_code
	 *
	 * @return void
	 */
	public function register( $tokens, $action_code ) {

		$action_tokens = $this->create_action_tokens( $tokens, $action_code );

		$closure = function ( $registered_tokens = array(), $action_id = null, $recipe_id = null ) use ( $action_tokens ) {
			return array_unique( array_merge( $registered_tokens, $action_tokens ), SORT_REGULAR );
		};

		$hook_name = "automator_action_{$action_code}_tokens_renderable";

		// Every action has its own tokens.
		add_filter(
			$hook_name,
			$closure,
			$this->renderable_priority,
			$this->renderable_number_args
		);

	}

	/**
	 * Creates a new set of action tokens.
	 *
	 * @param $tokens
	 * @param $action_code
	 *
	 * @return array
	 */
	public function create_action_tokens( $tokens = array(), $action_code = '' ) {

		$formatted_tokens = array();

		foreach ( $tokens as $key => $props ) {

			$action_token = new Entity(); // @suggestion, use DI for more robust implementation.

			$type = $props['type'] ?? 'int';

			try {
				$action_token->set_id( $key );
				$action_token->set_parent( $action_code );
				$action_token->set_name( $props['name'] );
				$action_token->set_type( $type );

				$formatted_tokens[] = $action_token->toArray();
			} catch ( Exception $e ) {
				_doing_it_wrong( __FUNCTION__, $e->getMessage(), '6.0' );
			}
		}

		return $formatted_tokens;

	}

}
