<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Blocks\Traits;

/**
 * Trait for recipe block-specific REST properties.
 *
 * Provides getters and setters for properties specific to recipe blocks
 * (filter, loop, delay).
 *
 * @since 7.0
 */
trait Recipe_Block_Properties {

	/**
	 * The block type
	 *
	 * @var string
	 */
	private string $block_type;

	/**
	 * The block ID
	 *
	 * @var string|null
	 */
	private ?string $block_id;

	/**
	 * Set the block type.
	 *
	 * @param string $block_type The block type.
	 *
	 * @return void
	 */
	protected function set_block_type( string $block_type ): void {
		$this->block_type = $block_type;
	}

	/**
	 * Set the block ID.
	 *
	 * @param string|null $block_id The block ID.
	 *
	 * @return void
	 */
	protected function set_block_id( ?string $block_id ): void {
		$this->block_id = $block_id;
	}

	/**
	 * Get the block type.
	 *
	 * @return string
	 */
	protected function get_block_type(): string {
		return $this->block_type;
	}

	/**
	 * Get the block ID.
	 *
	 * @return string|null
	 */
	protected function get_block_id(): ?string {
		return $this->block_id;
	}
}
