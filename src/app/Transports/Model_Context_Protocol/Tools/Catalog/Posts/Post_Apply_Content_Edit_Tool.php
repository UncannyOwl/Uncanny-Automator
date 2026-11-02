<?php
/**
 * Precise post content edits as a primary MCP tool.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Posts;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Rest_Operation_Service;
use Uncanny_Automator\App\Transports\Restful\Post_Content\Post_Content_Rest_Controller;

/** Reuse the native operation; this wrapper never patches or saves content. */
final class Post_Apply_Content_Edit_Tool extends Abstract_MCP_Tool {

	public const NAME = 'post_apply_content_edit';

	/** Primary name advertised in tools/list. */
	public function get_name(): string {
		return self::NAME;
	}

	/** Explain the supported source and required read-before-edit flow. */
	public function get_description(): string {
		return 'Apply precise literal edits to post_content of a blog post, page or supported custom post type, including product or course descriptions when the site exposes native REST content editing. '
			. 'First use rest_request GET /automator/v1/posts/{post_id}/content-edit for content_hash and the source. Large content returns an outline; read only what you need with query view=search (search, context), view=range (offset, bytes), view=head or view=tail. All views are raw source; nothing is rendered. '
			. 'Send post_id, expected_content_hash and ordered edits. Replace uses search and replace; insert_before/insert_after use search and content; delete uses search. '
			. 'For a block too large to quote, replace_range (offset, length, replace) or delete_range (offset, length) uses offsets from a read of the same content_hash, such as an outline block. To duplicate or reorder blocks without resending them, copy_range or move_range (offset, length, to) takes whole blocks from an outline and places them at to, a block offset or a block offset plus its length; a copy gets fresh block_id values, so rename any anchor in the copy. '
			. 'The result has the new content_hash and a short excerpt around each change; set include_content only when you need the whole saved source. '
			. 'Each search must match exactly once. Missing, ambiguous or stale source saves nothing. Do not rewrite the whole article for a small change. '
			. 'Preserve block markup. By default this edits only post_content, not prices, stock, settings or metadata. Explicit editor=wpbakery also rebuilds and verifies WPBakery generated CSS under native element permissions. Page Builder-owned content uses edit_part. '
			. 'Requires write access and native permissions. Inspect the saved source and verification; do not blindly retry an uncertain write.';
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

	/** Advertise the same patch contract used by the REST operation. */
	protected function schema_definition(): array {
		$properties = Post_Content_Rest_Controller::edit_arguments();
		foreach ( $properties as &$definition ) {
			unset( $definition['required'] );
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
			'required' => array( 'post_id', 'expected_content_hash', 'edits' ),
		);
	}

	/** Forward to the existing authorized REST save path. */
	protected function execute_tool( User_Context $context, array $params ): array {
		$body = array(
			'expected_content_hash' => $params['expected_content_hash'],
			'edits' => $params['edits'],
		);
		if ( true === ( $params['include_content'] ?? false ) ) {
			$body['include_content'] = true;
		}
		if ( isset( $params['editor'] ) ) {
			$body['editor'] = $params['editor'];
		}
		$dispatch_started = false;
		$result           = ( new Rest_Operation_Service() )->request(
			$context,
			array(
				'path' => '/automator/v1/posts/' . $params['post_id'] . '/content-edit',
				'method' => 'POST',
				'body' => $body,
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
				return Json_Rpc_Response::create_error_response( 'The content edit was refused before anything was saved. The body contains WordPress-reported details.', $result );
			}
			$result = Json_Rpc_Response::mark_unconfirmed_native_result( $result, Json_Rpc_Response::UNCONFIRMED_WRITE_GUIDANCE );
			return Json_Rpc_Response::create_error_response( 'WordPress returned a non-success response for the content edit. The body contains WordPress-reported details. ' . Json_Rpc_Response::UNCONFIRMED_WRITE_GUIDANCE, $result );
		}
		// Native WordPress may accept the request while retaining no content changes.
		$messages = array(
			'edited' => 'Content edit saved. Inspect verification and the change excerpts.',
			'unchanged' => isset( $result['body']['editor'] ) ? 'The requested content is already present. Inspect the editor result for generated styling verification.' : 'The requested content is already present. No save was needed.',
			'not_saved' => 'WordPress did not retain the requested content changes. Inspect verification and the notice.',
		);
		$message  = $messages[ $result['body']['action'] ?? '' ] ?? 'Content edit processed. Inspect the result before claiming it was saved.';
		return Json_Rpc_Response::create_success_response( $message, $result );
	}
}
