<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Services\Loopable\Data_Integrations;

use WP_Error;

/**
 * Xml_Parent_Xpath_Generator
 *
 * @package Uncanny_Automator\Services\Loopable\Data_Integrations
 */
class Xml_Parent_Xpath_Generator {

	/**
	 * XML content to be parsed.
	 *
	 * @var string
	 */
	private $xml_content;

	/**
	 * Array to store the generated XPaths.
	 *
	 * @var array
	 */
	private $xpaths = array();

	/**
	 * Constructor to initialize the XML content.
	 *
	 * @param string $xml_content The raw XML string.
	 */
	public function __construct( $xml_content ) {
		$this->xml_content = $xml_content;
	}

	/**
	 * Parse the XML and generate the parent XPaths.
	 *
	 * @return array|WP_Error Array of general parent XPaths or WP_Error if parsing fails.
	 */
	public function generate_parent_xpaths() {

		// Check if the XML content is empty.
		if ( empty( $this->xml_content ) ) {
			return new WP_Error( 'empty_xml', 'The XML content is empty.' );
		}

		// Suppress XML parsing errors and capture them.
		libxml_use_internal_errors( true );

		// Convert the XML content into a SimpleXMLElement object.
		try {
			$xml = new \SimpleXMLElement( $this->xml_content );
		} catch ( \Exception $e ) {
			return $this->handle_xml_errors();
		}

		// Start recursive generation of XPaths.
		$this->generate_xpath( $xml, '' );

		// Remove duplicate XPaths.
		$this->xpaths = array_unique( $this->xpaths );

		return $this->xpaths;
	}

	/**
	 * Recursively generate XPaths for the given XML element.
	 *
	 * @param \SimpleXMLElement $element The current XML element.
	 * @param string $current_path The current XPath being generated.
	 */
	private function generate_xpath( \SimpleXMLElement $element, $current_path ) {

		// Get the name of the current element.
		$element_name = $element->getName();

		// Append the element to the current path.
		$new_path = $current_path . '/' . $element_name;

		// Add the new path to the XPaths array (if it's not a leaf node).
		if ( $element->children()->count() > 0 ) {
			$this->xpaths[] = $new_path;
		}

		// Iterate over the child elements to continue the recursion.
		foreach ( $element->children() as $child ) {
			$this->generate_xpath( $child, $new_path );
		}
	}

	/**
	 * Handle and return the first XML parsing error using WP_Error.
	 *
	 * @return WP_Error The first captured XML error.
	 */
	private function handle_xml_errors() {
		$errors = libxml_get_errors();

		if ( ! empty( $errors ) ) {
			$first_error   = $errors[0];
			$error_message = $this->format_xml_error( $first_error );

			// Clear errors after handling them.
			libxml_clear_errors();

			// Return a WP_Error with the first XML parsing error.
			return new WP_Error( 'xml_parsing_error', $error_message );
		}

		// Clear errors after handling them.
		libxml_clear_errors();

		// Default error if no specific error was captured.
		return new WP_Error( 'unknown_error', 'An unknown error occurred while parsing the XML.' );
	}

	/**
	 * Format an XML error message for WP_Error.
	 *
	 * @param \LibXMLError $error The LibXML error object.
	 * @return string Formatted error message.
	 */
	private function format_xml_error( \LibXMLError $error ) {
		$file = $error->file;

		// If no file is provided, set the default to 'string'.
		if ( empty( $file ) ) {
			$file = 'string';
		}

		return sprintf(
			'XML Error [Level %d]: %s in %s on line %d, column %d',
			$error->level,
			trim( $error->message ),
			$file,
			$error->line,
			$error->column
		);
	}

}
