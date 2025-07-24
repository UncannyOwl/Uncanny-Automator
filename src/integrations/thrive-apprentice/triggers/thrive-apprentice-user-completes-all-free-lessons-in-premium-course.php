<?php
namespace Uncanny_Automator\Integrations\Thrive_Apprentice;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class THRIVE_APPRENTICE_USER_COMPLETES_ALL_FREE_LESSONS_IN_PREMIUM_COURSE
 *
 * @package Uncanny_Automator
 */
class THRIVE_APPRENTICE_USER_COMPLETES_ALL_FREE_LESSONS_IN_PREMIUM_COURSE extends Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'THRIVE_APPRENTICE_USER_COMPLETES_ALL_FREE_LESSONS_IN_PREMIUM_COURSE';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'THRIVE_APPRENTICE_USER_COMPLETES_ALL_FREE_LESSONS_IN_PREMIUM_COURSE_META';

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

		$this->add_action( 'thrive_apprentice_all_free_lessons_completed' );

		$this->set_action_args_count( 2 );

		$this->set_sentence(
			sprintf(
				// translators:  %1$s: Course
				esc_html_x( 'A user completes all free lessons in {{a premium course:%1$s}}', 'Thrive Apprentice', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user completes all free lessons in {{a premium course}}', 'Thrive Apprentice', 'uncanny-automator' )
		);
	}

	/**
	 * Loads available options for the Trigger.
	 *
	 * @return array The available trigger options.
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Course', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => false,
				'options'               => $this->helper->get_dropdown_options_courses( true, true ),
				'relevant_tokens'       => array(),
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
		list( $user_details, $course_id ) = $hook_args;

		if ( empty( $course_id ) || empty( $user_details ) ) {
			return false;
		}

		$course_id          = absint( $course_id );
		$selected_course_id = $trigger['meta'][ $this->get_trigger_meta() ];

		$this->set_user_id( absint( $user_details['user_id'] ) );

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
		list( $user_details, $course_id ) = $hook_args;

		$course_data = $this->helper->get_course_by_id( $course_id );
		$course_data = wp_remote_retrieve_body( $course_data );
		$course_data = json_decode( $course_data );

		return array(
			'COURSE_ID'            => $course_data->id,
			'COURSE_TITLE'         => $course_data->name,
			'COURSE_URL'           => $course_data->preview_url,
			'COURSE_DESCRIPTION'   => $course_data->excerpt,
			'USER_ID'              => $user_details['user_id'],
			'USER_EMAIL'           => $user_details['email'],
			'MEMBERSHIP_LEVEL'     => $user_details['membership_level'],
			'USER_REGISTERED_DATE' => $user_details['registered'],
			'USER_LAST_LOGIN_DATE' => $user_details['last_logged_in'],
			'USER_IP'              => $user_details['ip_address'],
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
