<?php

namespace Uncanny_Automator;

class UNCANNYCEUS_EARNS_NUMBERS_MORE_THAN extends \Uncanny_Automator\Recipe\Trigger {

	protected function setup_trigger() {

		if ( ! class_exists( '\uncanny_ceu\Utilities' ) ) {
			return;
		}
		// The hook is only available on or after CEU version 3.0.7
		$version = \uncanny_ceu\Utilities::get_version();
		if ( intval( '-1' ) === intval( version_compare( $version, '3.0.6', '>' ) ) ) {
			return;
		}

		$credit_designation_label_plural = get_option( 'credit_designation_label_plural', __( 'CEUs', 'uncanny-ceu' ) );
		$this->set_integration( 'UNCANNYCEUS' );
		$this->set_trigger_code( 'EARNS_NUMBERS_MORE_THAN' );
		$this->set_trigger_meta( 'CEUS_EARN_NUMBERS' );
		$this->set_sentence( sprintf( esc_attr_x( 'A user earns {{number:%1$s}} or more %2$s', 'Uncanny CEUs', 'uncanny-automator' ), $this->get_trigger_meta(), $credit_designation_label_plural ) );
		$this->set_readable_sentence( sprintf( esc_attr_x( 'A user earns {{a number of}} or more %1$s', 'Uncanny CEUs', 'uncanny-automator' ), $credit_designation_label_plural ) );
		$this->add_action( 'ceus_after_updated_user_ceu_record', 20, 7 );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		$credit_designation_label_plural = get_option( 'credit_designation_label_plural', __( 'CEUs', 'uncanny-ceu' ) );

		return array(
			array(
				'input_type'      => 'float',
				'option_code'     => $this->get_trigger_meta(),
				/* translators: Uncanny CEUs. 1. Credit designation label (plural) */
				'label'           => esc_attr_x( 'Number', 'Uncanny CEUs', 'uncanny-automator' ),
				'validation_type' => 'float',
				'required'        => true,
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$numbers_entered = $trigger['meta'][ $this->get_trigger_meta() ];
		$ceu_value       = $hook_args[6];

		if ( $ceu_value < $numbers_entered ) {
			return false;
		}

		return true;
	}

	/**
	 * define_tokens
	 *
	 * @param mixed $tokens
	 * @param mixed $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tokens[] = array(
			'tokenId'   => 'CEUS_AMOUNT',
			'tokenName' => _x( 'CEUs amount', 'Uncanny CEUs', 'uncanny-automator' ),
			'tokenType' => 'text',
		);

		$tokens[] = array(
			'tokenId'   => 'CEUS_TITLE',
			'tokenName' => _x( 'Course or CEUs title', 'Uncanny CEUs', 'uncanny-automator' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'CEUS_DATE_AWARDED',
			'tokenName' => _x( 'Date awarded', 'Uncanny CEUs', 'uncanny-automator' ),
			'tokenType' => 'date',
		);
		$tokens[] = array(
			'tokenId'   => 'CEUS_LABEL',
			'tokenName' => _x( 'Credit label for CEUs', 'Uncanny CEUs', 'uncanny-automator' ),
			'tokenType' => 'text',
		);

		return $tokens;
	}

	/**
	 * hydrate_tokens
	 *
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$token_values = array(
			'CEUS_AMOUNT'       => $hook_args[6],
			'CEUS_TITLE'        => $hook_args[4],
			'CEUS_DATE_AWARDED' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), absint( $hook_args[2] ) ),
			'CEUS_LABEL'        => get_option( 'credit_designation_label_plural', __( 'CEUs', 'uncanny-ceu' ) ),
		);

		return $token_values;
	}
}
