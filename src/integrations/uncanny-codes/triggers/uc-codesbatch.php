<?php

namespace Uncanny_Automator;

/**
 * Class UC_CODESBATCH
 *
 * @package Uncanny_Automator
 */
class UC_CODESBATCH {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UNCANNYCODE';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'UCBATCH';
		$this->trigger_meta = 'UNCANNYCODESBATCH';

		// Batch names are not available before version 4
		if ( floatval( UNCANNY_LEARNDASH_CODES_VERSION ) >= 4 ) {
			$this->define_trigger();
		}
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/uncanny-codes/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Logged-in trigger - Uncanny Codes */
			'sentence'            => sprintf( esc_attr__( 'A user redeems a code from {{a batch:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Uncanny Codes */
			'select_option_name'  => esc_attr__( 'A user redeems a code from {{a batch}}', 'uncanny-automator' ),
			'action'              => 'ulc_user_redeemed_code',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'user_redeemed_code_batch' ),
			'options'             => array(
				Automator()->helpers->recipe->uncanny_codes->options->get_all_code_batch( esc_attr__( 'Batch', 'uncanny-automator' ), $this->trigger_meta ),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @param $user_id
	 * @param $coupon_id
	 * @param $result
	 */
	public function user_redeemed_code_batch( $user_id, $coupon_id, $result ) {
		global $wpdb;
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( empty( $user_id ) ) {
			return;
		}

		$recipes        = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_batch = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		if ( empty( $recipes ) ) {
			return;
		}
		if ( empty( $required_batch ) ) {
			return;
		}
		$batch              = $wpdb->get_var( $wpdb->prepare( "SELECT g.id FROM `{$wpdb->prefix}uncanny_codes_groups` g LEFT JOIN `{$wpdb->prefix}uncanny_codes_codes` c ON g.ID = c.code_group WHERE c.ID = %d", $coupon_id ) );
		$matched_recipe_ids = array();
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = absint( $trigger['ID'] );
				if ( ! isset( $required_batch[ $recipe_id ] ) ) {
					continue;
				}
				if ( ! isset( $required_batch[ $recipe_id ][ $trigger_id ] ) ) {
					continue;
				}
				if ( intval( '-1' ) === intval( $required_batch[ $recipe_id ][ $trigger_id ] ) || (int) $batch === (int) $required_batch[ $recipe_id ][ $trigger_id ] ) {
					$matched_recipe_ids[ $recipe_id ] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}
		if ( empty( $matched_recipe_ids ) ) {
			return;
		}
		foreach ( $matched_recipe_ids as $matched_recipe_id ) {
			$pass_args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'ignore_post_id'   => true,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
				'user_id'          => $user_id,
				'is_signed_in'     => true,
			);

			$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

			if ( ! empty( $args ) ) {
				foreach ( $args as $result ) {
					if ( true === $result['result'] ) {

						$trigger_meta = array(
							'user_id'        => $user_id,
							'trigger_id'     => $result['args']['trigger_id'],
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						);

						$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':' . $this->trigger_meta;
						$trigger_meta['meta_value'] = maybe_serialize( $batch );
						Automator()->insert_trigger_meta( $trigger_meta );

						Automator()->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}
	}
}
