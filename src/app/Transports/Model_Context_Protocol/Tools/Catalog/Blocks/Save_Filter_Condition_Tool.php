<?php
/**
 * Filter-condition modifier upsert tool.
 *
 * condition_id absent = create, condition_id present = update.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Blocks;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Filter_Condition_Crud_Service;
use Uncanny_Automator\App\Recipe_Builder\Condition\Services\Condition_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Mcp_Input_Resolver;
use Uncanny_Automator\App\Recipe_Builder\Shared\Polyfill\Str;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use WP_Error;

/**
 * Save Filter Condition Tool — upsert for `uo-block-modifier(filter_condition)` posts.
 *
 * Configures a filter block: each condition is a modifier post under the
 * block, and the block gates its child actions on them. Writes ride
 * `Filter_Condition_Crud_Service` (the same service REST uses); the
 * condition code is validated against the discovery registry
 * (`Condition_Registry_Service` — the KEEP side of the condition system).
 *
 * Create: omit condition_id. Requires block_id (a filter block),
 * integration, condition_code. Update: include condition_id — the fields
 * sent are laid over the saved ones (and optionally integration/condition_code
 * are updated), so omitted fields keep their saved values, as `save_action`
 * and `save_loop_filter` do. A new integration or condition_code is checked
 * against the registry, as on create.
 */
class Save_Filter_Condition_Tool extends Abstract_MCP_Tool {

	/**
	 * Filter-condition CRUD service dependency.
	 *
	 * @var Filter_Condition_Crud_Service
	 */
	private Filter_Condition_Crud_Service $condition_service;

	/**
	 * Condition discovery registry dependency.
	 *
	 * @var Condition_Registry_Service
	 */
	private Condition_Registry_Service $registry_service;

	/**
	 * Constructor.
	 *
	 * @param Filter_Condition_Crud_Service|null $condition_service Filter-condition CRUD service.
	 * @param Condition_Registry_Service|null    $registry_service  Condition discovery registry.
	 */
	public function __construct(
		?Filter_Condition_Crud_Service $condition_service = null,
		?Condition_Registry_Service $registry_service = null
	) {
		$this->condition_service = $condition_service ?? Filter_Condition_Crud_Service::instance();
		$this->registry_service  = $registry_service ?? Condition_Registry_Service::instance();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'save_filter_condition';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Add or update a condition on a filter block. Omit condition_id to add, include condition_id to update. '
			. 'Create requires block_id (a filter block from save_block), integration and condition_code '
			. '(discover codes with search type=condition; field schemas with get_component_schema type=condition). '
			. 'Update requires block_id and condition_id; send only changed fields, which are merged with the saved fields: '
			. 'omitted fields keep their values, and an empty string clears a field. '
			. 'The filter block runs its child actions only when its conditions pass.';
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
		return array(
			'type'       => 'object',
			'properties' => array(
				'block_id'       => array(
					'type'        => 'integer',
					'description' => 'Filter block ID the condition belongs to. Required.',
					'minimum'     => 1,
				),
				'condition_id'   => array(
					'type'        => 'integer',
					'description' => 'Existing condition ID to update. Omit to add a new condition.',
					'minimum'     => 1,
				),
				'integration'    => array(
					'type'        => 'string',
					'description' => 'Integration code (e.g. WP). Required when adding.',
				),
				'condition_code' => array(
					'type'        => 'string',
					'description' => 'Condition code from the registry. Required when adding.',
				),
				'fields'         => array(
					'type'                 => 'object',
					'description'          => 'Condition fields keyed by option code. On update, merged with the saved fields: omitted fields keep their values; send an empty string to clear one.',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'block_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'condition_id'   => array( 'type' => 'integer' ),
				'block_id'       => array( 'type' => 'integer' ),
				'integration'    => array( 'type' => 'string' ),
				'condition_code' => array( 'type' => 'string' ),
				'fields'         => array( 'type' => 'object' ),
			),
			'required'   => array( 'condition_id', 'block_id', 'integration', 'condition_code', 'fields' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$this->require_authenticated_executor( $user_context );

		$block_id       = (int) ( $params['block_id'] ?? 0 );
		$condition_id   = isset( $params['condition_id'] ) ? (int) $params['condition_id'] : 0;
		$integration    = (string) ( $params['integration'] ?? '' );
		$condition_code = (string) ( $params['condition_code'] ?? '' );
		$fields         = $this->parse_json_param( $params['fields'] ?? array() );

		if ( $condition_id > 0 ) {
			$saved           = $this->condition_service->get_condition( $condition_id );
			$ownership_error = $this->validate_condition_ownership( $condition_id, $block_id, $saved );
			if ( null !== $ownership_error ) {
				return $ownership_error;
			}

			$code_error = $this->validate_renamed_condition( $saved, $integration, $condition_code );
			if ( null !== $code_error ) {
				return $code_error;
			}

			// The sent fields are normalized for their condition (a renamed one included), then laid over the saved ones.
			$data = array(
				'fields' => $this->merge_with_saved_fields(
					$saved['fields'],
					$this->normalize_fields(
						'' !== $integration ? $integration : (string) $saved['integration'],
						'' !== $condition_code ? $condition_code : (string) $saved['condition_code'],
						$fields
					)
				),
			);
			if ( '' !== $integration ) {
				$data['integration'] = $integration;
			}
			if ( '' !== $condition_code ) {
				$data['condition_code'] = $condition_code;
			}
			$result = $this->condition_service->update_condition( $condition_id, $data );
		} else {
			if ( '' === $integration || '' === $condition_code ) {
				return Json_Rpc_Response::create_error_response( 'integration and condition_code are required when adding a condition.' );
			}
			$code_error = $this->validate_registered_condition( $integration, $condition_code );
			if ( null !== $code_error ) {
				return $code_error;
			}
			$result = $this->condition_service->add_condition( $block_id, $integration, $condition_code, $this->normalize_fields( $integration, $condition_code, $fields ) );
		}

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response(
			$condition_id > 0 ? 'Condition updated.' : 'Condition added.',
			array(
				'condition_id'   => (int) $result['id'],
				'block_id'       => (int) $result['block_id'],
				'integration'    => (string) $result['integration'],
				'condition_code' => (string) $result['condition_code'],
				'fields'         => $this->ensure_object( $result['fields'] ),
			)
		);
	}

	/**
	 * Lay the caller's fields over the condition's saved fields.
	 *
	 * An omitted field keeps its saved value and a field sent as an empty
	 * string is cleared, as `save_action` and `save_loop_filter` do. A sent
	 * field replaces the whole saved field: its saved `_readable` label and
	 * `_custom` value go unless the caller sends them too, so a label never
	 * names the value it replaced.
	 *
	 * @since 8.0.0
	 *
	 * @param array $saved Saved condition fields.
	 * @param array $sent  Fields the caller sent.
	 *
	 * @return array Fields to store.
	 */
	private function merge_with_saved_fields( array $saved, array $sent ): array {
		$merged = array_replace( $saved, $sent );

		foreach ( array_keys( $sent ) as $code ) {
			$code = (string) $code;
			if ( Str::ends_with( $code, '_readable' ) || Str::ends_with( $code, '_custom' ) ) {
				$slot   = Str::ends_with( $code, '_custom' ) ? 'custom' : 'readable';
				$parent = substr( $code, 0, -strlen( '_' . $slot ) );
				if ( is_array( $merged[ $parent ] ?? null ) ) {
					// Fields_Bag backfills a missing nested slot from its flat companion.
					// Keep the flat key even when empty: execution omits empty nested slots.
					unset( $merged[ $parent ][ $slot ] );
				}
				continue;
			}

			foreach ( array( $code . '_readable', $code . '_custom' ) as $companion ) {
				if ( ! array_key_exists( $companion, $sent ) ) {
					unset( $merged[ $companion ] );
				}
			}
		}

		return $merged;
	}

	/**
	 * Check a renamed condition against the registry, as create does.
	 *
	 * The update stores the sent integration and condition_code over the
	 * saved ones, so the pair it stores is checked whenever it differs from
	 * the saved pair. An update that keeps the saved pair (fields only, or
	 * the same codes sent again) is not checked, so a condition whose
	 * integration is inactive still takes field changes.
	 *
	 * @since 8.0.0
	 *
	 * @param array  $saved          The condition as the service reads it.
	 * @param string $integration    Integration code the caller sent, or ''.
	 * @param string $condition_code Condition code the caller sent, or ''.
	 *
	 * @return array|null Error response when the new pair is unknown, null otherwise.
	 */
	private function validate_renamed_condition( array $saved, string $integration, string $condition_code ): ?array {
		$saved_integration = (string) ( $saved['integration'] ?? '' );
		$saved_code        = (string) ( $saved['condition_code'] ?? '' );
		$new_integration   = '' !== $integration ? $integration : $saved_integration;
		$new_code          = '' !== $condition_code ? $condition_code : $saved_code;

		if ( $new_integration === $saved_integration && $new_code === $saved_code ) {
			return null;
		}

		return $this->validate_registered_condition( $new_integration, $new_code );
	}

	/**
	 * Refuse an integration and condition code the registry does not know.
	 *
	 * @since 8.0.0
	 *
	 * @param string $integration    Integration code.
	 * @param string $condition_code Condition code.
	 *
	 * @return array|null Error response when the pair is unknown, null when it is registered.
	 */
	private function validate_registered_condition( string $integration, string $condition_code ): ?array {
		$exists = $this->registry_service->condition_exists( $integration, $condition_code );
		if ( empty( $exists['exists'] ) ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Unknown condition %s/%s. Discover codes with search type=condition.', $integration, $condition_code )
			);
		}

		return null;
	}

	/**
	 * The condition's fields as the builder stores them: each checkbox's boolean answer as
	 * 'true' or 'false'.
	 *
	 * @since 8.0.0
	 *
	 * @param string $integration    Integration code.
	 * @param string $condition_code Condition code.
	 * @param array  $fields         Condition fields keyed by option code.
	 *
	 * @return array
	 */
	private function normalize_fields( string $integration, string $condition_code, array $fields ): array {
		return ( new Field_Mcp_Input_Resolver() )->normalize_block_modifier( Block_Modifier_Types::FILTER_CONDITION, $integration, $condition_code, $fields );
	}

	/**
	 * Verify the condition belongs to the block the caller named.
	 *
	 * `update_condition()` checks existence only, so a condition_id from another
	 * block would otherwise update silently. Delete_Tool already walks
	 * condition → block → recipe for the same reason.
	 *
	 * @since 8.0.0 Takes the condition the caller read, so the update merges the same read.
	 *
	 * @param int            $condition_id Filter-condition post ID.
	 * @param int            $block_id     Block the caller claims owns it.
	 * @param array|WP_Error $condition    The condition as the service reads it.
	 *
	 * @return array|null Error response when ownership fails, null when valid.
	 */
	private function validate_condition_ownership( int $condition_id, int $block_id, $condition ): ?array {
		if ( is_wp_error( $condition ) ) {
			return Json_Rpc_Response::create_error_response( sprintf( 'Filter condition not found: %d.', $condition_id ) );
		}

		$owner_block_id = (int) ( $condition['block_id'] ?? 0 );

		if ( $owner_block_id !== $block_id ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Filter condition %d belongs to block %d, not block %d.', $condition_id, $owner_block_id, $block_id )
			);
		}

		return null;
	}
}
