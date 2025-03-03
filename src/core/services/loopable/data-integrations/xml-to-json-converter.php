<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Services\Loopable\Data_Integrations;

use RuntimeException;

/**
 * Class Xml_To_Json_Converter
 *
 * A robust class to convert XML files to JSON format.
 * Supports XML input from URL, file path, and raw text.
 * Automatically detects and handles XML namespaces for complex XML structures.
 * Allows specifying an XPath for filtering XML data.
 *
 * Features:
 * - Load XML from URL (using wp_remote_get for WordPress compatibility).
 * - Load XML from an absolute file path.
 * - Load XML from a string of raw XML data.
 * - Convert XML to JSON with proper namespace handling.
 * - Option to filter XML using an XPath query.
 *
 * @package Uncanny_Automator\Services\Loopable\Data_Integrations
 */
class Xml_To_Json_Converter {

	/**
	 * @var string Raw XML data.
	 */
	private $xml_content;

	/**
	 * @var string XPath for filtering the XML content, defaults to the root path.
	 */
	private $xpath = '/';

	/**
	 * @var int Sensible default HTTP Request timeout. Most servers have 30 seconds default timeout. Adding 25 seconds to allow 5 seconds processing.
	 */
	protected $http_request_timeout = 25;

	/**
	 * @var string The default user agent for HTTP Requests related to data integrations.
	 */
	protected $http_request_user_agent = '';

	/**
	 * Load XML data from a URL using wp_remote_get.
	 *
	 * @param string $url The URL of the XML file.
	 * @return self Returns the current instance.
	 * @throws RuntimeException If the XML data cannot be fetched or parsed.
	 */
	public function load_from_url( $url ) {

		// Overwrite the default WordPress HTTP agent because some feed doesnt like the syntax and its breaking due to multiple backslashes, or special characters';
		$this->http_request_user_agent = 'WordPress/' . wp_get_wp_version() . ';' . urlencode( get_bloginfo( 'url' ) );

		$args = array(
			'timeout'    => apply_filters( 'http_request_timeout', $this->http_request_timeout, $url ),
			'user-agent' => $this->http_request_user_agent,
		);

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException(
				sprintf(
				/* translators: %s: Error message */
					esc_html__( 'Failed to fetch the XML from the provided URL. %s', 'uncanny-automator' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$xml_data = wp_remote_retrieve_body( $response );
		if ( empty( $xml_data ) ) {
			throw new RuntimeException(
				sprintf(
				/* translators: %s: URL */
					esc_html__( 'No content found in the provided URL: %s', 'uncanny-automator' ),
					esc_url( $url )
				)
			);
		}

		$this->xml_content = $xml_data;

		return $this;
	}

	/**
	 * Load XML data from an absolute file path.
	 *
	 * @param string $file_path The absolute file path of the XML file.
	 * @return self Returns the current instance.
	 * @throws RuntimeException If the file cannot be read or parsed.
	 */
	public function load_from_file_path( $file_path ) {

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			throw new RuntimeException( 'The file does not exist or is not readable.' );
		}
		// Local file.
		$xml_data          = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->xml_content = $xml_data;

		return $this;
	}

	/**
	 * Load XML data from raw text.
	 *
	 * @param string $text The raw XML data as a string.
	 * @return self Returns the current instance.
	 * @throws RuntimeException If the XML data cannot be parsed.
	 */
	public function load_from_text( $text ) {

		if ( empty( $text ) ) {
			throw new RuntimeException( 'The provided XML text is empty.' );
		}

		$this->xml_content = $text;

		return $this;
	}

	/**
	 * Set the XPath for filtering the XML data.
	 *
	 * @param string $xpath The XPath query string.
	 * @return self Returns the current instance.
	 */
	public function set_xpath( $xpath ) {

		$this->xpath = $xpath;

		return $this;
	}

	/**
	 * Convert the loaded XML data to JSON with proper namespace handling and XPath filtering.
	 *
	 * @return string JSON representation of the filtered XML data.
	 * @throws RuntimeException If the XPath query returns no results.
	 */
	public function to_json() {
		return $this->convert_xml_to_json( $this->xml_content, $this->xpath );
	}

	/**
	 * Convert XML content to a JSON format with proper namespace handling and XPath filtering.
	 *
	 * @param string $xml_content Raw XML content.
	 * @param string $xpath XPath query for filtering the XML data.
	 * @return string JSON representation of the XML.
	 * @throws RuntimeException If the XPath query returns no results.
	 */
	public function convert_xml_to_json( $xml_content, $xpath = '/' ) {

		// Convert XML content to a SimpleXMLElement object, handling namespaces.
		$xml = simplexml_load_string( $xml_content, 'SimpleXMLElement', LIBXML_NOCDATA );

		// Get namespaces.
		$namespaces = $xml->getNamespaces( true );

		// Register namespaces for XPath.
		foreach ( $namespaces as $prefix => $namespace ) {
			$xml->registerXPathNamespace( $prefix, $namespace );
		}

		// Perform XPath query on the XML.
		$filtered_xml = $xml->xpath( $xpath );

		if ( empty( $filtered_xml ) ) {
			throw new RuntimeException( esc_html( "No results found for the specified XPath: $xpath." ) );
		}

		// Convert the filtered XML result into JSON.
		$json_array = array();
		foreach ( $filtered_xml as $element ) {
			$json_array[] = $this->simple_xml_to_array( $element, $namespaces );
		}

		return wp_json_encode( $json_array, JSON_PRETTY_PRINT );
	}

	/**
	 * Convert SimpleXMLElement (with namespaces) to an associative array.
	 *
	 * @param \SimpleXMLElement $xml SimpleXMLElement object.
	 * @param array $namespaces Array of XML namespaces.
	 * @return array The converted associative array.
	 */
	private function simple_xml_to_array( $xml, $namespaces ) {

		$json_array = array();

		// Handle attributes (if any).
		foreach ( $xml->attributes() as $attr => $value ) {
			$json_array['@attributes'][ $attr ] = (string) $value;
		}

		// Handle namespaced elements and child elements.
		foreach ( $namespaces as $prefix => $ns ) {
			foreach ( $xml->children( $ns ) as $key => $child ) {
				// Recursively process child elements.
				$json_array[ $prefix . ':' . $key ][] = $this->simple_xml_to_array( $child, $namespaces );
			}
		}

		// Process regular child elements (without namespaces).
		foreach ( $xml->children() as $key => $child ) {
			$json_array[ $key ][] = $this->simple_xml_to_array( $child, $namespaces );
		}

		// Process text content of the element (if any).
		$text_content = trim( (string) $xml );
		if ( ! empty( $text_content ) ) {
			$json_array['_loopable_xml_text'] = $text_content;
		}

		return $json_array;
	}
}
