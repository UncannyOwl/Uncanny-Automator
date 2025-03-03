<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Services\Loopable\Data_Integrations;

use RuntimeException;

/**
 * Class Csv_To_Json_Converter
 *
 * A robust class to convert CSV files to JSON format.
 * Supports CSV input from URL, file path, and raw text.
 *
 * Features:
 * - Load CSV from URL (using wp_remote_get for WordPress compatibility).
 * - Load CSV from an absolute file path.
 * - Load CSV from a string of text.
 * - Set the header row, start row, end row, and delimiter for flexible data parsing.
 * - Option to auto-detect the CSV delimiter.
 *
 * @package Uncanny_Automator\Services\Loopable\Data_Integrations
 */
class Csv_To_Json_Converter {

	/**
	 * @var array Parsed CSV data.
	 */
	private $data = array();

	/**
	 * @var int Header row index (0-based).
	 */
	private $header_row = 0;

	/**
	 * @var int Start row index (0-based).
	 */
	private $start_row = 1;

	/**
	 * @var int|null End row index (0-based), or null for no limit.
	 */
	private $end_row = null;

	/**
	 * @var string Delimiter used for parsing CSV (default is comma).
	 */
	private $delimiter = ',';

	/**
	 * @var bool Flag for auto-detecting the delimiter.
	 */
	private $auto_detect_delimiter = false;

	/**
	 * @var array Supported delimiters
	 */
	const DELIMETERS = array(
		'semicolon' => ';',
		'comma'     => ',',
		'pipe'      => '|',
		'tab'       => "\\t",
	);

	/**
	 * Load CSV data from a URL using wp_remote_get.
	 *
	 * @param string $url The URL of the CSV file.
	 * @return self Returns the current instance.
	 * @throws RuntimeException If the CSV data cannot be fetched.
	 */
	public function load_from_url( $url ) {
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( 'Failed to fetch the CSV from the provided URL.' );
		}

		$csv_data = wp_remote_retrieve_body( $response );
		if ( empty( $csv_data ) ) {
			throw new RuntimeException( 'No content found in the provided URL.' );
		}

		$this->parse_csv( $csv_data );
		return $this;
	}

	/**
	 * Load CSV data from an absolute file path.
	 *
	 * @param string $file_path The absolute file path of the CSV file.
	 * @return self Returns the current instance.
	 * @throws RuntimeException If the file cannot be read.
	 */
	public function load_from_file_path( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			throw new RuntimeException( 'The file does not exist or is not readable.' );
		}

		// Local file.
		$csv_data = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->parse_csv( $csv_data );
		return $this;
	}

	/**
	 * Load CSV data from raw text.
	 *
	 * @param string $text The raw CSV data as a string.
	 * @return self Returns the current instance.
	 * @throws RuntimeException If the provided CSV text is empty.
	 */
	public function load_from_text( $text ) {
		if ( empty( $text ) ) {
			throw new RuntimeException( 'The provided CSV text is empty.' );
		}

		$this->parse_csv( $text );
		return $this;
	}

	/**
	 * Set the header row for the CSV data.
	 *
	 * @param int $row The index of the header row (0-based).
	 * @return self Returns the current instance.
	 */
	public function set_header_row( $row ) {
		$this->header_row = $row;
		return $this;
	}

	/**
	 * Set the start row for data parsing.
	 *
	 * @param int $row The index of the start row (0-based).
	 * @return self Returns the current instance.
	 */
	public function set_start_row( $row ) {
		$this->start_row = $row;
		return $this;
	}

	/**
	 * Set the end row for data parsing.
	 *
	 * @param int|null $row The index of the end row (0-based) or null for no limit.
	 * @return self Returns the current instance.
	 */
	public function set_end_row( $row ) {
		$this->end_row = $row;
		return $this;
	}

	/**
	 * Set the delimiter for parsing the CSV data.
	 *
	 * @param string $delimiter The delimiter used in the CSV file (e.g., ',', ';', '\t').
	 * @return self Returns the current instance.
	 */
	public function set_delimiter( $delimiter ) {
		$this->delimiter             = $delimiter;
		$this->auto_detect_delimiter = false; // Disable auto-detect if a custom delimiter is set.
		return $this;
	}

	/**
	 * Enable or disable auto-detection of the delimiter.
	 *
	 * @param bool $auto Whether to enable auto-detection of the delimiter.
	 * @return self Returns the current instance.
	 */
	public function set_auto_detect_delimiter( $auto = true ) {
		$this->auto_detect_delimiter = $auto;
		return $this;
	}

	/**
	 * Parse the CSV data from a raw string.
	 *
	 * @param string $csv_data Raw CSV data as a string.
	 * @return void
	 */
	private function parse_csv( $csv_data ) {

		// If auto-detect is enabled, try to detect the delimiter.
		if ( $this->auto_detect_delimiter ) {
			$this->delimiter = $this->detect_delimiter( $csv_data );
		}

		$rows = str_getcsv( $csv_data, "\n" );

		$this->data = array_map(
			function( $row ) {
				return str_getcsv( $row, $this->delimiter );
			},
			$rows
		);
	}

	/**
	 * Attempt to detect the delimiter used in the CSV data.
	 *
	 * @param string $csv_data Raw CSV data as a string.
	 * @return string The detected delimiter (comma by default if unable to detect).
	 */
	private function detect_delimiter( $csv_data ) {

		$delimiters = array( ',', ';', "\t", '|' );
		$counts     = array();

		foreach ( $delimiters as $delimiter ) {
			$count                = substr_count( $csv_data, $delimiter );
			$counts[ $delimiter ] = $count;
		}

		// Find the delimiter with the highest occurrence.
		$most_frequent_delimiter = array_search( max( $counts ), $counts, true );

		// If no delimiter is found, return ',' as default.
		if ( false === $most_frequent_delimiter ) {
			return ',';
		}

		return $most_frequent_delimiter;
	}


	/**
	 * Convert the parsed CSV data to JSON format.
	 *
	 * @return string JSON representation of the CSV data.
	 */
	public function to_json() {
		$header = isset( $this->data[ $this->header_row ] ) ? $this->data[ $this->header_row ] : array();
		$output = array();

		$max = count( $this->data );

		for ( $i = $this->start_row; $i < $max; $i++ ) {
			if ( null !== $this->end_row && $i > $this->end_row ) {
				break;
			}

			// Length must be the same to avoid fatal errors.
			if ( count( (array) $header ) === count( (array) $this->data[ $i ] ) ) {
				$row      = array_combine( (array) $header, (array) $this->data[ $i ] );
				$output[] = $row;
			}
		}

		return wp_json_encode( $output, true );
	}

	/**
	 * Convert the parsed CSV data to JSON format as a numeric array.
	 *
	 * @return string|false JSON representation of the CSV data with numeric keys.
	 */
	public function to_json_numeric() {

		$output = array();
		$max    = count( $this->data );

		for ( $i = $this->start_row; $i < $max; $i++ ) {
			if ( null !== $this->end_row && $i > $this->end_row ) {
				break;
			}
			$output[] = $this->data[ $i ]; // Push each row as a numeric array.
		}

		return wp_json_encode( $output, true );
	}

	/**
	 * Count the number of columns in the CSV data.
	 *
	 * @param int $row The row index to count the columns from (defaults to header row).
	 * @return int Number of columns in the specified row.
	 */
	public function count_columns( $row = null ) {

		// Use the header row by default.
		$row = null === $row ? $this->header_row : $row;

		if ( isset( $this->data[ $row ] ) ) {
			return count( $this->data[ $row ] );
		}

		return 0; // If the row doesn't exist, return 0.
	}

	/**
	 * Convert a numeric index to an "Excel-style" alphabetic representation.
	 * Example: 0 -> 'A', 1 -> 'B', 26 -> 'AA', 27 -> 'AB', etc.
	 *
	 * @param int $number The number to convert.
	 * @return string The alphabetic representation.
	 */
	public function number_to_alpha( $number ) {
		$alpha = '';

		while ( $number >= 0 ) {
			$alpha  = chr( ( $number % 26 ) + 65 ) . $alpha;
			$number = floor( $number / 26 ) - 1;
		}

		return $alpha;
	}
}
