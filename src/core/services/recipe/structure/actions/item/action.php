<?php
namespace Uncanny_Automator\Services\Recipe\Structure\Actions\Item;

use stdClass;
use Uncanny_Automator\Services\Recipe\Common;
use Uncanny_Automator\Services\Recipe\Structure\Fields;
use Uncanny_Automator\Services\Recipe\Structure\Actions;

/**
 * An object representation of the action type in the actions item object inside the recipe object.
 *
 * @package Uncanny_Automator\Services\Recipe\Structure\Actions\Item
 *
 * @since 5.0
 */
final class Action implements \JsonSerializable {

	use Common\Trait_JSON_Serializer;
	use Common\Trait_Setter_Getter;

	protected $type             = 'action';
	protected $_ui_order        = 0; //phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore
	protected $is_item_on       = false;
	protected $id               = null;
	protected $integration_code = null;
	protected $code             = null;
	protected $miscellaneous    = null;
	protected $backup           = null;
	protected $fields           = null;
	protected $tokens           = null;

	private static $recipe = null;

	public function __construct( $recipe ) {
		self::$recipe = $recipe;
	}

	/**
	 * The property can_log_in_user and uses_credit is an official Trigger property.
	 *
	 * @param mixed[] $action;
	 *
	 * @return object
	 */
	private function miscellaneous( $action ) {

		$misc = new stdClass();

		$action_arr_from_object = Automator()->get_action( $action['meta']['code'] );

		$action_meta = isset( $action['meta'] ) ? $action['meta'] : array();

		$misc->parent_id   = $action['post_parent'];
		$misc->uses_credit = isset( $action_arr_from_object['uses_api'] );

		if ( isset( $action_meta['async_mode'] ) ) {
			$this->_ui_order      = $this->generate_async_timing( $action_meta );
			$misc->delay_schedule = $this->restructure_delay_schedule( $action_meta );
		}

		return $misc;

	}

	/**
	 * Generates the backup structure.
	 *
	 * @param mixed[] $action
	 *
	 * @return stdClass
	 */
	private function backup( $action ) {

		$backup = new stdClass();

		$integration = Automator()->get_integration( $action['meta']['integration'] );

		$sentence_human_readable = isset( $action['meta']['sentence_human_readable'] ) ? htmlentities( $action['meta']['sentence_human_readable'], ENT_QUOTES ) : '';

		$sentence_html = isset( $action['meta']['sentence_human_readable_html'] )
		? htmlentities( $action['meta']['sentence_human_readable_html'], ENT_QUOTES )
		: $sentence_human_readable;

		$backup->integration_name = isset( $integration['name'] ) ? $integration['name'] : null;
		$backup->sentence         = $sentence_human_readable;
		$backup->sentence_html    = $sentence_html;

		return $backup;

	}

	/**
	 * @param mixed[] $action.
	 *
	 * @return self;
	 */
	public function hydrate_from( $action = array() ) {

		$fields = new Fields( $action, self::$recipe, 'action' );

		$tokens = new Actions\Item\Tokens( $action );

		$this->id               = $action['ID'];
		$this->is_item_on       = 'publish' === $action['post_status'];
		$this->integration_code = $action['meta']['integration'];
		$this->code             = $action['meta']['code'];
		$this->miscellaneous    = $this->miscellaneous( $action );
		$this->backup           = $this->backup( $action );
		$this->fields           = $fields->get_fields();
		$this->tokens           = $tokens->get_tokens( $fields->get_original_fields() );

		return $this;
	}

	/**
	 * Restructures delay schedule to follow the specs.
	 *
	 * @param mixed[] $action_meta
	 *
	 * @return mixed[]
	 */
	private function restructure_delay_schedule( $action_meta ) {

		$args = wp_parse_args(
			$action_meta,
			array(
				'async_mode'          => null,
				'async_delay_number'  => null,
				'async_delay_unit'    => null,
				'async_schedule_time' => null,
				'async_schedule_date' => null,
				'async_sentence'      => null,
			)
		);

		return array(
			'MODE'          => array(
				'value' => $args['async_mode'],
			),
			'DELAY_VALUE'   => array(
				'value' => $args['async_delay_number'],
			),
			'DELAY_UNIT'    => array(
				'value' => $args['async_delay_unit'],
			),
			'SCHEDULE_TIME' => array(
				'value' => $args['async_schedule_time'],
			),
			'SCHEDULE_DATE' => array(
				'value' => $args['async_schedule_date'],
			),
		);
	}

	/**
	 * Generates async timing.
	 *
	 * Converts the date to timestamp if its a schedule. Otherwise, if its a delay, calculates the timing
	 * from todays timestamp (now) + the delay.
	 *
	 * @param $action_meta
	 *
	 * @return int The timestamp.
	 */
	private function generate_async_timing( $action_meta ) {

		if ( 'delay' === $action_meta['async_mode'] ) {
			$ts = strtotime( 'now +' . $action_meta['async_delay_number'] . ' ' . $action_meta['async_delay_unit'] );
			return absint( 3 . $ts );
		}

		if ( 'schedule' === $action_meta['async_mode'] ) {
			$ts = strtotime( $action_meta['async_schedule_date'] . ' ' . $action_meta['async_schedule_time'] );
			return absint( 4 . $ts );
		}

		return 0;

	}

}


