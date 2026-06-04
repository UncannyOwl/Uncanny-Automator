<?php

namespace Uncanny_Automator\Integrations\Learndash;
/**
 * Class LD_PASSQUIZ
 *
 * @property \Uncanny_Automator\Integrations\Learndash\Ld_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Learndash
 */
class LD_PASSQUIZ extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'LD_PASSQUIZ', 'LD' )
			->trigger_meta( 'LDQUIZ' )
			->hook( 'learndash_quiz_submitted', 15, 4 )
			->hook( 'learndash_essay_quiz_data_updated', 15, 4 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hooks are auto-applied from definition().

		$this->set_sentence(
			sprintf(
				esc_html_x( 'A user passes {{a quiz:%1$s}}', 'LearnDash', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'A user passes {{a quiz}}', 'LearnDash', 'uncanny-automator' ) );
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
				'label'                    => esc_html_x( 'Quiz', 'LearnDash', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'options'                  => array(),
				'custom_value_description' => esc_html_x( 'Quiz ID', 'LearnDash', 'uncanny-automator' ),
				'remote_data'              => $this->item_helpers->remote_data_load_config( 'quizzes' ),
			),
		);
	}

	/**
	 * Define additional tokens for this trigger.
	 *
	 * @param array $trigger The trigger definition.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tokens_class = new Ld_Tokens_New_Framework();

		return array_merge( $tokens, $tokens_class->quiz_tokens() );
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

		$user    = false;
		$quiz    = false;
		$helpers = $this->item_helpers;

		if ( did_action( 'learndash_quiz_submitted' ) ) {

			$data         = $hook_args[0] ?? array();
			$current_user = $hook_args[1] ?? null;

			if ( empty( $data ) ) {
				return false;
			}

			$passed = $helpers->submitted_quiz_pased( $data );

			if ( is_wp_error( $passed ) || empty( $passed ) ) {
				return false;
			}

			$quiz = $data['quiz'] ?? null;
			$user = $current_user;

		} elseif ( did_action( 'learndash_essay_quiz_data_updated' ) ) {

			$pro_quiz_id = $hook_args[0] ?? 0;
			$essay       = $hook_args[3] ?? null;

			if ( empty( $essay ) ) {
				return false;
			}

			$passed = $helpers->graded_quiz_passed( $essay, $pro_quiz_id );

			if ( is_wp_error( $passed ) || empty( $passed ) ) {
				return false;
			}

			$quiz = get_post_meta( $essay->ID, 'quiz_post_id', true );
			$user = get_user_by( 'id', $essay->post_author );
		}

		$post_id = is_object( $quiz ) ? $quiz->ID : $quiz;

		if ( empty( $user ) ) {
			$user = wp_get_current_user();
		}

		if ( empty( $user->ID ) || empty( $post_id ) ) {
			return false;
		}

		$this->set_user_id( $user->ID );

		$selected = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		if ( intval( '-1' ) !== intval( $selected ) && absint( $selected ) !== absint( $post_id ) ) {
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

		$quiz    = false;
		$user    = false;
		$data    = array();

		if ( did_action( 'learndash_quiz_submitted' ) ) {
			$data = $hook_args[0] ?? array();
			$quiz = $data['quiz'] ?? null;
			$user = $hook_args[1] ?? null;
		} elseif ( did_action( 'learndash_essay_quiz_data_updated' ) ) {
			$essay = $hook_args[3] ?? null;
			$quiz  = ! empty( $essay ) ? get_post_meta( $essay->ID, 'quiz_post_id', true ) : null;
			$user  = ! empty( $essay ) ? get_user_by( 'id', $essay->post_author ) : null;
		}

		$quiz_id = is_object( $quiz ) ? $quiz->ID : $quiz;

		if ( empty( $user ) ) {
			$user = wp_get_current_user();
		}

		$tokens_class = new Ld_Tokens_New_Framework();

		return array_merge(
			$tokens_class->hydrate_quiz_tokens( $quiz_id, $data ),
			array( $this->get_trigger_meta() => get_the_title( $quiz_id ) )
		);
	}
}
