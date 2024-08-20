<?php
namespace Uncanny_Automator\Services\Loopable;

use JsonSerializable;

/**
 * Loopable_Token_Collection
 *
 * @since 5.10
 *
 * @package Uncanny_Automator\Services\Loopable
 */
class Loopable_Token_Collection implements JsonSerializable {

	/**
	 * The item collection.
	 *
	 * @var mixed[]
	 */
	protected $items = array();

	/**
	 * Creates a new item that can be iterated.
	 *
	 * @param mixed[] $item
	 *
	 * @return void
	 */
	public function create_item( array $item ) {
		$this->items[] = $item;
	}

	/**
	 * @todo - Remove #[\ReturnTypeWillChange] when dropping 7.0 and use array as return type
	 *
	 * @return array
	 */
	#[\ReturnTypeWillChange] // @phpstan-ignore-line (Return type not supported in 7.0)
	public function jsonSerialize() {
		return $this->items;
	}
}
