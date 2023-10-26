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
	 *
	 * @return void
	 */
	public function __construct() {

		// Token parser.
		add_filter( 'automator_maybe_parse_token', array( $this, 'wpf_token' ), 20, 6 );

		// Saves the value of the tokens for later use.
		add_action( 'automator_save_wp_form', array( $this, 'wpf_form_save_entry' ), 40, 4 );
		add_action( 'automator_save_anon_wp_form', array( $this, 'wpf_form_save_entry' ), 40, 4 );

		// Constructs entry tokens.
		add_filter( 'automator_maybe_trigger_wpf_anonwpfforms_tokens', array( $this, 'wpf_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_wpf_wpfforms_tokens', array( $this, 'wpf_possible_tokens' ), 2000, 2 );
		add_filter( 'automator_maybe_trigger_wpf_tokens', array( $this, 'wpf_entry_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'wpf_entry_tokens' ), 20, 6 );
	}

	/**
	 * Possible tokens.
	 *
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
		$allowed_token_types = array(
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

				// Added support for multiple option (label).
				if ( isset( $field['choices'] ) ) {
					$fields[] = array(
						'tokenId'         => $token_id . '|label',
						'tokenName'       => sprintf( '%s %s', $input_title, esc_attr__( '(label)', 'uncanny-automator' ) ),
						'tokenType'       => in_array( $field['type'], $allowed_token_types, true ) ? $field['type'] : 'text',
						'tokenIdentifier' => $trigger_meta,
					);
				}
			}

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	/**
	 * Maybe Parse Token.
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
	public function wpf_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( empty( $pieces ) ) {
			return $value;
		}

		if ( ! in_array( 'WPFFORMS', $pieces, true )
			&& ! in_array( 'ANONWPFFORMS', $pieces, true )
			&& ! in_array( 'ANONWPFSUBFORM', $pieces, true ) ) {
			return $value;
		}

		$trigger_id   = $pieces[0];
		$trigger_meta = $pieces[1];
		$field        = $pieces[2];

		// Anon Form Title and ID.
		if ( 'ANONWPFFORMS' === $field || 'ANONWPFFORMS_ID' === $field ) {
			if ( ! empty( $trigger_data ) ) {
				foreach ( $trigger_data as $trigger ) {
					if ( (int) $trigger['ID'] === (int) $trigger_id ) {
						if ( array_key_exists( 'ANONWPFFORMS', $trigger['meta'] ) ) {
							$form_id = $trigger['meta']['ANONWPFFORMS'];
							if ( 'ANONWPFFORMS_ID' === $field ) {
								return $form_id;
							} elseif ( array_key_exists( $field . '_readable', $trigger['meta'] ) ) {
								return $trigger['meta'][ $field . '_readable' ];
							}
						}
					}
				}
			}
		}

		$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;

		$parse_tokens = array(
			'trigger_id'     => $trigger_id,
			'trigger_log_id' => $trigger_log_id,
			'user_id'        => $user_id,
		);

		$meta_key = sprintf( '%d:%s', $pieces[0], $pieces[1] );

		// Fetches the specific entry.
		$entry = Automator()->db->trigger->get_token_meta( $meta_key, $parse_tokens );

		if ( empty( $entry ) ) {
			return $value;
		}

		$to_match = "{$trigger_id}:{$trigger_meta}:{$field}";

		if ( is_array( $entry ) && key_exists( $to_match, $entry ) ) {
			$value = $entry[ $to_match ];
		}

		$token_info = explode( '|', $pieces[2] );

		// Get the form.
		list( $form_id, $field_id ) = $token_info;

		// Get Field config.
		$form       = $this->get_wpforms_form( absint( $form_id ) );
		$field      = $form['fields'][ $field_id ];
		$field_type = $form['fields'][ $field_id ]['type'];

		// Fetch label if needed.
		if ( $this->should_fetch_label( $token_info ) ) {
			$value = $this->get_field_label( $field, $entry, $to_match );
		} else {
			// Populate non-dynamic choices index(es).
			if ( ! empty( $field['choices'] ) && empty( $field['dynamic_choices'] ) ) {
				$value = $this->get_non_dynamic_choice_index( $value, $field );
				// Populate values for dynamic choices if show values is enabled.
				if ( isset( $field['show_values'] ) && absint( $field['show_values'] ) > 0 ) {
					$value = $this->populate_choice_index_show_values( $value, $field );
				}
			}
		}

		// Check for WPForms Lite.
		if ( class_exists( 'WPForms_Lite' ) ) {
			// Check for Pro Only Fields.
			if ( $this->is_pro_field( $field_type ) ) {
				$value = __( 'This token requires WPForms Pro', 'uncanny-automator' );
			}
		}

		// Flatten array with value index ( File Uploads etc. ).
		if ( is_array( $value ) ) {
			$value_string = '';
			// Simple array with value key.
			if ( key_exists( 'value', $value ) ) {
				$value_string .= $value['value'];
			} else {
				// Multi - Level arrays File Uploads etc.
				foreach ( $value as $key => $item ) {
					if ( isset( $item['value'] ) ) {
						$value_string .= $item['value'] . ' ';
					}
				}
			}

			$value = ! empty( $value_string ) ? trim( $value_string ) : $value;
		}

		return $value;
	}

	/**
	 * Save form entry.
	 *
	 * @param $fields
	 * @param $form_data
	 * @param $recipes
	 * @param $args
	 *
	 * @return void
	 */
	public function wpf_form_save_entry( $fields, $form_data, $recipes, $args ) {

		if ( ! is_array( $args ) || empty( $fields ) ) {
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

						if ( ! is_array( $field ) ) {
							continue;
						}

						$field_id = $field['id'];
						$key      = "{$meta_key}:{$form_id}|{$field_id}";

						// Use `value_raw` if available, otherwise the normal `value`.
						$data[ $key ] = isset( $field['value_raw'] )
							? $field['value_raw']
							// Otherwise, use the value.
							: $field['value'];

						// Separate checkbox and select.
						if ( in_array( $field['type'], array( 'checkbox', 'select' ), true ) ) {
							$field_value = isset( $field['value_raw'] ) ? $field['value_raw'] : $field['value'];
							$choices     = explode( PHP_EOL, $field_value );

							if ( is_array( $choices ) ) {
								$data[ $key ] = implode( ', ', $choices );
							}
						}

						// Maybe add spaces after commas.
						if ( isset( $field['dynamic_items'] ) || ( strpos( $field['type'], 'payment' ) === 0 && 'payment-single' !== $field['type'] ) ) {
							$data[ $key ] = str_replace( ',', ', ', $data[ $key ] );
							// Remove any double spaces this may have caused.
							$data[ $key ] = preg_replace( '/\s+/', ' ', $data[ $key ] );
						}
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
	 * Add Possible Tokens.
	 *
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
				'tokenType'       => 'int',
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
	 * Maybe Parse Entry Tokens.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 *
	 * @return string|null
	 */
	public function wpf_entry_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( in_array( 'WPFENTRYTOKENS', $pieces, true ) ) {
			if ( $trigger_data ) {
				foreach ( $trigger_data as $trigger ) {
					$trigger_id     = $trigger['ID'];
					$trigger_log_id = $replace_args['trigger_log_id'];
					$meta_key       = $pieces[2];
					$meta_value     = Automator()->helpers->recipe->get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id );
					if ( class_exists( 'WPForms_Lite' ) ) {
						$meta_value = __( 'This token requires WPForms Pro', 'uncanny-automator' );
					}
					if ( ! empty( $meta_value ) ) {
						$value = maybe_unserialize( $meta_value );
					}
				}
			}
		}

		return $value;
	}

	/**
	 * Retrieves the field choice label.
	 *
	 * @param array $field
	 * @param array $entry
	 * @param string $to_match
	 *
	 * @return string
	 */
	private function get_field_label( $field = array(), $entry = array(), $to_match = '' ) {

		// Get the entry.
		$entry_choice = $entry[ str_replace( '|label', '', $to_match ) ];

		// Bail if no selection.
		if ( empty( $entry_choice ) ) {
			return '';
		}

		// Check if there are choices.
		if ( ! empty( $field['choices'] ) ) {

			// Dynamic field check.
			if ( isset( $field['dynamic_choices'] ) && ! empty( $field['dynamic_choices'] ) ) {
				return $this->handle_dynamic_select_labels( $field, $entry_choice );
			}

			// Payment field check.
			if ( 0 === strpos( $field['type'], 'payment' ) ) {
				return $this->handle_payment_labels( $field, $entry_choice );
			}

			// All other non dynamic fields.
			return $this->handle_non_dynamic_labels( $field, $entry_choice );
		}

		// No choices.
		return '';
	}

	/**
	 * Determines if the field should be parsed as label.
	 *
	 * @param array $token_info The token info.
	 *
	 * @return bool Returns true if length is 3 and has label flag. Returns false, otherwise.
	 */
	private function should_fetch_label( $token_info = array() ) {
		return 3 === count( $token_info ) && 'label' === $token_info[2];
	}

	/**
	 * Retrieves a specific WPForm form.
	 *
	 * @param int $form_id The ID of the form.
	 *
	 * @return array The decoded json value.
	 */
	private function get_wpforms_form( $form_id = 0 ) {

		static $forms = array();
		if ( ! empty( $forms[ $form_id ] ) ) {
			return $forms[ $form_id ];
		}

		global $wpdb;

		$forms[ $form_id ] = json_decode(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_content FROM $wpdb->posts WHERE id = %d",
					$form_id
				)
			),
			true
		);

		// Parse the form data for matching fields functionality.
		foreach ( $forms[ $form_id ]['fields'] as $field_id => $field ) {
			if ( ! empty( $field['choices'] ) ) {
				foreach ( $field['choices'] as $choice_id => $choice ) {
					// Handle smart tags in label.
					$smart_tags = wpforms()->get( 'smart_tags' );
					// Support Legacy Smart Tags version 1.6.7
					if ( ! is_a( $smart_tags, '\WPForms\SmartTags\SmartTags' ) ) {
						$smart_tags = wpforms()->smart_tags;
					}
					$label = $smart_tags->process( $choice['label'], $forms[ $form_id ] );

					// Normalize choice label.
					$forms[ $form_id ]['fields'][ $field_id ]['choices'][ $choice_id ]['label'] = $this->normalize_whitespace( $label );
					// Normalize choice value.
					$forms[ $form_id ]['fields'][ $field_id ]['choices'][ $choice_id ]['value'] = $this->normalize_whitespace( $choice['value'] );
				}
			}
		}

		return $forms[ $form_id ];
	}

	/**
	 * Retrieves the checkbox value ( index of choice ).
	 *
	 * @param mixed $value The value.
	 * @param array $field The current field config.
	 *
	 * @return string The selected index(es).
	 */
	private function get_non_dynamic_choice_index( $value, $field ) {

		// Skip if payment field.
		if ( strpos( $field['type'], 'payment' ) === 0 ) {
			return apply_filters( 'automator_wpforms_non_dynamic_choice_value', $value, $field );
		}

		$label_key        = isset( $field['show_values'] ) && absint( $field['show_values'] ) > 0 ? 'value' : 'label';
		$labels           = wp_list_pluck( $field['choices'], $label_key );
		$multiple         = isset( $field['multiple'] ) && absint( $field['multiple'] ) > 0 || $field['type'] === 'checkbox';
		$value_has_commas = strpos( $value, ',' ) !== false;

		// Multiple Choice and value contains commas.
		if ( $multiple && $value_has_commas ) {
			$selected = $this->get_non_dynamic_multiple_choice_index( $value, $labels );
		} else {
			// Single choice.
			$value    = $this->normalize_whitespace( $value );
			$selected = array_search( $value, $labels, true );
			$selected = false !== $selected ? $selected : '';
		}

		return apply_filters( 'automator_wpforms_non_dynamic_choice_value', $selected, $field );
	}

	/**
	 * Retrieves matched indexes of non dynamic multiple choice fields.
	 *
	 * @param mixed $value The value.
	 * @param array $labels - Choice Labels.
	 *
	 * @return string The selected index(es).
	 */
	private function get_non_dynamic_multiple_choice_index( $value, $labels ) {

		// Add a trailing comma to the value to avoid partial matches if label contains commas.
		$value    .= ',';
		$selected = array();

		// Loop through choices and search for label.
		foreach ( $labels as $key => $label ) {
			// Check if string is in value.
			$pos = strpos( $value, $label . ',' );
			if ( false !== $pos ) {
				// Store the choice and its position
				$selected[] = array(
					'index'    => $key,
					'position' => $pos,
				);
			}
		}

		if ( ! empty( $selected ) ) {
			// Sort the found choices by their positions in ascending order
			usort(
				$selected,
				function ( $a, $b ) {
					return $a['position'] - $b['position'];
				}
			);
			// Extract the sorted indexes
			$selected = array_column( $selected, 'index' );
		}

		return ! empty( $selected ) ? implode( ', ', $selected ) : '';
	}

	/**
	 * Retrieves the selected values for show values.
	 *
	 * @param string $value - The selected value index(es).
	 * @param array $field - The field configuration.
	 *
	 * @return string The selected values.
	 */
	private function populate_choice_index_show_values( $value, $field ) {

		if ( empty( $value ) ) {
			return '';
		}

		$indexes = array_map( 'intval', explode( ', ', $value ) );
		$value   = '';
		foreach ( $indexes as $index ) {
			if ( isset( $field['choices'][ $index ] ) ) {
				$value .= $field['choices'][ $index ]['value'] . ', ';
			}
		}

		return empty( $value ) ? '' : rtrim( $value, ', ' );
	}

	/**
	 * Handles non dynamic labels.
	 *
	 * @param array $field - The field configuration.
	 * @param string $value - The selected value.
	 *
	 * @return string The selections
	 */
	private function handle_non_dynamic_labels( $field, $value ) {

		// Get Selected Indexes.
		$indexes = $this->get_non_dynamic_choice_index( $value, $field );
		if ( empty( $indexes ) ) {
			return '';
		}
		// Explode and map to int.
		$indexes  = array_map( 'intval', explode( ', ', $indexes ) );
		$selected = array();
		foreach ( $indexes as $index ) {
			if ( isset( $field['choices'][ $index ] ) ) {
				$selected[] = $field['choices'][ $index ]['label'];
			}
		}

		return ! empty( $selected ) ? implode( ', ', $selected ) : '';
	}

	/**
	 * Handles payment field labels.
	 *
	 * @param array $field The field configuration.
	 * @param string $value The selected value.
	 *
	 * @return string The label.
	 */
	private function handle_payment_labels( $field, $value ) {

		$show_price_after_labels = isset( $field['show_price_after_labels'] ) ? (bool) $field['show_price_after_labels'] : false;
		$indexes                 = array_map( 'intval', explode( ',', $value ) );

		if ( empty( $indexes ) ) {
			return '';
		}

		$selected = array();
		foreach ( $indexes as $index ) {
			if ( isset( $field['choices'][ $index ] ) ) {
				$selected[] = $show_price_after_labels ? $field['choices'][ $index ]['label'] . ' - ' . wpforms_format_amount( $field['choices'][ $index ]['value'], true ) : $field['choices'][ $index ]['label'];
			}
		}

		return ! empty( $selected ) ? implode( ', ', $selected ) : '';
	}

	/**
	 * Handles dynamic select field.
	 *
	 * @param array $field The field configuration.
	 * @param string $entry_choice The selected value.
	 *
	 * @return string The selected value label.
	 */
	private function handle_dynamic_select_labels( $field, $entry_choice ) {

		$type = isset( $field['dynamic_choices'] ) ? $field['dynamic_choices'] : null;

		// Return blank string if not dynamic choices.
		if ( empty( $type ) ) {
			return '';
		}

		$choices = array_map( 'trim', explode( ',', $entry_choice ) );

		$labels = array();

		foreach ( $choices as $choice ) {

			// Handle post types.
			if ( 'post_type' === $type ) {
				$labels[] = get_the_title( $choice );
			}

			// Handle taxonomy.
			if ( 'taxonomy' === $type ) {
				$term     = get_term( $choice );
				$labels[] = isset( $term->name ) ? $term->name : '';
			}
		}

		return implode( ', ', $labels );
	}

	/**
	 * Check if field is available in pro only.
	 *
	 * @param string $field_type
	 *
	 * @return bool
	 */
	private function is_pro_field( $field_type ) {

		// REVIEW - Would be nice to grab these from a function.
		$pro_only = apply_filters(
			'automator_wpforms_pro_only_fields',
			array(
				'phone',
				'address',
				'date-time',
				'url',
				'file-upload',
				'password',
				'richtext',
				'layout',
				'pagebreak',
				'divider',
				'html',
				'content',
				'rating',
				'hidden',
				'captcha',
				'signature',
				'likert_scale',
				'net_promoter_score',
				'paypal-commerce',
				'square',
				'authorize_net',
			)
		);

		return in_array( $field_type, $pro_only, true );

	}

	/**
	 * Strip white space for single spaces.
	 */
	public function normalize_whitespace( $string ) {
		if ( is_string( $string ) && ! empty( $string ) ) {
			$string = preg_replace( '/\s+/', ' ', $string );
			$string = trim( $string );
		}
		return $string;
	}

}
