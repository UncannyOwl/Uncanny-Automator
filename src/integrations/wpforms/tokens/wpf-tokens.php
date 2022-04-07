<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use WPForms_Form_Handler;

/**
 * Class Wpf_Tokens
 *
 * @package Uncanny_Automator
 */
class Wpf_Tokens {

	/**
	 * Wpf_Tokens constructor.
	 */
	public function __construct() {

		add_filter( 'automator_maybe_trigger_wpf_anonwpfforms_tokens', array( $this, 'wpf_possible_tokens' ), 20, 2 );
		add_filter(
			'automator_maybe_trigger_wpf_wpfforms_tokens',
			array(
				$this,
				'wpf_possible_tokens',
			),
			2000,
			2
		);
		add_filter( 'automator_maybe_parse_token', array( $this, 'wpf_token' ), 20, 6 );
		add_action( 'automator_save_wp_form', array( $this, 'wpf_form_save_entry' ), 20, 4 );
		add_action( 'automator_save_anon_wp_form', array( $this, 'wpf_form_save_entry' ), 20, 4 );
		// Entry tokens
		add_filter( 'automator_maybe_trigger_wpf_tokens', array( $this, 'wpf_entry_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'wpf_entry_tokens' ), 20, 6 );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function wpf_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$form_id      = $args['value'];
		$trigger_meta = $args['meta'];
		$form_ids     = array();
		$wpforms      = new WPForms_Form_Handler();
		if ( ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {
			$form = $wpforms->get( $form_id );
			if ( $form ) {
				$form_ids[] = $form->ID;
			}
		}

		if ( empty( $form_ids ) ) {
			return $tokens;
		}
		$allowed_token_types    = array(
			'url',
			'email',
			'float',
			'int',
			'text',
			'file-upload',
		);
		$disallowed_field_types = apply_filters(
			'automator_wpforms_disallowed_fields',
			array(
				'pagebreak',
				'password',
				'divider',
				'entry-preview',
				'html',
				'stripe-credit-card',
				'authorize_net',
				'square',
			),
			$form_ids
		);
		foreach ( $form_ids as $form_id ) {
			$fields = array();
			$form   = $wpforms->get( $form_id );
			$meta   = wpforms_decode( $form->post_content );
			if ( ! isset( $meta['fields'] ) ) {
				continue;
			}
			if ( ! is_array( $meta['fields'] ) ) {
				continue;
			}
			foreach ( $meta['fields'] as $field ) {
				if ( in_array( (string) $field['type'], $disallowed_field_types, true ) ) {
					continue;
				}
				$input_id    = $field['id'];
				$input_title = isset( $field['label'] ) ? $field['label'] : sprintf( '%d- %s', $field['id'], __( 'No name', 'uncanny-automator' ) );
				$token_id    = "$form_id|$input_id";
				$fields[]    = array(
					'tokenId'         => $token_id,
					'tokenName'       => $input_title,
					'tokenType'       => in_array( $field['type'], $allowed_token_types, true ) ? $field['type'] : 'text',
					'tokenIdentifier' => $trigger_meta,
				);
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
	 * @return null|string
	 */
	public function wpf_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( empty( $pieces ) ) {
			return $value;
		}
		if ( ! in_array( 'WPFFORMS', $pieces, true ) && ! in_array( 'ANONWPFFORMS', $pieces, true )
		     && ! in_array( 'ANONWPFSUBFORM', $pieces, true ) ) {
			return $value;
		}

		$trigger_id   = $pieces[0];
		$trigger_meta = $pieces[1];
		$field        = $pieces[2];
		// Form title
		if ( 'WPFFORMS' === $field || 'ANONWPFFORMS' === $field ) {
			if ( $trigger_data ) {
				foreach ( $trigger_data as $trigger ) {
					if ( array_key_exists( $field . '_readable', $trigger['meta'] ) ) {
						return $trigger['meta'][ $field . '_readable' ];
					}
				}
			}
		}

		// Form ID
		if ( 'WPFFORMS_ID' === $field ) {
			if ( $trigger_data ) {
				foreach ( $trigger_data as $trigger ) {
					if ( array_key_exists( 'WPFFORMS', $trigger['meta'] ) ) {
						return $trigger['meta']['WPFFORMS'];
					}
				}
			}
		}

		if ( 'ANONWPFFORMS_ID' === $field ) {
			if ( $trigger_data ) {
				foreach ( $trigger_data as $trigger ) {
					if ( array_key_exists( 'ANONWPFFORMS', $trigger['meta'] ) ) {
						return $trigger['meta']['ANONWPFFORMS'];
					}
				}
			}
		}

		$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;
		$parse_tokens   = array(
			'trigger_id'     => $trigger_id,
			'trigger_log_id' => $trigger_log_id,
			'user_id'        => $user_id,
		);

		$meta_key = sprintf( '%d:%s', $pieces[0], $pieces[1] );
		$entry    = Automator()->db->trigger->get_token_meta( $meta_key, $parse_tokens );

		if ( empty( $entry ) ) {
			return $value;
		}
		$to_match = "{$trigger_id}:{$trigger_meta}:{$field}";
		if ( is_array( $entry ) && key_exists( $to_match, $entry ) ) {
			$value = $entry[ $to_match ];

		}

		return $value;
	}

	/**
	 * @param $fields
	 * @param $form_data
	 * @param $recipes
	 * @param $args
	 *
	 * @return void
	 */
	public function wpf_form_save_entry( $fields, $form_data, $recipes, $args ) {
		if ( ! is_array( $args ) ) {
			return;
		}
		foreach ( $args as $trigger_result ) {
			if ( true !== $trigger_result['result'] ) {
				continue;
			}

			if ( ! $recipes ) {
				continue;
			}
			foreach ( $recipes as $recipe ) {
				$triggers = $recipe['triggers'];
				if ( ! $triggers ) {
					continue;
				}
				foreach ( $triggers as $trigger ) {
					$trigger_id = $trigger['ID'];
					if ( ! array_key_exists( 'WPFFORMS', $trigger['meta'] ) && ! array_key_exists( 'ANONWPFFORMS', $trigger['meta'] ) ) {
						continue;
					}
					$trigger_args = $trigger_result['args'];
					$meta_key     = sprintf( '%d:%s', $trigger_id, $trigger_args['meta'] );
					$form_id      = $form_data['id'];
					$data         = array();
					foreach ( $fields as $field ) {
						$field_id     = $field['id'];
						$key          = "{$meta_key}:{$form_id}|{$field_id}";
						$data[ $key ] = $field['value'];
					}

					$user_id        = (int) $trigger_result['args']['user_id'];
					$trigger_log_id = (int) $trigger_result['args']['trigger_log_id'];
					$run_number     = (int) $trigger_result['args']['run_number'];

					$args = array(
						'user_id'        => $user_id,
						'trigger_id'     => $trigger_id,
						'meta_key'       => $meta_key,
						'meta_value'     => maybe_serialize( $data ),
						'run_number'     => $run_number, //get run number
						'trigger_log_id' => $trigger_log_id,
					);

					Automator()->insert_trigger_meta( $args );
				}
			}
		}
	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed|\string[][]
	 */
	public function wpf_entry_possible_tokens( $tokens = array(), $args = array() ) {
		$fields = array(
			array(
				'tokenId'         => 'WPFENTRYID',
				'tokenName'       => __( 'Entry ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPFENTRYTOKENS',
			),
			array(
				'tokenId'         => 'WPFENTRYIP',
				'tokenName'       => __( 'User IP', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPFENTRYTOKENS',
			),
			array(
				'tokenId'         => 'WPFENTRYDATE',
				'tokenName'       => __( 'Entry submission date', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPFENTRYTOKENS',
			),
		);

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
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
	public function wpf_entry_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( in_array( 'WPFENTRYTOKENS', $pieces ) ) {
			if ( $trigger_data ) {
				foreach ( $trigger_data as $trigger ) {
					$trigger_id     = $trigger['ID'];
					$trigger_log_id = $replace_args['trigger_log_id'];
					$meta_key       = $pieces[2];
					$meta_value     = Automator()->helpers->recipe->get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id );
					if ( ! empty( $meta_value ) ) {
						$value = maybe_unserialize( $meta_value );
					}
				}
			}
		}

		return $value;
	}
}
