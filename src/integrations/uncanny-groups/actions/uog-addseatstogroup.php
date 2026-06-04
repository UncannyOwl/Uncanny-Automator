<?php

namespace Uncanny_Automator\Integrations\Uncanny_Groups;

/**
 * Class UOG_ADDSEATSTOGROUP
 *
 * @package Uncanny_Automator
 * @property \Uncanny_Automator\Integrations\Uncanny_Groups\Uog_Helpers $item_helpers
 */
class UOG_ADDSEATSTOGROUP extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action configuration.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'UOG' );
		$this->set_action_code( 'ADDSEATSTOGROUP' );
		$this->set_action_meta( 'UNCANNYGROUP' );
		$this->set_requires_user( false );

		// translators: %1$s is the number of seats, %2$s is the group.
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Add {{a number of:%1$s}} seats to {{an Uncanny group:%2$s}}', 'Uncanny Groups', 'uncanny-automator' ),
				'NUMOFSEATS',
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Add {{a number of}} seats to {{an Uncanny group}}', 'Uncanny Groups', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				$this->get_action_meta() . '_TOTAL_SEATS'     => array(
					'name' => esc_html_x( 'Total seats', 'Uncanny Groups', 'uncanny-automator' ),
					'type' => 'int',
				),
				$this->get_action_meta() . '_REMAINING_SEATS' => array(
					'name' => esc_html_x( 'Remaining seats', 'Uncanny Groups', 'uncanny-automator' ),
					'type' => 'int',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {

		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Group', 'Uncanny Groups', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => true,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'groups_strict' ),
			),
			array(
				'option_code'     => 'NUMOFSEATS',
				'label'           => esc_html_x( 'Quantity', 'Uncanny Groups', 'uncanny-automator' ),
				'input_type'      => 'int',
				'required'        => true,
				'supports_tokens' => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$uo_group_id = $parsed[ $this->get_action_meta() ] ?? '';
		$check_group = $this->item_helpers->learndash_validate_groups( array( $uo_group_id ) );

		if ( empty( $check_group ) || ! is_array( $check_group ) ) {
			$this->add_log_error( esc_html_x( 'The selected group is not found.', 'Uncanny Groups', 'uncanny-automator' ) );
			return false;
		}

		$uo_group_num_seats = absint( $parsed['NUMOFSEATS'] ?? 0 );
		$code_group_id      = ulgm()->group_management->seat->get_code_group_id( $uo_group_id );

		if ( empty( $code_group_id ) ) {
			$this->add_log_error( esc_html_x( 'Group management is not enabled on the selected group.', 'Uncanny Groups', 'uncanny-automator' ) );
			return false;
		}

		$existing_seats = ulgm()->group_management->seat->total_seats( $uo_group_id );

		// Seats added.
		if ( $uo_group_num_seats > 0 ) {
			$new_seats = $existing_seats + $uo_group_num_seats;
			$new_codes = ulgm()->group_management->generate_random_codes( $uo_group_num_seats );

			$attr = array(
				'qty'           => $uo_group_num_seats,
				'code_group_id' => $code_group_id,
			);
			ulgm()->group_management->add_additional_codes( $attr, $new_codes );
			update_post_meta( $uo_group_id, '_ulgm_total_seats', $new_seats );

			$this->hydrate_tokens(
				array(
					$this->get_action_meta() . '_TOTAL_SEATS'     => ulgm()->group_management->seat->total_seats( $uo_group_id ),
					$this->get_action_meta() . '_REMAINING_SEATS' => ulgm()->group_management->seat->remaining_seats( $uo_group_id ),
				)
			);
		}

		return true;
	}
}
