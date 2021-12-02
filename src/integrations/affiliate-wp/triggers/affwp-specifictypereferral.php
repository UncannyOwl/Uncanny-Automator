<?php

namespace Uncanny_Automator;

class AFFWP_SPECIFICTYPEREFERRAL {

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
		$this->trigger_code = 'AFFWPREFERRAL';
		$this->trigger_meta = 'SPECIFICETYPEREF';
		//      add_action(
		//          'plugins_loaded',
		//          function () {
				$this->define_trigger();
		//          }
		//      );
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
			'sentence'            => sprintf( __( 'An affiliate makes a referral of a {{specific type:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Affiliate WP */
			'select_option_name'  => __( 'An affiliate makes a referral of a {{specific type}}', 'uncanny-automator' ),
			'action'              => 'affwp_complete_referral',
			'priority'            => 99,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'affwp_insert_specific_type_referral' ),
			'options'             => array(
				Automator()->helpers->recipe->affiliate_wp->options->get_referral_types( null, $this->trigger_meta, array( 'any_option' => true ) ),
			),
		);

		Automator()->register->trigger( $trigger );

	}

	/**
	 * Processes our hook.
	 *
	 * @param $referral_id
	 */
	public function affwp_insert_specific_type_referral( $referral_id, $referral, $referral_reference ) {

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_type      = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$referral           = affwp_get_referral( $referral_id );
		$type               = $referral->type;
		$user_id            = affwp_get_affiliate_user_id( $referral->affiliate_id );
		$user               = get_user_by( 'id', $user_id );
		$affiliate          = affwp_get_affiliate( $referral->affiliate_id );
		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( $type === $required_type[ $recipe_id ][ $trigger_id ] || '-1' === $required_type[ $recipe_id ][ $trigger_id ] ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$pass_args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
					'is_signed_in'     => true,
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

							$trigger_meta['meta_key']   = $this->trigger_meta;
							$trigger_meta['meta_value'] = maybe_serialize( $type );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'AFFILIATEWPID';
							$trigger_meta['meta_value'] = maybe_serialize( $referral->affiliate_id );
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
							$trigger_meta['meta_value'] = maybe_serialize( $user->user_email );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'AFFILIATEWPWEBSITE';
							$trigger_meta['meta_value'] = maybe_serialize( $user->user_url );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'AFFILIATEWPURL';
							$trigger_meta['meta_value'] = maybe_serialize( affwp_get_affiliate_referral_url( array( 'affiliate_id' => $referral->affiliate_id ) ) );
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

							$trigger_meta['meta_key']   = 'REFERRALAMOUNT';
							$trigger_meta['meta_value'] = maybe_serialize( $referral->amount );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'REFERRALDATE';
							$trigger_meta['meta_value'] = maybe_serialize( $referral->date );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'REFERRALDESCRIPTION';
							$trigger_meta['meta_value'] = maybe_serialize( $referral->description );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'REFERRALCONTEXT';
							$trigger_meta['meta_value'] = maybe_serialize( $referral->context );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'REFERRALREFERENCE';
							$trigger_meta['meta_value'] = maybe_serialize( $referral->reference );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'REFERRALCUSTOM';
							$trigger_meta['meta_value'] = maybe_serialize( $referral->custom );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'REFERRALSTATUS';
							$trigger_meta['meta_value'] = maybe_serialize( $referral->status );
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
	}

}
