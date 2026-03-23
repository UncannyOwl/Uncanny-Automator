<?php
/**
 * Item Manifest Trait
 *
 * Provides getters/setters for item-level metadata (triggers, actions, conditions, loop filters).
 * Allows developers to explicitly define item details instead of relying solely on discovery.
 *
 * ## Usage
 *
 * **For Third-Party Developers (Optional):**
 * Use this trait and call setters in your trigger/action setup method to provide explicit metadata:
 * ```php
 * protected function setup_trigger() {
 *     $this->set_integration( 'MY_INTEGRATION' );
 *     $this->set_trigger_code( 'MY_TRIGGER' );
 *     $this->set_readable_sentence( 'User submits a form' );
 *
 *     // Optional: Use manifest trait setters to provide metadata
 *     $this->set_readable_description( 'Triggers when a user submits any form on the site.' );
 *     $this->set_mcp_description( 'Form submission trigger: activates when a user submits any form on the site, capturing all form field data including field names, values, and metadata for downstream automation processing.' );
 * }
 * ```
 *
 * **During Registration:**
 * If this trait is used, manifest data is automatically extracted during item registration
 * and made available to the discovery service. This allows the discovery service to use your
 * explicit metadata instead of falling back to the readable sentence.
 *
 * **Important:** Getters only return values explicitly set via setters.
 * They do NOT fetch from complete.json or perform any data lookups.
 *
 * **For Internal Items:**
 * Internal items have metadata in complete.json, but getters do NOT
 * access this data to avoid performance overhead and circular dependencies.
 *
 * @package Uncanny_Automator
 * @since 5.8
 */

namespace Uncanny_Automator;

/**
 * Trait Item_Manifest
 *
 * Provides manifest properties and methods for item metadata.
 *
 * **Note:** Getters return only values set via setters. They do NOT fetch from
 * complete.json or perform lookups.
 *
 * @package Uncanny_Automator
 * @since 5.8
 */
trait Item_Manifest {

	/**
	 * Readable description (user-friendly).
	 *
	 * @var string
	 */
	protected $readable_description = '';

	/**
	 * MCP description (AI-friendly, detailed).
	 *
	 * @var string
	 */
	protected $mcp_description = '';

	/**
	 * Set readable description.
	 *
	 * Provides a clear, user-friendly description of what the item does.
	 *
	 * @param string $description User-readable description
	 * @return void
	 */
	public function set_readable_description( $description ) {
		$this->readable_description = (string) $description;
	}

	/**
	 * Get readable description.
	 *
	 * Returns only values set via set_readable_description(). Does NOT fetch from complete.json.
	 *
	 * @return string
	 */
	public function get_readable_description() {
		return $this->readable_description;
	}

	/**
	 * Set MCP description.
	 *
	 * Provides a detailed, AI-friendly description optimized for MCP context.
	 *
	 * @param string $description MCP description
	 * @return void
	 */
	public function set_mcp_description( $description ) {
		$this->mcp_description = (string) $description;
	}

	/**
	 * Get MCP description.
	 *
	 * Returns only values set via set_mcp_description(). Does NOT fetch from complete.json.
	 *
	 * @return string
	 */
	public function get_mcp_description() {
		return $this->mcp_description;
	}

	/**
	 * Extract manifest data from item instance.
	 *
	 * Only extracts non-empty values from manifest trait.
	 * This method is called automatically during item registration
	 * if the trait is detected.
	 *
	 * @return array Manifest data
	 */
	public function extract_item_manifest_data() {
		$manifest = array();

		// Only include readable_description if set
		$readable = $this->get_readable_description();
		if ( ! empty( $readable ) ) {
			$manifest['readable_description'] = $readable;
		}

		// Only include mcp_description if explicitly set (not empty)
		$mcp = $this->get_mcp_description();
		if ( ! empty( $mcp ) ) {
			$manifest['mcp_description'] = $mcp;
		}

		return $manifest;
	}
}
