<?php
namespace Uncanny_Automator\Integrations\Thrive_Apprentice;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class THRIVE_APPRENTICE_CONTENT_UNLOCKED
 *
 * @package Uncanny_Automator
 */
class THRIVE_APPRENTICE_CONTENT_UNLOCKED extends Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'THRIVE_APPRENTICE_CONTENT_UNLOCKED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'THRIVE_APPRENTICE_CONTENT_UNLOCKED_META';

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

		// Hook into content unlock event
		$this->add_action( 'tva_drip_content_unlocked_for_specific_user' );

		$this->set_action_args_count( 3 );

		/* translators: %1$s: Content type */
		$this->set_sentence(
			sprintf(
				// translators:  %1$s: Content,  %2$s: Course
				esc_html_x( '{{Content:%1$s}} is unlocked for a user in {{a course:%2$s}}', 'Thrive Apprentice', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'COURSE:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( '{{Content}} is unlocked for a user', 'Thrive Apprentice', 'uncanny-automator' )
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
				'option_code'     => 'COURSE',
				'label'           => esc_html_x( 'Course', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->helper->get_dropdown_options_courses( true, true ),
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => 'CONTENT_TYPE',
				'label'           => esc_html_x( 'Content type', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'ajax'            => array(
					'event'         => 'parent_fields_change',
					'endpoint'      => 'automator_thrive_apprentice_content_type_handler',
					'listen_fields' => array( 'COURSE' ),
				),
			),
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Content', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'ajax'            => array(
					'event'         => 'parent_fields_change',
					'endpoint'      => 'automator_thrive_apprentice_content_handler',
					'listen_fields' => array( 'CONTENT_TYPE' ),
				),
			),
		);
	}

	/**
	 * Get content data and course information
	 *
	 * @param object $content The content object
	 * @return array|WP_Error Array of content and course data or WP_Error on failure
	 */
	private function get_content_and_course_data( $content ) {
		if ( empty( $content ) || ! isset( $content->post_type ) || ! isset( $content->ID ) ) {
			return new \WP_Error( 'invalid_content', 'Invalid content object' );
		}

		$content_data = null;
		switch ( $content->post_type ) {
			case 'tva_lesson':
				$content_data = new \TVA_Lesson( $content->ID );
				break;
			case 'tva_module':
				$content_data = new \TVA_Module( $content->ID );
				break;
			case 'tva_chapter':
				$content_data = new \TVA_Chapter( $content->ID );
				break;
			case 'tva_assessment':
				$content_data = new \TVA_Assessment( $content->ID );
				break;
			case 'tva_product':
				$content_data = new \TVA_Product( $content->ID );
				break;
			default:
				return new \WP_Error( 'invalid_content_type', 'Invalid content type' );
		}

		if ( is_null( $content_data ) ) {
			return new \WP_Error( 'content_data_error', 'Failed to load content data' );
		}

		$course = $content_data->get_course_v2();
		if ( ! $course ) {
			return new \WP_Error( 'course_error', 'Failed to load course data' );
		}

		$course_details = $course->get_details();
		if ( empty( $course_details ) ) {
			return new \WP_Error( 'course_details_error', 'Failed to load course details' );
		}

		return array(
			'content_data'   => $content_data,
			'course'         => $course,
			'course_details' => $course_details,
			'course_id'      => absint( $course_details['course_id'] ),
			'content_type'   => $content->post_type,
			'content_id'     => $content->ID,
			'content_title'  => $content->post_title,
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
		list( $user, $content, $tva_product ) = $hook_args;

		if ( empty( $content ) || empty( $user ) ) {
			return false;
		}

		$selected_content_id   = $trigger['meta'][ $this->get_trigger_meta() ];
		$selected_course_id    = $trigger['meta']['COURSE'];
		$selected_content_type = sanitize_text_field( $trigger['meta']['CONTENT_TYPE'] );

		$user_id = $user instanceof \WP_User ? $user->ID : 0;
		$this->set_user_id( $user_id );

		// If all fields are set to "Any" (-1), return true
		if ( -3 === array_sum( array( intval( $selected_course_id ), intval( $selected_content_type ), intval( $selected_content_id ) ) ) ) {
			return true;
		}

		// If "Any" course is selected, return true
		if ( intval( '-1' ) === intval( $selected_course_id ) ) {
			return true;
		}

		$data = $this->get_content_and_course_data( $content );

		if ( is_wp_error( $data ) ) {
			return false;
		}

		// First check: Course validation
		if ( intval( '-1' ) !== intval( $selected_course_id ) && absint( $selected_course_id ) !== $data['course_id'] ) {
			return false;
		}

		// Second check: Content type validation
		if ( intval( '-1' ) !== intval( $selected_content_type ) && $selected_content_type !== $data['content_type'] ) {
			return false;
		}

		// Third check: Specific content validation
		if ( intval( '-1' ) !== intval( $selected_content_id ) && absint( $selected_content_id ) !== $data['content_id'] ) {
			return false;
		}

		// If we get here, all conditions are met
		return true;
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger The trigger configuration.
	 * @param array $hook_args The hook arguments.
	 *
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $user, $content, $tva_product ) = $hook_args;

		$data = $this->get_content_and_course_data( $content );
		if ( is_wp_error( $data ) ) {
			return array();
		}

		return array(
			'COURSE_ID'     => $data['course_id'],
			'COURSE_TITLE'  => $data['course_details']['course_title'],
			'CONTENT_TYPE'  => ucfirst( str_replace( 'tva_', '', $data['content_type'] ) ),
			'CONTENT_TITLE' => $data['content_title'],
			'CONTENT_ID'    => $data['content_id'],
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
			'COURSE_ID'     => array(
				'name'      => esc_html_x( 'Course ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'COURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'COURSE_TITLE'  => array(
				'name'      => esc_html_x( 'Course title', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'COURSE_TITLE',
				'tokenName' => esc_html_x( 'Course title', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'CONTENT_TYPE'  => array(
				'name'      => esc_html_x( 'Content type', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'CONTENT_TYPE',
				'tokenName' => esc_html_x( 'Content type', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'CONTENT_TITLE' => array(
				'name'      => esc_html_x( 'Content title', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'CONTENT_TITLE',
				'tokenName' => esc_html_x( 'Content title', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'CONTENT_ID'    => array(
				'name'      => esc_html_x( 'Content ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'CONTENT_ID',
				'tokenName' => esc_html_x( 'Content ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
		);
	}
}
