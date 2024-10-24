<?php

namespace Uncanny_Automator\Integrations\Kadence;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class KADENCE_ANON_FORM_SUBMITTED
 *
 * @pacakge Uncanny_Automator
 */
class KADENCE_ANON_FORM_SUBMITTED extends Trigger {

	protected $helpers;

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'KADENCE' );
		$this->set_trigger_code( 'KADENCE_ANON_SUBMITTED_FORM' );
		$this->set_trigger_meta( 'KADENCE_FORMS' );
		$this->set_trigger_type( 'anonymous' );
		$this->set_sentence( sprintf( esc_attr_x( '{{A form:%1$s}} is submitted', 'Kadence', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( '{{A form}} is submitted', 'Kadence', 'uncanny-automator' ) );
		$this->add_action( 'automator_kadence_form_submitted', 10, 3 );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'input_type'      => 'select',
				'option_code'     => $this->get_trigger_meta(),
				'label'           => _x( 'Form', 'Kadence', 'uncanny-automator' ),
				'required'        => true,
				'options'         => $this->helpers->get_all_kadence_form_options( true ),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		list( $fields_data, $unique_id, $post_id ) = $hook_args;

		$form_id = ( is_null( $unique_id ) ) ? $post_id : $unique_id;

		if ( ! isset( $form_id ) ) {
			return false;
		}

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$selected_form_id = $trigger['meta'][ $this->get_trigger_meta() ];

		return ( intval( '-1' ) === intval( $selected_form_id ) ) || ( $selected_form_id === $form_id );
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
			'tokenId'   => 'KADENCE_FORM_ID',
			'tokenName' => __( 'Form ID', 'uncanny-automator' ),
			'tokenType' => 'int',
		);
		$tokens[] = array(
			'tokenId'   => 'KADENCE_FORM_TITLE',
			'tokenName' => __( 'Form title', 'uncanny-automator' ),
			'tokenType' => 'text',
		);
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) || intval( '-1' ) === intval( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return $tokens;
		}
		$form_id = $trigger['meta'][ $this->get_trigger_meta() ];

		return $this->helpers->get_kadence_form_tokens( $form_id, $tokens );
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
		list( $fields_data, $unique_id, $post_id ) = $hook_args;
		$form_id                                   = ( is_null( $unique_id ) ) ? $post_id : $unique_id;
		if ( is_null( $unique_id ) ) {
			$form_id   = $post_id;
			$form_name = get_post( $post_id )->post_title;
		}
		if ( ! is_null( $unique_id ) ) {
			$form_uid  = explode( '_', $unique_id );
			$form_id   = $unique_id;
			$form_name = get_post( $form_uid[0] )->post_title . ' - ' . $unique_id;
		}
		$trigger_token_values = array(
			'KADENCE_FORM_ID'    => $form_id,
			'KADENCE_FORM_TITLE' => $form_name,
		);
		foreach ( $fields_data as $field_data ) {
			$trigger_token_values[ 'KADENCE_' . str_replace( ' ', '_', $field_data['label'] ) ] = $field_data['value'];
		}

		return $trigger_token_values;
	}
}
