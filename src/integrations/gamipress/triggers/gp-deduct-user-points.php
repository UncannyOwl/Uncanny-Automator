<?php

namespace Uncanny_Automator;

/**
 * Class GP_DEDUCT_USER_POINTS
 * @package Uncanny_Automator
 */
class GP_DEDUCT_USER_POINTS extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * @return mixed
	 */
	protected function setup_trigger() {
		$this->set_integration( 'GP' );
		$this->set_trigger_code( 'GP_DEDUCT_POINTS' );
		$this->set_trigger_meta( 'GP_POINTS_TYPES' );
		// translators: GamiPress - Membership plan
		$this->set_sentence( sprintf( esc_html_x( 'A user loses {{greater than, less than, or equal to:%3$s}} {{a number of:%1$s}} {{a specific type of:%2$s}} points', 'GamiPress', 'uncanny-automator' ), 'GP_POINT_VALUE:' . $this->get_trigger_meta(), $this->get_trigger_meta(), 'GP_NUMBER_CONDITION:' . $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A user loses {{greater than, less than, or equal to}} {{a number of}} {{a specific type of}} points', 'GamiPress', 'uncanny-automator' ) );
		$this->add_action( 'gamipress_deduct_points_to_user', 10, 4 );
	}

	/**
	 * options
	 *
	 * The method to display a default option group
	 *
	 * @return array
	 */
	public function options() {
		$point_types       = Automator()->helpers->recipe->gamipress->options->list_gp_points_types( esc_attr_x( 'Point type', 'Gamipress', 'uncanny-automator' ), $this->trigger_meta );
		$number_conditions = Automator()->helpers->recipe->field->less_or_greater_than();
		$all_point_types   = array();
		foreach ( $point_types['options'] as $key => $option ) {
			$all_point_types[] = array(
				'text'  => $option,
				'value' => $key,
			);
		}
		$logical_conditions = array();
		foreach ( $number_conditions['options'] as $key => $option ) {
			$logical_conditions[] = array(
				'text'  => $option,
				'value' => $key,
			);
		}

		return array(
			array(
				'option_code' => 'GP_POINT_VALUE',
				'label'       => esc_attr_x( 'Points', 'GamiPress', 'uncanny-automator' ),
				'placeholder' => esc_attr_x( 'Example: 15', 'GamiPress', 'uncanny-automator' ),
				'input_type'  => 'int',
				'default'     => null,
				'required'    => true,
			),
			array(
				'input_type'  => 'select',
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Point type', 'GamiPress', 'uncanny-automator' ),
				'required'    => true,
				'options'     => $all_point_types,
			),
			array(
				'input_type'  => 'select',
				'option_code' => 'GP_NUMBER_CONDITION',
				'label'       => esc_html_x( 'Condition', 'GamiPress', 'uncanny-automator' ),
				'required'    => true,
				'options'     => $logical_conditions,
			),
		);
	}

	/**
	 * @param $trigger
	 * @param $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$tokens[] = array(
			'tokenId' => 'GP_POINT_VALUE_CHANGED',
			'tokenName' => 'Points changed',
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId' => 'GP_POINTS_AFTER',
			'tokenName' => 'Points after change',
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId' => 'GP_POINTS',
			'tokenName' => 'Points',
			'tokenType' => 'text',
		);
		return $tokens;
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ], $hook_args ) ) {
			return false;
		}

		list( $user_id, $points, $points_type, $args ) = $hook_args;

		$selected_points_type = $trigger['meta'][ $this->get_trigger_meta() ];
		$entered_points       = $trigger['meta']['GP_POINT_VALUE'];
		$required_condition   = $trigger['meta']['GP_NUMBER_CONDITION'];
		$this->set_user_id( $user_id );

		$match_condition = Automator()->utilities->match_condition_vs_number( $required_condition, $entered_points, abs( $points ) );

		return ( ( intval( '-1' ) === intval( $selected_points_type ) || (string) $selected_points_type === (string) $points_type ) && $match_condition );
	}

	/**
	 * @param $completed_trigger
	 * @param $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		list( $user_id, $points, $points_type, $args ) = $hook_args;
		$current_points                                = gamipress_get_user_points( $user_id, $points_type );

		return array(
			'GP_POINTS_TYPES'     => gamipress_get_points_type_singular( $points_type ),
			'GP_NUMBER_CONDITION' => $completed_trigger['meta']['GP_NUMBER_CONDITION_readable'],
			'GP_POINT_VALUE'      => $completed_trigger['meta']['GP_POINT_VALUE'],
			'GP_POINT_VALUE_CHANGED'      => $points,
			'GP_POINTS_AFTER'     => $current_points,
			'GP_POINTS'     => $current_points,
		);
	}
}
