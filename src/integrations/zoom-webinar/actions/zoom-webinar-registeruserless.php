<?php

namespace Uncanny_Automator\Integrations\Zoom_Webinar;

use Uncanny_Automator\Recipe\App_Action;
use Exception;

/**
 * Class ZOOM_WEBINAR_REGISTERUSERLESS
 *
 * @package Uncanny_Automator
 * @property Zoom_Webinar_App_Helpers $helpers
 * @property Zoom_Webinar_Api_Caller $api
 */
class ZOOM_WEBINAR_REGISTERUSERLESS extends App_Action {

	use Zoom_Webinar_Registration_Trait;

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'ZOOMWEBINAR' );
		$this->set_action_code( 'ZOOMWEBINARREGISTERUSERLESS' );
		$this->set_action_meta( 'ZOOMWEBINAR' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		// translators: %1$s Webinar topic
		$this->set_sentence( sprintf( esc_html_x( 'Add an attendee to {{a webinar:%1$s}}', 'Zoom Webinar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Add an attendee to {{a webinar}}', 'Zoom Webinar', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_email_field(),
			array(
				'option_code' => 'FIRSTNAME',
				'input_type'  => 'text',
				'label'       => esc_attr_x( 'First name', 'Zoom Webinar', 'uncanny-automator' ),
				'placeholder' => '',
				'description' => '',
				'required'    => false,
				'tokens'      => true,
				'default'     => '',
			),
			array(
				'option_code' => 'LASTNAME',
				'input_type'  => 'text',
				'label'       => esc_attr_x( 'Last name', 'Zoom Webinar', 'uncanny-automator' ),
				'placeholder' => '',
				'description' => '',
				'required'    => false,
				'tokens'      => true,
				'default'     => '',
			),
			$this->get_account_user_field( 'ZOOMUSER' ),
			$this->get_webinar_selection_field( $this->get_action_meta() ),
			$this->get_webinar_occurrences_field( $this->get_action_meta() ),
			$this->get_webinar_questions_repeater( $this->get_action_meta() ),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$webinar_key = $this->get_parsed_meta_value( $this->get_action_meta() );

		if ( empty( $webinar_key ) ) {
			throw new Exception( esc_html_x( 'Webinar was not found.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		$webinar_key  = $this->parse_webinar_key( $webinar_key );
		$webinar_user = $this->parse_user_data_from_fields( $action_data );

		$webinar_questions = $this->get_parsed_meta_value( 'WEBINARQUESTIONS' );
		if ( ! empty( $webinar_questions ) ) {
			$webinar_user = $this->parse_webinar_questions( $webinar_user, $webinar_questions, $recipe_id, $user_id, $args );
		}

		$occurrences         = $this->get_parsed_meta_value( 'OCCURRENCES' );
		$webinar_occurrences = $this->parse_webinar_occurrences( $occurrences );

		$this->api->register_user_for_webinar( $webinar_user, $webinar_key, $webinar_occurrences, $action_data );

		return true;
	}
}
