<?php
/**
 * Abstract base class for integration helpers.
 *
 * Hosts the remote-data REST framework that integrations opt into. Concrete
 * helpers (Free) and composition-based Pro helpers extend this class to
 * register a single REST handler per integration via the
 * `automator_remote_data_instance_{id}` filter, and to expose typed builders
 * for the field-option `remote_data` block consumed by the recipe builder JS.
 *
 * @package Uncanny_Automator\Recipe
 * @since   7.3
 */

declare(strict_types=1);

namespace Uncanny_Automator\Recipe;

use Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Request;
use Exception;

/**
 * Abstract Helpers.
 */
abstract class Abstract_Helpers {

	/**
	 * Integration ID used to route remote-data REST requests to this helper instance.
	 *
	 * Used as the filter key for `automator_remote_data_instance_{remote_data_id}`.
	 * Set automatically by the Integration abstract from `get_integration()`,
	 * or manually via set_remote_data_id().
	 *
	 * @var string|null
	 */
	protected $remote_data_id = null;

	////////////////////////////////////////////////////////////
	// Registration
	////////////////////////////////////////////////////////////

	/**
	 * Get the remote-data routing ID.
	 *
	 * @return string
	 */
	public function get_remote_data_id(): string {
		return $this->remote_data_id ?? '';
	}

	/**
	 * Set the remote-data routing ID.
	 *
	 * @param string $remote_data_id The routing ID (typically the integration code).
	 *
	 * @return void
	 */
	public function set_remote_data_id( string $remote_data_id ) {
		$this->remote_data_id = $remote_data_id;
	}

	/**
	 * Register the remote-data filter.
	 *
	 * Maps `automator_remote_data_instance_{remote_data_id}` to this instance
	 * so the REST controller can resolve the handler dynamically.
	 *
	 * @return void
	 */
	public function register_remote_data_filter() {
		$remote_data_id = $this->get_remote_data_id();

		if ( empty( $remote_data_id ) ) {
			return;
		}

		add_filter(
			'automator_remote_data_instance_' . sanitize_key( $remote_data_id ),
			function () {
				return $this;
			}
		);
	}

	////////////////////////////////////////////////////////////
	// Dispatch
	////////////////////////////////////////////////////////////

	/**
	 * Process a remote data REST request.
	 *
	 * Single public entry point called by Remote_Data_Rest_Controller (and by
	 * non-REST callers via Remote_Data_Handler_Resolver). Resolves the handler
	 * method by prefixing the data identifier with `remote_data_get_`. Only
	 * `remote_data_get_*` methods are reachable through REST dispatch — an
	 * implicit allowlist that prevents arbitrary protected/public methods on
	 * the helper from being callable via the REST surface.
	 *
	 * Convention for `remote_data_get_*` methods on concrete helpers: declare
	 * the parameter without a type hint and document the type in the docblock
	 * (`@param Remote_Data_Request $request`). The dispatcher always passes a
	 * `Remote_Data_Request`, so runtime enforcement at the integration boundary
	 * is redundant — and skipping the import keeps integration helpers free of
	 * a deep `use` from the App transport namespace. IDE/static-analysis tools
	 * pick up the type from the docblock.
	 *
	 * @param string              $data    The data identifier (second URL segment, e.g. 'posts').
	 * @param Remote_Data_Request $request The typed request DTO.
	 *
	 * @return array Response data array.
	 *
	 * @throws Exception If the resolved remote_data_get_ method does not exist.
	 */
	public function process_remote_data_request( string $data, Remote_Data_Request $request ): array {

		$method = 'remote_data_get_' . sanitize_key( $data );

		if ( ! method_exists( $this, $method ) ) {
			throw new Exception(
				esc_html_x( 'The requested remote data method does not exist.', 'Remote Data REST', 'uncanny-automator' )
			);
		}

		return $this->{ $method }( $request );
	}

	////////////////////////////////////////////////////////////
	// Field-option builders
	////////////////////////////////////////////////////////////

	/**
	 * Build a `remote_data` block that fetches once when the trigger/action
	 * options open.
	 *
	 * Use for fields whose options are needed eagerly at panel-open time and
	 * don't depend on any other field. Equivalent to the legacy
	 * `'ajax' => [ 'event' => 'on_load', 'endpoint' => '...' ]` shape.
	 *
	 * @param string      $data Data identifier (URL second segment, e.g. 'webhook_url').
	 * @param string|null $id   Override the integration ID (default: $this->get_remote_data_id()).
	 *
	 * @return array Field-option-shaped `remote_data` block.
	 */
	public function remote_data_load_config( string $data, ?string $id = null ): array {
		return array(
			'id'    => $id ?? $this->get_remote_data_id(),
			'data'  => $data,
			'event' => 'on_load',
		);
	}

	/**
	 * Build a `remote_data` block that fetches when one or more parent fields change.
	 *
	 * Use for cascading dropdowns whose options depend on the value(s) of one
	 * or more parent fields. Equivalent to the legacy
	 * `'ajax' => [ 'event' => 'parent_fields_change', 'listen_fields' => [...], 'endpoint' => '...' ]`.
	 *
	 * @param string      $data          Data identifier (e.g. 'lessons').
	 * @param string[]    $listen_fields Parent option codes whose changes trigger refetch.
	 * @param string|null $id            Override the integration ID.
	 *
	 * @return array Field-option-shaped `remote_data` block.
	 */
	public function remote_data_parent_config(
		string $data,
		array $listen_fields,
		?string $id = null
	): array {
		return array(
			'id'            => $id ?? $this->get_remote_data_id(),
			'data'          => $data,
			'event'         => 'parent_fields_change',
			'listen_fields' => $listen_fields,
		);
	}

	/**
	 * Build a `remote_data` block that fetches when the user types in a select's search box.
	 *
	 * Use for selects with too many options to load eagerly — server-side
	 * search via the user's typed query (`q`). Optionally accepts
	 * `$clear_on_change_fields` so the select clears its current value when a
	 * related field changes (since `search_options` doesn't auto-refetch on
	 * parent change). Equivalent to the legacy
	 * `'ajax' => [ 'event' => 'search_options', 'clear_on_change_fields' => [...], 'endpoint' => '...' ]`.
	 *
	 * @param string      $data                   Data identifier (e.g. 'posts_search').
	 * @param string[]    $clear_on_change_fields Optional. Parent option codes that should clear this field when they change.
	 * @param string|null $id                     Override the integration ID.
	 *
	 * @return array Field-option-shaped `remote_data` block.
	 */
	public function remote_data_search_config(
		string $data,
		array $clear_on_change_fields = array(),
		?string $id = null
	): array {
		$block = array(
			'id'    => $id ?? $this->get_remote_data_id(),
			'data'  => $data,
			'event' => 'search_options',
		);
		if ( ! empty( $clear_on_change_fields ) ) {
			$block['clear_on_change_fields'] = $clear_on_change_fields;
		}
		return $block;
	}

	/**
	 * Add repeater-specific `mapping_column` to a `remote_data` block.
	 *
	 * Wraps the result of remote_data_load_config() or
	 * remote_data_parent_config() — search_options does not apply to repeaters.
	 * `mapping_column` identifies the unique-ID column inside the repeater row
	 * template, used to preserve user-edited cells across row refreshes.
	 *
	 * @param array  $remote_data    Output of remote_data_load_config() or remote_data_parent_config().
	 * @param string $mapping_column Option code of the unique-id column inside the repeater's row template.
	 *
	 * @return array Repeater-augmented `remote_data` block.
	 */
	public function remote_data_with_mapping_column( array $remote_data, string $mapping_column ): array {
		$remote_data['mapping_column'] = $mapping_column;
		return $remote_data;
	}

	////////////////////////////////////////////////////////////
	// Response builders
	////////////////////////////////////////////////////////////

	/**
	 * Build a success response.
	 *
	 * @param array  $data The data to send.
	 * @param string $key  The key for the data array. Default 'options'.
	 *
	 * @return array
	 */
	protected function remote_data_success( array $data, string $key = 'options' ): array {
		return array(
			'success' => true,
			$key      => $data,
		);
	}

	/**
	 * Build an error response.
	 *
	 * @param string $message The error message.
	 * @param string $key     The key for the empty data array. Default 'options'.
	 *
	 * @return array
	 */
	protected function remote_data_error( string $message, string $key = 'options' ): array {
		return array(
			'success' => false,
			'error'   => $message,
			$key      => array(),
		);
	}
}
