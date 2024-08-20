<?php

namespace Uncanny_Automator\Integrations\Ontraport;

use Exception;

/**
 * Class Ontraport_Upsert_Contact
 *
 * @package Uncanny_Automator
 */
class Ontraport_Upsert_Contact extends \Uncanny_Automator\Recipe\Action {

	public $prefix = 'ONTRAPORT_UPSERT_CONTACT';

	/**
	 * Spins up new action inside "ONTRAPORT" integration.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'ONTRAPORT' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/ontraport/' ) );
		$this->set_requires_user( false );

		$sentence = sprintf(
			/* translators: Action sentence */
			esc_attr_x( 'Create or update {{a contact:%1$s}}', 'Ontraport', 'uncanny-automator' ),
			$this->get_action_meta()
		);

		$this->set_sentence( $sentence );
		$this->set_readable_sentence( esc_attr_x( 'Create or update {{a contact}}', 'Ontraport', 'uncanny-automator' ) );
		$this->set_background_processing( true );

	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$status_options = array(
			array(
				'text'  => _x( 'Closed - Lost', 'Ontraport', 'uncanny-automator' ),
				'value' => '1',
			),
			array(
				'text'  => _x( 'Closed - Won', 'Ontraport', 'uncanny-automator' ),
				'value' => '2',
			),
			array(
				'text'  => _x( 'Committed', 'Ontraport', 'uncanny-automator' ),
				'value' => '3',
			),
			array(
				'text'  => _x( 'Consideration', 'Ontraport', 'uncanny-automator' ),
				'value' => '4',
			),
			array(
				'text'  => _x( 'Demo Scheduled', 'Ontraport', 'uncanny-automator' ),
				'value' => '5',
			),
			array(
				'text'  => _x( 'Qualified Lead', 'Ontraport', 'uncanny-automator' ),
				'value' => '6',
			),
			array(
				'text'  => _x( 'New Prospect', 'Ontraport', 'uncanny-automator' ),
				'value' => '7',
			),
		);

		$email = array(
			'option_code' => $this->get_action_meta(),
			'label'       => _x( 'Email', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => true,
		);

		$first_name = array(
			'option_code' => 'FIRST_NAME',
			'label'       => _x( 'First name', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
		);

		$last_name = array(
			'option_code' => 'LAST_NAME',
			'label'       => _x( 'Last name', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
		);

		$address = array(
			'option_code' => 'ADDRESS',
			'label'       => _x( 'Address', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
		);

		$sms_number = array(
			'option_code' => 'SMS_NUMBER',
			'label'       => _x( 'SMS Number', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
		);

		$status = array(
			'option_code'         => 'STATUS',
			'label'               => _x( 'Status', 'Ontraport', 'uncanny-automator' ),
			'input_type'          => 'select',
			'required'            => false,
			'options'             => $status_options,
			'allow_custom_values' => true,
		);

		$facebook_link = array(
			'option_code' => 'FACEBOOK_LINK',
			'label'       => _x( 'Facebook link', 'Ontraport', 'uncanny-automator' ),
			'placeholder' => _x( 'https://', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'url',
			'required'    => false,
		);

		$instagram_link = array(
			'option_code' => 'INSTAGRAM_LINK',
			'label'       => _x( 'Instagram link', 'Ontraport', 'uncanny-automator' ),
			'placeholder' => _x( 'https://', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'url',
			'required'    => false,
		);

		$linkedin_link = array(
			'option_code' => 'LINKEDIN_LINK',
			'label'       => _x( 'LinkedIn link', 'Ontraport', 'uncanny-automator' ),
			'placeholder' => _x( 'https://', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'url',
			'required'    => false,
		);

		$twitter_link = array(
			'option_code' => 'TWITTER_LINK',
			'label'       => _x( 'Twitter link', 'Ontraport', 'uncanny-automator' ),
			'placeholder' => _x( 'https://', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'url',
			'required'    => false,
		);

		$fields = array( $email, $first_name, $last_name, $address, $sms_number, $status, $facebook_link, $instagram_link, $linkedin_link, $twitter_link );

		return apply_filters( 'automator_ontraport_upsert_fields', $fields, $this );

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
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email          = $this->get_parsed_meta_value( $this->get_action_meta(), '' );
		$first_name     = $this->get_parsed_meta_value( 'FIRST_NAME', '' );
		$last_name      = $this->get_parsed_meta_value( 'LAST_NAME', '' );
		$last_name      = $this->get_parsed_meta_value( 'LAST_NAME', '' );
		$address        = $this->get_parsed_meta_value( 'ADDRESS', '' );
		$sms_number     = $this->get_parsed_meta_value( 'SMS_NUMBER', '' );
		$status         = $this->get_parsed_meta_value( 'STATUS', '' );
		$facebook_link  = $this->get_parsed_meta_value( 'FACEBOOK_LINK', '' );
		$instagram_link = $this->get_parsed_meta_value( 'INSTAGRAM_LINK', '' );
		$linkedin_link  = $this->get_parsed_meta_value( 'LINKEDIN_LINK', '' );
		$twitter_link   = $this->get_parsed_meta_value( 'TWITTER_LINK', '' );

		$fields = array(
			'firstname'      => $first_name,
			'lastname'       => $last_name,
			'email'          => $email,
			'address'        => $address,
			'sms_number'     => $sms_number,
			'status'         => $status,
			'facebook_link'  => $facebook_link,
			'instagram_link' => $instagram_link,
			'linkedin_link'  => $linkedin_link,
			'twitter_link'   => $twitter_link,
		);

		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception( "Invalid email provided: {$email}", 400 );
		}

		$body = array(
			'fields' => wp_json_encode( $fields ),
		);

		$body = apply_filters(
			'uncanny_automator_ontraport_fields',
			$body,
			array(
				$action_data,
				$recipe_id,
				$args,
				$parsed,
			)
		);

		$this->helpers->api_request( 'contact_upsert', $body, $action_data );

	}

}
