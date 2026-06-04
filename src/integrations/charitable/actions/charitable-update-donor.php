<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class CHARITABLE_UPDATE_DONOR
 *
 * @property \Uncanny_Automator\Integrations\Charitable\Charitable_Helpers $item_helpers
 */
class CHARITABLE_UPDATE_DONOR extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'CHARITABLE' );
		$this->set_action_code( 'CHARITABLE_UPDATE_DONOR' );
		$this->set_action_meta( 'DONOR_ID' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Donor ID */
				esc_html_x( "Update {{a donor's:%1\$s}} profile", 'Charitable', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( "Update {{a donor's}} profile", 'Charitable', 'uncanny-automator' ) );
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
			array(
				array(
					'option_code' => $this->get_action_meta(),
					'label'       => esc_html_x( 'Donor ID', 'Charitable', 'uncanny-automator' ),
					'input_type'  => 'int',
					'required'    => true,
				),
			),
			Donor_Fields::regular_fields( 'DONOR_EMAIL', false, $this->item_helpers ),
			Donor_Fields::address_fields(),
			array( Donor_Fields::custom_fields_repeater() )
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

		if ( ! class_exists( 'Charitable_Donor' ) || ! function_exists( 'charitable_get_table' ) ) {
			$this->add_log_error( 'Charitable is not active.' );
			return false;
		}

		$donor_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		if ( empty( $donor_id ) ) {
			$this->add_log_error( 'Donor ID is required.' );
			return false;
		}

		// Verify the donor row actually exists — mirrors Charitable_Donors::ajax_donor_update().
		// `$donor->donor_id` only reflects the constructor argument, not DB presence.
		$donor_row = charitable_get_table( 'donors' )->get_by( 'donor_id', $donor_id );
		if ( ! $donor_row ) {
			$this->add_log_error( sprintf( 'Donor %d not found.', $donor_id ) );
			return false;
		}

		$donor = new \Charitable_Donor( $donor_id );

		if ( ! method_exists( $donor, 'update' ) ) {
			$this->add_log_error( 'Charitable_Donor::update() is not available. Charitable Pro may be required.' );
			return false;
		}

		Donor_Fields::apply_profile_fields( $donor, $parsed );
		Donor_Fields::apply_address( $donor, $parsed );
		Donor_Fields::apply_custom_fields( $donor, $donor_id, $action_data, $recipe_id, $user_id, $args );

		$this->hydrate_tokens( array( 'DONOR_ID' => $donor_id ) );

		return true;
	}
}
