<?php

namespace Uncanny_Automator;

use LLMS_Section;

/**
 * Class LF_MARKSECTIONDONE
 * @package Uncanny_Automator
 */
class LF_MARKSECTIONDONE {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'LF';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'LFMARKSECTIONDONE-A';
		$this->action_meta = 'LFSECTION';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {



		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/lifterlms/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - LifterLMS */
			'sentence'           => sprintf( esc_attr__( 'Mark {{a section:%1$s}} complete for the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - LifterLMS */
			'select_option_name' => esc_attr__( 'Mark {{a section}} complete for the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'lf_mark_section_done' ),
			'options'            => [
				Automator()->helpers->recipe->lifterlms->options->all_lf_sections( esc_attr__( 'Section', 'uncanny-automator' ), $this->action_meta, false ),
			],
		);

		Automator()->register->action( $action );
	}


	/**
	 * Validation function when the action is hit.
	 *
	 * @param string $user_id user id.
	 * @param array $action_data action data.
	 * @param string $recipe_id recipe id.
	 */
	public function lf_mark_section_done( $user_id, $action_data, $recipe_id, $args ) {



		if ( ! function_exists( 'llms_mark_complete' ) ) {
			$error_message = 'The function llms_mark_complete does not exist';
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$section_id = $action_data['meta'][ $this->action_meta ];

		// Get all lessons of section.
		$section = new LLMS_Section( $section_id );
		$lessons = $section->get_lessons();
		if ( ! empty( $lessons ) ) {
			foreach ( $lessons as $lesson ) {
				llms_mark_complete( $user_id, $lesson->id, 'lesson' );
			}
		}

		llms_mark_complete( $user_id, $section_id, 'section' );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}
}
