<?php
/**
 * Bounded curriculum editing through LearnDash's course builder lifecycle.
 *
 * @package Uncanny_Automator\Integrations\Learndash\Mcp
 */

declare(strict_types=1);

namespace Uncanny_Automator\Integrations\Learndash\Mcp;

use LD_REST_Courses_Steps_Controller_V2;
use LDLMS_Factory_Post;
use RuntimeException;
use Throwable;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Post_Content_Edit_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Post_Content_Edit_Store;
use WP_Error;
use WP_REST_Request;

/**
 * Validates and verifies complete curriculum replacements through LearnDash.
 *
 * The native model owns relationships, ordering, sections and derived state.
 * This service adds bounded input, authorization and stale-edit protection.
 *
 * @package Uncanny_Automator\Integrations\Learndash\Mcp
 */
final class Curriculum_Service {

	/**
	 * Maximum number of lesson, topic and quiz nodes in one curriculum.
	 *
	 * @var int
	 */
	public const MAX_STEPS = 500;

	/**
	 * Shared post-edit transaction and rollback boundary.
	 *
	 * @var Post_Content_Edit_Store
	 */
	private Post_Content_Edit_Store $lock;

	/**
	 * Authorization and locking checks for native relationship cleanup.
	 *
	 * @var LearnDash_Curriculum_References
	 */
	private LearnDash_Curriculum_References $references;

	/**
	 * Initialize the service with the shared edit lock and reference adapter.
	 *
	 * @param Post_Content_Edit_Store|null         $lock       Transaction boundary. Defaults to the shared WordPress store.
	 * @param LearnDash_Curriculum_References|null $references Reference adapter. Defaults to the current site's connection.
	 */
	public function __construct( ?Post_Content_Edit_Store $lock = null, ?LearnDash_Curriculum_References $references = null ) {
		global $wpdb;
		$this->lock       = $lock ?? new WP_Post_Content_Edit_Store( $wpdb );
		$this->references = $references ?? new WP_LearnDash_Curriculum_References( $wpdb );
	}

	/**
	 * Define the complete curriculum replacement schema.
	 *
	 * Ordered arrays preserve authored placement across JSON clients. Numeric
	 * object keys can be reordered by JavaScript before a request reaches PHP.
	 *
	 * @return array JSON Schema for the ordered curriculum and expected revision.
	 */
	public static function schema(): array {
		$id      = array(
			'type'    => 'integer',
			'minimum' => 1,
		);
		$quizzes = array(
			'type'     => 'array',
			'maxItems' => self::MAX_STEPS,
			'items'    => $id,
		);
		$topics  = array(
			'type'     => 'array',
			'maxItems' => self::MAX_STEPS,
			'items'    => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'id', 'quizzes' ),
				'properties'           => array(
					'id'      => $id,
					'quizzes' => $quizzes,
				),
			),
		);
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'expected_revision', 'lessons', 'quizzes' ),
			'properties'           => array(
				'expected_revision' => array(
					'type'        => 'string',
					'pattern'     => '^[a-f0-9]{64}$',
					'description' => 'Revision from a fresh curriculum GET. A changed curriculum or builder mode is refused with 409.',
				),
				'lessons'           => array(
					'type'        => 'array',
					'maxItems'    => self::MAX_STEPS,
					'description' => 'Complete ordered lessons, topics and nested quizzes. Omitted existing steps are detached from this course, not deleted. At most 500 step IDs in the whole curriculum; each ID appears once.',
					'items'       => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'id', 'topics', 'quizzes' ),
						'properties'           => array(
							'id'      => $id,
							'topics'  => $topics,
							'quizzes' => $quizzes,
						),
					),
				),
				'quizzes'           => $quizzes + array( 'description' => 'Complete ordered course-level quizzes. Lesson and topic quizzes belong inside their parent nodes.' ),
				'sections'          => array(
					'type'        => 'array',
					'maxItems'    => 100,
					'description' => 'Omit to preserve existing section headings. Send [] to remove them, or the complete replacement. Order is the zero-based position in the combined headings-and-lessons outline, in increasing order. IDs are unique positive virtual IDs, not post IDs. Titles are plain text.',
					'items'       => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'id', 'title', 'order' ),
						'properties'           => array(
							'id'    => $id,
							'title' => array(
								'type'      => 'string',
								'minLength' => 1,
								'maxLength' => 200,
							),
							'order' => array(
								'type'    => 'integer',
								'minimum' => 0,
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Require administrator access and native permission to edit the target course.
	 *
	 * @param int $course_id Target course ID.
	 *
	 * @return bool|WP_Error True when permitted, or an authorization/dependency error.
	 */
	public function permissions_check( int $course_id ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html_x( 'An administrator is required to inspect or edit the curriculum.', 'LearnDash', 'uncanny-automator' ), array( 'status' => rest_authorization_required_code() ) );
		}
		if ( ! class_exists( 'LD_REST_Courses_Steps_Controller_V2' ) || ! class_exists( 'LDLMS_Factory_Post' ) || ! method_exists( 'LDLMS_Course_Steps', 'set_steps_keeping_sections' ) ) {
			return new WP_Error( 'learndash_curriculum_unavailable', esc_html_x( 'Activate LearnDash 4.10 or newer to edit a curriculum while preserving its sections.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 503 ) );
		}
		$post = get_post( $course_id );
		if ( ! $post || learndash_get_post_type_slug( 'course' ) !== $post->post_type || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return new WP_Error( 'learndash_curriculum_course_invalid', esc_html_x( 'Choose an existing LearnDash course that is not trashed or an auto-draft.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 404 ) );
		}
		$controller = new LD_REST_Courses_Steps_Controller_V2();
		$request    = new WP_REST_Request( 'POST' );
		$request->set_param( 'id', $course_id );
		$allowed = $controller->update_course_steps_permissions_check( $request );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		if ( true !== $allowed || ! current_user_can( 'edit_post', $course_id ) ) {
			return new WP_Error( 'rest_forbidden', esc_html_x( 'You cannot edit this LearnDash course.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Read the curriculum through the native model after checking the transaction boundary.
	 *
	 * Native inspection can reconcile stored relationships and derived metadata;
	 * callers therefore need the same editing permission as a replacement.
	 *
	 * @param int $course_id Target course ID.
	 *
	 * @return array|WP_Error Ordered curriculum snapshot, or an inspection error.
	 */
	public function read( int $course_id ) {
		$available = $this->transaction_boundary();
		if ( is_wp_error( $available ) ) {
			return $available;
		}
		$allowed = $this->permissions_check( $course_id );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$affected = array( $course_id );
		try {
			// Native inspection can save repairs. Serialize it with curriculum writes
			// so an older inspection cannot overwrite a newer builder save.
			return $this->with_curriculum_lock(
				$course_id,
				$affected,
				function () use ( $course_id, &$affected ) {
					$this->references->lock_post_ids( array( $course_id ) );
					$existing = $this->authorized_existing_ids( $course_id, true );
					if ( is_wp_error( $existing ) ) {
						return $existing;
					}
					$affected = array_merge( $affected, $existing );
					return $this->snapshot( $course_id, $affected );
				}
			);
		} catch ( Throwable $exception ) {
			return new WP_Error( 'learndash_curriculum_storage_unavailable', esc_html_x( 'The curriculum could not be inspected safely. Read it again before editing.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 503 ) );
		}
	}

	/**
	 * Refuse native reconciliation inside a caller-owned transaction.
	 *
	 * LearnDash also uses plain SQL reads that can see an older transaction
	 * snapshot. It could rebuild newer relationships from that stale view before
	 * our revision check. Refusal preserves both the data and caller's transaction.
	 *
	 * @return bool|WP_Error True when no transaction is active, or a boundary error.
	 */
	private function transaction_boundary() {
		try {
			if ( $this->references->has_transaction() ) {
				return new WP_Error( 'learndash_curriculum_transaction_active', esc_html_x( 'Inspect or save the curriculum outside an existing database transaction.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 409 ) );
			}
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'learndash_curriculum_storage_unavailable', esc_html_x( 'The curriculum transaction boundary could not be verified.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 503 ) );
		}
		return true;
	}

	/**
	 * Read the native hierarchy, section headings and revision after authorization.
	 *
	 * @param int   $course_id  Target course ID.
	 * @param int[] $locked_ids Posts to refresh from locking reads during a save. Empty for inspection.
	 *
	 * @return array|WP_Error Ordered curriculum snapshot, or an inspection error.
	 */
	private function snapshot( int $course_id, array $locked_ids = array() ) {
		$allowed = $this->permissions_check( $course_id );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		clean_post_cache( $course_id );
		if ( $locked_ids ) {
			$this->references->lock_post_ids( array_merge( array( $course_id ), $locked_ids ) );
		}
		$existing = $this->authorized_existing_ids( $course_id, ! empty( $locked_ids ) );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}
		$model   = LDLMS_Factory_Post::course_steps( $course_id, true );
		$tree    = $model->get_steps( 'h' );
		$outline = $this->outline( $tree );
		$ids     = $this->step_ids( $outline );
		if ( count( $ids ) > self::MAX_STEPS ) {
			return new WP_Error( 'learndash_curriculum_too_large', esc_html_x( 'This curriculum exceeds the 500-step editing limit.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 413 ) );
		}
		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				foreach ( $ids as $cached_id ) {
					clean_post_cache( $cached_id );
				}
				return new WP_Error( 'rest_forbidden', esc_html_x( 'You cannot edit every step in this curriculum.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 403 ) );
			}
		}
		$raw_sections = get_post_meta( $course_id, 'course_sections', true );
		$sections     = is_string( $raw_sections ) ? json_decode( $raw_sections, true ) : $raw_sections;
		$shared       = learndash_is_course_shared_steps_enabled();
		$builder      = learndash_is_course_builder_enabled();
		return array(
			'course_id'       => $course_id,
			'revision'        => hash( 'sha256', wp_json_encode( array( $course_id, $tree, $raw_sections, $shared, $builder ) ) ),
			'lessons'         => $outline['lessons'],
			'quizzes'         => $outline['quizzes'],
			'sections'        => $this->sections_for_response( is_array( $sections ) ? $sections : array() ),
			'shared_steps'    => $shared,
			'builder_enabled' => $builder,
			'total_steps'     => count( $ids ),
			'notice'          => 'Native inspection may reconcile existing course relationships and derived metadata. It does not change learner progress. total_steps counts all lesson, topic and quiz nodes, not LearnDash progress units.',
		);
	}

	/**
	 * Replace and verify the complete curriculum within the shared edit transaction.
	 *
	 * @param int   $course_id Target course ID.
	 * @param array $input     Complete ordered curriculum and expected revision; sections are optional.
	 *
	 * @return array|WP_Error Verified saved snapshot, or a validation/conflict/save error.
	 */
	public function apply( int $course_id, array $input ) {
		$available = $this->transaction_boundary();
		if ( is_wp_error( $available ) ) {
			return $available;
		}
		$valid = rest_validate_value_from_schema( $input, self::schema(), 'curriculum' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( array( 'status' => 400 ) );
			return $valid;
		}
		if ( strlen( wp_json_encode( $input ) ) > 262144 || count( $this->step_ids( $input ) ) > self::MAX_STEPS ) {
			return new WP_Error( 'learndash_curriculum_too_large', esc_html_x( 'The curriculum exceeds the 500-step or 256 KiB editing limit.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 413 ) );
		}
		$allowed = $this->permissions_check( $course_id );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$affected = $this->step_ids( $input );
		try {
			return $this->with_curriculum_lock(
				$course_id,
				$affected,
				function () use ( $course_id, $input, &$affected ) {
					$this->references->lock_post_ids( array_merge( array( $course_id ), $affected ) );
					$existing = $this->authorized_existing_ids( $course_id, true );
					if ( is_wp_error( $existing ) ) {
						return $existing;
					}
					$affected = array_unique( array_merge( array( $course_id ), $existing, $affected ) );
					$before   = $this->snapshot( $course_id, $affected );
					if ( is_wp_error( $before ) ) {
						return $before;
					}
					$affected = array_unique( array_merge( $this->step_ids( $before ), $affected ) );
					if ( ! hash_equals( $before['revision'], $input['expected_revision'] ) ) {
						return new WP_Error( 'learndash_curriculum_conflict', esc_html_x( 'The curriculum changed. Read it again before saving.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 409 ) );
					}
					if ( ! $before['builder_enabled'] ) {
						return new WP_Error( 'learndash_curriculum_builder_disabled', esc_html_x( 'Enable the LearnDash course builder before replacing a curriculum.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 409 ) );
					}
					$this->references->lock_post_ids( $affected );
					$tree = $this->validated_tree( $course_id, $input, $before['shared_steps'] );
					if ( is_wp_error( $tree ) ) {
						return $tree;
					}
					$model = LDLMS_Factory_Post::course_steps( $course_id, true );
					// Omission preserves native heading records; an explicit [] removes them.
					$model->set_steps( $tree, ! array_key_exists( 'sections', $input ) );
					// Shared membership is saved by native SQL; invalidate warmed metadata.
					foreach ( $affected as $id ) {
						clean_post_cache( $id );
					}
					$after = $this->snapshot( $course_id, $affected );
					if ( is_wp_error( $after ) ) {
						return $after;
					}
					$sections = $input['sections'] ?? $before['sections'];
					if ( $input['lessons'] !== $after['lessons'] || $input['quizzes'] !== $after['quizzes'] || $sections !== $after['sections'] || ! $this->relationships_match( $course_id, $input, $before, $affected ) ) {
						return new WP_Error( 'learndash_curriculum_unverified', esc_html_x( 'LearnDash did not retain the requested curriculum. Read it before retrying.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 409 ) );
					}
					return $after + array( 'verification' => 'exact' );
				}
			);
		} catch ( Throwable $exception ) {
			return new WP_Error( 'learndash_curriculum_save_failed', esc_html_x( 'The curriculum save could not be completed. Read it before retrying.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 503 ) );
		}
	}

	/**
	 * Keep native reconciliation and its uncommitted cache values inside one operation.
	 *
	 * @param int      $course_id Target course ID.
	 * @param int[]    $affected  Posts to invalidate after commit or rollback.
	 * @param callable $operation Native inspection or save to run under the course lock.
	 *
	 * @return array|WP_Error Operation result, or a storage/validation error.
	 */
	private function with_curriculum_lock( int $course_id, array &$affected, callable $operation ) {
		$cache = $GLOBALS['wp_object_cache'];
		try {
			// Priming locked rows into Redis would expose changes before COMMIT.
			// A scoped copy keeps native post and query caches local to this request.
			$GLOBALS['wp_object_cache'] = clone $cache; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolate native transaction cache writes; restored in finally.
			wp_cache_add_non_persistent_groups( array( 'posts', 'post_meta', 'post-queries' ) );
			return $this->lock->with_post_lock( $course_id, $operation );
		} finally {
			// Restore the original cache before invalidating persistent entries. Never
			// copy transaction-local values back, including after a failed verification.
			$GLOBALS['wp_object_cache'] = $cache; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the original cache before persistent invalidation.
			foreach ( array_unique( array_merge( array( $course_id ), $affected ) ) as $id ) {
				clean_post_cache( $id );
			}
			LDLMS_Factory_Post::course_steps( $course_id, true );
		}
	}

	/**
	 * Authorize stored references before asking native reads to reconcile them.
	 *
	 * This WordPress lookup is only an authorization/cache boundary; the native
	 * model remains the source of the returned curriculum and every saved value.
	 *
	 * @param int  $course_id Target course ID.
	 * @param bool $lock      Whether to lock reference rows inside the save transaction.
	 *
	 * @return int[]|WP_Error Authorized affected post IDs, or a storage/permission error.
	 */
	private function authorized_existing_ids( int $course_id, bool $lock = false ) {
		$stored = get_post_meta( $course_id, 'ld_course_steps', true );
		$tree   = $stored['steps']['h'] ?? $stored['h'] ?? array();
		if ( ! is_array( $tree ) || ! $this->stored_hierarchy_is_valid( $tree ) ) {
			return new WP_Error( 'learndash_curriculum_storage_invalid', esc_html_x( 'LearnDash has an invalid stored curriculum. Repair it before editing.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 409 ) );
		}
		$ids = $this->step_ids( $this->outline( $tree ) );
		try {
			$linked = $this->references->post_ids( $course_id, learndash_get_post_types( 'course_steps' ), self::MAX_STEPS + 1, $lock );
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'learndash_curriculum_storage_unavailable', esc_html_x( 'Course relationships could not be inspected safely.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 503 ) );
		}
		$ids = array_values( array_unique( array_merge( $ids, array_map( 'intval', $linked ) ) ) );
		if ( count( $ids ) > self::MAX_STEPS ) {
			return new WP_Error( 'learndash_curriculum_too_large', esc_html_x( 'This curriculum exceeds the 500-step editing limit.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 413 ) );
		}
		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				return new WP_Error( 'rest_forbidden', esc_html_x( 'You cannot edit every existing course relationship.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 403 ) );
			}
		}
		return $ids;
	}

	/**
	 * Check stored containers before native inspection can reconcile relationships.
	 *
	 * @param array $tree Stored native hierarchy.
	 *
	 * @return bool Whether every hierarchy container can be inspected safely.
	 */
	private function stored_hierarchy_is_valid( array $tree ): bool {
		foreach ( array( 'sfwd-lessons', 'sfwd-quiz' ) as $key ) {
			if ( array_key_exists( $key, $tree ) && ! is_array( $tree[ $key ] ) ) {
				return false;
			}
		}
		foreach ( $tree['sfwd-lessons'] ?? array() as $lesson ) {
			if ( ! is_array( $lesson ) ) {
				return false;
			}
			foreach ( array( 'sfwd-topic', 'sfwd-quiz' ) as $key ) {
				if ( array_key_exists( $key, $lesson ) && ! is_array( $lesson[ $key ] ) ) {
					return false;
				}
			}
			foreach ( $lesson['sfwd-topic'] ?? array() as $topic ) {
				if ( ! is_array( $topic ) || ( array_key_exists( 'sfwd-quiz', $topic ) && ! is_array( $topic['sfwd-quiz'] ) ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Verify saved associations, parent placement and the native progress-unit count.
	 *
	 * @param int   $course_id Target course ID.
	 * @param array $input     Requested ordered curriculum.
	 * @param array $before    Pre-save snapshot containing the shared-step mode.
	 * @param int[] $affected  All posts the save may have changed, including removed steps.
	 *
	 * @return bool Whether every affected relationship and derived count matches.
	 */
	private function relationships_match( int $course_id, array $input, array $before, array $affected ): bool {
		$ids = $this->step_ids( $input );
		foreach ( array_diff( $affected, array( $course_id ) ) as $id ) {
			$included   = in_array( $id, $ids, true );
			$associated = $before['shared_steps'] ? metadata_exists( 'post', $id, 'ld_course_' . $course_id ) : (int) learndash_get_course_id( $id, true ) === $course_id;
			if ( $included !== $associated ) {
				return false;
			}
			if ( $included && $before['shared_steps'] && (int) get_post_meta( $id, 'ld_course_' . $course_id, true ) !== $course_id ) {
				return false;
			}
		}
		// LearnDash counts lessons and topics plus one unit for course-level quizzes;
		// nested quizzes do not add units. This differs from our total node count.
		$progress_units = count( $input['lessons'] ) + ( empty( $input['quizzes'] ) ? 0 : 1 );
		foreach ( $input['lessons'] as $lesson ) {
			$progress_units += count( $lesson['topics'] );
			if ( ! $before['shared_steps'] ) {
				foreach ( $lesson['topics'] as $topic ) {
					if ( (int) learndash_get_lesson_id( $topic['id'], $course_id ) !== $lesson['id'] || ! $this->quiz_parents_match( $topic['quizzes'], $topic['id'], $course_id ) ) {
						return false;
					}
				}
				if ( ! $this->quiz_parents_match( $lesson['quizzes'], $lesson['id'], $course_id ) ) {
					return false;
				}
			}
		}
		return (int) get_post_meta( $course_id, '_ld_course_steps_count', true ) === $progress_units && ( $before['shared_steps'] || $this->quiz_parents_match( $input['quizzes'], 0, $course_id ) );
	}

	/**
	 * Check the immediate parent of each quiz when shared steps are disabled.
	 *
	 * @param int[] $quizzes   Quiz post IDs.
	 * @param int   $parent    Expected lesson or topic ID, or zero for course-level quizzes.
	 * @param int   $course_id Target course ID.
	 *
	 * @return bool Whether every quiz has the expected native parent.
	 */
	private function quiz_parents_match( array $quizzes, int $parent, int $course_id ): bool {
		foreach ( $quizzes as $id ) {
			if ( (int) learndash_get_lesson_id( $id, $course_id ) !== $parent ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Convert the request into a native tree after validating every placement.
	 *
	 * @param int   $course_id Target course ID.
	 * @param array $input     Schema-validated ordered curriculum.
	 * @param bool  $shared    Whether LearnDash allows steps to belong to multiple courses.
	 *
	 * @return array|WP_Error Native hierarchy, or an invalid placement/ownership error.
	 */
	private function validated_tree( int $course_id, array $input, bool $shared ) {
		$tree = array(
			'sfwd-lessons' => array(),
			'sfwd-quiz'    => array(),
		);
		$seen = array();
		foreach ( $input['lessons'] as $lesson ) {
			$seen[] = array( $lesson['id'], 'lesson' );
			$node   = array(
				'sfwd-topic' => array(),
				'sfwd-quiz'  => array_fill_keys( $lesson['quizzes'], array() ),
			);
			foreach ( $lesson['topics'] as $topic ) {
				$seen[]                             = array( $topic['id'], 'topic' );
				$node['sfwd-topic'][ $topic['id'] ] = array( 'sfwd-quiz' => array_fill_keys( $topic['quizzes'], array() ) );
			}
			$tree['sfwd-lessons'][ $lesson['id'] ] = $node;
		}
		foreach ( $this->step_ids( $input, true ) as $id ) {
			$seen[] = array( $id, 'quiz' );
		}
		$tree['sfwd-quiz'] = array_fill_keys( $input['quizzes'], array() );
		$ids               = array_column( $seen, 0 );
		if ( count( $ids ) !== count( array_unique( $ids ) ) ) {
			return new WP_Error( 'learndash_curriculum_duplicate_step', esc_html_x( 'Each lesson, topic and quiz may appear only once in this curriculum.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 400 ) );
		}
		foreach ( $seen as list( $id, $type ) ) {
			$post = get_post( $id );
			if ( ! $post || learndash_get_post_type_slug( $type ) !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'future', 'draft', 'pending', 'private' ), true ) ) {
				return new WP_Error(
					'learndash_curriculum_step_invalid',
					esc_html_x( 'Every step must be an existing LearnDash post of the expected type, not trashed or an auto-draft.', 'LearnDash', 'uncanny-automator' ),
					array(
						'status'  => 400,
						'post_id' => $id,
					)
				);
			}
			if ( ! current_user_can( 'edit_post', $id ) ) {
				return new WP_Error( 'rest_forbidden', esc_html_x( 'You cannot edit every requested curriculum step.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 403 ) );
			}
			$owner = (int) learndash_get_course_id( $id, true );
			if ( ! $shared && 0 !== $owner && $course_id !== $owner ) {
				return new WP_Error(
					'learndash_curriculum_step_owned',
					esc_html_x( 'A requested step belongs to another course and shared course steps are disabled.', 'LearnDash', 'uncanny-automator' ),
					array(
						'status'  => 409,
						'post_id' => $id,
					)
				);
			}
		}
		if ( array_key_exists( 'sections', $input ) ) {
			$sections = $this->native_sections( $input['sections'], count( $input['lessons'] ) );
			if ( is_wp_error( $sections ) ) {
				return $sections;
			}
			$tree['section-heading'] = $sections;
		}
		return $tree;
	}

	/**
	 * Validate section positions and convert headings to LearnDash's storage shape.
	 *
	 * @param array $sections Ordered headings with virtual IDs, plain text titles and positions.
	 * @param int   $lessons  Number of lessons in the replacement curriculum.
	 *
	 * @return array|WP_Error Native heading records, or an invalid section error.
	 */
	private function native_sections( array $sections, int $lessons ) {
		$native   = array();
		$ids      = array();
		$previous = -1;
		// Positions include earlier headings as well as lessons; they are not lesson indexes.
		foreach ( $sections as $index => $section ) {
			if ( isset( $ids[ $section['id'] ] ) || $section['order'] <= $previous || $section['order'] > $lessons + $index || trim( $section['title'] ) !== $section['title'] || wp_strip_all_tags( $section['title'] ) !== $section['title'] ) {
				return new WP_Error( 'learndash_curriculum_section_invalid', esc_html_x( 'Sections need unique IDs, plain text titles and increasing positions within the combined headings-and-lessons outline.', 'LearnDash', 'uncanny-automator' ), array( 'status' => 400 ) );
			}
			$ids[ $section['id'] ] = true;
			$previous              = $section['order'];
			$native[]              = array(
				'ID'         => $section['id'],
				'post_title' => $section['title'],
				'order'      => $section['order'],
				'type'       => 'section-heading',
			);
		}
		return $native;
	}

	/**
	 * Convert the native ID-keyed hierarchy into ordered transport arrays.
	 *
	 * @param array $tree Native lesson, topic and quiz hierarchy.
	 *
	 * @return array Ordered lessons with nested topics/quizzes and course-level quizzes.
	 */
	private function outline( array $tree ): array {
		$lessons = array();
		foreach ( $tree['sfwd-lessons'] ?? array() as $id => $lesson ) {
			$topics = array();
			foreach ( $lesson['sfwd-topic'] ?? array() as $topic_id => $topic ) {
				$topics[] = array(
					'id'      => (int) $topic_id,
					'quizzes' => array_map( 'intval', array_keys( $topic['sfwd-quiz'] ?? array() ) ),
				);
			}
			$lessons[] = array(
				'id'      => (int) $id,
				'topics'  => $topics,
				'quizzes' => array_map( 'intval', array_keys( $lesson['sfwd-quiz'] ?? array() ) ),
			);
		}
		return array(
			'lessons' => $lessons,
			'quizzes' => array_map( 'intval', array_keys( $tree['sfwd-quiz'] ?? array() ) ),
		);
	}

	/**
	 * Collect step IDs without removing duplicate placements.
	 *
	 * @param array $outline      Ordered lessons, topics and quizzes.
	 * @param bool  $only_quizzes Whether to omit lesson and topic IDs.
	 *
	 * @return int[] Step IDs, retaining duplicates so validation can reject them.
	 */
	private function step_ids( array $outline, bool $only_quizzes = false ): array {
		$ids = $outline['quizzes'];
		foreach ( $outline['lessons'] as $lesson ) {
			if ( ! $only_quizzes ) {
				$ids[] = $lesson['id'];
			}
			$ids = array_merge( $ids, $lesson['quizzes'] );
			foreach ( $lesson['topics'] as $topic ) {
				if ( ! $only_quizzes ) {
					$ids[] = $topic['id'];
				}
				$ids = array_merge( $ids, $topic['quizzes'] );
			}
		}
		return $ids;
	}

	/**
	 * Convert native heading records to the response's authored section fields.
	 *
	 * @param array $sections Stored native section-heading records.
	 *
	 * @return array Headings containing their virtual ID, title and outline position.
	 */
	private function sections_for_response( array $sections ): array {
		$result = array();
		foreach ( $sections as $section ) {
			$result[] = array(
				'id'    => (int) ( $section['ID'] ?? 0 ),
				'title' => (string) ( $section['post_title'] ?? '' ),
				'order' => (int) ( $section['order'] ?? 0 ),
			);
		}
		return $result;
	}
}
