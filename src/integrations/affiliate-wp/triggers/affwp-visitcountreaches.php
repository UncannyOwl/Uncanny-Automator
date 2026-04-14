<?php

namespace Uncanny_Automator;

/**
 * Class AFFWP_VISITCOUNTREACHES
 *
 * @package Uncanny_Automator
 */
class AFFWP_VISITCOUNTREACHES {

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
		$this->trigger_code = 'AFFWPVISITCOUNTREACHES';
		$this->trigger_meta = 'AFFWPVISITCOUNT';
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
			'sentence'            => sprintf( esc_html_x( "An affiliate's visit count is {{greater than, less than, or equal to:%1\$s}} {{a specific number:%2\$s}}", 'Affiliate Wp', 'uncanny-automator' ), 'NUMBERCOND', $this->trigger_meta ),
			/* translators: Logged-in trigger - Affiliate WP */
			'select_option_name'  => esc_html_x( "An affiliate's visit count is {{greater than, less than, or equal to}} {{a specific number}}", 'Affiliate Wp', 'uncanny-automator' ),
			'action'              => 'affwp_post_insert_visit',
			'priority'            => 10,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'affwp_visit_count_reaches' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->field->less_or_greater_than(),
					array(
						'option_code' => $this->trigger_meta,
						'label'       => esc_html_x( 'Number of visits', 'Affiliate Wp', 'uncanny-automator' ),
						'input_type'  => 'int',
						'required'    => true,
					),
				),
			)
		);
	}

	/**
	 * Fires when a visit is recorded.
	 *
	 * @param int   $visit_id The visit ID.
	 * @param array $data     The visit data.
	 */
	public function affwp_visit_count_reaches( $visit_id, $data ) {

		if ( empty( $data['affiliate_id'] ) ) {
			return;
		}

		$affiliate_id = absint( $data['affiliate_id'] );
		$affiliate    = affwp_get_affiliate( $affiliate_id );

		if ( ! $affiliate ) {
			return;
		}

		$user_id = affwp_get_affiliate_user_id( $affiliate_id );

		if ( 0 === absint( $user_id ) ) {
			return;
		}

		$visit_count = absint( affwp_count_visits( $affiliate_id ) );

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_count     = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$required_condition = Automator()->get->meta_from_recipes( $recipes, 'NUMBERCOND' );

		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( Automator()->utilities->match_condition_vs_number( $required_condition[ $recipe_id ][ $trigger_id ], $required_count[ $recipe_id ][ $trigger_id ], $visit_count ) ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		if ( empty( $matched_recipe_ids ) ) {
			return;
		}

		$user = get_user_by( 'ID', $user_id );

		foreach ( $matched_recipe_ids as $matched_recipe_id ) {
			$pass_args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'user_id'          => $user_id,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
				'ignore_post_id'   => true,
				'is_signed_in'     => false,
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
						$trigger_meta['meta_value'] = maybe_serialize( $visit_count );
						Automator()->insert_trigger_meta( $trigger_meta );

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
					}
				}
			}
		}
	}
}
