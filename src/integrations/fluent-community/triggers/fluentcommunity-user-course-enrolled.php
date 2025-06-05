<?php
namespace Uncanny_Automator\Integrations\Fluent_Community;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class FLUENTCOMMUNITY_USER_ENROLLED_COURSE
 *
 * @package Uncanny_Automator
 */
class FLUENTCOMMUNITY_USER_ENROLLED_COURSE extends Trigger {

	/**
	 * The prefix for the trigger
	 *
	 * @var string
	 */
	protected $prefix = 'FLUENTCOMMUNITY_USER_ENROLLED_COURSE';

	/**
	 * The helpers for the trigger
	 *
	 * @var array
	 */
	protected $helpers;

	/**
	 * Setup the trigger
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'FLUENT_COMMUNITY' );
		$this->set_trigger_code( $this->prefix . '_CODE' );
		$this->set_trigger_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );

		$this->add_action( 'fluent_community/course/enrolled' );
		$this->set_action_args_count( 3 );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s - Course */
				esc_html_x( 'A user is enrolled in {{a course:%1$s}}', 'FluentCommunity', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user is enrolled in {{a course}}', 'FluentCommunity', 'uncanny-automator' )
		);
	}

	/**
	 * Define the options for the trigger
	 *
	 * @return array The options for the trigger.
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Course', 'FluentCommunity', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->helpers->all_courses( true ),
				'relevant_tokens'       => array(),
				'supports_custom_value' => false,
			),
		);
	}

	/**
	 * Validate.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $hook_args The arguments.
	 * @return mixed
	 */
	public function validate( $trigger, $hook_args ) {
		$course  = isset( $hook_args[0] ) && is_object( $hook_args[0] ) ? $hook_args[0] : null;
		$user_id = isset( $hook_args[1] ) ? absint( $hook_args[1] ) : null;

		if ( empty( $user_id ) || empty( $course->id ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		$selected = isset( $trigger['meta'][ $this->get_trigger_meta() ] )
			? (int) $trigger['meta'][ $this->get_trigger_meta() ]
			: -1;

		return ( intval( '-1' ) === intval( $selected ) || absint( $course->id ) === $selected );
	}


	/**
	 * Hydrate tokens.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $hook_args The arguments.
	 * @return mixed
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$course = isset( $hook_args[0] ) ? $hook_args[0] : null;

		if ( ! is_object( $course ) || empty( $course->id ) ) {
			return array();
		}

		return array(
			'COURSE_ID'    => $course->id,
			'COURSE_TITLE' => isset( $course->title ) ? $course->title : '',
		);
	}
	/**
	 * Define tokens.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $tokens The destination.
	 * @return mixed
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			'COURSE_ID'    => array(
				'name'      => esc_html_x( 'Course ID', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'COURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'FluentCommunity', 'uncanny-automator' ),
			),
			'COURSE_TITLE' => array(
				'name'      => esc_html_x( 'Course title', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'COURSE_TITLE',
				'tokenName' => esc_html_x( 'Course title', 'FluentCommunity', 'uncanny-automator' ),
			),
		);
	}
}
