<?php

namespace Uncanny_Automator;

/**
 * Class UC_CODESSUFFIX
 *
 * @package Uncanny_Automator
 */
class UC_CODESSUFFIX {

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
		$this->trigger_code = 'UCSUFFIX';
		$this->trigger_meta = 'UNCANNYCODESSUFFIX';
		$this->define_trigger();
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
			'sentence'            => sprintf( esc_attr__( 'A user redeems a code with a {{specific:%1$s}} suffix', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Uncanny Codes */
			'select_option_name'  => esc_attr__( 'A user redeems a code with a {{specific}} suffix', 'uncanny-automator' ),
			'action'              => 'ulc_user_redeemed_code',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'user_redeemed_code_suffix' ),
			'options'             => array(
				Automator()->helpers->recipe->uncanny_codes->options->get_all_code_suffix( esc_attr__( 'Suffix', 'uncanny-automator' ), $this->trigger_meta ),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * @param $user_id
	 * @param $coupon_id
	 * @param $result
	 */
	public function user_redeemed_code_suffix( $user_id, $coupon_id, $result ) {
		global $wpdb;
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( empty( $user_id ) ) {
			return;
		}

		$recipes         = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_suffix = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta . '_readable' );

		$suffix = $wpdb->get_var( $wpdb->prepare( "SELECT g.suffix FROM `{$wpdb->prefix}uncanny_codes_groups` g LEFT JOIN `{$wpdb->prefix}uncanny_codes_codes` c ON g.ID = c.code_group WHERE c.ID = %d", $coupon_id ) );

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( isset( $required_suffix[ $recipe_id ] ) && isset( $required_suffix[ $recipe_id ][ $trigger_id ] ) ) {
					if ( (string) $suffix === (string) $required_suffix[ $recipe_id ][ $trigger_id ] ) {
						if ( ! Automator()->is_recipe_completed( $recipe_id, $user_id ) ) {
							$pass_args = array(
								'code'             => $this->trigger_code,
								'meta'             => $this->trigger_meta,
								'ignore_post_id'   => true,
								'user_id'          => $user_id,
								'recipe_to_match'  => $recipe_id,
								'trigger_to_match' => $trigger_id,
							);

							$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

							if ( isset( $args ) ) {
								foreach ( $args as $result ) {
									if ( true === $result['result'] ) {

										$trigger_meta = array(
											'user_id'    => $user_id,
											'trigger_id' => $result['args']['trigger_id'],
											'trigger_log_id' => $result['args']['get_trigger_id'],
											'run_number' => $result['args']['run_number'],
										);

										$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':' . $this->trigger_meta;
										$trigger_meta['meta_value'] = maybe_serialize( $suffix );
										Automator()->insert_trigger_meta( $trigger_meta );

										Automator()->maybe_trigger_complete( $result['args'] );
									}
								}
							}
						}
					}
				}
			}
		}

		return;

	}
}
