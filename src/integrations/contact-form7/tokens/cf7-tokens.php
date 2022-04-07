<?php

namespace Uncanny_Automator;

use WPCF7_ContactForm;
use WPCF7_Pipes;

/**
 * Class Cf7_Tokens
 *
 * @package Uncanny_Automator
 */
class Cf7_Tokens {

	public function __construct() {

		add_filter( 'automator_maybe_trigger_cf7_cf7forms_tokens', array( $this, 'cf7_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_cf7_cf7fields_tokens', array( $this, 'cf7_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_cf7_anoncf7forms_tokens', array( $this, 'cf7_possible_tokens' ), 20, 2 );

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_cf7_token' ), 20, 6 );

		// save submission to user meta
		add_action( 'automator_save_cf7_form', array( $this, 'automator_save_cf7_form_func' ), 20, 3 );
		add_action( 'automator_save_anon_cf7_form', array( $this, 'automator_save_cf7_form_func' ), 20, 3 );
	}

	/**
	 * @param WPCF7_ContactForm $contact_form
	 * @param                   $recipes
	 * @param                   $args
	 */
	public function automator_save_cf7_form_func( WPCF7_ContactForm $contact_form, $recipes, $args ) {
		if ( ! is_array( $args ) ) {
			return;
		}
		foreach ( $args as $trigger_result ) {
			if ( true !== $trigger_result['result'] ) {
				continue;
			}
			if ( ! $recipes || ! $contact_form instanceof WPCF7_ContactForm ) {
				continue;
			}
			foreach ( $recipes as $recipe ) {
				$triggers = $recipe['triggers'];
				if ( ! $triggers ) {
					continue;
				}
				foreach ( $triggers as $trigger ) {
					$trigger_id = $trigger['ID'];
					if ( ! array_key_exists( 'CF7FORMS', $trigger['meta'] ) && ! array_key_exists( 'CF7FIELDS', $trigger['meta'] ) && ! array_key_exists( 'ANONCF7FORMS', $trigger['meta'] ) ) {
						continue;
					}
					$meta_key_prefix = 'CF7FORMS_';
					if ( isset( $trigger['meta']['CF7FORMS'] ) ) {
						$form_id = (int) $trigger['meta']['CF7FORMS'];
					} elseif ( isset( $trigger['meta']['CF7FIELDS'] ) ) {
						$form_id = (int) $trigger['meta']['CF7FIELDS'];
					} elseif ( isset( $trigger['meta']['ANONCF7FORMS'] ) ) {
						$meta_key_prefix = 'ANONCF7FORMS_';
						$form_id         = (int) $trigger['meta']['ANONCF7FORMS'];
					}
					$data           = $this->get_data_from_contact_form( $contact_form );
					$user_id        = (int) $trigger_result['args']['user_id'];
					$trigger_log_id = (int) $trigger_result['args']['get_trigger_id'];
					$run_number     = (int) $trigger_result['args']['run_number'];
					$args           = array(
						'user_id'        => $user_id,
						'trigger_id'     => $trigger_id,
						'meta_key'       => $meta_key_prefix . $form_id,
						'meta_value'     => serialize( $data ),
						'run_number'     => $run_number,
						// get run number
						'trigger_log_id' => $trigger_log_id,
					);

					Automator()->insert_trigger_meta( $args );
				}//end foreach
			}//end foreach
		}//end foreach
	}

	/**
	 * @param WPCF7_ContactForm $contact_form
	 *
	 * @return array
	 */
	public function get_data_from_contact_form( WPCF7_ContactForm $contact_form ) {
		$data = array();
		if ( $contact_form instanceof WPCF7_ContactForm ) {
			$tags = $contact_form->scan_form_tags();
			foreach ( $tags as $tag ) {
				if ( empty( $tag->name ) ) {
					continue;
				}
				$array_data_types = apply_filters( 'automator_cf7_data_type_of_array', array( 'checkbox' ), $tag, $contact_form );
				if ( in_array( $tag->type, $array_data_types, true ) ) {
					$request_tag_name = automator_filter_input_array( $tag->name, INPUT_POST );
				} else {
					$request_tag_name = automator_filter_input( $tag->name, INPUT_POST );
				}

				$pipes = $tag->pipes;
				$value = ! empty( $request_tag_name ) ? Automator()->utilities->automator_sanitize( $request_tag_name, 'mixed' ) : '';

				if ( WPCF7_USE_PIPE && $pipes instanceof WPCF7_Pipes && ! $pipes->zero() ) {
					if ( is_array( $value ) ) {
						$new_value = array();

						foreach ( $value as $v ) {
							$new_value[] = $pipes->do_pipe( wp_unslash( $v ) );
						}

						$value = $new_value;
					} else {
						$value = $pipes->do_pipe( wp_unslash( $value ) );
					}
				}

				$data[ $tag->name ] = $value;
			}//end foreach
			return $data;
		}//end if
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function cf7_general_tokens( $tokens = array(), $args = array() ) {

		return $tokens;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function cf7_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$form_id      = absint( $args['value'] );
		$trigger_meta = $args['meta'];

		if ( empty( $form_id ) ) {
			return $tokens;
		}
		$contact_form7 = WPCF7_ContactForm::get_instance( $form_id );
		if ( ! $contact_form7 instanceof WPCF7_ContactForm ) {
			return $tokens;
		}

		$cf7_tags = $contact_form7->scan_form_tags();
		if ( $cf7_tags ) {
			$fields = array();
			foreach ( $cf7_tags as $tag ) {
				if ( empty( $tag->name ) ) {
					continue;
				}
				$input_id = $tag->name;
				// convert your-name to Your Name, your-email to Your Email
				$input_title = ucwords( str_replace( array( '-', '_' ), ' ', $tag->name ) );
				$token_id    = "$form_id|$input_id";
				$token_type  = 'text';
				if ( strpos( $tag->type, 'email' ) || 'email*' === $tag->type || 'email' === $tag->type ) {
					$token_type = 'email';
				}

				$fields[] = array(
					'tokenId'         => $token_id,
					'tokenName'       => $input_title,
					'tokenType'       => $token_type,
					'tokenIdentifier' => $trigger_meta,
				);
			}

			$tokens = array_merge( $tokens, $fields );
		}//end if

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
		$piece        = 'CF7FORMS';
		$piece_anon   = 'ANONCF7FORMS';
		$piece_fields = 'CF7FIELDS';

		if ( ! $pieces ) {
			return $value;
		}

		if ( ! in_array( $piece, $pieces ) && ! in_array( $piece_fields, $pieces ) && ! in_array( $piece_anon, $pieces ) && ! in_array( 'CF7SUBFIELD', $pieces ) && ! in_array( 'ANONCF7SUBFORM', $pieces ) ) {
			return $value;
		}

		if ( empty( $trigger_data ) ) {
			return $value;
		}
		// Render Form Name
		if ( isset( $pieces[2] ) && ( $piece === $pieces[2] || $piece_anon === $pieces[2] ) ) {
			foreach ( $trigger_data as $t_d ) {
				if ( empty( $t_d ) ) {
					continue;
				}
				if ( isset( $t_d['meta'][ $piece . '_readable' ] ) ) {
					return $t_d['meta'][ $piece . '_readable' ];
				}
				if ( isset( $t_d['meta'][ $piece_anon . '_readable' ] ) ) {
					return $t_d['meta'][ $piece_anon . '_readable' ];
				}
			}
		}
		// Render Form ID
		if ( isset( $pieces[2] ) && ( "{$piece}_ID" === $pieces[2] || "{$piece_anon}_ID" === $pieces[2] ) ) {
			foreach ( $trigger_data as $t_d ) {
				if ( empty( $t_d ) ) {
					continue;
				}
				if ( isset( $t_d['meta'][ $piece ] ) ) {
					return $t_d['meta'][ $piece ];
				}
				if ( isset( $t_d['meta'][ $piece_anon ] ) ) {
					return $t_d['meta'][ $piece_anon ];
				}
			}
		}

		// Render Form URL
		if ( isset( $pieces[2] ) && ( "{$piece}_URL" === $pieces[2] || "{$piece_anon}_URL" === $pieces[2] ) ) {
			foreach ( $trigger_data as $t_d ) {
				if ( empty( $t_d ) ) {
					continue;
				}
				if ( isset( $t_d['meta'][ $piece ] ) ) {
					return get_permalink( $t_d['meta'][ $piece ] );
				}
				if ( isset( $t_d['meta'][ $piece_anon ] ) ) {
					return get_permalink( $t_d['meta'][ $piece_anon ] );
				}
			}
		}

		foreach ( $trigger_data as $trigger ) {
			if ( empty( $trigger ) ) {
				continue;
			}
			$trigger_id     = absint( $trigger['ID'] );
			$trigger_log_id = absint( $replace_args['trigger_log_id'] );
			$prefix         = $pieces[1];
			$token_info     = explode( '|', $pieces[2] );
			$form_id        = absint( $token_info[0] );
			$meta_key       = $token_info[1];
			$meta_field     = $prefix . '_' . $form_id;
			$user_meta      = Automator()->helpers->recipe->get_form_data_from_trigger_meta( $meta_field, $trigger_id, $trigger_log_id, $user_id );

			if ( is_array( $user_meta ) && key_exists( trim( $meta_key ), $user_meta ) ) {
				if ( is_array( $user_meta[ $meta_key ] ) ) {
					$value = join( ', ', $user_meta[ $meta_key ] );
				} else {
					$value = $user_meta[ $meta_key ];
				}
			}
		}//end foreach

		if ( in_array( 'CF7SUBFIELD', $pieces ) ) {
			if ( $trigger_data ) {
				foreach ( $trigger_data as $trigger ) {
					if ( array_key_exists( 'CF7SUBFIELD', $trigger['meta'] ) && isset( $trigger['meta'][ $pieces[2] ] ) ) {
						$value = $trigger['meta'][ $pieces[2] ];
					}
				}
			}
		}

		return $value;
	}
}
