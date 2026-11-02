<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Loop_Logs_Helpers;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks\Filter_Block;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Fields_Bag;
use Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects\Loop_Field_Translator;
use Uncanny_Automator\Resolver\Fields_Resolver;

/**
 * A loop recorded by Pro 8.0, in the shape the run-details log reads a loop in.
 *
 * Pro 7.x stored the recipe's legacy actions object in a loop entry's flow (`{ items: [...] }`, each loop with its
 * `iterable_expression`, `filters` and `items`). Pro 8.0 stores the recipe's 8.0 tree there, as the run walked it:
 * a list of nodes whose loop is a `block` node of type `loop`, with its loop filters as `modifiers` and its body on
 * the `LOOP-ITERATION-COMPLETED` path. This reads the loop out of that tree:
 *
 * - `iterable_expression`: from the loop's fields, as Pro's loop processor builds it (Loop_Field_Translator).
 * - `filters`: each loop filter's integration, code and saved field values. The tree records no sentence for a
 *   loop filter, so its title is empty.
 * - `items`: each action in the body, with its sentence as the tree recorded it and its fields as the log reads an
 *   action's fields (Fields_Resolver); a Filter block in the body is a `filter` item holding its actions, with its
 *   match mode and its conditions; the actions of any other block in the body are listed in its place, as the
 *   run-details flow lists a Queue's.
 *
 * @since 8.0.0
 */
final class Loop_Block_Flow {

	/**
	 * Whether a loop entry's flow is the 8.0 recipe tree: a list of nodes, where Pro 7.x stored `{ items: [...] }`.
	 *
	 * @param mixed[] $flow The loop entry's flow.
	 *
	 * @return bool
	 */
	public static function is_recipe_tree( array $flow ) {
		return ! isset( $flow['items'] ) && array() !== $flow && array_keys( $flow ) === range( 0, count( $flow ) - 1 );
	}

	/**
	 * The loop with the given id in an 8.0 recipe tree, in the legacy loop shape.
	 *
	 * @param mixed[] $content The tree's content nodes.
	 * @param int     $loop_id Loop block id.
	 *
	 * @return mixed[]|null The loop, or null when the tree holds no loop with that id.
	 */
	public static function find_loop( array $content, $loop_id ) {

		foreach ( $content as $node ) {
			$node = (array) $node;
			if ( 'block' !== ( $node['kind'] ?? '' ) ) {
				continue;
			}
			if ( Block_Type::LOOP === ( $node['block_type'] ?? '' ) && (int) ( $node['id'] ?? 0 ) === (int) $loop_id ) {
				return self::loop( $node );
			}
			foreach ( (array) ( $node['paths'] ?? array() ) as $path ) {
				$found = self::find_loop( (array) ( ( (array) $path )['items'] ?? array() ), $loop_id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * A loop block node in the legacy loop shape.
	 *
	 * @param mixed[] $node Loop block node.
	 *
	 * @return mixed[]
	 */
	private static function loop( array $node ) {

		$expression = Loop_Field_Translator::from_fields( (array) ( $node['fields'] ?? array() ) );

		return array(
			'type'                => 'loop',
			'id'                  => (int) ( $node['id'] ?? 0 ),
			'iterable_expression' => array(
				'type'   => $expression['type'] ?? null,
				'fields' => $expression['fields'] ?? null,
				'backup' => null,
			),
			'filters'             => array_map( array( self::class, 'loop_filter' ), array_values( (array) ( $node['modifiers'] ?? array() ) ) ),
			'items'               => self::items( self::path_items( $node, Block_Path_Code::LOOP_ITERATION_COMPLETED ) ),
		);
	}

	/**
	 * A loop filter modifier in the legacy loop-filter shape.
	 *
	 * @param mixed $modifier Loop filter modifier node.
	 *
	 * @return mixed[]
	 */
	private static function loop_filter( $modifier ) {

		$modifier = (array) $modifier;
		$fields   = array();

		foreach ( Fields_Bag::from_array( (array) ( $modifier['fields'] ?? array() ) )->to_array() as $code => $field ) {
			$fields[ $code ] = array(
				'type'     => 'text',
				'backup'   => array( 'label' => $code ),
				'readable' => $field['readable'],
				'value'    => $field['value'],
			);
		}

		return array(
			'id'               => $modifier['id'] ?? 0,
			'integration_code' => (string) ( $modifier['integration_code'] ?? '' ),
			'code'             => (string) ( $modifier['code'] ?? '' ),
			'backup'           => array( 'sentence_html' => '' ),
			'fields'           => $fields,
		);
	}

	/**
	 * The items of a block path.
	 *
	 * @param mixed[] $node      Block node.
	 * @param string  $path_code Path code.
	 *
	 * @return mixed[]
	 */
	private static function path_items( array $node, $path_code ) {

		foreach ( (array) ( $node['paths'] ?? array() ) as $path ) {
			$path = (array) $path;
			if ( ( $path['path_code'] ?? '' ) === $path_code ) {
				return (array) ( $path['items'] ?? array() );
			}
		}

		return array();
	}

	/**
	 * Body nodes in the legacy item shape: actions, and Filter blocks as `filter` items; any other block's actions
	 * stand in its place.
	 *
	 * @param mixed[] $nodes Body nodes.
	 *
	 * @return mixed[]
	 */
	private static function items( array $nodes ) {

		$items = array();

		foreach ( $nodes as $node ) {
			$node = (array) $node;

			if ( 'action' === ( $node['kind'] ?? '' ) ) {
				$items[] = self::action( $node );
				continue;
			}

			if ( 'block' !== ( $node['kind'] ?? '' ) ) {
				continue;
			}

			if ( Block_Type::FILTER === ( $node['block_type'] ?? '' ) ) {
				$items[] = self::filter( $node );
				continue;
			}

			foreach ( (array) ( $node['paths'] ?? array() ) as $path ) {
				$items = array_merge( $items, self::actions_only( self::items( (array) ( ( (array) $path )['items'] ?? array() ) ) ) );
			}
		}

		return $items;
	}

	/**
	 * The actions among legacy items, a filter item's actions in its place.
	 *
	 * @param mixed[] $items Legacy items.
	 *
	 * @return mixed[]
	 */
	private static function actions_only( array $items ) {

		$actions = array();

		foreach ( $items as $item ) {
			$actions = array_merge( $actions, 'filter' === $item['type'] ? $item['items'] : array( $item ) );
		}

		return $actions;
	}

	/**
	 * An action node in the legacy action shape.
	 *
	 * @param mixed[] $node Action node.
	 *
	 * @return mixed[]
	 */
	private static function action( array $node ) {

		$action_id = (int) ( $node['id'] ?? 0 );
		$code      = (string) ( $node['code'] ?? '' );
		$action    = '' === $code ? null : Automator()->get_action( $code );

		$resolver = new Fields_Resolver();
		$resolver->set_object_type( 'action' );
		$resolver->set_object_id( $action_id );

		// Values belong to the recorded tree, including when the current action was edited or deleted.
		$saved_meta = array();
		foreach ( Fields_Bag::to_legacy_flat( (array) ( $node['fields'] ?? array() ) ) as $key => $value ) {
			$saved_meta[ $key ] = array( $value );
		}
		$saved_meta['code']        = array( $code );
		$saved_meta['integration'] = array( (string) ( $node['integration_code'] ?? '' ) );

		return array(
			'type'             => 'action',
			'id'               => $action_id,
			'integration_code' => (string) ( $node['integration_code'] ?? '' ),
			'code'             => $code,
			'miscellaneous'    => array( 'uses_credit' => is_array( $action ) && isset( $action['uses_api'] ) ),
			'backup'           => array( 'sentence_html' => (string) ( $node['presentation']['sentence_human_readable_html'] ?? '' ) ),
			'fields'           => $resolver->resolve_saved_object_fields( $saved_meta ),
		);
	}

	/**
	 * A Filter block node in the legacy filter shape: its match mode, its conditions and the actions on both paths.
	 *
	 * @param mixed[] $node Filter block node.
	 *
	 * @return mixed[]
	 */
	private static function filter( array $node ) {

		$mode       = Fields_Bag::from_array( (array) ( $node['fields'] ?? array() ) )->scalar( Filter_Block::FIELD_MATCH_MODE );
		$conditions = array();

		foreach ( (array) ( $node['modifiers'] ?? array() ) as $modifier ) {
			$modifier     = (array) $modifier;
			$conditions[] = array(
				'id'               => $modifier['id'] ?? 0,
				'integration_code' => (string) ( $modifier['integration_code'] ?? '' ),
				'code'             => (string) ( $modifier['code'] ?? '' ),
				'backup'           => array( 'sentence_html' => '' ),
			);
		}

		$actions = array();
		foreach ( (array) ( $node['paths'] ?? array() ) as $path ) {
			$actions = array_merge( $actions, self::actions_only( self::items( (array) ( ( (array) $path )['items'] ?? array() ) ) ) );
		}

		return array(
			'type'       => 'filter',
			'id'         => (int) ( $node['id'] ?? 0 ),
			'logic'      => '' === $mode ? Filter_Block::MATCH_MODE_ANY : $mode,
			'conditions' => $conditions,
			'items'      => $actions,
		);
	}
}
