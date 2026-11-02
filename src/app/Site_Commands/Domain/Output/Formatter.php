<?php
/**
 * Print command output the way WP-CLI prints it.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Output;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;

/**
 * Rows become a table, JSON, CSV, a count or one field per line. Options are
 * validated before the command runs; the only late failure is JSON encoding,
 * which Run_Site_Command reports without undoing or misreporting a change.
 *
 * Output is sized before it is built: only the rows that fit the budget are
 * rendered, table cells are capped for display, and both are declared.
 */
final class Formatter {

	/** Longest cell a table shows; --field and JSON show values in full. */
	public const MAX_CELL_CHARACTERS = 500;

	/** Room kept for the notices printed after a list. */
	private const NOTICE_BYTES = 512;

	/**
	 * Encodes JSON: json_encode() here, wp_json_encode() when WordPress wires it.
	 *
	 * @var callable
	 */
	private $json_encode;

	private int $max_bytes;
	private bool $truncated = false;
	private bool $shortened = false;

	/**
	 * Items the last output displayed, when it was a list.
	 *
	 * @var int|null
	 */
	private ?int $shown = null;

	/**
	 * Choose the JSON encoder and the output budget. Domain code stays free of
	 * WordPress calls, so the caller passes wp_json_encode when WordPress is loaded.
	 *
	 * @param callable|null $json_encode Same signature as json_encode().
	 * @param int           $max_bytes   Largest output to build.
	 */
	public function __construct( ?callable $json_encode = null, int $max_bytes = Output_Budget::DEFAULT_MAX_BYTES ) {
		$this->json_encode = $json_encode ?? 'json_encode';
		$this->max_bytes   = $max_bytes;
	}

	/**
	 * Whether the last output left out items to stay within the budget.
	 *
	 * @return bool
	 */
	public function was_truncated(): bool {
		return $this->truncated;
	}

	/**
	 * Print the output of a command.
	 *
	 * @param Command_Definition         $definition The command that produced the output.
	 * @param Command_Output             $output     Its output.
	 * @param array<string, string|bool> $options    Validated options.
	 * @return string
	 *
	 * @throws Command_Error When JSON cannot be encoded.
	 */
	public function format( Command_Definition $definition, Command_Output $output, array $options ): string {
		$this->truncated = false;
		$this->shortened = false;
		$this->shown     = null;

		if ( $output->is_text() ) {
			return $output->text_output();
		}

		$rows   = $output->rows_output();
		$format = (string) ( $options[ Formats::FORMAT_OPTION ] ?? Formats::TABLE );

		if ( isset( $options[ Formats::FIELD_OPTION ] ) && $output->is_record() ) {
			$value = $rows[0][ (string) $options[ Formats::FIELD_OPTION ] ] ?? null;

			return Formats::JSON === $format ? $this->json( $value ) : $this->text( $value );
		}

		if ( isset( $options[ Formats::FIELD_OPTION ] ) ) {
			$field  = (string) $options[ Formats::FIELD_OPTION ];
			$values = array();
			foreach ( $rows as $row ) {
				$values[] = $row[ $field ] ?? null;
			}
			// As in WP-CLI, --field prints one value per line; only json changes that.
			if ( Formats::JSON === $format ) {
				$this->shown = count( $values );
				return $this->json( $values );
			}
			return $this->lines( $values );
		}

		$fields = isset( $options[ Formats::FIELDS_OPTION ] )
			? self::split_fields( (string) $options[ Formats::FIELDS_OPTION ] )
			: $definition->default_fields();

		if ( $output->is_record() ) {
			return $this->record( $fields, $rows[0] ?? array(), $format );
		}
		if ( Formats::COUNT === $format ) {
			return (string) $output->total();
		}

		$picked = array();
		foreach ( $rows as $row ) {
			$cells = array();
			foreach ( $fields as $field ) {
				$cells[ $field ] = $row[ $field ] ?? null;
			}
			$picked[] = $cells;
		}

		if ( Formats::JSON === $format ) {
			$this->shown = count( $picked );
			return $this->json( $picked );
		}

		$total       = count( $picked );
		$kept        = $this->fit( $picked, Formats::CSV !== $format );
		$this->shown = count( $kept );
		$text        = Formats::CSV === $format ? $this->csv( $fields, $kept ) : $this->table( $fields, $kept );

		return $text . $this->notices( count( $kept ), $total );
	}

	/**
	 * The leading rows whose rendered size fits the budget, measured before
	 * anything is built. Table columns are measured over every row, so a
	 * table line is never larger than estimated.
	 *
	 * @param array<int, array<string, scalar|null>> $rows   Rows.
	 * @param bool                                   $table  Whether the rows print as a table.
	 * @return array<int, array<string, scalar|null>>
	 */
	private function fit( array $rows, bool $table ): array {
		// Measuring shortens cells; only the rows printed may report that.
		$shortened = $this->shortened;
		$widths    = array();
		if ( $table ) {
			foreach ( $rows as $row ) {
				foreach ( $row as $field => $value ) {
					$widths[ $field ] = max( $widths[ $field ] ?? $this->width( (string) $field ), $this->width( $this->cell( $value ) ) );
				}
			}
		}

		// The header, the three borders of a table, and the notices count too,
		// so the output budget never cuts rows this kept.
		$used = 0;
		foreach ( array_keys( $rows[0] ?? array() ) as $field ) {
			$used += $table ? $widths[ $field ] + 3 : strlen( (string) $field ) + 3;
		}
		$used  = $table ? 4 * ( $used + 2 ) : $used + 2;
		$limit = $this->max_bytes - self::NOTICE_BYTES;
		$kept  = array();
		foreach ( $rows as $row ) {
			$size = 2;
			foreach ( $row as $field => $value ) {
				if ( $table ) {
					$cell  = $this->cell( $value );
					$size += strlen( $cell ) + $widths[ $field ] - $this->width( $cell ) + 3;
				} else {
					$text  = $this->text( $value );
					$size += strlen( $text ) + substr_count( $text, '"' ) + 3;
				}
			}
			$used += $size;
			if ( $used > $limit ) {
				$this->truncated = true;
				break;
			}
			$kept[] = $row;
		}

		$this->shortened = $shortened;

		return $kept;
	}

	/**
	 * One value per line, keeping only the lines that fit the budget. A value
	 * too long for the budget is kept only when it is the only one, so it can
	 * still be read; in a list it would hide how many items were left out.
	 *
	 * @param array<int, scalar|null> $values Values.
	 * @return string
	 */
	private function lines( array $values ): string {
		$limit = (int) ( $this->max_bytes * 0.9 );
		$used  = 0;
		$lines = array();
		foreach ( $values as $value ) {
			$line  = $this->text( $value );
			$used += strlen( $line ) + 1;
			if ( $used > $limit && count( $values ) > 1 ) {
				$this->truncated = true;
				break;
			}
			$lines[] = $line;
		}
		$this->shown = count( $lines );
		$notices     = $this->notices( count( $lines ), count( $values ) );

		// Values are printed exactly, empty ones included; only a notice with
		// nothing before it loses its separating line break.
		return array() === $lines ? substr( $notices, 1 ) : implode( "\n", $lines ) . $notices;
	}

	/**
	 * Stats about the output last formatted: how many items it shows of how
	 * many exist, what the output limit left out, warnings, then the
	 * command's own lines. A count is its own stat, so it keeps only the
	 * warnings, such as entries that could not be read.
	 *
	 * @param Command_Definition         $definition The command that produced the output.
	 * @param Command_Output             $output     Its output.
	 * @param array<string, string|bool> $options    Validated options.
	 * @param bool                       $cut        Whether the output budget cut the formatted text, which
	 *                                               makes the number of items shown unknown.
	 * @return string
	 */
	public function stats( Command_Definition $definition, Command_Output $output, array $options, bool $cut = false ): string {
		if ( Formats::COUNT === ( $options[ Formats::FORMAT_OPTION ] ?? null ) && ! isset( $options[ Formats::FIELD_OPTION ] ) ) {
			return implode( "\n", $output->warnings() );
		}

		$lines = array();
		if ( ! $output->is_text() && ! $output->is_record() ) {
			$retrieved = count( $output->rows_output() );
			$shown     = $this->shown ?? $retrieved;
			$total     = $output->total();
			if ( $cut ) {
				$lines[] = sprintf( '%s: %d found. The output was cut at the size limit, so only some are shown. Narrow the command, or use fewer --fields.', $definition->label(), $total );
				return implode( "\n", array_merge( $lines, $output->warnings(), $output->stats() ) );
			}
			$lines[] = $shown < $total
				? sprintf( '%s: %d of %d listed.', $definition->label(), $shown, $total )
				: sprintf( '%s: %d listed.', $definition->label(), $total );
			if ( $shown < $retrieved ) {
				$lines[] = sprintf( 'The output limit left out %d of the %d items read. Narrow the command, or use fewer --fields.', $retrieved - $shown, $retrieved );
			}
		}

		// Warnings come before the command's own lines, so a cut never drops them.
		return implode( "\n", array_merge( $lines, $output->warnings(), $output->stats() ) );
	}

	/**
	 * Notices that say what the output leaves out.
	 *
	 * @param int $shown Items shown.
	 * @param int $total Items found.
	 * @return string
	 */
	private function notices( int $shown, int $total ): string {
		$notices = '';
		if ( $shown < $total ) {
			$notices .= "\n" . sprintf( 'Output truncated: showing %d of %d items. Narrow the command with its filters, --fields or --format=count.', $shown, $total );
		}
		if ( $this->shortened ) {
			$notices .= "\n" . sprintf( 'Some values were shortened to %d characters. Use --field=<name> or --format=json to read them in full.', self::MAX_CELL_CHARACTERS );
		}

		return $notices;
	}

	/**
	 * One item as WP-CLI prints it: a Field and Value table, a JSON object,
	 * or Field,Value CSV lines.
	 *
	 * @param string[]                   $fields Fields to show, in order.
	 * @param array<string, scalar|null> $item   The item.
	 * @param string                     $format Output format.
	 * @return string
	 *
	 * @throws Command_Error When JSON cannot be encoded.
	 */
	private function record( array $fields, array $item, string $format ): string {
		$picked = array();
		foreach ( $fields as $field ) {
			$picked[ $field ] = $item[ $field ] ?? null;
		}
		if ( Formats::JSON === $format ) {
			return $this->json( $picked );
		}

		// As in WP-CLI, a single item prints booleans as true or false.
		$pairs = array();
		foreach ( $picked as $field => $value ) {
			$pairs[] = array(
				'Field' => $field,
				'Value' => is_bool( $value ) ? ( $value ? 'true' : 'false' ) : $value,
			);
		}

		return Formats::CSV === $format
			? $this->csv( array( 'Field', 'Value' ), $pairs )
			: $this->table( array( 'Field', 'Value' ), $pairs ) . $this->notices( 1, 1 );
	}

	/**
	 * Split a --fields value into unique, trimmed names.
	 *
	 * @param string $value Comma-separated fields.
	 * @return string[]
	 */
	public static function split_fields( string $value ): array {
		return array_values( array_unique( array_map( 'trim', explode( ',', $value ) ) ) );
	}

	/**
	 * Encode as JSON, keeping value types. Invalid UTF-8 from plugin headers
	 * is replaced, not allowed to turn the whole listing into nothing.
	 *
	 * @param mixed $rows Rows, an item, the values of one field, or one value.
	 * @return string
	 *
	 * @throws Command_Error When encoding still fails.
	 */
	private function json( $rows ): string {
		if ( ! function_exists( 'json_encode' ) ) {
			throw new Command_Error( 'JSON output needs the PHP json extension, which this site does not have. Use --format=table or --format=csv.' );
		}
		$estimate = $this->json_size( $rows );
		if ( $estimate > $this->max_bytes ) {
			throw new Command_Error( sprintf( 'The JSON output would be about %d bytes, over the %d-byte limit. Narrow the command with its filters or --fields, or use --format=table or --format=count.', $estimate, $this->max_bytes ) );
		}
		$json = call_user_func( $this->json_encode, $rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
		if ( false === $json ) {
			throw new Command_Error( 'The output could not be encoded as JSON. Use --format=table instead.' );
		}

		return $json;
	}

	/**
	 * A lower bound on the encoded size, measured without encoding, so output
	 * that would fit is never refused. The encoded text is measured again by
	 * the output budget.
	 *
	 * @param mixed $value Value to encode.
	 * @return int
	 */
	private function json_size( $value ): int {
		if ( is_array( $value ) ) {
			$list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			$size = 2 + max( 0, count( $value ) - 1 );
			foreach ( $value as $key => $item ) {
				$size += ( $list ? 0 : $this->json_string_size( (string) $key ) + 1 ) + $this->json_size( $item );
			}

			return $size;
		}
		if ( is_string( $value ) ) {
			return $this->json_string_size( $value );
		}
		if ( null === $value ) {
			return 4;
		}

		return strlen( $this->text( $value ) );
	}

	/**
	 * The encoded size of a string: its quotes, and one more byte for each
	 * character JSON escapes, the least an escape adds.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private function json_string_size( string $text ): int {
		return strlen( $text ) + 2 + strlen( $text ) - strlen( str_replace( array( '"', '\\' ), '', $text ) ) + (int) preg_match_all( '/[\x00-\x1f]/', $text );
	}

	/**
	 * Rows as CSV with a header line, quoted the way fputcsv() quotes. Built
	 * in plain PHP: some hosts restrict the stream functions fputcsv() needs.
	 *
	 * @param string[]                               $fields Columns.
	 * @param array<int, array<string, scalar|null>> $rows   Rows.
	 * @return string
	 */
	private function csv( array $fields, array $rows ): string {
		$lines = array( $this->csv_line( $fields ) );
		foreach ( $rows as $row ) {
			$lines[] = $this->csv_line( array_values( $row ) );
		}

		return implode( "\n", $lines );
	}

	/**
	 * One CSV line.
	 *
	 * @param array<int, scalar|null> $values Values.
	 * @return string
	 */
	private function csv_line( array $values ): string {
		$cells = array();
		foreach ( $values as $value ) {
			$text    = $this->text( $value );
			$cells[] = 1 === preg_match( '/[,"\\\\\r\n\t ]/', $text ) ? '"' . str_replace( '"', '""', $text ) . '"' : $text;
		}

		return implode( ',', $cells );
	}

	/**
	 * A bordered ASCII table. An empty list still prints its header, as
	 * WP-CLI does, so "no items" never looks like a failure.
	 *
	 * @param string[]                               $fields Columns.
	 * @param array<int, array<string, scalar|null>> $rows   Rows.
	 * @return string
	 */
	private function table( array $fields, array $rows ): string {
		$widths = array();
		foreach ( $fields as $field ) {
			$widths[ $field ] = $this->width( $field );
			foreach ( $rows as $row ) {
				$widths[ $field ] = max( $widths[ $field ], $this->width( $this->cell( $row[ $field ] ) ) );
			}
		}

		$border = '+';
		foreach ( $widths as $width ) {
			$border .= str_repeat( '-', $width + 2 ) . '+';
		}

		$lines = array( $border, $this->line( array_combine( $fields, $fields ), $widths ), $border );
		foreach ( $rows as $row ) {
			$lines[] = $this->line( $row, $widths );
		}
		if ( array() !== $rows ) {
			$lines[] = $border;
		}

		return implode( "\n", $lines );
	}

	/**
	 * One table line.
	 *
	 * @param array<string, scalar|null> $row    Cells by field.
	 * @param array<string, int>         $widths Column widths.
	 * @return string
	 */
	private function line( array $row, array $widths ): string {
		$line = '|';
		foreach ( $widths as $field => $width ) {
			$cell  = $this->cell( $row[ $field ] ?? null );
			$line .= ' ' . $cell . str_repeat( ' ', $width - $this->width( $cell ) ) . ' |';
		}

		return $line;
	}

	/**
	 * A cell on one line.
	 *
	 * @param scalar|null $value Cell value.
	 * @return string
	 */
	private function cell( $value ): string {
		$text = str_replace( array( "\r\n", "\r", "\n" ), ' ', $this->text( $value ) );
		if ( strlen( $text ) <= self::MAX_CELL_CHARACTERS ) {
			return $text;
		}
		$short = function_exists( 'mb_substr' )
			? mb_substr( $text, 0, self::MAX_CELL_CHARACTERS, 'UTF-8' )
			: ( 1 === preg_match( '/^.{0,' . self::MAX_CELL_CHARACTERS . '}/su', $text, $match ) ? $match[0] : substr( $text, 0, self::MAX_CELL_CHARACTERS ) );
		if ( $short === $text ) {
			return $text;
		}
		$this->shortened = true;

		return $short . '…';
	}

	/**
	 * A value as text: false and null print empty, true prints 1, as WP-CLI does.
	 *
	 * @param scalar|null $value Value.
	 * @return string
	 */
	private function text( $value ): string {
		return (string) $value;
	}

	/**
	 * Display width of text.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private function width( string $text ): int {
		if ( function_exists( 'mb_strwidth' ) ) {
			return mb_strwidth( $text, 'UTF-8' );
		}
		$characters = preg_match_all( '/./su', $text );

		return false === $characters ? strlen( $text ) : $characters;
	}
}
