<?php

namespace Uncanny_Automator\Integrations\Learndash;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Ld_Helpers
 *
 * Modern helpers for the LearnDash integration.
 *
 * Migrated triggers/actions access this class via $this->get_item_helpers().
 * Old Pro code accesses it via the deprecated singleton shim:
 *   Automator()->helpers->recipe->learndash->options->method()
 *
 * Dropdown/token methods delegate to the legacy Learndash_Helpers class
 * (instantiated with AJAX hooks disabled) to avoid duplicating 1000+ lines
 * of tested, working code. This delegation will be removed in a future
 * major version when the legacy helper is deleted.
 *
 * @package Uncanny_Automator
 */
class Ld_Helpers extends Abstract_Helpers {

	/**
	 * @deprecated 7.2 — Backward-compat shim for old Pro calling ->options->method().
	 *             Migrated code must use $this->get_item_helpers() instead.
	 * @var self
	 */
	public $options;

	/**
	 * @deprecated 7.2 — Backward-compat shim for old Pro calling ->pro->method().
	 * @var mixed
	 */
	public $pro;

	/**
	 * Legacy helper instance for method delegation.
	 *
	 * @var \Uncanny_Automator\Learndash_Helpers
	 */
	private $legacy;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->options = $this;
		// Instantiate old helper with AJAX hooks disabled — we register them
		// in the integration's register_hooks() method instead.
		$this->legacy = new \Uncanny_Automator\Learndash_Helpers( false );

		if ( class_exists( '\Uncanny_Automator_Pro\Learndash_Pro_Helpers' ) ) {
			$this->pro = new \Uncanny_Automator_Pro\Learndash_Pro_Helpers( false );
		}
	}

	// =========================================================================
	// Deprecated shims — old Pro compat only.
	// =========================================================================

	/**
	 * @deprecated 7.2
	 *
	 * @param \Uncanny_Automator\Learndash_Helpers $options Ignored.
	 *
	 * @return void
	 */
	public function setOptions( $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		// No-op. Old Pro may call this during bootstrap.
	}

	/**
	 * @deprecated 7.2
	 *
	 * @param mixed $pro Pro helper reference.
	 *
	 * @return void
	 */
	public function setPro( $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( class_exists( '\Uncanny_Automator_Pro\Learndash_Pro_Helpers' ) ) {
			$this->pro = new \Uncanny_Automator_Pro\Learndash_Pro_Helpers( false );
			return;
		}

		$this->pro = $pro;
	}

	// =========================================================================
	// Dropdown option methods — delegated to legacy helper.
	// Return format: array( array( 'text' => '...', 'value' => '...' ), ... )
	// =========================================================================

	/**
	 * All LearnDash courses dropdown.
	 *
	 * @return mixed
	 */
	public function all_ld_courses() {
		return self::modernize_field( call_user_func_array( array( $this->legacy, 'all_ld_courses' ), func_get_args() ) );
	}

	/**
	 * Get all LD courses (simpler variant).
	 *
	 * @return mixed
	 */
	public function get_all_ld_courses() {
		return self::modernize_field( call_user_func_array( array( $this->legacy, 'get_all_ld_courses' ), func_get_args() ) );
	}

	/**
	 * All LearnDash lessons dropdown.
	 *
	 * @return mixed
	 */
	public function all_ld_lessons() {
		return self::modernize_field( call_user_func_array( array( $this->legacy, 'all_ld_lessons' ), func_get_args() ) );
	}

	/**
	 * All LearnDash topics dropdown.
	 *
	 * @return mixed
	 */
	public function all_ld_topics() {
		return self::modernize_field( call_user_func_array( array( $this->legacy, 'all_ld_topics' ), func_get_args() ) );
	}

	/**
	 * All LearnDash groups dropdown.
	 *
	 * @return mixed
	 */
	public function all_ld_groups() {
		return self::modernize_field( call_user_func_array( array( $this->legacy, 'all_ld_groups' ), func_get_args() ) );
	}

	/**
	 * All LearnDash quizzes dropdown.
	 *
	 * @return mixed
	 */
	public function all_ld_quiz() {
		return self::modernize_field( call_user_func_array( array( $this->legacy, 'all_ld_quiz' ), func_get_args() ) );
	}

	// =========================================================================
	// Token definition methods — delegated to legacy helper.
	// =========================================================================

	/**
	 * @return array
	 */
	public function get_course_relevant_tokens() {
		return call_user_func_array( array( $this->legacy, 'get_course_relevant_tokens' ), func_get_args() );
	}

	/**
	 * @return array
	 */
	public function get_lesson_relevant_tokens() {
		return call_user_func_array( array( $this->legacy, 'get_lesson_relevant_tokens' ), func_get_args() );
	}

	/**
	 * @return array
	 */
	public function get_topic_relevant_tokens() {
		return call_user_func_array( array( $this->legacy, 'get_topic_relevant_tokens' ), func_get_args() );
	}

	/**
	 * @return array
	 */
	public function get_group_relevant_tokens() {
		return call_user_func_array( array( $this->legacy, 'get_group_relevant_tokens' ), func_get_args() );
	}

	/**
	 * @return array
	 */
	public function get_quiz_relevant_tokens() {
		return call_user_func_array( array( $this->legacy, 'get_quiz_relevant_tokens' ), func_get_args() );
	}

	// =========================================================================
	// Token hydration methods — delegated to legacy helper.
	// =========================================================================

	/**
	 * @return array
	 */
	public function hydrate_ld_course_action_tokens() {
		return call_user_func_array( array( $this->legacy, 'hydrate_ld_course_action_tokens' ), func_get_args() );
	}

	/**
	 * @return array
	 */
	public function hydrate_ld_lesson_action_tokens() {
		return call_user_func_array( array( $this->legacy, 'hydrate_ld_lesson_action_tokens' ), func_get_args() );
	}

	/**
	 * @return array
	 */
	public function hydrate_ld_topic_action_tokens() {
		return call_user_func_array( array( $this->legacy, 'hydrate_ld_topic_action_tokens' ), func_get_args() );
	}

	/**
	 * @return array
	 */
	public function hydrate_ld_group_action_tokens() {
		return call_user_func_array( array( $this->legacy, 'hydrate_ld_group_action_tokens' ), func_get_args() );
	}

	/**
	 * @return array
	 */
	public function hydrate_ld_quiz_action_tokens() {
		return call_user_func_array( array( $this->legacy, 'hydrate_ld_quiz_action_tokens' ), func_get_args() );
	}

	// =========================================================================
	// Quiz helper methods — delegated to legacy helper.
	// =========================================================================

	/**
	 * Check if a submitted quiz was passed.
	 *
	 * @param array $data Quiz submission data.
	 *
	 * @return mixed WP_Error or bool.
	 */
	public function submitted_quiz_pased( $data ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return $this->legacy->submitted_quiz_pased( $data );
	}

	/**
	 * Check if a graded essay quiz was passed.
	 *
	 * @param int $essay       Essay post ID.
	 * @param int $pro_quiz_id Pro Quiz ID.
	 *
	 * @return mixed WP_Error or bool.
	 */
	public function graded_quiz_passed( $essay, $pro_quiz_id ) {
		return $this->legacy->graded_quiz_passed( $essay, $pro_quiz_id );
	}

	/**
	 * Check if course timer plugin is activated.
	 *
	 * @return bool
	 */
	public static function is_course_timer_activated() {
		return \Uncanny_Automator\Learndash_Helpers::is_course_timer_activated();
	}

	/**
	 * Process mark complete for course/lesson/topic.
	 *
	 * @return bool
	 */
	public static function process_mark_complete() {
		return call_user_func_array(
			array( '\Uncanny_Automator\Learndash_Helpers', 'process_mark_complete' ),
			func_get_args()
		);
	}

	// =========================================================================
	// Option format transformation.
	// =========================================================================

	/**
	 * Transform a legacy field definition's options to modern text/value format.
	 *
	 * Legacy format: 'options' => array( 42 => 'Course Title', 55 => 'Other Course' )
	 * Modern format: 'options' => array( array( 'value' => '42', 'text' => 'Course Title' ), ... )
	 *
	 * If the options are already in modern format (first item has 'value' key), returns as-is.
	 *
	 * @param array $field_definition The full field definition array from legacy helper.
	 *
	 * @return array The field definition with transformed options.
	 */
	public static function modernize_field( $field_definition ) {

		if ( ! is_array( $field_definition ) || ! isset( $field_definition['options'] ) || ! is_array( $field_definition['options'] ) ) {
			return $field_definition;
		}

		$options = $field_definition['options'];

		// Already modern format — first item has 'value' key.
		if ( ! empty( $options ) && isset( reset( $options )['value'] ) ) {
			return $field_definition;
		}

		$field_definition['options'] = automator_array_as_options( $options );

		return $field_definition;
	}

	// =========================================================================
	// Static utility methods.
	// =========================================================================

	/**
	 * Comparison operator options for numeric condition fields.
	 *
	 * @return array[] Modern select options format.
	 */
	public static function comparison_operators() {
		return array(
			array( 'value' => '<', 'text' => esc_html_x( 'less than', 'LearnDash', 'uncanny-automator' ) ),
			array( 'value' => '>', 'text' => esc_html_x( 'greater than', 'LearnDash', 'uncanny-automator' ) ),
			array( 'value' => '=', 'text' => esc_html_x( 'equal to', 'LearnDash', 'uncanny-automator' ) ),
			array( 'value' => '!=', 'text' => esc_html_x( 'not equal to', 'LearnDash', 'uncanny-automator' ) ),
			array( 'value' => '>=', 'text' => esc_html_x( 'greater or equal to', 'LearnDash', 'uncanny-automator' ) ),
			array( 'value' => '<=', 'text' => esc_html_x( 'less or equal to', 'LearnDash', 'uncanny-automator' ) ),
		);
	}

	/**
	 * Full NUMBERCOND field definition for numeric comparison triggers.
	 *
	 * DRY helper — returns the complete field array ready to include in options().
	 *
	 * @return array Field definition.
	 */
	public static function comparison_field() {
		return array(
			'option_code' => 'NUMBERCOND',
			'label'       => esc_html_x( 'Condition', 'LearnDash', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => self::comparison_operators(),
		);
	}

	// =========================================================================
	// Remote-data handlers — entity dropdowns served via REST.
	// =========================================================================

	/**
	 * Remote-data handler: Load all courses (with "Any course").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_courses( $request ): array {
		return $this->remote_data_success(
			$this->build_post_type_options( 'sfwd-courses', true, esc_html_x( 'Any course', 'LearnDash', 'uncanny-automator' ) )
		);
	}

	/**
	 * Remote-data handler: Load all courses (no "Any" option).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_courses_strict( $request ): array {
		return $this->remote_data_success(
			$this->build_post_type_options( 'sfwd-courses', false )
		);
	}

	/**
	 * Remote-data handler: Load all quizzes (with "Any quiz").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_quizzes( $request ): array {
		return $this->remote_data_success(
			$this->build_post_type_options( 'sfwd-quiz', true, esc_html_x( 'Any quiz', 'LearnDash', 'uncanny-automator' ) )
		);
	}

	/**
	 * Remote-data handler: Load all quizzes (no "Any" option).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_quizzes_strict( $request ): array {
		return $this->remote_data_success(
			$this->build_post_type_options( 'sfwd-quiz', false )
		);
	}

	/**
	 * Remote-data handler: Load all lessons (no "Any" option).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_lessons_strict( $request ): array {
		return $this->remote_data_success(
			$this->build_post_type_options( 'sfwd-lessons', false )
		);
	}

	/**
	 * Remote-data handler: Load all topics (no "Any" option).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_topics_strict( $request ): array {
		return $this->remote_data_success(
			$this->build_post_type_options( 'sfwd-topic', false )
		);
	}

	/**
	 * Remote-data handler: Load all groups (with "Any group").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_groups( $request ): array {
		return $this->remote_data_success(
			$this->build_post_type_options( 'groups', true, esc_html_x( 'Any group', 'LearnDash', 'uncanny-automator' ) )
		);
	}

	/**
	 * Remote-data handler: Load all groups (no "Any" option).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_groups_strict( $request ): array {
		return $this->remote_data_success(
			$this->build_post_type_options( 'groups', false )
		);
	}

	/**
	 * Remote-data handler: Lessons in a course (with "Any lesson").
	 *
	 * Reads the parent LDCOURSE value to filter lessons.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_lessons_from_course( $request ): array {
		return $this->remote_data_success(
			$this->build_lessons_from_course( $request, true )
		);
	}

	/**
	 * Remote-data handler: Lessons in a course (no "Any" option).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_lessons_from_course_strict( $request ): array {
		return $this->remote_data_success(
			$this->build_lessons_from_course( $request, false )
		);
	}

	/**
	 * Remote-data handler: Topics in a lesson (with "Any topic").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_topics_from_lesson( $request ): array {
		return $this->remote_data_success(
			$this->build_topics_from_lesson( $request, true )
		);
	}

	/**
	 * Remote-data handler: Topics in a lesson (no "Any" option).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_topics_from_lesson_strict( $request ): array {
		return $this->remote_data_success(
			$this->build_topics_from_lesson( $request, false )
		);
	}

	/**
	 * Build options for a LearnDash post type.
	 *
	 * @param string $post_type   The post type slug.
	 * @param bool   $include_any Whether to prepend an "Any" option.
	 * @param string $any_label   Label for the Any option.
	 *
	 * @return array
	 */
	private function build_post_type_options( $post_type, $include_any = true, $any_label = '' ) {

		return automator_wp_query(
			array(
				'post_type'   => $post_type,
				'include_any' => $include_any,
				'any_label'   => $any_label,
			)
		);
	}

	/**
	 * Build the "lessons in a course" option list.
	 *
	 * @param Remote_Data_Request $request     The remote-data request.
	 * @param bool                $include_any Whether to prepend "Any lesson".
	 *
	 * @return array
	 */
	private function build_lessons_from_course( $request, $include_any ) {

		$values        = $request->get_values();
		$selected      = $request->get_field_value( 'LDCOURSE' );
		$ld_course_id  = ( 'automator_custom_value' === $selected )
			? absint( $values['LDCOURSE_custom'] ?? 0 )
			: absint( $selected );

		$options = array();

		if ( $include_any || -1 === $ld_course_id ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_attr_x( 'Any lesson', 'LearnDash', 'uncanny-automator' ),
			);
		}

		if ( -1 === $ld_course_id || 0 === $ld_course_id ) {
			return $options;
		}

		foreach ( learndash_get_lesson_list( $ld_course_id, array( 'num' => 0 ) ) as $lesson ) {
			$options[] = array(
				'value' => $lesson->ID,
				'text'  => $lesson->post_title,
			);
		}

		return $options;
	}

	/**
	 * Build the "topics in a lesson" option list.
	 *
	 * @param Remote_Data_Request $request     The remote-data request.
	 * @param bool                $include_any Whether to prepend "Any topic".
	 *
	 * @return array
	 */
	private function build_topics_from_lesson( $request, $include_any ) {

		$values    = $request->get_values();
		$selected  = $request->get_field_value( 'LDLESSON' );
		$lesson_id = ( 'automator_custom_value' === $selected )
			? absint( $values['LDLESSON_custom'] ?? 0 )
			: absint( $selected );
		$course_id = absint( $values['LDCOURSE'] ?? 0 );

		// "Any course" or "Any lesson" picked — no concrete topics to list.
		if ( -1 === $course_id || -1 === $lesson_id ) {
			$options = array();
			if ( $include_any ) {
				$options[] = array(
					'value' => '-1',
					'text'  => esc_attr_x( 'Any topic', 'LearnDash', 'uncanny-automator' ),
				);
			}
			return $options;
		}

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_attr_x( 'Any topic', 'LearnDash', 'uncanny-automator' ),
			);
		}

		if ( 0 === $lesson_id ) {
			return $options;
		}

		foreach ( learndash_get_topic_list( $lesson_id, $course_id ) as $topic ) {
			$options[] = array(
				'value' => $topic->ID,
				'text'  => $topic->post_title,
			);
		}

		return $options;
	}

	// =========================================================================
	// Remote-data handlers — Pro-served segments.
	// Pro field configs route here via the cross-integration `id='ld'` override:
	//   $this->item_helpers->remote_data_load_config( 'groups_hierarchy', 'ld' )
	// =========================================================================

	/**
	 * Remote-data handler: Groups with hierarchy (with "Any group").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_groups_hierarchy( $request ): array {
		return $this->remote_data_success(
			$this->build_groups_hierarchy_options( true )
		);
	}

	/**
	 * Remote-data handler: Groups with hierarchy (no "Any" option).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_groups_hierarchy_strict( $request ): array {
		return $this->remote_data_success(
			$this->build_groups_hierarchy_options( false )
		);
	}

	/**
	 * Remote-data handler: Certificates dropdown.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_certificates( $request ): array {
		return $this->remote_data_success(
			$this->build_certificate_options()
		);
	}

	/**
	 * Remote-data handler: Lessons and topics in a course (with "Any lesson or topic").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_lessontopics_from_course( $request ): array {
		return $this->remote_data_success(
			$this->build_lessontopics_from_course( $request, true )
		);
	}

	/**
	 * Remote-data handler: Lessons and topics in a course (no "Any" option).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_lessontopics_from_course_strict( $request ): array {
		return $this->remote_data_success(
			$this->build_lessontopics_from_course( $request, false )
		);
	}

	/**
	 * Remote-data handler: Assignments in a lesson/topic (with "Any assignment").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_assignments_from_lessontopic( $request ): array {
		return $this->remote_data_success(
			$this->build_assignments_from_lessontopic( $request )
		);
	}

	/**
	 * Remote-data handler: Essay questions in a quiz (with "Any essay").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_quiz_essays( $request ): array {
		return $this->remote_data_success(
			$this->build_quiz_essays( $request )
		);
	}

	/**
	 * Remote-data handler: All questions in a quiz (with "Any question").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_quiz_questions( $request ): array {
		return $this->remote_data_success(
			$this->build_quiz_questions( $request )
		);
	}

	/**
	 * Remote-data handler: Quizzes in a course / lesson / topic (with "Any quiz").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_quizzes_from_course_lessontopic( $request ): array {
		return $this->remote_data_success(
			$this->build_quizzes_from_course_lessontopic( $request, true )
		);
	}

	/**
	 * Remote-data handler: Quizzes in a course / lesson / topic (no "Any" option).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_quizzes_from_course_lessontopic_strict( $request ): array {
		return $this->remote_data_success(
			$this->build_quizzes_from_course_lessontopic( $request, false )
		);
	}

	/**
	 * Remote-data handler: Essay questions in a course / lesson / topic / quiz.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_essay_questions_from_course_lessontopic_quiz( $request ): array {
		return $this->remote_data_success(
			$this->build_essay_questions_from_course_lessontopic_quiz( $request )
		);
	}

	// =========================================================================
	// Builders for Pro-served remote-data segments.
	// =========================================================================

	/**
	 * Build groups-with-hierarchy option list.
	 *
	 * @param bool $include_any Whether to prepend an "Any group" option.
	 *
	 * @return array
	 */
	private function build_groups_hierarchy_options( $include_any ) {

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_attr_x( 'Any group', 'LearnDash', 'uncanny-automator' ),
			);
		}

		// LearnDash hierarchical groups setting required — fall back to flat list otherwise.
		if ( ! function_exists( 'learndash_get_group_children' ) || ! self::is_group_hierarchy_enabled() ) {
			foreach ( $this->build_post_type_options( 'groups', false ) as $option ) {
				$options[] = $option;
			}
			return $options;
		}

		$top_level = get_posts(
			array(
				'post_type'      => 'groups',
				'posts_per_page' => 9999,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
				'post_parent'    => 0,
			)
		);

		foreach ( $top_level as $group ) {
			$options[] = array(
				'value' => (string) $group->ID,
				'text'  => $group->post_title,
			);
			$this->collect_group_children( $group->ID, 1, $options );
		}

		return $options;
	}

	/**
	 * Recursively collect group children, prefixing each level with em-dashes.
	 *
	 * @param int   $parent_id Parent group post ID.
	 * @param int   $depth     Current depth.
	 * @param array $options   Output options array (by reference).
	 *
	 * @return void
	 */
	private function collect_group_children( $parent_id, $depth, &$options ) {

		$children = get_posts(
			array(
				'post_type'      => 'groups',
				'posts_per_page' => 9999,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
				'post_parent'    => $parent_id,
			)
		);

		$prefix = str_repeat( '&mdash;', $depth ) . ' ';

		foreach ( $children as $child ) {
			$options[] = array(
				'value' => (string) $child->ID,
				'text'  => $prefix . $child->post_title,
			);
			$this->collect_group_children( $child->ID, $depth + 1, $options );
		}
	}

	/**
	 * Whether LearnDash's hierarchical-groups setting is on.
	 *
	 * @return bool
	 */
	private static function is_group_hierarchy_enabled() {
		$settings = get_option( 'learndash_settings_groups_management_display' );
		if ( empty( $settings['group_hierarchical_enabled'] ) ) {
			return false;
		}
		return 'yes' === $settings['group_hierarchical_enabled'];
	}

	/**
	 * Build certificate options, excluding shortcode-driven certificates.
	 *
	 * @return array
	 */
	private function build_certificate_options() {

		$certificates = get_posts(
			array(
				'post_type'      => 'sfwd-certificates',
				'posts_per_page' => 9999,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		$pattern = get_shortcode_regex();
		$options = array();

		foreach ( $certificates as $cert ) {
			$content = $cert->post_content;
			if (
				preg_match_all( '/' . $pattern . '/s', $content, $matches )
				&& ! empty( $matches[2] )
				&& ( in_array( 'quizinfo', $matches[2], true ) || in_array( 'courseinfo', $matches[2], true ) )
			) {
				continue;
			}
			$options[] = array(
				'value' => (string) $cert->ID,
				'text'  => $cert->post_title,
			);
		}

		return $options;
	}

	/**
	 * Build flat lesson + topic option list for the parent course.
	 *
	 * Reads LDCOURSE (or LDCOURSE_custom) from the request. When "Any course"
	 * (-1) is selected, returns every lesson + every topic across all courses.
	 *
	 * @param Remote_Data_Request $request     The remote-data request.
	 * @param bool                $include_any Whether to prepend "Any lesson or topic".
	 *
	 * @return array
	 */
	private function build_lessontopics_from_course( $request, $include_any = true ) {

		$values    = $request->get_values();
		$selected  = $request->get_field_value( 'LDCOURSE' );
		$course_id = ( 'automator_custom_value' === $selected )
			? absint( $values['LDCOURSE_custom'] ?? 0 )
			: absint( $selected );

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_attr_x( 'Any lesson or topic', 'LearnDash', 'uncanny-automator' ),
			);
		}

		// "Any course" picked — return all lessons + topics across the site.
		if ( -1 === $course_id ) {
			foreach ( get_posts( array( 'post_type' => 'sfwd-lessons', 'posts_per_page' => 9999, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ) ) as $lesson ) {
				$options[] = array( 'value' => (string) $lesson->ID, 'text' => $lesson->post_title );
			}
			foreach ( get_posts( array( 'post_type' => 'sfwd-topic', 'posts_per_page' => 9999, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ) ) as $topic ) {
				$options[] = array( 'value' => (string) $topic->ID, 'text' => $topic->post_title );
			}
			return $options;
		}

		if ( 0 === $course_id ) {
			return $options;
		}

		foreach ( learndash_get_lesson_list( $course_id, array( 'num' => 0 ) ) as $lesson ) {
			$options[] = array(
				'value' => (string) $lesson->ID,
				'text'  => $lesson->post_title,
			);
			foreach ( learndash_get_topic_list( $lesson->ID, $course_id ) as $topic ) {
				$options[] = array(
					'value' => (string) $topic->ID,
					'text'  => $topic->post_title,
				);
			}
		}

		// Sort lessons + topics alphabetically (after the optional "Any" sentinel).
		$any = $include_any ? array_shift( $options ) : null;
		usort(
			$options,
			static function ( $a, $b ) {
				return strcasecmp( $a['text'], $b['text'] );
			}
		);
		if ( null !== $any ) {
			array_unshift( $options, $any );
		}

		return $options;
	}

	/**
	 * Build assignment options scoped to LDCOURSE + LDSTEP (lesson/topic).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	private function build_assignments_from_lessontopic( $request ) {

		$values   = $request->get_values();
		$selected = $request->get_field_value( 'LDSTEP' );

		$lesson_id = ( 'automator_custom_value' === $selected )
			? absint( $values['LDSTEP_custom'] ?? 0 )
			: absint( $selected );

		$course_raw = $values['LDCOURSE'] ?? '';
		$course_id  = ( 'automator_custom_value' === $course_raw )
			? absint( $values['LDCOURSE_custom'] ?? 0 )
			: absint( $course_raw );

		$options = array(
			array(
				'value' => '-1',
				'text'  => esc_attr_x( 'Any assignment', 'LearnDash', 'uncanny-automator' ),
			),
		);

		$args = array(
			'post_type'   => 'sfwd-assignment',
			'numberposts' => -1,
		);

		$meta_query = array();
		if ( $course_id > 0 ) {
			$meta_query[] = array(
				'key'     => 'course_id',
				'value'   => $course_id,
				'compare' => '=',
			);
		}
		if ( $lesson_id > 0 ) {
			$meta_query[] = array(
				'key'     => 'lesson_id',
				'value'   => $lesson_id,
				'compare' => '=',
			);
		}
		if ( ! empty( $meta_query ) ) {
			if ( count( $meta_query ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		foreach ( get_posts( $args ) as $assignment ) {
			$options[] = array(
				'value' => (string) $assignment->ID,
				'text'  => $assignment->post_title,
			);
		}

		return $options;
	}

	/**
	 * Build essay-only question options for the selected quiz.
	 *
	 * Reads the parent quiz value via `get_group_id()` so the cascade pivots
	 * regardless of which trigger meta key holds the quiz ID.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	private function build_quiz_essays( $request ) {

		$values   = $request->get_values();
		$group_id = $request->get_group_id();
		$raw      = $values[ $group_id ] ?? 0;

		$options = array(
			array(
				'value' => '-1',
				'text'  => esc_attr_x( 'Any essay', 'LearnDash', 'uncanny-automator' ),
			),
		);

		// Parent set to "Any quiz" — no concrete quiz to enumerate.
		if ( '-1' === (string) $raw ) {
			return $options;
		}

		$quiz_id = ( 'automator_custom_value' === $raw )
			? absint( $values[ $group_id . '_custom' ] ?? 0 )
			: absint( $raw );

		if ( $quiz_id <= 0 || ! function_exists( 'learndash_get_quiz_questions' ) ) {
			return $options;
		}

		foreach ( learndash_get_quiz_questions( $quiz_id ) as $question_post_id => $pro_quiz_id ) {
			if ( 'essay' !== (string) get_post_meta( $question_post_id, 'question_type', true ) ) {
				continue;
			}
			$options[] = array(
				'value' => (string) $question_post_id,
				'text'  => get_the_title( $question_post_id ),
			);
		}

		return $options;
	}

	/**
	 * Build all-question options for the selected quiz.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	private function build_quiz_questions( $request ) {

		$values   = $request->get_values();
		$group_id = $request->get_group_id();
		$raw      = $values[ $group_id ] ?? 0;

		$options = array(
			array(
				'value' => '-1',
				'text'  => esc_attr_x( 'Any question', 'LearnDash', 'uncanny-automator' ),
			),
		);

		// Parent set to "Any quiz" — no concrete quiz to enumerate.
		if ( '-1' === (string) $raw ) {
			return $options;
		}

		$quiz_id = ( 'automator_custom_value' === $raw )
			? absint( $values[ $group_id . '_custom' ] ?? 0 )
			: absint( $raw );

		if ( $quiz_id <= 0 || ! function_exists( 'learndash_get_quiz_questions' ) ) {
			return $options;
		}

		foreach ( learndash_get_quiz_questions( $quiz_id ) as $question_post_id => $pro_quiz_id ) {
			$options[] = array(
				'value' => (string) $question_post_id,
				'text'  => get_the_title( $question_post_id ),
			);
		}

		return $options;
	}

	/**
	 * Build quiz options scoped to LDCOURSE and/or LDSTEP (lesson/topic).
	 *
	 * @param Remote_Data_Request $request     The remote-data request.
	 * @param bool                $include_any Whether to prepend "Any quiz".
	 *
	 * @return array
	 */
	private function build_quizzes_from_course_lessontopic( $request, $include_any ) {

		$values     = $request->get_values();
		$step_raw   = $values['LDSTEP'] ?? '';
		$course_raw = $values['LDCOURSE'] ?? '';

		$step_id = ( 'automator_custom_value' === $step_raw )
			? absint( $values['LDSTEP_custom'] ?? 0 )
			: absint( $step_raw );

		$course_id = ( 'automator_custom_value' === $course_raw )
			? absint( $values['LDCOURSE_custom'] ?? 0 )
			: absint( $course_raw );

		$options = array();
		if ( $include_any ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_attr_x( 'Any quiz', 'LearnDash', 'uncanny-automator' ),
			);
		}

		// Nothing chosen yet — list every published quiz.
		if ( 0 === $course_id && 0 === $step_id ) {
			foreach ( $this->build_post_type_options( 'sfwd-quiz', false ) as $option ) {
				$options[] = $option;
			}
			return $options;
		}

		$quizzes = array();

		if ( $step_id > 0 ) {
			$quizzes = learndash_get_lesson_quiz_list( $step_id, null, $course_id );
		} elseif ( $course_id > 0 ) {
			$quizzes  = learndash_get_course_quiz_list( $course_id );
			$step_ids = learndash_get_course_steps( $course_id );
			if ( ! empty( $step_ids ) ) {
				foreach ( $step_ids as $sid ) {
					$quizzes = array_merge( $quizzes, learndash_get_lesson_quiz_list( $sid, null, $course_id ) );
				}
			}
		}

		foreach ( $quizzes as $quiz ) {
			if ( empty( $quiz['post'] ) ) {
				continue;
			}
			$options[] = array(
				'value' => (string) $quiz['post']->ID,
				'text'  => $quiz['post']->post_title,
			);
		}

		return $options;
	}

	/**
	 * Build essay-question options scoped to LDCOURSE / LDSTEP / LDQUIZ.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	private function build_essay_questions_from_course_lessontopic_quiz( $request ) {

		$values     = $request->get_values();
		$course_raw = $values['LDCOURSE'] ?? '';
		$step_raw   = $values['LDSTEP'] ?? '';
		$quiz_raw   = $values['LDQUIZ'] ?? '';

		$course_id = ( 'automator_custom_value' === $course_raw )
			? absint( $values['LDCOURSE_custom'] ?? 0 )
			: absint( $course_raw );
		$step_id   = ( 'automator_custom_value' === $step_raw )
			? absint( $values['LDSTEP_custom'] ?? 0 )
			: absint( $step_raw );
		$quiz_id   = ( 'automator_custom_value' === $quiz_raw )
			? absint( $values['LDQUIZ_custom'] ?? 0 )
			: absint( $quiz_raw );

		$any_label = esc_attr_x( 'Any question', 'LearnDash', 'uncanny-automator' );

		// Nothing scoped — return every essay question across the site.
		if ( 0 === $course_id && 0 === $step_id && 0 === $quiz_id ) {
			return $this->essay_question_options( $any_label );
		}

		$quiz_ids = array();

		if ( $quiz_id > 0 ) {
			$quiz_ids[] = $quiz_id;
		} elseif ( $step_id > 0 ) {
			foreach ( (array) learndash_get_lesson_quiz_list( $step_id ) as $quiz ) {
				if ( ! empty( $quiz['post'] ) ) {
					$quiz_ids[] = $quiz['post']->ID;
				}
			}
		} elseif ( $course_id > 0 ) {
			$quizzes  = (array) learndash_get_course_quiz_list( $course_id );
			$step_ids = (array) learndash_get_course_steps( $course_id );
			foreach ( $step_ids as $sid ) {
				$quizzes = array_merge( $quizzes, (array) learndash_get_lesson_quiz_list( $sid ) );
			}
			foreach ( $quizzes as $quiz ) {
				if ( ! empty( $quiz['post'] ) ) {
					$quiz_ids[] = $quiz['post']->ID;
				}
			}
		}

		return $this->essay_question_options( $any_label, $quiz_ids );
	}

	/**
	 * Query essay-typed questions, optionally scoped to a list of quiz IDs.
	 *
	 * @param string $any_label Label for the leading "Any" option (always included).
	 * @param array  $quiz_ids  Quiz post IDs to filter against (optional).
	 *
	 * @return array
	 */
	private function essay_question_options( $any_label, $quiz_ids = array() ) {

		$options = array(
			array(
				'value' => '-1',
				'text'  => $any_label,
			),
		);

		$meta_query = array();
		if ( ! empty( $quiz_ids ) ) {
			$meta_query[] = array(
				'key'     => 'quiz_id',
				'value'   => array_map( 'intval', $quiz_ids ),
				'compare' => 'IN',
			);
		}
		$meta_query[] = array(
			'key'     => 'question_type',
			'value'   => 'essay',
			'compare' => '=',
		);
		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'AND';
		}

		$questions = get_posts(
			array(
				'post_type'      => 'sfwd-question',
				'posts_per_page' => 9999,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		foreach ( $questions as $question ) {
			$options[] = array(
				'value' => (string) $question->ID,
				'text'  => $question->post_title,
			);
		}

		return $options;
	}

	// =========================================================================
	// Legacy delegators retained for old Pro composition callers (Phase 5).
	// Not on the modern remote_data path — these serve `$this->pro` callers.
	// =========================================================================

	/**
	 * Activity-callback delegator for legacy callers.
	 *
	 * @param array $args Activity args.
	 *
	 * @return void
	 */
	public function learndash_update_user_activity_func( $args ) {
		$this->legacy->learndash_update_user_activity_func( $args );
	}

	/**
	 * All groups with hierarchy (Pro).
	 *
	 * @return mixed
	 */
	public function all_ld_groups_with_hierarchy() {
		if ( null !== $this->pro && method_exists( $this->pro, 'all_ld_groups_with_hierarchy' ) ) {
			return call_user_func_array( array( $this->pro, 'all_ld_groups_with_hierarchy' ), func_get_args() );
		}
		return $this->all_ld_groups( func_get_args() );
	}

	/**
	 * All certificates (Pro).
	 *
	 * @return mixed
	 */
	public function all_ld_certificates() {
		if ( null !== $this->pro && method_exists( $this->pro, 'all_ld_certificates' ) ) {
			return call_user_func_array( array( $this->pro, 'all_ld_certificates' ), func_get_args() );
		}
		return array();
	}

	/**
	 * All quizzes with format options (Pro).
	 *
	 * @return mixed
	 */
	public function all_ld_quizzes() {
		if ( null !== $this->pro && method_exists( $this->pro, 'all_ld_quizzes' ) ) {
			return call_user_func_array( array( $this->pro, 'all_ld_quizzes' ), func_get_args() );
		}
		return $this->all_ld_quiz( func_get_args() );
	}

	/**
	 * Validate group IDs (Pro).
	 *
	 * @param array $group_ids Group IDs.
	 *
	 * @return array
	 */
	public function learndash_validate_groups( $group_ids ) {
		if ( null !== $this->pro && method_exists( $this->pro, 'learndash_validate_groups' ) ) {
			return $this->pro->learndash_validate_groups( $group_ids );
		}
		return $group_ids;
	}

	/**
	 * Generate certificate PDF (Pro).
	 *
	 * @return mixed
	 */
	public function generate_pdf() {
		if ( null !== $this->pro && method_exists( $this->pro, 'generate_pdf' ) ) {
			return call_user_func_array( array( $this->pro, 'generate_pdf' ), func_get_args() );
		}
		return false;
	}

	/**
	 * Generate certificate contents (Pro).
	 *
	 * @return array
	 */
	public function generate_certificate_contents() {
		if ( null !== $this->pro && method_exists( $this->pro, 'generate_certificate_contents' ) ) {
			return call_user_func_array( array( $this->pro, 'generate_certificate_contents' ), func_get_args() );
		}
		return array();
	}
}
