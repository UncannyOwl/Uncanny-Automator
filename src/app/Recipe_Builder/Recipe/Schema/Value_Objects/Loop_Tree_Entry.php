<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects\Loop_Field_Translator;

/**
 * Tree-entry projection for a loop block.
 *
 * The loop's storage-shape `iterable_expression` array is translated into
 * the canonical `LOOP_TYPE` / `LOOP_TOKEN` field map via
 * {@see Loop_Field_Translator::to_fields()} — same translator the
 * frontend wire serializer consumes, so the tree and wire stay in sync.
 *
 * Adds `tokens` (empty PoC, Pro hooks enrich at projection time) and
 * `path_codes` (just LOOP_ITERATION_COMPLETED).
 */
final class Loop_Tree_Entry extends Block_Tree_Entry {

	/**
	 * Loop tokens. Always empty: nothing enriches this slot, and the
	 * projection has no filter seam for Pro to hook. The FE tolerates the
	 * empty array per its Zod schema.
	 *
	 * @var array<int,mixed>
	 */
	private array $tokens;

	/**
	 * Path codes this block type exposes (just LOOP_ITERATION_COMPLETED).
	 *
	 * @var string[]
	 */
	private array $path_codes;

	/**
	 * Frozen VO — construct via {@see self::from_block_row()} only.
	 */
	private function __construct() {}

	/**
	 * The modifier type loops accept (loop filters).
	 *
	 * @return string|null
	 */
	public static function modifier_type(): ?string {
		return Block_Modifier_Types::LOOP_FILTER;
	}

	/**
	 * The path bucket loop children live under.
	 *
	 * @return string
	 */
	public static function default_path_code(): string {
		return Block_Path_Code::LOOP_ITERATION_COMPLETED;
	}

	/**
	 * Build the tree entry from a Block_Store-shape row plus its already-built
	 * modifier and path projections.
	 *
	 * @param array<string,mixed> $block_row Block_Store loop row.
	 * @param array<int,mixed>    $modifiers Built loop-filter modifier nodes.
	 * @param array<int,mixed>    $paths     Built path projections (children).
	 * @return self
	 */
	public static function from_block_row( array $block_row, array $modifiers, array $paths ): self {
		$instance             = new self();
		$instance->id         = (int) ( $block_row['id'] ?? 0 );
		$instance->block_type = Block_Type::LOOP;

		// Loop blocks store the FE's structured fields verbatim — stored keys
		// win so the config panel round-trips its own shape. But expression-
		// only writers (Pro's Token_Loop_Auto, folded legacy loops) leave the
		// stored map partial or absent, so the translated iterable_expression
		// fills every missing key — otherwise the dialog renders an empty
		// Token field while the runtime iterates fine.
		$stored_fields = $block_row['fields'] ?? array();
		$stored_fields = is_array( $stored_fields ) ? $stored_fields : array();

		$expression = $block_row['iterable_expression'] ?? array();
		$translated = Loop_Field_Translator::to_fields( is_array( $expression ) ? $expression : array() );

		$instance->fields = $stored_fields + $translated;

		$instance->modifiers       = $modifiers;
		$instance->paths           = $paths;
		$instance->menu_order      = (int) ( $block_row['menu_order'] ?? 0 );
		$instance->legacy_group_id = $block_row['legacy_group_id'] ?? null;
		// TODO(unshipped-seam): loop tokens are never populated. There is no
		// `apply_filters` site for `automator_wire_loop_tokens` anywhere in
		// Free, so a Pro listener could not fire even if one were registered —
		// wiring the seam is a prerequisite, not just the listener.
		$instance->tokens     = array();
		$instance->path_codes = Block_Path_Code::for_block_type( Block_Type::LOOP );

		return $instance;
	}

	/**
	 * Canonical tree-node array: the shared block shape plus the loop-only
	 * `tokens` and `path_codes` keys.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return parent::to_array() + array(
			'tokens'     => $this->tokens,
			'path_codes' => $this->path_codes,
		);
	}
}
