<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Token_Dependency_Extractor;

/**
 * Tree-entry projection for a filter block.
 *
 * Adds `path_codes` (the two filter branches — conditions met / not met)
 * and `depends_on` (action-token-reference scan over the block's modifier
 * fields).
 */
final class Filter_Tree_Entry extends Block_Tree_Entry {

	/**
	 * The branches this filter routes items down, such as the met and not-met paths.
	 *
	 * @var array
	 */
	private array $path_codes;

	/**
	 * Ids the filter's own conditions read from, so the tree knows what must resolve first.
	 *
	 * @var array
	 */
	private array $depends_on;

	/**
	 * Private by design: build through the named constructors below so every
	 * instance is fully projected before it is handed out.
	 *
	 */
	private function __construct() {}

	/**
	 * Which modifier kind hangs off a filter block.
	 *
	 * @return string|null Modifier type literal, or null when the block takes none.
	 */
	public static function modifier_type(): ?string {
		return Block_Modifier_Types::FILTER_CONDITION;
	}

	/**
	 * The branch a filter routes items down unless told otherwise.
	 *
	 * @return string Catalog path code.
	 */
	public static function default_path_code(): string {
		return Block_Path_Code::FILTER_CONDITIONS_MET;
	}

	/**
	 * Build from a `Block_Store::get_by_parent` / `get_children` row.
	 *
	 * @param array                      $block_row       Block storage row.
	 * @param array                      $modifiers       Pre-built modifier projections (filter_condition rows).
	 * @param array                      $paths           Pre-built path buckets.
	 * @param Token_Dependency_Extractor $token_extractor Token-reference scanner.
	 */
	public static function from_block_row(
		array $block_row,
		array $modifiers,
		array $paths,
		Token_Dependency_Extractor $token_extractor
	): self {
		$instance                  = new self();
		$instance->id              = (int) ( $block_row['id'] ?? 0 );
		$instance->block_type      = Block_Type::FILTER;
		$instance->fields          = is_array( $block_row['fields'] ?? null ) ? $block_row['fields'] : array();
		$instance->modifiers       = $modifiers;
		$instance->paths           = $paths;
		$instance->menu_order      = (int) ( $block_row['menu_order'] ?? 0 );
		$instance->legacy_group_id = $block_row['legacy_group_id'] ?? null;
		$instance->path_codes      = Block_Path_Code::for_block_type( Block_Type::FILTER );

		// depends_on: scan the filter's modifier field values for upstream
		// action token references.
		$fields_bag = array();
		foreach ( $modifiers as $modifier ) {
			foreach ( (array) ( $modifier['fields'] ?? array() ) as $field_value ) {
				$fields_bag[] = $field_value;
			}
		}
		$instance->depends_on = $token_extractor->from_fields_bag( $fields_bag, $instance->id );

		return $instance;
	}

	/**
	 * Flatten this filter block entry to the cached tree shape.
	 *
	 * @return array<string, mixed> Storage-ready projection.
	 */
	public function to_array(): array {
		return parent::to_array() + array(
			'path_codes' => $this->path_codes,
			'depends_on' => $this->depends_on,
		);
	}
}
