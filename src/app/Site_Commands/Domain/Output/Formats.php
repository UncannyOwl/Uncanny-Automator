<?php
/**
 * Output formats and output options for commands that list items.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Output;

/**
 * The WP-CLI formats this runtime implements. Others are refused.
 */
final class Formats {

	public const TABLE = 'table';
	public const JSON  = 'json';
	public const CSV   = 'csv';
	public const COUNT = 'count';

	public const ALL = array( self::TABLE, self::JSON, self::CSV, self::COUNT );

	/**
	 * Formats for a single item, which WP-CLI prints as Field and Value.
	 */
	public const RECORD = array( self::TABLE, self::JSON, self::CSV );

	public const FORMAT_OPTION = 'format';
	public const FIELDS_OPTION = 'fields';
	public const FIELD_OPTION  = 'field';

	/**
	 * Option names reserved for item lists.
	 */
	public const OPTIONS = array( self::FORMAT_OPTION, self::FIELDS_OPTION, self::FIELD_OPTION );
}
