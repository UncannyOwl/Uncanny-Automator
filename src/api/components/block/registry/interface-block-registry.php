<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Block\Registry;

use Uncanny_Automator\Api\Components\Block\Block;
use Uncanny_Automator\Api\Components\Block\Block_Config;

/**
 * Block Registry Interface.
 *
 * Contract for block registration and discovery.
 *
 * @package Uncanny_Automator\Api\Components\Block\Registry
 * @since 7.0.0
 */
interface Block_Registry {

	/**
	 * Get all available blocks.
	 *
	 * @return array Array of Block objects.
	 */
	public function get_available_blocks(): array;

	/**
	 * Get specific block definition.
	 *
	 * @param string $type Block type.
	 * @return Block|null Block object or null if not found.
	 */
	public function get_block_definition( string $type ): ?Block;

	/**
	 * Register a block.
	 *
	 * @param Block_Config $config Block configuration.
	 * @return void
	 */
	public function register_block( Block_Config $config ): void;

	/**
	 * Check if block is registered.
	 *
	 * @param string $type Block type.
	 * @return bool True if registered.
	 */
	public function is_registered( string $type ): bool;
}
