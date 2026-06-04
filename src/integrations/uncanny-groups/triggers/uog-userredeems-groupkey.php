<?php

namespace Uncanny_Automator\Integrations\Uncanny_Groups;

/**
 * Class UOG_USERREDEEMS_GROUPKEY
 *
 * @package Uncanny_Automator
 * @property \Uncanny_Automator\Integrations\Uncanny_Groups\Uog_Helpers $item_helpers
 */
class UOG_USERREDEEMS_GROUPKEY extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'REDEEMSGROUPKEY', 'UOG' )
			->trigger_meta( 'UNCANNYGROUPS' )
			->hook( 'ulgm_user_redeems_group_key', 20, 2 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		// translators: %1$s is the group.
		$this->set_sentence(
			sprintf(
				esc_html_x( 'A user redeems {{a group:%1$s}} key', 'Uncanny Groups', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'A user redeems {{a group}} key', 'Uncanny Groups', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {

		return array(
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
				'tokenId'   => 'UNCANNYGROUPS',
				'tokenName' => esc_html_x( 'Group title', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYGROUPS_ID',
				'tokenName' => esc_html_x( 'Group ID', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYGROUPS_URL',
				'tokenName' => esc_html_x( 'Group URL', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYGROUPS_KEY',
				'tokenName' => esc_html_x( 'Key redeemed', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYGROUPS_KEY_BATCH_ID',
				'tokenName' => esc_html_x( 'Key batch ID', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYGROUPS_REMAINING_SEATS',
				'tokenName' => esc_html_x( 'Remaining seats', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'UNCANNYGROUPS_TOTAL_SEATS',
				'tokenName' => esc_html_x( 'Total seats', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'int',
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

		list( $user_id, $code ) = $hook_args;

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( empty( $user_id ) ) {
			return false;
		}

		if ( is_array( $code ) && 'success' !== $code['result'] ) {
			return false;
		}

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$selected_group = $trigger['meta'][ $this->get_trigger_meta() ];

		// Match "Any group" or specific group.
		if ( '-1' !== $selected_group && absint( $code['ld_group_id'] ) !== absint( $selected_group ) ) {
			return false;
		}

		return true;
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

		list( $user_id, $code ) = $hook_args;

		$ld_group_id = $code['ld_group_id'];

		return array(
			'UNCANNYGROUPS'                 => get_the_title( $ld_group_id ),
			'UNCANNYGROUPS_ID'              => $ld_group_id,
			'UNCANNYGROUPS_URL'             => get_permalink( $ld_group_id ),
			'UNCANNYGROUPS_KEY'             => $code['key'],
			'UNCANNYGROUPS_KEY_BATCH_ID'    => $code['group_id'],
			'UNCANNYGROUPS_REMAINING_SEATS' => ulgm()->group_management->seat->remaining_seats( $ld_group_id ),
			'UNCANNYGROUPS_TOTAL_SEATS'     => ulgm()->group_management->seat->total_seats( $ld_group_id ),
		);
	}
}
