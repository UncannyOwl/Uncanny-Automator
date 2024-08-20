<?php

namespace Uncanny_Automator\Integrations\Keap;

use Exception;
use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class KEAP_ADD_NOTE_CONTACT
 *
 * @package Uncanny_Automator
 */
class KEAP_ADD_NOTE_CONTACT extends \Uncanny_Automator\Recipe\Action {

	use Log_Properties;

	/**
	 * Prefix for action code / meta.
	 *
	 * @var string
	 */
	public $prefix = 'KEAP_ADD_NOTE_CONTACT';

	/**
	 * Set up action.
	 *
	 * @return void
	 */
	public function setup_action() {

		/** @var \Uncanny_Automator\Integrations\Keap\Keap_Helpers $helper */
		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'KEAP' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/keap/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s Note type, Contact Email, %2$s
				esc_attr_x( 'Add {{a note:%1$s}} to {{a contact:%2$s}}', 'Keap', 'uncanny-automator' ),
				'NOTE_TYPE:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add {{a note}} to {{a contact}}', 'Keap', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			// Email.
			$this->helpers->get_email_field_config( $this->get_action_meta() ),
			// Title.
			array(
				'option_code' => 'NOTE_TITLE',
				'label'       => esc_attr__( 'Title', 'uncanny-automator' ),
				'input_type'  => 'text',
				'description' => esc_attr__( 'Enter the title of the note. ( optional )', 'uncanny-automator' ),
			),
			// Content.
			array(
				'option_code' => 'NOTE_BODY',
				'label'       => esc_attr__( 'Body', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'description' => esc_attr__( 'Enter the content of the note.', 'uncanny-automator' ),
				'required'    => true,
			),
			// Type.
			array(
				'option_code' => 'NOTE_TYPE',
				'label'       => esc_attr__( 'Type', 'uncanny-automator' ),
				'input_type'  => 'select',
				'options'     => $this->get_note_types(),
				'description' => esc_attr__( 'Select the type of the note.', 'uncanny-automator' ),
				'required'    => true,
			),
			// Account User ID.
			array(
				'option_code' => 'ACCOUNT_USER_ID',
				'label'       => esc_attr__( 'Keap account user', 'uncanny-automator' ),
				'input_type'  => 'select',
				'options'     => array(),
				'description' => esc_attr__( 'Select the Keap account user to assign the note to. If using a custom value emails are excepted.', 'uncanny-automator' ),
				'required'    => true,
				'ajax'        => array(
					'endpoint' => 'automator_keap_get_account_users',
					'event'    => 'on_load',
				),
			),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {

		$tokens                       = $this->helpers->define_contact_action_tokens();
		$tokens['NOTE_ID']            = array(
			'name' => esc_attr_x( 'Note ID', 'Keap', 'uncanny-automator' ),
			'type' => 'int',
		);
		$tokens['ACCOUNT_USER_EMAIL'] = array(
			'name' => esc_attr_x( 'Keap account user email', 'Keap', 'uncanny-automator' ),
			'type' => 'string',
		);

		$tokens['ACCOUNT_USER_FIRST_NAME'] = array(
			'name' => esc_attr_x( 'Keap account user first name', 'Keap', 'uncanny-automator' ),
			'type' => 'string',
		);

		$tokens['ACCOUNT_USER_LAST_NAME'] = array(
			'name' => esc_attr_x( 'Keap account user last name', 'Keap', 'uncanny-automator' ),
			'type' => 'string',
		);

		return $tokens;
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

		// Required fields - throws error if not set and valid.
		$email = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );

		// Build variables for request data.
		$title = $this->get_parsed_meta_value( 'NOTE_TITLE', false );
		$title = sanitize_text_field( $title );
		$text  = $this->get_parsed_meta_value( 'NOTE_BODY', false );
		$text  = sanitize_textarea_field( $text );
		$type  = $this->get_parsed_meta_value( 'NOTE_TYPE', false );
		$type  = sanitize_text_field( $type );

		// Validate we have a message.
		if ( empty( $text ) ) {
			throw new \Exception( esc_html_x( 'Note body is required.', 'Keap', 'uncanny-automator' ) );
		}

		// Validate we have a title or type.
		if ( empty( $title ) && empty( $type ) ) {
			throw new \Exception( esc_html_x( 'Either a title or type is required.', 'Keap', 'uncanny-automator' ) );
		}

		// Validate Account User ID.
		$account_user_id   = $this->get_parsed_meta_value( 'ACCOUNT_USER_ID', false );
		$validated_user_id = $this->helpers->get_valid_account_user_selection( $account_user_id );
		if ( is_wp_error( $validated_user_id ) ) {
			throw new \Exception( $validated_user_id->get_error_message() );
		}

		$note = array(
			'title'   => $title,
			'text'    => $text,
			'type'    => $type,
			'user_id' => $validated_user_id,
		);

		// Send request.
		$response = $this->helpers->api_request(
			'add_note_to_contact',
			array(
				'email' => $email,
				'note'  => wp_json_encode( $note ),
			),
			$action_data
		);

		// Hydrate tokens.
		$results = $response['data']['results'] ?? array();
		$contact = $response['data']['contact'] ?? array();
		$tokens  = $this->helpers->hydrate_contact_tokens( $contact );

		// Add note specific tokens.
		$tokens['ACCOUNT_USER_FIRST_NAME'] = $results['assigned_to_user']['given_name'] ?? '';
		$tokens['ACCOUNT_USER_LAST_NAME']  = $results['assigned_to_user']['family_name'] ?? '';
		$tokens['ACCOUNT_USER_EMAIL']      = $results['assigned_to_user']['email_address'] ?? '';
		$tokens['NOTE_ID']                 = $results['id'] ?? 0;

		$this->hydrate_tokens( $tokens );

		return true;
	}

	/**
	 * Get note types.
	 *
	 * @return array
	 */
	private function get_note_types() {
		return array(
			array(
				'value' => 'Appointment',
				'text'  => _x( 'Appointment', 'Keap', 'uncanny-automator' ),
			),
			array(
				'value' => 'Call',
				'text'  => _x( 'Call', 'Keap', 'uncanny-automator' ),
			),
			array(
				'value' => 'Email',
				'text'  => _x( 'Email', 'Keap', 'uncanny-automator' ),
			),
			array(
				'value' => 'Fax',
				'text'  => _x( 'Fax', 'Keap', 'uncanny-automator' ),
			),
			array(
				'value' => 'Letter',
				'text'  => _x( 'Letter', 'Keap', 'uncanny-automator' ),
			),
			array(
				'value' => 'Other',
				'text'  => _x( 'Other', 'Keap', 'uncanny-automator' ),
			),
		);
	}

}
