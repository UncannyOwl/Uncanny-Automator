<?php
/**
 * Comprehensive recipe inspection tool.
 *
 * Returns the recipe as the ModelRecipe wire shape the recipe builder parses,
 * read through the wire service, with the admin-only keys stripped.
 *
 * Absorbs: list_actions, loop_list, loop_get, loop_filter_list, loop_filter_get,
 *          list_conditions, get_user_selector.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Recipes;

use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration_Exception;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Model_Recipe_Schema;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Wire_Service;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Get Recipe Tool — comprehensive.
 *
 * One call returns the whole recipe in the builder's own vocabulary: triggers
 * under flow.entry, the run-as selector, and execution-ordered content with
 * blocks and their path-keyed branches.
 *
 * @since 7.0.0 (rewritten 7.1.0; wire shape 8.0.0)
 */
class Get_Recipe_Tool extends Abstract_MCP_Tool {

	/**
	 * Cache-first wire read of the recipe tree.
	 *
	 * @var Recipe_Wire_Service
	 */
	private Recipe_Wire_Service $wire;

	/**
	 * Lazy-default DI: production reads the shared wire service; tests inject one over a fixture tree.
	 *
	 * @param Recipe_Wire_Service|null $wire Wire read seam.
	 */
	public function __construct( ?Recipe_Wire_Service $wire = null ) {
		$this->wire = $wire ?? Recipe_Wire_Service::instance();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'get_recipe';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return 'Get a recipe by ID as the full recipe tree in one call. '
			. 'Triggers are in flow.entry.triggers with flow.entry.operator (all or any). The run-as user selector is flow.run_as. '
			. 'flow.content is the execution-ordered list of items (type=item: trigger or action) and blocks (type=block: filter, delay_schedule or loop). '
			. 'A block\'s children sit in its paths list, each { path_code, content }: FILTER-CONDITIONS-MET and FILTER-CONDITIONS-NOT-MET on a filter, DELAY-COMPLETED on a delay_schedule (the Queue block in the builder), LOOP-ITERATION-COMPLETED on a loop; '
			. 'these are the same path_code values save_action and save_block accept. '
			. 'Conditions are the modifiers of a filter block; loop filters are the modifiers of a loop block. '
			. 'Every item and block carries tokens (use as {{id}}), availability (whether the step runs on this site) and tags. '
			. 'Absorbs list_actions, loop_list, loop_get, loop_filter_list, loop_filter_get, list_conditions and get_user_selector.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'The ID of the recipe to get.',
					'minimum'     => 1,
				),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return Model_Recipe_Schema::recipe_for_agent();
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute_tool( User_Context $user_context, array $params ) {
		$recipe_id = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'Parameter recipe_id must be a positive integer. Use list_recipes to find recipe IDs.' );
		}

		try {
			$recipe = $this->wire->get_wire_array( $recipe_id );
		} catch ( Legacy_Storage_Migration_Exception $e ) {
			return Json_Rpc_Response::create_error_response( $e->user_message( $recipe_id ) );
		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response( sprintf( 'Recipe %d could not be read (%s).', $recipe_id, $e->getMessage() ) );
		}

		if ( null === $recipe ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Recipe not found with ID %d. Use list_recipes to find valid recipe IDs.', $recipe_id )
			);
		}

		return Json_Rpc_Response::create_success_response( 'Recipe retrieved successfully', $this->strip_admin_only_keys( $recipe ) );
	}

	/**
	 * Drop the keys that mean nothing to an MCP client: edit rights and admin urls.
	 *
	 * @param array<string, mixed> $recipe The wire array.
	 * @return array<string, mixed>
	 */
	private function strip_admin_only_keys( array $recipe ): array {
		foreach ( Wire_Key::AGENT_STRIPPED_TOP_LEVEL as $key ) {
			unset( $recipe[ $key ] );
		}

		foreach ( Wire_Key::AGENT_STRIPPED_MISCELLANEOUS as $key ) {
			unset( $recipe[ Wire_Key::MISCELLANEOUS ][ $key ] );
		}

		return $recipe;
	}
}
