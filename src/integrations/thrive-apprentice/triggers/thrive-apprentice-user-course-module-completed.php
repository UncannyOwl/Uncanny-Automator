<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class THRIVE_APPRENTICE_USER_COURSE_MODULE_COMPLETED
 *
 * @package Uncanny_Automator
 */
class THRIVE_APPRENTICE_USER_COURSE_MODULE_COMPLETED extends Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'THRIVE_APPRENTICE_USER_COURSE_MODULE_COMPLETED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'THRIVE_APPRENTICE_USER_COURSE_MODULE_COMPLETED_META';

	/**
	 * Helper instance
	 *
	 * @var Thrive_Apprentice_Helpers
	 */
	protected $helper;

	/**
	 * Setup trigger
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->helper = new Thrive_Apprentice_Helpers( false );

		$this->set_integration( 'THRIVE_APPRENTICE' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_is_pro( false );

		// Hook into module completion event
		$this->add_action( 'thrive_apprentice_module_finish' );

		$this->set_action_args_count( 2 );

		$this->set_sentence(
			sprintf(
				// translators:  %1$s: Module,  %2$s: Course
				esc_html_x( 'A user completes {{a module:%2$s}} in {{a course:%1$s}}', 'Thrive Apprentice', 'uncanny-automator' ),
				'COURSE:' . $this->get_trigger_meta(),
				$this->get_trigger_meta() . ':' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user completes {{a module}} in {{a course}}', 'Thrive Apprentice', 'uncanny-automator' )
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool True if validation was successful.
	 */
	public function validate( $trigger, $hook_args ) {
		list($module, $user) = $hook_args;

		if ( empty( $module ) || empty( $user ) ) {
			return false;
		}

		// Get module and course IDs from the trigger
		$selected_module_id = $trigger['meta'][ $this->get_trigger_meta() ];
		$selected_course_id = $trigger['meta']['COURSE'];

		// Get actual module and course IDs from the hook
		$module_id = absint( $module['module_id'] ?? 0 );
		$course_id = absint( $module['course_id'] ?? 0 );

		$this->set_user_id( absint( $user['user_id'] ) );

		// Match if any module is selected (-1) or if specific module matches
		$module_matches = intval( '-1' ) === intval( $selected_module_id ) || (int) $selected_module_id === (int) $module_id;
		// Match if any course is selected (-1) or if specific course matches
		$course_matches = intval( '-1' ) === intval( $selected_course_id ) || (int) $selected_course_id === (int) $course_id;

		return $module_matches && $course_matches;
	}

	/**
	 * Loads available options for the Trigger.
	 *
	 * @return array The available trigger options.
	 */
	public function options() {
		return array(
			array(
				'option_code'     => 'COURSE',
				'label'           => esc_html_x( 'Course', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->helper->get_dropdown_options_courses( true, true ),
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Module', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'ajax'            => array(
					'event'         => 'parent_fields_change',
					'endpoint'      => 'automator_thrive_apprentice_updated_modules_handler',
					'listen_fields' => array( 'COURSE' ),
				),
			),
		);
	}

	/**
	 * Hydrate tokens with values.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array The token values.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list($module, $user) = $hook_args;

		return array(
			'COURSE_ID'          => $module['course_id'],
			'COURSE_TITLE'       => $module['course_title'],
			'MODULE_ID'          => $module['module_id'],
			'MODULE_TITLE'       => $module['module_title'],
			'MODULE_DESCRIPTION' => $module['module_description'],
			'MODULE_URL'         => $module['module_url'],
		);
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger The trigger configuration.
	 * @param array $tokens The existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			'MODULE_ID'          => array(
				'name'      => esc_html_x( 'Module ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'MODULE_ID',
				'tokenName' => esc_html_x( 'Module ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'MODULE_TITLE'       => array(
				'name'      => esc_html_x( 'Module title', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'MODULE_TITLE',
				'tokenName' => esc_html_x( 'Module title', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'MODULE_DESCRIPTION' => array(
				'name'      => esc_html_x( 'Module description', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'MODULE_DESCRIPTION',
				'tokenName' => esc_html_x( 'Module description', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'MODULE_URL'         => array(
				'name'      => esc_html_x( 'Module URL', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'url',
				'tokenId'   => 'MODULE_URL',
				'tokenName' => esc_html_x( 'Module URL', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'COURSE_ID'          => array(
				'name'      => esc_html_x( 'Course ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'COURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'COURSE_TITLE'       => array(
				'name'      => esc_html_x( 'Course title', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'COURSE_TITLE',
				'tokenName' => esc_html_x( 'Course title', 'Thrive Apprentice', 'uncanny-automator' ),
			),
		);
	}
}
