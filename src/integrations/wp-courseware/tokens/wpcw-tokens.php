<?php

namespace Uncanny_Automator;


/**
 * Class Wpcw_Tokens
 * @package Uncanny_Automator
 */
class Wpcw_Tokens {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPCW';

	public function __construct() {
		add_filter( 'automator_maybe_parse_token', [ $this, 'wpcw_token' ], 20, 6 );
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 *
	 * @return string|null
	 */
	public function wpcw_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$piece = 'WPCW_MODULE';
		if ( $pieces ) {
			if ( in_array( $piece, $pieces, true ) ) {

				$recipe_log_id = Automator()->maybe_create_recipe_log_entry( $recipe_id, $user_id )['recipe_log_id'];
				if ( $trigger_data && $recipe_log_id ) {
					foreach ( $trigger_data as $trigger ) {
						if ( key_exists( $piece, $trigger['meta'] ) ) {
							$trigger_id     = $trigger['ID'];
							$trigger_log_id = $replace_args['trigger_log_id'];
							$meta_key       = $pieces[2];
							$meta_value     = Automator()->helpers->recipe->get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id );
							if ( ! empty( $meta_value ) && is_numeric( $meta_value ) ) {
								if ( function_exists( 'wpcw_get_module' ) ) {
									$module = wpcw_get_module( $meta_value );
									if ( ! empty( $module ) ) {
										$value = $module->module_title;
									}
								}
							}
						}
					}
				}
			}
		}

		return $value;
	}
}
