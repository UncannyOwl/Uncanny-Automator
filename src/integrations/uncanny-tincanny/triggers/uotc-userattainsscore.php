<?php

namespace Uncanny_Automator\Integrations\Uncanny_Tincanny;

use TINCANNYSNC\Database;

/**
 * Trigger: A user attains a score on a Tin Can module.
 *
 * @property \Uncanny_Automator\Integrations\Uncanny_Tincanny\Uotc_Helpers $item_helpers
 */
class UOTC_USERATTAINSSCORE extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USERATTAINSSCORE', 'UOTC' )
			->trigger_meta( 'TCUSERATTAINSSCORE' )
			->hook( 'tincanny_module_result_processed', 99, 3 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		// translators: %1$s is the condition, %2$s is the score, %3$s is the module.
		$this->set_sentence( sprintf( esc_html_x( 'A user attains a score {{greater than, less than or equal to:%1$s}} {{a score:%2$s}} on {{a Tin Can module:%3$s}}', 'Tin Canny Reporting', 'uncanny-automator' ), 'NUMBERCOND', $this->get_trigger_meta(), 'TCMODULEINTERACTION' ) );
		$this->set_readable_sentence( esc_html_x( 'A user attains {{a score}} {{greater than, less than or equal to}} on {{a Tin Can module}}', 'Tin Canny Reporting', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {

		// Build NUMBERCOND options from legacy helper.

		$condition_options = array();

		foreach ( $number_conditions['options'] as $key => $label ) {
			$condition_options[] = array(
				'text'  => $label,
				'value' => $key,
			);
		}

		return array(
			array(
				'option_code' => 'TCMODULEINTERACTION',
				'label'       => esc_html_x( 'Module', 'Tin Canny Reporting', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'remote_data' => $this->item_helpers->remote_data_load_config( 'modules' ),
				'options'     => array(),
			),
			array(
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Module Score', 'Tin Canny Reporting', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'default'     => '0',
			),
			array(
				'option_code' => 'NUMBERCOND',
				'label'       => esc_html_x( 'Condition', 'Tin Canny Reporting', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => $condition_options,
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
				'tokenId'   => 'NUMBERCOND',
				'tokenName' => esc_html_x( 'Condition', 'Tin Canny Reporting', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
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

		list( $module_id, $user_id, $score ) = $hook_args;

		if ( 0 === absint( $user_id ) ) {
			return false;
		}

		if ( absint( $score ) < 0 ) {
			return false;
		}

		if ( ! empty( $module_id ) && ! absint( $module_id ) ) {
			return false;
		}

		$selected_module = $trigger['meta']['TCMODULEINTERACTION'] ?? '';
		$condition       = $trigger['meta']['NUMBERCOND'] ?? '>=';
		$required_score  = $trigger['meta'][ $this->get_trigger_meta() ] ?? 0;

		// Check module match.
		if ( '-1' !== $selected_module && (int) $selected_module !== (int) $module_id ) {
			return false;
		}

		// Check score condition.
		if ( ! \Automator()->utilities->match_condition_vs_number( $condition, $required_score, $score ) ) {
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

		list( $module_id, $user_id, $score ) = $hook_args;

		$module_info = Uotc_Helpers::get_item( $module_id );
		$module_name = isset( $module_info['file_name'] ) ? $module_info['file_name'] : '';
		$condition   = $trigger['meta']['NUMBERCOND'] ?? '';

		// Translate condition symbol to readable text.
		$condition_labels = array(
			'<'  => esc_html_x( 'less than', 'Tin Canny Reporting', 'uncanny-automator' ),
			'>'  => esc_html_x( 'greater than', 'Tin Canny Reporting', 'uncanny-automator' ),
			'='  => esc_html_x( 'equal to', 'Tin Canny Reporting', 'uncanny-automator' ),
			'!=' => esc_html_x( 'not equal to', 'Tin Canny Reporting', 'uncanny-automator' ),
			'>=' => esc_html_x( 'greater or equal to', 'Tin Canny Reporting', 'uncanny-automator' ),
			'<=' => esc_html_x( 'less or equal to', 'Tin Canny Reporting', 'uncanny-automator' ),
		);

		$tokens = array(
			$this->get_trigger_meta()  => $score,
			'TCMODULEINTERACTION'      => $module_name,
			'NUMBERCOND'               => isset( $condition_labels[ $condition ] ) ? $condition_labels[ $condition ] : '',
			'TC_COURSE_TITLE'          => '',
			'TC_COURSE_ID'             => '',
			'TC_COURSE_URL'            => '',
			'TC_LESSON_TITLE'          => '',
			'TC_LESSON_ID'             => '',
			'TC_LESSON_URL'            => '',
			'TC_TOPIC_TITLE'           => '',
			'TC_TOPIC_ID'              => '',
			'TC_TOPIC_URL'             => '',
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
			if ( ! empty( $tin_can_data->lesson_id ) ) {
				$tokens['TC_LESSON_TITLE'] = get_the_title( $tin_can_data->lesson_id );
				$tokens['TC_LESSON_ID']    = $tin_can_data->lesson_id;
				$tokens['TC_LESSON_URL']   = get_the_permalink( $tin_can_data->lesson_id );
			}
		} else {
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
