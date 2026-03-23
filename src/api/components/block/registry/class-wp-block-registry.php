<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Block\Registry;

use Uncanny_Automator\Api\Components\Block\Block;
use Uncanny_Automator\Api\Components\Block\Block_Config;
use Uncanny_Automator\Api\Components\Block\Enums\Block_Type;
use Uncanny_Automator\Api\Components\Block\Registry\Blocks\Abstract_Block;
use Uncanny_Automator\Api\Components\Block\Registry\Blocks\Delay_Block;
use Uncanny_Automator\Api\Components\Block\Registry\Blocks\Filter_Block;
use Uncanny_Automator\Api\Components\Block\Registry\Blocks\Loop_Block;

/**
 * WordPress Block Registry.
 *
 * WordPress implementation of block registry for hardcoded block definitions.
 *
 * @package Uncanny_Automator\Api\Components\Block\Registry
 * @since 7.0.0
 */
class WP_Block_Registry implements Block_Registry {

	/**
	 * Registered blocks.
	 *
	 * @var array<string, Block>
	 */
	private array $blocks = array();

	/**
	 * Block definition classes.
	 *
	 * @var array<string, class-string<Abstract_Block>>
	 */
	private array $block_classes = array();

	/**
	 * Whether blocks have been initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_block_classes();
	}

	/**
	 * Register block definition classes.
	 *
	 * @return void
	 */
	private function register_block_classes(): void {
		$this->block_classes = array(
			Block_Type::DELAY_SCHEDULE => Delay_Block::class,
			Block_Type::FILTER         => Filter_Block::class,
			Block_Type::LOOP           => Loop_Block::class,
		);
	}

	/**
	 * Initialize blocks from registered classes.
	 *
	 * @return void
	 */
	private function ensure_initialized(): void {
		if ( $this->initialized ) {
			return;
		}

		foreach ( $this->block_classes as $class ) {
			$block_instance = new $class();
			$config         = $block_instance->get_config();
			$this->register_block( $config );
		}

		$this->initialized = true;
	}

	/**
	 * Get all available blocks.
	 *
	 * @return array Array of Block objects.
	 */
	public function get_available_blocks(): array {
		$this->ensure_initialized();
		return array_values( $this->blocks );
	}

	/**
	 * Get specific block definition.
	 *
	 * @param string $type Block type.
	 * @return Block|null Block object or null if not found.
	 */
	public function get_block_definition( string $type ): ?Block {
		$this->ensure_initialized();
		return $this->blocks[ $type ] ?? null;
	}

	/**
	 * Register a block.
	 *
	 * @param Block_Config $config Block configuration.
	 * @return void
	 */
	public function register_block( Block_Config $config ): void {
		$block                               = new Block( $config );
		$this->blocks[ $config->get_type() ] = $block;
	}

	/**
	 * Check if block is registered.
	 *
	 * @param string $type Block type.
	 * @return bool True if registered.
	 */
	public function is_registered( string $type ): bool {
		$this->ensure_initialized();
		return isset( $this->blocks[ $type ] );
	}
}
