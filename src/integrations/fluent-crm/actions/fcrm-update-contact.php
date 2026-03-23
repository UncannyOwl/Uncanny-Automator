<?php

namespace Uncanny_Automator;

/**
 * Class FCRM_UPDATE_CONTACT
 *
 * @package Uncanny_Automator
 */
class FCRM_UPDATE_CONTACT {

	use Recipe\Actions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {

		$this->setup_action();
	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {
		$this->set_integration( 'FCRM' );
		$this->set_action_code( 'FCRMADDORUPDATECONTACT' );
		$this->set_action_meta( 'FCRMUSEREMAIL' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'integration/fluentcrm/' ) );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		// translators: %1$s: Contact email
		$this->set_sentence( sprintf( esc_html_x( 'Add or Update {{a contact:%1$s}}', 'FluentCRM', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Add or Update {{a contact}}', 'FluentCRM', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_action();
	}

	/**
	 * Options definitions.
	 *
	 * @return array
	 */
	public function load_options() {

		$options = array();

		// Email field
		$options[] = array(
			'option_code'     => $this->get_action_meta(),
			'label'           => esc_html_x( 'Email address', 'FluentCRM', 'uncanny-automator' ),
			'input_type'      => 'email',
			'required'        => true,
			'description'     => esc_html_x( 'Enter the email address of the contact. The contact will be updated if it exists, or created if it does not.', 'FluentCRM', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		// Repeater field for standard fields
		$options[] = array(
			'option_code'       => 'FCRM_FIELDS',
			'input_type'        => 'repeater',
			'relevant_tokens'   => array(),
			'label'             => esc_html_x( 'Fields', 'FluentCRM', 'uncanny-automator' ),
			'description'       => esc_html_x( 'Select standard contact fields to update', 'FluentCRM', 'uncanny-automator' ),
			'required'          => false,
			'add_row_button'    => esc_html_x( 'Add pair', 'FluentCRM', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove pair', 'FluentCRM', 'uncanny-automator' ),
			'hide_actions'      => true,
			'default_value'     => array(
				array(
					'FCRM_COLUMN_NAME'  => '',
					'FCRM_COLUMN_VALUE' => '',
					'FCRM_UPDATE_FIELD' => true,
				),
			),
			'fields'            => array(
				array(
					'option_code' => 'FCRM_COLUMN_NAME',
					'label'       => esc_html_x( 'Field', 'FluentCRM', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
					'read_only'   => true,
					'options'     => array(),
				),
				array(
					'option_code' => 'FCRM_COLUMN_VALUE',
					'label'       => esc_html_x( 'Value', 'FluentCRM', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
					'options'     => array(),
				),
				array(
					'option_code' => 'FCRM_UPDATE_FIELD',
					'label'       => esc_html_x( 'Update', 'FluentCRM', 'uncanny-automator' ),
					'input_type'  => 'checkbox',
					'is_toggle'   => true,
					'required'    => false,
					'default_value' => true,
					'description' => esc_html_x( 'Enable to update this field', 'FluentCRM', 'uncanny-automator' ),
				),
			),
			'ajax'              => array(
				'endpoint'       => 'automator_fetch_fluentcrm_fields',
				'event'          => 'on_load',
				'listen_fields'  => array(),
				'mapping_column' => 'FCRM_COLUMN_NAME',
			),
		);

		// Repeater field for custom fields
		$options[] = array(
			'option_code'       => 'FCRM_CUSTOM_FIELDS',
			'input_type'        => 'repeater',
			'relevant_tokens'   => array(),
			'label'             => esc_html_x( 'Custom fields', 'FluentCRM', 'uncanny-automator' ),
			'description'       => esc_html_x( 'Select custom fields to update', 'FluentCRM', 'uncanny-automator' ),
			'required'          => false,
			'add_row_button'    => esc_html_x( 'Add pair', 'FluentCRM', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove pair', 'FluentCRM', 'uncanny-automator' ),
			'hide_actions'      => true,
			'default_value'     => array(
				array(
					'FCRM_CUSTOM_COLUMN_NAME'  => '',
					'FCRM_CUSTOM_COLUMN_VALUE' => '',
					'FCRM_CUSTOM_UPDATE_FIELD' => true,
				),
			),
			'fields'            => array(
				array(
					'option_code' => 'FCRM_CUSTOM_COLUMN_NAME',
					'label'       => esc_html_x( 'Custom Field', 'FluentCRM', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
					'read_only'   => true,
					'options'     => array(),
				),
				array(
					'option_code' => 'FCRM_CUSTOM_COLUMN_VALUE',
					'label'       => esc_html_x( 'Value', 'FluentCRM', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
					'options'     => array(),
				),
				array(
					'option_code' => 'FCRM_CUSTOM_UPDATE_FIELD',
					'label'       => esc_html_x( 'Update', 'FluentCRM', 'uncanny-automator' ),
					'input_type'  => 'checkbox',
					'is_toggle'   => true,
					'required'    => false,
					'default_value' => true,
					'description' => esc_html_x( 'Enable to update this field', 'FluentCRM', 'uncanny-automator' ),
				),
			),
			'ajax'              => array(
				'endpoint'       => 'automator_fetch_fluentcrm_custom_fields',
				'event'          => 'on_load',
				'listen_fields'  => array(),
				'mapping_column' => 'FCRM_CUSTOM_COLUMN_NAME',
			),
		);

		// Lists field (separate from repeater)
		$options[] = Automator()->helpers->recipe->fluent_crm->options->fluent_crm_lists(
			esc_html_x( 'Lists', 'FluentCRM', 'uncanny-automator' ),
			'FCRMLIST',
			array(
				'supports_multiple_values' => true,
				'is_any'                   => false,
				'is_required'              => false,
				'supports_custom_value'    => true,
			)
		);

		// Tags field (separate from repeater)
		$options[] = Automator()->helpers->recipe->fluent_crm->options->fluent_crm_tags(
			esc_html_x( 'Tags', 'FluentCRM', 'uncanny-automator' ),
			'FCRMTAG',
			array(
				'supports_multiple_values' => true,
				'is_any'                   => false,
				'is_required'              => false,
				'supports_custom_value'    => true,
			)
		);

		// Status field for new contacts
		$status_options_new = Automator()->helpers->recipe->fluent_crm->get_subscriber_statuses( false, true );
		$options[]          = Automator()->helpers->recipe->field->select(
			array(
				'input_type'            => 'select',
				'option_code'           => 'FCRMSTATUS_NEW',
				'label'                 => esc_html_x( 'Status for new contacts', 'FluentCRM', 'uncanny-automator' ),
				'options'               => $status_options_new,
				'supports_custom_value' => true,
				'description'           => esc_html_x( 'Set the status when creating a new contact', 'FluentCRM', 'uncanny-automator' ),
			)
		);

		// Status field for existing contacts
		$status_options_existing           = Automator()->helpers->recipe->fluent_crm->get_subscriber_statuses( false, true );
		$status_options_existing['9999']   = esc_html_x( 'Do not update', 'FluentCRM', 'uncanny-automator' );
		$options[]                         = Automator()->helpers->recipe->field->select(
			array(
				'input_type'            => 'select',
				'option_code'           => 'FCRMSTATUS_EXISTING',
				'label'                 => esc_html_x( 'Status for existing contacts', 'FluentCRM', 'uncanny-automator' ),
				'options'               => $status_options_existing,
				'supports_custom_value' => true,
				'default_value'         => '9999',
				'description'           => esc_html_x( 'Set the status when updating an existing contact', 'FluentCRM', 'uncanny-automator' ),
			)
		);

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => $options,
				),
			)
		);
	}

	/**
	 * Process our action.
	 *
	 * @param int $user_id The user ID.
	 * @param array $action_data The action data.
	 * @param int $recipe_id The recipe ID.
	 * @param array $args The args.
	 * @param array $parsed The parsed variables.
	 *
	 * @return void
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! function_exists( 'FluentCrmApi' ) ) {
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			$message                             = esc_html_x( 'FluentCRM is not installed or activated.', 'FluentCRM', 'uncanny-automator' );
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $message );

			return;
		}

		$email = isset( $parsed[ $this->get_action_meta() ] )
			? sanitize_email( $parsed[ $this->get_action_meta() ] ) :
			null;

		if ( empty( $email ) || ! is_email( $email ) ) {
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			$message                             = esc_html_x( 'Valid email address is required.', 'FluentCRM', 'uncanny-automator' );
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $message );

			return;
		}

		// Check if contact already exists (needed to determine field update behavior)
		$existing_contact = FluentCrmApi( 'contacts' )->getContact( $email );
		$is_new_contact   = empty( $existing_contact );

		// Start with email - createOrUpdate will merge with existing data
		$data          = array();
		$data['email'] = $email;

		// Parse standard field values from repeater
		$field_values = json_decode( $action_data['meta']['FCRM_FIELDS'] ?? '[]' );

		// Parse custom field values from repeater
		$custom_field_values = json_decode( $action_data['meta']['FCRM_CUSTOM_FIELDS'] ?? '[]' );

		// Filter standard fields based on contact existence
		// For new contacts: include all fields regardless of toggle
		// For existing contacts: only include fields with update toggle enabled
		$fields_to_update = array();
		foreach ( $field_values as $field ) {
			if ( $is_new_contact || ( isset( $field->FCRM_UPDATE_FIELD ) && ! empty( $field->FCRM_UPDATE_FIELD ) ) ) {
				// Normalize field structure for processing
				$fields_to_update[] = (object) array(
					'FCRM_COLUMN_NAME'  => $field->FCRM_COLUMN_NAME ?? '',
					'FCRM_COLUMN_VALUE' => $field->FCRM_COLUMN_VALUE ?? '',
				);
			}
		}

		// Filter custom fields based on contact existence
		// For new contacts: include all fields regardless of toggle
		// For existing contacts: only include fields with update toggle enabled
		foreach ( $custom_field_values as $field ) {
			if ( $is_new_contact || ( isset( $field->FCRM_CUSTOM_UPDATE_FIELD ) && ! empty( $field->FCRM_CUSTOM_UPDATE_FIELD ) ) ) {
				// Normalize field structure for processing (use same structure as standard fields)
				$fields_to_update[] = (object) array(
					'FCRM_COLUMN_NAME'  => $field->FCRM_CUSTOM_COLUMN_NAME ?? '',
					'FCRM_COLUMN_VALUE' => $field->FCRM_CUSTOM_COLUMN_VALUE ?? '',
				);
			}
		}

		// Validate field values before processing
		try {
			$this->validate_field_values( $fields_to_update, $recipe_id, $user_id, $args );
		} catch ( \Exception $e ) {
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

			return;
		}

		// Process fields to update
		foreach ( $fields_to_update as $field_data ) {
			$column_name  = $field_data->FCRM_COLUMN_NAME ?? '';
			$column_value = Automator()->parse->text( $field_data->FCRM_COLUMN_VALUE ?? '', $recipe_id, $user_id, $args );

			if ( empty( $column_name ) ) {
				continue;
			}

			// Map field names to data keys
			$field_mapping = $this->get_field_mapping();

			// Extract field key from column name (format: "field_key - Label")
			$field_parts = explode( ' - ', $column_name );
			$field_key   = trim( reset( $field_parts ) );

			if ( isset( $field_mapping[ $field_key ] ) ) {
				$data_key = $field_mapping[ $field_key ];

				// Handle special field types
				if ( 'date_of_birth' === $data_key ) {
					// Allow empty values to clear the field
					$data[ $data_key ] = ! empty( $column_value ) ? $this->validate_date_value( $column_value ) : '';
				} else {
					// Allow empty values to clear the field
					$data[ $data_key ] = ! empty( $column_value ) ? sanitize_text_field( $column_value ) : '';
				}
			} else {
				// Custom field - use field_key as slug
				if ( ! empty( $field_key ) ) {
					// Initialize custom_values if not set
					if ( ! isset( $data['custom_values'] ) ) {
						$data['custom_values'] = array();
					}

					// Get custom field definition for validation
					$custom_field = $this->get_custom_field_by_slug( $field_key );

					if ( $custom_field ) {
						// Allow empty values to clear custom fields
						if ( empty( $column_value ) ) {
							$data['custom_values'][ $field_key ] = '';
						} else {
							$data['custom_values'][ $field_key ] = $this->format_custom_field_value( $column_value, $custom_field, $recipe_id, $user_id, $args );
						}
					} else {
						// Allow empty values to clear the field
						$data['custom_values'][ $field_key ] = ! empty( $column_value ) ? sanitize_text_field( $column_value ) : '';
					}
				}
			}
		}

		// Handle status based on whether contact is new or existing
		if ( $is_new_contact ) {
			// New contact - use FCRMSTATUS_NEW if provided
			if ( ! empty( $action_data['meta']['FCRMSTATUS_NEW'] ) ) {
				$data['status'] = Automator()->parse->text( $action_data['meta']['FCRMSTATUS_NEW'], $recipe_id, $user_id, $args );
			}
		} else {
			// Existing contact - use FCRMSTATUS_EXISTING if provided and not "Do not update"
			if ( ! empty( $action_data['meta']['FCRMSTATUS_EXISTING'] ) && '9999' !== $action_data['meta']['FCRMSTATUS_EXISTING'] ) {
				$data['status'] = Automator()->parse->text( $action_data['meta']['FCRMSTATUS_EXISTING'], $recipe_id, $user_id, $args );
			}
		}

		// Handle lists if provided
		if ( ! empty( $action_data['meta']['FCRMLIST'] ) ) {
			$data['lists'] = $this->validate_multiselect_value( $action_data['meta']['FCRMLIST'] );
		}

		// Handle tags if provided
		if ( ! empty( $action_data['meta']['FCRMTAG'] ) ) {
			$data['tags'] = $this->validate_multiselect_value( $action_data['meta']['FCRMTAG'] );
		}

		$data['query_timestamp'] = time();

		// First create or update the contact
		$contact = FluentCrmApi( 'contacts' )->createOrUpdate( $data, true );

		// If contact exists and we have empty strings to clear, update directly using the model
		// createOrUpdate with true might skip empty strings during merge, so we update directly
		if ( $contact && class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			$subscriber = \FluentCrm\App\Models\Subscriber::find( $contact->id );

			if ( $subscriber ) {
				$needs_save = false;

				// Update standard fields that are empty strings
				foreach ( $data as $key => $value ) {
					if ( in_array( $key, array( 'email', 'query_timestamp', 'lists', 'tags', 'custom_values' ), true ) ) {
						continue;
					}
					if ( '' === $value ) {
						$subscriber->$key = '';
						$needs_save       = true;
					}
				}

				// Update custom_values that are empty strings
				if ( isset( $data['custom_values'] ) && is_array( $data['custom_values'] ) ) {
					foreach ( $data['custom_values'] as $custom_key => $custom_value ) {
						if ( '' === $custom_value ) {
							// Get existing custom values or initialize as empty array
							$custom_values = $subscriber->custom_values;
							if ( ! is_array( $custom_values ) ) {
								$custom_values = array();
							}
							$custom_values[ $custom_key ] = '';
							$subscriber->custom_values    = $custom_values;
							$needs_save                   = true;
						}
					}
				}

				if ( $needs_save ) {
					$subscriber->save();
				}
			}
		}

		if ( ! $contact ) {
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			/* translators: Subscriber email */
			$message = sprintf( esc_html_x( 'We are not able to create or update a contact %s.', 'FluentCRM', 'uncanny-automator' ), $data['email'] );
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $message );

			return;
		}

		if ( 'pending' === $contact->status ) {
			$contact->sendDoubleOptinEmail();
		}

		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}

	/**
	 * Get field mapping for standard fields.
	 *
	 * @return array
	 */
	private function get_field_mapping() {
		return array(
			'first_name'      => 'first_name',
			'last_name'       => 'last_name',
			'phone'           => 'phone',
			'date_of_birth'   => 'date_of_birth',
			'address_line_1'  => 'address_line_1',
			'address_line_2'  => 'address_line_2',
			'city'            => 'city',
			'state'           => 'state',
			'postal_code'     => 'postal_code',
			'country'         => 'country',
		);
	}

	/**
	 * Get custom field by slug.
	 *
	 * @param string $slug The field slug.
	 * @return array|null The custom field definition or null.
	 */
	private function get_custom_field_by_slug( $slug ) {
		if ( ! function_exists( 'fluentcrm_get_custom_contact_fields' ) ) {
			return null;
		}

		$custom_fields = fluentcrm_get_custom_contact_fields();

		if ( empty( $custom_fields ) ) {
			return null;
		}

		foreach ( $custom_fields as $custom_field ) {
			if ( isset( $custom_field['slug'] ) && $custom_field['slug'] === $slug ) {
				return $custom_field;
			}
		}

		return null;
	}

	/**
	 * Validate field values based on their field types.
	 *
	 * @param array  $fields_to_update Array of field objects to update.
	 * @param int    $recipe_id The recipe ID.
	 * @param int    $user_id The user ID.
	 * @param array  $args The args.
	 *
	 * @return void
	 * @throws \Exception If validation fails.
	 */
	private function validate_field_values( $fields_to_update, $recipe_id, $user_id, $args ) {
		$field_mapping = $this->get_field_mapping();

		foreach ( $fields_to_update as $field_data ) {
			$column_name  = $field_data->FCRM_COLUMN_NAME ?? '';
			$column_value = Automator()->parse->text( $field_data->FCRM_COLUMN_VALUE ?? '', $recipe_id, $user_id, $args );

			if ( empty( $column_name ) ) {
				continue;
			}

			// Skip validation if value is empty (unless field is required)
			if ( empty( $column_value ) ) {
				continue;
			}

			// Extract field key from column name (format: "field_key - Label")
			$field_parts = explode( ' - ', $column_name );
			$field_key   = trim( reset( $field_parts ) );

			// Validate standard fields
			if ( isset( $field_mapping[ $field_key ] ) ) {
				$data_key = $field_mapping[ $field_key ];

				switch ( $data_key ) {
					case 'date_of_birth':
						$date = strtotime( $column_value );
						if ( false === $date ) {
							throw new \Exception(
								sprintf(
									// translators: %1$s: field label
									esc_html_x( 'Invalid date format for field: %1$s', 'FluentCRM', 'uncanny-automator' ),
									$column_name
								)
							);
						}
						break;
				}
			} else {
				// Validate custom fields
				$custom_field = $this->get_custom_field_by_slug( $field_key );

				if ( $custom_field ) {
					$field_type = $custom_field['type'] ?? 'text';

					switch ( $field_type ) {
						case 'date':
						case 'date_time':
							$date = strtotime( $column_value );
							if ( false === $date ) {
								$field_label = $custom_field['label'] ?? $field_key;
								throw new \Exception(
									sprintf(
										// translators: %1$s: field label
										esc_html_x( 'Invalid date format for field: %1$s', 'FluentCRM', 'uncanny-automator' ),
										$field_label
									)
								);
							}
							break;

						case 'number':
							if ( ! is_numeric( $column_value ) ) {
								$field_label = $custom_field['label'] ?? $field_key;
								throw new \Exception(
									sprintf(
										// translators: %1$s: field label
										esc_html_x( 'Invalid number for field: %1$s', 'FluentCRM', 'uncanny-automator' ),
										$field_label
									)
								);
							}
							break;

						case 'select-one':
						case 'radio':
							// Validate against available choices
							if ( ! empty( $custom_field['options'] ) && is_array( $custom_field['options'] ) ) {
								if ( ! in_array( $column_value, $custom_field['options'], true ) ) {
									$field_label = $custom_field['label'] ?? $field_key;
									throw new \Exception(
										sprintf(
											// translators: %1$s: field label, %2$s: invalid value
											esc_html_x( 'Invalid value for field %1$s: %2$s', 'FluentCRM', 'uncanny-automator' ),
											$field_label,
											$column_value
										)
									);
								}
							}
							break;

						case 'select-multi':
							// Validate against available choices
							if ( ! empty( $custom_field['options'] ) && is_array( $custom_field['options'] ) ) {
								$values         = array_map( 'trim', explode( ',', $column_value ) );
								$invalid_values = array();

								foreach ( $values as $value ) {
									if ( ! in_array( $value, $custom_field['options'], true ) ) {
										$invalid_values[] = $value;
									}
								}

								if ( ! empty( $invalid_values ) ) {
									$field_label = $custom_field['label'] ?? $field_key;
									throw new \Exception(
										sprintf(
											// translators: %1$s: field label, %2$s: invalid values
											esc_html_x( 'Invalid value(s) for field %1$s: %2$s', 'FluentCRM', 'uncanny-automator' ),
											$field_label,
											implode( ', ', $invalid_values )
										)
									);
								}
							}
							break;
					}
				}
			}
		}
	}

	/**
	 * Format custom field value based on field type.
	 *
	 * @param string $value The field value.
	 * @param array $custom_field The custom field definition.
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id The user ID.
	 * @param array $args The args.
	 * @return mixed The formatted value.
	 */
	private function format_custom_field_value( $value, $custom_field, $recipe_id, $user_id, $args ) {
		$parsed_value = Automator()->parse->text( $value, $recipe_id, $user_id, $args );
		$field_type   = $custom_field['type'] ?? 'text';

		switch ( $field_type ) {
			case 'checkbox':
				// Checkbox values are arrays
				$checkbox_val = array();
				if ( ! empty( $parsed_value ) ) {
					// Split by comma if multiple values
					$values = array_map( 'trim', explode( ',', $parsed_value ) );
					// Validate against available options
					if ( ! empty( $custom_field['options'] ) ) {
						foreach ( $values as $val ) {
							if ( in_array( $val, $custom_field['options'], true ) ) {
								$checkbox_val[] = $val;
							}
						}
					} else {
						$checkbox_val = $values;
					}
				}
				return $checkbox_val;

			case 'select-multi':
				return $this->validate_multiselect_value( $parsed_value, true );

			case 'date':
				return $this->validate_date_value( $parsed_value );

			case 'date_time':
				return $this->validate_date_value( $parsed_value, 'Y-m-d H:i:s' );

			case 'number':
				return is_numeric( $parsed_value ) ? (float) $parsed_value : '';

			default:
				return sanitize_text_field( $parsed_value );
		}
	}

	/**
	 * Validate and format date values.
	 *
	 * @param string $value The date value to validate.
	 * @param string $format The expected format (optional, defaults to 'Y-m-d').
	 *
	 * @return string The formatted date or empty string if invalid.
	 */
	private function validate_date_value( $value, $format = 'Y-m-d' ) {
		if ( empty( $value ) ) {
			return $value;
		}

		// Try to parse the date using the expected format
		$date = \DateTime::createFromFormat( $format, $value );

		if ( $date && $date->format( $format ) === $value ) {
			return $value; // Date is already in correct format
		}

		// Try to parse using strtotime for more flexible date parsing
		$timestamp = strtotime( $value );
		if ( false !== $timestamp ) {
			return gmdate( $format, $timestamp );
		}

		// If we can't parse the date, return empty string to indicate invalid format
		return '';
	}

	/**
	 * Validate and format multiselect values.
	 *
	 * @param mixed $value The multiselect value.
	 * @param bool  $is_custom_field Whether the value is from a custom field.
	 *
	 * @return array The formatted array of values.
	 */
	private function validate_multiselect_value( $value, $is_custom_field = false ) {
		// If value is already an array, return it
		if ( is_array( $value ) ) {
			return $is_custom_field ? $value : array_map( 'intval', $value );
		}

		// If value is a JSON string, decode it
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $is_custom_field ? $decoded : array_map( 'intval', $decoded );
			}
		}

		// If value is a single value (custom value), convert to array
		if ( ! empty( $value ) ) {
			return array( $is_custom_field ? $value : intval( $value ) );
		}

		// Return empty array if no valid value
		return array();
	}
}
