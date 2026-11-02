<?php
/**
 * Read and change WordPress post meta in its stored form.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Post_Meta;

use Uncanny_Automator\App\Recipe_Builder\Shared\Meta\Services\Post_Meta_Edit_Service;
use Uncanny_Automator\App\Recipe_Builder\Shared\Meta\Utilities\Post_Meta_Patcher;
use Uncanny_Automator\App\Recipe_Builder\Shared\Meta\Utilities\Post_Meta_Value;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Rest_Request_Scope;

/** Thin REST adapter; permissions, storage rules and writes live in the service. */
final class Post_Meta_Rest_Controller extends \WP_REST_Controller {

	public const ROUTE = '/automator/v1/posts/(?P<id>[\d]+)/meta';
	private Post_Meta_Edit_Service $service;

	/** Bind the meta use case. */
	public function __construct( ?Post_Meta_Edit_Service $service = null ) {
		$this->namespace = 'automator/v1';
		$this->rest_base = 'posts';
		$this->service   = $service ?? new Post_Meta_Edit_Service();
	}

	/** Register the read-before-change pair, independent of the site's REST prefix. */
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
			'/posts/(?P<id>[\d]+)/meta',
			array(
				array(
					'methods' => 'GET',
					'callback' => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args' => array_merge( $id, self::read_arguments() ),
				),
				array(
					'methods' => 'POST',
					'callback' => array( $this, 'post_apply_meta_edit' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args' => array_merge( $id, self::edit_arguments() ),
				),
			)
		);
	}

	/** List keys, or read chosen keys in full. Nothing is decoded as a PHP object. */
	public static function read_arguments(): array {
		return array(
			'keys' => array(
				'type' => 'array',
				'minItems' => 1,
				'maxItems' => Post_Meta_Edit_Service::MAX_KEYS,
				'items' => array(
					'type' => 'string',
					'minLength' => 1,
					'maxLength' => 255,
				),
				'description' => 'Exact keys to read in full, with each key\'s hash for changes. Omit to list all keys with values up to 2 KiB and up to 10 rows each; a list leaves hash null where it does not load every row. A key that does not exist is returned with exists: false and the hash to create it.',
			),
			'path' => array(
				'type' => 'array',
				'minItems' => 1,
				'maxItems' => Post_Meta_Patcher::MAX_PATH,
				'items' => self::path_segment(),
				'description' => 'With exactly one key, return only the value at this path inside its stored array, for example ["sizes", "large", "width"].',
			),
			'search' => array(
				'type' => 'string',
				'minLength' => 1,
				'description' => 'List only keys containing this text, ignoring letter case.',
			),
			'page' => array(
				'type' => 'integer',
				'minimum' => 1,
				'default' => 1,
				'description' => 'Page of the key list.',
			),
			'per_page' => array(
				'type' => 'integer',
				'minimum' => 1,
				'maximum' => Post_Meta_Edit_Service::MAX_PER_PAGE,
				'default' => 50,
				'description' => 'Keys per page of the list.',
			),
		);
	}

	/**
	 * Shared REST and MCP change definitions.
	 *
	 * WordPress sanitizes a value with the first anyOf branch it matches, and its
	 * array branch renumbers integer keys. Object precedes array so a matched array
	 * keeps its keys, and changes skip sanitizing entirely: they are validated
	 * against this schema and passed to the service exactly as decoded from JSON.
	 */
	public static function edit_arguments(): array {
		$value = array(
			'description' => 'Any JSON value. JSON objects become PHP arrays with the same keys.',
			'anyOf' => array(
				array( 'type' => 'string' ),
				array( 'type' => 'integer' ),
				array( 'type' => 'number' ),
				array( 'type' => 'boolean' ),
				array( 'type' => 'null' ),
				array( 'type' => 'object' ),
				array( 'type' => 'array' ),
			),
		);
		return array(
			'changes' => array(
				'type' => 'array',
				'minItems' => 1,
				'maxItems' => Post_Meta_Edit_Service::MAX_CHANGES,
				'required' => true,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => array( self::class, 'literal' ),
				'description' => 'Ordered changes, each to a different key, saved together or not at all. Every change sends expected_hash, the key\'s hash from a fresh GET; a changed key is refused with 409. set replaces a value and keeps its stored form: text stays text and arrays stay serialized arrays unless change_type is true. Send arrays and objects as JSON values, never serialized text. patch changes paths inside a stored array and leaves the rest byte-identical. add adds another row to a key that holds several values. delete removes one row by meta_id, or every row of the key. A key with several rows needs meta_id for set and patch.',
				'items' => array(
					'type' => 'object',
					'additionalProperties' => false,
					'required' => array( 'op', 'key', 'expected_hash' ),
					'properties' => array(
						'op' => array(
							'type' => 'string',
							'enum' => array( 'set', 'patch', 'add', 'delete' ),
						),
						'key' => array(
							'type' => 'string',
							'minLength' => 1,
							'maxLength' => 255,
							'description' => 'Exact meta key, as read.',
						),
						'expected_hash' => array(
							'type' => 'string',
							'pattern' => '^[a-f0-9]{64}$',
							'description' => 'The key\'s hash from a fresh GET. A key that does not exist yet has hash ' . Post_Meta_Value::ABSENT_HASH . '.',
						),
						'value' => array_merge( $value, array( 'description' => 'For set and add: text, an integer, an array or an object. WordPress stores top-level integers as text. Inside arrays every JSON type is kept.' ) ),
						'meta_id' => array(
							'type' => 'integer',
							'minimum' => 1,
							'description' => 'Row to change or delete, from a read of this key.',
						),
						'change_type' => array(
							'type' => 'boolean',
							'description' => 'For set: allow changing the stored form, such as text to an array. Plugins that read the key may expect the old form.',
						),
						'edits' => array(
							'type' => 'array',
							'minItems' => 1,
							'maxItems' => Post_Meta_Patcher::MAX_EDITS,
							'description' => 'For patch: ordered path edits. Each sets value at path, or removes the entry with unset: true. Containers along the path must exist; the last key may be new. Removing a list entry does not renumber the rest.',
							'items' => array(
								'type' => 'object',
								'additionalProperties' => false,
								'required' => array( 'path' ),
								'properties' => array(
									'path' => array(
										'type' => 'array',
										'minItems' => 1,
										'maxItems' => Post_Meta_Patcher::MAX_PATH,
										'items' => self::path_segment(),
									),
									'value' => $value,
									'unset' => array(
										'type' => 'boolean',
										'enum' => array( true ),
									),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Keep the decoded JSON exactly as sent; the service validates it.
	 *
	 * @param mixed $value Decoded request value.
	 * @return mixed
	 */
	public static function literal( $value ) {
		return $value;
	}

	/** Delegate post-level access checks. */
	public function permissions_check( \WP_REST_Request $request ) {
		return $this->service->permissions_check( (int) $request['id'] );
	}

	/** List keys or read chosen keys. */
	public function get_item( $request ) {
		$args = array();
		foreach ( array_keys( self::read_arguments() ) as $key ) {
			if ( null !== $request[ $key ] ) {
				$args[ $key ] = $request[ $key ];
			}
		}
		return rest_ensure_response( $this->service->read( (int) $request['id'], $args ) );
	}

	/** Apply changes under the post lock and hash checks. */
	public function post_apply_meta_edit( \WP_REST_Request $request ) {
		$result = $this->service->apply( (int) $request['id'], (array) $request['changes'] );
		Rest_Request_Scope::refuse_if_nothing_saved( $result );
		return rest_ensure_response( $result );
	}

	/** One array key in a path. */
	private static function path_segment(): array {
		return array(
			'anyOf' => array(
				array( 'type' => 'string' ),
				array( 'type' => 'integer' ),
			),
		);
	}
}
