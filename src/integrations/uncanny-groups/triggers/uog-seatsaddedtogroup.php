<?php

namespace Uncanny_Automator\Integrations\Uncanny_Groups;

/**
 * Class UOG_SEATSADDEDTOGROUP
 *
 * @package Uncanny_Automator
 * @property \Uncanny_Automator\Integrations\Uncanny_Groups\Uog_Helpers $item_helpers
 */
class UOG_SEATSADDEDTOGROUP extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'UOG_SEATSADDEDTOGROUP', 'UOG' )
			->trigger_meta( 'UOG_SEATSADDEDTOGROUP_META' )
			->trigger_type( 'anonymous' )
			->hook( 'ulgm_seats_added', 10, 5 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_login_required( false );

		// translators: %1$s is the condition, %2$s is the number of seats, %3$s is the group.
		$this->set_sentence(
			sprintf(
				esc_html_x(
					'A number of seats {{greater than, less than, equal to, not equal to:%1$s}}{{a specific number:%2$s}} are added to {{an Uncanny group:%3$s}}',
					'Uncanny Groups',
					'uncanny-automator'
				),
				'NUMBERCOND:' . $this->get_trigger_meta(),
				$this->get_trigger_meta() . '_NUMOFSEATS:' . $this->get_trigger_meta(),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x(
				'A number of seats {{greater than, less than, equal to, not equal to}} {{a specific number}} are added to {{an Uncanny group}}',
				'Uncanny Groups',
				'uncanny-automator'
			)
		);
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {

		return array(
			Uog_Helpers::comparison_field(),
			array(
				'option_code'     => $this->get_trigger_meta() . '_NUMOFSEATS',
				'label'           => esc_html_x( 'Seats', 'Uncanny Groups', 'uncanny-automator' ),
				'input_type'      => 'int',
				'required'        => true,
				'supports_tokens' => true,
			),
			array(
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Group', 'Uncanny Groups', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(),
				'remote_data' => $this->item_helpers->remote_data_load_config( 'groups' ),
			),
		);
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		return array(
			array(
				'tokenId'   => 'GROUP_ID',
				'tokenName' => esc_html_x( 'Group ID', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'GROUP_TITLE',
				'tokenName' => esc_html_x( 'Group title', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SEATS_ADDED',
				'tokenName' => esc_html_x( 'Seats added', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'GROUP_LEADER_EMAILS',
				'tokenName' => esc_html_x( 'Group leader email(s)', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'GROUP_REMAINING_SEATS',
				'tokenName' => esc_html_x( 'Remaining seats', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'GROUP_TOTAL_SEATS',
				'tokenName' => esc_html_x( 'Total seats', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $count, $ld_group_id ) = $hook_args;

		if ( empty( $count ) || absint( $count ) <= 0 ) {
			return false;
		}

		if ( empty( $ld_group_id ) || 'groups' !== get_post_type( $ld_group_id ) ) {
			return false;
		}

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$selected_group = $trigger['meta'][ $this->get_trigger_meta() ];

		// Match "Any group" or specific group.
		if ( '-1' !== $selected_group && absint( $ld_group_id ) !== absint( $selected_group ) ) {
			return false;
		}

		// Validate the number condition.
		$condition = $trigger['meta']['NUMBERCOND'] ?? '';
		$threshold = isset( $trigger['meta'][ $this->get_trigger_meta() . '_NUMOFSEATS' ] )
			? absint( $trigger['meta'][ $this->get_trigger_meta() . '_NUMOFSEATS' ] )
			: 0;

		return \Automator()->utilities->match_condition_vs_number( $condition, $threshold, absint( $count ) );
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $count, $ld_group_id ) = $hook_args;

		return array(
			'NUMBERCOND'            => $trigger['meta']['NUMBERCOND_readable'] ?? $this->item_helpers->get_number_conditions_values( $trigger['meta']['NUMBERCOND'] ?? '' ),
			'GROUP_ID'              => absint( $ld_group_id ),
			'GROUP_TITLE'           => get_the_title( absint( $ld_group_id ) ),
			'SEATS_ADDED'           => absint( $count ),
			'GROUP_LEADER_EMAILS'   => $this->item_helpers->get_group_leaders_email_addresses( $ld_group_id ),
			'GROUP_REMAINING_SEATS' => ulgm()->group_management->seat->remaining_seats( $ld_group_id ),
			'GROUP_TOTAL_SEATS'     => ulgm()->group_management->seat->total_seats( $ld_group_id ),
		);
	}
}
