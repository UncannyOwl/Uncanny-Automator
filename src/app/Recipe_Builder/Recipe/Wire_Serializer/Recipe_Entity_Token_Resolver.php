<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Bridge\Action_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Action_Tokens_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Action_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Action_Tokens_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Recipe_Item_Meta_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Trigger_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Trigger_Tokens_Bridge;
use Uncanny_Automator\App\Bridge\Recipe_Item_Meta_Bridge;
use Uncanny_Automator\App\Bridge\Trigger_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Trigger_Tokens_Bridge;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects\Loop_Field_Translator;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Recipe_Builder\Token\Domain\Token_Hint_Tag;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Token_Data_Types_Helper;
use Uncanny_Automator\Services\Loopable\Action_Loopable_Token;
use Uncanny_Automator\Services\Loopable\Trigger_Loopable_Token;

/**
 * Derives a single flow node's published token definitions at projection time,
 * reproducing the legacy structure-generator composition (field + custom
 * [+ trigger common] + loopable) with the composite legacy wire IDs, the
 * trigger-side first-occurrence dedup, and the loopable last-definition-per-class
 * quirk — so every stored {{token}} reference in existing recipes keeps
 * resolving byte-identically (golden rule). Loop-block nodes derive their
 * iteration tokens through the live automator_recipe_main_object_loop_tokens_items
 * filter (the same seam the legacy structure fires). Rows arrive from the
 * bridges/listeners in their raw shapes; this class owns composition and
 * wire shaping.
 */
final class Recipe_Entity_Token_Resolver {

	/**
	 * Trigger token derivation seam (raw legacy rows).
	 *
	 * @var Trigger_Tokens_Bridge
	 */
	private Trigger_Tokens_Bridge $trigger_tokens;

	/**
	 * Action token derivation seam (raw legacy rows).
	 *
	 * @var Action_Tokens_Bridge
	 */
	private Action_Tokens_Bridge $action_tokens;

	/**
	 * Flat item meta reader.
	 *
	 * @var Recipe_Item_Meta_Bridge
	 */
	private Recipe_Item_Meta_Bridge $item_meta;

	/**
	 * Field-definition source for field/relevant tokens (new-framework registry;
	 * materializes options_callback declarations and normalizes relevant_tokens
	 * to the wire list shape).
	 *
	 * @var Field_Registry_Service
	 */
	private Field_Registry_Service $field_registry;

	/**
	 * Trigger definition reader (loopable_tokens classes).
	 *
	 * @var Trigger_Definition_Bridge
	 */
	private Trigger_Definition_Bridge $trigger_definitions;

	/**
	 * Action definition reader (loopable_tokens classes).
	 *
	 * @var Action_Definition_Bridge
	 */
	private Action_Definition_Bridge $action_definitions;

	/**
	 * Lazy-default DI per Rule 9c: production gets the real bridges, tests inject fakes.
	 *
	 * @param Trigger_Tokens_Bridge|null     $trigger_tokens      Trigger derivation seam.
	 * @param Action_Tokens_Bridge|null      $action_tokens       Action derivation seam.
	 * @param Recipe_Item_Meta_Bridge|null   $item_meta           Flat item meta reader.
	 * @param Field_Registry_Service|null    $field_registry      Field-definition source.
	 * @param Trigger_Definition_Bridge|null $trigger_definitions Trigger definition reader.
	 * @param Action_Definition_Bridge|null  $action_definitions  Action definition reader.
	 */
	public function __construct(
		?Trigger_Tokens_Bridge $trigger_tokens = null,
		?Action_Tokens_Bridge $action_tokens = null,
		?Recipe_Item_Meta_Bridge $item_meta = null,
		?Field_Registry_Service $field_registry = null,
		?Trigger_Definition_Bridge $trigger_definitions = null,
		?Action_Definition_Bridge $action_definitions = null
	) {
		$this->trigger_tokens      = $trigger_tokens ?? new Automator_Trigger_Tokens_Bridge();
		$this->action_tokens       = $action_tokens ?? new Automator_Action_Tokens_Bridge();
		$this->item_meta           = $item_meta ?? new Automator_Recipe_Item_Meta_Bridge();
		$this->field_registry      = $field_registry ?? Field_Registry_Service::instance();
		$this->trigger_definitions = $trigger_definitions ?? new Automator_Trigger_Definition_Bridge();
		$this->action_definitions  = $action_definitions ?? new Automator_Action_Definition_Bridge();
	}

	/**
	 * Wire token rows for one item node.
	 *
	 * @param array<string,mixed> $node         A wire flow node (type 'item').
	 * @param int                 $recipe_id    Recipe id.
	 * @param bool                $may_be_empty Topology flag from the decorator: the node's
	 *                                          tokens may be absent at runtime (any-operator
	 *                                          trigger, or conditional-block descendant).
	 * @return array<int,array<string,mixed>>
	 */
	public function resolve_tokens( array $node, int $recipe_id, bool $may_be_empty = false ): array {
		$is_loop = ( Wire_Node_Type::BLOCK === ( $node[ Wire_Key::TYPE ] ?? '' ) && Block_Type::LOOP === ( $node[ Wire_Key::BLOCK_TYPE ] ?? '' ) );
		$rows    = $is_loop ? $this->loop_rows( $node, $may_be_empty ) : $this->item_rows( $node, $recipe_id, $may_be_empty );

		// FE contract: id is min-length 1 — one blank id fails the whole recipe parse.
		return array_values(
			array_filter(
				$rows,
				static function ( $row ) {
					return '' !== $row[ Wire_Key::ID ];
				}
			)
		);
	}

	/**
	 * The may-be-empty warning tag, appended only to RUN-DATA rows (values
	 * sourced from run logs, absent when the producing step didn't fire).
	 * Saved-config field/relevant rows and post-fact common rows (Trigger
	 * ID/title) parse regardless of the run and never carry it.
	 *
	 * @return array<string,string>
	 */
	private function may_be_empty_tag(): array {
		return ( new Token_Hint_Tag( Token_Hint_Tag::MAY_BE_EMPTY, esc_html_x( 'May be empty', 'Token', 'uncanny-automator' ), 'triangle-exclamation', 'warning' ) )->to_array();
	}

	/**
	 * Composed rows for one item node (trigger / action), per the
	 * legacy generator category order, deduped on the trigger side.
	 *
	 * @param array<string,mixed> $node      A wire flow node (type 'item').
	 * @param int                 $recipe_id Recipe id.
	 * @return array<int,array<string,mixed>>
	 */
	private function item_rows( array $node, int $recipe_id, bool $may_be_empty ): array {
		$item_id = (int) ( $node[ Wire_Key::ID ] ?? 0 );
		$code    = (string) ( $node[ Wire_Key::CODE ] ?? '' );
		if ( $item_id <= 0 || '' === $code ) {
			return array();
		}

		$is_trigger = ( Integration_Item_Types::TRIGGER === ( $node[ Wire_Key::ITEM_TYPE ] ?? '' ) );
		$meta       = $this->item_meta->get_item_meta( $item_id );

		// Legacy parity: get_recipe_data() skips children with no meta entirely,
		// so a meta-less item (deleted/never-saved post) publishes no tokens.
		if ( empty( $meta ) ) {
			return array();
		}

		// Category order matches the legacy generators (fields, custom, common, loopable).
		$rows = array_merge(
			$this->field_and_relevant_rows( $item_id, $code, $meta, $is_trigger ),
			$this->custom_rows( $item_id, $code, $meta, $recipe_id, $is_trigger, $may_be_empty ),
			$is_trigger ? $this->common_rows( $item_id, $may_be_empty ) : array(),
			$this->loopable_rows( $item_id, $code, $is_trigger, $may_be_empty )
		);

		if ( $is_trigger ) {
			$rows = $this->deduplicate_by_id( $rows );
		}

		return $rows;
	}

	/**
	 * Iteration-token rows for one loop-block node: reconstruct the
	 * iterable_expression from the node's serialized fields, fire the live
	 * legacy filter through a Loop_Token_Context, and map the returned
	 * structure rows to the wire shape (ids verbatim — golden rule).
	 *
	 * @param array<string,mixed> $node A wire flow node (type 'block', block_type 'loop').
	 * @return array<int,array<string,mixed>>
	 */
	private function loop_rows( array $node, bool $may_be_empty ): array {
		$loop_id = (int) ( $node[ Wire_Key::ID ] ?? 0 );
		// Field_Output_Serializer emits stdClass for EMPTY field maps — guard
		// before the array-typed translator.
		$fields = is_array( $node[ Wire_Key::FIELDS ] ?? null ) ? $node[ Wire_Key::FIELDS ] : array();
		if ( $loop_id <= 0 || empty( $fields ) ) {
			return array();
		}

		$iterable = Loop_Field_Translator::from_fields( $fields );
		if ( empty( $iterable ) ) {
			return array();
		}

		$context = new Loop_Token_Context( $loop_id, $this->bridge_token_loop_fields( $iterable ) );
		$legacy  = (array) Dispatcher::filter( 'automator_recipe_main_object_loop_tokens_items', array(), $context );

		$rows = array();
		foreach ( $legacy as $token ) {
			if ( is_object( $token ) ) {
				$token = (array) $token;
			}
			if ( ! is_array( $token ) ) {
				continue;
			}
			$rows[] = array(
				Wire_Key::ID        => (string) ( $token['id'] ?? '' ),
				Wire_Key::NAME      => (string) ( $token['name'] ?? '' ),
				Wire_Key::DATA_TYPE => Token_Data_Types_Helper::normalize( (string) ( $token['data_type'] ?? '' ) ),
				Wire_Key::TAGS      => $may_be_empty ? array( $this->may_be_empty_tag() ) : array(),
			);
		}
		return $rows;
	}

	/**
	 * Normalize a token-loop's fields blob to the {"TOKEN":{"value": chip}}
	 * JSON STRING the universal loopable listeners json_decode (they bail
	 * silently on arrays or any other shape). Loop_Field_Translator now
	 * emits this shape directly; the interim {"token_key": chip} vocabulary
	 * is still accepted for tolerance. Non-token loop types pass through
	 * untouched.
	 *
	 * @param array<string,mixed> $expression Reconstructed iterable_expression.
	 * @return array<string,mixed>
	 */
	private function bridge_token_loop_fields( array $expression ): array {
		if ( 'token' !== ( $expression['type'] ?? '' ) ) {
			return $expression;
		}

		$decoded = json_decode( (string) ( $expression['fields'] ?? '' ), true );
		$decoded = is_array( $decoded ) ? $decoded : array();
		$chip    = (string) ( $decoded['TOKEN']['value'] ?? $decoded['token_key'] ?? '' );

		$expression['fields'] = (string) wp_json_encode( array( 'TOKEN' => array( 'value' => $chip ) ) );

		return $expression;
	}

	/**
	 * Field + relevant-token rows: the item's DECLARED field definitions from
	 * Field_Registry_Service, intersected with the item's SAVED meta keys
	 * (field tokens are config-dependent — one row per saved field, matching
	 * the legacy Fields_Resolver semantics the parity oracle encodes). Rows
	 * declaring relevant_tokens emit relevant rows ONLY (legacy continue);
	 * plain saved rows emit one field token carrying the field-value hint.
	 * relevant_tokens arrives pre-normalized to the {token_code, label} wire
	 * list (see Field_Registry_Service::normalize_field_for_wire()).
	 *
	 * @param int                 $item_id    Item post id.
	 * @param string              $code       Item code.
	 * @param array<string,mixed> $meta       Flat saved item meta.
	 * @param bool                $is_trigger Trigger vs action dispatch.
	 * @return array<int,array<string,mixed>>
	 */
	private function field_and_relevant_rows( int $item_id, string $code, array $meta, bool $is_trigger ): array {
		$definitions = $this->field_registry->get_item_fields(
			(string) ( $meta['integration'] ?? '' ),
			$is_trigger ? Integration_Item_Types::TRIGGER : Integration_Item_Types::ACTION,
			$code
		);

		if ( is_wp_error( $definitions ) || ! is_array( $definitions ) ) {
			return array();
		}

		$rows = array();
		foreach ( $definitions as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$option_code = (string) ( $field['option_code'] ?? '' );
			if ( '' === $option_code || ! array_key_exists( $option_code, $meta ) ) {
				continue;
			}
			$rows = array_merge( $rows, $this->option_rows( $field, $option_code, $item_id, $code, $is_trigger ) );
		}
		return $rows;
	}

	/**
	 * Rows for one saved option definition: its relevant tokens when declared
	 * (the field itself then emits nothing — legacy continue), else one field
	 * token.
	 *
	 * @param array<string,mixed> $field       Normalized field definition row.
	 * @param string              $option_code The field's option code.
	 * @param int                 $item_id     Item post id.
	 * @param string              $code        Item code.
	 * @param bool                $is_trigger  Trigger vs action dispatch.
	 * @return array<int,array<string,mixed>>
	 */
	private function option_rows( array $field, string $option_code, int $item_id, string $code, bool $is_trigger ): array {
		if ( isset( $field['relevant_tokens'] ) && is_array( $field['relevant_tokens'] ) ) {
			$rows = array();
			foreach ( $field['relevant_tokens'] as $entry ) {
				if ( ! is_array( $entry ) || '' === (string) ( $entry['token_code'] ?? '' ) ) {
					continue;
				}
				// Trigger: {trigger_id}:{CODE}:{token_code}; action: {action_id}:{FIELD_CODE}:{token_code}.
				$id     = $is_trigger
					? sprintf( '%d:%s:%s', $item_id, $code, (string) $entry['token_code'] )
					: sprintf( '%d:%s:%s', $item_id, $option_code, (string) $entry['token_code'] );
				$rows[] = array(
					Wire_Key::ID        => $id,
					Wire_Key::NAME      => (string) ( $entry['label'] ?? '' ),
					Wire_Key::DATA_TYPE => Token_Data_Types_Helper::normalize( 'text' ),
					Wire_Key::TAGS      => array(),
				);
			}
			return $rows;
		}

		// Trigger: {trigger_id}:{CODE}:{field_code}; action: ACTION_FIELD:{action_id}:{CODE}:{field_code}.
		$id = $is_trigger
			? sprintf( '%d:%s:%s', $item_id, $code, $option_code )
			: sprintf( 'ACTION_FIELD:%d:%s:%s', $item_id, $code, $option_code );

		return array(
			array(
				Wire_Key::ID        => $id,
				Wire_Key::NAME      => (string) ( $field['label'] ?? '' ),
				Wire_Key::DATA_TYPE => Token_Data_Types_Helper::normalize( 'text' ),
				Wire_Key::TAGS      => array(
					( new Token_Hint_Tag( Token_Hint_Tag::FIELD_VALUE, esc_html_x( 'Field value', 'Token', 'uncanny-automator' ), '', 'info' ) )->to_array(),
				),
			),
		);
	}

	/**
	 * Custom-category rows from the legacy derivation bridges, with the
	 * composite wire IDs the legacy generators emit.
	 *
	 * @param int                 $item_id    Item post id.
	 * @param string              $code       Item code.
	 * @param array<string,mixed> $meta       Flat item meta.
	 * @param int                 $recipe_id  Recipe id.
	 * @param bool                $is_trigger Trigger vs action dispatch.
	 * @return array<int,array<string,mixed>>
	 */
	private function custom_rows( int $item_id, string $code, array $meta, int $recipe_id, bool $is_trigger, bool $may_be_empty ): array {
		$legacy = $is_trigger
			? $this->trigger_tokens->get_renderable_tokens( $meta, $recipe_id )
			: $this->action_tokens->get_renderable_tokens( $meta, $item_id, $recipe_id );

		$rows = array();
		foreach ( $legacy as $token ) {
			if ( ! is_array( $token ) ) {
				continue;
			}
			// Trigger: {trigger_id}:{tokenIdentifier}:{tokenId}; action: ACTION_META:{action_id}:{CODE}:{tokenId}.
			$id     = $is_trigger
				? sprintf( '%d:%s:%s', $item_id, (string) ( $token['tokenIdentifier'] ?? '' ), (string) ( $token['tokenId'] ?? '' ) )
				: sprintf( 'ACTION_META:%d:%s:%s', $item_id, $code, (string) ( $token['tokenId'] ?? '' ) );
			$rows[] = array(
				Wire_Key::ID        => $id,
				Wire_Key::NAME      => (string) ( $token['tokenName'] ?? '' ),
				// Raw bridge rows' tokenType IS the data type (int/text/email…).
				Wire_Key::DATA_TYPE => Token_Data_Types_Helper::normalize( (string) ( $token['tokenType'] ?? '' ) ),
				Wire_Key::TAGS      => $may_be_empty ? array( $this->may_be_empty_tag() ) : array(),
			);
		}
		return $rows;
	}

	/**
	 * The three trigger-common rows every trigger publishes.
	 *
	 * @param int $trigger_id Trigger post id.
	 * @return array<int,array<string,mixed>>
	 */
	private function common_rows( int $trigger_id, bool $may_be_empty ): array {
		// Fourth element: whether the value is RUN-derived. ID/title read post
		// facts and parse regardless of the run; the completion date exists
		// only when the trigger fired.
		$commons = array(
			array( 'ID', esc_attr_x( 'Trigger ID', 'Trigger', 'uncanny-automator' ), 'int', false ),
			array( 'TITLE', esc_attr_x( 'Trigger title', 'Trigger', 'uncanny-automator' ), 'text', false ),
			array( 'COMPLETION_DATE', esc_attr_x( 'Trigger completion date', 'Trigger', 'uncanny-automator' ), 'date', true ),
		);

		$rows = array();
		foreach ( $commons as $common ) {
			$rows[] = array(
				Wire_Key::ID        => sprintf( 'TRIGGER_COMMON:%d:%s', $trigger_id, $common[0] ),
				Wire_Key::NAME      => $common[1],
				Wire_Key::DATA_TYPE => Token_Data_Types_Helper::normalize( $common[2] ),
				Wire_Key::TAGS      => ( $may_be_empty && $common[3] ) ? array( $this->may_be_empty_tag() ) : array(),
			);
		}
		return $rows;
	}

	/**
	 * Loopable-token rows from the item's registered loopable classes,
	 * reproducing the legacy last-definition-per-class quirk (one row per
	 * class; the last definition wins) and dropping the legacy log_identifer.
	 *
	 * @param int    $item_id    Item post id.
	 * @param string $code       Item code.
	 * @param bool   $is_trigger Trigger vs action dispatch.
	 * @return array<int,array<string,mixed>>
	 */
	private function loopable_rows( int $item_id, string $code, bool $is_trigger, bool $may_be_empty ): array {
		$definition = $is_trigger
			? $this->trigger_definitions->get_trigger_definition( $code )
			: $this->action_definitions->get_action_definition( $code );

		$loopable_classes = is_array( $definition ) ? ( $definition['loopable_tokens'] ?? array() ) : array();
		if ( empty( $loopable_classes ) || ! is_array( $loopable_classes ) ) {
			return array();
		}

		$rows = array();
		foreach ( $loopable_classes as $token_class ) {
			if ( is_string( $token_class ) && class_exists( $token_class ) ) {
				$token_class = new $token_class( $item_id );
				// The listing hook only: the loop rows read it later in this projection. A run-time hook left
				// here would act again on a run in this request, saving the token's list to the trigger log twice.
				if ( $is_trigger ? $token_class instanceof Trigger_Loopable_Token : $token_class instanceof Action_Loopable_Token ) {
					$token_class->register_listing_hooks( $definition );
				}
			}
			if ( ! is_object( $token_class ) || ! method_exists( $token_class, 'get_definitions' ) ) {
				continue;
			}

			// Legacy quirk: one row per class, last definition wins.
			$row = null;
			foreach ( (array) $token_class->get_definitions() as $id => $token_definition ) {
				// Trigger: TOKEN_EXTENDED:DATA_TOKEN_{id}:{trigger_id}:{CODE}:{id};
				// action adds the ACTION_TOKEN segment after DATA_TOKEN_{id}.
				$wire_id = $is_trigger
					? sprintf( 'TOKEN_EXTENDED:DATA_TOKEN_%s:%d:%s:%s', (string) $id, $item_id, $code, (string) $id )
					: sprintf( 'TOKEN_EXTENDED:DATA_TOKEN_%s:ACTION_TOKEN:%d:%s:%s', (string) $id, $item_id, $code, (string) $id );
				$row     = array(
					Wire_Key::ID        => $wire_id,
					Wire_Key::NAME      => (string) ( $token_definition['name'] ?? '' ),
					Wire_Key::DATA_TYPE => Token_Data_Types_Helper::normalize( 'json' ),
					Wire_Key::TAGS      => $may_be_empty ? array( $this->may_be_empty_tag() ) : array(),
				);
			}
			if ( null !== $row ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	/**
	 * Collapse rows sharing an id to the first occurrence, mirroring the legacy
	 * trigger-side deduplicate_by_id (declaration order preserved).
	 *
	 * @param array<int,array<string,mixed>> $rows Composed rows.
	 * @return array<int,array<string,mixed>>
	 */
	private function deduplicate_by_id( array $rows ): array {
		$seen   = array();
		$unique = array();
		foreach ( $rows as $row ) {
			$id = (string) $row[ Wire_Key::ID ];
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$unique[]    = $row;
		}
		return $unique;
	}
}
