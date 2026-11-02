<?php
/**
 * Read and patch authored WordPress content through its native controller.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Shared\Content\Services;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Post_Content_Edit_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Post_Content_Edit_Store;
use Uncanny_Automator\App\Recipe_Builder\Shared\Content\Utilities\Post_Content_Patcher;
use Uncanny_Automator\App\Recipe_Builder\Shared\Content\Utilities\Post_Content_Reader;
use Uncanny_Automator\App\Recipe_Builder\Shared\Content\Utilities\Post_Content_Request_Scope;

/** Native post types own permissions, validation, sanitization and save hooks. */
final class Post_Content_Edit_Service {

	/** Bytes of excerpt shared by every change in one write result. */
	private const EXCERPT_BUDGET = 4096;

	private Post_Content_Edit_Store $store;
	private Post_Content_Patcher $patcher;
	private Post_Content_Reader $reader;

	/** Inject persistence for tests; production uses the current WordPress site. */
	public function __construct( ?Post_Content_Edit_Store $store = null, ?Post_Content_Patcher $patcher = null ) {
		global $wpdb;
		$this->store   = $store ?? new WP_Post_Content_Edit_Store( $wpdb );
		$this->patcher = $patcher ?? new Post_Content_Patcher();
		$this->reader  = new Post_Content_Reader();
	}

	/** Check both native edit-context read access and write permission. */
	public function permissions_check( int $post_id ) {
		$result = $this->authorized_snapshot( $post_id );
		return is_wp_error( $result ) ? $result : true;
	}

	/** Refuse withheld or changed source, allowing the known core JSON escaping. */
	private function authorized_snapshot( int $post_id ) {
		if ( ! is_user_logged_in() ) {
			return $this->error( 'rest_forbidden', 'Sign in before reading or editing authored content.', 401 );
		}
		$target = $this->target( $post_id );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		$request = $this->native_request( $target, 'GET' );
		$allowed = $target['controller']->get_item_permissions_check( $request );
		if ( is_wp_error( $allowed ) || true !== $allowed ) {
			return is_wp_error( $allowed ) ? $allowed : $this->error( 'rest_forbidden', 'You cannot read this post for editing.', 403 );
		}
		$allowed = $target['controller']->update_item_permissions_check( $this->native_request( $target, 'POST' ) );
		if ( is_wp_error( $allowed ) || true !== $allowed ) {
			return is_wp_error( $allowed ) ? $allowed : $this->error( 'rest_forbidden', 'You cannot edit this post.', 403 );
		}
		$native = Post_Content_Request_Scope::dispatch( $request );
		if ( is_wp_error( $native ) ) {
			return $native;
		}
		$data = $native->get_data();
		if ( ! is_string( $data['content']['raw'] ?? null ) || ! $this->native_source_matches( $target['post']->post_content, $data['content']['raw'] ) ) {
			return $this->error( 'post_content_edit_source_unavailable', 'The site does not expose complete authored source for editing.', 403 );
		}
		return $this->snapshot( $target['post'] );
	}

	/**
	 * Core Block Hooks escape double hyphens in block-comment JSON on REST reads.
	 * Compare against that one lossless representation, not broadly normalized HTML
	 * or parsed blocks: provider redaction and other source changes must still fail.
	 * The original stored bytes remain authoritative for hashes, offsets and edits.
	 */
	private function native_source_matches( string $source, string $native ): bool {
		if ( $source === $native ) {
			return true;
		}
		// Use the block delimiter grammar from WP_Block_Parser::next_token(),
		// restricted to opening/void blocks with valid JSON object attributes.
		$encoded = preg_replace_callback(
			'/<!--\s+wp:(?:[a-z][a-z0-9_-]*\/)?[a-z][a-z0-9_-]*\s+({(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)(?:\/)?-->/s',
			static function ( array $match ): string {
				if ( ! is_object( json_decode( $match[1] ) ) ) {
					return $match[0];
				}
				return str_replace( $match[1], str_replace( '--', '\\u002d\\u002d', $match[1] ), $match[0] );
			},
			$source
		);
		return null !== $encoded && $native === $encoded;
	}

	/**
	 * Return authored source, or a bounded view of it, with the whole-document fingerprint.
	 *
	 * Without a view, documents up to 64 KiB are returned whole and larger ones as an
	 * outline, so one read never floods a client. Every view reads raw post_content;
	 * nothing is rendered and shortcodes stay literal.
	 *
	 * @param array $args view, bytes, offset, length, search, context, max_matches, depth.
	 */
	public function read( int $post_id, array $args = array() ) {
		clean_post_cache( $post_id );
		$snapshot = $this->authorized_snapshot( $post_id );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		$raw  = $snapshot['content']['raw'];
		$view = $args['view'] ?? null;
		if ( null === $view ) {
			$view = strlen( $raw ) <= Post_Content_Reader::FULL_LIMIT ? 'full' : 'outline';
		}
		$base = $this->describe( $snapshot ) + array( 'view' => $view );
		if ( isset( $args['element'] ) && 'wpbakery' !== ( $args['editor'] ?? '' ) ) {
			return $this->error( 'post_content_editor_required', 'Element inspection requires editor=wpbakery.', 400 );
		}
		if ( isset( $args['editor'] ) ) {
			if ( 'wpbakery' !== $args['editor'] ) {
				return $this->error( 'post_content_editor_unsupported', 'This content editor is not supported.', 400 );
			}
			$inspection = ( new Wpbakery_Content_Inspector() )->inspect( get_post( $post_id ), $args['element'] ?? '' );
			if ( is_wp_error( $inspection ) ) {
				return $inspection;
			}
			$base['editor'] = $inspection;
		}
		if ( 'full' === $view ) {
			return $base + array( 'content' => $snapshot['content'] );
		}
		$slice = $this->bounded( $args['bytes'] ?? $args['length'] ?? Post_Content_Reader::DEFAULT_SLICE, 1, Post_Content_Reader::MAX_SLICE );
		switch ( $view ) {
			case 'head':
				return $base + $this->reader->head( $raw, $slice );
			case 'tail':
				return $base + $this->reader->tail( $raw, $slice );
			case 'range':
				$range = $this->reader->range( $raw, max( 0, (int) ( $args['offset'] ?? 0 ) ), $slice );
				return is_wp_error( $range ) ? $range : $base + $range;
			case 'search':
				if ( ! is_string( $args['search'] ?? null ) || '' === $args['search'] ) {
					return $this->error( 'post_content_view_invalid', 'The search view needs a non-empty search string.', 400 );
				}
				return $base + $this->reader->search( $raw, $args['search'], $this->bounded( $args['context'] ?? 300, 0, Post_Content_Reader::MAX_CONTEXT ), $this->bounded( $args['max_matches'] ?? 10, 1, Post_Content_Reader::MAX_MATCHES ) );
			default:
				$outline = $this->reader->outline( $raw, $this->bounded( $args['depth'] ?? 3, 1, 10 ) );
				if ( ! isset( $args['view'] ) ) {
					$outline['notice'] = 'The content is larger than 64 KiB, so an outline is returned instead of the full source. Use view=search to find text, view=range with an offset from this outline to read a block, or view=full to read everything.';
				}
				return $base + $outline;
		}
	}

	/**
	 * Apply every edit in memory before calling the native save pipeline once.
	 *
	 * An error returned before that save carries `nothing_saved` in its data:
	 * the request was refused and the site is unchanged.
	 */
	public function apply( int $post_id, string $expected_content_hash, array $edits, bool $include_content = false, string $editor = '' ) {
		$saving = false;
		$result = $this->attempt( $post_id, $expected_content_hash, $edits, $include_content, $editor, $saving );

		return $saving ? $result : self::mark_nothing_saved( $result );
	}

	/**
	 * Validate, patch and save.
	 *
	 * @param bool $saving Set once the native save pipeline is called.
	 */
	private function attempt( int $post_id, string $expected_content_hash, array $edits, bool $include_content, string $editor, bool &$saving ) {
		if ( ! in_array( $editor, array( '', 'wpbakery' ), true ) ) {
			return $this->error( 'post_content_editor_unsupported', 'This content editor is not supported.', 400 );
		}
		$input = wp_json_encode( $edits );
		if ( false === $input || strlen( $input ) > 262144 ) {
			return $this->error( 'post_content_edit_too_large', 'Edits exceed the 256 KiB input limit.', 413 );
		}
		$allowed = $this->permissions_check( $post_id );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		return $this->store->with_post_lock(
			$post_id,
			function () use ( $post_id, $expected_content_hash, $edits, $include_content, $editor, &$saving ) {
				$allowed = $this->permissions_check( $post_id );
				if ( is_wp_error( $allowed ) ) {
					return $allowed;
				}
				$target = $this->target( $post_id );
				if ( is_wp_error( $target ) ) {
					return $target;
				}
				$before = $this->snapshot( $target['post'] );
				if ( ! hash_equals( $before['content_hash'], $expected_content_hash ) ) {
					return $this->error( 'post_content_edit_stale', 'The content changed. Nothing was saved. Read it again before preparing new edits.', 409 );
				}
				$patched = $this->patcher->apply_with_changes( $before['content']['raw'], $edits );
				if ( is_wp_error( $patched ) ) {
					return $patched;
				}
				$content   = $patched['content'];
				$lifecycle = 'wpbakery' === $editor ? new Wpbakery_Content_Lifecycle() : null;
				if ( null !== $lifecycle ) {
					try {
						$validated = $lifecycle->validate( $target['post'], $before['content']['raw'], $content, $edits );
					} catch ( \Throwable $error ) {
						return $this->error( 'wpbakery_validation_failed', 'WPBakery could not validate this edit. Nothing was saved. Check the native editor and its element registrations.', 503 );
					}
					if ( is_wp_error( $validated ) ) {
						return $validated;
					}
				}
				if ( $content === $before['content']['raw'] ) {
					$result = $this->result( $before, 'unchanged', 0, 'exact', array(), $include_content );
					return $this->complete_editor( $post_id, $lifecycle, $result );
				}
				$request = $this->native_request( $target, 'POST' );
				$request->set_body_params( array( 'content' => $content ) );
				// Dispatch the registered native route, retaining provider middleware,
				// permission overrides and Page Builder's rest_pre_insert refusal.
				$saving = true;
				$result = Post_Content_Request_Scope::dispatch( $request );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				clean_post_cache( $post_id );
				$saved = $this->authorized_snapshot( $post_id );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
				if ( null !== $lifecycle && $content !== $saved['content']['raw'] ) {
					return $this->error( 'wpbakery_source_changed', 'WordPress changed the requested WPBakery source. The coupled content and styling update was refused. Read the page before retrying.', 409 );
				}
				// Native sanitizers can remove the entire requested change. A successful
				// save callback is not proof that any content edit persisted.
				if ( $before['content']['raw'] === $saved['content']['raw'] ) {
					return $this->result( $saved, 'not_saved', 0, 'changed_by_wordpress', array(), $include_content );
				}
				$exact  = $content === $saved['content']['raw'];
				$result = $this->result( $saved, 'edited', count( $edits ), $exact ? 'exact' : 'changed_by_wordpress', $this->changes( $exact ? $saved['content']['raw'] : $content, $patched['changes'] ), $include_content );
				return $this->complete_editor( $post_id, $lifecycle, $result );
			}
		);
	}

	/** Complete derived state before the store commits the content update. */
	private function complete_editor( int $post_id, ?Wpbakery_Content_Lifecycle $lifecycle, array $result ) {
		if ( null === $lifecycle ) {
			return $result;
		}
		$editor = $lifecycle->rebuild( $post_id );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}
		clean_post_cache( $post_id );
		$post = get_post( $post_id );
		if ( ! $post || $result['status'] !== $post->post_status || hash( 'sha256', $post->post_content ) !== $result['content_hash'] ) {
			return $this->error( 'wpbakery_source_changed', 'The page changed during native styling generation. Read the page before retrying.', 409 );
		}
		$result['editor'] = $editor;
		return $result;
	}

	/** Resolve an existing content-bearing post type and its actual controller. */
	private function target( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || $post_id <= 0 ) {
			return $this->error( 'rest_post_invalid_id', 'Post not found.', 404 );
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || ! $type->show_in_rest || ! post_type_supports( $post->post_type, 'editor' ) || in_array( $post->post_type, array( 'attachment', 'revision', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_font_family', 'wp_font_face' ), true ) ) {
			return $this->error( 'post_content_edit_unsupported', 'This post type does not support ordinary REST content editing.', 400 );
		}
		$controller = $type->get_rest_controller();
		if ( ! $controller instanceof \WP_REST_Posts_Controller ) {
			return $this->error( 'post_content_edit_unsupported', 'This provider needs its own content editing operation.', 400 );
		}
		return array(
			'post' => $post,
			'type' => $type,
			'controller' => $controller,
		);
	}

	/** Build a content-only native request without rendering unused response fields. */
	private function native_request( array $target, string $method ): \WP_REST_Request {
		$type      = $target['type'];
		$namespace = $type->rest_namespace ?? 'wp/v2';
		$path      = '/' . ( $namespace ? $namespace : 'wp/v2' ) . '/' . ( $type->rest_base ? $type->rest_base : $type->name ) . '/' . $target['post']->ID;
		$request   = new \WP_REST_Request( $method, $path );
		$request->set_url_params( array( 'id' => $target['post']->ID ) );
		$request->set_query_params(
			array(
				'context' => 'edit',
				'_fields' => 'GET' === $method ? 'id,content.raw' : 'id',
			)
		);
		return $request;
	}

	/**
	 * Compact write result: the new fingerprint and a short window around each change.
	 * The full source is returned only when the caller asks for it.
	 */
	private function result( array $snapshot, string $action, int $applied, string $verification, array $changes, bool $include_content ): array {
		$result = $this->describe( $snapshot ) + array(
			'action' => $action,
			'edits_applied' => $applied,
			'verification' => $verification,
			'changes' => $changes,
		);
		if ( 'not_saved' === $action ) {
			$result['notice'] = 'WordPress did not retain the requested content changes. The saved content is unchanged. Review the permitted markup or save filters before preparing another edit.';
		} elseif ( 'changed_by_wordpress' === $verification ) {
			$result['notice'] = 'WordPress changed the saved source after the edit. The change windows show what was submitted; read the content again before the next edit.';
		}
		if ( $include_content ) {
			$result['content'] = $snapshot['content'];
		}
		return $result;
	}

	/**
	 * Each change with source on either side, sharing one excerpt budget across the
	 * whole result. One change shows up to 800 bytes with 200 on either side; many
	 * changes each show less, so a large batch never floods the reply.
	 */
	private function changes( string $source, array $spans ): array {
		$windows = array();
		$each    = max( 160, min( 1200, intdiv( self::EXCERPT_BUDGET, max( 1, count( $spans ) ) ) ) );
		$context = min( 200, intdiv( $each, 4 ) );
		foreach ( $spans as $span ) {
			$shown     = min( $span['length'], $each - 2 * $context );
			$view      = $this->reader->range( $source, max( 0, $span['offset'] - $context ), $shown + 2 * $context );
			$windows[] = array(
				'index' => $span['index'],
				'offset' => $span['offset'],
				'length' => $span['length'],
				'excerpt' => is_wp_error( $view ) ? '' : $view['text'],
				'excerpt_truncated' => $span['length'] > $shown,
			);
		}
		return $windows;
	}

	/** Clamp a view size, so direct callers get the same limits REST validation enforces. */
	private function bounded( $value, int $min, int $max ): int {
		return max( $min, min( $max, (int) $value ) );
	}

	/** Whole-document facts shared by every read view and write result. */
	private function describe( array $snapshot ): array {
		$raw = $snapshot['content']['raw'];
		return array(
			'post_id' => $snapshot['post_id'],
			'post_type' => $snapshot['post_type'],
			'status' => $snapshot['status'],
			'content_hash' => $snapshot['content_hash'],
			'total_bytes' => strlen( $raw ),
			'total_lines' => $this->reader->line_count( $raw ),
		);
	}

	/** Return only the fields needed to prepare and verify a content edit. */
	private function snapshot( \WP_Post $post ): array {
		return array(
			'post_id' => (int) $post->ID,
			'post_type' => $post->post_type,
			'status' => $post->post_status,
			'content' => array( 'raw' => $post->post_content ),
			'content_hash' => hash( 'sha256', $post->post_content ),
		);
	}

	/**
	 * Flag an error that happened before anything was written.
	 *
	 * @param array|\WP_Error $result Edit result.
	 * @return array|\WP_Error
	 */
	private static function mark_nothing_saved( $result ) {
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			$data = $result->get_error_data( $code );
			// Set in place: add_data() would move the old data to additional_data.
			$result->error_data[ $code ] = array_merge( is_array( $data ) ? $data : array(), array( 'nothing_saved' => true ) );
		}

		return $result;
	}

	/** Keep native-compatible HTTP errors. */
	private function error( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
