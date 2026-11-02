<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects;

use InvalidArgumentException;

/**
 * The rule that rejected a candidate at a landing position, and where that rule came from.
 *
 * Immutable. A block or path row always carries a reason (`Block_Unsupported_Entity` requires one); a
 * candidate's own rule may not, so `reason` is empty only for `SOURCE_CANDIDATE`.
 */
final class Placement_Violation {

	/**
	 * An ancestor's block-level `unsupported_entities` row.
	 *
	 * @var string
	 */
	public const SOURCE_BLOCK = 'block';

	/**
	 * An ancestor's per-path `unsupported_entities` row, on the path the chain passes through.
	 *
	 * @var string
	 */
	public const SOURCE_PATH = 'path';

	/**
	 * The candidate's own `required_block_ancestors` rule.
	 *
	 * @var string
	 */
	public const SOURCE_CANDIDATE = 'candidate';

	/**
	 * Every accepted source.
	 *
	 * @var string[]
	 */
	private const SOURCES = array( self::SOURCE_BLOCK, self::SOURCE_PATH, self::SOURCE_CANDIDATE );

	/**
	 * Where the rejecting rule came from.
	 *
	 * @var string
	 */
	private string $source;

	/**
	 * The rule's reason; empty when it carries none.
	 *
	 * @var string
	 */
	private string $reason;

	/**
	 * The rejecting ancestor's block type for a row; the required block type for a candidate rule.
	 *
	 * @var string
	 */
	private string $ancestor_block_type;

	/**
	 * Wrap the three facts; refuse an unknown source.
	 *
	 * @param string $source              One of the SOURCE_* constants.
	 * @param string $reason              The row's or rule's reason; '' when the rule carries none.
	 * @param string $ancestor_block_type The rejecting ancestor's block type, or the block type a candidate rule requires.
	 *
	 * @throws InvalidArgumentException On a source outside the three.
	 */
	public function __construct( string $source, string $reason, string $ancestor_block_type ) {
		if ( ! in_array( $source, self::SOURCES, true ) ) {
			throw new InvalidArgumentException( 'Placement violation source must be block, path or candidate.' );
		}

		$this->source              = $source;
		$this->reason              = $reason;
		$this->ancestor_block_type = $ancestor_block_type;
	}

	/**
	 * Where the rejecting rule came from.
	 *
	 * @return string
	 */
	public function get_source(): string {
		return $this->source;
	}

	/**
	 * The rule's reason; empty when it carries none.
	 *
	 * @return string
	 */
	public function get_reason(): string {
		return $this->reason;
	}

	/**
	 * The rejecting ancestor's block type, or the block type a candidate rule requires.
	 *
	 * @return string
	 */
	public function get_ancestor_block_type(): string {
		return $this->ancestor_block_type;
	}

	/**
	 * The three facts, for an error payload.
	 *
	 * @return array{source:string, reason:string, ancestor_block_type:string}
	 */
	public function to_wire(): array {
		return array(
			'source'              => $this->source,
			'reason'              => $this->reason,
			'ancestor_block_type' => $this->ancestor_block_type,
		);
	}
}
