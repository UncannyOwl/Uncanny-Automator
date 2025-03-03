<?php

namespace Uncanny_Automator\Integrations\Stripe;

class Delete_Customer extends \Uncanny_Automator\Recipe\Action {

	protected $helpers;

	protected $dependencies;

	/**
	 * setup_action
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'STRIPE' );
		$this->set_action_code( 'DELETE_CUSTOMER' );
		$this->set_action_meta( 'EMAIL' );

		$this->set_requires_user( false );

		/* translators: %1$s Contact Email */
		$this->set_sentence( sprintf( esc_attr_x( 'Delete {{a customer:%1$s}}', 'Stripe', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Delete {{a customer}}', 'Stripe', 'uncanny-automator' ) );
	}

	/**
	 * options
	 *
	 * @return array
	 */
	public function options() {

		$email = array(
			'option_code' => 'EMAIL',
			'label'       => esc_html__( 'Email', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
			'description' => esc_html__( 'Email address of the customer', 'uncanny-automator' ),
		);

		return array(
			$email,
		);
	}

	/**
	 * define_tokens
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'CUSTOMER_ID' => array(
				'name' => esc_html__( 'Customer ID', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return null
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$customer = array(
			'email' => $this->get_parsed_meta_value( 'EMAIL', '' ),
		);

		$response = $this->helpers->api->delete_customer( $customer, $action_data );

		if ( empty( $response['data']['customer']['id'] ) ) {

			$error = _x( 'Customer could not be deleted', 'Stripe', 'uncanny-automator' );

			throw new \Exception( esc_html( $error ) );
		}

		$this->hydrate_tokens(
			array(
				'CUSTOMER_ID' => $response['data']['customer']['id'],
			)
		);

		return true;
	}
}
