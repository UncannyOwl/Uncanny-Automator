<?php
namespace Uncanny_Automator\Resolver;

/**
 * Given a Recipe ID, Object ID, and Object Type (e.g 'trigger') resolve all the fields that are in the following:
 *
 * - The 'extra_options'. The options stored as 'extra_options' (e.g. options_callback)
 * - The 'options_group'. The options that are set via Traits set_options_group method, or via classic 'options_group'.
 * - The 'options'. The options that are set via Traits set_options method, or via classic 'options'.
 *
 * @since 4.12
 */
class Fields_Resolver {

	/**
	 * The type of the object where the fields are loaded.
	 *
	 * @var string $object_type E.g. 'trigger', 'action', 'closure'.
	 */
	protected $object_type = 'trigger';

	/**
	 * The object ID.
	 *
	 * @var int $object_id The Trigger ID, Action ID, or Closure ID.
	 */
	protected $object_id = 0;

	/**
	 * The Recipe ID.
	 *
	 * @var int $recipe_id The Recipe ID.
	 */
	protected $recipe_id = 0;

	/**
	 * Object post meta contains keys that we do not need.
	 *
	 * @var array<string> $ignored_meta_keys The meta keys to ignore.
	 */
	protected $ignored_meta_keys = array(
		'code',
		'integration',
		'uap_trigger_version',
		'add_action',
		'sentence',
		'sentence_human_readable',
		'integration_name',
		'sentence_human_readable_html',
		'can_log_in_new_user',
	);

	/**
	 * Whether to show relevant tokens or not.
	 */
	protected $show_relevant_tokens = false;

	/**
	 * Sets the $object_type property.
	 *
	 * @param string $object_type
	 * @return self
	 */
	public function set_object_type( $object_type = '' ) {
		$this->object_type = $object_type;
		return $this;
	}

	/**
	 * @return string
	 */
	public function get_object_type() {
		return $this->object_type;
	}

	/**
	 * Get the value of object_id
	 *
	 * @return int
	 */
	public function get_object_id() {
		return $this->object_id;
	}

	/**
	 * Set the value of object_id
	 *
	 * @param int $object_id
	 *
	 * @return self
	 */
	public function set_object_id( $object_id ) {
		$this->object_id = $object_id;

		return $this;
	}

	/**
	 * Get the value of
	 *
	 * @return int
	 */
	public function get_recipe_id() {
		return $this->recipe_id;
	}

	/**
	 * Set the value of recipe_id
	 *
	 * @param int $recipe_id
	 *
	 * @return self
	 */
	public function set_recipe_id( $recipe_id ) {
		$this->recipe_id = $recipe_id;

		return $this;
	}

	/**
	 * @param string $meta_key
	 *
	 * @return void
	 */
	public function add_ignored_meta_keys( $meta_key = '' ) {
		$this->ignored_meta_keys[] = $meta_key;
	}

	public function get_show_relevant_tokens() {
		return $this->show_relevant_tokens;
	}

	public function set_show_relevant_tokens( $bool = false ) {
		$this->show_relevant_tokens = (bool) $bool;
	}

	/**
	 * @return array<string>
	 */
	public function get_ignored_meta_keys() {
		return apply_filters( 'automator_field_logger_ignored_meta_keys', $this->ignored_meta_keys, $this );
	}

	/**
	 * @param array<array<string>> $trigger_meta
	 *
	 * @return array<string>
	 */
	private function flatten_post_meta_array( $trigger_meta = array() ) {

		// Flatten the post meta.
		$trigger_meta = array_map(
			function( $item = array() ) {
				return is_array( $item ) && isset( $item[0] ) ? $item[0] : '';
			},
			$trigger_meta
		);

		// Ignore uncessary meta keys.
		$trigger_meta = array_filter(
			$trigger_meta,
			function( $key ) {
				return ! in_array( $key, $this->get_ignored_meta_keys(), true );
			},
			ARRAY_FILTER_USE_KEY
		);

		return $trigger_meta;

	}

	/**
	 * Processes the option value.
	 *
	 * @param mixed[] $args
	 *
	 * @return mixed[]
	 */
	private function process_option_value( $args ) {

		$args = wp_parse_args(
			$args,
			array(
				'object_meta'       => null,
				'saved_field_code'  => null,
				'saved_field_value' => null,
				'integration_code'  => null,
				'object_code'       => null,
				'option_field'      => array(),
			)
		);

		// Handle empty JSON val.
		if ( '[]' === $args['saved_field_value'] ) {
			$args['saved_field_value'] = '';
		}

		$saved_field_value = $args['saved_field_value'];

		// Custom value handling (automator_custom_value).
		if ( 'automator_custom_value' === $saved_field_value ) {
			$saved_field_value = isset( $args['object_meta'][ $args['saved_field_code'] . '_custom' ] )
				? $args['object_meta'][ $args['saved_field_code'] . '_custom' ]
				: $saved_field_value;
		}

		$readable = isset( $args['object_meta'][ $args['saved_field_code'] . '_readable' ] )
			? $args['object_meta'][ $args['saved_field_code'] . '_readable' ] :
			''; // Defaults to null.

		$field = array(
			'field_code' => $args['option_field']['option_code'],
			'type'       => $args['option_field']['input_type'],
			'label'      => $args['option_field']['label'],
			'attributes' => array(),
			'value'      => array(
				'readable' => $readable,
				'raw'      => $saved_field_value,
			),
		);

		// Show the relevant tokens if object has it configured to true.
		if ( true === $this->get_show_relevant_tokens() ) {
			$field['relevant_tokens'] = isset( $args['option_field']['relevant_tokens'] )
				? $args['option_field']['relevant_tokens'] :
				null;
		}

		// Repeater fields handling.
		if ( 'repeater' === $field['type'] ) {

			// We'll use this to tag labels with option code later.
			$rep_fields = array();
			// The actual fields.
			$repeater_fields = $args['option_field']['fields'];

			foreach ( $repeater_fields as $rep_field ) {
				$rep_fields[ $rep_field['option_code'] ] = $rep_field['label'];
			}
			// Update the field value.
			$field['value']['readable'] = strtr( $readable, $rep_fields );
			$field['value']['raw']      = $saved_field_value; // Use the actual saved value.

		}

		if ( 'textarea' === $field['type'] ) {
			$field['attributes']['supports_html'] = isset( $args['option_field']['supports_tinymce'] )
				? $args['option_field']['supports_tinymce'] :
				false; // Handles textarea.
		}

		if ( 'action' === $this->get_object_type() ) {
			// Put the raw for now, we will parse it later in the actual log with the actual value that was saved.
			$field['value']['parsed'] = $saved_field_value;
		}

		// Handle repeater.
		if ( 'repeater' === $args['option_field']['input_type'] ) {

			// The final replace pairs to store.
			$repeater_field_replace_pairs = array();

			// Get the option codes.
			$repeater_field_option_codes = array_column( $args['option_field']['fields'], 'option_code' );

			// Get the labels.
			$labels = array_column( $args['option_field']['fields'], 'label' );

			// Store as key-pair values. E.g. 'GS_COLUMN_NAME' => 'Column'.
			$replace_pairs = array();
			foreach ( $repeater_field_option_codes as $key => $value ) {
				$replace_pairs[ $value ] = $labels[ $key ];
			}

			// Replace the keys.
			$repeater_fields = (array) json_decode( $field['value']['raw'], true );

			// The $repeater_field_array_index is a numeric variable.
			foreach ( $repeater_fields as $repeater_field_array_index => $repeater_field ) {
				foreach ( $repeater_field as $repeater_field_key => $repeater_field_value ) {
					// Replace the key.
					$replaced_key = strtr( $repeater_field_key, $replace_pairs );
					// Add them to repeater field's replace pairs.
					$repeater_field_replace_pairs[ $repeater_field_array_index ][ $replaced_key ] = $repeater_field_value;
				}
			}
			// Overwrite the value.
			$field['value']['raw'] = wp_json_encode( $repeater_field_replace_pairs );
		}

		return $field;

	}

	/**
	 * Processes the option field.
	 *
	 * @param mixed[] $options
	 * @param array<string> $object_meta
	 * @param string $integration_code
	 * @param string $object_code
	 *
	 * @return mixed[]
	 */
	private function process_option( $options = array(), $object_meta = array(), $integration_code = '', $object_code = '' ) {

		$fields = array();

		foreach ( $options as $option_field ) {

			foreach ( $object_meta as $saved_field_code => $saved_field_value ) {

				if ( is_array( $option_field )
				&& isset( $option_field['option_code'] )
				&& $option_field['option_code'] === $saved_field_code
				) {

					$fields[] = $this->process_option_value(
						array(
							'object_meta'       => $object_meta,
							'saved_field_code'  => $saved_field_code,
							'saved_field_value' => $saved_field_value,
							'integration_code'  => $integration_code,
							'object_code'       => $object_code,
							'option_field'      => $option_field,
						)
					);

				}
			}
		}

		return $fields;

	}

	/**
	 * @param array<array<array<string>>> $options_group
	 * @param array<string> $trigger_meta
	 * @param string $integration_code
	 * @param string $object_code
	 *
	 * @return mixed[] $fields
	 */
	private function process_option_group( $options_group = array(), $trigger_meta = array(), $integration_code = '', $object_code = '' ) {
		$fields = array();
		foreach ( $options_group as $option_fields ) {
			foreach ( $trigger_meta as $saved_field_code => $saved_field_value ) {
				foreach ( $option_fields as $option_field ) {
					if ( is_array( $option_field ) && isset( $option_field['option_code'] ) && $option_field['option_code'] === $saved_field_code ) {
						$fields[] = $this->process_option_value(
							array(
								'object_meta'       => $trigger_meta,
								'saved_field_code'  => $saved_field_code,
								'saved_field_value' => $saved_field_value,
								'integration_code'  => $integration_code,
								'object_code'       => $object_code,
								'option_field'      => $option_field,
							)
						);
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * Given the recipe id, object id, analyze all the fields and returns the options_group and options.
	 *
	 * Notes:
	 *
	 * - The method below is a bit complex. What it does is basically get all the values\
	 *   that was saved in the post_meta table as 'extra_options'. Then it gets the fields that\
	 *   are implemented by the developer in the integration. Anaylyses both and then intersects\
	 *   with the fields from the memory (integration specific), and the field values from the DB\
	 *   to get the field value during run.
	 *
	 * - This method resolve fields from 'extra_options', 'options', and 'options_group'
	 *
	 * @todo Simplify this method.
	 *
	 * @return mixed[] Returns an array with keys 'options' and 'options_group'.
	 */
	public function resolve_object_fields() {

		$options_fields       = array();
		$options_group_fields = array();

		$object_meta = get_post_meta( $this->get_object_id() );

		// The function get_post_meta return a mixed data.
		if ( ! is_array( $object_meta ) ) {
			$object_meta = array();
		}

		$object_meta_flattended = $this->flatten_post_meta_array( $object_meta );

		// Aggregate the options starting with the 'extra_options' from recipe meta.
		$options_aggregate = get_post_meta( $this->get_recipe_id(), 'extra_options', true );

		// The function get_post_meta return a mixed data.
		if ( ! is_array( $options_aggregate ) ) {
			$options_aggregate = array();
		}

		$object_code = isset( $object_meta['code'][0] ) ? $object_meta['code'][0] : '';

		$options_group = Automator()->get->object_field_options_from_object_code( $object_code, 'options_group', $this->get_object_type() );
		$options       = Automator()->get->object_field_options_from_object_code( $object_code, 'options', $this->get_object_type() );

		// Figure out if we have an options_group here.
		if ( is_array( $options_group ) && ! empty( $options_group ) ) {
			// Inject the options_group into the options_group aggregate array of a specific trigger code in a specific integration.
			$options_aggregate[ $options_group['integration'] ][ $options_group['trigger_code'] ]['options_group'] = $options_group['options_group'];
		}

		// Figure out if we have a simple options here. If we do, resolve.
		if ( is_array( $options ) && ! empty( $options ) ) {
			// Inject the options into the options aggregate array of a specific trigger code in a specific integration.
			$options_aggregate[ $options['integration'] ][ $options['trigger_code'] ]['options'] = $options['options'];
		}

		// Resolve extra options.
		foreach ( $options_aggregate as $integration_code => $option_code_options ) {
			foreach ( $option_code_options as $inner_object_code => $option ) {

				if ( $object_code !== $inner_object_code ) {
					continue; // Skip. Only process the Object's field.
				}

				// Process option code.
				if ( isset( $option['options_group'] ) ) {
					$options_group_processed = $this->process_option_group(
						$option['options_group'],
						$object_meta_flattended,
						$integration_code,
						$inner_object_code
					);
					if ( ! empty( $options_group_processed ) ) {
						$options_fields[] = $options_group_processed;
					}
				}

				if ( isset( $option['options'] ) ) {
					// Process simple options.
					$options_processed = $this->process_option(
						$option['options'],
						$object_meta_flattended,
						$integration_code,
						$inner_object_code
					);
					if ( ! empty( $options_processed ) ) {
						$options_fields[] = $options_processed;
					}
				}
			}
		}

		return array(
			'options'       => 1 === count( $options_fields ) ? array_shift( $options_fields ) : $options_fields, // / @phpstan-ignore-line PHP Stan issue: <https://github.com/phpstan/phpstan/issues/2889>
			'options_group' => 1 === count( $options_group_fields ) ? array_shift( $options_group_fields ) : $options_group_fields, // / @phpstan-ignore-line PHP Stan issue: <https://github.com/phpstan/phpstan/issues/2889>
		);

	}
}
