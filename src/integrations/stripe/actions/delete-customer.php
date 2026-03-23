<?php

namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Delete_Customer
 *
 * @package Uncanny_Automator
 *
 * @property Stripe_App_Helpers $helpers
 * @property Stripe_Api_Caller $api
 */
class Delete_Customer extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * setup_action
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'STRIPE' );
		$this->set_action_code( 'DELETE_CUSTOMER' );
		$this->set_action_meta( 'EMAIL' );

		$this->set_requires_user( false );

		// translators: %1$s is the customer email
		$this->set_sentence( sprintf( esc_html_x( 'Delete {{a customer:%1$s}}', 'Stripe', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Delete {{a customer}}', 'Stripe', 'uncanny-automator' ) );
	}

	/**
	 * options
	 *
	 * @return array
	 */
	public function options() {

		$email = array(
			'option_code' => 'EMAIL',
			'label'       => esc_html_x( 'Email', 'Stripe', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
			'description' => esc_html_x( 'Email address of the customer', 'Stripe', 'uncanny-automator' ),
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
				'name' => esc_html_x( 'Customer ID', 'Stripe', 'uncanny-automator' ),
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

		$response = $this->api->delete_customer( $customer, $action_data );

		if ( empty( $response['data']['customer']['id'] ) ) {
			throw new \Exception(
				esc_html_x( 'Customer could not be deleted', 'Stripe', 'uncanny-automator' )
			);
		}

		$this->hydrate_tokens(
			array(
				'CUSTOMER_ID' => $response['data']['customer']['id'],
			)
		);

		return true;
	}
}
