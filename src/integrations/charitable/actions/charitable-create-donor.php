<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class CHARITABLE_CREATE_DONOR
 *
 * @property \Uncanny_Automator\Integrations\Charitable\Charitable_Helpers $item_helpers
 */
class CHARITABLE_CREATE_DONOR extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'CHARITABLE' );
		$this->set_action_code( 'CHARITABLE_CREATE_DONOR' );
		$this->set_action_meta( 'DONOR_EMAIL' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Donor email */
				esc_html_x( 'Create {{a donor:%1$s}}', 'Charitable', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Create {{a donor}}', 'Charitable', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'DONOR_ID' => array(
				'name' => esc_html_x( 'Donor ID', 'Charitable', 'uncanny-automator' ),
				'type' => 'int',
			),
		);
	}

	/**
	 * Action options.
	 *
	 * @return array
	 */
	public function options() {
		return array_merge(
			Donor_Fields::regular_fields( $this->get_action_meta(), true, $this->item_helpers ),
			Donor_Fields::address_fields(),
			array(
				array(
					'option_code' => 'DONOR_USER_ID',
					'label'       => esc_html_x( 'WP user ID to link (optional)', 'Charitable', 'uncanny-automator' ),
					'input_type'  => 'int',
					'required'    => false,
				),
				Donor_Fields::custom_fields_repeater(),
			)
		);
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( 'Charitable_Donor' ) || ! method_exists( 'Charitable_Donor', 'create' ) ) {
			$this->add_log_error( 'Charitable_Donor::create() is not available. Charitable Pro may be required.' );
			return false;
		}

		$email = sanitize_email( $parsed[ $this->get_action_meta() ] ?? '' );
		if ( empty( $email ) || ! is_email( $email ) ) {
			$this->add_log_error( 'A valid email is required.' );
			return false;
		}

		$first_name = sanitize_text_field( $parsed['DONOR_FIRST_NAME'] ?? '' );
		if ( empty( $first_name ) ) {
			$this->add_log_error( 'First name is required.' );
			return false;
		}

		$create_data = array(
			'email'      => $email,
			'first_name' => $first_name,
			'last_name'  => sanitize_text_field( $parsed['DONOR_LAST_NAME'] ?? '' ),
		);

		$linked_user_id = absint( $parsed['DONOR_USER_ID'] ?? 0 );
		if ( ! empty( $linked_user_id ) ) {
			$create_data['user_id'] = $linked_user_id;
		}

		$donor    = new \Charitable_Donor();
		$donor_id = $donor->create( $create_data );

		if ( empty( $donor_id ) || is_wp_error( $donor_id ) ) {
			$this->add_log_error( is_wp_error( $donor_id ) ? $donor_id->get_error_message() : 'Failed to create donor.' );
			return false;
		}

		// Re-instantiate so the donor object is bound to the new ID for follow-up updates.
		$donor = new \Charitable_Donor( $donor_id );

		Donor_Fields::apply_profile_fields( $donor, $parsed );
		Donor_Fields::apply_address( $donor, $parsed );
		Donor_Fields::apply_custom_fields( $donor, $donor_id, $action_data, $recipe_id, $user_id, $args );

		$this->hydrate_tokens( array( 'DONOR_ID' => $donor_id ) );

		return true;
	}
}
