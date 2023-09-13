<?php
namespace Uncanny_Automator\Services;

/**
 * The properties class for sending properties to logs.
 *
 * @since 5.0
 */
class Properties {

	/**
	 * The properties to send. See default for sample format.
	 *
	 * @var array{array{type:string,attributes:mixed[],label:string,value:string}} $items
	 */
	public $items = array(
		array(
			'type'       => '',
			'label'      => '',
			'value'      => '',
			'attributes' => array(),
		),
		// ...
	);

	/**
	 * Adds a property to properties.
	 *
	 * @param array{type:string,attributes:mixed[],label:string,value:string} $item
	 *
	 * @return void
	 */
	public function add_item( $item ) {

		if ( empty( $item['value'] ) || empty( $item['label'] ) || empty( $item['type'] ) ) {
			return;
		}

		$this->items[] = $item;
	}

	/**
	 * Retrieves all items.
	 *
	 * @return array{array{type:string,attributes:mixed[],label:string,value:string}}
	 */
	public function get_items() {

		$items = array_filter(
			$this->items,
			function( $item ) {
				return ! empty( $item['value'] ) && ! empty( $item['label'] ) && ! empty( $item['type'] );
			}
		);

		return array_values( $items );

	}

	/**
	 * Dispatches the method "record_properties" into "automator_action_created" hook for saving the props.
	 *
	 * @return void
	 */
	public function dispatch() {
		add_action( 'automator_action_created', array( $this, 'record_properties' ), 20, 1 );
	}

	/**
	 * Callback method to action hook "automator_action_created".
	 *
	 * @param mixed[] $action
	 *
	 * @return void
	 */
	public function record_properties( $action ) {

		Automator()->db->action->add_meta(
			$action['user_id'],
			$action['action_log_id'],
			$action['action_id'],
			'properties',
			maybe_serialize( $this->get_items() )
		);

		// Remove the action from filters db to make sure subsequent actions renew the hook.
		remove_action( 'automator_action_created', array( $this, 'record_properties' ), 20 );

	}

}
