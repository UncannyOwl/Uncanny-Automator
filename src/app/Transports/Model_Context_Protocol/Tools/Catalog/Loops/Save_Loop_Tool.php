<?php
/**
 * Consolidated loop upsert tool.
 *
 * Replaces: loop_add, loop_update.
 * loop_id absent = create, loop_id present = update.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\App\Recipe_Builder\Loop\Enums\Loop_Status;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Block_Crud_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Utilities\Recipe_Link_Builder;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Validation\Token_Validator;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;

/**
 * Save Loop Tool — upsert.
 *
 * Create: omit loop_id. Requires recipe_id, type. loopable_token required when type=token.
 * Update: include loop_id. Requires recipe_id, loop_id. Other fields optional.
 *
 * @since 7.1.0
 */
class Save_Loop_Tool extends Abstract_Loop_Tool {

	/**
	 * @var Loop_CRUD_Service
	 */
	private Loop_CRUD_Service $loop_service;

	/**
	 * Constructor.
	 *
	 * @param Loop_CRUD_Service|null $loop_service Loop CRUD service.
	 */
	public function __construct( ?Loop_CRUD_Service $loop_service = null ) {
		$this->loop_service = $loop_service ?? Loop_CRUD_Service::instance();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'save_loop';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Create or update a loop that repeats its actions for each matching user, post, or token item. '
			. 'To create, pass recipe_id and type (users/posts/token), omitting loop_id. To update, pass recipe_id and loop_id. Changing type reconciles incompatible filters automatically, and an update that leaves the loop with no filter adds its type\'s default filter. '
			. 'For token loops, also pass loopable_token: a registry token found with search(type="loopable_token"), a loopable token of a trigger, or one of an action that runs before the loop, at its level or in a block the loop is in. A new loop goes at the end of the recipe, so any top-level action before it counts; an action inside another block does not. get_recipe lists the valid ones of each loop as loopable_token_ids. '
			. 'Loop filters select which items run. After creation, use get_recipe to inspect the loop and its automatically added default filter. User loops initially exclude administrators. '
			. 'Find compatible filters with search(type="loop_filter", mode="list", loop_id=the returned loop_id). '
			. 'Use save_loop_filter to configure them: include filter_id to update an existing filter, or omit it to add one. Multiple filters must all match; adding a filter does not replace the default. A loop with no filters cannot be published or run. '
			. 'Add actions with save_action(parent_type="loop", parent_id=loop_id). Use get_tokens(recipe_id, loop_id) for the current iteration\'s values. '
			. 'Check the finished configuration with get_recipe. Loops are always published; saving a loop does not execute it. '
			. 'Use those names as root tools when listed. Otherwise use rest_discover and rest_request for the corresponding native operations: automator/search, automator/get-recipe, automator/save-loop-filter, automator/save-action and automator/get-tokens.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_annotations(): array {
		return array(
			'readOnlyHint'    => false,
			'destructiveHint' => true,
			'idempotentHint'  => false,
			'openWorldHint'   => true,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		$loopable_token_schema = array(
			'type'        => 'string',
			'description' => 'Required when type=token. Loopable token ID to iterate over. Use search with type=loopable_token for registry sources, or pass a loopable token of a trigger, or of an action that runs before the loop at its level or in a block the loop is in.',
		);

		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'      => array(
					'type'        => 'integer',
					'description' => 'Recipe ID. Required for both create and update.',
					'minimum'     => 1,
				),
				'loop_id'        => array(
					'type'        => 'integer',
					'description' => 'Existing loop ID to update. Omit to create a new loop.',
					'minimum'     => 1,
				),
				'type'           => array(
					'type'        => 'string',
					'description' => 'Iteration type. Required for create. May be changed on update; invalidated loop filters are reconciled automatically.',
					'enum'        => array( 'users', 'posts', 'token' ),
				),
				'loopable_token' => $loopable_token_schema,
				'fields'         => array(
					'type'                 => 'object',
					'description'          => 'Stored iterable-expression fields; normally omit. User and post selection is configured through save_loop_filter, or the discovered automator/save-loop-filter operation, not this object. user_ids, user_roles, post_type and post_status here do not select items. For token loops, pass loopable_token; the tool builds the TOKEN field automatically.',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'loop_id'   => array( 'type' => 'integer' ),
				'loop'      => array(
					'type'       => 'object',
					'properties' => array(
						'id'                  => array( 'type' => 'integer' ),
						'type'                => array( 'type' => 'string' ),
						'iterable_expression' => array( 'type' => 'object' ),
						'recipe_id'           => array( 'type' => 'integer' ),
					),
				),
				'recipe_id' => array( 'type' => 'integer' ),
				'links'     => array( 'type' => 'object' ),
				'warnings'  => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'   => array( 'loop_id', 'loop', 'recipe_id', 'links' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$this->require_authenticated_executor( $user_context );

		$loop_id = isset( $params['loop_id'] ) ? (int) $params['loop_id'] : null;

		if ( null !== $loop_id && $loop_id > 0 ) {
			return $this->update_loop( $loop_id, $params );
		}

		return $this->create_loop( $params );
	}
	// ──────────────────────────────────────────────────────────────────
	// CREATE PATH — port of Loop_Add_Tool::execute_tool()
	// ──────────────────────────────────────────────────────────────────
	/**
	 * Create loop.
	 *
	 * @param array $params The parameters.
	 * @return array
	 */
	private function create_loop( array $params ): array {

		$recipe_id = (int) $params['recipe_id'];
		$type      = $params['type'] ?? '';

		if ( empty( $type ) ) {
			return Json_Rpc_Response::create_error_response( 'type is required for creating a loop (users, posts, or token).' );
		}

		if ( 'token' === $type && empty( $params['loopable_token'] ) ) {
			return Json_Rpc_Response::create_error_response( 'loopable_token is required when type=token.' );
		}

		$fields = $this->parse_json_param( $params['fields'] ?? array() );

		// For token type, set the TOKEN field from loopable_token param.
		if ( 'token' === $type && ! empty( $params['loopable_token'] ) ) {
			$token_id    = $params['loopable_token'];
			$token_value = '{{' . $token_id . '}}';

			$fields['TOKEN'] = array(
				'type'   => 'text',
				'value'  => $token_value,
				'backup' => array(
					'label'                  => 'Token',
					'show_label_in_sentence' => false,
				),
			);

			$validation = ( new Token_Validator() )->validate_loopable( $recipe_id, $token_value );
			if ( ! $validation['valid'] ) {
				return Json_Rpc_Response::create_error_response( $validation['message'] );
			}
		}

		$iterable_expression = array(
			'type'   => $type,
			'fields' => wp_json_encode( $fields ),
		);

		if ( 'token' === $type && ! empty( $params['loopable_token'] ) ) {
			$backup = $this->build_token_backup( $params['loopable_token'] );
			if ( ! empty( $backup ) ) {
				$iterable_expression['backup'] = wp_json_encode( $backup );
			}
		}

		$filters = $this->parse_json_param( $params['filters'] ?? array() );

		$result = $this->loop_service->add_to_recipe(
			$recipe_id,
			$iterable_expression,
			Loop_Status::PUBLISH,
			$filters
		);

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response(
			'Loop added successfully',
			array(
				'loop_id'   => $result['loop_id'] ?? 0,
				'loop'      => $result['loop'] ?? array(),
				'recipe_id' => $recipe_id,
				'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}
	// ──────────────────────────────────────────────────────────────────
	// UPDATE PATH — port of Loop_Update_Tool::execute_tool()
	// ──────────────────────────────────────────────────────────────────
	/**
	 * Update loop.
	 *
	 * @since 7.2.4 Enforces loop ownership against the provided recipe_id before updates.
	 *
	 * @param int $loop_id The ID.
	 * @param array $params The parameters.
	 * @return array
	 */
	private function update_loop( int $loop_id, array $params ): array {

		$recipe_id = (int) $params['recipe_id'];
		$ownership = $this->validate_loop_ownership( $loop_id, $recipe_id );
		if ( null !== $ownership ) {
			return $ownership;
		}

		// Fetch existing state.
		$existing_loop  = $this->get_existing_loop( $loop_id );
		$existing_type  = $existing_loop['type'] ?? '';
		$effective_type = $existing_type;
		if ( array_key_exists( 'type', $params ) && '' !== (string) $params['type'] ) {
			$effective_type = (string) $params['type'];
		}
		$type_changed = ( $effective_type !== $existing_type );

		// Determine loopable_token.
		$loopable_token = $params['loopable_token'] ?? '';
		if ( empty( $loopable_token ) && 'token' === $effective_type ) {
			$loopable_token = $this->extract_loopable_token_from_fields( $existing_loop['fields'] ?? '' );
		}

		if ( 'token' === $effective_type && empty( $loopable_token ) ) {
			return Json_Rpc_Response::create_error_response( 'loopable_token is required for token-type loops.' );
		}

		// Build fields: preserve existing, merge passed, enforce TOKEN invariant.
		$existing_fields = $this->parse_json_param( $existing_loop['fields'] ?? '' );
		$passed_fields   = $this->parse_json_param( $params['fields'] ?? array() );
		$token_warning   = '';

		if ( 'token' === $effective_type && array_key_exists( 'TOKEN', $passed_fields ) ) {
			$token_warning = 'TOKEN field is reserved and was overridden by the loopable_token value.';
			unset( $passed_fields['TOKEN'] );
		}

		$fields = array_merge( $existing_fields, $passed_fields );

		if ( 'token' === $effective_type ) {
			$token_value     = '{{' . $loopable_token . '}}';
			$fields['TOKEN'] = array(
				'type'   => 'text',
				'value'  => $token_value,
				'backup' => array(
					'label'                  => 'Token',
					'show_label_in_sentence' => false,
				),
			);

			$validation = ( new Token_Validator() )->validate_loopable( $recipe_id, $token_value, $loop_id );
			if ( ! $validation['valid'] ) {
				return Json_Rpc_Response::create_error_response( $validation['message'] );
			}
		}

		// Switching away from a token loop must not carry the stale TOKEN field forward.
		if ( 'token' !== $effective_type ) {
			unset( $fields['TOKEN'] );
		}

		// Carry the (possibly changed) type so the service reconciles filters
		// whose ancestor rules no longer hold under the new iteration type.
		$iterable_expression = array(
			'type' => $effective_type,
		);

		if ( 'token' === $effective_type || $type_changed || array_key_exists( 'fields', $params ) ) {
			$iterable_expression['fields'] = wp_json_encode( $fields );
		}

		if ( 'token' === $effective_type ) {
			$backup                        = $this->build_token_backup( $loopable_token );
			$iterable_expression['backup'] = wp_json_encode( $backup );
		} elseif ( $type_changed ) {
			// Switched away from token: overwrite the stale token backup so the
			// service's array_merge doesn't carry it forward onto the row.
			$iterable_expression['backup'] = wp_json_encode( array() );
		}

		$result = $this->loop_service->update_loop( $loop_id, $iterable_expression, null );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$loop      = $result['loop'] ?? array();
		$recipe_id = (int) ( $loop['recipe_id'] ?? $recipe_id );

		$payload = array(
			'loop_id'   => $loop_id,
			'loop'      => $loop,
			'recipe_id' => $recipe_id,
			'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
		);

		if ( ! empty( $token_warning ) ) {
			$payload['warnings'] = array( $token_warning );
		}

		return Json_Rpc_Response::create_success_response( 'Loop updated successfully', $payload );
	}

	/**
	 * Validate that a loop exists and belongs to the provided recipe.
	 *
	 * @since 7.2.4
	 *
	 * @param int $loop_id Loop ID.
	 * @param int $recipe_id Recipe ID.
	 * @return array|null Error response if invalid ownership, null when valid.
	 */
	private function validate_loop_ownership( int $loop_id, int $recipe_id ): ?array {
		$loop_post = get_post( $loop_id );

		// Loops persist as uo-block(block_type=loop); ownership rides the
		// block store's recipe_id link (loops can nest inside other blocks).
		if ( ! $loop_post || AUTOMATOR_POST_TYPE_BLOCK !== $loop_post->post_type ) {
			return Json_Rpc_Response::create_error_response( sprintf( 'Loop not found: %d.', $loop_id ) );
		}

		$block = Block_Crud_Service::instance()->get_block( $loop_id );
		if ( is_wp_error( $block ) || 'loop' !== (string) ( $block['block_type'] ?? '' ) ) {
			return Json_Rpc_Response::create_error_response( sprintf( 'Loop not found: %d.', $loop_id ) );
		}
		if ( (int) ( $block['recipe_id'] ?? 0 ) !== $recipe_id ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Loop %d belongs to recipe %d, not recipe %d.', $loop_id, (int) ( $block['recipe_id'] ?? 0 ), $recipe_id )
			);
		}

		return null;
	}

	/**
	 * Get existing loop data from post meta.
	 *
	 * @param int $loop_id Loop ID.
	 * @return array Loop data.
	 */
	private function get_existing_loop( int $loop_id ): array {
		$iterable_expression = get_post_meta( $loop_id, 'iterable_expression', true );
		if ( ! is_array( $iterable_expression ) ) {
			return array();
		}
		return array(
			'type'   => $iterable_expression['type'] ?? '',
			'fields' => $iterable_expression['fields'] ?? '',
		);
	}

	/**
	 * Extract loopable token ID from existing fields JSON.
	 *
	 * @param string $fields_json JSON string.
	 * @return string Token ID or empty.
	 */
	private function extract_loopable_token_from_fields( string $fields_json ): string {
		if ( empty( $fields_json ) ) {
			return '';
		}
		$fields = json_decode( $fields_json, true );
		if ( ! is_array( $fields ) || empty( $fields['TOKEN']['value'] ) ) {
			return '';
		}
		if ( preg_match( '/\{\{(.+?)\}\}/', $fields['TOKEN']['value'], $matches ) ) {
			return $matches[1];
		}
		return '';
	}
}
