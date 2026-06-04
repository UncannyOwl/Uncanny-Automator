<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Class EC_CREATE_ORGANIZER
 *
 * Creates a new organizer via Tribe__Events__API::createOrganizer().
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class EC_CREATE_ORGANIZER extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'EC' );
		$this->set_action_code( 'EC_CREATE_ORGANIZER' );
		$this->set_action_meta( 'ORGANIZER_NAME' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );

		/* translators: %1$s is the organizer name field */
		$this->set_sentence( sprintf( esc_html_x( 'Create {{an organizer:%1$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Create {{an organizer}}', 'The Events Calendar', 'uncanny-automator' ) );
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	public function define_tokens() {
		return array(
			'EC_CREATED_ORGANIZER_ID'   => array(
				'name' => esc_html_x( 'Organizer ID', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'int',
			),
			'EC_CREATED_ORGANIZER_NAME' => array(
				'name' => esc_html_x( 'Organizer name', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function options() {

		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Organizer name', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'ORGANIZER_EMAIL',
				'label'       => esc_html_x( 'Email', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => false,
			),
			array(
				'option_code' => 'ORGANIZER_PHONE',
				'label'       => esc_html_x( 'Phone', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'ORGANIZER_WEBSITE',
				'label'       => esc_html_x( 'Website', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'url',
				'required'    => false,
			),
			array(
				'option_code' => 'ORGANIZER_DESCRIPTION',
				'label'       => esc_html_x( 'Description', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( 'Tribe__Events__API' ) ) {
			$this->add_log_error( esc_html_x( 'The Events Calendar API is not available.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		$name = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );

		if ( '' === $name ) {
			$this->add_log_error( esc_html_x( 'Organizer name is required.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		$data = array(
			'Organizer'   => $name,
			'Email'       => sanitize_email( $parsed['ORGANIZER_EMAIL'] ?? '' ),
			'Phone'       => sanitize_text_field( $parsed['ORGANIZER_PHONE'] ?? '' ),
			'Website'     => esc_url_raw( $parsed['ORGANIZER_WEBSITE'] ?? '' ),
			'Description' => wp_kses_post( $parsed['ORGANIZER_DESCRIPTION'] ?? '' ),
		);

		$organizer_id = \Tribe__Events__API::createOrganizer( $data );

		if ( is_wp_error( $organizer_id ) ) {
			$this->add_log_error( $organizer_id->get_error_message() );
			return false;
		}

		if ( empty( $organizer_id ) ) {
			$this->add_log_error( esc_html_x( 'Failed to create organizer.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'EC_CREATED_ORGANIZER_ID'   => (int) $organizer_id,
				'EC_CREATED_ORGANIZER_NAME' => get_the_title( $organizer_id ),
			)
		);

		return true;
	}
}
