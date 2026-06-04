<?php

namespace Uncanny_Automator\Integrations\Learndash;

/**
 * Class LD_COURSEDONE
 *
 * @property \Uncanny_Automator\Integrations\Learndash\Ld_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Learndash
 */
class LD_COURSEDONE extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'COURSEDONE', 'LD' )
			->trigger_meta( 'LDCOURSE' )
			->hook( 'learndash_course_completed', 20, 1 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().

		$this->set_sentence(
			sprintf(
				esc_html_x( 'A user completes {{a course:%1$s}}', 'LearnDash', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'A user completes {{a course}}', 'LearnDash', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'              => $this->get_trigger_meta(),
				'label'                    => esc_html_x( 'Course', 'LearnDash', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'options'                  => array(),
				'custom_value_description' => esc_html_x( 'Course ID', 'LearnDash', 'uncanny-automator' ),
				'remote_data'              => $this->item_helpers->remote_data_load_config( 'courses' ),
			),
		);
	}

	/**
	 * Define additional tokens for this trigger.
	 *
	 * @param array $trigger   The trigger definition.
	 * @param array $tokens    Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tokens_class = new Ld_Tokens_New_Framework();

		return array_merge(
			$tokens,
			$tokens_class->course_tokens(),
			$tokens_class->course_completion_tokens()
		);
	}

	/**
	 * Validate whether the trigger should fire.
	 *
	 * @param array $trigger   The trigger definition and metadata.
	 * @param array $hook_args The arguments from the WP hook.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $data ) = $hook_args;

		if ( empty( $data ) ) {
			return false;
		}

		$selected  = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';
		$course_id = $data['course']->ID ?? 0;
		$user_id   = $data['user']->ID ?? 0;

		if ( empty( $user_id ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		if ( intval( '-1' ) !== intval( $selected ) && absint( $selected ) !== absint( $course_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Hydrate tokens with actual values.
	 *
	 * @param array $trigger   The trigger definition.
	 * @param array $hook_args The arguments from the WP hook.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $data ) = $hook_args;

		$course_id = $data['course']->ID ?? 0;
		$user_id   = $data['user']->ID ?? 0;

		$tokens_class = new Ld_Tokens_New_Framework();

		return array_merge(
			$tokens_class->hydrate_course_tokens( $course_id, $user_id ),
			$tokens_class->hydrate_course_completion_tokens( $course_id, $user_id ),
			array( $this->get_trigger_meta() => get_the_title( $course_id ) )
		);
	}
}
