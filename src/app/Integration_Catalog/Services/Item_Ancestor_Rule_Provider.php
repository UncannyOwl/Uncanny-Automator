<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Integration_Catalog\Services;

use InvalidArgumentException;
use Uncanny_Automator\App\Bridge\Action_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Action_Definition_Bridge;
use Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects\Ancestor_Rule;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;

/**
 * The single reader of the placement rules an action declares.
 *
 * Answers "which blocks must action <code> sit inside?" for the catalogue,
 * the publish gate and keep-mode block delete, so the three can never
 * disagree. The only code that knows the registration payload key exists.
 *
 * A malformed rule is dropped and reported, never thrown: the frontend
 * validates the scoped-integrations response with a strict parser, so one bad
 * declaration passed through would blank the builder's whole catalogue.
 */
final class Item_Ancestor_Rule_Provider {

	/**
	 * Registration payload key the action abstract writes the rules under.
	 *
	 * @var string
	 */
	public const PAYLOAD_KEY = 'required_block_ancestors';

	/**
	 * The only entity_type the frontend rule schema accepts.
	 *
	 * @var string
	 */
	private const ENTITY_TYPE = 'block';

	/**
	 * Singleton instance.
	 *
	 * @var Item_Ancestor_Rule_Provider|null
	 */
	private static ?Item_Ancestor_Rule_Provider $instance = null;

	/**
	 * Action registry reader.
	 *
	 * @var Action_Definition_Bridge
	 */
	private Action_Definition_Bridge $actions;

	/**
	 * Lazy-default constructor injection (Rule 9c).
	 *
	 * @param Action_Definition_Bridge|null $actions Action registry reader.
	 */
	private function __construct( ?Action_Definition_Bridge $actions = null ) {
		$this->actions = $actions ?? new Automator_Action_Definition_Bridge();
	}

	/**
	 * Singleton accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Test seam — fresh instance with an injected registry reader.
	 *
	 * @param Action_Definition_Bridge|null $actions Action registry reader.
	 *
	 * @return self
	 */
	public static function create_with_dependencies( ?Action_Definition_Bridge $actions = null ): self {
		return new self( $actions );
	}

	/**
	 * Whether the action is registered in this request.
	 *
	 * @param string $action_code Action code.
	 *
	 * @return bool
	 */
	public function is_registered( string $action_code ): bool {
		return '' !== $action_code && null !== $this->actions->get_action_definition( $action_code );
	}

	/**
	 * The validated placement rules an action declares.
	 *
	 * @param string        $action_code Action code.
	 * @param callable|null $on_drop     Receives ( string $action_code, string $reason ) per dropped rule; null logs it.
	 *
	 * @return array<int,array> Wire-shaped rules; empty when the action is unknown or unrestricted.
	 */
	public function get_rules( string $action_code, ?callable $on_drop = null ): array {
		if ( '' === $action_code ) {
			return array();
		}

		$definition = $this->actions->get_action_definition( $action_code );
		$declared   = $definition[ self::PAYLOAD_KEY ] ?? array();

		if ( ! is_array( $declared ) ) {
			$this->report( $on_drop, $action_code, 'placement rules must be an array' );
			return array();
		}

		$rules = array();
		foreach ( $declared as $raw ) {
			try {
				$rule = ( new Ancestor_Rule( is_array( $raw ) ? $raw : array() ) )->to_array();
			} catch ( InvalidArgumentException $e ) {
				$this->report( $on_drop, $action_code, $e->getMessage() );
				continue;
			}

			if ( self::ENTITY_TYPE !== $rule['entity_type'] ) {
				$this->report( $on_drop, $action_code, sprintf( "entity_type must be 'block', got '%s'", $rule['entity_type'] ) );
				continue;
			}

			// A typo passes the VO and the FE schema alike; the symptom would be an
			// action placeable nowhere and a recipe that can never go live.
			if ( ! Block_Type::is_valid( $rule['entity_code'] ) ) {
				$this->report( $on_drop, $action_code, sprintf( "entity_code '%s' is not a block type", $rule['entity_code'] ) );
				continue;
			}

			$rules[] = $rule;
		}

		return $rules;
	}

	/**
	 * Hand a dropped rule to the caller's reporter, or log it.
	 *
	 * @param callable|null $on_drop     Caller's reporter.
	 * @param string        $action_code Declaring action.
	 * @param string        $reason      Why the rule was dropped.
	 *
	 * @return void
	 */
	private function report( ?callable $on_drop, string $action_code, string $reason ): void {
		if ( null !== $on_drop ) {
			$on_drop( $action_code, $reason );
			return;
		}

		automator_log( sprintf( 'Dropped a placement rule declared by action %s: %s', $action_code, $reason ), 'Item_Ancestor_Rule_Provider' );
	}
}
