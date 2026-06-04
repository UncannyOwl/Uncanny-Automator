<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class CHARITABLE_CREATE_OFFLINE_DONATION
 *
 * @property \Uncanny_Automator\Integrations\Charitable\Charitable_Helpers $item_helpers
 */
class CHARITABLE_CREATE_OFFLINE_DONATION extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'CHARITABLE' );
		$this->set_action_code( 'CHARITABLE_CREATE_OFFLINE_DONATION' );
		$this->set_action_meta( 'CHARITABLE_CAMPAIGN' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Campaign */
				esc_html_x( 'Create an offline donation to {{a campaign:%1$s}}', 'Charitable', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Create an offline donation to {{a campaign}}', 'Charitable', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'DONATION_ID' => array(
				'name' => esc_html_x( 'Donation ID', 'Charitable', 'uncanny-automator' ),
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

		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Campaign', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'remote_data' => $this->item_helpers->remote_data_load_config( 'campaigns_strict' ),
			),
			array(
				'option_code' => 'DONATION_AMOUNT',
				'label'       => esc_html_x( 'Amount', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'float',
				'required'    => true,
			),
			array(
				'option_code'           => 'DONOR_ID',
				'label'                 => esc_html_x( 'Existing donor', 'Charitable', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'supports_custom_value' => true,
				'description'           => esc_html_x( 'Optional. Leave empty to create the donor from the fields below.', 'Charitable', 'uncanny-automator' ),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'donors_strict' ),
			),
			array(
				'option_code' => 'DONOR_FIRST_NAME',
				'label'       => esc_html_x( 'Donor first name', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'DONOR_LAST_NAME',
				'label'       => esc_html_x( 'Donor last name', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'DONOR_EMAIL',
				'label'       => esc_html_x( 'Donor email', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => false,
			),
			array(
				'option_code' => 'DONATION_NOTE',
				'label'       => esc_html_x( 'Note', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
			),
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

		if ( ! function_exists( 'charitable_create_donation' ) ) {
			$this->add_log_error( 'Charitable is not active.' );
			return false;
		}

		$campaign_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$amount      = (float) ( $parsed['DONATION_AMOUNT'] ?? 0 );
		$donor_id    = absint( $parsed['DONOR_ID'] ?? 0 );
		$first_name  = sanitize_text_field( $parsed['DONOR_FIRST_NAME'] ?? '' );
		$last_name   = sanitize_text_field( $parsed['DONOR_LAST_NAME'] ?? '' );
		$email       = sanitize_email( $parsed['DONOR_EMAIL'] ?? '' );
		$note        = sanitize_textarea_field( $parsed['DONATION_NOTE'] ?? '' );

		// If an existing donor was picked, pull canonical email/name from the donor record
		// so Charitable matches the donation to that donor instead of creating a new one.
		if ( $donor_id > 0 && class_exists( 'Charitable_Donor' ) ) {
			$donor = new \Charitable_Donor( $donor_id );
			if ( $donor && $donor->donor_id ) {
				$email      = sanitize_email( $donor->email );
				$first_name = sanitize_text_field( $donor->first_name );
				$last_name  = sanitize_text_field( $donor->last_name );
			}
		}

		if ( empty( $campaign_id ) || $amount <= 0 || empty( $email ) || ! is_email( $email ) ) {
			$this->add_log_error( 'Missing required donation fields. Select a donor or provide a valid email.' );
			return false;
		}

		$user_data = array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'email'      => $email,
		);

		if ( $donor_id > 0 ) {
			$user_data['donor_id'] = $donor_id;
		}

		$donation_id = charitable_create_donation(
			array(
				'campaigns' => array(
					array(
						'campaign_id' => $campaign_id,
						'amount'      => $amount,
					),
				),
				'user'      => $user_data,
				'gateway'   => 'offline',
				'status'    => 'charitable-completed',
				'note'      => $note,
			)
		);

		if ( empty( $donation_id ) || is_wp_error( $donation_id ) ) {
			$this->add_log_error( is_wp_error( $donation_id ) ? $donation_id->get_error_message() : 'Failed to create donation.' );
			return false;
		}

		$this->hydrate_tokens( array( 'DONATION_ID' => $donation_id ) );

		return true;
	}
}
