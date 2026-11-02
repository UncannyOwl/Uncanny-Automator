<?php
/**
 * Read authored source and apply precise content edits for WordPress post types.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Post_Content;

use Uncanny_Automator\App\Recipe_Builder\Shared\Content\Services\Post_Content_Edit_Service;
use Uncanny_Automator\App\Recipe_Builder\Shared\Content\Utilities\Post_Content_Reader;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Rest_Request_Scope;

/** Thin REST adapter; native permissions and content mutation live in the service. */
final class Post_Content_Rest_Controller extends \WP_REST_Controller {

	public const ROUTE = '/automator/v1/posts/(?P<id>[\d]+)/content-edit';
	private Post_Content_Edit_Service $service;

	/** Bind the content use case. */
	public function __construct( ?Post_Content_Edit_Service $service = null ) {
		$this->namespace = 'automator/v1';
		$this->rest_base = 'posts';
		$this->service   = $service ?? new Post_Content_Edit_Service();
	}

	/** Register the read-before-edit pair, independent of the site's REST prefix. */
	public function register_routes() {
		$id = array(
			'id' => array(
				'type' => 'integer',
				'minimum' => 1,
				'required' => true,
			),
		);
		register_rest_route(
			$this->namespace,
			'/posts/(?P<id>[\d]+)/content-edit',
			array(
				array(
					'methods' => 'GET',
					'callback' => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args' => array_merge( $id, self::read_arguments() ),
				),
				array(
					'methods' => 'POST',
					'callback' => array( $this, 'post_apply_content_edit' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args' => array_merge(
						$id,
						self::edit_arguments()
					),
				),
			)
		);
	}

	/** Bounded read views of raw source; nothing is rendered. */
	public static function read_arguments(): array {
		return array(
			'editor' => array(
				'type' => 'string',
				'enum' => array( 'wpbakery' ),
				'description' => 'Also inspect native WPBakery 9.0.1 element availability, permissions and stored CSS diagnostics without rendering or rebuilding styles.',
			),
			'element' => array(
				'type' => 'string',
				'pattern' => '^[a-zA-Z0-9_-]{1,128}$',
				'description' => 'With editor=wpbakery, return the named registered element definition and parameters, for example vc_column or vc_btn. Read the catalog first.',
			),
			'view' => array(
				'type' => 'string',
				'enum' => Post_Content_Reader::VIEWS,
				'description' => 'Omit for the whole source up to 64 KiB, or an outline when larger. outline: block map with byte offsets. head/tail: first or last bytes. range: bytes from offset. search: literal matches with context and whether the text is unique. full: everything. All views read raw post_content; nothing is rendered and shortcodes stay literal.',
			),
			'bytes' => array(
				'type' => 'integer',
				'minimum' => 1,
				'maximum' => Post_Content_Reader::MAX_SLICE,
				'description' => 'Bytes to return for head, tail or range. Default 8192.',
			),
			'offset' => array(
				'type' => 'integer',
				'minimum' => 0,
				'description' => 'Start byte for range, for example an offset from the outline or a search match.',
			),
			'length' => array(
				'type' => 'integer',
				'minimum' => 1,
				'maximum' => Post_Content_Reader::MAX_SLICE,
				'description' => 'Bytes to return for range, same as bytes.',
			),
			'search' => array(
				'type' => 'string',
				'minLength' => 1,
				'description' => 'Literal text to find for the search view.',
			),
			'context' => array(
				'type' => 'integer',
				'minimum' => 0,
				'maximum' => Post_Content_Reader::MAX_CONTEXT,
				'description' => 'Bytes of source before and after each search match. Default 300.',
			),
			'max_matches' => array(
				'type' => 'integer',
				'minimum' => 1,
				'maximum' => Post_Content_Reader::MAX_MATCHES,
				'description' => 'Matches to return for search; match_count always reports the total. Default 10.',
			),
			'depth' => array(
				'type' => 'integer',
				'minimum' => 1,
				'maximum' => 10,
				'description' => 'Deepest nesting level listed in the outline. Default 3.',
			),
		);
	}

	/** Shared REST and primary MCP content-edit input definitions. */
	public static function edit_arguments(): array {
		return array(
			'editor' => array(
				'type' => 'string',
				'enum' => array( 'wpbakery' ),
				'description' => 'Explicit WPBakery mode checks native element permissions and rebuilds and verifies its generated CSS in the guarded content save. Requires WPBakery 9.0.1. Does not change status or page settings. Published edits are immediately live. Without this option only authored content is edited.',
			),
			'expected_content_hash' => array(
				'type' => 'string',
				'pattern' => '^[a-f0-9]{64}$',
				'required' => true,
				'description' => 'content_hash from a fresh GET on this endpoint. Changed content is refused with 409.',
			),
			'edits' => array(
				'type' => 'array',
				'minItems' => 1,
				'maxItems' => 50,
				'required' => true,
				'description' => 'Ordered source edits; all must succeed before saving. replace, insert_before, insert_after and delete match a literal search that must occur exactly once. replace_range and delete_range use offset and length from a content read of the same content_hash, for blocks too large to quote. copy_range and move_range place whole blocks (offset, length) at a block boundary (to) without resending them; a copy gets fresh block_id values. A later edit sees the source after earlier ones. Applies to post_content of posts, pages and supported custom post types. Preserve block markup. Page Builder content uses edit_part instead.',
				'items' => array(
					'type' => 'object',
					'additionalProperties' => false,
					'properties' => array(
						'action' => array(
							'type' => 'string',
							'enum' => array( 'replace', 'insert_before', 'insert_after', 'delete', 'replace_range', 'delete_range', 'copy_range', 'move_range' ),
							'default' => 'replace',
						),
						'search' => array(
							'type' => 'string',
							'minLength' => 1,
							'description' => 'Unique literal source for replace, insert_before, insert_after and delete.',
						),
						'replace' => array(
							'type' => 'string',
							'description' => 'Replacement source for replace or replace_range. Empty string deletes the matched source.',
						),
						'content' => array(
							'type' => 'string',
							'description' => 'Source to insert before or after the match.',
						),
						'offset' => array(
							'type' => 'integer',
							'minimum' => 0,
							'description' => 'Start byte for replace_range, delete_range, copy_range or move_range, from an outline, range or search read.',
						),
						'length' => array(
							'type' => 'integer',
							'minimum' => 1,
							'description' => 'Bytes to replace, delete, copy or move from offset. For copy and move, use a block offset and length from an outline, or a span of whole sibling blocks.',
						),
						'to' => array(
							'type' => 'integer',
							'minimum' => 0,
							'description' => 'Destination for copy_range or move_range, in the source before this edit: a block offset to place before that block, or a block offset plus its length to place after it.',
						),
					),
				),
			),
			'include_content' => array(
				'type' => 'boolean',
				'default' => false,
				'description' => 'Return the full saved source. By default the result has the new content_hash and a short excerpt around each change.',
			),
		);
	}

	/** Delegate native object-level access checks. */
	public function permissions_check( \WP_REST_Request $request ) {
		return $this->service->permissions_check( (int) $request['id'] );
	}

	/** Read current source and its content fingerprint. */
	public function get_item( $request ) {
		$args = array();
		foreach ( array_keys( self::read_arguments() ) as $key ) {
			if ( null !== $request[ $key ] ) {
				$args[ $key ] = $request[ $key ];
			}
		}
		return rest_ensure_response( $this->service->read( (int) $request['id'], $args ) );
	}

	/** Apply source edits under native save and concurrency checks. */
	public function post_apply_content_edit( \WP_REST_Request $request ) {
		$result = $this->service->apply( (int) $request['id'], $request['expected_content_hash'], $request['edits'], true === $request['include_content'], $request['editor'] ?? '' );
		Rest_Request_Scope::refuse_if_nothing_saved( $result );
		return rest_ensure_response( $result );
	}
}
