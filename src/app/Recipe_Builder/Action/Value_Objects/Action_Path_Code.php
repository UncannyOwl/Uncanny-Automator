<?php
// phpcs:disable WordPress.Security.EscapeOutput
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use InvalidArgumentException;

/**
 * Action Path Code value object.
 *
 * Identifies which branch (path) of a parent block an action lives in
 * (e.g. `FILTER-CONDITIONS-NOT-MET`). The sibling of {@see Action_Parent_Id}:
 * parent_id says *which* block, path_code says *which branch of it*.
 *
 * Validity here is well-formedness only — the value must be a known
 * `Block_Path_Code`. Whether the path is valid *for a specific block type*
 * needs the parent block and stays a service-level check.
 *
 * Shared type, not Action-private: the validity authority is
 * {@see Block_Path_Code} so a future Block aggregate can carry the same
 * concept.
 */
final class Action_Path_Code {

	/**
	 * The validated path code string (e.g. `FILTER-CONDITIONS-NOT-MET`).
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Accept a path code, rejecting anything the block-path vocabulary does not define.
	 *
	 * @param string $value A known path code from {@see Block_Path_Code}.
	 * @throws InvalidArgumentException When `$value` is not a known path code.
	 */
	public function __construct( string $value ) {
		if ( ! Block_Path_Code::is_valid( $value ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Invalid action path code: "%s".', $value )
			);
		}
		$this->value = $value;
	}

	/**
	 * Get the path code string.
	 *
	 * @return string
	 */
	public function get_value(): string {
		return $this->value;
	}
}
