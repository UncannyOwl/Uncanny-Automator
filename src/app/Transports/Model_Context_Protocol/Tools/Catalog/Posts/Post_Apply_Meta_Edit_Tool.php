<?php
/**
 * Post meta changes as an MCP operation.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Posts;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Rest_Operation_Service;
use Uncanny_Automator\App\Transports\Restful\Post_Meta\Post_Meta_Rest_Controller;

/** Reuse the reviewed REST operation; this wrapper never writes meta itself. */
final class Post_Apply_Meta_Edit_Tool extends Abstract_MCP_Tool {

	public const NAME = 'post_apply_meta_edit';

	/** Name advertised in tools/list and as the native ability. */
	public function get_name(): string {
		return self::NAME;
	}

	/** Explain the read-before-change flow and the stored-form rules. */
	public function get_description(): string {
		return 'Change custom fields (post meta) of a post, page or custom post type without breaking their stored form. '
			. 'First use rest_request GET /automator/v1/posts/{post_id}/meta to list keys, then with query keys (an array) to read chosen keys in full. Each key has a hash, rows and stored_as: text, serialized_array, serialized_string, serialized_scalar or null. A missing key has exists: false and the hash to create it. A list shows values up to 2 KiB and 10 rows per key and leaves hash null where it does not load everything; read the key before changing it. '
			. 'Send post_id and ordered changes, each to a different key with that key\'s expected_hash. set replaces a value and keeps its stored form; send arrays and objects as JSON values, never serialized text, and send top-level numbers or flags as the exact text to store, such as "1". patch changes paths inside a stored array and leaves the rest byte-identical; prefer it for large arrays. add adds a row to a key that holds several values. delete removes one row by meta_id, or the whole key. '
			. 'Changes save together or not at all. A changed key, a key with several rows and no meta_id, or a different stored form without change_type: true saves nothing. '
			. 'Keys starting with an underscore are protected by WordPress unless their plugin allows editing; their values are hidden and they cannot be changed here, so use that plugin\'s tools, or the post\'s featured_media and template fields for those settings. Keys named like credentials, such as api_key or smtp_pass, are never shown or changed; other values are shown as the Custom Fields panel shows them. Rows holding PHP objects, damaged or self-referencing serialized data are never changed. '
			. 'Each result reports verification exact, or changed_by_wordpress with the stored value. Read the key again before another change.';
	}

	/** Describe a guarded write; annotations do not grant authorization. */
	protected function get_annotations(): array {
		return array(
			'readOnlyHint' => false,
			'destructiveHint' => true,
			'idempotentHint' => false,
			'openWorldHint' => true,
		);
	}

	/** Advertise the same change contract the REST operation validates. */
	protected function schema_definition(): array {
		$properties = Post_Meta_Rest_Controller::edit_arguments();
		foreach ( $properties as &$definition ) {
			unset( $definition['required'], $definition['validate_callback'], $definition['sanitize_callback'] );
		}
		unset( $definition );
		$properties['post_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
			'description' => 'WordPress post ID, including pages and supported custom post types.',
		);
		return array(
			'type' => 'object',
			'properties' => $properties,
			'required' => array( 'post_id', 'changes' ),
		);
	}

	/** Forward to the authorized REST save path. */
	protected function execute_tool( User_Context $context, array $params ): array {
		$dispatch_started = false;
		$result           = ( new Rest_Operation_Service() )->request(
			$context,
			array(
				'path' => '/automator/v1/posts/' . $params['post_id'] . '/meta',
				'method' => 'POST',
				'body' => array( 'changes' => $params['changes'] ),
			),
			$dispatch_started
		);
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_wp_error_response( $result, $dispatch_started, ! $dispatch_started );
		}
		if ( $result['status'] < 200 || $result['status'] >= 300 ) {
			if ( ! $dispatch_started ) {
				// The route refused before writing anything.
				$result['outcome'] = 'refused_before_running';
				return Json_Rpc_Response::create_error_response( 'The custom field change was refused before anything was saved. The body contains WordPress-reported details.', $result );
			}
			$result = Json_Rpc_Response::mark_unconfirmed_native_result( $result, Json_Rpc_Response::UNCONFIRMED_WRITE_GUIDANCE );
			return Json_Rpc_Response::create_error_response( 'WordPress returned a non-success response for the custom field change. The body contains WordPress-reported details. ' . Json_Rpc_Response::UNCONFIRMED_WRITE_GUIDANCE, $result );
		}
		$messages = array(
			'saved' => 'Meta changes saved. Inspect each change\'s verification.',
			'unchanged' => 'The requested values are already stored. No save was needed.',
		);
		$message  = $messages[ $result['body']['action'] ?? '' ] ?? 'Meta change processed. Inspect the result before claiming it was saved.';
		return Json_Rpc_Response::create_success_response( $message, $result );
	}
}
