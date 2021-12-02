<?php

namespace Uncanny_Automator;

/**
 * Class RESTRICT_CONTENT_ADD_MEMBERSHIP_LEVEL
 *
 * @package Uncanny_Automator
 */
class RESTRICT_CONTENT_ADD_MEMBERSHIP_LEVEL {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'RC';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'RCADDMEMBERSHIPLEVEL';
		$this->action_meta = 'RCMEMBERSHIPLEVEL';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => 'Uncanny Automator',
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/restrict-content/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Logged-in trigger - Popup Maker */
			'sentence'           => sprintf( esc_attr__( 'Add the user to {{a membership level:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Logged-in trigger - Popup Maker */
			'select_option_name' => esc_attr__( 'Add the user to {{a membership level}}', 'uncanny-automator' ),
			'priority'           => 11,
			'accepted_args'      => 3,
			'execution_function' => array( $this, 'add_rcp_membership' ),
			'options_group'      => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->restrict_content->options->get_membership_levels(
						null,
						$this->action_meta,
						array( 'any' => false )
					),
					Automator()->helpers->recipe->field->text_field( 'RCMEMBERSHIPEXPIRY', esc_attr__( 'Expiry date', 'uncanny-automator' ), true, 'text', '', false, esc_attr__( 'Leave empty to use expiry settings from the membership level, or type a specific date in the format YYYY-MM-DD', 'uncanny-automator' ) ),
				),
			),
		);

		Automator()->register->action( $action );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function add_rcp_membership( $user_id, $action_data, $recipe_id, $args ) {

		$level_id    = absint( $action_data['meta'][ $this->action_meta ] );
		$expiry_date = Automator()->parse->text( $action_data['meta']['RCMEMBERSHIPEXPIRY'], $recipe_id, $user_id, $args );
		// Get all the active membership level IDs.
		$level_ids = rcp_get_membership_levels(
			array(
				'status' => 'active',
				'fields' => 'id',
			)
		);
		if ( empty( $level_ids ) ) {
			Automator()->complete_action( $user_id, $action_data, $recipe_id, __( 'You must have at least one active membership level.', 'rcp' ) );

			return;
		}

		$customer     = rcp_get_customer_by_user_id( $user_id );
		$newest_time  = current_time( 'timestamp' );
		$created_date = date( 'Y-m-d H:i:s', $newest_time );
		// Create a new customer record if one does not exist.
		if ( empty( $customer ) ) {
			$customer_id = rcp_add_customer(
				array(
					'user_id'         => absint( $user_id ),
					'date_registered' => $created_date,
				)
			);
		} else {
			$customer_id = $customer->get_id();
		}

		// Now add the membership.

		/*
		 * For the time always active status.
		 */
		$status          = 'active';
		$membership_args = array(
			'customer_id'      => absint( $customer_id ),
			'user_id'          => $user_id,
			'object_id'        => ! empty( $level_id ) ? $level_id : $level_ids[ array_rand( $level_ids ) ],
			// specified or random membership level ID
			'status'           => $status,
			'created_date'     => $created_date,
			'gateway'          => 'manual',
			'subscription_key' => rcp_generate_subscription_key(),
		);
		if ( ! empty( $expiry_date ) ) {
			$membership_args['expiration_date'] = date( 'Y-m-d H:i:s', strtotime( $expiry_date ) );
		}

		$membership_id = rcp_add_membership( $membership_args );

		// Add membership meta to designate this as a generated record so we can deleted it later.
		rcp_add_membership_meta( $membership_id, 'rcp_generated_via_UA', $recipe_id );

		$membership = rcp_get_membership( $membership_id );

		// Generate a transaction ID.
		$auth_key       = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$transaction_id = strtolower( md5( $membership_args['subscription_key'] . date( 'Y-m-d H:i:s' ) . $auth_key . uniqid( 'rcp', true ) ) );

		// Create a corresponding payment record.
		$payment_args = array(
			'subscription'     => rcp_get_subscription_name( $membership_args['object_id'] ),
			'object_id'        => $membership_args['object_id'],
			'date'             => $membership_args['created_date'],
			'amount'           => $membership->get_initial_amount(),
			'subtotal'         => $membership->get_initial_amount(),
			'user_id'          => $user_id,
			'subscription_key' => $membership_args['subscription_key'],
			'transaction_id'   => $transaction_id,
			'status'           => 'pending' == $membership_args['status'] ? 'pending' : 'complete',
			'gateway'          => 'manual',
			'customer_id'      => $customer_id,
			'membership_id'    => $membership_id,
		);

		$rcp_payments = new \RCP_Payments();
		$payment_id   = $rcp_payments->insert( $payment_args );

		// Add payment meta to designate this as a generated record so we can delete it later.
		$rcp_payments->add_meta( $payment_id, 'rcp_generated_via_UA', $recipe_id );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}
}
