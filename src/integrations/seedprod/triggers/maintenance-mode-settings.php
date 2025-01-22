<?php

namespace Uncanny_Automator\Integrations\Seedprod;

/**
 * Class MAINTENANCE_MODE_SETTINGS
 * @package Uncanny_Automator
 */
class MAINTENANCE_MODE_SETTINGS extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * @return mixed
	 */
	protected function setup_trigger() {
		$this->set_integration( 'SEEDPROD' );
		$this->set_trigger_code( 'SEEDPROD_MAINTENANCE_MODE' );
		$this->set_trigger_meta( 'SEEDPROD_MAINTENANCE_MODE_META' );
		/* translators: Trigger sentence */
		$this->set_sentence( sprintf( esc_attr_x( 'Maintenance mode is set to {{a status:%1$s}}', 'SeedProd', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		/* translators: Trigger sentence */
		$this->set_readable_sentence( esc_attr_x( 'Maintenance mode is set to {{a status}}', 'SeedProd', 'uncanny-automator' ) );
		$this->add_action( 'update_option_seedprod_settings', 10, 3 );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'input_type'      => 'select',
				'label'           => _x( 'Status', 'Seedprod', 'uncanny-automator' ),
				'required'        => true,
				'options'         => array(
					array(
						'value' => 'enabled',
						'text'  => esc_attr_x( 'Enabled', 'Seedprod', 'uncanny-automator' ),
					),
					array(
						'value' => 'disabled',
						'text'  => esc_attr_x( 'Disabled', 'Seedprod', 'uncanny-automator' ),
					),
				),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		list( $old_value, $new_value, $option ) = $hook_args;
		$new_value                              = json_decode( $new_value );
		$old_value                              = json_decode( $old_value );

		$maintenance_mode_new_value = isset( $new_value->enable_maintenance_mode ) ? $new_value->enable_maintenance_mode : '';
		$maintenance_mode_old_value = isset( $old_value->enable_maintenance_mode ) ? $old_value->enable_maintenance_mode : '';

		if ( $maintenance_mode_old_value === $maintenance_mode_new_value ) {
			return false;
		}

		$selected_status  = $trigger['meta'][ $this->get_trigger_meta() ];
		$maintenance_mode = ( true === $maintenance_mode_new_value ) ? 'enabled' : 'disabled';

		return ( $selected_status === $maintenance_mode );

	}

	/**
	 * Define Tokens.
	 *
	 * @param array $tokens
	 * @param array $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			array(
				'tokenId'   => 'MAINTENANCE_MODE_STATUS',
				'tokenName' => esc_attr_x( 'Status', 'SeedProd', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Populate the tokens with actual values when a trigger runs.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$new_value               = $hook_args[1];
		$maintenance_mode_status = json_decode( $new_value )->enable_maintenance_mode;
		$maintenance_mode_status = ( true == $maintenance_mode_status ) ? 'Enabled' : 'Disabled';

		return array( 'MAINTENANCE_MODE_STATUS' => $maintenance_mode_status );
	}
}
