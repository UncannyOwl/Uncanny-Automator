<?php

namespace Uncanny_Automator;

/**
 * Class Cf7_Tokens
 * @package uncanny_automator
 */
class Cf7_Tokens {


	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'CF7';

	public function __construct() {

		add_filter( 'automator_maybe_trigger_cf7_cf7forms_tokens', [ $this, 'cf7_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_trigger_cf7_cf7fields_tokens', [ $this, 'cf7_possible_tokens' ], 20, 2 );

		add_filter( 'automator_maybe_parse_token', [ $this, 'parse_cf7_token' ], 20, 6 );

		//save submission to user meta
		add_action( 'automator_save_cf7_form', [ $this, 'automator_save_cf7_form_func' ], 20, 3 );
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $code
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $code ) {

		if ( self::$integration === $code ) {
			if ( class_exists( 'WPCF7' ) ) {
				$status = true;
			} else {
				$status = false;
			}
		}

		return $status;
	}

	/**
	 * @param \WPCF7_ContactForm $contact_form
	 * @param $recipes
	 * @param $args
	 */
	public function automator_save_cf7_form_func( \WPCF7_ContactForm $contact_form, $recipes, $args ) {
		if ( is_array( $args ) ) {
			foreach( $args as $trigger_result ) {
				//$trigger_result = array_pop( $args );
				if ( TRUE === $trigger_result['result'] ) {
					global $uncanny_automator;
					if ( $recipes && $contact_form instanceof \WPCF7_ContactForm ) {
						foreach ( $recipes as $recipe ) {
							$triggers = $recipe['triggers'];
							if ( $triggers ) {
								foreach ( $triggers as $trigger ) {
									$trigger_id = $trigger['ID'];
									if ( ! key_exists( 'CF7FORMS', $trigger['meta'] ) ) {
										continue;
									} else {
										$form_id = (int) $trigger['meta']['CF7FORMS'];
										$data    = $this->get_data_from_contact_form( $contact_form );
										$user_id = (int) $trigger_result['args']['user_id'];
										if ( $user_id ) {
											$recipe_log_id_raw = $uncanny_automator->maybe_create_recipe_log_entry( $recipe['ID'], $user_id );
											if ( is_array( $recipe_log_id_raw ) && key_exists( 'recipe_log_id', $recipe_log_id_raw ) ) {
												//$recipe_log_id  = absint( $recipe_log_id_raw['recipe_log_id'] );
												$trigger_log_id = (int) $trigger_result['args']['get_trigger_id'];
												$run_number     = (int) $trigger_result['args']['run_number'];
												
												$args = [
													'user_id'        => $user_id,
													'trigger_id'     => $trigger_id,
													'meta_key'       => 'CF7FORMS_' . $form_id,
													'meta_value'     => serialize( $data ),
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

	/**
	 * @param \WPCF7_ContactForm $contact_form
	 *
	 * @return array
	 */
	public function get_data_from_contact_form( \WPCF7_ContactForm $contact_form ) {
		$data = [];
		if ( $contact_form instanceof \WPCF7_ContactForm ) {
			$tags = $contact_form->scan_form_tags();
			global $uncanny_automator;
			foreach ( $tags as $tag ) {
				if ( empty( $tag->name ) ) {
					continue;
				}

				$pipes = $tag->pipes;

				$value = ( ! empty( $_POST[ $tag->name ] ) ) ? $uncanny_automator->uap_sanitize( $_POST[ $tag->name ], 'mixed' ) : '';
				if ( WPCF7_USE_PIPE && $pipes instanceof \WPCF7_Pipes && ! $pipes->zero() ) {
					if ( is_array( $value ) ) {
						$new_value = [];

						foreach ( $value as $v ) {
							$new_value[] = $pipes->do_pipe( wp_unslash( $v ) );
						}

						$value = $new_value;
					} else {
						$value = $pipes->do_pipe( wp_unslash( $value ) );
					}
				}

				$data[ $tag->name ] = $value;
			}

			return $data;
		}
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function cf7_general_tokens( $tokens = [], $args = [] ) {

		return $tokens;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function cf7_possible_tokens( $tokens = [], $args = [] ) {
		$form_id      = absint( $args['value'] );
		$trigger_meta = $args['meta'];

		if ( empty( $form_id ) ) {
			return $tokens;
		}
		$contact_form7 = \WPCF7_ContactForm::get_instance( $form_id );
		if ( ! $contact_form7 instanceof \WPCF7_ContactForm ) {
			return $tokens;
		}

		$cf7_tags = $contact_form7->scan_form_tags();
		if ( $cf7_tags ) {
			$fields = [];
			foreach ( $cf7_tags as $tag ) {
				if ( empty( $tag->name ) ) {
					continue;
				}
				$input_id = $tag->name;
				//convert your-name to Your Name, your-email to Your Email
				$input_title = ucwords( str_replace( [ '-', '_' ], ' ', $tag->name ) );
				$token_id    = "$form_id|$input_id";
				$token_type  = 'text';
				if ( strpos( $tag->type, 'email' ) || 'email*' === $tag->type || 'email' === $tag->type ) {
					$token_type = 'email';
				}

				$fields[] = [
					'tokenId'         => $token_id,
					'tokenName'       => $input_title,
					'tokenType'       => $token_type,
					'tokenIdentifier' => $trigger_meta,
				];
			}

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed
	 */
	public function parse_cf7_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$piece = 'CF7FORMS';
		if ( $pieces ) {
			if ( in_array( $piece, $pieces ) ) {
				global $uncanny_automator;
				$recipe_log_id = $uncanny_automator->maybe_create_recipe_log_entry( $recipe_id, $user_id )['recipe_log_id'];

				if ( $trigger_data && $recipe_log_id ) {
					foreach ( $trigger_data as $trigger ) {
						if ( key_exists( $piece, $trigger['meta'] ) ) {
							$trigger_id     = $trigger['ID'];
							$trigger_log_id = $replace_args['trigger_log_id'];
							$token_info     = explode( '|', $pieces[2] );
							$form_id        = $token_info[0];
							$meta_key       = $token_info[1];
							$meta_field     = $piece . '_' . $form_id;
							$user_meta      = $this->get_form_data_from_trigger_meta( $user_id, $meta_field, $trigger_id, $trigger_log_id );
							if ( is_array( $user_meta ) && key_exists( trim( $meta_key ), $user_meta ) ) {
								if ( is_array( $user_meta[ $meta_key ] ) ) {
									$value = join( ', ', $user_meta[ $meta_key ] );
								} else {
									$value = $user_meta[ $meta_key ];
								}
							}
						}
					}
				}
			}
		}

		return $value;
	}

	/**
	 * @param $user_id
	 * @param $meta_key
	 * @param $trigger_id
	 * @param $trigger_log_id
	 *
	 * @return mixed|string
	 */
	public function get_form_data_from_trigger_meta( $user_id, $meta_key, $trigger_id, $trigger_log_id ) {
		global $wpdb;
		if ( empty( $user_id ) || empty( $meta_key ) || empty( $trigger_id ) || empty( $trigger_log_id ) ) {
			return '';
		}

		$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE user_id = %d AND meta_key = %s AND automator_trigger_id = %d AND automator_trigger_log_id = %d ORDER BY ID DESC LIMIT 0,1", $user_id, $meta_key, $trigger_id, $trigger_log_id ) );
		if ( ! empty( $meta_value ) ) {
			return maybe_unserialize( $meta_value );
		}

		return '';
	}

}