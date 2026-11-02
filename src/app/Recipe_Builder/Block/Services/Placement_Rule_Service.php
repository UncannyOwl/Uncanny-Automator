<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Block\Services;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Integration_Catalog\Services\Item_Ancestor_Rule_Provider;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\WP_Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Unsupported_Entity;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Placement_Violation;
use WP_Error;

/**
 * The one evaluator of block placement rules: which blocks and actions may sit under which blocks.
 *
 * Backend mirror of the frontend's getChainNestingViolation for the items slot. A candidate is checked
 * against every ancestor, innermost first: the ancestor's block-level `unsupported_entities` rows, then its
 * rows for the path the chain passes through; then against the candidate's own declared rules through
 * Ancestor_Rule_Evaluator. The first rule that names the candidate wins. The two sides must agree exactly;
 * a placement the builder allows and the backend refuses, or the reverse, is a bug on one side.
 */
final class Placement_Rule_Service {

	/**
	 * WP_Error code a write-time refusal carries: a create or move the placement rules reject.
	 *
	 * @var string
	 */
	public const REJECTION_CODE = 'automator_placement_rejected';

	/**
	 * Depth guard against a parent cycle in corrupt data.
	 *
	 * @var int
	 */
	private const MAX_DEPTH = 32;

	/**
	 * Singleton instance.
	 *
	 * @var Placement_Rule_Service|null
	 */
	private static ?Placement_Rule_Service $instance = null;

	/**
	 * Block row reader, for the chain.
	 *
	 * @var Block_Store
	 */
	private Block_Store $block_store;

	/**
	 * Block catalogue, for each ancestor's rows and each block's name.
	 *
	 * @var WP_Block_Registry
	 */
	private WP_Block_Registry $block_registry;

	/**
	 * The rules an action declares.
	 *
	 * @var Item_Ancestor_Rule_Provider
	 */
	private Item_Ancestor_Rule_Provider $rules;

	/**
	 * Candidate-side evaluator.
	 *
	 * @var Ancestor_Rule_Evaluator
	 */
	private Ancestor_Rule_Evaluator $evaluator;

	/**
	 * Lazy-default constructor injection (Rule 9c).
	 *
	 * @param Block_Store|null                 $block_store    Block row reader.
	 * @param WP_Block_Registry|null           $block_registry Block catalogue.
	 * @param Item_Ancestor_Rule_Provider|null $rules          Action placement rules.
	 * @param Ancestor_Rule_Evaluator|null     $evaluator      Candidate-side evaluator.
	 */
	private function __construct(
		?Block_Store $block_store = null,
		?WP_Block_Registry $block_registry = null,
		?Item_Ancestor_Rule_Provider $rules = null,
		?Ancestor_Rule_Evaluator $evaluator = null
	) {
		$this->block_store    = $block_store ?? Database::get_block_store();
		$this->block_registry = $block_registry ?? new WP_Block_Registry();
		$this->rules          = $rules ?? Item_Ancestor_Rule_Provider::instance();
		$this->evaluator      = $evaluator ?? new Ancestor_Rule_Evaluator();
	}

	/**
	 * Shared instance.
	 *
	 * @return Placement_Rule_Service
	 */
	public static function instance(): Placement_Rule_Service {
		return self::$instance ??= new self();
	}

	/**
	 * An instance with explicit dependencies, for tests.
	 *
	 * @param Block_Store|null                 $block_store    Block row reader.
	 * @param WP_Block_Registry|null           $block_registry Block catalogue.
	 * @param Item_Ancestor_Rule_Provider|null $rules          Action placement rules.
	 * @param Ancestor_Rule_Evaluator|null     $evaluator      Candidate-side evaluator.
	 *
	 * @return Placement_Rule_Service
	 */
	public static function create_with_dependencies(
		?Block_Store $block_store = null,
		?WP_Block_Registry $block_registry = null,
		?Item_Ancestor_Rule_Provider $rules = null,
		?Ancestor_Rule_Evaluator $evaluator = null
	): Placement_Rule_Service {
		return new self( $block_store, $block_registry, $rules, $evaluator );
	}

	/**
	 * One chain entry: a block and the path the chain passes through at it. An empty path, or one the block
	 * type does not declare (a stale stamp from a previous parent), reads as the block type's default, as it
	 * does for the tree and for a keep-delete landing.
	 *
	 * @param string $block_type Block type.
	 * @param array  $fields     The block's wire-shape fields, for field-aware candidate rules.
	 * @param string $path_code  The path the chain passes through; '' for the default.
	 *
	 * @return array{block_type:string, fields:array, path_code:string}
	 */
	public static function entry( string $block_type, array $fields, string $path_code ): array {
		return array(
			'block_type' => $block_type,
			'fields'     => $fields,
			'path_code'  => in_array( $path_code, Block_Path_Code::for_block_type( $block_type ), true )
				? $path_code
				: Block_Path_Code::default_for_block_type( $block_type ),
		);
	}

	/**
	 * The blocks above a landing position, outermost first. Empty when the parent is the recipe or unknown.
	 *
	 * @param int    $parent_id The parent the candidate lands under.
	 * @param string $path_code The parent's path it lands on; '' for the parent's default path.
	 *
	 * @return array<int, array{block_type:string, fields:array, path_code:string}>
	 */
	public function chain_for( int $parent_id, string $path_code = '' ): array {
		$chain = array();
		$path  = $path_code;
		$block = $parent_id > 0 ? $this->block_store->get( $parent_id ) : null;
		$depth = 0;

		while ( null !== $block && $depth < self::MAX_DEPTH ) {
			array_unshift(
				$chain,
				self::entry(
					(string) ( $block['block_type'] ?? '' ),
					is_array( $block['fields'] ?? null ) ? $block['fields'] : array(),
					$path
				)
			);
			$path   = (string) ( $block['path_code'] ?? '' );
			$parent = (int) ( $block['parent_id'] ?? 0 );
			$block  = $parent > 0 ? $this->block_store->get( $parent ) : null;
			++$depth;
		}

		return $chain;
	}

	/**
	 * A block candidate.
	 *
	 * @param string $block_type Block type.
	 *
	 * @return array{entity_type:string, entity_code:string}
	 */
	public function block_candidate( string $block_type ): array {
		return array(
			'entity_type' => 'block',
			'entity_code' => $block_type,
		);
	}

	/**
	 * An action candidate: named by an item row through its code or the `action` literal, by an
	 * integration row through its integration, and carrying the rules its definition declares.
	 *
	 * @param string $code             Action code.
	 * @param string $integration_code Integration code.
	 *
	 * @return array{entity_type:string, entity_code:string, item_type:string, integration_code:string, required_block_ancestors:array}
	 */
	public function action_candidate( string $code, string $integration_code ): array {
		return array(
			'entity_type'              => 'item',
			'entity_code'              => $code,
			'item_type'                => Integration_Item_Types::ACTION,
			'integration_code'         => $integration_code,
			'required_block_ancestors' => $this->rules->get_rules( $code ),
		);
	}

	/**
	 * The first rule that rejects the candidate at the chain, or null when every rule allows it.
	 *
	 * @param array<int, array{block_type:string, fields:array, path_code:string}> $chain     Ancestors, outermost first.
	 * @param array                                                                 $candidate A block or action candidate.
	 *
	 * @return Placement_Violation|null
	 */
	public function violation( array $chain, array $candidate ): ?Placement_Violation {
		foreach ( array_reverse( $chain ) as $entry ) {
			$block_type = (string) ( $entry['block_type'] ?? '' );
			$definition = $this->block_registry->get_block_definition( $block_type );
			if ( null === $definition ) {
				continue;
			}

			$row = self::first_match( $definition->get_unsupported_entities(), $candidate );
			if ( null !== $row ) {
				return new Placement_Violation( Placement_Violation::SOURCE_BLOCK, $row->get_reason(), $block_type );
			}

			foreach ( $definition->get_paths() as $path ) {
				if ( $path->get_path_code() !== (string) ( $entry['path_code'] ?? '' ) ) {
					continue;
				}
				$row = self::first_match( $path->get_unsupported_entities(), $candidate );
				if ( null !== $row ) {
					return new Placement_Violation( Placement_Violation::SOURCE_PATH, $row->get_reason(), $block_type );
				}
				break;
			}
		}

		if ( ! $this->evaluator->is_violation( $chain, $candidate ) ) {
			return null;
		}

		// Only required rules can be declared (Item_Ancestor_Rule_Provider), so a rejection here is a
		// required-ancestor miss; the first rule is the one the builder's dialog reports too.
		$rule = (array) ( $candidate['required_block_ancestors'][0] ?? array() );

		return new Placement_Violation(
			Placement_Violation::SOURCE_CANDIDATE,
			(string) ( $rule['reason'] ?? '' ),
			(string) ( $rule['entity_code'] ?? '' )
		);
	}

	/**
	 * The sentence a consumer shows: the rule's reason, or, for a candidate rule that carries none, the
	 * block the action must sit inside.
	 *
	 * @param Placement_Violation $violation    The rejection.
	 * @param string              $display_name The candidate's display name.
	 *
	 * @return string
	 */
	public function message( Placement_Violation $violation, string $display_name ): string {
		if ( '' !== $violation->get_reason() ) {
			return $violation->get_reason();
		}

		$definition = $this->block_registry->get_block_definition( $violation->get_ancestor_block_type() );
		$block_name = null === $definition ? $violation->get_ancestor_block_type() : $definition->get_name();

		return sprintf(
			// translators: %1$s is the action's display name, %2$s is a block name such as "Loop".
			esc_html_x( 'The action **%1$s** can only be used inside a **%2$s** block.', 'Recipe violation message', 'uncanny-automator' ),
			$display_name,
			$block_name
		);
	}

	/**
	 * The error a write path returns when it refuses a placement: the sentence as the message, the
	 * violation as data, HTTP 422 for the REST layer.
	 *
	 * @param Placement_Violation $violation    The rejection.
	 * @param string              $display_name The candidate's display name.
	 *
	 * @return WP_Error
	 */
	public function rejection( Placement_Violation $violation, string $display_name ): WP_Error {
		return new WP_Error(
			self::REJECTION_CODE,
			$this->message( $violation, $display_name ),
			array(
				'status'    => 422,
				'violation' => $violation->to_wire(),
			)
		);
	}

	/**
	 * The first row that names the candidate.
	 *
	 * @param Block_Unsupported_Entity[] $rows      An ancestor's rows.
	 * @param array                      $candidate The candidate.
	 *
	 * @return Block_Unsupported_Entity|null
	 */
	private static function first_match( array $rows, array $candidate ): ?Block_Unsupported_Entity {
		foreach ( $rows as $row ) {
			if ( self::names( $row, $candidate ) ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Whether a row names the candidate: a block row by block type; an item row by action code or by the
	 * `action` literal; an integration row by integration code.
	 *
	 * @param Block_Unsupported_Entity $row       The row.
	 * @param array                    $candidate The candidate.
	 *
	 * @return bool
	 */
	private static function names( Block_Unsupported_Entity $row, array $candidate ): bool {
		$code             = $row->get_entity_code();
		$type             = (string) ( $candidate['entity_type'] ?? '' );
		$entity_code      = (string) ( $candidate['entity_code'] ?? '' );
		$item_type        = (string) ( $candidate['item_type'] ?? '' );
		$integration_code = (string) ( $candidate['integration_code'] ?? '' );

		switch ( $row->get_entity_type() ) {
			case 'block':
				return 'block' === $type && $entity_code === $code;
			case 'item':
				return 'item' === $type && ( $entity_code === $code || $item_type === $code );
			case 'integration':
				return 'item' === $type && $integration_code === $code;
		}

		return false;
	}
}
