<?php
namespace Uncanny_Automator\Integrations\Thrive_Apprentice;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class THRIVE_APPRENTICE_USER_COURSE_COMPLETED
 *
 * @package Uncanny_Automator
 */
class THRIVE_APPRENTICE_USER_COURSE_COMPLETED extends Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'THRIVE_APPRENTICE_USER_COURSE_COMPLETED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'THRIVE_APPRENTICE_USER_COURSE_COMPLETED_META';

	/**
	 * Setup trigger
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'THRIVE_APPRENTICE' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_is_pro( false );

		$this->add_action( 'thrive_apprentice_course_finish' );

		$this->set_action_args_count( 2 );

		$this->set_sentence(
			sprintf(
				// translators:  %1$s: Course
				esc_html_x( 'A user completes {{a course:%1$s}}', 'Thrive Apprentice', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user completes {{a course}}', 'Thrive Apprentice', 'uncanny-automator' )
		);
	}

	/**
	 * Loads available options for the Trigger.
	 *
	 * @return array The available trigger options.
	 */
	public function options() {
		$helper = new Thrive_Apprentice_Helpers( false );
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'required'        => true,
				'label'           => esc_html_x( 'Course', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'options'         => $helper->get_dropdown_options_courses( true, true ),
				'relevant_tokens' => array(),
			),
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
		list( $course, $user ) = $hook_args;

		if ( empty( $course ) || empty( $user ) ) {
			return false;
		}

		$this->set_user_id( absint( $user['user_id'] ) );

		$course_id          = absint( $course['course_id'] );
		$selected_course_id = $trigger['meta'][ $this->get_trigger_meta() ];

		// Match if any course is selected (-1) or if specific course matches
		return intval( '-1' ) === intval( $selected_course_id ) || (int) $selected_course_id === (int) $course_id;
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
		list( $course_data, $user_data ) = $hook_args;

		return array(
			'COURSE_ID'            => $course_data['course_id'],
			'COURSE_TITLE'         => $course_data['course_title'],
			'COURSE_URL'           => $course_data['course_url'],
			'COURSE_DESCRIPTION'   => $course_data['course_description'],
			'USER_ID'              => $user_data['user_id'],
			'USER_EMAIL'           => $user_data['email'],
			'MEMBERSHIP_LEVEL'     => $user_data['membership_level'],
			'USER_REGISTERED_DATE' => $user_data['registered'],
			'USER_LAST_LOGIN_DATE' => $user_data['last_logged_in'],
			'USER_IP'              => $user_data['ip_address'],
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
			'COURSE_ID'            => array(
				'name'      => esc_html_x( 'Course ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'COURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'COURSE_TITLE'         => array(
				'name'      => esc_html_x( 'Course title', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'COURSE_TITLE',
				'tokenName' => esc_html_x( 'Course title', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'COURSE_URL'           => array(
				'name'      => esc_html_x( 'Course URL', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'url',
				'tokenId'   => 'COURSE_URL',
				'tokenName' => esc_html_x( 'Course URL', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'COURSE_DESCRIPTION'   => array(
				'name'      => esc_html_x( 'Course description', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'COURSE_DESCRIPTION',
				'tokenName' => esc_html_x( 'Course description', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'USER_ID'              => array(
				'name'      => esc_html_x( 'User ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'USER_ID',
				'tokenName' => esc_html_x( 'User ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'USER_EMAIL'           => array(
				'name'      => esc_html_x( 'User email', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'email',
				'tokenId'   => 'USER_EMAIL',
				'tokenName' => esc_html_x( 'User email', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'MEMBERSHIP_LEVEL'     => array(
				'name'      => esc_html_x( 'Membership level', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'MEMBERSHIP_LEVEL',
				'tokenName' => esc_html_x( 'Membership level', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'USER_REGISTERED_DATE' => array(
				'name'      => esc_html_x( 'User registered date', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'date',
				'tokenId'   => 'USER_REGISTERED_DATE',
				'tokenName' => esc_html_x( 'User registered date', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'USER_LAST_LOGIN_DATE' => array(
				'name'      => esc_html_x( 'User last login date', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'date',
				'tokenId'   => 'USER_LAST_LOGIN_DATE',
				'tokenName' => esc_html_x( 'User last login date', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'USER_IP'              => array(
				'name'      => esc_html_x( 'User IP', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'USER_IP',
				'tokenName' => esc_html_x( 'User IP', 'Thrive Apprentice', 'uncanny-automator' ),
			),
		);
	}
}
