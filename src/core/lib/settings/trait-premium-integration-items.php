<?php
namespace Uncanny_Automator\Settings;

use Exception;
use ReflectionClass;

/**
 * Trait for discovering available actions and triggers in premium integrations
 *
 * @package Uncanny_Automator\Settings
 */
trait Premium_Integration_Items {

	/**
	 * Get available actions by scanning directories and applying filters
	 *
	 * @return array
	 */
	protected function get_available_actions() {
		return $this->get_available_items( 'actions' );
	}

	/**
	 * Get available triggers by scanning directories and applying filters
	 *
	 * @return array
	 */
	protected function get_available_triggers() {
		return $this->get_available_items( 'triggers' );
	}

	/**
	 * Get available items (actions or triggers) by scanning directories and applying filters
	 *
	 * @param string $type Either 'actions' or 'triggers'
	 * @return array
	 * @throws Exception If invalid item type.
	 */
	protected function get_available_items( $type ) {

		// Validate type.
		$this->validate_item_type( $type );

		// Get trigger or action items from the integration.
		$items = $this->get_items_from_classes( $type );

		// Adjust the ID for the filter name.
		$integration_id = strtolower( $this->get_id() );

		/**
		 * Filter the available items
		 *
		 * @param array $items The current items
		 * @return array
		 */
		$items = apply_filters( "automator_{$integration_id}_{$type}", $items );

		// Remove duplicates and reindex array
		return array_values( array_unique( $items ) );
	}

	/**
	 * Get items by instantiating classes and getting readable sentences
	 *
	 * @param string $type Either 'actions' or 'triggers'
	 * @return array
	 * @throws Exception If invalid item type.
	 */
	protected function get_items_from_classes( $type ) {

		// Validate type.
		$this->validate_item_type( $type );

		$items     = array();
		$directory = $this->get_items_directory( $type );

		if ( ! is_dir( $directory ) ) {
			return $items;
		}

		// Get all PHP files in the directory
		$files = glob( $directory . '/*.php' );

		foreach ( $files as $file ) {
			$class_name = $this->get_class_name_from_file( $file );
			if ( $class_name && class_exists( $class_name ) ) {
				$item     = new $class_name( $this->dependencies );
				$sentence = $item->get_readable_sentence();
				if ( $sentence ) {
					$items[] = $this->format_readable_sentence( $sentence );
				}
			}
		}

		return $items;
	}

	/**
	 * Get the full path to the items directory
	 *
	 * @param string $type Either 'actions' or 'triggers'
	 * @return string
	 * @throws Exception If invalid item type.
	 */
	protected function get_items_directory( $type ) {

		// Validate type.
		$this->validate_item_type( $type );

		// Get the current class's directory
		$reflection = new ReflectionClass( get_class( $this ) );
		$dir        = dirname( $reflection->getFileName() );

		// Go up one level and into type directory
		return dirname( $dir ) . '/' . $type;
	}

	/**
	 * Get class name from file path
	 *
	 * @param string $file_path
	 * @return string|null
	 */
	protected function get_class_name_from_file( $file_path ) {
		// Get the file contents
		$content = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		// Extract namespace
		if ( preg_match( '/namespace\s+([^;]+);/', $content, $matches ) ) {
			$namespace = $matches[1];
		}

		// Extract class name
		if ( preg_match( '/class\s+(\w+)/', $content, $matches ) ) {
			$class_name = $matches[1];
			return isset( $namespace ) ? $namespace . '\\' . $class_name : $class_name;
		}

		return null;
	}

	/**
	 * Format the readable sentence by replacing placeholders
	 *
	 * @param string $sentence
	 * @return string
	 */
	protected function format_readable_sentence( $sentence ) {
		// Replace {{text}} with "text"
		return preg_replace( '/\{\{([^}]+)\}\}/', '$1', $sentence );
	}

	/**
	 * Validate item type
	 *
	 * @param string $type
	 * @return void
	 * @throws Exception
	 */
	private function validate_item_type( $type ) {
		if ( 'actions' !== $type && 'triggers' !== $type ) {
			throw new Exception( 'Invalid item type' );
		}
	}
}
