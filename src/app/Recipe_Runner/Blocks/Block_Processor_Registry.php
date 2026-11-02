<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Blocks;

/**
 * Block type to processor. One processor per block type; the last registration wins.
 */
final class Block_Processor_Registry {

	/**
	 * Registered processors keyed by block type.
	 *
	 * @var array<string, Block_Processor>
	 */
	private array $processors = array();

	/**
	 * Register the processor for a block type.
	 *
	 * @param string          $block_type One of the `Block_Type::*` strings.
	 * @param Block_Processor $processor  The implementation.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When the block type is empty.
	 */
	public function register( string $block_type, Block_Processor $processor ): void {
		if ( '' === $block_type ) {
			throw new \InvalidArgumentException( 'A block processor needs a block type.' );
		}

		$this->processors[ $block_type ] = $processor;
	}

	/**
	 * The processor for a block type, or null when none is registered.
	 *
	 * @param string $block_type Block type.
	 *
	 * @return Block_Processor|null
	 */
	public function processor_for( string $block_type ): ?Block_Processor {
		return $this->processors[ $block_type ] ?? null;
	}

	/**
	 * Every registered processor keyed by block type.
	 *
	 * @return array<string, Block_Processor>
	 */
	public function all(): array {
		return $this->processors;
	}
}
