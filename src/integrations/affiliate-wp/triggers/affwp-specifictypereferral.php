<?php

namespace Uncanny_Automator;

/**
 * Class AFFWP_SPECIFICTYPEREFERRAL
 *
 * @package Uncanny_Automator
 */
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
		//migrate old keys to new key
		add_action(
			'admin_init',
			function () {
				if ( 'yes' === get_option( 'affwp_insert_referral_migrated', 'no' ) ) {
					return;
				}
				global $wpdb;
				$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_value = %s WHERE meta_value = %s AND meta_key LIKE %s", 'affwp_insert_referral', 'affwp_complete_referral', 'add_action' ) );
				update_option( 'affwp_insert_referral_migrated', 'yes' );
			},
			99
		);
		$this->trigger_code = 'AFFWPREFERRAL';
		$this->trigger_meta = 'SPECIFICETYPEREF';
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
			'sentence'            => sprintf( __( 'An affiliate makes a referral of a {{specific type:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Affiliate WP */
			'select_option_name'  => __( 'An affiliate makes a referral of a {{specific type}}', 'uncanny-automator' ),
			'action'              => 'affwp_insert_referral',
			'priority'            => 99,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'affwp_insert_specific_type_referral' ),
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
					Automator()->helpers->recipe->affiliate_wp->options->get_referral_types( null, $this->trigger_meta, array( 'any_option' => true ) ),
				),
			)
		);
	}

	/**
	 * Processes our hook.
	 *
	 * @param $referral_id
	 */
	public function affwp_insert_specific_type_referral( $referral_id ) {
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_type      = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$referral           = affwp_get_referral( $referral_id );
		$type               = $referral->type;
		$user_id            = affwp_get_affiliate_user_id( $referral->affiliate_id );
		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( $type === $required_type[ $recipe_id ][ $trigger_id ] || intval( '-1' ) === intval( $required_type[ $recipe_id ][ $trigger_id ] ) ) {
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

							Automator()->db->token->save( 'referral', maybe_serialize( $referral ), $trigger_meta );
							Automator()->maybe_trigger_complete( $result['args'] );
							break;
						}
					}
				}
			}
		}
	}

}
