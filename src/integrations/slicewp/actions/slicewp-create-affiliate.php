<?php

namespace Uncanny_Automator\Integrations\SliceWP;

use Uncanny_Automator\Recipe\Action;

/**
 * Class SLICEWP_CREATE_AFFILIATE
 *
 * @pacakge Uncanny_Automator
 */
class SLICEWP_CREATE_AFFILIATE extends Action {

	protected function setup_action() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'SLICEWP' );
		$this->set_action_code( 'SLICEWP_CREATE_AFFILIATE' );
		$this->set_action_meta( 'SLICEWP_AFFILIATE' );
		$this->set_requires_user( true );
		// translators: 1: Affiliate name
		$this->set_sentence( sprintf( esc_attr_x( 'Add {{a new affiliate:%1$s}}', 'SliceWP', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Add {{a new affiliate}}', 'SliceWP', 'uncanny-automator' ) );
	}

	public function options() {
		return array(
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'user_email',
					'input_type'  => 'email',
					'label'       => esc_attr__( 'Email', 'uncanny-automator' ),
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'payment_email',
					'input_type'  => 'email',
					'label'       => esc_attr__( 'Payment email', 'uncanny-automator' ),
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'website',
					'input_type'  => 'url',
					'label'       => esc_attr__( 'Website', 'uncanny-automator' ),
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'promotional_methods',
					'input_type'  => 'textarea',
					'label'       => esc_attr__( 'How will you promote us?', 'uncanny-automator' ),
				)
			),
			Automator()->helpers->recipe->field->select(
				array(
					'option_code' => 'status',
					'label'       => esc_attr__( 'Status', 'uncanny-automator' ),
					'options'     => $this->helpers->get_statuses(),
				)
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'welcome_email',
					'label'       => esc_attr__( 'Send welcome email after adding an affiliate?', 'uncanny-automator' ),
					'required'    => false,
					'input_type'  => 'checkbox',
					'is_toggle'   => true,
				)
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param       $parsed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$email               = isset( $parsed['user_email'] ) ? sanitize_email( $parsed['user_email'] ) : '';
		$payment_email       = isset( $parsed['payment_email'] ) ? sanitize_email( $parsed['payment_email'] ) : '';
		$website             = isset( $parsed['website'] ) ? sanitize_email( $parsed['website'] ) : '';
		$promotional_methods = isset( $parsed['promotional_methods'] ) ? wp_kses_post( $parsed['promotional_methods'] ) : '';
		$status              = isset( $parsed['status'] ) ? sanitize_text_field( $parsed['status'] ) : '';
		$welcome_email       = isset( $parsed['welcome_email'] ) ? sanitize_text_field( $parsed['welcome_email'] ) : '';
		$welcome_email       = ( 'true' === $welcome_email ) ? true : false;
		$existing_user       = email_exists( $email );

		if ( false === $existing_user ) {
			$this->add_log_error( sprintf( 'The provided email: %s does not exist.', $email ) );

			return false;
		}
		$user_id = $existing_user;

		if ( true === slicewp_is_user_affiliate( $user_id ) ) {
			$this->add_log_error( sprintf( 'The user: %s is already an affiliate.', $email ) );

			return false;
		}

		$affiliate_data = array(
			'user_id'       => absint( $user_id ),
			'date_created'  => slicewp_mysql_gmdate(),
			'date_modified' => slicewp_mysql_gmdate(),
			'payment_email' => sanitize_email( $payment_email ),
			'website'       => esc_url( $website ),
			'status'        => $status,
		);

		$affiliate_id = slicewp_insert_affiliate( $affiliate_data );

		if ( empty( $affiliate_id ) ) {
			$this->add_log_error( 'Affiliate account could not be created!' );

			return false;
		}

		slicewp_add_affiliate_meta( $affiliate_id, 'promotional_methods', $promotional_methods );

		if ( true === $welcome_email ) {
			do_action( 'slicewp_register_affiliate', $affiliate_id );
		}

		return true;
	}
}
