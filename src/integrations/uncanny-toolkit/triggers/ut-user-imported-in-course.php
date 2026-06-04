<?php

namespace Uncanny_Automator\Integrations\Uncanny_Toolkit;

/**
 * Trigger: A user is imported to a LearnDash course.
 *
 * @property \Uncanny_Automator\Integrations\Uncanny_Toolkit\Ut_Helpers $item_helpers
 */
class UT_USER_IMPORTED_IN_COURSE extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'UTUSERIMPORTEDCOURSE', 'UNCANNYTOOLKIT' )
			->trigger_meta( 'UOUSERIMPORTEDCOURSE' )
			->hook( 'uo_after_user_row_imported', 20, 4 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		// translators: %1$s is a LearnDash course.
		$this->set_sentence( sprintf( esc_html_x( 'A user is imported to {{a LearnDash course:%1$s}}', 'Uncanny Toolkit', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A user is imported to {{a LearnDash course}}', 'Uncanny Toolkit', 'uncanny-automator' ) );
	}

	/**
	 * Check if LearnDash and Toolkit Pro are active.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return defined( 'LEARNDASH_VERSION' ) && defined( 'UNCANNY_TOOLKIT_PRO_VERSION' );
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
				'label'       => esc_html_x( 'Course', 'Uncanny Toolkit', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'remote_data' => $this->item_helpers->remote_data_load_config( 'courses' ),
				'options'     => array(),
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
				'tokenId'   => 'user_id',
				'tokenName' => esc_html_x( 'Imported user ID', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'user_login',
				'tokenName' => esc_html_x( 'Imported user login', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'user_email',
				'tokenName' => esc_html_x( 'Imported user email', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'first_name',
				'tokenName' => esc_html_x( 'Imported user first name', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'last_name',
				'tokenName' => esc_html_x( 'Imported user last name', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'display_name',
				'tokenName' => esc_html_x( 'Imported user display name', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'wp_role',
				'tokenName' => esc_html_x( 'Imported user WordPress role', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'learndash_course_id',
				'tokenName' => esc_html_x( 'Course ID', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);

		return array_merge( $new_tokens, $tokens );
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * The trigger fires once per imported course ID. The framework loops through
	 * recipes automatically, so validate() only checks the current recipe's
	 * selection against one course ID at a time.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $user_id, $csv_data, $csv_header, $key_location ) = $hook_args;

		if ( ! is_numeric( $user_id ) ) {
			return false;
		}

		$meta_value = Ut_Helpers::build_token_data( $csv_data, $csv_header, $key_location, $user_id );

		if ( ! isset( $meta_value['learndash_course_ids'] ) || empty( $meta_value['learndash_course_ids'] ) ) {
			return false;
		}

		$selected_course = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		// "Any course" sentinel.
		if ( '-1' === $selected_course ) {
			return true;
		}

		// Check if the selected course is among the imported courses.
		foreach ( $meta_value['learndash_course_ids'] as $course_id ) {
			if ( (int) $selected_course === (int) $course_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $user_id, $csv_data, $csv_header, $key_location ) = $hook_args;

		$meta_value = Ut_Helpers::build_token_data( $csv_data, $csv_header, $key_location, $user_id );

		$tokens          = array();
		$selected_course = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		if ( is_array( $meta_value ) ) {
			foreach ( $meta_value as $key => $val ) {
				if ( is_array( $val ) ) {
					$tokens[ $key ] = implode( ' | ', $val );
				} else {
					$tokens[ $key ] = wp_strip_all_tags( $val );
				}
			}
		}

		// Determine which course ID to use for the single-course token.
		$course_id = 0;
		if ( '-1' === $selected_course && ! empty( $meta_value['learndash_course_ids'] ) ) {
			$course_id = reset( $meta_value['learndash_course_ids'] );
		} elseif ( ! empty( $selected_course ) ) {
			$course_id = $selected_course;
		}

		$tokens['learndash_course_id']       = $course_id;
		$tokens[ $this->get_trigger_meta() ] = get_the_title( $course_id );

		return $tokens;
	}
}
