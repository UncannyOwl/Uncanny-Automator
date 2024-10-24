<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Services\Loopable\Data_Integrations;

use Exception;

/**
 * Class Json_To_Array_Converter.
 *
 * A utility class that converts JSON content into an associative array.
 * Supports JSON input via string, file path, or URL, making it versatile for different sources.
 *
 * @package uncanny-automator
 */
class Json_To_Array_Converter {

	/**
	 * Converts JSON content to an associative array.
	 *
	 * This method accepts a JSON string, file path, or URL and converts
	 * the valid JSON into an associative array.
	 *
	 * @param string $input JSON string, file path, or URL.
	 *
	 * @return array Associative array representation of the JSON data.
	 *
	 * @throws Exception If the input is invalid or the file cannot be found.
	 */
	public function convert( $input ) {
		$json_content = $this->get_json_content( $input );
		$this->validate_json( $json_content );

		return json_decode( $json_content, true );
	}

	/**
	 * Retrieves the JSON content from the given input (URL, file path, or string).
	 *
	 * Handles fetching JSON data from various input formats, including URLs, file paths,
	 * or raw JSON strings. Throws an exception if the file or URL is not accessible.
	 *
	 * @param string $input The input string, which could be a URL, file path, or JSON string.
	 *
	 * @return string The raw JSON content as a string.
	 *
	 * @throws Exception If the file or URL cannot be accessed, or if the input is invalid.
	 */
	private function get_json_content( $input ) {

		// Check if the input is a valid URL and fetch the JSON data via HTTP.
		if ( $this->is_url( $input ) ) {

			$response = wp_remote_get( $input );

			// Handle HTTP request errors.
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				throw new Exception(
					sprintf(
						/* translators: Error message */
						__( 'Error fetching the JSON from the URL: %s', 'uncanny-automator' ),
						$error_message
					)
				);
			}

			$status_code = wp_remote_retrieve_response_code( $response );

			// Check for successful status code (200 OK).
			if ( 200 !== $status_code ) {
				throw new Exception(
					sprintf(
						/* translators: Error message */
						__( 'HTTP error: Received status code %d while fetching the JSON from the URL.', 'uncanny-automator' ),
						$status_code
					)
				);
			}

			return wp_remote_retrieve_body( $response );
		}

		// If input is a valid file path, retrieve the contents.
		if ( file_exists( $input ) && is_readable( $input ) ) {
			// Local file, no need to use wp_remote_*.
			return file_get_contents( $input ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		// If input is a valid JSON string, return it as-is.
		if ( $this->is_json( $input ) ) {
			return $input;
		}

		// If none of the conditions are met, throw an exception for invalid input.
		throw new Exception(
			/* translators: Error exception message */
			__( 'Input must be a valid URL, file path, or JSON string.', 'uncanny-automator' )
		);
	}

	/**
	 * Validates if the provided string is valid JSON.
	 *
	 * This method checks whether the provided JSON content is well-formed
	 * and throws an exception if it is not.
	 *
	 * @param string $json_content The raw JSON content as a string.
	 *
	 * @return void
	 *
	 * @throws Exception If the JSON content is invalid.
	 */
	private function validate_json( $json_content ) {
		json_decode( $json_content );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error_message = json_last_error_msg();
			throw new Exception(
				sprintf(
					/* translators: Error exception message */
					__( 'Invalid JSON data: %s', 'uncanny-automator' ),
					$error_message
				)
			);
		}
	}

	/**
	 * Determines if the input string is a valid URL.
	 *
	 * Uses PHP's `filter_var` function to check if the provided input is a valid URL.
	 *
	 * @param string $input The input string to validate.
	 *
	 * @return bool True if the input is a valid URL, false otherwise.
	 */
	private function is_url( $input ) {
		return filter_var( $input, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Determines if the input string is valid JSON.
	 *
	 * Decodes the string and checks for any JSON parsing errors.
	 *
	 * @param string $input The input string to validate.
	 *
	 * @return bool True if the input is a valid JSON string, false otherwise.
	 */
	private function is_json( $input ) {
		json_decode( $input );

		return json_last_error() === JSON_ERROR_NONE;
	}
}
