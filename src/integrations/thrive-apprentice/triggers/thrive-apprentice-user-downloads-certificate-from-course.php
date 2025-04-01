<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class THRIVE_APPRENTICE_USER_DOWNLOADS_CERTIFICATE_FROM_COURSE
 *
 * Handles the trigger for when a user downloads a certificate from a Thrive Apprentice course
 *
 * @package Uncanny_Automator
 * @author Uncanny Automator
 */
class THRIVE_APPRENTICE_USER_DOWNLOADS_CERTIFICATE_FROM_COURSE extends Trigger {

	/**
	 * Trigger code constant
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'THRIVE_APPRENTICE_USER_DOWNLOADS_CERTIFICATE_FROM_COURSE';

	/**
	 * Trigger meta constant
	 *
	 * @var string
	 */
	const TRIGGER_META = 'THRIVE_APPRENTICE_USER_DOWNLOADS_CERTIFICATE_FROM_COURSE_META';

	/**
	 * Helper instance
	 *
	 * @var Thrive_Apprentice_Helpers
	 */
	protected $helper;

	/**
	 * Setup trigger configurations
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->helper = new Thrive_Apprentice_Helpers( false );

		$this->set_integration( 'THRIVE_APPRENTICE' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_is_pro( false );

		$this->add_action( 'tva_certificate_downloaded' );

		$this->set_action_args_count( 3 );

		$this->set_sentence(
			sprintf(
				// translators:  %1$s: Course
				esc_html_x( 'A user downloads a certificate from {{a course:%1$s}}', 'Thrive Apprentice', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user downloads a certificate from {{a course}}', 'Thrive Apprentice', 'uncanny-automator' )
		);
	}

	/**
	 * Define available options for the trigger
	 *
	 * @return array The available trigger options
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
	 * Validate if the trigger conditions are met
	 *
	 * @param array $trigger    The trigger configuration
	 * @param array $hook_args  The arguments passed to the hook
	 * @return bool True if validation was successful
	 */
	public function validate( $trigger, $hook_args ) {
		if ( empty( $hook_args ) ) {
			return false;
		}

		list( $certificate, $user, $course ) = $hook_args;

		if ( empty( $course ) || empty( $user ) || empty( $certificate ) ) {
			return false;
		}

		$course_id          = absint( $course['course_id'] );
		$user_id            = absint( $user['user_id'] );
		$selected_course_id = $trigger['meta'][ $this->get_trigger_meta() ];

		$this->set_user_id( $user_id );

		return intval( '-1' ) === intval( $selected_course_id ) || (int) $selected_course_id === (int) $course_id;
	}

	/**
	 * Populate token values from trigger data
	 *
	 * @param array $trigger    The trigger configuration
	 * @param array $hook_args  The arguments passed to the hook
	 * @return array The token values
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		if ( empty( $hook_args ) ) {
			return array();
		}

		list( $certificate, $user_data, $course_data ) = $hook_args;

		return array(
			'COURSE_ID'          => isset( $course_data['course_id'] ) ? absint( $course_data['course_id'] ) : '',
			'COURSE_TITLE'       => isset( $course_data['course_name'] ) ? sanitize_text_field( $course_data['course_name'] ) : '',
			'CERTIFICATE_NUMBER' => isset( $certificate['certificate_number'] ) ? sanitize_text_field( $certificate['certificate_number'] ) : '',
			'CERTIFICATE_URL'    => isset( $certificate['certificate_url'] ) ? esc_url( $certificate['certificate_url'] ) : '',
			// Get current site time in formatted string (date + time)
			'DOWNLOAD_DATE'      => current_datetime()->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'USER_ID'            => isset( $user_data['user_id'] ) ? absint( $user_data['user_id'] ) : '',
			'USER_EMAIL'         => isset( $user_data['user_email'] ) ? sanitize_email( $user_data['user_email'] ) : '',
		);
	}

	/**
	 * Define available tokens for the trigger
	 *
	 * @param array $trigger  The trigger configuration
	 * @param array $tokens   The existing tokens
	 * @return array The defined tokens
	 */
	public function define_tokens( $trigger, $tokens ) {
		$additional_tokens = array(
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
			'USER_EMAIL'         => array(
				'name'      => esc_html_x( 'User email', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'email',
				'tokenId'   => 'USER_EMAIL',
				'tokenName' => esc_html_x( 'User email', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'USER_ID'            => array(
				'name'      => esc_html_x( 'User ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'USER_ID',
				'tokenName' => esc_html_x( 'User ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'CERTIFICATE_NUMBER' => array(
				'name'      => esc_html_x( 'Certificate number', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'CERTIFICATE_NUMBER',
				'tokenName' => esc_html_x( 'Certificate number', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'DOWNLOAD_DATE'      => array(
				'name'      => esc_html_x( 'Download date', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'date',
				'tokenId'   => 'DOWNLOAD_DATE',
				'tokenName' => esc_html_x( 'Download date', 'Thrive Apprentice', 'uncanny-automator' ),
			),
		);

		return array_merge( $tokens, $additional_tokens );
	}
}
