<?php
namespace Uncanny_Automator\Integrations\Thrive_Apprentice;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class THRIVE_APPRENTICE_USER_CERTIFICATE_VERIFIED
 *
 * @package Uncanny_Automator
 */
class THRIVE_APPRENTICE_USER_CERTIFICATE_VERIFIED extends Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'THRIVE_APPRENTICE_USER_CERTIFICATE_VERIFIED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'THRIVE_APPRENTICE_USER_CERTIFICATE_VERIFIED_META';

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
		$this->set_trigger_type( 'user' );

		// Hook into certificate verification event
		$this->add_action( 'tva_certificate_verified' );

		$this->set_action_args_count( 3 );

		/* translators: %1$s: Course title */
		$this->set_sentence(
			sprintf(
				// translators:  %1$s: Course
				esc_html_x( "A user's certificate for {{a course:%1\$s}} is verified", 'Thrive Apprentice', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( "A user's certificate for {{a course}} is verified", 'Thrive Apprentice', 'uncanny-automator' )
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
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Course', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->helper->get_dropdown_options_courses( true, true ),
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
		list( $certificate_data, $user_data, $course_data ) = $hook_args;

		if ( empty( $certificate_data ) ) {
			return false;
		}

		$course_id          = absint( $course_data['course_id'] );
		$selected_course_id = $trigger['meta'][ $this->get_trigger_meta() ];

		$this->set_user_id( $user_data['user_id'] );
		// Match if any course is selected (-1) or if specific course matches
		return intval( '-1' ) === intval( $selected_course_id ) || absint( $course_id ) === absint( $selected_course_id );
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
		list( $certificate_data, $user_data, $course_data ) = $hook_args;

		return array(
			'USER_ID'            => $user_data['user_id'],
			'USER_EMAIL'         => $user_data['user_email'],
			'COURSE_ID'          => $course_data['course_id'],
			'COURSE_TITLE'       => $course_data['course_name'],
			'CERTIFICATE_NUMBER' => $certificate_data['certificate_number'],
			// Get current site time in formatted string (date + time)
			'VERIFICATION_DATE'  => current_datetime()->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
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
			'USER_ID'            => array(
				'name'      => esc_html_x( 'User ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'USER_ID',
				'tokenName' => esc_html_x( 'User ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'USER_EMAIL'         => array(
				'name'      => esc_html_x( 'User email', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'email',
				'tokenId'   => 'USER_EMAIL',
				'tokenName' => esc_html_x( 'User email', 'Thrive Apprentice', 'uncanny-automator' ),
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
			'CERTIFICATE_NUMBER' => array(
				'name'      => esc_html_x( 'Certificate number', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'CERTIFICATE_NUMBER',
				'tokenName' => esc_html_x( 'Certificate number', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'VERIFICATION_DATE'  => array(
				'name'      => esc_html_x( 'Verification date', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'date',
				'tokenId'   => 'VERIFICATION_DATE',
				'tokenName' => esc_html_x( 'Verification date', 'Thrive Apprentice', 'uncanny-automator' ),
			),
		);
	}
}
