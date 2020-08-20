<?php

namespace Uncanny_Automator;


use Forminator_API;

/**
 * Class Fr_Tokens
 *
 * @package Uncanny_Automator
 */
class Fr_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'FR';

	public function __construct() {
		//*************************************************************//
		// See this filter generator AT automator-get-data.php
		// in function recipe_trigger_tokens()
		//*************************************************************//

		add_filter( 'automator_maybe_trigger_fr_frform_tokens', [ $this, 'fr_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'fr_token' ], 20, 6 );

		// Save latest form entry in trigger meta for tokens.
		add_action( 'automator_save_forminator_form_entry', [ $this, 'fr_save_form_entry' ], 10, 3 );
	}

	/**
	 * Only load this integration and its triggers and actions if the related
	 * plugin is active
	 *
	 * @param bool $status status of plugin.
	 * @param string $plugin plugin code.
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {

		if ( self::$integration === $plugin ) {
			if ( class_exists( 'Forminator' ) ) {
				$status = true;
			} else {
				$status = false;
			}
		}

		return $status;
	}

	/**
	 * Prepare tokens.
	 *
	 * @param array $tokens .
	 * @param array $args .
	 *
	 * @return array
	 */
	public function fr_possible_tokens( $tokens = [], $args = [] ) {
		$form_id      = $args['value'];
		$trigger_meta = $args['meta'];

		if ( ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {
			$form_meta = Forminator_API::get_form_fields( $form_id );
			if ( isset( $form_meta ) && ! empty( $form_meta ) ) {
				$fields = [];
				foreach ( $form_meta as $field ) {
					$input_id    = $field->slug;
					$input_title = $field->raw['field_label'];
					$token_id    = "$form_id|$input_id";
					$fields[]    = [
						'tokenId'         => $token_id,
						'tokenName'       => $input_title,
						'tokenType'       => $field->raw['type'],
						'tokenIdentifier' => $trigger_meta,
					];
				}

				$tokens = array_merge( $tokens, $fields );
			}
		}


		return $tokens;
	}

	/**
	 * Parse the token.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return null|string
	 */
	public function fr_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$piece = 'FRFORM';
		if ( $pieces ) {
			if ( in_array( $piece, $pieces ) ) {
				global $uncanny_automator;
				$recipe_log_id = isset( $replace_args['recipe_log_id'] ) ? (int) $replace_args['recipe_log_id'] : $uncanny_automator->maybe_create_recipe_log_entry( $recipe_id, $user_id )['recipe_log_id'];
				if ( $trigger_data && $recipe_log_id ) {
					foreach ( $trigger_data as $trigger ) {
						if ( key_exists( $piece, $trigger['meta'] ) ) {
							$trigger_id     = $trigger['ID'];
							$trigger_log_id = $replace_args['trigger_log_id'];
							$token_info     = explode( '|', $pieces[2] );
							$form_id        = $token_info[0];
							$meta_key       = $token_info[1];
							$meta_field     = $piece . '_' . $form_id;
							$entry_id       = $uncanny_automator->helpers->recipe->get_form_data_from_trigger_meta( $meta_field, $trigger_id, $trigger_log_id, $user_id );
							if ( ! empty( $entry_id ) ) {
								$entry = Forminator_API::get_entry( $form_id, $entry_id );
								$value = $entry->get_meta( $meta_key );
							}
						}
					}
				}
			}
		}

		return $value;
	}

	/**
	 * Save form entry in meta.
	 *
	 * @param $form_id
	 * @param $recipes
	 * @param $args
	 *
	 * @return null|string
	 */
	public function fr_save_form_entry( $form_id, $recipes, $args ) {
		if ( is_array( $args ) ) {
			foreach ( $args as $trigger_result ) {
				if ( true === $trigger_result['result'] ) {
					global $uncanny_automator;
					if ( $recipes && absint( $form_id ) > 0 ) {
						foreach ( $recipes as $recipe ) {
							$triggers = $recipe['triggers'];
							if ( $triggers ) {
								foreach ( $triggers as $trigger ) {
									$trigger_id = $trigger['ID'];
									if ( ! key_exists( 'FRFORM', $trigger['meta'] ) ) {
										continue;
									} else {
										// Only form entry id will be saved.
										$form_entry        = forminator_get_latest_entry_by_form_id( $form_id );
										$data              = $form_entry->entry_id;
										$user_id           = (int) $trigger_result['args']['user_id'];
										$recipe_log_id_raw = isset( $trigger_result['args']['recipe_log_id'] ) ? (int) $trigger_result['args']['recipe_log_id'] : $uncanny_automator->maybe_create_recipe_log_entry( $recipe['ID'], $user_id );
										if ( $recipe_log_id_raw ) {
											$trigger_log_id = (int) $trigger_result['args']['get_trigger_id'];
											$run_number     = (int) $trigger_result['args']['run_number'];
											$args           = [
												'user_id'        => $user_id,
												'trigger_id'     => $trigger_id,
												'meta_key'       => 'FRFORM_' . $form_id,
												'meta_value'     => $data,
												'run_number'     => $run_number, //get run number
												'trigger_log_id' => $trigger_log_id,
											];

											$uncanny_automator->insert_trigger_meta( $args );
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}

}