<?php
namespace Uncanny_Automator\Services\Recipe\Structure\Actions\Item;

use Uncanny_Automator\Services\Recipe\Common;
use Uncanny_Automator\Services\Recipe\Structure\Actions\Item\Action;
use Uncanny_Automator\Services\Structure\Actions\Item\Loop\Loop_Db;

/**
 * An object representation of the loop type in the actions item object inside the recipe object.
 *
 * @package Uncanny_Automator\Services\Recipe\Structure\Actions\Item
 * @since 5.0
 */
final class Loop implements \JsonSerializable {

	use Common\Trait_JSON_Serializer;
	use Common\Trait_Setter_Getter;

	protected $type = 'loop';
	protected $id   = null;

	/**
	 * The order of items based on its type.
	 * 0 = normal actions; 1 = closures; 2 = loop.
	 * @var int
	 */
	protected $_ui_order           = 2; //phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore
	protected $iterable_expression = array();
	protected $run_on              = null;
	protected $filters             = array();
	protected $items               = array();
	protected $tokens              = array();

	protected static $loop_id;
	protected static $recipe;
	protected static $actions;

	public function __construct( $recipe, $actions, $loop_id ) {

		self::$loop_id = $loop_id;
		self::$recipe  = $recipe;
		self::$actions = $actions;

		$this->id = absint( $loop_id );
		$this->hydrate_iterable_expression();
		$this->hydrate_tokens();
		$this->hydrate_filters();
		$this->hydrate_items( $recipe );

	}

	/**
	 * Hydrates the tokens properties.
	 *
	 * @return void
	 */
	private function hydrate_tokens() {
		$this->tokens = (array) apply_filters( 'automator_recipe_main_object_loop_tokens_items', array(), $this );
	}

	/**
	 * Hydrates the Loop Filters.
	 *
	 * @return void
	 */
	private function hydrate_filters() {

		$loop_db = new Loop_Db();

		$filters = $loop_db->find_loop_filters( self::$loop_id );

		$loop_filters = array();

		foreach ( $filters as $filter ) {

			$filter_id = $filter['ID'];
			$fields    = (string) get_post_meta( $filter_id, 'fields', true );
			$backup    = (string) get_post_meta( $filter_id, 'backup', true );

			$loop_filters[] = array(
				'id'               => absint( $filter_id ),
				'code'             => strtoupper( (string) get_post_meta( $filter_id, 'code', true ) ),
				'integration_code' => strtoupper( (string) get_post_meta( $filter_id, 'integration_code', true ) ),
				'backup'           => (array) json_decode( $backup, true ),
				'fields'           => (array) json_decode( $fields, true ),
			);

		}

		$this->filters = $loop_filters;
	}

	/**
	 * Hydrates the Actions that are under the loop.
	 *
	 * @return void
	 */
	private function hydrate_items() {

		$actions = Automator()->get_recipe_data( 'uo-action', self::$loop_id );

		$config = self::$recipe->get_config();

		$action_items = array();

		foreach ( $actions as $action_item ) {

			if ( isset( $config['publish_only'] ) && true === $config['publish_only'] ) {
				if ( 'publish' !== $action_item['post_status'] ) {
					continue; // Skip draft actions if config is set to publish only.
				}
			}

			$action = new Action( self::$recipe );
			$action->hydrate_from( $action_item );

			$action_items[] = $action;

		}

		$action_items = apply_filters( 'automator_recipe_main_object_loop_action_items', $action_items, self::$recipe, $this, self::$actions );

		$this->items = $action_items;
	}

	/**
	 * @return void
	 */
	private function hydrate_iterable_expression() {

		$iterable_expression = (array) get_post_meta( absint( self::$loop_id ), 'iterable_expression', true );

		$this->iterable_expression = wp_parse_args(
			$iterable_expression,
			array(
				'type'       => 'users',
				'value'      => '',
				'value_html' => '',
			)
		);

	}

}
