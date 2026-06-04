<?php

namespace Uncanny_Automator\Integrations\Uncanny_Tincanny;

use TINCANNYSNC\Database;

/**
 * Trigger: A Tin Can verb is recorded from a Tin Can module.
 *
 * @property \Uncanny_Automator\Integrations\Uncanny_Tincanny\Uotc_Helpers $item_helpers
 */
class UOTC_MODULEINTERACTION extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'MODULEINTERACTION', 'UOTC' )
			->trigger_meta( 'TCMODULEINTERACTION' )
			->hook( 'tincanny_module_completed', 99, 3 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		// translators: %1$s is a verb, %2$s is a module.
		$this->set_sentence( sprintf( esc_html_x( '{{A Tin Can verb:%1$s}} is recorded from {{a Tin Can module:%2$s}}', 'Tin Canny Reporting', 'uncanny-automator' ), 'TCVERB', $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( '{{A Tin Can verb}} is recorded from {{a Tin Can module}}', 'Tin Canny Reporting', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Module', 'Tin Canny Reporting', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'remote_data' => $this->item_helpers->remote_data_load_config( 'modules' ),
				'options'     => array(),
			),
			array(
				'option_code' => 'TCVERB',
				'label'       => esc_html_x( 'Verb', 'Tin Canny Reporting', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(
					array(
						'value' => '-1',
						'text'  => esc_html_x( 'Any', 'Tin Canny Reporting', 'uncanny-automator' ),
					),
					array(
						'value' => 'completed',
						'text'  => esc_html_x( 'Completed', 'Tin Canny Reporting', 'uncanny-automator' ),
					),
					array(
						'value' => 'passed',
						'text'  => esc_html_x( 'Passed', 'Tin Canny Reporting', 'uncanny-automator' ),
					),
					array(
						'value' => 'failed',
						'text'  => esc_html_x( 'Failed', 'Tin Canny Reporting', 'uncanny-automator' ),
					),
					array(
						'value' => 'answered',
						'text'  => esc_html_x( 'Answered', 'Tin Canny Reporting', 'uncanny-automator' ),
					),
					array(
						'value' => 'attempted',
						'text'  => esc_html_x( 'Attempted', 'Tin Canny Reporting', 'uncanny-automator' ),
					),
					array(
						'value' => 'experienced',
						'text'  => esc_html_x( 'Experienced', 'Tin Canny Reporting', 'uncanny-automator' ),
					),
				),
			),
		);
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$new_tokens = array(
			array(
				'tokenId'   => 'TC_COURSE_TITLE',
				'tokenName' => esc_html_x( 'Course title', 'Tin Canny Reporting', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TC_COURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'Tin Canny Reporting', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'TC_COURSE_URL',
				'tokenName' => esc_html_x( 'Course URL', 'Tin Canny Reporting', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'TC_LESSON_TITLE',
				'tokenName' => esc_html_x( 'Lesson title', 'Tin Canny Reporting', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TC_LESSON_ID',
				'tokenName' => esc_html_x( 'Lesson ID', 'Tin Canny Reporting', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'TC_LESSON_URL',
				'tokenName' => esc_html_x( 'Lesson URL', 'Tin Canny Reporting', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'TC_TOPIC_TITLE',
				'tokenName' => esc_html_x( 'Topic title', 'Tin Canny Reporting', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TC_TOPIC_ID',
				'tokenName' => esc_html_x( 'Topic ID', 'Tin Canny Reporting', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'TC_TOPIC_URL',
				'tokenName' => esc_html_x( 'Topic URL', 'Tin Canny Reporting', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);

		return array_merge( $tokens, $new_tokens );
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * Includes 10-second deduplication window from original implementation.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $module_id, $user_id, $verb ) = $hook_args;

		if ( empty( $user_id ) || empty( $verb ) ) {
			return false;
		}

		if ( empty( $module_id ) && ! absint( $module_id ) ) {
			return false;
		}

		// Flush cache to get the latest data.
		\automator_cache_delete_group();

		$selected_module = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';
		$selected_verb   = $trigger['meta']['TCVERB'] ?? '';

		// Check module match.
		if ( '-1' !== $selected_module && (string) $selected_module !== (string) $module_id ) {
			return false;
		}

		// Check verb match.
		if ( '-1' !== $selected_verb && strtolower( $selected_verb ) !== strtolower( $verb ) ) {
			return false;
		}

		// 10-second deduplication window.
		if ( ! $this->passes_deduplication_check( $user_id, $trigger ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if enough time has passed since the last completed run of this recipe.
	 *
	 * Prevents duplicate fires within a 10-second window, matching the original
	 * Tin Canny behavior.
	 *
	 * @param int   $user_id The user ID.
	 * @param array $trigger The trigger settings.
	 *
	 * @return bool
	 */
	private function passes_deduplication_check( $user_id, $trigger ) {

		$recipe_id = $trigger['recipe_id'] ?? 0;

		if ( empty( $recipe_id ) ) {
			return true;
		}

		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT date_time, CURRENT_TIMESTAMP as current_mysql_time
				FROM {$wpdb->prefix}uap_recipe_log
				WHERE 1=1
				AND user_id = %d
				AND automator_recipe_id = %d
				AND completed = 1
				ORDER BY ID DESC",
				$user_id,
				$recipe_id
			)
		);

		if ( empty( $result ) ) {
			return true;
		}

		$last_time    = strtotime( $result->date_time );
		$current_time = strtotime( $result->current_mysql_time );

		return ( $current_time - $last_time ) > 10;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * Resolves course/lesson/topic tokens from the Tin Canny reporting table,
	 * matching the original token parsing logic.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $module_id, $user_id, $verb ) = $hook_args;

		$module_info = Uotc_Helpers::get_item( $module_id );
		$module_name = isset( $module_info['file_name'] ) ? $module_info['file_name'] : '';

		$tokens = array(
			'TCVERB'                      => $verb,
			$this->get_trigger_meta()     => $module_name,
			'TC_COURSE_TITLE'             => '',
			'TC_COURSE_ID'                => '',
			'TC_COURSE_URL'               => '',
			'TC_LESSON_TITLE'             => '',
			'TC_LESSON_ID'                => '',
			'TC_LESSON_URL'               => '',
			'TC_TOPIC_TITLE'              => '',
			'TC_TOPIC_ID'                 => '',
			'TC_TOPIC_URL'                => '',
		);

		if ( ! class_exists( '\TINCANNYSNC\Database' ) ) {
			return $tokens;
		}

		global $wpdb;

		$table_name   = $wpdb->prefix . Database::TABLE_REPORTING;
		$tin_can_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d AND module LIKE %s ORDER BY xstored DESC LIMIT 0,1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				'%%/uncanny-snc/' . $module_id . '/%%'
			)
		);

		if ( empty( $tin_can_data ) ) {
			return $tokens;
		}

		if ( ! empty( $tin_can_data->course_id ) ) {
			$tokens['TC_COURSE_TITLE'] = get_the_title( $tin_can_data->course_id );
			$tokens['TC_COURSE_ID']    = $tin_can_data->course_id;
			$tokens['TC_COURSE_URL']   = get_the_permalink( $tin_can_data->course_id );
		}

		$post_type = get_post_type( $tin_can_data->lesson_id );

		if ( 'sfwd-lessons' === $post_type ) {
			// lesson_id is actually a lesson.
			if ( ! empty( $tin_can_data->lesson_id ) ) {
				$tokens['TC_LESSON_TITLE'] = get_the_title( $tin_can_data->lesson_id );
				$tokens['TC_LESSON_ID']    = $tin_can_data->lesson_id;
				$tokens['TC_LESSON_URL']   = get_the_permalink( $tin_can_data->lesson_id );
			}
		} else {
			// lesson_id is actually a topic.
			if ( ! empty( $tin_can_data->lesson_id ) ) {
				$tokens['TC_TOPIC_TITLE'] = get_the_title( $tin_can_data->lesson_id );
				$tokens['TC_TOPIC_ID']    = $tin_can_data->lesson_id;
				$tokens['TC_TOPIC_URL']   = get_the_permalink( $tin_can_data->lesson_id );
			}

			// Resolve the parent lesson for shared steps.
			if ( ! empty( $tin_can_data->course_id )
				&& function_exists( 'learndash_course_get_single_parent_step' )
				&& class_exists( '\LearnDash_Settings_Section' )
				&& 'yes' === \LearnDash_Settings_Section::get_section_setting( 'LearnDash_Settings_Courses_Builder', 'shared_steps' )
			) {
				$lesson_id = learndash_course_get_single_parent_step( $tin_can_data->course_id, $tin_can_data->lesson_id );
				if ( ! empty( $lesson_id ) ) {
					$tokens['TC_LESSON_TITLE'] = get_the_title( $lesson_id );
					$tokens['TC_LESSON_ID']    = $lesson_id;
					$tokens['TC_LESSON_URL']   = get_the_permalink( $lesson_id );
				}
			}
		}

		return $tokens;
	}
}
