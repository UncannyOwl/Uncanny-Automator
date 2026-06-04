<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class CHARITABLE_UPDATE_DONATION_STATUS
 *
 * @property \Uncanny_Automator\Integrations\Charitable\Charitable_Helpers $item_helpers
 */
class CHARITABLE_UPDATE_DONATION_STATUS extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'CHARITABLE' );
		$this->set_action_code( 'CHARITABLE_UPDATE_DONATION_STATUS' );
		$this->set_action_meta( 'CHARITABLE_DONATION_ID' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Donation ID 2: Status */
				esc_html_x( "Update {{a donation's:%1\$s}} status to {{a status:%2\$s}}", 'Charitable', 'uncanny-automator' ),
				$this->get_action_meta(),
				'CHARITABLE_DONATION_STATUS:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( "Update {{a donation's}} status to {{a status}}", 'Charitable', 'uncanny-automator' ) );
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
				'label'       => esc_html_x( 'Donation ID', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'int',
				'required'    => true,
			),
			array(
				'option_code' => 'CHARITABLE_DONATION_STATUS',
				'label'       => esc_html_x( 'Status', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => $this->item_helpers->get_donation_status_options_no_any(),
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

		$donation_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$new_status  = sanitize_text_field( $parsed['CHARITABLE_DONATION_STATUS'] ?? '' );

		$donation = $this->item_helpers->get_donation( $donation_id );
		if ( ! $donation ) {
			$this->add_log_error( sprintf( 'Donation %d not found.', $donation_id ) );
			return false;
		}

		if ( empty( $new_status ) ) {
			$this->add_log_error( 'No status provided.' );
			return false;
		}

		$allowed_statuses = wp_list_pluck(
			$this->item_helpers->get_donation_status_options_no_any(),
			'value'
		);

		if ( ! in_array( $new_status, $allowed_statuses, true ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: 1: Provided status */
					esc_html_x( 'Invalid donation status "%1$s". Must be one of the supported Charitable statuses.', 'Charitable', 'uncanny-automator' ),
					$new_status
				)
			);
			return false;
		}

		$result = $donation->update_status( $new_status );
		if ( empty( $result ) ) {
			$this->add_log_error( 'Failed to update donation status.' );
			return false;
		}

		return true;
	}
}
