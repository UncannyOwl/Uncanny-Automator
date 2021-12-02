<?php

namespace Uncanny_Automator;

/**
 * Class AFFWP_NEWAFFILAITE
 *
 * @package Uncanny_Automator
 */
class AFFWP_NEWAFFILIATE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'AFFWP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'NEWAFFILIATE';
		$this->trigger_meta = 'USERBECOMESAFFILIATE';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/affiliatewp/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Affiliate WP */
			'sentence'            => sprintf( __( 'A user becomes an affiliate', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Affiliate WP */
			'select_option_name'  => __( 'A user becomes an affiliate', 'uncanny-automator' ),
			'action'              => 'affwp_set_affiliate_status',
			'priority'            => 10,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'affwp_user_becomes_affiliate' ),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );

	}

	/**
	 * @param $affiliate_id
	 * @param $status
	 * @param $data
	 *
	 * @return mixed
	 */
	public function affwp_user_becomes_affiliate( $affiliate_id, $status, $old_status ) {

		// Only fire this trigger if status is set to 'active'.
		if ( 'active' !== $status ) {
			return $status;
		}

		$affiliate = affwp_get_affiliate( $affiliate_id );

		$user_id = affwp_get_affiliate_user_id( $affiliate_id );

		$user = get_user_by( 'ID', $user_id );

		if ( 0 === absint( $user_id ) ) {
			// Its a logged in recipe and
			// user ID is 0. Skip process
			return;
		}

		$pass_args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $user_id,
			'ignore_post_id' => true,
			'is_signed_in'   => true,
		);

		$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {

					$trigger_meta = array(
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					);

					$trigger_meta['meta_key']   = 'AFFILIATEWPID';
					$trigger_meta['meta_value'] = maybe_serialize( $affiliate_id );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'AFFILIATEWPSTATUS';
					$trigger_meta['meta_value'] = maybe_serialize( $affiliate->status );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'AFFILIATEWPREGISTERDATE';
					$trigger_meta['meta_value'] = maybe_serialize( $affiliate->date_registered );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'AFFILIATEWPPAYMENTEMAIL';
					$trigger_meta['meta_value'] = maybe_serialize( $affiliate->payment_email );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'AFFILIATEWPACCEMAIL';
					$trigger_meta['meta_value'] = maybe_serialize( $user->data->user_email );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'AFFILIATEWPWEBSITE';
					$trigger_meta['meta_value'] = maybe_serialize( $user->user_url );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'AFFILIATEWPURL';
					$trigger_meta['meta_value'] = maybe_serialize( affwp_get_affiliate_referral_url( array( 'affiliate_id' => $affiliate_id ) ) );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'AFFILIATEWPREFRATE';
					$trigger_meta['meta_value'] = ! empty( $affiliate->rate ) ? maybe_serialize( $affiliate->rate ) : maybe_serialize( '0' );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'AFFILIATEWPREFRATETYPE';
					$trigger_meta['meta_value'] = ! empty( $affiliate->rate_type ) ? maybe_serialize( $affiliate->rate_type ) : maybe_serialize( '0' );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'AFFILIATEWPPROMOMETHODS';
					$trigger_meta['meta_value'] = maybe_serialize( get_user_meta( $affiliate->user_id, 'affwp_promotion_method', true ) );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'AFFILIATEWPNOTES';
					$trigger_meta['meta_value'] = maybe_serialize( affwp_get_affiliate_meta( $affiliate->affiliate_id, 'notes', true ) );
					Automator()->insert_trigger_meta( $trigger_meta );

					$dynamic_coupons = affwp_get_dynamic_affiliate_coupons( $affiliate->ID, false );
					$coupons         = '';
					if ( isset( $dynamic_coupons ) && is_array( $dynamic_coupons ) ) {
						foreach ( $dynamic_coupons as $coupon ) {
							$coupons .= $coupon->coupon_code . '<br/>';
						}
					}

					$trigger_meta['meta_key']   = 'AFFILIATEWPCOUPON';
					$trigger_meta['meta_value'] = maybe_serialize( $coupons );
					Automator()->insert_trigger_meta( $trigger_meta );

					Automator()->maybe_trigger_complete( $result['args'] );
					break;
				}
			}
		}
	}
}
